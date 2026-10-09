<?php
/**
 * includes/AcessoService.php
 *
 * Camada de permissões por TIPO DE ACESSO dos barbeiros (Proprietário x
 * Funcionário). Sempre decide pelo valor gravado no banco
 * (Barbeiro.tipo_usuario) — nunca por um valor vindo do navegador nem por
 * uma cópia antiga na sessão: se o administrador mudar o tipo de alguém, a
 * mudança vale já na próxima requisição.
 *
 *   - Proprietário: acesso total (todos os funcionários, todo o financeiro,
 *     todas as comissões, configuração da porcentagem).
 *   - Funcionário: só os próprios dados (agenda, atendimentos, comissão).
 *
 * Barbeiros que já existiam antes desta estrutura continuam como
 * 'proprietario' (DEFAULT da coluna) — nada muda para quem já usa o sistema
 * até o administrador marcar alguém como Funcionário.
 *
 * Uso nos endpoints/páginas (sempre depois de session.php e config.php):
 *   AcessoService::exigirProprietario($pdo);                // página
 *   AcessoService::exigirProprietario($pdo, json: true);    // endpoint AJAX
 *   AcessoService::exigirAcessoAoFuncionario($pdo, $idAlvo, json: true);
 */

require_once __DIR__ . '/guard.php';

final class AcessoService
{
    public const PROPRIETARIO = 'proprietario';
    public const FUNCIONARIO  = 'funcionario';

    /** @var array<int,string> */
    private static array $cache = [];

    /** Tipo de acesso do barbeiro logado ('proprietario' | 'funcionario'); null se não for barbeiro. */
    public static function tipoDoBarbeiroLogado(PDO $pdo): ?string
    {
        if (($_SESSION['tipo'] ?? null) !== 'barbeiro') {
            return null;
        }
        return self::tipoDoBarbeiro($pdo, (int) ($_SESSION['id'] ?? 0));
    }

    public static function tipoDoBarbeiro(PDO $pdo, int $idBarbeiro): string
    {
        if ($idBarbeiro <= 0) {
            return self::FUNCIONARIO; // sem identificação válida: nunca assume poder
        }
        if (isset(self::$cache[$idBarbeiro])) {
            return self::$cache[$idBarbeiro];
        }

        try {
            $stmt = $pdo->prepare('SELECT tipo_usuario FROM Barbeiro WHERE id_barbeiro = :id');
            $stmt->execute(['id' => $idBarbeiro]);
            $valor = $stmt->fetchColumn();
            if ($valor === false) {
                $tipo = self::FUNCIONARIO; // barbeiro inexistente
            } else {
                $tipo = $valor === self::FUNCIONARIO ? self::FUNCIONARIO : self::PROPRIETARIO;
            }
        } catch (Throwable $e) {
            // Coluna ainda não existe (migração não rodou): comportamento
            // antigo do sistema — todo barbeiro tem acesso completo.
            // QUALQUER OUTRO erro (timeout, deadlock, queda do banco) NÃO
            // pode virar poder de proprietário: falha FECHADA, e sem guardar
            // no cache para tentar de novo na próxima chamada.
            $colunaAusente = $e instanceof PDOException
                && (($e->errorInfo[1] ?? null) === 1054 || $e->getCode() === '42S22');
            if (!$colunaAusente) {
                error_log('AcessoService: falha ao ler tipo_usuario: ' . $e->getMessage());
                return self::FUNCIONARIO;
            }
            $tipo = self::PROPRIETARIO;
        }

        return self::$cache[$idBarbeiro] = $tipo;
    }

    public static function ehProprietario(PDO $pdo): bool
    {
        return self::tipoDoBarbeiroLogado($pdo) === self::PROPRIETARIO;
    }

    public static function ehFuncionario(PDO $pdo): bool
    {
        return self::tipoDoBarbeiroLogado($pdo) === self::FUNCIONARIO;
    }

    /** Limpa o cache em memória (usado em testes e depois de alterar um tipo na mesma requisição). */
    public static function esquecer(): void
    {
        self::$cache = [];
    }

    /**
     * Só o Proprietário passa. Barbeiro Funcionário recebe ACESSO NEGADO
     * (403) e a tentativa é registrada no canal de alertas.
     */
    public static function exigirProprietario(PDO $pdo, bool $json = false): void
    {
        exigirSessao(['barbeiro'], $json);

        if (self::ehProprietario($pdo)) {
            return;
        }

        self::negar($json, 'Recurso exclusivo do proprietário');
    }

    /**
     * Proprietário OU administrador (login de admin) — usado na
     * configuração da porcentagem de comissão.
     */
    public static function exigirProprietarioOuAdmin(PDO $pdo, bool $json = false): void
    {
        exigirSessao(['admin', 'barbeiro'], $json);

        if (($_SESSION['tipo'] ?? null) === 'admin' || self::ehProprietario($pdo)) {
            return;
        }

        self::negar($json, 'Recurso exclusivo do proprietário');
    }

    /**
     * Garante que quem pede dados do funcionário $idAlvo pode vê-los:
     * Proprietário vê qualquer um; Funcionário só o próprio id. Qualquer
     * outro id (URL/parâmetro manipulado) => ACESSO NEGADO.
     */
    public static function exigirAcessoAoFuncionario(PDO $pdo, int $idAlvo, bool $json = false): void
    {
        exigirSessao(['barbeiro'], $json);

        if (self::ehProprietario($pdo) || $idAlvo === (int) $_SESSION['id']) {
            return;
        }

        self::negar($json, 'Tentativa de acessar dados de outro funcionário (#' . $idAlvo . ')');
    }

    /** Resposta padrão de acesso negado (HTTP 403) + alerta no Discord. */
    public static function negar(bool $json, string $motivo = ''): void
    {
        DiscordLogger::alerta('🚫 ACESSO NEGADO (permissão)', [
            ['name' => '📄 Recurso',  'value' => $_SERVER['REQUEST_URI'] ?? '—', 'inline' => false],
            ['name' => '👤 Usuário',  'value' => ($_SESSION['nome'] ?? '—') . ' (#' . ($_SESSION['id'] ?? '—') . ')', 'inline' => true],
            ['name' => '📝 Motivo',   'value' => $motivo !== '' ? $motivo : '—', 'inline' => true],
            ['name' => '🌐 IP',       'value' => $_SERVER['REMOTE_ADDR'] ?? '—', 'inline' => true],
        ]);

        http_response_code(403);

        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'erro' => 'Acesso negado.']);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acesso negado — BarbERP</title>
<style>
    body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#0b0f17; color:#e7ebf3; font-family:'Poppins',system-ui,sans-serif; text-align:center; padding:24px; }
    .box{ max-width:420px; }
    h1{ font-size:24px; margin:0 0 8px; letter-spacing:.02em; }
    p{ color:#8b97ac; font-size:14px; line-height:1.55; margin:0 0 20px; }
    a{ display:inline-block; padding:11px 22px; border-radius:12px; background:#3d7ec9; color:#fff; text-decoration:none; font-size:14px; font-weight:600; }
</style>
</head>
<body>
    <div class="box">
        <h1>ACESSO NEGADO</h1>
        <p>Você não tem permissão para acessar esta área. Se acredita que isso é um engano, fale com o proprietário da barbearia.</p>
        <a href="/inicio">Voltar ao início</a>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
