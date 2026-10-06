<?php
/**
 * includes/ImagemPersistente.php
 *
 * Faz as imagens enviadas (foto de perfil, foto de serviço, logo/capa do
 * catálogo) sobreviverem a um redeploy.
 *
 * Problema: no EasyPanel/Nixpacks cada deploy cria um container novo, e a
 * pasta assets/uploads/ vive dentro dele — sem um volume montado, todos os
 * arquivos enviados somem. Aqui cada imagem enviada ganha também uma CÓPIA
 * no banco de dados (tabela ArquivoUpload, que já é persistente) e, no
 * primeiro acesso depois de cada deploy, as imagens que faltam no disco são
 * recriadas a partir do banco. O resto do sistema continua usando arquivos
 * em disco normalmente (nginx serve direto, is_file() etc.).
 *
 * Observação: com um volume em /app/assets/uploads isso continua funcionando
 * (as cópias do banco só servem de reserva).
 *
 * Caminhos sempre relativos a assets/uploads/, ex.: "perfil/barbeiro_1_ab.png".
 */

final class ImagemPersistente
{
    private const PASTAS = ['perfil', 'servicos', 'catalogo'];
    private const TIPOS  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    private static ?PDO $pdo = null;
    private static bool $tabelaOk = false;

    public static function raiz(): string
    {
        return __DIR__ . '/../assets/uploads/';
    }

    /** Chamado uma vez por requisição por config/config.php. Nunca derruba a página. */
    public static function iniciar(PDO $pdo): void
    {
        self::$pdo = $pdo;
        try {
            self::restaurarSeNecessario();
        } catch (Throwable $e) {
            error_log('ImagemPersistente: ' . $e->getMessage());
        }
    }

    /** Guarda (ou atualiza) no banco a cópia de um arquivo que acabou de ser gravado em disco. */
    public static function guardar(string $rel): void
    {
        try {
            if (!self::caminhoValido($rel) || self::$pdo === null) {
                return;
            }
            $arquivo = self::raiz() . $rel;
            if (!is_file($arquivo)) {
                return;
            }
            $dados = file_get_contents($arquivo);
            if ($dados === false || $dados === '') {
                return;
            }
            self::garantirTabela();
            $stmt = self::$pdo->prepare(
                'INSERT INTO ArquivoUpload (caminho, mime, tamanho, dados) VALUES (:c, :m, :t, :d)
                 ON DUPLICATE KEY UPDATE mime = VALUES(mime), tamanho = VALUES(tamanho), dados = VALUES(dados)'
            );
            $stmt->bindValue(':c', $rel);
            $stmt->bindValue(':m', self::mimeDe($rel));
            $stmt->bindValue(':t', strlen($dados), PDO::PARAM_INT);
            $stmt->bindValue(':d', $dados, PDO::PARAM_LOB);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('ImagemPersistente::guardar: ' . $e->getMessage());
        }
    }

    /** Remove a cópia do banco (chamar junto com o unlink do arquivo). */
    public static function remover(string $rel): void
    {
        try {
            if (!self::caminhoValido($rel) || self::$pdo === null) {
                return;
            }
            self::garantirTabela();
            self::$pdo->prepare('DELETE FROM ArquivoUpload WHERE caminho = :c')->execute(['c' => $rel]);
        } catch (Throwable $e) {
            error_log('ImagemPersistente::remover: ' . $e->getMessage());
        }
    }

    /**
     * Primeira requisição depois de um deploy (o marcador fica em /tmp do
     * container, que é novo a cada deploy): recria no disco as imagens que
     * existem no banco e não existem mais na pasta, e guarda no banco as que
     * estão só no disco (ex.: enviadas antes desta proteção existir).
     */
    private static function restaurarSeNecessario(): void
    {
        $marcador = sys_get_temp_dir() . '/barberp_uploads_sincronizados';
        if (is_file($marcador)) {
            return;
        }

        $trava = @fopen($marcador . '.lock', 'c');
        if ($trava === false || !flock($trava, LOCK_EX)) {
            return;
        }
        if (is_file($marcador)) { // outro processo terminou enquanto esperávamos
            flock($trava, LOCK_UN);
            return;
        }

        try {
            self::garantirTabela();

            // 1) banco -> disco
            $caminhos = self::$pdo->query('SELECT caminho FROM ArquivoUpload')->fetchAll(PDO::FETCH_COLUMN);
            $stmt = self::$pdo->prepare('SELECT dados FROM ArquivoUpload WHERE caminho = :c');
            foreach ($caminhos as $rel) {
                if (!self::caminhoValido($rel)) {
                    continue;
                }
                $destino = self::raiz() . $rel;
                if (is_file($destino)) {
                    continue;
                }
                $pasta = dirname($destino);
                if (!is_dir($pasta)) {
                    @mkdir($pasta, 0775, true);
                }
                $stmt->execute(['c' => $rel]);
                $dados = $stmt->fetchColumn();
                if ($dados !== false && $dados !== null && $dados !== '') {
                    @file_put_contents($destino, $dados);
                    @chmod($destino, 0664);
                }
            }

            // 2) disco -> banco (o que ainda não tem cópia)
            $conhecidos = array_flip($caminhos);
            foreach (self::PASTAS as $pasta) {
                $dir = self::raiz() . $pasta . '/';
                if (!is_dir($dir)) {
                    continue;
                }
                foreach (scandir($dir) ?: [] as $nome) {
                    $rel = $pasta . '/' . $nome;
                    if (!isset($conhecidos[$rel]) && self::caminhoValido($rel) && is_file($dir . $nome)) {
                        self::guardar($rel);
                    }
                }
            }

            @touch($marcador);
        } finally {
            flock($trava, LOCK_UN);
            fclose($trava);
        }
    }

    private static function garantirTabela(): void
    {
        if (self::$tabelaOk) {
            return;
        }
        self::$pdo->exec(
            'CREATE TABLE IF NOT EXISTS ArquivoUpload (
                caminho VARCHAR(190) NOT NULL PRIMARY KEY,
                mime VARCHAR(60) NOT NULL,
                tamanho INT UNSIGNED NOT NULL DEFAULT 0,
                dados MEDIUMBLOB NOT NULL,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::$tabelaOk = true;
    }

    /** Só aceita "pasta/nome.ext" com pasta conhecida e nome simples (sem ../). */
    private static function caminhoValido(string $rel): bool
    {
        return preg_match('#^(' . implode('|', self::PASTAS) . ')/[A-Za-z0-9_][A-Za-z0-9_.-]{0,120}\.(jpg|jpeg|png|webp)$#', $rel) === 1;
    }

    private static function mimeDe(string $rel): string
    {
        return self::TIPOS[strtolower(pathinfo($rel, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
