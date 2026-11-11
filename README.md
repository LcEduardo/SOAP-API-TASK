# Serviço SOAP de Tasks

Serviço SOAP 1.1 (document/literal) em PHP puro com PostgreSQL para consultar tarefas (`tasks`).

> Quer entender como o servidor funciona? Veja [docs/soap/server.md](docs/soap/server.md) (com WSDL) e [docs/soap/sem-wsdl.md](docs/soap/sem-wsdl.md) (sem WSDL).

## Requisitos

| Ferramenta | Versão | Observação |
|---|---|---|
| PHP | **8.3 ou mais nova** (testado com 8.3 e 8.4) | Precisa das extensões `pdo_pgsql`, `pgsql` e `soap` ativas |
| PostgreSQL | qualquer versão atual | Banco `task` |
| Composer | 2.x | Instala o autoload e o Phinx |
| Phinx | 0.16 | Ferramenta de migrations, instalada pelo Composer |
| PHPUnit | 12 | Testes automatizados, instalado pelo Composer (só em desenvolvimento) |

Para conferir a versão do PHP e as extensões:

```
php -v
php -m
```

> **Por que 8.3?** O `composer.json` tem `"config": { "platform": { "php": "8.3.0" } }`. Isso faz o Composer escolher só pacotes que funcionam no PHP 8.3, mesmo que ele esteja rodando num PHP mais novo (ex.: 8.4 do Herd). Sem isso, ele poderia instalar pacotes que exigem 8.4 e o projeto quebraria em quem usa 8.3. Não remova essa configuração sem combinar com o time.

## Estrutura

```
.
├── composer.json              # Configuração do autoload PSR-4 e dependências (Phinx, phpdotenv)
├── .env.example               # Modelo das variáveis de ambiente (copie para .env)
├── .env                       # Suas credenciais do banco (NÃO vai para o git)
├── phinx.php                  # Configuração das migrations (conexão com o banco)
├── phpunit.xml                # Configuração dos testes
├── tests/                     # namespace Tests
│   ├── Unit/                  # Testes de unidade (não usam o banco)
│   └── Integration/           # Testes que sobem o servidor embutido do PHP
├── docs/soap/                 # Explicação do SoapServer (com e sem WSDL)
├── db/
│   ├── migrations/            # Migrations: cada arquivo é uma mudança no banco
│   └── seeds/                 # Seeds: dados de exemplo
├── config/
│   └── database.php           # Conexão com o banco via PDO
├── soap/                      # namespace App\Soap (protocolo SOAP)
│   ├── tasks.wsdl             # Contrato SOAP: operação getTask
│   ├── TaskService.php        # Converte a resposta do ControllerTask em resposta SOAP / SoapFault
│   └── server.php             # Endpoint SOAP (POST)
└── src/
    ├── Controller/            # namespace App\Controller
    │   ├── ControllerBoss.php     # Monta a resposta de bosses (corpo + status HTTP)
    │   ├── ControllerTask.php     # Monta a resposta de tasks (corpo + status HTTP)
    │   └── ControllerWorker.php   # Monta a resposta de workers (corpo + status HTTP)
    └── Model/                 # namespace App\Model
        ├── Model.php              # Classe base com getById e create genéricos
        ├── Boss.php               # Consultas SQL de bosses
        ├── Task.php               # Consultas SQL de tasks
        └── Worker.php             # Consultas SQL de workers
```

## Autoload (Composer PSR-4)

As classes são carregadas automaticamente pelo Composer, sem `require` manual. O `composer.json` liga cada namespace a uma pasta:

| Namespace | Pasta |
|---|---|
| `App\Controller\` | `src/Controller/` |
| `App\Model\` | `src/Model/` |
| `App\Soap\` | `soap/` |

Para uma classe nova ser encontrada:

- o arquivo deve ter **o mesmo nome da classe** (`class Task` → `Task.php`);
- o arquivo deve declarar o namespace da pasta (`namespace App\Model;`);
- classes nativas do PHP usadas dentro do namespace precisam de `use` (`use PDO;`, `use Exception;`).

Se você mudar o mapeamento no `composer.json`, rode `composer dump-autoload`.

## Como rodar

1. Na raiz do projeto, instale o autoload (gera a pasta `vendor/`, que não vai para o git):

   ```
   composer install
   ```

2. Crie o banco `task` no PostgreSQL. Copie `.env.example` para `.env` e preencha suas credenciais:

   ```
   cp .env.example .env
   ```

   O `.env` está no `.gitignore`: a senha nunca vai para o repositório. Em produção, você pode definir as variáveis (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`) direto no servidor em vez de usar o arquivo. Se faltar alguma, o servidor responde 500 e o Phinx mostra qual variável está faltando.
3. Crie as tabelas e insira os dados de exemplo:

   ```
   php vendor/bin/phinx migrate
   php vendor/bin/phinx seed:run
   ```
4. Na raiz do projeto, suba o servidor embutido do PHP:

   ```
   php -S localhost:8000
   ```

5. Teste com `POST http://localhost:8000/soap/server.php` (veja o exemplo na seção abaixo).

## SOAP

A operação disponível é `getTask`, via SOAP 1.1 (document/literal). Precisa da extensão `soap` ativa no `php.ini` (`extension=soap`).

As chamadas são feitas via `POST http://localhost:8000/soap/server.php`. O contrato (WSDL) é o arquivo `soap/tasks.wsdl`.

Requisição de exemplo (para Postman, SoapUI etc., com header `Content-Type: text/xml` e `SOAPAction: urn:TaskService#getTask`):

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="urn:TaskService">
  <soapenv:Body>
    <tns:getTask><tns:id>1</tns:id></tns:getTask>
  </soapenv:Body>
</soapenv:Envelope>
```

Erros voltam como `SoapFault`:

| faultcode | Quando |
|---|---|
| `Client` | id não é inteiro positivo, ou a task não existe |
| `Server` | Falha no banco |

Qualquer método que não seja POST (GET, PUT, DELETE...) recebe **405 Method Not Allowed**, com o header `Allow: POST` e um Fault `Client`.

## Migrations (Phinx)

| Comando | O que faz |
|---|---|
| `php vendor/bin/phinx create NomeDaMudanca` | Cria um arquivo de migration novo em `db/migrations/` |
| `php vendor/bin/phinx migrate` | Aplica as migrations pendentes |
| `php vendor/bin/phinx rollback` | Desfaz a última migration |
| `php vendor/bin/phinx status` | Mostra quais migrations já rodaram |
| `php vendor/bin/phinx seed:run` | Insere os dados de exemplo |

Como funciona:

- O servidor (`config/database.php`) e as migrations (`phinx.php`) leem as credenciais do mesmo `.env` (via `vlucas/phpdotenv`, que coloca as variáveis em `$_ENV`). Para mudar as credenciais, mude só o `.env`.
- O Phinx guarda na tabela `phinxlog` quais migrations já rodaram. Por isso `migrate` só aplica as que ainda faltam.
- A primeira migration (`CreateInitialTables`) cria as tabelas `workers`, `bosses` e `tasks`.

Regras para o dia a dia:

- **Toda mudança no banco vira uma migration nova** (`phinx create`). Não altere as tabelas direto no banco.
- **Não edite uma migration que já rodou** em algum banco. Para corrigir algo, crie outra migration.
- **Banco que já tinha as tabelas** (criadas antes das migrations): rode `php vendor/bin/phinx migrate --fake` uma vez. Isso marca a migration inicial como aplicada sem tentar criar as tabelas de novo.
- **Rode `seed:run` só em banco vazio.** Os e-mails são únicos, então rodar de novo dá erro.

## Testes (PHPUnit) e TDD

Para rodar todos os testes:

```
composer test
```

Os testes ficam em `tests/`, espelhando as pastas do código: o teste de `src/Controller/ControllerWorker.php` fica em `tests/Unit/Controller/ControllerWorkerTest.php`.

- O nome do arquivo e da classe termina em `Test` e a classe estende `PHPUnit\Framework\TestCase`.
- Cada método de teste começa com `test` e descreve o comportamento (`testGetWorkerReturns400WhenIdIsNotInteger`).
- Testes de unidade **não usam o banco**: o model é trocado por um falso com `createStub` (só devolve valores) ou `createMock` (também confere se foi chamado).

Fluxo do TDD, para cada comportamento novo:

1. **Vermelho:** escreva um teste para o comportamento que ainda não existe e rode `composer test`. Ele deve falhar.
2. **Verde:** escreva o mínimo de código para o teste passar.
3. **Refatorar:** melhore o código sem mudar o comportamento, rodando os testes de novo para garantir que continuam passando.

## Papel de cada camada

- **Endpoint (`soap/server.php`)**: é a porta de entrada. Recusa qualquer método que não seja POST (405), abre a conexão com o banco e entrega a requisição ao `SoapServer`, que lê o WSDL e chama o método certo.
- **Adaptador SOAP (`TaskService`)**: valida o id e converte a resposta do controller (`status` + `body`) em resposta SOAP ou `SoapFault` (`Client` para 4xx, `Server` para 5xx).
- **Controller (`ControllerTask`, `ControllerWorker`, `ControllerBoss`)**: recebe o model pronto, chama a consulta e transforma o resultado em resposta (`status` + `body`). É quem trata as exceções vindas do model e decide o código (200, 404, 500). Não sabe nada de SOAP.
- **Model (`Task`, `Worker`, `Boss`)**: só conversa com o banco, usando *prepared statements*. Se não encontra o registro, lança uma exceção e deixa o controller decidir o que responder.
