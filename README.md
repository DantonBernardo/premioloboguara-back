# Prêmio Lobo-guará · Backend

API do sistema de avaliação do Prêmio Municipal de Ciência, Tecnologia e Inovação de Guarapuava, desenvolvido pela SECTI.

Client: [premioloboguara-client](https://github.com/DantonBernardo/premioloboguara-client).

## Tecnologias

Laravel 13, PHP, Eloquent, PHPUnit, Laravel Pint, Dompdf e SVGGraph. Desenvolvimento validado com PHP 8.5; consulte `composer.json` e `composer.lock` para os requisitos das dependências.

## Executar localmente

Requisitos: PHP compatível com o lockfile, Composer e extensões exigidas pelas dependências, incluindo PDO SQLite para a configuração local padrão. PostgreSQL também é utilizado na configuração Docker.

```sh
git clone https://github.com/DantonBernardo/premioloboguara-back.git
cd premioloboguara-back
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve
```

A API estará em `http://localhost:8000`. O `.env.example` utiliza SQLite, sessões, cache e filas em banco. Para outro banco, configure as variáveis `DB_*` no `.env` antes das migrations. Inicie o client em outro terminal.

## Seeds

```sh
# Categorias, subcategorias e usuários
php artisan db:seed

# Massa demonstrativa de 200 projetos; disponível em local/testing
php artisan db:seed --class=ProjetoSeeder
```

A massa de projetos inclui grupos diferentes de três avaliadores, notas de exemplo e projetos em diferentes estágios. O seeder de usuários permite e-mails nulos e preserva senhas já alteradas.

Conta administrativa inicial: `scti@guarapuava.pr.gov.br`. A senha inicial dos usuários criados pela seed é `senha123`; altere as credenciais antes de disponibilizar o sistema em produção. Usuários sem e-mail precisam desse dado para acessar pelo login.

## Regras de avaliação

- Cada projeto recebe até três avaliações de avaliadores distintos associados ao projeto.
- O backend valida os critérios e calcula cada nota, limitada a 10 pontos.
- A média do projeto considera somente as avaliações recebidas: soma das notas dividida pela quantidade de avaliações.
- Critérios e limites estão em `config/avaliacao.php`; categorias e subcategorias em `config/categorias.php`.
- Projeto Inovador admite bônus de parceria de até 0,5 ponto, respeitando o teto de 10.
- A habilitação exige média mínima 7 e ausência de zero nos critérios eliminatórios de máximo 3 pontos.
- Justificativas são opcionais. O avaliador consulta e revisa somente sua própria avaliação.
- O código do projeto combina chave de oito dígitos, categoria e subcategoria, com unicidade no banco.

## API

Autenticação por sessão e cookie com proteção CSRF. As rotas ficam em `routes/web.php`, sob `/api`; não há autenticação por token Bearer.

| Rota | Uso |
| --- | --- |
| `GET /api/csrf` | Preparar proteção CSRF |
| `POST /api/auth/login` | Entrar |
| `POST /api/auth/logout` | Sair |
| `GET /api/auth/me` | Usuário da sessão |
| `PATCH /api/configuracoes/perfil` | Atualizar nome |
| `GET /api/admin/dashboard` | Indicadores do painel |
| `GET /api/admin/projetos` | Projetos com busca, categoria e paginação |
| `GET /api/admin/ranking` | Ranking e destaques por categoria |
| `GET /api/admin/relatorios` | Indicadores e classificação com filtros |
| `GET /api/admin/relatorios/pdf` | PDF geral |
| `GET /api/admin/relatorios/pdf?tipo=resultados` | Lista exclusiva dos resultados com três notas |
| `GET /api/avaliador/projetos` | Projetos associados ao avaliador autenticado |
| `GET /api/avaliador/projetos/{id}` | Ficha e critérios da categoria |
| `PUT /api/avaliador/projetos/{id}/avaliacao` | Registrar ou revisar avaliação |

Rotas administrativas exigem administrador ativo; rotas de avaliação exigem o perfil correspondente. Consulte `php artisan route:list --path=api` para a lista completa.

## Verificação

```sh
php artisan test --compact
php vendor/bin/pint --dirty --format agent
```

Os testes cobrem regras de notas, autorização, chaves, seeds, paginação, rankings e exportações em PDF. Utilizam a configuração isolada de `phpunit.xml`.

## Organização

- `app/Services/Avaliacao.php`: validação e cálculo das avaliações.
- `app/Services/Relatorios.php`: análise e indicadores.
- `app/Services/ChaveProjeto.php`: geração das chaves.
- `app/Services/GraficosRelatorio.php`: gráficos para PDF.
- `app/Http/Controllers/`: endpoints.
- `app/Http/Requests/`: validação das requisições.
- `resources/views/relatorios/`: templates dos PDFs geral e de resultados.
- `database/`: migrations, factories e seeders.

## Produção

O Dockerfile possui target `prod` para PHP-FPM. Configure banco, `APP_KEY`, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false` e cookies seguros para HTTPS. O nginx do client encaminha a API ao serviço `backend:9000`; ambos precisam estar na mesma rede.

Execute as migrations no ambiente de destino e permita escrita em `storage` e `bootstrap/cache`. `.env`, banco SQLite, logs e `vendor/` não são versionados. Use as seeds demonstrativas somente em desenvolvimento.
