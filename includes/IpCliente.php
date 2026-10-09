<?php
/**
 * includes/IpCliente.php
 *
 * IP do cliente para limites de tentativa (login e agendamento público).
 *
 * Atrás de proxy reverso (EasyPanel/Traefik) REMOTE_ADDR é o IP do próprio
 * proxy. Só então olhamos X-Forwarded-For, e pegamos o ÚLTIMO endereço
 * público da lista — é o que o nosso proxy acrescentou ao ver a conexão; os
 * da esquerda foram escritos pelo cliente e podem ser inventados (por isso
 * não usamos o primeiro, nem CF-Connecting-IP/X-Real-IP, que um atacante
 * também consegue enviar). Se o app for exposto direto, vale REMOTE_ADDR.
 */
final class IpCliente
{
    public static function obter(): string
    {
        $remoto = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($remoto !== '' && self::publico($remoto)) {
            return $remoto; // sem proxy na frente: ninguém pode forjar nada
        }

        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            $partes = array_reverse(array_map('trim', explode(',', $xff)));
            foreach ($partes as $ip) {
                if (self::publico($ip)) {
                    return $ip;
                }
            }
        }

        return $remoto !== '' ? $remoto : 'desconhecido';
    }

    private static function publico(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
