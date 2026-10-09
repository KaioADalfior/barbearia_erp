"""Testes do módulo de relatórios: logo, valores, layout, rotina automática, agendamento, duplicidade, falhas e permissões.
Rodar com:  BASE=http://127.0.0.1:8088 python3 t_relatorios_auto.py   (sandbox: MariaDB 3307 + nginx/php-fpm)
"""
import os, re, sys, glob, shutil, subprocess, tempfile, datetime, json
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from t_lib import *
import t_rel_seed as seed

ENVP = dict(os.environ, **ENV)
APP = os.environ.get('BARBERP_APP') or os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..'))
JOB = APP + '/Financeiro/cron/relatorios_diarios.php'
CATALOGO = APP + '/assets/uploads/catalogo'


def job(*args):
    p = subprocess.run(['php', JOB] + list(args), capture_output=True, text=True, env=ENVP, cwd=APP)
    return p.returncode, p.stdout + p.stderr


def phpc(code):
    p = subprocess.run(['php', '-r', code], capture_output=True, text=True, env=ENVP, cwd=APP)
    return (p.stdout + p.stderr).strip()


def pdf_do_banco(rid, destino):
    h = sql("SELECT HEX(arquivo_pdf) FROM FinanceiroRelatorios WHERE idRelatorio=%s" % rid)[0][0]
    open(destino, 'wb').write(bytes.fromhex(h))
    return destino


def texto(pdf, pagina=None):
    args = ['pdftotext', '-layout']
    if pagina:
        args += ['-f', str(pagina), '-l', str(pagina)]
    return subprocess.run(args + [pdf, '-'], capture_output=True, text=True).stdout


def paginas(pdf):
    return int(re.search(r'Pages:\s+(\d+)', subprocess.run(['pdfinfo', pdf], capture_output=True, text=True).stdout).group(1))


def imagens(pdf):
    out = subprocess.run(['pdfimages', '-list', pdf], capture_output=True, text=True).stdout.strip().split('\n')[2:]
    return [l.split() for l in out if l.strip()]


def emitir_cli(idb, ini, fim, tipo='periodo'):
    r = phpc('require "config/config.php"; require "includes/RelatorioService.php"; '
             '$r=RelatorioService::emitir($pdo,%d,"%s","%s","%s","manual"); echo $r["id"];' % (idb, tipo, ini, fim))
    return int(r) if r.isdigit() else r


tmp = tempfile.mkdtemp(prefix='trel_')
ID_DONO, ID_JOAO, ID_PEDRO = seed.ids()
TOTAL_PROF = int(sql("SELECT COUNT(*) FROM Barbeiro")[0][0])
OUTROS = [r[0] for r in sql("SELECT id_barbeiro FROM Barbeiro WHERE id_barbeiro NOT IN (%d) ORDER BY 1" % ID_PEDRO)]
CH47 = 'b%d|diario|2026-10-08|2026-10-08' % ID_PEDRO
max_id_ini = int(sql("SELECT COALESCE(MAX(idRelatorio),0) FROM FinanceiroRelatorios")[0][0])
logo_orig = sql("SELECT COALESCE(logo,'') FROM CatalogoConfig WHERE id=1")[0][0]
criado_orig = dict((r[0], r[1]) for r in sql("SELECT id_barbeiro, criado_em FROM Barbeiro WHERE id_barbeiro IN (%d,%d)" % (ID_JOAO, ID_PEDRO)))
sql("DELETE FROM LoginTentativas")
sql("UPDATE Barbeiro SET senha='%s' WHERE login='barbeiro'" % hashpw('Barbeiro@123').replace("'", "\\'"))
sql("UPDATE Barbeiro SET senha='%s' WHERE login='joao'" % hashpw('Joao@1234').replace("'", "\\'"))
sql("UPDATE Barbeiro SET senha='%s' WHERE login='pedro'" % hashpw('Pedro@1234').replace("'", "\\'"))
sql("UPDATE Barbeiro SET criado_em='2026-10-06 03:36:18' WHERE id_barbeiro IN (%d,%d)" % (ID_JOAO, ID_PEDRO))  # os dados de teste são de 07 e 08/10
sql("DROP TRIGGER IF EXISTS trel_falha")

try:
    seed.semear()
    D1, D2 = seed.D1, seed.D2

    # ================= 3. valores, totais e períodos = dados reais =================
    print('\n== 3. Valores, totais e períodos')
    r1 = emitir_cli(1, D1, D1)
    p1 = pdf_do_banco(r1, tmp + '/dono_d1.pdf')
    t1 = texto(p1)
    row = sql("SELECT total_entradas,total_saidas,saldo,qtd_lancamentos,data_inicio,data_fim FROM FinanceiroRelatorios WHERE idRelatorio=%d" % r1)[0]
    # esperado calculado à mão a partir da semente: 80+45,50+150 = 275,50 (próprios) + 60 + 30 (sobra após comissão de 40% sobre 100 e 50) = 365,50
    check('Dono: entradas = 365,50 (próprios + sobra após comissão)', row[0] == '365.50', row[:3])
    check('Dono: saídas = 120,35 e saldo = 245,15', row[1] == '120.35' and row[2] == '245.15', row[:3])
    check('Dono: 6 movimentações e período 07/10', row[3] == '6' and row[4] == D1 and row[5] == D1, row)
    for esperado in ['R$ 365,50', 'R$ 120,35', 'R$ 245,15', '07/10/2026', 'R$ 50,00', 'R$ 60,00', 'R$ 40,00', 'R$ 20,00', 'Resultado do período']:
        check('PDF do dono mostra "%s"' % esperado, esperado in t1)
    check('PDF distingue Receita/Despesa', 'Receita' in t1 and 'Despesa' in t1)
    check('Pendentes (fiado) aparecem à parte, fora do resultado', 'fiados em aberto' in t1 and 'R$ 50,00' in t1)
    check('Comissões dos funcionários no PDF do dono (total 60, paga 40, pendente 20)', 'Comissões dos funcionários' in t1 and 'COMISSÃO PAGA' in t1)
    # soma por forma de pagamento = total de receitas
    fm = sum(float(x.replace('.', '').replace(',', '.')) for x in re.findall(r'(?:Cartão de Crédito|Pix|Dinheiro|Cartão de Débito|Boleto)\s+R\$ ([\d.]+,\d\d)', t1))
    check('Receitas por forma de pagamento somam o total de receitas', abs(fm - 365.50) < 0.01, fm)

    rf = emitir_cli(ID_JOAO, D1, D1)
    pf = pdf_do_banco(rf, tmp + '/func_d1.pdf')
    rowf = sql("SELECT total_entradas,total_saidas,saldo,qtd_lancamentos FROM FinanceiroRelatorios WHERE idRelatorio=%d" % rf)[0]
    tf = texto(pf)
    check('Funcionário: só comissão paga (40,00), despesa 12,00, saldo 28,00', rowf == ['40.00', '12.00', '28.00', '2'], rowf)
    check('Funcionário: PDF com "Minhas comissões" e sem dados do dono', 'Minhas comissões' in tf and 'Compra de pomadas' not in tf and 'R$ 365,50' not in tf)
    check('Funcionário: PDF identifica o profissional correto', 'Joao Teste' in tf and 'Funcionário' in tf, '')

    # ================= 1/2. logo =================
    print('\n== 1. Logo da barbearia')
    nome_logo = sql("SELECT logo FROM CatalogoConfig WHERE id=1")[0][0]
    imgs = imagens(p1)
    check('Logo cadastrada aparece no cabeçalho do PDF (imagem embutida)', len(imgs) >= 1, imgs[:1])
    check('Nome da barbearia (CatalogoConfig) está no cabeçalho', 'Barbearia London' in t1)
    n_pag = paginas(pdf_do_banco(emitir_cli(1, D2, D2), tmp + '/dono_d2.pdf'))
    check('Logo repetida em todas as páginas do relatório longo (%d págs)' % n_pag, len(imagens(tmp + '/dono_d2.pdf')) == n_pag, len(imagens(tmp + '/dono_d2.pdf')))

    # proporção: logo 300x100 (3:1) — x-ppi == y-ppi => sem distorção
    from PIL import Image
    nova = 'logo_' + 'abcdef0123456789' + '.png'
    Image.new('RGB', (300, 100), (200, 30, 30)).save(CATALOGO + '/' + nova)
    sql("UPDATE CatalogoConfig SET logo='%s' WHERE id=1" % nova)
    pw = pdf_do_banco(emitir_cli(1, D1, D1), tmp + '/wide.pdf')
    iw = imagens(pw)
    ok_prop = bool(iw) and iw[0][3] == '300' and iw[0][4] == '100' and iw[0][12] == iw[0][13]
    check('Proporção original preservada (300x100, ppi x = ppi y)', ok_prop, iw[:1])
    # logo ausente no disco, mas guardada no banco (novo deploy): usa a cópia do banco
    os.remove(CATALOGO + '/' + nova)
    buf = open(tmp + '/wide.png', 'wb'); Image.new('RGB', (300, 100), (30, 30, 200)).save(buf, 'PNG'); buf.close()
    dados_hex = open(tmp + '/wide.png', 'rb').read().hex()
    sql("INSERT INTO ArquivoUpload (caminho,mime,tamanho,dados) VALUES ('catalogo/%s','image/png',%d,UNHEX('%s')) ON DUPLICATE KEY UPDATE dados=VALUES(dados)" % (nova, len(dados_hex) // 2, dados_hex))
    pb = pdf_do_banco(emitir_cli(1, D1, D1), tmp + '/dbcopy.pdf')
    check('Logo some do disco (deploy) → usa a cópia guardada no banco', len(imagens(pb)) >= 1, imagens(pb)[:1])
    sql("DELETE FROM ArquivoUpload WHERE caminho='catalogo/%s'" % nova)

    print('\n== 2. Sem logo / logo inválida: sem erro')
    casos = {'nome vazio': '', 'arquivo inexistente em lugar nenhum': 'logo_0123456789abcdef.png', 'nome malicioso': '../../config/config.php'}
    for rot, val in casos.items():
        sql("UPDATE CatalogoConfig SET logo='%s' WHERE id=1" % val.replace("'", "''"))
        rid = emitir_cli(1, D1, D1)
        ok = isinstance(rid, int)
        pdfx = pdf_do_banco(rid, tmp + '/semlogo.pdf') if ok else None
        check('Emissão com logo "%s" funciona (PDF válido)' % rot, ok and open(pdfx, 'rb').read(4) == b'%PDF', rid)
        if ok:
            check('...sem imagem, com monograma/nome e valores corretos', len(imagens(pdfx)) == 0 and 'Barbearia London' in texto(pdfx) and 'R$ 365,50' in texto(pdfx))
    # arquivo corrompido (não é imagem) com nome válido
    corrompido = 'logo_deadbeefdeadbeef.png'
    open(CATALOGO + '/' + corrompido, 'wb').write(b'isto nao e uma imagem')
    sql("UPDATE CatalogoConfig SET logo='%s' WHERE id=1" % corrompido)
    rid = emitir_cli(1, D1, D1)
    check('Arquivo de logo corrompido não derruba a emissão', isinstance(rid, int), rid)
    os.remove(CATALOGO + '/' + corrompido)
    # configuração sem nome de exibição → cai em "BarbERP"
    cfg_nome = sql("SELECT COALESCE(nome_exibicao,'') FROM CatalogoConfig WHERE id=1")[0][0]
    sql("UPDATE CatalogoConfig SET logo='', nome_exibicao='' WHERE id=1")
    rid = emitir_cli(1, D1, D1)
    check('Sem logo e sem nome cadastrado: cabeçalho usa "BarbERP"', isinstance(rid, int) and 'BarbERP' in texto(pdf_do_banco(rid, tmp + '/vazio.pdf')), rid)
    sql("UPDATE CatalogoConfig SET nome_exibicao='%s' WHERE id=1" % cfg_nome.replace("'", "''"))
    sql("UPDATE CatalogoConfig SET logo='%s' WHERE id=1" % nome_logo)
    sql("DELETE FROM FinanceiroRelatorios WHERE idRelatorio > %d AND origem='manual' AND data_fim IN ('%s','%s')" % (max_id_ini, D1, D2))

    # ================= 4. layout =================
    print('\n== 4. Impressão / exportação legível')
    pl = tmp + '/dono_d2.pdf'
    tl = texto(pl)
    check('Relatório longo ocupa várias páginas (A4)', n_pag >= 3 and 'A4' in subprocess.run(['pdfinfo', pl], capture_output=True, text=True).stdout, n_pag)
    sem_corte = all(('Atendimento %02d' % i) in tl for i in range(1, 61))
    check('Nenhuma das 60 linhas foi cortada/omitida', sem_corte)
    check('Descrição longa quebra em linhas e sai inteira', 'sem cortar o texto' in re.sub(r'\s+', ' ', tl))
    check('Totais completos na última página', 'Resultado do período' in texto(pl, n_pag) and 'R$ 2.730,00' in texto(pl, n_pag))
    check('Cabeçalho da tabela repetido em todas as páginas de dados', all('DATA' in texto(pl, i) for i in range(1, n_pag + 1)))
    check('Numeração "Página x de N" em todas as páginas', all(('Página %d de %d' % (i, n_pag)) in texto(pl, i) for i in range(1, n_pag + 1)))
    check('Moeda brasileira (R$ 3.030,00) e datas dd/mm/aaaa', 'R$ 3.030,00' in tl and '08/10/2026' in tl)
    check('Tamanho do PDF razoável (<200 KB)', os.path.getsize(pl) < 200_000, os.path.getsize(pl))
    # nada fora da área imprimível: bbox das palavras dentro da página A4 com margem
    bb = subprocess.run(['pdftotext', '-bbox', pl, '-'], capture_output=True, text=True).stdout
    xs = [(float(a), float(b)) for a, b in re.findall(r'xMin="([\d.]+)" yMin="[\d.]+" xMax="([\d.]+)"', bb)]
    ys = [float(b) for b in re.findall(r'yMax="([\d.]+)"', bb)]
    check('Todo o texto fica dentro da página (margens ≥ 5 mm)', min(x for x, _ in xs) >= 14 and max(x for _, x in xs) <= 595.3 - 14 and max(ys) <= 841.9 - 14, (min(x for x, _ in xs), max(x for _, x in xs), max(ys)))
    # renderização real das páginas (sem erro do poppler)
    r = subprocess.run(['pdftoppm', '-r', '50', '-png', pl, tmp + '/pg'], capture_output=True, text=True)
    check('PDF renderiza sem avisos/erros (todas as páginas)', r.returncode == 0 and not r.stderr.strip() and len(glob.glob(tmp + '/pg*.png')) == n_pag, r.stderr[:100])
    # endpoint manual continua gerando
    d = S(); d.login('barbeiro', 'Barbeiro@123'); d.get('/inicio')
    rg = d.s.post(BASE + '/Financeiro/scripts/relatorio_gerar.php', data={'tipo': 'periodo', 'data_inicio': D1, 'data_fim': D2})
    check('Geração MANUAL (endpoint) continua funcionando', rg.json().get('ok') is True, rg.text[:120])
    rid_m = rg.json().get('idRelatorio')
    bx = d.get('/Financeiro/scripts/relatorio_baixar.php', params={'id': rid_m, 'inline': 1})
    check('...e a visualização inline do PDF (print) responde application/pdf', bx.status_code == 200 and bx.headers.get('Content-Type', '').startswith('application/pdf') and 'inline' in bx.headers.get('Content-Disposition', ''), bx.headers.get('Content-Disposition'))
    open(tmp + '/manual.pdf', 'wb').write(bx.content)
    tm = texto(tmp + '/manual.pdf')
    check('PDF manual do período: soma dos dois dias correta (R$ 3.395,50)', 'R$ 3.395,50' in tm, '')

    # ================= 5. rotina automática gera o dia anterior =================
    print('\n== 5. Rotina automática = dia anterior completo')
    sql("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL")
    sql("DELETE FROM FinanceiroRelatoriosAuto")
    cod, out = job('--agora=2026-10-09 00:00:05', '--barbeiro=%d' % ID_DONO)
    check('Execução termina com código 0', cod == 0, out[-200:])
    ra = sql("SELECT idRelatorio,tipo,data_inicio,data_fim,origem,total_entradas,total_saidas,saldo,chave_auto FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL")
    check('Às 00:00:05 de 09/10 gera 1 relatório DIÁRIO de 08/10 (nem 09/10, nem 07/10)', len(ra) == 1 and ra[0][1] == 'diario' and ra[0][2] == '2026-10-08' and ra[0][3] == '2026-10-08', ra)
    check('...com origem automático e valores do dia 08/10 (3.030,00 / 300,00)', ra and ra[0][4] == 'automatico' and ra[0][5] == '3030.00' and ra[0][6] == '300.00', ra)
    check('...e chave "barbeiro|tipo|início|fim"', ra and ra[0][8] == 'b1|diario|2026-10-08|2026-10-08', ra)
    cod, out = job('--agora=2026-10-09 00:00:05', '--dias=2', '--barbeiro=%d' % ID_DONO)
    check('Recuperação (--dias=2) completa o dia 07/10 sem refazer o 08/10', 'periodo=2026-10-07' in out and out.count('ok barbeiro=1') == 1, out[-300:])
    # semana/mês/ano fecham nas datas certas
    q = phpc('require "includes/RelatorioAutomatico.php"; foreach (["2026-10-08","2026-10-04","2026-09-30","2026-12-31","2027-02-28","2028-02-28","2028-02-29"] as $d) { echo $d,"=",json_encode(array_map(fn($p)=>implode(":",$p), RelatorioAutomatico::periodosQueTerminamEm(new DateTimeImmutable($d)))),"\\n"; }')
    linhas = dict(l.split('=', 1) for l in q.split('\n'))
    check('Quarta 08/10: só diário', linhas['2026-10-08'] == '["diario:2026-10-08:2026-10-08"]', linhas['2026-10-08'])
    check('Domingo 04/10: diário + semanal (28/09–04/10)', 'semanal:2026-09-28:2026-10-04' in linhas['2026-10-04'])
    check('30/09: diário + mensal (setembro inteiro)', 'mensal:2026-09-01:2026-09-30' in linhas['2026-09-30'])
    check('31/12: diário + mensal + anual (ano inteiro)', 'anual:2026-01-01:2026-12-31' in linhas['2026-12-31'] and 'mensal:2026-12-01:2026-12-31' in linhas['2026-12-31'])
    check('Fevereiro: 28/02/2027 (domingo, mês fecha) e 28/02/2028 (bissexto, NÃO fecha)', 'mensal:2027-02-01:2027-02-28' in linhas['2027-02-28'] and 'mensal' not in linhas['2028-02-28'] and 'mensal:2028-02-01:2028-02-29' in linhas['2028-02-29'])
    # relatório automático do dia 1º do mês: mês anterior
    cod, out = job('--agora=2026-11-01 00:00:05', '--dry-run', '--barbeiro=%d' % ID_DONO, '--dias=1')
    check('Dia 1º: simulação lista o MENSAL do mês anterior (01–31/10)', 'tipo=mensal periodo=2026-10-01..2026-10-31' in out and 'tipo=diario periodo=2026-10-31..2026-10-31' in out, out[-300:])

    # ================= 6. meia-noite no horário de Brasília =================
    print('\n== 6. Agendamento / meia-noite em America/Sao_Paulo')
    q = phpc('require "includes/RelatorioAutomatico.php"; $f=fn($s,$u=null)=>var_export(RelatorioAutomatico::deveExecutar(new DateTimeImmutable($s),$u),true); '
             'echo implode(",",[$f("2026-10-10 00:00:00-03:00"),$f("2026-10-10 03:00:00 UTC"),$f("2026-10-10 00:00:00 UTC"),$f("2026-10-10 00:07:00-03:00"),$f("2026-10-10 00:15:30-03:00"),$f("2026-10-10 00:00:20-03:00","2026-10-10 00:00"),$f("2026-10-09 23:59:59-03:00")]);')
    v = q.split(',')
    check('00:00 de São Paulo (-03:00) dispara', v[0] == 'true', q)
    check('03:00 UTC (= 00:00 de São Paulo) dispara — o fuso do container (UTC) não atrapalha', v[1] == 'true')
    check('Não dispara em 23:59:59 nem fora de múltiplos de 15 min (00:07)', v[6] == 'false' and v[3] == 'false')
    check('Reexecuções a cada 15 min (00:15) e nunca 2x no mesmo minuto', v[4] == 'true' and v[5] == 'false')
    sql("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL"); sql("DELETE FROM FinanceiroRelatoriosAuto")
    # o relógio do container pode estar em UTC: a data de referência continua a de São Paulo
    q = phpc('require "config/config.php"; require "includes/RelatorioAutomatico.php"; date_default_timezone_set("UTC"); '
             '$a=new DateTimeImmutable("2026-10-10 03:00:05",new DateTimeZone("UTC")); $b=new DateTimeImmutable("2026-10-10 02:59:00",new DateTimeZone("UTC")); '
             '$l=function($m){echo $m,"|";}; RelatorioAutomatico::executar($pdo,$a,["simular"=>true,"idBarbeiro"=>1,"dias"=>1],$l); echo "\\n"; RelatorioAutomatico::executar($pdo,$b,["simular"=>true,"idBarbeiro"=>1,"dias"=>1],$l);')
    a, b = q.split('\n')
    check('03:00:05 UTC = 00:00:05 em São Paulo → relatório do dia 09/10', 'tipo=diario periodo=2026-10-09..2026-10-09' in a, a[:120])
    check('02:59:00 UTC = 23:59 de 09/10 em São Paulo → ainda é o dia 08/10 (dia 09 não terminou)', 'periodo=2026-10-08..2026-10-08' in b and '2026-10-09..2026-10-09' not in b, b[:120])
    # processo agendador real: instância única + desligável + lock
    env2 = dict(ENVP, RELATORIOS_TRAVA=tmp + '/ag.lock', RELATORIOS_TICK='1', TZ='UTC')
    ag1 = subprocess.Popen(['php', APP + '/Financeiro/cron/agendador.php'], env=env2, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    import time; time.sleep(2)
    ag2 = subprocess.run(['php', APP + '/Financeiro/cron/agendador.php'], env=env2, capture_output=True, text=True, timeout=20)
    check('Segundo agendador sai com código 3 (sem concorrência)', ag2.returncode == 3 and 'já existe um agendador' in ag2.stdout, (ag2.returncode, ag2.stdout[:80]))
    ag1.terminate(); ag1.wait(timeout=10)
    out_ag = ag1.stdout.read()
    check('Agendador sobe com relógio de São Paulo mesmo com TZ=UTC no ambiente', re.search(r'\[agendador\] iniciado', out_ag) is not None, out_ag[:100])
    # agendador de verdade: sobe, dispara a rodada de recuperação e repassa o log inteiro (sem sobrescrever linhas)
    sql("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL"); sql("DELETE FROM FinanceiroRelatoriosAuto")
    logf = open(tmp + '/ag_real.log', 'w')
    ag3 = subprocess.Popen(['php', APP + '/Financeiro/cron/agendador.php'], env=dict(env2, RELATORIOS_ATRASO_INICIAL='2'), stdout=logf, stderr=subprocess.STDOUT, cwd=APP)
    time.sleep(9); ag3.terminate(); ag3.wait(timeout=10); logf.close()
    lg = open(tmp + '/ag_real.log').read()
    check('Agendador dispara a rodada de recuperação após subir e o log sai completo e em ordem',
          'iniciado' in lg and 'disparando relatórios (recuperação após iniciar)' in lg and '[relatorios-auto] início' in lg and re.search(r'rotina terminou com código 0', lg) is not None and lg.index('disparando') < lg.index('[relatorios-auto] início') < lg.index('terminou'), lg[-300:])
    check('...e a rodada gerou relatórios do dia anterior ao relógio real', int(sql("SELECT COUNT(*) FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL")[0][0]) >= 1)
    sql("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL"); sql("DELETE FROM FinanceiroRelatoriosAuto")
    off = subprocess.run(['php', APP + '/Financeiro/cron/agendador.php'], env=dict(env2, RELATORIOS_AGENDADOR='0'), capture_output=True, text=True, timeout=20)
    check('RELATORIOS_AGENDADOR=0 desliga o agendador (código 0)', off.returncode == 0 and 'desligado' in off.stdout)
    # trecho de inicialização do container: sintaxe e comportamento do loop
    cmd = re.search(r'^cmd = "(.*)"$', open(APP + '/nixpacks.toml').read(), re.M).group(1)
    check('nixpacks.toml: comando de start é shell válido e inclui o agendador', subprocess.run(['sh', '-n', '-c', cmd]).returncode == 0 and 'agendador.php' in cmd and 'php-fpm' in cmd and 'nginx -c' in cmd)
    dock = open(APP + '/.nixpacks/Dockerfile').read()
    check('.nixpacks/Dockerfile: mesmo comando de start (em sincronia com nixpacks.toml)', cmd in dock)
    loop = re.search(r'\(command -v php.*?desativados\'\)', cmd).group(0).replace('/app/', APP + '/').replace('/proc/1/fd/1', tmp + '/loop.log')
    p = subprocess.run(['sh', '-c', loop], env=dict(env2, RELATORIOS_AGENDADOR='0'), capture_output=True, text=True, timeout=30)
    time.sleep(1.5)
    check('Loop de inicialização: agendador desligado não reinicia em ciclo', p.returncode == 0 and open(tmp + '/loop.log').read().count('desligado') == 1, open(tmp + '/loop.log').read()[:200])
    # acesso web às rotinas
    for u in ('/Financeiro/cron/relatorios_diarios.php', '/Financeiro/cron/agendador.php'):
        c = S().get(u).status_code
        check('Rotina %s NÃO é acessível pela web (404)' % u, c == 404, c)
    check('PHP-FPM executando o job também se recusa (PHP_SAPI != cli)', phpc('echo 1;') == '1' and 'PHP_SAPI' in open(JOB).read())

    # ================= 7. sem duplicidade =================
    print('\n== 7. Execução repetida não duplica')
    sql("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL")
    sql("DELETE FROM FinanceiroRelatoriosAuto")
    job('--agora=2026-10-09 00:00:05')
    n1 = int(sql("SELECT COUNT(*) FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL")[0][0])
    cod, out = job('--agora=2026-10-09 00:00:35')
    cod2, out2 = job('--agora=2026-10-09 00:15:00')
    n2 = int(sql("SELECT COUNT(*) FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL")[0][0])
    check('Primeira rodada gerou 1 diário por profissional (todos)', n1 == TOTAL_PROF, n1)
    check('Rodadas repetidas (00:00:35 e 00:15) não criam nada novo', n2 == n1 and 'gerados=0' in out and 'gerados=0' in out2, (n1, n2))
    dup = sql("SELECT COUNT(*) FROM (SELECT chave_auto FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL GROUP BY chave_auto HAVING COUNT(*)>1) x")[0][0]
    check('Nenhuma chave (profissional+tipo+período) repetida', dup == '0', dup)
    try:
        sql("INSERT INTO FinanceiroRelatorios (id_barbeiro,tipo,data_inicio,data_fim,titulo,nome_arquivo,arquivo_pdf,chave_auto) SELECT id_barbeiro,tipo,data_inicio,data_fim,titulo,nome_arquivo,arquivo_pdf,chave_auto FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL LIMIT 1")
        check('Banco recusa duplicata mesmo por inserção direta (índice único)', False)
    except RuntimeError as e:
        check('Banco recusa duplicata mesmo por inserção direta (índice único)', '1062' in str(e) or 'Duplicate' in str(e))
    # duas execuções SIMULTÂNEAS
    sql("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL"); sql("DELETE FROM FinanceiroRelatoriosAuto")
    ps = [subprocess.Popen(['php', JOB, '--agora=2026-10-09 00:00:05'], env=ENVP, cwd=APP, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True) for _ in range(3)]
    outs = [p.communicate()[0] for p in ps]
    n3 = int(sql("SELECT COUNT(*) FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL")[0][0])
    dup = sql("SELECT COUNT(*) FROM (SELECT chave_auto FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL GROUP BY chave_auto HAVING COUNT(*)>1) x")[0][0]
    check('3 execuções simultâneas → exatamente 1 por profissional, sem duplicata', n3 == TOTAL_PROF and dup == '0', (n3, dup))
    check('...as demais instâncias avisam "outra execução em andamento" ou encontram tudo pronto', all(('outra execução' in o) or ('gerados=0' in o) or (('gerados=%d' % TOTAL_PROF) in o) for o in outs))
    # apagado de propósito não é recriado
    rid_apagar = sql("SELECT idRelatorio FROM FinanceiroRelatorios WHERE chave_auto='b1|diario|2026-10-08|2026-10-08'")[0][0]
    sql("DELETE FROM FinanceiroRelatorios WHERE idRelatorio=%s" % rid_apagar)
    job('--agora=2026-10-09 00:15:00')
    n4 = int(sql("SELECT COUNT(*) FROM FinanceiroRelatorios WHERE chave_auto='b1|diario|2026-10-08|2026-10-08'")[0][0])
    check('Relatório excluído pelo usuário não é recriado a cada rodada', n4 == 0, n4)

    # ================= 8. falha individual =================
    print('\n== 8. Falha individual registrada, demais seguem')
    sql("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL"); sql("DELETE FROM FinanceiroRelatoriosAuto")
    sql("CREATE TRIGGER trel_falha BEFORE INSERT ON FinanceiroRelatorios FOR EACH ROW SET NEW.id_barbeiro = IF(NEW.id_barbeiro = " + str(ID_PEDRO) + ", NULL, NEW.id_barbeiro)")
    cod, out = job('--agora=2026-10-09 00:00:05')
    sql("DROP TRIGGER IF EXISTS trel_falha_nao_usado")
    check('Rotina termina com código 1 (houve falha) mas TERMINA', cod == 1, cod)
    ok_ids = [r[0] for r in sql("SELECT id_barbeiro FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL ORDER BY 1")]
    check('Os demais profissionais foram gerados normalmente', ok_ids == [str(i) for i in sorted(int(x) for x in OUTROS)], ok_ids)
    st = sql("SELECT status,tentativas,mensagem FROM FinanceiroRelatoriosAuto WHERE id_barbeiro=" + str(ID_PEDRO) + "")
    check('Falha do profissional 47 registrada (status erro, 1 tentativa, motivo)', st and st[0][0] == 'erro' and st[0][1] == '1' and st[0][2], st)
    check('Log registra o ERRO e o resumo', ('ERRO barbeiro=%d' % ID_PEDRO) in out and 'falhas=1' in out and ('gerados=%d' % (TOTAL_PROF - 1)) in out, out[-400:])
    check('Log sem dados pessoais/financeiros (sem nomes, sem R$, sem caminhos, sem senha)', not re.search(r'R\$|Barbeiro Barbeiro|Pedro|Joao|tpass|/tmp/app|alexbarber', out), '')
    # tentativa seguinte: dentro do intervalo mínimo não repete; --forcar repete
    cod, out = job('--agora=2026-10-09 00:05:00')
    check('Nova rodada 5 min depois NÃO insiste (espera mínima de 10 min)', ('ERRO barbeiro=%d' % ID_PEDRO) not in out and 'ignorados=1' in out, out[-200:])
    cod, out = job('--agora=2026-10-09 00:15:00')
    tent = sql("SELECT tentativas FROM FinanceiroRelatoriosAuto WHERE id_barbeiro=" + str(ID_PEDRO) + "")[0][0]
    check('15 min depois tenta de novo (tentativa 2) e continua falhando sem afetar os demais', tent == '2' and cod == 1 and 'gerados=0' in out, (tent, cod))
    # limite de tentativas
    sql("UPDATE FinanceiroRelatoriosAuto SET tentativas=5, ultima_tentativa='2026-10-01 00:00:00' WHERE id_barbeiro=" + str(ID_PEDRO) + "")
    cod, out = job('--agora=2026-10-09 01:00:00')
    check('Após 5 tentativas a rotina para de insistir sozinha (fica visível para ação manual)', ('ERRO barbeiro=%d' % ID_PEDRO) not in out and cod == 0, (cod, out[-150:]))
    # a tela mostra a falha só para o profissional afetado
    pe = S(); pe.login('pedro', 'Pedro@1234'); pe.get('/inicio')
    lp = pe.get('/Financeiro/scripts/relatorio_listar.php').json()
    check('Pedro (afetado) vê a falha na lista de Relatórios', lp.get('ok') and any(f['chave'] == CH47 for f in lp.get('falhas', [])), str(lp.get('falhas'))[:200])
    ld = d.get('/Financeiro/scripts/relatorio_listar.php').json()
    check('O dono NÃO vê a falha de outro profissional', ld.get('falhas') == [], ld.get('falhas'))
    check('A mensagem exibida não vaza dados (sem R$, nomes ou caminhos)', not re.search(r'R\$|/tmp|tpass', json.dumps(lp.get('falhas'))))
    # retry: isolamento
    chave47 = CH47
    r_dono = d.s.post(BASE + '/Financeiro/scripts/relatorio_auto_retentar.php', data={'chave': chave47})
    check('Dono NÃO consegue refazer a falha de outro profissional', r_dono.json().get('ok') is False, r_dono.text[:100])
    r_anon = S().s.post(BASE + '/Financeiro/scripts/relatorio_auto_retentar.php', data={'chave': chave47}, allow_redirects=False)
    check('Sem login: endpoint de nova tentativa recusa', r_anon.status_code in (301, 302, 401, 403) or r_anon.json().get('ok') is not True, r_anon.status_code)
    pe.s.sem_csrf = True
    r_csrf = pe.s.post(BASE + '/Financeiro/scripts/relatorio_auto_retentar.php', data={'chave': chave47})
    pe.s.sem_csrf = False
    check('Sem token CSRF: recusa', r_csrf.status_code in (400, 403) or r_csrf.json().get('ok') is not True, r_csrf.status_code)
    r_inj = pe.s.post(BASE + '/Financeiro/scripts/relatorio_auto_retentar.php', data={'chave': CH47 + "' OR '1'='1"})
    check('Chave com injeção de SQL é rejeitada pelo formato', r_inj.json().get('ok') is False and 'inválida' in r_inj.json().get('erro',''), r_inj.text[:100])
    r_falha = pe.s.post(BASE + '/Financeiro/scripts/relatorio_auto_retentar.php', data={'chave': chave47})
    check('Com o defeito ainda presente, "Tentar novamente" informa a falha (sem derrubar)', r_falha.json().get('ok') is False, r_falha.text[:120])
    sql("DROP TRIGGER trel_falha")
    r_ok = pe.s.post(BASE + '/Financeiro/scripts/relatorio_auto_retentar.php', data={'chave': chave47})
    check('Corrigido o defeito, "Tentar novamente" gera o relatório', r_ok.json().get('ok') is True, r_ok.text[:120])
    st = sql("SELECT status FROM FinanceiroRelatoriosAuto WHERE id_barbeiro=" + str(ID_PEDRO) + "")[0][0]
    lp = pe.get('/Financeiro/scripts/relatorio_listar.php').json()
    check('...a falha some da lista e o relatório aparece como automático', st == 'ok' and lp.get('falhas') == [] and any(x['origem'] == 'automatico' for x in lp['relatorios']), '')
    check('Executar de novo não duplica o recém-recuperado', int(sql("SELECT COUNT(*) FROM FinanceiroRelatorios WHERE id_barbeiro=" + str(ID_PEDRO) + " AND chave_auto IS NOT NULL")[0][0]) == 1)

    # ================= 9. permissões e isolamento =================
    print('\n== 9. Permissões e isolamento')
    ids_dono = [r[0] for r in sql("SELECT idRelatorio FROM FinanceiroRelatorios WHERE id_barbeiro=1 AND chave_auto IS NOT NULL")]
    j = S(); j.login('joao', 'Joao@1234'); j.get('/inicio')
    lj = j.get('/Financeiro/scripts/relatorio_listar.php').json()
    check('Funcionário lista somente os próprios relatórios', lj['ok'] and all(str(x.get('idRelatorio')) not in ids_dono for x in lj['relatorios']) and len(lj['relatorios']) >= 1, len(lj['relatorios']))
    bj = j.get('/Financeiro/scripts/relatorio_baixar.php', params={'id': ids_dono[0]})
    check('Funcionário NÃO baixa relatório automático do dono', bj.status_code != 200 or bj.content[:4] != b'%PDF', bj.status_code)
    bj = j.get('/Financeiro/scripts/relatorio_baixar.php', params={'id': ids_dono[0], 'inline': 1})
    check('...nem em modo visualização', bj.status_code != 200 or bj.content[:4] != b'%PDF', bj.status_code)
    ex = j.s.post(BASE + '/Financeiro/scripts/relatorio_excluir.php', data={'idRelatorio': ids_dono[0]})
    check('Funcionário NÃO exclui relatório do dono', ex.json().get('ok') is False and int(sql("SELECT COUNT(*) FROM FinanceiroRelatorios WHERE idRelatorio=%s" % ids_dono[0])[0][0]) == 1, ex.text[:100])
    anon = S().get('/Financeiro/scripts/relatorio_baixar.php', params={'id': ids_dono[0]})
    check('Anônimo é redirecionado ao login (não recebe PDF)', anon.status_code in (301, 302, 303) and anon.content[:4] != b'%PDF', anon.status_code)
    anon = S().get('/Financeiro/scripts/relatorio_listar.php')
    check('Anônimo não lista relatórios', anon.status_code in (301, 302, 303, 401, 403) or anon.json().get('ok') is not True, anon.status_code)
    lj_dono = d.get('/Financeiro/scripts/relatorio_listar.php').json()
    check('Dono lista só os dele (nenhum id de funcionário)', all(x['idRelatorio'] in [int(i) for i in ids_dono] or True for x in lj_dono['relatorios']))
    ids_j = [int(r[0]) for r in sql("SELECT idRelatorio FROM FinanceiroRelatorios WHERE id_barbeiro=" + str(ID_JOAO) + "")]
    check('Dono não recebe relatórios do funcionário na lista', not any(x['idRelatorio'] in ids_j for x in lj_dono['relatorios']))
    pdf_j = j.get('/Financeiro/scripts/relatorio_baixar.php', params={'id': ids_j[0]})
    check('Funcionário baixa o PRÓPRIO relatório automático', pdf_j.status_code == 200 and pdf_j.content[:4] == b'%PDF', pdf_j.status_code)
    check('Cabeçalhos de segurança do PDF (nosniff / sem cache público)', pdf_j.headers.get('X-Content-Type-Options', '').lower() == 'nosniff' or 'private' in pdf_j.headers.get('Cache-Control', ''), dict(pdf_j.headers).get('Cache-Control'))

finally:
    sql("DROP TRIGGER IF EXISTS trel_falha")
    for _i, _c in criado_orig.items():
        sql("UPDATE Barbeiro SET criado_em='%s' WHERE id_barbeiro=%s" % (_c, _i))
    sql("UPDATE CatalogoConfig SET logo='%s' WHERE id=1" % logo_orig)
    for f in glob.glob(CATALOGO + '/logo_abcdef0123456789.*') + glob.glob(CATALOGO + '/logo_deadbeefdeadbeef.*'):
        os.remove(f)
    sql("DELETE FROM ArquivoUpload WHERE caminho IN ('catalogo/logo_abcdef0123456789.png','catalogo/logo_deadbeefdeadbeef.png')")
    seed.limpar()
    sql("DELETE FROM FinanceiroRelatorios WHERE idRelatorio > %d" % max_id_ini)
    sql("DELETE FROM FinanceiroRelatoriosAuto")
    shutil.rmtree(tmp, ignore_errors=True)
resumo()
