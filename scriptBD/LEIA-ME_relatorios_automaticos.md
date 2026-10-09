# Relatórios automáticos (todo dia à 00h, horário de Brasília)

## O que é gerado
Por profissional (cada barbeiro/funcionário recebe o seu, com as mesmas regras do módulo manual):

| Tipo | Quando | Período coberto |
|---|---|---|
| Diário | todo dia, 00h | o **dia anterior inteiro** (00:00:00–23:59:59) |
| Semanal | segunda-feira | semana anterior (segunda a domingo) |
| Mensal | dia 1º | mês anterior inteiro |
| Anual | 1º de janeiro | ano anterior inteiro |

Ficam na tela **Financeiro > Relatórios** (a mesma lista dos manuais, com o selo "Automático"; há um filtro e o botão "Visualizar"). Falhas aparecem num aviso no topo da lista, com o botão "Tentar novamente" (cada um vê só as próprias). O tipo "Período" continua sendo só manual.

## Como funciona em produção (Docker/EasyPanel + Nixpacks)
O app é construído pelo Nixpacks (`nixpacks.toml`) e sobe `php-fpm` + `nginx` num único container. Não há cron na imagem e o container roda em UTC. Por isso foi usado um **agendador dedicado**, iniciado junto com o container (não depende de ninguém abrir o sistema):

* `nixpacks.toml` (`[start] cmd`) — e `.nixpacks/Dockerfile`, mantido igual — agora também inicia `Financeiro/cron/agendador.php` em segundo plano, num loop que o reinicia se cair. Como o comando é executado a cada start do container, **o agendamento sobrevive a reinícios e novos deploys**.
* `agendador.php` converte o relógio para `America/Sao_Paulo` por conta própria (não depende do fuso do container) e chama `Financeiro/cron/relatorios_diarios.php`: à **00:00**, depois **a cada 15 min** (repetir falhas / recuperar o que ficou pendente) e uma vez ~45 s depois de subir (recupera uma virada de dia perdida durante um deploy).
* **Sem duplicidade / sem concorrência**: trava de arquivo (um único agendador por container), `GET_LOCK` no MySQL (dois containers nunca rodam juntos) e chave única no banco (`barbeiro|tipo|início|fim`). A rotina é idempotente: rodar várias vezes só gera o que falta.
* **Falhas**: uma falha não impede as demais; fica registrada em `FinanceiroRelatoriosAuto` (tentativas, motivo sem dados pessoais), é repetida a cada ≥ 10 min até 5 vezes, depois fica visível na tela para "Tentar novamente".
* **Logs**: saem no log do container (`[agendador]` e `[relatorios-auto]`), só com ids e contagens — sem nomes, valores ou credenciais. Um resumo (apenas contagens) vai ao canal Discord de relatórios, se configurado.
* A pasta `Financeiro/cron/` é bloqueada na web (nginx e `.htaccess`) e os scripts recusam execução fora da linha de comando.

## O que aplicar em produção (nada é aplicado automaticamente)
1. **Backup do banco** antes do deploy.
2. (Opcional) rodar `scriptBD/atualizacao_relatorios_automaticos.sql` — aditivo e idempotente. Se não rodar, o próprio sistema cria as colunas/tabela na primeira execução (precisa de permissão `ALTER`/`CREATE` para o usuário do banco).
3. Fazer commit/push e **redeploy** no EasyPanel (o `nixpacks.toml` alterado entra no build).
4. Nenhuma variável nova é obrigatória. Opcionais: `RELATORIOS_AGENDADOR=0` desliga o agendador interno (use se preferir o Cron Job do EasyPanel, abaixo).

## Como validar em produção
1. **Logs do container** (EasyPanel > serviço > Logs), logo após o deploy: `[agendador] iniciado (pid …, fuso America/Sao_Paulo)` e, ~45 s depois, `disparando relatórios (recuperação após iniciar)` seguido das linhas `[relatorios-auto] ok …` / `fim gerados=… falhas=…`.
   * Se aparecer `AVISO: php CLI nao encontrado`, a imagem não tem o `php` de linha de comando no PATH: use a alternativa abaixo.
2. **Console do serviço** (terminal do EasyPanel): `php /app/Financeiro/cron/relatorios_diarios.php --dry-run` mostra o que seria gerado sem gravar nada; sem `--dry-run` gera de fato (idempotente).
3. **Uma instância só**: `pgrep -fa agendador.php` deve listar exatamente um processo `php`.
4. **Na manhã seguinte** (ou após a primeira meia-noite): em Financeiro > Relatórios deve haver um relatório "Automático" do dia anterior para cada profissional, e nos logs a linha `[agendador] disparando relatórios (meia-noite)` às 00:00.
5. Se a data/hora do banco estiver errada, confira `time_zone` (a conexão força `-03:00`, ver `config/config.php`).

## Alternativa: Cron Job do EasyPanel
Se preferir não usar o processo interno: defina `RELATORIOS_AGENDADOR=0` no serviço e crie um Cron Job do EasyPanel no mesmo serviço com o comando `php /app/Financeiro/cron/relatorios_diarios.php` e a agenda `*/15 * * * *` (a rotina é idempotente; o primeiro disparo depois das 00h gera o dia anterior). Confirme na sua versão do EasyPanel em qual fuso a agenda é interpretada — o que importa é que rode ao menos uma vez após a meia-noite de Brasília. Use **só um** dos dois mecanismos.

## Comandos úteis
```
php Financeiro/cron/relatorios_diarios.php                       # rodada normal
php Financeiro/cron/relatorios_diarios.php --dry-run             # só simula
php Financeiro/cron/relatorios_diarios.php --dias=7              # recupera até 7 dias atrás
php Financeiro/cron/relatorios_diarios.php --forcar              # ignora espera/limite de tentativas
php Financeiro/cron/relatorios_diarios.php --barbeiro=3          # só um profissional
```
Códigos de saída: 0 ok · 1 alguma falha (as demais foram geradas) · 2 sem banco · 64 argumento inválido.

## Logo nos relatórios
Vem da vitrine pública (Catálogo > Configurar): nome, endereço, telefone e logo de `CatalogoConfig`. A imagem é lida de `assets/uploads/catalogo/`; se o arquivo sumiu num deploy, usa a cópia guardada no banco (`ArquivoUpload`). É redimensionada (máx. 600 px) preservando a proporção e embutida no PDF (precisa da extensão PHP GD). Sem logo, ou com qualquer problema na imagem, o cabeçalho usa um monograma com a inicial da barbearia — o relatório nunca deixa de ser emitido.

## Limitações conhecidas
* Os PDFs ficam no banco (`FinanceiroRelatorios.arquivo_pdf`, ~20–50 KB cada): 1 diário por profissional por dia. Não há política de retenção automática — o usuário pode excluir pela tela; considere uma limpeza periódica se o volume crescer.
* O sistema atende uma barbearia por instalação: o "isolamento por estabelecimento" é o isolamento por banco/instalação, e por profissional dentro dela.
* Um relatório excluído pelo usuário não é recriado automaticamente (para recriar, gere manualmente).
