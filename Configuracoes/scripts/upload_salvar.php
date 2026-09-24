<?php
// upload_salvar.php
// Salva um novo registro de Upload/Versão (painel Admin -> Configurações).
// A versão pode ser digitada manualmente no modal ou, se o campo ficar em
// branco, é gerada automaticamente a partir da última versão cadastrada:
//   (nenhuma ainda) -> v1.0.0
//   v1.0.0          -> v1.0.0.1
//   v1.0.0.1        -> v1.0.0.2
//   ... e assim por diante.

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/configuracoes.php');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '../paginas/configuracoes.php');

/**
 * Gera a próxima versão com base na última cadastrada.
 * Mantém um eventual prefixo "v"/"V" e incrementa (ou adiciona) o último
 * segmento numérico separado por ponto.
 */
function gerarProximaVersao(?string $ultimaVersao): string
{
    if ($ultimaVersao === null || trim($ultimaVersao) === '') {
        return 'v1.0.0';
    }

    $prefixo = '';
    $corpo   = $ultimaVersao;

    if (stripos($corpo, 'v') === 0) {
        $prefixo = $corpo[0]; // preserva "v" ou "V" original
        $corpo   = substr($corpo, 1);
    }

    $partes = explode('.', $corpo);

    // Se algum segmento não for numérico, não há como incrementar com segurança:
    // cai para o padrão de acrescentar ".1" ao final da string original.
    $todosNumericos = true;
    foreach ($partes as $p) {
        if ($p === '' || !ctype_digit($p)) {
            $todosNumericos = false;
            break;
        }
    }

    if (!$todosNumericos) {
        return $ultimaVersao . '.1';
    }

    if (count($partes) <= 3) {
        // v1.0.0 -> v1.0.0.1
        $partes[] = '1';
    } else {
        // v1.0.0.1 -> v1.0.0.2
        $ultimoIndice = count($partes) - 1;
        $partes[$ultimoIndice] = (string) ((int) $partes[$ultimoIndice] + 1);
    }

    return $prefixo . implode('.', $partes);
}

$versaoInformada = trim($_POST['versao'] ?? '');
$descricao       = trim($_POST['descricao'] ?? '');
$data            = trim($_POST['data'] ?? '');
$hora            = trim($_POST['hora'] ?? '');

// ---------- Validação básica ----------
if ($descricao === '' || $data === '' || $hora === '') {
    header('Location: ../paginas/configuracoes.php?upload_status=erro'
        . '&up_versao='    . urlencode($versaoInformada)
        . '&up_descricao=' . urlencode($descricao)
        . '&up_data='      . urlencode($data)
        . '&up_hora='      . urlencode($hora));
    exit;
}

$dataHoraValida = DateTime::createFromFormat('Y-m-d H:i', $data . ' ' . $hora);
if (!$dataHoraValida) {
    header('Location: ../paginas/configuracoes.php?upload_status=erro'
        . '&up_versao='    . urlencode($versaoInformada)
        . '&up_descricao=' . urlencode($descricao)
        . '&up_data='      . urlencode($data)
        . '&up_hora='      . urlencode($hora));
    exit;
}

// ---------- Determina a versão ----------
if ($versaoInformada !== '') {
    $versao = $versaoInformada;

    // Impede duas versões iguais
    $stmt = $pdo->prepare('SELECT idUpload FROM UploadVersao WHERE versao = :versao LIMIT 1');
    $stmt->execute(['versao' => $versao]);
    if ($stmt->fetch()) {
        header('Location: ../paginas/configuracoes.php?upload_status=duplicada'
            . '&up_versao='    . urlencode($versaoInformada)
            . '&up_descricao=' . urlencode($descricao)
            . '&up_data='      . urlencode($data)
            . '&up_hora='      . urlencode($hora));
        exit;
    }
} else {
    // Busca a última versão cadastrada (pela ordem de inserção) e gera a próxima
    $stmt = $pdo->query('SELECT versao FROM UploadVersao ORDER BY idUpload DESC LIMIT 1');
    $ultima = $stmt->fetch();
    $versao = gerarProximaVersao($ultima['versao'] ?? null);
}

$stmt = $pdo->prepare(
    'INSERT INTO UploadVersao (versao, descricao, data_hora, id_admin)
     VALUES (:versao, :descricao, :data_hora, :id_admin)'
);
$stmt->execute([
    'versao'    => $versao,
    'descricao' => $descricao,
    'data_hora' => $dataHoraValida->format('Y-m-d H:i:00'),
    'id_admin'  => $_SESSION['id'] ?? null,
]);

DiscordLogger::uploads('🆕 Nova versão publicada', [
    ['name' => '🏷️ Versão', 'value' => $versao, 'inline' => true],
    ['name' => '👤 Admin', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => true],
    ['name' => '📅 Data/Hora', 'value' => $dataHoraValida->format('d/m/Y H:i'), 'inline' => true],
    ['name' => '📝 Descrição', 'value' => $descricao, 'inline' => false],
]);

header('Location: ../paginas/configuracoes.php?upload_status=sucesso&up_versao=' . urlencode($versao));
exit;
