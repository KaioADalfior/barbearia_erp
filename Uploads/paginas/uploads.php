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
<title>Uploads &amp; Versões — BarbERP</title>

<?php require_once __DIR__ . '/../../includes/theme-init.php'; ?>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=3">

<style>
    /* Tokens, tipografia (.display, .eyebrow) e tema claro/escuro vêm de
       assets/css/admin-theme.css; aqui só as peças exclusivas desta tela. */
    body.up-body{
        background-image:
            radial-gradient(ellipse at 50% 0%, rgba(var(--accent-rgb),0.11), transparent 55%),
            radial-gradient(ellipse at 50% 100%, rgba(0,0,0,0.35), transparent 60%),
            repeating-linear-gradient(45deg, rgba(255,236,205,0.014) 0, rgba(255,236,205,0.014) 1px, transparent 1px, transparent 7px);
    }
    html[data-theme="light"] body.up-body{
        background-image:radial-gradient(ellipse at 50% 0%, rgba(var(--accent-rgb),0.16), transparent 55%);
    }

    .up-emblem{
        background:
            radial-gradient(circle at 35% 30%, rgba(255,255,255,0.10), transparent 45%),
            linear-gradient(145deg, var(--surface-3), var(--bg));
        border:1px solid rgba(var(--accent-rgb),0.55);
        box-shadow:0 0 0 4px rgba(var(--accent-rgb),0.08), var(--shadow-1), inset 0 1px 1px rgba(255,255,255,0.05);
    }

    .up-stripe{
        height:4px;
        width:100%;
        background:linear-gradient(90deg, var(--accent-lo), var(--accent), var(--accent-hi), var(--accent), var(--accent-lo));
    }

    .up-card{
        background:linear-gradient(180deg, var(--surface-2), var(--surface));
        border:1px solid var(--line-strong);
        box-shadow:var(--shadow-2);
    }

    .version-item{
        border:1px solid var(--line);
        background:var(--surface-3);
        transition:border-color .2s, background-color .2s;
    }
    .version-item:hover{
        border-color:rgba(var(--accent-rgb),0.35);
    }
    .version-item:first-child{
        border-color:rgba(var(--accent-rgb),0.5);
        background:rgba(var(--accent-rgb),0.08);
    }

    .badge-versao{
        display:inline-flex;
        align-items:center;
        font-weight:600;
        letter-spacing:0.02em;
        font-size:14px;
        color:var(--accent-strong);
        background:rgba(var(--accent-rgb),0.12);
        border:1px solid rgba(var(--accent-rgb),0.4);
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
        color:var(--success-text);
        background:var(--success-soft);
        border:1px solid rgba(66,140,82,0.4);
        border-radius:999px;
        padding:0.2rem 0.6rem;
    }

    .up-tick{
        width:1px;
        height:14px;
        background:rgba(var(--accent-rgb),0.5);
    }
</style>
</head>

<body class="up-body flex items-center justify-center px-6 py-10">

<div class="w-full max-w-2xl">

    <!-- Emblema -->
    <div class="flex flex-col items-center mb-7">
        <div class="up-emblem w-16 h-16 rounded-full flex items-center justify-center mb-4">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="5" y="11" width="14" height="9" rx="2" stroke="var(--accent-strong)" stroke-width="1.4"/>
                <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="var(--accent-strong)" stroke-width="1.4" stroke-linecap="round"/>
                <circle cx="12" cy="15.4" r="1.3" fill="var(--accent-strong)"/>
            </svg>
        </div>

        <p class="eyebrow uppercase mb-1" style="color:var(--accent-strong); opacity:.75">Plataforma de Gestão</p>
        <h1 class="display text-4xl text-[color:var(--cream)] leading-none">
            UPLOADS <span style="color:var(--accent-strong)">&amp; VERSÕES</span>
        </h1>
        <div class="flex items-center gap-3 mt-3 text-[11px] tracking-wider" style="color:var(--text-muted)">
            <span>HISTÓRICO DE ATUALIZAÇÕES</span>
            <span class="up-tick"></span>
            <span>SOMENTE VISUALIZAÇÃO</span>
        </div>
    </div>

    <!-- Card -->
    <div class="up-card rounded-3xl shadow-2xl overflow-hidden">

        <div class="up-stripe"></div>

        <div class="p-6 sm:p-8">

            <?php if (empty($uploads)): ?>

                <div class="text-center py-10">
                    <p class="text-sm" style="color:var(--text-muted)">Nenhuma versão foi publicada ainda.</p>
                    <p class="text-xs mt-1" style="color:var(--text-muted)">Assim que o desenvolvedor lançar um upload, ele aparecerá aqui.</p>
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
                            <p class="text-xs" style="color:var(--text-muted)">
                                <?= date('d/m/Y \à\s H:i', strtotime($u['data_hora'])) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>

        <div class="up-stripe"></div>
    </div>

    <p class="text-center text-xs mt-8 font-light" style="color:var(--text-muted)">
        <a href="/login" class="transition-colors" style="color:var(--accent-strong)">&larr; Voltar para o login</a>
    </p>

</div>

</body>
</html>
