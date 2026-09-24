<?php
// includes/DiscordLogger.php
// Cliente central de logs via Discord Webhooks.
// Cada método corresponde a um canal (sistema, erro, alerta, login,
// sessoes, clientes, agendamentos, uploads, configuracoes, database, admin)
// e envia um embed para o webhook configurado em config/webhooks_discord.php.

require_once __DIR__ . '/../config/webhooks_discord.php';

class DiscordLogger
{
    // Identidade exibida no Discord em toda mensagem enviada pelo webhook,
    // independente do nome/avatar padrão configurado lá nas Integrações.
    const NOME_BOT = 'DAK ChatBots e Soluções Digitais';

    // Paleta de cores dos embeds (decimal) — paleta atual do Discord,
    // mais viva e consistente com o tema escuro do app.
    const COR_SUCESSO = 5763719;   // verde   #57F287
    const COR_ALERTA  = 16027660;  // âmbar   #F4A90C
    const COR_ERRO    = 15548997;  // vermelho #ED4245
    const COR_EDICAO  = 5793266;   // azul/blurple #5865F2
    const COR_INFO    = 15418526;  // fúcsia  #EB459E
    const COR_PADRAO  = 10070709;  // slate   #99AAB5

    // Rótulo (autor do embed) por canal — dá identidade visual imediata,
    // sem precisar abrir a mensagem pra saber de onde veio.
    private const CATEGORIAS = [
        'sistema'        => '⚙️  Sistema',
        'erro'           => '💥  Sistema · Erros',
        'alerta'         => '🚨  Sistema · Alertas',
        'login'          => '🔐  Autenticação · Login',
        'sessoes'        => '🔐  Autenticação · Sessão',
        'financeiro_lancamentos' => '💵  Financeiro · Lançamentos',
        'financeiro_relatorios'  => '📄  Financeiro · Relatórios',
        'clientes'       => '🧑‍🤝‍🧑  Clientes',
        'servicos'       => '💈  Serviços',
        'agendamentos'   => '📅  Agendamentos',
        'uploads'        => '📦  Uploads',
        'configuracoes'  => '🛠️  Configurações',
        'database'       => '🗄️  Banco de Dados',
        'admin'          => '👑  Administração',
    ];

    // ---------------- Sistema ----------------

    public static function sistema(string $titulo, array $campos = [], int $cor = self::COR_PADRAO): void
    {
        self::enviar('sistema', self::url('WEBHOOK_LOGS_SISTEMA'), $titulo, $campos, $cor);
    }

    public static function erro(string $titulo, $detalhe = null, array $camposExtra = []): void
    {
        $campos = $camposExtra;

        if ($detalhe instanceof \Throwable) {
            array_unshift($campos, ['name' => '📄 Local', 'value' => '```' . $detalhe->getFile() . ':' . $detalhe->getLine() . '```', 'inline' => false]);
            array_unshift($campos, ['name' => '💬 Mensagem', 'value' => $detalhe->getMessage(), 'inline' => false]);
        } elseif (is_string($detalhe) && $detalhe !== '') {
            array_unshift($campos, ['name' => '💬 Mensagem', 'value' => $detalhe, 'inline' => false]);
        }

        self::enviar('erro', self::url('WEBHOOK_LOGS_ERROS'), $titulo, $campos, self::COR_ERRO);
    }

    public static function alerta(string $titulo, array $campos = []): void
    {
        self::enviar('alerta', self::url('WEBHOOK_LOGS_ALERTAS'), $titulo, $campos, self::COR_ALERTA);
    }

    // ---------------- Autenticação ----------------

    public static function login(bool $sucesso, string $titulo, array $campos = []): void
    {
        self::enviar('login', self::url('WEBHOOK_LOGIN'), $titulo, $campos, $sucesso ? self::COR_SUCESSO : self::COR_ERRO);
    }

    public static function sessoes(string $titulo, array $campos = []): void
    {
        self::enviar('sessoes', self::url('WEBHOOK_SESSOES'), $titulo, $campos, self::COR_PADRAO);
    }

    // ---------------- Financeiro ----------------
    // Duas categorias dentro do mesmo módulo: lançamentos (baixas e fiados,
    // movimentação de caixa) e relatórios (geração/exclusão de PDF) —
    // separados porque são naturezas de evento diferentes, mas ambos fora
    // do canal "sistema" para não misturar dados financeiros com logs
    // genéricos da aplicação.

    public static function financeiroLancamentos(string $titulo, array $campos = [], ?int $cor = null): void
    {
        self::enviar('financeiro_lancamentos', self::url('WEBHOOK_FINANCEIRO_LANCAMENTOS'), $titulo, $campos, $cor ?? self::COR_SUCESSO);
    }

    public static function financeiroRelatorios(string $titulo, array $campos = [], ?int $cor = null): void
    {
        self::enviar('financeiro_relatorios', self::url('WEBHOOK_FINANCEIRO_RELATORIOS'), $titulo, $campos, $cor ?? self::COR_SUCESSO);
    }

    // ---------------- Clientes ----------------

    public static function clientes(string $titulo, array $campos = [], ?int $cor = null): void
    {
        self::enviar('clientes', self::url('WEBHOOK_CLIENTES'), $titulo, $campos, $cor ?? self::COR_SUCESSO);
    }

    // ---------------- Serviços ----------------

    public static function servicos(string $titulo, array $campos = [], ?int $cor = null): void
    {
        self::enviar('servicos', self::url('WEBHOOK_SERVICOS'), $titulo, $campos, $cor ?? self::COR_SUCESSO);
    }

    // ---------------- Agendamentos ----------------

    public static function agendamentos(string $titulo, array $campos = [], ?int $cor = null): void
    {
        self::enviar('agendamentos', self::url('WEBHOOK_AGENDAMENTOS'), $titulo, $campos, $cor ?? self::COR_SUCESSO);
    }

    // ---------------- Uploads ----------------

    public static function uploads(string $titulo, array $campos = [], ?int $cor = null): void
    {
        self::enviar('uploads', self::url('WEBHOOK_UPLOADS'), $titulo, $campos, $cor ?? self::COR_SUCESSO);
    }

    // ---------------- Configurações ----------------

    public static function configuracoes(string $titulo, array $campos = []): void
    {
        self::enviar('configuracoes', self::url('WEBHOOK_CONFIGURACOES'), $titulo, $campos, self::COR_EDICAO);
    }

    // ---------------- Banco de Dados ----------------

    public static function database(string $titulo, $detalhe = null, array $camposExtra = []): void
    {
        $campos = $camposExtra;

        if ($detalhe instanceof \Throwable) {
            array_unshift($campos, ['name' => '💬 Mensagem', 'value' => $detalhe->getMessage(), 'inline' => false]);
        } elseif (is_array($detalhe)) {
            $campos = array_merge($detalhe, $campos);
        } elseif (is_string($detalhe) && $detalhe !== '') {
            array_unshift($campos, ['name' => '💬 Mensagem', 'value' => $detalhe, 'inline' => false]);
        }

        self::enviar('database', self::url('WEBHOOK_DATABASE'), $titulo, $campos, self::COR_ERRO);
    }

    // ---------------- Administração ----------------

    public static function admin(string $titulo, array $campos = [], ?int $cor = null): void
    {
        self::enviar('admin', self::url('WEBHOOK_ADMIN'), $titulo, $campos, $cor ?? self::COR_PADRAO);
    }

    // ---------------- Núcleo ----------------

    private static function url(string $nomeConstante): ?string
    {
        return defined($nomeConstante) ? constant($nomeConstante) : null;
    }

    private static function enviar(string $categoria, ?string $webhookUrl, string $titulo, array $campos, int $cor): void
    {
        // Agora as URLs configuradas em webhooks_discord.php apontam para o
        // proxy Cloudflare (DISCORD_PROXY_URL), não mais direto pro Discord —
        // então só validamos que é uma URL https configurada.
        if (!$webhookUrl || strpos($webhookUrl, 'https://') !== 0) {
            // Webhook ainda não configurado para este canal — não quebra o sistema.
            return;
        }

        $embed = [
            'author' => [
                'name' => self::CATEGORIAS[$categoria] ?? ucfirst($categoria),
            ],
            'title'     => mb_substr($titulo, 0, 256),
            'color'     => $cor,
            'fields'    => self::normalizarCampos($campos),
            'footer'    => [
                'text' => self::NOME_BOT . '  •  ' . date('d/m/Y \à\s H:i'),
            ],
        ];

        // Avatar/ícone opcional do bot, se configurado.
        if (defined('WEBHOOK_AVATAR_URL') && WEBHOOK_AVATAR_URL !== '') {
            $embed['thumbnail'] = ['url' => WEBHOOK_AVATAR_URL];
        }

        $payload = [
            'username' => self::NOME_BOT,
            'embeds'   => [$embed],
        ];

        if (defined('WEBHOOK_AVATAR_URL') && WEBHOOK_AVATAR_URL !== '') {
            $payload['avatar_url'] = WEBHOOK_AVATAR_URL;
        }

        self::enviarSemBloquear($webhookUrl, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private static function normalizarCampos(array $campos): array
    {
        $normalizados = [];
        foreach (array_slice($campos, 0, 25) as $campo) { // Discord aceita no máx. 25 campos por embed
            $valor = (string) ($campo['value'] ?? '—');
            $normalizados[] = [
                'name'   => mb_substr((string) ($campo['name'] ?? '—'), 0, 256),
                'value'  => $valor === '' ? '—' : mb_substr($valor, 0, 1024),
                'inline' => (bool) ($campo['inline'] ?? true),
            ];
        }
        return $normalizados;
    }

    // Envia com timeout curto para nunca travar a resposta ao usuário caso
    // o Discord esteja fora do ar ou lento. Qualquer falha vai só pro log
    // local do PHP, nunca interrompe a função que chamou o logger.
    private static function enviarSemBloquear(string $url, string $payload): void
    {
        try {
            if (!function_exists('curl_init')) {
                error_log('DiscordLogger: extensão cURL não disponível.');
                return;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            error_log('DiscordLogger: falha ao enviar webhook — ' . $e->getMessage());
        }
    }
}