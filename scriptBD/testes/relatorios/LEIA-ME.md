# Testes automatizados — relatórios (logo, PDF, geração automática, agendamento)

**Rodam SOMENTE em ambiente de teste/sandbox, NUNCA em produção.** Eles criam e apagam dados de teste
(marcados com `[TREL]`), trocam senhas dos usuários `barbeiro`/`joao`/`pedro` e criam/removem um
*trigger* temporário no banco. A conexão está fixa em `127.0.0.1:3307`, banco `alexbarber`, usuário `t`
(ver `ENV` em `t_lib.py`) — é de propósito, para não alcançar o banco de produção.

Pré-requisitos: PHP 8.x CLI com GD, MariaDB/MySQL de teste com o esquema do projeto, nginx + php-fpm servindo o projeto
(`BASE`, padrão `http://127.0.0.1:8088`), `python3` com `requests` e `Pillow`, `poppler-utils` (`pdftotext`, `pdfimages`, `pdftoppm`, `pdfinfo`),
e usuários `barbeiro` (proprietário), `joao` e `pedro` (funcionários) — criados pela suíte de comissões.

```
BASE=http://127.0.0.1:8088 python3 t_relatorios_auto.py   # 109 verificações: logo, valores, layout, rotina, fuso, duplicidade, falhas, permissões
node t_ui_relatorios.js                                    # tela de Relatórios (Playwright; defina TEST_NODE_MODULES/CHROMIUM_PATH se preciso)
```

Não cobertos aqui (dependem do ambiente real): execução do agendador dentro do container do EasyPanel/Nixpacks, o `php` CLI da
imagem de produção e o fuso/entrega do Cron Job do EasyPanel — ver `scriptBD/LEIA-ME_relatorios_automaticos.md`.
