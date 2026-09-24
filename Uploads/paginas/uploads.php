<?php
// Uploads/paginas/uploads.php
// Página pública (link acessível a partir do login) que exibe, apenas para
// leitura, o histórico de versões/uploads lançados pelo desenvolvedor no
// painel do Admin (Configurações -> Versões & Uploads).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/config.php';

$stmt = $pdo->query(
    'SELECT versao, descricao, data_hora
     FROM UploadVersao
     ORDER BY data_hora DESC, idUpload DESC'
);
$uploads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Uploads &amp; Versões — Alex Barbearia</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
    :root{
        --onyx:#0a0e1a;
        --charcoal:#101828;
        --charcoal-2:#182338;
        --charcoal-3:#182338;
        --gold:#3d7ec9;
        --gold-light:#6fa8ea;
        --cream:#e9eef6;
        --pole-red:#1e4d80;
        --pole-blue:#5b9bd8;
    }

    *{ box-sizing:border-box; }

    body{
        font-family:'Poppins', sans-serif;
        background-color:var(--onyx);
        background-image:
            radial-gradient(ellipse at 50% 0%, rgba(61,126,201,0.10), transparent 55%),
            radial-gradient(ellipse at 50% 100%, rgba(0,0,0,0.65), transparent 60%),
            repeating-linear-gradient(45deg, rgba(255,255,255,0.015) 0, rgba(255,255,255,0.015) 1px, transparent 1px, transparent 6px);
        min-height:100vh;
    }

    .display{
        font-family:'Bebas Neue', sans-serif;
        letter-spacing:0.06em;
    }

    .eyebrow{
        font-family:'Poppins', sans-serif;
        letter-spacing:0.35em;
        font-size:11px;
        font-weight:500;
    }

    .emblem{
        background:
            radial-gradient(circle at 35% 30%, rgba(255,255,255,0.10), transparent 45%),
            linear-gradient(145deg, #142238, #0a0e1a);
        border:1px solid rgba(61,126,201,0.55);
        box-shadow:
            0 0 0 4px rgba(61,126,201,0.08),
            0 12px 30px -8px rgba(0,0,0,0.7),
            inset 0 1px 1px rgba(255,255,255,0.05);
    }

    .barber-stripe{
        height:6px;
        width:100%;
        background:repeating-linear-gradient(
            -45deg,
            var(--pole-red) 0 14px,
            var(--cream) 14px 28px,
            var(--pole-blue) 28px 42px,
            var(--cream) 42px 56px
        );
        background-size:200% 100%;
        animation:pole-scroll 7s linear infinite;
    }
    @keyframes pole-scroll{
        from{ background-position:0 0; }
        to{ background-position:-79px 0; }
    }
    @media (prefers-reduced-motion: reduce){
        .barber-stripe{ animation:none; }
    }

    .card{
        background:linear-gradient(180deg, var(--charcoal), var(--charcoal-2));
        border:1px solid rgba(61,126,201,0.14);
    }

    .version-item{
        border:1px solid rgba(255,255,255,0.07);
        background:rgba(255,255,255,0.02);
        transition:border-color .2s, background-color .2s;
    }
    .version-item:hover{
        border-color:rgba(61,126,201,0.3);
        background:rgba(61,126,201,0.04);
    }
    .version-item:first-child{
        border-color:rgba(61,126,201,0.45);
        background:rgba(61,126,201,0.07);
    }

    .badge-versao{
        display:inline-flex;
        align-items:center;
        font-family:'Bebas Neue', sans-serif;
        letter-spacing:0.04em;
        font-size:17px;
        color:var(--gold-light);
        background:rgba(61,126,201,0.12);
        border:1px solid rgba(61,126,201,0.4);
        border-radius:999px;
        padding:0.2rem 0.9rem;
    }

    .badge-novo{
        display:inline-flex;
        align-items:center;
        font-size:10px;
        font-weight:600;
        letter-spacing:0.1em;
        text-transform:uppercase;
        color:#bfe6c7;
        background:rgba(66,140,82,0.14);
        border:1px solid rgba(66,140,82,0.4);
        border-radius:999px;
        padding:0.2rem 0.6rem;
    }

    .divider-tick{
        width:1px;
        height:14px;
        background:rgba(61,126,201,0.4);
    }
</style>
</head>

<body class="flex items-center justify-center px-6 py-10">

<div class="w-full max-w-2xl">

    <!-- Emblema -->
    <div class="flex flex-col items-center mb-7">
        <div class="emblem w-16 h-16 rounded-full flex items-center justify-center mb-4">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.5 5.5a2.5 2.5 0 1 1 3.4 3.4L18 17.5" stroke="#6fa8ea" stroke-width="1.4" stroke-linecap="round"/>
                <path d="M6.5 18.5a2.5 2.5 0 1 0 3.4-3.4L18 6.5" stroke="#6fa8ea" stroke-width="1.4" stroke-linecap="round"/>
                <circle cx="6.2" cy="6.2" r="1.6" stroke="#6fa8ea" stroke-width="1.2"/>
                <circle cx="6.2" cy="17.8" r="1.6" stroke="#6fa8ea" stroke-width="1.2"/>
            </svg>
        </div>

        <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Alex Barbearia</p>
        <h1 class="display text-4xl text-[color:var(--cream)] leading-none">
            UPLOADS <span class="text-yellow-500">&amp; VERSÕES</span>
        </h1>
        <div class="flex items-center gap-3 mt-3 text-[11px] text-zinc-500 tracking-wider">
            <span>HISTÓRICO DE ATUALIZAÇÕES</span>
            <span class="divider-tick"></span>
            <span>SOMENTE VISUALIZAÇÃO</span>
        </div>
    </div>

    <!-- Card -->
    <div class="card rounded-3xl shadow-2xl overflow-hidden">

        <div class="barber-stripe"></div>

        <div class="p-6 sm:p-8">

            <?php if (empty($uploads)): ?>

                <div class="text-center py-10">
                    <p class="text-sm text-zinc-500">Nenhuma versão foi publicada ainda.</p>
                    <p class="text-xs text-zinc-600 mt-1">Assim que o desenvolvedor lançar um upload, ele aparecerá aqui.</p>
                </div>

            <?php else: ?>

                <div class="flex flex-col gap-3">
                    <?php foreach ($uploads as $index => $u): ?>
                        <div class="version-item rounded-2xl p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <span class="badge-versao"><?= htmlspecialchars($u['versao']) ?></span>
                                <?php if ($index === 0): ?>
                                    <span class="badge-novo">Mais recente</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-[color:var(--cream)] leading-relaxed mb-3"><?= nl2br(htmlspecialchars($u['descricao'])) ?></p>
                            <p class="text-xs text-zinc-500">
                                <?= date('d/m/Y \à\s H:i', strtotime($u['data_hora'])) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>

        <div class="barber-stripe"></div>
    </div>

    <p class="text-center text-xs text-zinc-600 mt-8 font-light">
        <a href="../../Autenticacao/paginas/login.php" class="hover:text-yellow-500/70 transition-colors">&larr; Voltar para o login</a>
    </p>

</div>

</body>
</html>
