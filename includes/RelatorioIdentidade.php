<?php
/**
 * includes/RelatorioIdentidade.php
 *
 * Identidade da barbearia usada no cabeçalho dos relatórios em PDF: nome,
 * contatos e LOGO. A fonte é a mesma da vitrine pública (Catálogo >
 * Configurar → tabela CatalogoConfig, id = 1). O sistema atende uma
 * barbearia por instalação/banco de dados, então não existe logo "de outro
 * estabelecimento" para vazar: a logo sempre é a do banco que gera o relatório.
 *
 * Como a logo é carregada (nesta ordem, sem nunca derrubar a emissão):
 *   1. nome do arquivo em CatalogoConfig.logo (validado: logo_<hex>.jpg|png|webp);
 *   2. arquivo em assets/uploads/catalogo/ (somente leitura);
 *   3. se o arquivo sumiu (novo deploy), a cópia guardada no banco
 *      (ArquivoUpload.dados) — lida direto do banco, sem gravar nada em disco,
 *      para rodar igual no PHP-FPM (usuário nobody) e na rotina diária (CLI/root);
 *   4. a imagem é decodificada com GD, achatada sobre fundo branco (o FPDF não
 *      lida bem com transparência/WebP), reduzida (máx. 600 px — mais que
 *      isso só engorda o PDF) e salva como PNG temporário, preservando a
 *      proporção original. Quem usa o arquivo deve chamar liberar().
 * Sem logo (ou qualquer falha) devolve null e o cabeçalho usa um monograma.
 */
final class RelatorioIdentidade
{
    private const MAX_PIXELS = 24000000;
    private const LADO_MAX   = 600;

    /**
     * @return array{nome:string, endereco:string, contato:string, logo:?array{arquivo:string,largura:int,altura:int}}
     */
    public static function carregar(PDO $pdo): array
    {
        require_once __DIR__ . '/CatalogoService.php';

        $cfg = [];
        try {
            $cfg = CatalogoService::config($pdo);
        } catch (Throwable $e) {
            error_log('RelatorioIdentidade: config indisponível: ' . $e->getMessage());
        }

        $nome = trim((string) ($cfg['nome_exibicao'] ?? ''));
        $contato = [];
        $fone = trim((string) ($cfg['telefone'] ?? ''));
        $zap  = trim((string) ($cfg['whatsapp'] ?? ''));
        if ($fone !== '') {
            $contato[] = 'Tel.: ' . $fone;
        }
        if ($zap !== '' && $zap !== $fone) {
            $contato[] = 'WhatsApp: ' . $zap;
        }

        return [
            'nome'     => $nome !== '' ? $nome : 'BarbERP',
            'endereco' => trim((string) ($cfg['endereco'] ?? '')),
            'contato'  => implode('  |  ', $contato),
            'logo'     => self::logoParaPdf($pdo, (string) ($cfg['logo'] ?? '')),
        ];
    }

    /** Remove o PNG temporário criado por carregar(). */
    public static function liberar(array $identidade): void
    {
        $arquivo = $identidade['logo']['arquivo'] ?? null;
        if (is_string($arquivo) && $arquivo !== '' && is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    /** @return ?array{arquivo:string,largura:int,altura:int} */
    public static function logoParaPdf(PDO $pdo, string $nomeLogo): ?array
    {
        try {
            if (!CatalogoService::nomeImagemValido($nomeLogo) || strncmp($nomeLogo, 'logo_', 5) !== 0) {
                return null;
            }
            $bytes = self::bytesDaLogo($pdo, $nomeLogo);
            if ($bytes === null) {
                return null;
            }
            return self::prepararPng($bytes);
        } catch (Throwable $e) {
            error_log('RelatorioIdentidade: logo ignorada: ' . $e->getMessage());
            return null;
        }
    }

    private static function bytesDaLogo(PDO $pdo, string $nome): ?string
    {
        $disco = __DIR__ . '/../assets/uploads/catalogo/' . $nome;
        if (is_file($disco) && is_readable($disco)) {
            $dados = @file_get_contents($disco);
            if (is_string($dados) && $dados !== '') {
                return $dados;
            }
        }
        try {
            $stmt = $pdo->prepare('SELECT dados FROM ArquivoUpload WHERE caminho = :c');
            $stmt->execute(['c' => 'catalogo/' . $nome]);
            $dados = $stmt->fetchColumn();
            if (is_string($dados) && $dados !== '') {
                return $dados;
            }
        } catch (Throwable $e) {
            // tabela ainda não existe: sem cópia no banco
        }
        return null;
    }

    /** @return ?array{arquivo:string,largura:int,altura:int} */
    private static function prepararPng(string $bytes): ?array
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        $origem = @imagecreatefromstring($bytes);
        if ($origem === false) {
            return null;
        }
        $w = imagesx($origem);
        $h = imagesy($origem);
        $escala = min(1.0, self::LADO_MAX / max($w, $h));
        $nw = max(1, (int) round($w * $escala));
        $nh = max(1, (int) round($h * $escala));

        $destino = imagecreatetruecolor($nw, $nh);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled($destino, $origem, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($origem);

        $tmp = tempnam(sys_get_temp_dir(), 'barberp_logo_');
        if ($tmp === false) {
            imagedestroy($destino);
            return null;
        }
        $png = $tmp . '.png';
        @rename($tmp, $png);
        $ok = @imagepng($destino, $png, 6);
        imagedestroy($destino);
        if (!$ok || !is_file($png) || filesize($png) === 0) {
            @unlink($png);
            return null;
        }

        return ['arquivo' => $png, 'largura' => $nw, 'altura' => $nh];
    }
}
