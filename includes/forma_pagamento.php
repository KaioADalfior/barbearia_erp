<?php
/**
 * includes/forma_pagamento.php
 *
 * Componente reutilizável "Forma de pagamento": mesmas opções, mesmo
 * comportamento (seleção múltipla via checkboxes) e mesmo visual já usados
 * em Financeiro/paginas/financeiro_baixa.php ("Nova Baixa"). Extraído para
 * cá para ser reaproveitado também no modal de Concluir Agendamento e no
 * modal de Receber Fiado, sem duplicar HTML/CSS.
 *
 * Requer o CSS: assets/css/forma-pagamento.css (incluir uma vez por página).
 *
 * Uso:
 *   <?php renderFormaPagamentoCampo('concluir'); ?>
 *   // gera inputs name="concluir-forma[]", ids concluir-forma-pix, etc.
 *
 *   No JS, para ler o que foi marcado:
 *   obterFormasSelecionadas('concluir') // -> ['pix', 'dinheiro']
 */

// Opções únicas — fonte de verdade também usada pelo backend
// (FinanceiroService) e pelo JS (rótulos), para nunca ficarem fora de sincronia.
const FORMAS_PAGAMENTO_LABELS = [
    'pix'      => 'Pix',
    'credito'  => 'Cartão de Crédito',
    'debito'   => 'Cartão de Débito',
    'dinheiro' => 'Dinheiro',
    'boleto'   => 'Boleto',
];

/**
 * Ícones (mesmos SVGs do módulo Cadastrar Baixa), indexados pela mesma
 * chave usada em FORMAS_PAGAMENTO_LABELS.
 */
function formaPagamentoIconeSvg(string $chave): string
{
    $icones = [
        'pix' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9.2 4.5 4.5 9.2a2.2 2.2 0 0 0 0 3.1l7.2 7.2a2.2 2.2 0 0 0 3.1 0l4.7-4.7a2.2 2.2 0 0 0 0-3.1l-7.2-7.2a2.2 2.2 0 0 0-3.1 0Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M9 9.5h1.4a1.6 1.6 0 0 1 0 3.2H9M9 9.5v6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',
        'credito' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="6" width="18" height="12.5" rx="2" stroke="currentColor" stroke-width="1.5"/>
                <path d="M3 10h18" stroke="currentColor" stroke-width="1.5"/>
                <path d="M6.5 14.5h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>',
        'debito' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="6" width="18" height="12.5" rx="2" stroke="currentColor" stroke-width="1.5"/>
                <path d="M6.5 15h11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>',
        'dinheiro' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="2.5" y="6.5" width="19" height="11" rx="1.8" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.4"/>
            </svg>',
        'boleto' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 5v14M7.5 5v14M9.5 5v14M13 5v14M15 5v14M16.8 5v14M20 5v14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>',
    ];

    return $icones[$chave] ?? '';
}

/**
 * Imprime o grid de checkboxes "Forma de pagamento".
 *
 * @param string $prefix       Prefixo único de IDs/name neste formulário (ex: 'baixa', 'concluir', 'fiado').
 * @param array  $selecionadas Chaves já marcadas (ex: ao reabrir um lançamento para edição).
 * @param bool   $comLabel     Se deve imprimir o <label> "Forma de pagamento" acima do grid.
 */
function renderFormaPagamentoCampo(string $prefix, array $selecionadas = [], bool $comLabel = true): void
{
    $prefixHtml = htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="mb-2">
        <?php if ($comLabel): ?>
            <label class="field-label block mb-2">Forma de pagamento <span class="text-zinc-500 normal-case">(selecione uma ou mais)</span></label>
        <?php endif; ?>
        <div class="forma-grid" id="<?= $prefixHtml ?>-forma-grid">
            <?php foreach (FORMAS_PAGAMENTO_LABELS as $chave => $label): ?>
                <?php $id = $prefixHtml . '-forma-' . $chave; ?>
                <input type="checkbox" class="forma-check" id="<?= $id ?>"
                       name="<?= $prefixHtml ?>-forma[]" value="<?= $chave ?>"
                       <?= in_array($chave, $selecionadas, true) ? 'checked' : '' ?>>
                <label for="<?= $id ?>" class="forma-label">
                    <span class="forma-label__check"></span>
                    <span class="forma-label__icone"><?= formaPagamentoIconeSvg($chave) ?></span>
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p id="<?= $prefixHtml ?>-forma-erro" class="text-xs mt-2 hidden valor-saida">Selecione ao menos uma forma de pagamento.</p>
    </div>
    <?php
}

/**
 * Imprime o helper JS global compartilhado (obterFormasSelecionadas,
 * validarFormasSelecionadas, limparFormasSelecionadas). Chamar apenas UMA
 * vez por página (em qualquer ponto do HTML), nunca a partir de um
 * endpoint AJAX/JSON — este arquivo é incluído por ambos, então a marcação
 * abaixo só é emitida quando esta função é chamada explicitamente.
 */
function renderFormaPagamentoAssets(): void
{
    ?>
    <script>
        if (typeof window.obterFormasSelecionadas !== 'function') {
            window.obterFormasSelecionadas = function (prefix) {
                return Array.from(document.querySelectorAll('#' + prefix + '-forma-grid .forma-check:checked'))
                    .map(function (el) { return el.value; });
            };

            window.validarFormasSelecionadas = function (prefix) {
                const erro = document.getElementById(prefix + '-forma-erro');
                const ok = window.obterFormasSelecionadas(prefix).length > 0;
                if (erro) erro.classList.toggle('hidden', ok);
                return ok;
            };

            window.limparFormasSelecionadas = function (prefix) {
                document.querySelectorAll('#' + prefix + '-forma-grid .forma-check').forEach(function (el) { el.checked = false; });
                const erro = document.getElementById(prefix + '-forma-erro');
                if (erro) erro.classList.add('hidden');
            };

            // Bloqueia/libera a seleção de forma de pagamento — usado quando
            // o toggle "Fiado" está ligado (a forma só é escolhida depois,
            // no recebimento em Financeiro > Fiados).
            window.definirFormasHabilitadas = function (prefix, habilitado) {
                const grid = document.getElementById(prefix + '-forma-grid');
                if (!grid) return;
                grid.classList.toggle('is-disabled', !habilitado);
                grid.querySelectorAll('.forma-check').forEach(function (el) { el.disabled = !habilitado; });
                if (!habilitado) window.limparFormasSelecionadas(prefix);
            };
        }
    </script>
    <?php
}
