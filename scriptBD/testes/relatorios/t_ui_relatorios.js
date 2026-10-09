// UI da tela Financeiro > Relatórios: relatórios automáticos, filtro, visualizar e falhas com "Tentar novamente"
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const AQUI = __dirname;
const APP = process.env.BARBERP_APP || path.resolve(__dirname, '../../..');
const NODE_MODS = process.env.TEST_NODE_MODULES || '/tmp/mdb/node_modules'; // tailwind/sweetalert2 locais (sem CDN)
const { execSync } = require('child_process');
const BASE='http://127.0.0.1:8088';
const ENVV={...process.env,DB_HOST:'127.0.0.1;port=3307',DB_NAME:'alexbarber',DB_USER:'t',DB_PASS:'tpass'};
const q = s => execSync(`mariadb -h127.0.0.1 -P3307 -ut -ptpass alexbarber -N -B -e "${s}"`).toString().trim();
const sh = c => execSync(c,{env:ENVV,cwd:APP}).toString();
let falhas=0; const ok=(n,c,x='')=>{console.log((c?'PASS':'FAIL')+' - '+n+(x?' | '+x:'')); if(!c) falhas++;};
(async()=>{
 q("DELETE FROM LoginTentativas; DROP TRIGGER IF EXISTS trel_falha");
 sh('python3 ' + AQUI + '/t_rel_seed.py');
 const PED=q("SELECT id_barbeiro FROM Barbeiro WHERE login='pedro'");
 q("UPDATE Barbeiro SET criado_em='2026-10-06 03:36:18' WHERE login IN ('joao','pedro')");
 q("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL; DELETE FROM FinanceiroRelatoriosAuto");
 q("CREATE TRIGGER trel_falha BEFORE INSERT ON FinanceiroRelatorios FOR EACH ROW SET NEW.id_barbeiro = IF(NEW.id_barbeiro = "+PED+", NULL, NEW.id_barbeiro)");
 try { sh('php Financeiro/cron/relatorios_diarios.php --agora="2026-10-09 00:00:05" || true'); } catch(e){}
 const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium',args:['--no-sandbox']});
 async function novo(login,senha){
  const ctx=await b.newContext({viewport:{width:1280,height:900}});
  await ctx.route('**/cdn.tailwindcss.com/**',r=>r.fulfill({contentType:'application/javascript',path:NODE_MODS+'/@tailwindcss/browser/dist/index.global.js'}));
  await ctx.route('**/fonts.googleapis.com/**',r=>r.fulfill({contentType:'text/css',body:''}));
  await ctx.route('**/cdn.jsdelivr.net/**',r=>{ if(r.request().url().includes('sweetalert2')) return r.fulfill({contentType:'application/javascript',path:NODE_MODS+'/sweetalert2/dist/sweetalert2.all.min.js'}); r.fulfill({contentType:'application/javascript',body:'window.Chart=function(){return {destroy(){},update(){}}};'})});
  const p=await ctx.newPage(); p.errs=[]; p.on('pageerror',e=>p.errs.push(e.message));
  await p.goto(BASE+'/login'); await p.fill('#login',login); await p.fill('#senha',senha);
  await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);
  return p;
 }
 // ---- dono
 let p=await novo('barbeiro','Barbeiro@123');
 await p.goto(BASE+'/financeiro/relatorios'); await p.waitForSelector('#tbody-relatorios tr',{timeout:5000});
 ok('Dono: lista mostra o relatório automático com selo "Automático"', (await p.locator('#tbody-relatorios .badge-auto').count())>=1);
 ok('Dono: botão Visualizar (abre PDF inline) e Baixar presentes', (await p.locator('#tbody-relatorios a[title="Visualizar PDF"]').count())>=1 && (await p.locator('#tbody-relatorios a[title="Baixar PDF"]').count())>=1);
 ok('Dono: link de visualizar usa inline=1 e abre em nova aba', (await p.locator('#tbody-relatorios a[title="Visualizar PDF"]').first().getAttribute('href')).includes('inline=1') && (await p.locator('#tbody-relatorios a[title="Visualizar PDF"]').first().getAttribute('target'))==='_blank');
 ok('Dono: período e "Gerado em" visíveis', (await p.locator('#tbody-relatorios tr').first().innerText()).match(/\d{2}\/\d{2}\/\d{4}/)!==null);
 const total=await p.locator('#tbody-relatorios tr').count();
 await p.selectOption('#filtro-origem','manual');
 ok('Filtro "Gerados por mim" esconde os automáticos', (await p.locator('#tbody-relatorios .badge-auto').count())===0);
 await p.selectOption('#filtro-origem','automatico');
 ok('Filtro "Automáticos" mostra só os automáticos', (await p.locator('#tbody-relatorios tr').count())>=1 && (await p.locator('#tbody-relatorios tr').count())===(await p.locator('#tbody-relatorios .badge-auto').count()));
 await p.selectOption('#filtro-origem','todos');
 ok('Filtro "Todos" volta à lista completa', (await p.locator('#tbody-relatorios tr').count())===total);
 ok('Dono: sem painel de falhas (não há falhas dele)', await p.locator('#painel-falhas').isHidden());
 // PDF aberto pelo link (mesma sessão) é um PDF
 const href=await p.locator('#tbody-relatorios a[title="Visualizar PDF"]').first().getAttribute('href');
 const resp=await p.request.get(BASE+href);
 ok('Link Visualizar entrega application/pdf', resp.status()===200 && (resp.headers()['content-type']||'').startsWith('application/pdf'));
 await p.screenshot({path:'/tmp/ui_relatorios_dono.png'});
 ok('Sem erros JS (dono)', p.errs.length===0, p.errs.join(';'));
 await p.context().close();
 // ---- profissional com falha
 p=await novo('pedro','Pedro@1234');
 await p.goto(BASE+'/financeiro/relatorios'); await p.waitForTimeout(1200);
 ok('Funcionário afetado vê o painel de falhas', await p.locator('#painel-falhas').isVisible());
 ok('...com o período e o botão "Tentar novamente"', (await p.locator('#lista-falhas li').first().innerText()).includes('08/10/2026') && (await p.locator('#lista-falhas button').count())===1);
 ok('...sem vazar dados (nada de R$ nem caminhos)', !/R\$|\/tmp|tpass/.test(await p.locator('#painel-falhas').innerText()));
 await p.screenshot({path:'/tmp/ui_relatorios_falha.png'});
 await p.click('#lista-falhas button'); await p.waitForTimeout(1200);
 ok('Tentar novamente com o defeito ainda presente: painel continua e mostra aviso', await p.locator('#painel-falhas').isVisible());
 q("DROP TRIGGER trel_falha");
 await p.click('#lista-falhas button'); await p.waitForTimeout(1500);
 ok('Corrigido o defeito: falha some e o relatório aparece como Automático', await p.locator('#painel-falhas').isHidden() && (await p.locator('#tbody-relatorios .badge-auto').count())>=1);
 ok('BD: estado da falha = ok', q("SELECT status FROM FinanceiroRelatoriosAuto WHERE id_barbeiro="+PED)==='ok');
 // excluir (confirmação com Swal tematizado)
 await p.locator('#tbody-relatorios button[title="Excluir relatório"]').first().click(); await p.waitForTimeout(500);
 const bg=await p.evaluate(()=>{const e=document.querySelector('.swal2-popup'); return e?getComputedStyle(e).backgroundColor:'';});
 ok('Confirmação de exclusão abre com o fundo do tema (não azul fixo)', bg!=='' && bg!=='rgb(24, 35, 56)', bg);
 await p.keyboard.press('Escape');
 ok('Sem erros JS (funcionário)', p.errs.length===0, p.errs.join(';'));
 await b.close();
 // limpeza do que o teste criou
 q("DROP TRIGGER IF EXISTS trel_falha");
 sh('python3 ' + AQUI + '/t_rel_seed.py limpar');
 q("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL; DELETE FROM FinanceiroRelatoriosAuto");
 console.log(falhas?('\n'+falhas+' FALHA(S)'):'\nTODOS OK'); process.exit(falhas?1:0);
})().catch(e=>{console.error(e); try{q("DROP TRIGGER IF EXISTS trel_falha")}catch(_){} process.exit(2);});
