<?php
// Publico/paginas/escolher_barbeiro.php
// Link GERAL da barbearia (sem login), diferente do link pessoal de cada
// barbeiro: acessado em /c/agendar (sem token nenhum — ver .htaccess e
// .nixpacks/assets/nginx.template.conf). Lista todos os barbeiros
// cadastrados pra o cliente escolher com quem quer agendar; ao escolher,
// segue pro fluxo normal de Publico/paginas/agendar.php (mesmo link
// pessoal que já existia, sem nenhuma mudança nele).
//
// Se a barbearia só tem 1 barbeiro cadastrado, pula direto pro link dele
// (não faz sentido mostrar uma lista de escolha com uma opção só). Os
// links pessoais de cada barbeiro continuam funcionando exatamente como
// antes, sem depender desta página.

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/PublicoTokenService.php';

$stmtBarbeiros = $pdo->prepare('SELECT id_barbeiro, nome, foto, link_publico FROM Barbeiro ORDER BY nome ASC');
$stmtBarbeiros->execute();
$barbeirosBrutos = $stmtBarbeiros->fetchAll();

// Garante que todo barbeiro listado tenha um link público pra apontar —
// gera na hora (e grava) pra quem ainda não tinha gerado manualmente em
// Configurações.
$barbeiros = array_map(function ($b) use ($pdo) {
    $token = PublicoTokenService::garantirLink($pdo, (int) $b['id_barbeiro'], $b['nome'], $b['link_publico']);

    $foto = null;
    if (!empty($b['foto'])) {
        $caminhoFoto = __DIR__ . '/../../assets/uploads/perfil/' . $b['foto'];
        if (is_file($caminhoFoto)) {
            $foto = '/assets/uploads/perfil/' . rawurlencode($b['foto']);
        }
    }

    return [
        'nome'  => $b['nome'],
        'foto'  => $foto,
        'token' => $token,
    ];
}, $barbeirosBrutos);

// Barbearia com um profissional só: não faz sentido pedir pra escolher —
// segue direto pro link pessoal dele.
if (count($barbeiros) === 1) {
    header('Location: /c/agendar/' . rawurlencode($barbeiros[0]['token']), true, 302);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agendar horário</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
    :root{
        --onyx:#0b0f17;
        --charcoal:#111827;
        --charcoal-2:#141b2b;
        --gold:#2f6fed;
        --gold-light:#5b93f7;
        --cream:#e7ebf3;
    }
    *{ box-sizing:border-box; }
    body{
        font-family:'Poppins', sans-serif;
        background-color:var(--onyx);
        background-image:
            radial-gradient(ellipse at 50% 0%, rgba(47,111,237,0.12), transparent 55%),
            radial-gradient(ellipse at 50% 100%, rgba(0,0,0,0.65), transparent 60%);
        color:var(--cream);
        min-height:100vh;
        margin:0;
    }
    .barber-stripe{ height:4px; width:100%; background:linear-gradient(90deg, var(--gold), var(--gold-light)); }
    .card{
        background:linear-gradient(180deg, var(--charcoal), var(--charcoal-2));
        border:1px solid rgba(47,111,237,0.14);
        border-radius:1.5rem;
        overflow:hidden;
    }
    .barbeiro-card{
        display:flex; align-items:center; gap:14px;
        padding:14px 16px; border-radius:1rem; border:1px solid rgba(255,255,255,0.08);
        background:rgba(255,255,255,0.02); text-decoration:none; color:var(--cream);
        transition:border-color .15s, background-color .15s;
    }
    .barbeiro-card:hover{ border-color:var(--gold-light); background:rgba(47,111,237,0.10); }
    .barbeiro-card__seta{ margin-left:auto; color:#5b6f8c; }
</style>
</head>
<body>

<div class="max-w-xl mx-auto px-5 py-10">

    <div class="flex flex-col items-center mb-8 text-center">
        <h1 class="text-xl font-semibold">Escolha seu profissional</h1>
        <p class="text-xs mt-1" style="color:#7f8fac;">Selecione quem vai te atender pra continuar o agendamento</p>
    </div>

    <div class="card p-2">
        <div class="barber-stripe"></div>
        <div class="p-4 flex flex-col gap-3">
            <?php foreach ($barbeiros as $b): ?>
                <a class="barbeiro-card" href="/c/agendar/<?= rawurlencode($b['token']) ?>">
                    <?php if ($b['foto']): ?>
                        <img src="<?= htmlspecialchars($b['foto']) ?>" alt="" class="w-11 h-11 rounded-full object-cover" style="border:2px solid rgba(47,111,237,0.5);">
                    <?php else: ?>
                        <div class="w-11 h-11 rounded-full flex items-center justify-center text-sm font-semibold" style="background:linear-gradient(145deg,#17233a,#0b0f17); border:1px solid rgba(47,111,237,0.5); color:var(--gold-light);">
                            <?= htmlspecialchars(mb_strtoupper(mb_substr($b['nome'], 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                    <span class="font-medium"><?= htmlspecialchars($b['nome']) ?></span>
                    <span class="barbeiro-card__seta">&rsaquo;</span>
                </a>
            <?php endforeach; ?>

            <?php if (empty($barbeiros)): ?>
                <p class="text-sm text-center py-4" style="color:#7f8fac;">Nenhum profissional disponível para agendamento no momento.</p>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-center text-xs mt-8" style="color:#465064;">Agendamento online &mdash; powered by BarbERP</p>
</div>

</body>
</html>
