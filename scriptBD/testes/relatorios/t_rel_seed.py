"""Dados de teste de relatórios — todos marcados com [TREL] e removidos só por essa marca."""
import os, sys; sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from t_lib import sql

D1 = '2026-10-07'   # quarta: dia com todos os tipos de lançamento
D2 = '2026-10-08'   # quinta: muitos lançamentos (várias páginas)
TAG = '[TREL]'


def limpar():
    sql("DELETE FROM FinanceiroRecebimentos WHERE idLancamento IN (SELECT idLancamento FROM FinanceiroLancamentos WHERE titulo LIKE '[[]TREL]%' OR titulo LIKE '\\[TREL]%')")
    sql("DELETE FROM Comissoes WHERE cliente_nome LIKE '\\[TREL]%'")
    sql("DELETE FROM FinanceiroLancamentos WHERE titulo LIKE '\\[TREL]%'")
    # relatórios/estado automático gerados pelos testes (sempre sobre as datas de teste)
    sql("DELETE FROM FinanceiroRelatorios WHERE chave_auto IS NOT NULL OR data_fim BETWEEN '2026-10-07' AND '2026-10-08'")
    sql("DELETE FROM FinanceiroRelatoriosAuto")


def lanc(idb, tipo, titulo, valor, forma, data, origem='manual', status='pago', pago=None, desc=None):
    d = "'" + desc.replace("'", "''") + "'" if desc else 'NULL'
    pago = valor if (pago is None and status == 'pago') else (pago or 0)
    q = ("INSERT INTO FinanceiroLancamentos (id_barbeiro,origem,tipo,titulo,descricao,valor,valor_pago,forma_pagamento,status,data) "
         "VALUES (%d,'%s','%s','%s',%s,%s,%s,'%s','%s','%s'); SELECT LAST_INSERT_ID()"
         % (idb, origem, tipo, titulo.replace("'", "''"), d, valor, pago, forma, status, data))
    lid = int(sql(q)[0][0])
    if status == 'pago' and valor > 0:
        sql("INSERT INTO FinanceiroRecebimentos (idLancamento,id_barbeiro,tipo,valor,forma_pagamento,data) VALUES (%d,%d,'%s',%s,'%s','%s')"
            % (lid, idb, tipo, valor, forma, data))
    return lid


def ids():
    r = dict((l, int(i)) for i, l in sql("SELECT id_barbeiro, login FROM Barbeiro WHERE login IN ('barbeiro','joao','pedro')"))
    return r['barbeiro'], r['joao'], r['pedro']


def semear(idDono=None, idFunc=None):
    idDono, idFunc, _ = ids()
    limpar()
    # --- dono, dia D1
    lanc(idDono, 'entrada', TAG + ' Corte + barba (cliente com nome bem comprido para testar a quebra de linha da descrição no PDF sem cortar)', 80.00, 'pix', D1)
    lanc(idDono, 'entrada', TAG + ' Corte social', 45.50, 'dinheiro', D1)
    lanc(idDono, 'saida', TAG + ' Compra de pomadas e navalhas', 120.35, 'debito', D1)
    lanc(idDono, 'entrada', TAG + ' Pacote mensal', 150.00, 'credito', D1)
    lanc(idDono, 'entrada', TAG + ' Fiado do João', 50.00, 'pix', D1, status='pendente', pago=0)
    # atendimento do funcionário: dono fica com a sobra (100 - 40% = 60)
    lid = lanc(idFunc, 'entrada', TAG + ' Corte (atendimento do funcionário)', 100.00, 'pix', D1, origem='agendamento')
    sql("INSERT INTO Comissoes (idAgendamento,idLancamento,id_barbeiro,cliente_nome,servico_nome,forma_pagamento,data_servico,hora_servico,valor_servico,percentual,valor_comissao,status,pago_em) "
        "VALUES (900001,%d,%d,'%s Cliente A','Corte','pix','%s','10:00:00',100.00,40.00,40.00,'pago','%s 18:00:00')" % (lid, idFunc, TAG, D1, D1))
    lid2 = lanc(idFunc, 'entrada', TAG + ' Barba (atendimento do funcionário)', 50.00, 'dinheiro', D1, origem='agendamento')
    sql("INSERT INTO Comissoes (idAgendamento,idLancamento,id_barbeiro,cliente_nome,servico_nome,forma_pagamento,data_servico,hora_servico,valor_servico,percentual,valor_comissao,status) "
        "VALUES (900002,%d,%d,'%s Cliente B','Barba','dinheiro','%s','11:00:00',50.00,40.00,20.00,'pendente')" % (lid2, idFunc, TAG, D1))
    # --- funcionário: despesa própria
    lanc(idFunc, 'saida', TAG + ' Vale-transporte', 12.00, 'dinheiro', D1)
    # --- dia D2: 60 lançamentos → várias páginas
    for i in range(1, 61):
        lanc(idDono, 'entrada', TAG + ' Atendimento %02d' % i + (' - descrição longa para forçar a quebra de linha sem cortar o texto' if i % 7 == 0 else ''),
             20 + i, ['pix', 'dinheiro', 'credito', 'debito'][i % 4], D2)
    lanc(idDono, 'saida', TAG + ' Aluguel da cadeira', 300.00, 'pix', D2)


if __name__ == '__main__':
    if len(sys.argv) > 1 and sys.argv[1] == 'limpar':
        limpar(); print('limpo')
    else:
        semear(); print('semeado')
