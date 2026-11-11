# SOAP sem WSDL

O `soap/server.php` usa o modo com WSDL. Para ver o modo sem WSDL, basta trocar o primeiro parâmetro do `SoapServer`:

```php
// com WSDL (o que está em soap/server.php)
$server = new SoapServer(__DIR__ . '/tasks.wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);

// sem WSDL
$server = new SoapServer(null, ['uri' => 'urn:TaskService']);
```

O `tests/Unit/Soap/TaskServiceNoWsdlTest.php` monta o servidor desse segundo jeito, então o modo sem WSDL continua testado.

O `setObject(...)` e o `handle()` continuam iguais. O que muda é **como** o `SoapServer` lê a requisição e monta a resposta.

## O que muda

| | Com WSDL | Sem WSDL |
|---|---|---|
| Estilo | document/literal (definido no `<binding>`) | sempre **rpc/encoded** |
| Opções obrigatórias | caminho do `.wsdl` | `uri` (namespace das respostas) |
| O que `getTask` recebe | um objeto: `$params->id` | os filhos de `<getTask>` como argumentos, na ordem: `getTask("1")` |
| Tipos da resposta | os do schema (`Task` com `id` `xsd:int`, `title` `xsd:string`...) | adivinhados a partir dos valores PHP |
| Array associativo na resposta | vira `<task><id>1</id>...</task>` | vira um `ns2:Map` de pares `<item><key/><value/></item>` |
| Requisição fora do contrato | validada contra o schema | aceita qualquer coisa; só falha ao chamar o método |
| Operação inexistente | `Procedure 'deleteTask' not present` | `Function 'deleteTask' doesn't exist` (e o PHP encerra com *fatal error* depois de enviar o fault) |
| Cliente | `new SoapClient('.../tasks.wsdl')` e pronto | precisa saber `location` e `uri` por fora: `new SoapClient(null, ['location' => ..., 'uri' => 'urn:TaskService'])` |
| Cache de WSDL | precisa de `cache_wsdl` | não se aplica |

## Exemplo de resposta

Requisição:

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="urn:TaskService">
  <soapenv:Body>
    <tns:getTask><id>1</id></tns:getTask>
  </soapenv:Body>
</soapenv:Envelope>
```

Com WSDL:

```xml
<ns1:getTaskResponse>
  <ns1:task>
    <ns1:id>1</ns1:id>
    <ns1:title>Create login page</ns1:title>
    ...
  </ns1:task>
</ns1:getTaskResponse>
```

Sem WSDL (o mesmo `['task' => [...]]` retornado pelo `TaskService`):

```xml
<ns1:getTaskResponse>
  <return xsi:type="ns2:Map">
    <item>
      <key xsi:type="xsd:string">task</key>
      <value xsi:type="ns2:Map">
        <item>
          <key xsi:type="xsd:string">id</key>
          <value xsi:type="xsd:int">1</value>
        </item>
        <item>
          <key xsi:type="xsd:string">title</key>
          <value xsi:type="xsd:string">Create login page</value>
        </item>
        <item>
          <key xsi:type="xsd:string">description</key>
          <value xsi:nil="true"/>
        </item>
        ...
      </value>
    </item>
  </return>
</ns1:getTaskResponse>
```

O elemento se chama `return` porque não há WSDL dizendo o nome. E `ns2:Map` (`http://xml.apache.org/xml-soap`) é um formato do Apache SOAP que clientes .NET/Java normalmente não entendem sem configuração extra.

## Bug encontrado: `<getTask/>` sem `<id>`

Com WSDL, um `<getTask/>` vazio ainda chega como objeto (sem `id`), e o `TaskService` responde `Client: The id must be a positive integer`.

Sem WSDL, o `SoapServer` chamava `getTask()` **sem nenhum argumento**. Isso gerava um `ArgumentCountError`, que virava:

```xml
<faultcode>SOAP-ENV:Server</faultcode>
<faultstring>Too few arguments to function App\Soap\TaskService::getTask(), 0 passed and exactly 1 expected</faultstring>
```

Esse fault tem dois problemas: diz que o erro é do servidor (quando é de quem chamou) e expõe detalhes internos do código. A correção foi `getTask($params = null)` em `soap/TaskService.php`. O teste de regressão é `TaskServiceNoWsdlTest::testMissingIdIsClientFaultWithoutLeakingPhpError`.

## Conclusão

Sem WSDL dá menos trabalho no começo (não precisa escrever o `.wsdl`), mas o serviço perde o contrato. Os clientes não conseguem gerar código, os tipos ficam por conta do PHP, e as respostas saem num formato (`ns2:Map`) pouco portável. Para uma API que outros sistemas vão consumir, o modo com WSDL é o mais indicado.
