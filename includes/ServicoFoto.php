<?php
// includes/ServicoFoto.php
// Foto opcional dos serviços (ex.: foto de um degradê). O arquivo fica em
// assets/uploads/servicos/ com nome aleatório; o banco guarda somente o
// nome do arquivo (coluna Servico.foto).

require_once __DIR__ . '/ImagemPersistente.php';

final class ServicoFoto
{
    private const MAX_BYTES = 3 * 1024 * 1024; // 3MB
    private const TIPOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Mensagens para os códigos de erro devolvidos por processarUpload(). */
    public const MENSAGENS_ERRO = [
        'envio'     => 'A foto não foi enviada corretamente. Tente novamente.',
        'tamanho'   => 'A foto deve ter no máximo 3MB.',
        'formato'   => 'Formato de foto inválido. Envie uma imagem JPG, PNG ou WEBP.',
        'permissao' => 'O servidor não conseguiu salvar a foto (pasta sem permissão). Avise o suporte.',
        'salvar'    => 'Não foi possível salvar a foto no servidor.',
    ];

    public static function pasta(): string
    {
        return __DIR__ . '/../assets/uploads/servicos/';
    }

    /** URL pública (caminho absoluto) da foto, ou null se não houver arquivo. */
    public static function url(?string $nome): ?string
    {
        if (!self::nomeValido($nome) || !ImagemPersistente::garantir('servicos/' . $nome)) {
            return null;
        }
        return '/assets/uploads/servicos/' . rawurlencode($nome);
    }

    /**
     * Informa se a coluna Servico.foto existe. Se ainda não existir, tenta
     * criá-la uma única vez (ALTER TABLE); se o usuário do banco não tiver
     * permissão, devolve false e o sistema segue funcionando sem fotos até
     * o script scriptBD/atualizacao_servico_foto.sql ser executado.
     */
    public static function colunaExiste(PDO $pdo): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $existe = $pdo->query("SHOW COLUMNS FROM Servico LIKE 'foto'")->fetch() !== false;
            if (!$existe) {
                $pdo->exec('ALTER TABLE Servico ADD COLUMN foto VARCHAR(255) NULL');
                $existe = true;
            }
        } catch (Throwable $e) {
            error_log('ServicoFoto: coluna Servico.foto indisponível: ' . $e->getMessage());
            $existe = false;
        }

        return $cache = $existe;
    }

    /**
     * Valida e salva o arquivo enviado em $_FILES['foto'].
     *
     * @return array{ok:bool, nome?:string, erro?:string}  erro = código de MENSAGENS_ERRO
     */
    public static function processarUpload(array $arquivo): array
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $codigo = $arquivo['error'] ?? 0;
            $tamanho = $codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE;
            return ['ok' => false, 'erro' => $tamanho ? 'tamanho' : 'envio'];
        }

        if ($arquivo['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'erro' => 'tamanho'];
        }

        $mime = mime_content_type($arquivo['tmp_name']);
        if (!isset(self::TIPOS[$mime]) || @getimagesize($arquivo['tmp_name']) === false) {
            return ['ok' => false, 'erro' => 'formato'];
        }

        $pasta = self::pasta();
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0755, true);
        }
        if (!is_dir($pasta) || !is_writable($pasta)) {
            error_log('ServicoFoto: pasta sem permissão de escrita: ' . $pasta);
            return ['ok' => false, 'erro' => 'permissao'];
        }

        $nome = 'servico_' . bin2hex(random_bytes(8)) . '.' . self::TIPOS[$mime];
        if (!move_uploaded_file($arquivo['tmp_name'], $pasta . $nome)) {
            return ['ok' => false, 'erro' => 'salvar'];
        }

        // Cópia no banco: a pasta de uploads some a cada redeploy (ver ImagemPersistente).
        ImagemPersistente::guardar('servicos/' . $nome);

        return ['ok' => true, 'nome' => $nome];
    }

    /** Apaga o arquivo da foto (ignora nomes inesperados e arquivos inexistentes). */
    public static function remover(?string $nome): void
    {
        if (self::nomeValido($nome)) {
            if (is_file(self::pasta() . $nome)) {
                @unlink(self::pasta() . $nome);
            }
            ImagemPersistente::remover('servicos/' . $nome);
        }
    }

    private static function nomeValido(?string $nome): bool
    {
        return is_string($nome) && preg_match('/^servico_[a-f0-9]+\.(jpg|png|webp)$/', $nome) === 1;
    }
}
