import os, re, json, subprocess, sys, datetime, urllib.parse
import requests

APP = os.environ.get('BARBERP_APP') or os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..'))
BASE = __import__('os').environ.get('BASE', 'http://127.0.0.1:8088')
DBC = ['mariadb', '-h127.0.0.1', '-P3307', '-ut', '-ptpass', 'alexbarber', '-N', '-B', '-e']
ENV = {'DB_HOST': '127.0.0.1;port=3307', 'DB_NAME': 'alexbarber', 'DB_USER': 't', 'DB_PASS': 'tpass'}
RES = []


def sql(q):
    out = subprocess.run(DBC + [q], capture_output=True, text=True)
    if out.returncode:
        raise RuntimeError(out.stderr)
    return [l.split('\t') for l in out.stdout.strip().split('\n') if l]


def php(code):
    import os
    env = dict(os.environ, **ENV)
    out = subprocess.run(['php', '-r', 'require "' + APP + '/config/config.php"; require "' + APP + '/includes/FinanceiroService.php"; ' + code],
                         capture_output=True, text=True, env=env, cwd=APP)
    return (out.stdout + out.stderr).strip()


def check(nome, cond, extra=''):
    RES.append((nome, bool(cond)))
    print(('PASS' if cond else 'FAIL'), '-', nome, ('| ' + str(extra)) if extra != '' else '')


class CsrfSession(requests.Session):
    """Como o navegador: todo POST sai com X-CSRF-Token (o script de sidebar-script.php faz isso no fetch)."""
    sem_csrf = False
    _tok = None

    def request(self, method, url, **kw):
        if method.upper() == 'POST' and not self.sem_csrf and '/Autenticacao/' not in url:
            if not self._tok:
                for pg in ('/inicio', '/painel'):
                    t = super().request('GET', BASE + pg, allow_redirects=False)
                    m = re.search(r'var token = "([0-9a-f]+)"', t.text)
                    if m:
                        self._tok = m.group(1)
                        break
            if self._tok:
                kw['headers'] = dict(kw.get('headers') or {}, **{'X-CSRF-Token': self._tok})
        return super().request(method, url, **kw)


class S:
    def __init__(self):
        self.s = CsrfSession()

    def get(self, path, **kw):
        return self.s.get(BASE + path, allow_redirects=kw.pop('allow_redirects', False), **kw)

    def token(self, path='/servicos'):
        r = self.get(path)
        m = re.search(r'name="_csrf" value="([0-9a-f]+)"', r.text)
        return m.group(1) if m else None

    def post(self, path, data, tokpath='/servicos'):
        data = dict(data)
        data['_csrf'] = self.token(tokpath)
        return self.s.post(BASE + path, data=data, allow_redirects=False)

    def login(self, login, senha):
        r = self.get('/login')
        tok = re.search(r'name="_csrf" value="([0-9a-f]+)"', r.text).group(1)
        r = self.s.post(BASE + '/Autenticacao/scripts/auth.php', data={'login': login, 'senha': senha, '_csrf': tok}, allow_redirects=False)
        return r.headers.get('Location', '')




def resumo():
    ok = sum(1 for _, c in RES if c)
    print('\n%d/%d testes OK' % (ok, len(RES)))
    for n, c in RES:
        if not c:
            print('FALHOU:', n)
    return ok == len(RES)


def hashpw(p):
    return subprocess.run(['php', '-r', 'echo password_hash($argv[1], PASSWORD_DEFAULT);', p], capture_output=True, text=True).stdout
