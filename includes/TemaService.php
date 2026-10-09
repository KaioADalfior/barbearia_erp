<?php
/**
 * includes/TemaService.php
 *
 * Personalização da COR DE DESTAQUE do sistema interno (pós-login) por
 * barbearia. Segue o mesmo conceito da vitrine pública (Catálogo): uma cor
 * principal escolhida pelo proprietário/administrador, com presets, e o
 * texto sobre ela escolhido pelo contraste.
 *
 * Armazenamento: ConfiguracaoSistema (chave, valor) — a mesma tabela da
 * porcentagem de comissão —, chave 'tema_cor_destaque', valor '#rrggbb'.
 * Sem linha (ou valor inválido) = identidade padrão (latão/dourado), que
 * já está nas variáveis de assets/css/admin-theme.css; nesse caso nada
 * é emitido na página.
 *
 * A cor escolhida NUNCA é usada crua: para cada tema (escuro/claro) são
 * derivados tokens que garantem legibilidade (WCAG):
 *   --accent        preenchimento (botões, barras, pontos): contraste >= 2,2 com a superfície
 *   --accent-hi/-lo variações para hover/gradiente
 *   --accent-on     texto sobre o preenchimento (claro ou escuro, o de maior contraste)
 *   --accent-strong texto/ícone/borda/foco sobre a superfície: contraste >= 4,5
 *
 * A mesma derivação existe em JavaScript (assets/js/tema-cor.js) para a
 * pré-visualização ao vivo; tests comparam os dois.
 */
final class TemaService
{
    public const CHAVE      = 'tema_cor_destaque';
    public const COR_PADRAO = '#c9a14a';

    /** Superfícies de referência (as mesmas de admin-theme.css). */
    public const SUPERFICIE_ESCURO = '#16120f';
    public const SUPERFICIE_CLARO  = '#fbf8f2';

    private const TEXTO_ESCURO = '#1b1408';
    private const TEXTO_CLARO  = '#ffffff';

    // ------------------------------------------------------------ validação

    /** '#RRGGBB' (qualquer caixa) -> '#rrggbb'; qualquer outra coisa -> null. */
    public static function validar($valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }
        $v = strtolower(trim($valor));
        return preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : null;
    }

    // ------------------------------------------------------------ persistência

    /** Cor salva ou o padrão (tabela ausente, linha ausente ou valor inválido = padrão). */
    public static function corAtual(?PDO $pdo): string
    {
        if ($pdo === null) {
            return self::COR_PADRAO;
        }
        try {
            $stmt = $pdo->prepare('SELECT valor FROM ConfiguracaoSistema WHERE chave = :c');
            $stmt->execute(['c' => self::CHAVE]);
            $v = $stmt->fetchColumn();
            return self::validar($v === false ? null : $v) ?? self::COR_PADRAO;
        } catch (Throwable $e) {
            return self::COR_PADRAO;
        }
    }

    /** @throws InvalidArgumentException cor inválida */
    public static function salvar(PDO $pdo, string $cor): string
    {
        $v = self::validar($cor);
        if ($v === null) {
            throw new InvalidArgumentException('Cor inválida. Use o formato #RRGGBB.');
        }
        self::garantirTabela($pdo);
        $pdo->prepare(
            'INSERT INTO ConfiguracaoSistema (chave, valor) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        )->execute(['c' => self::CHAVE, 'v' => $v]);
        return $v;
    }

    /** Volta à identidade padrão (apaga só a chave do tema). */
    public static function restaurarPadrao(PDO $pdo): void
    {
        self::garantirTabela($pdo);
        $pdo->prepare('DELETE FROM ConfiguracaoSistema WHERE chave = :c')->execute(['c' => self::CHAVE]);
    }

    private static function garantirTabela(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ConfiguracaoSistema (
                chave VARCHAR(60) NOT NULL PRIMARY KEY,
                valor VARCHAR(255) NOT NULL,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    // ------------------------------------------------------------ cores

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    private static function hex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, (int) round($rgb[0]))), max(0, min(255, (int) round($rgb[1]))), max(0, min(255, (int) round($rgb[2]))));
    }

    /** Luminância relativa WCAG (0 a 1). */
    public static function luminancia(string $hex): float
    {
        $c = array_map(static function (int $v): float {
            $s = $v / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** Razão de contraste WCAG (1 a 21). */
    public static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Mistura $a com $b: $t = 0 devolve $a, 1 devolve $b. */
    public static function misturar(string $a, string $b, float $t): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);
        return self::hex([$x[0] + ($y[0] - $x[0]) * $t, $x[1] + ($y[1] - $x[1]) * $t, $x[2] + ($y[2] - $x[2]) * $t]);
    }

    /** Clareia (escuro) ou escurece (claro) $cor, em passos, até atingir $min de contraste com $superficie. */
    private static function ajustar(string $cor, string $superficie, float $min, bool $clarear): string
    {
        $alvo = $clarear ? '#ffffff' : '#000000';
        $atual = $cor;
        for ($i = 1; $i <= 40 && self::contraste($atual, $superficie) < $min; $i++) {
            $atual = self::misturar($cor, $alvo, $i * 0.025);
        }
        return $atual;
    }

    /**
     * Escolhe o texto sobre o preenchimento (escuro ou claro) e, se preciso,
     * desloca levemente o preenchimento para que esse texto atinja 4,5:1,
     * sem deixar o preenchimento perder 2,2:1 de contraste com a superfície.
     * Vence a opção que exige o menor deslocamento (empate: texto escuro).
     * Se nenhuma servir, mantém o preenchimento e usa o texto de maior contraste.
     *
     * @return array{0:string,1:string} [preenchimento, texto]
     */
    private static function conciliar(string $fill, string $superficie): array
    {
        $melhor = null;
        foreach ([[self::TEXTO_ESCURO, '#ffffff'], [self::TEXTO_CLARO, '#000000']] as [$on, $alvo]) {
            for ($i = 0; $i <= 40; $i++) {
                $f = $i === 0 ? $fill : self::misturar($fill, $alvo, $i * 0.02);
                if (self::contraste($f, $on) >= 4.5 && self::contraste($f, $superficie) >= 2.2) {
                    if ($melhor === null || $i < $melhor[0]) {
                        $melhor = [$i, $f, $on];
                    }
                    break;
                }
            }
        }
        if ($melhor !== null) {
            return [$melhor[1], $melhor[2]];
        }
        $on = self::contraste($fill, self::TEXTO_ESCURO) >= self::contraste($fill, self::TEXTO_CLARO)
            ? self::TEXTO_ESCURO : self::TEXTO_CLARO;
        return [$fill, $on];
    }

    /**
     * Tokens de um tema.
     *
     * @return array{accent:string, accent-rgb:string, accent-hi:string, accent-lo:string, accent-on:string, accent-strong:string}
     */
    public static function tokens(string $cor, bool $escuro): array
    {
        $cor = self::validar($cor) ?? self::COR_PADRAO;
        $superficie = $escuro ? self::SUPERFICIE_ESCURO : self::SUPERFICIE_CLARO;

        $fill = self::ajustar($cor, $superficie, 2.2, $escuro);
        [$fill, $on] = self::conciliar($fill, $superficie);
        $strong = self::ajustar($fill, $superficie, 4.5, $escuro);
        [$r, $g, $b] = self::rgb($fill);

        return [
            'accent'        => $fill,
            'accent-rgb'    => "$r,$g,$b",
            'accent-hi'     => self::misturar($fill, '#ffffff', 0.2),
            'accent-lo'     => self::misturar($fill, '#000000', 0.22),
            'accent-on'     => $on,
            'accent-strong' => $strong,
        ];
    }

    // ------------------------------------------------------------ saída HTML

    /**
     * <style> com as variáveis da cor personalizada, ou '' quando vale o padrão.
     * Os seletores têm especificidade maior que :root / html[data-theme] de
     * admin-theme.css (que carrega depois), por isso vencem sem !important.
     */
    public static function css(?PDO $pdo): string
    {
        $cor = self::corAtual($pdo);
        if ($cor === self::COR_PADRAO) {
            return '';
        }
        $linhas = static function (array $t): string {
            $s = '';
            foreach ($t as $k => $v) {
                $s .= "--$k:$v;";
            }
            return $s;
        };
        return '<style id="ab-tema">'
            . 'html:root:root{' . $linhas(self::tokens($cor, true)) . '}'
            . 'html[data-theme="light"]:root:root{' . $linhas(self::tokens($cor, false)) . '}'
            . '</style>' . "\n";
    }
}
