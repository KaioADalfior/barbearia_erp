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

    /**
     * Guarda (ou atualiza) no banco a cópia de um arquivo que acabou de ser
     * gravado em disco. Devolve true só se a cópia foi conferida no banco
     * (mesmo tamanho). Qualquer falha é registrada (log + Discord) em vez de
     * ficar em silêncio — sem a cópia, a imagem some no próximo deploy.
     */
    public static function guardar(string $rel): bool
    {
        $emulacao = null;
        try {
            if (!self::caminhoValido($rel) || self::$pdo === null) {
                return false;
            }
            $arquivo = self::raiz() . $rel;
            if (!is_file($arquivo)) {
                return false;
            }
            $dados = file_get_contents($arquivo);
            if ($dados === false || $dados === '') {
                return false;
            }
            self::garantirTabela();

            // Prepared statement NATIVO: os bytes da imagem vão como binário,
            // sem passar por escape/charset do texto (com emulação, alguns
            // servidores MySQL rejeitam ou alteram bytes que não são UTF-8 válido).
            $emulacao = self::$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
            self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $stmt = self::$pdo->prepare(
                'INSERT INTO ArquivoUpload (caminho, mime, tamanho, dados) VALUES (:c, :m, :t, :d)
                 ON DUPLICATE KEY UPDATE mime = VALUES(mime), tamanho = VALUES(tamanho), dados = VALUES(dados)'
            );
            $stmt->bindValue(':c', $rel);
            $stmt->bindValue(':m', self::mimeDe($rel));
            $stmt->bindValue(':t', strlen($dados), PDO::PARAM_INT);
            $stmt->bindValue(':d', $dados, PDO::PARAM_LOB);
            $stmt->execute();

            $conf = self::$pdo->prepare('SELECT LENGTH(dados) FROM ArquivoUpload WHERE caminho = :c');
            $conf->execute(['c' => $rel]);
            if ((int) $conf->fetchColumn() !== strlen($dados)) {
                throw new RuntimeException('cópia no banco com tamanho diferente do arquivo (' . $rel . ')');
            }
            return true;
        } catch (Throwable $e) {
            self::registrarFalha('guardar ' . $rel, $e);
            return false;
        } finally {
            if ($emulacao !== null) {
                try { self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulacao); } catch (Throwable $e) {}
            }
        }
    }

    /**
     * Garante que o arquivo exista em disco: se faltar, recria a partir do
     * banco (só esse arquivo). Use no lugar de is_file() onde uma imagem
     * enviada é exibida — assim ela aparece mesmo que a restauração geral
     * de depois do deploy não tenha rodado ou tenha falhado.
     */
    public static function garantir(string $rel): bool
    {
        $destino = self::raiz() . $rel;
        if (is_file($destino)) {
            return true;
        }
        try {
            if (!self::caminhoValido($rel) || self::$pdo === null) {
                return false;
            }
            self::garantirTabela();
            $stmt = self::$pdo->prepare('SELECT dados FROM ArquivoUpload WHERE caminho = :c');
            $stmt->execute(['c' => $rel]);
            $dados = $stmt->fetchColumn();
            if ($dados === false || $dados === null || $dados === '') {
                return false;
            }
            $pasta = dirname($destino);
            if (!is_dir($pasta)) {
                @mkdir($pasta, 0775, true);
            }
            if (@file_put_contents($destino, $dados) === false) {
                self::registrarFalha('restaurar ' . $rel, new RuntimeException('sem permissão para gravar em ' . $pasta));
                return false;
            }
            @chmod($destino, 0664);
            return is_file($destino);
        } catch (Throwable $e) {
            self::registrarFalha('garantir ' . $rel, $e);
            return false;
        }
    }

    /**
     * Entrega a imagem direto do banco (usado por Uploads/scripts/imagem_servir.php
     * quando o arquivo não está em disco). Também recria o arquivo no disco.
     */
    public static function enviarDoBanco(string $rel): bool
    {
        try {
            if (!self::caminhoValido($rel) || self::$pdo === null) {
                return false;
            }
            self::garantirTabela();
            $stmt = self::$pdo->prepare('SELECT mime, dados FROM ArquivoUpload WHERE caminho = :c');
            $stmt->execute(['c' => $rel]);
            $linha = $stmt->fetch();
            if (!$linha || $linha['dados'] === null || $linha['dados'] === '') {
                return false;
            }
            self::garantir($rel);
            header('Content-Type: ' . $linha['mime']);
            header('Content-Length: ' . strlen($linha['dados']));
            header('Cache-Control: public, max-age=3600');
            header('X-Content-Type-Options: nosniff');
            echo $linha['dados'];
            return true;
        } catch (Throwable $e) {
            self::registrarFalha('enviarDoBanco ' . $rel, $e);
            return false;
        }
    }

    private static function registrarFalha(string $onde, Throwable $e): void
    {
        error_log('ImagemPersistente::' . $onde . ': ' . $e->getMessage());
        try {
            if (class_exists('DiscordLogger')) {
                DiscordLogger::erro('🖼️ Imagem sem cópia permanente no banco (' . $onde . ')', $e);
            }
        } catch (Throwable $ignorado) {
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
        // Se a última tentativa falhou, espera um pouco antes de tentar de novo.
        $retry = $marcador . '.retry';
        if (is_file($retry) && (time() - (int) @filemtime($retry)) < 60) {
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

        $falhou = false;
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
                    if (@file_put_contents($destino, $dados) === false) {
                        $falhou = true;
                        error_log('ImagemPersistente: não consegui gravar ' . $destino);
                    } else {
                        @chmod($destino, 0664);
                    }
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
                        if (!self::guardar($rel)) {
                            $falhou = true;
                        }
                    }
                }
            }

            // Só marca como sincronizado se deu tudo certo; senão tenta de novo na próxima requisição.
            if (!$falhou) {
                @touch($marcador);
            } else {
                @touch($retry);
            }
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
