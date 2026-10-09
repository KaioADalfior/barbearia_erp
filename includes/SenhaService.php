<?php
/**
 * includes/SenhaService.php
 *
 * Conferência de senha do login e da troca de senha.
 *
 * Regra: se o valor guardado no banco É um hash (password_hash), a senha só
 * confere com password_verify(). O valor do próprio hash NUNCA é aceito como
 * senha (antes, quem obtivesse o hash do banco entrava digitando o hash —
 * "pass-the-hash"). A comparação direta com o valor guardado só existe para
 * contas legadas que ainda estão com a senha em texto puro (dado de
 * instalação antigo): elas entram uma vez e são convertidas para hash na
 * hora (ver upgrade em auth.php).
 */
final class SenhaService
{
    /** true se $armazenada já é um hash gerado por password_hash(). */
    public static function ehHash(string $armazenada): bool
    {
        $info = password_get_info($armazenada);
        return !empty($info['algo']);
    }

    public static function confere(string $digitada, ?string $armazenada): bool
    {
        if ($armazenada === null || $armazenada === '') {
            // Gasta o mesmo tempo de uma conferência real (evita revelar por
            // tempo de resposta se o login existe).
            password_verify($digitada, '$2y$10$usesomesillystringforsaltOa7xq0XqKQmQp1J2xN7m4o2kCz1S6');
            return false;
        }
        if (self::ehHash($armazenada)) {
            return password_verify($digitada, $armazenada);
        }
        return hash_equals($armazenada, $digitada); // legado: texto puro
    }
}
