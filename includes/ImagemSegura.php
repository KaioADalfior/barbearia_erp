<?php
/**
 * includes/ImagemSegura.php
 *
 * Grava uma imagem enviada (foto de perfil, de serviço, logo/capa) de forma
 * segura. Um arquivo que "parece" JPEG/PNG para getimagesize()/mime_content_type()
 * pode carregar código PHP escondido (comentário, EXIF, bloco depois do fim
 * da imagem). Se o servidor web executar esse arquivo como PHP, vira
 * execução remota de código. Por isso a imagem nunca é gravada como veio:
 *
 *   1) com GD disponível, ela é decodificada e RE-CODIFICADA (só os pixels
 *      sobrevivem; metadados e qualquer payload são descartados);
 *   2) sem GD, o arquivo é recusado se contiver marcas de código
 *      (<?php, <?=, <script), e só então é movido como está.
 *
 * Além disso o nginx/Apache não executam PHP dentro de assets/uploads/.
 */
final class ImagemSegura
{
    private const MAX_PIXELS = 24000000;

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return 0; // sem limite
        }
        $n = (int) $v;
        switch (strtolower(substr($v, -1))) {
            case 'g': return $n * 1024 * 1024 * 1024;
            case 'm': return $n * 1024 * 1024;
            case 'k': return $n * 1024;
        }
        return $n;
    }

    /** @return bool true se gravou $destino */
    public static function mover(string $tmp, string $mime, string $destino): bool
    {
        if (function_exists('imagecreatefromstring') && function_exists('getimagesize')) {
            $dados = @file_get_contents($tmp);
            if ($dados === false || $dados === '') {
                return false;
            }
            // Limite de dimensões: um PNG/JPEG minúsculo em bytes pode declarar
            // dezenas de milhares de pixels por lado (bomba de descompressão) e
            // esgotar a memória do PHP ao decodificar. 24 MP cobre qualquer foto
            // de celular comum (12 MP) com folga.
            $dim = @getimagesizefromstring($dados);
            if ($dim === false || $dim[0] < 1 || $dim[1] < 1 || ($dim[0] * $dim[1]) > self::MAX_PIXELS) {
                return false;
            }
            // Decodificar + (no JPEG) montar o fundo branco usa ~8 bytes/pixel.
            $precisa = (int) ($dim[0] * $dim[1] * 9) + 16 * 1024 * 1024;
            $limite = self::bytes((string) ini_get('memory_limit'));
            if ($limite > 0 && $limite < $precisa) {
                @ini_set('memory_limit', (string) $precisa);
                if (self::bytes((string) ini_get('memory_limit')) < $precisa) {
                    return false;
                }
            }
            $img = @imagecreatefromstring($dados);
            if ($img !== false) {
                $ok = self::gravar($img, $mime, $destino);
                imagedestroy($img);
                if ($ok) {
                    @chmod($destino, 0664);
                }
                return $ok;
            }
            return false; // GD não conseguiu ler: não é uma imagem de verdade
        }

        // Sem GD: último recurso.
        $inicio = @file_get_contents($tmp);
        if ($inicio === false || preg_match('/<\?(php|=)|<script/i', $inicio)) {
            return false;
        }
        if (!@move_uploaded_file($tmp, $destino)) {
            return false;
        }
        @chmod($destino, 0664);
        return true;
    }

    private static function gravar($img, string $mime, string $destino): bool
    {
        switch ($mime) {
            case 'image/png':
                imagealphablending($img, false);
                imagesavealpha($img, true);
                return imagepng($img, $destino, 6);
            case 'image/webp':
                if (!function_exists('imagewebp')) {
                    return false;
                }
                imagealphablending($img, false);
                imagesavealpha($img, true);
                return imagewebp($img, $destino, 88);
            default: // image/jpeg
                // JPEG não tem transparência: achata sobre branco.
                $w = imagesx($img);
                $h = imagesy($img);
                $fundo = imagecreatetruecolor($w, $h);
                imagefill($fundo, 0, 0, imagecolorallocate($fundo, 255, 255, 255));
                imagecopy($fundo, $img, 0, 0, 0, 0, $w, $h);
                $ok = imagejpeg($fundo, $destino, 88);
                imagedestroy($fundo);
                return $ok;
        }
    }
}
