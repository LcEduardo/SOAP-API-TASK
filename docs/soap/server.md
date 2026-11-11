# How the SOAP server works

This explains the three lines at the end of [`soap/server.php`](../../soap/server.php) that create the SOAP server, give it the object that does the work, and process one request:

```php
$server = new SoapServer(__DIR__ . '/tasks.wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
$server->setObject(new TaskService(new ControllerTask(new Task($db))));
$server->handle();
```

---

## Line 1: Create the server from the contract

```php
$server = new SoapServer(__DIR__ . '/tasks.wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
```

### `SoapServer`

This class comes from PHP's `ext-soap` extension, which is why `composer.json` requires `ext-soap`. It does all the protocol work for you:

- reads the incoming XML envelope,
- works out which operation is being called,
- turns the XML into PHP values,
- calls your PHP method,
- turns your return value back into XML,
- turns exceptions into `<SOAP-ENV:Fault>` responses.

### First argument: the WSDL path

`__DIR__ . '/tasks.wsdl'` is the absolute path to [`soap/tasks.wsdl`](../../soap/tasks.wsdl). `__DIR__` means the folder of the current file, so the path works no matter which directory PHP was started from.

The WSDL is the **contract**. `SoapServer` reads it to learn:

| WSDL section | What the server learns from it |
|---|---|
| `<types>` | The shapes of the data: `getTask` takes an `id` string; `getTaskResponse` holds a `task` of type `Task` with `id`, `title`, `status`, etc. |
| `<message>` / `<portType>` | There is one operation, `getTask`, with an input message and an output message. |
| `<binding>` | `style="document"` and `use="literal"`: the body is plain XML that follows the schema, with no SOAP-encoding type tags. |
| `<service>` | The public address, `http://localhost:8000/soap/server.php`. |

Because the server is in **WSDL mode**, it checks requests against this contract. A call to an operation the WSDL doesn't declare (say `deleteTask`) gets a Fault back, even if the PHP class had such a method.

You can also pass `null` instead of a WSDL. That's "non-WSDL mode", where you set `uri` yourself and lose the typed contract. Using a WSDL is the better choice, because clients (SoapUI, .NET, Java) can generate their code from it.

### Second argument: `['cache_wsdl' => WSDL_CACHE_NONE]`

By default PHP caches the parsed WSDL (in `/tmp`, controlled by the `soap.wsdl_cache_*` settings in php.ini) so it doesn't re-parse the file on every request. The catch is that **edits to `tasks.wsdl` may not show up** until the cache expires (one day by default). That's a classic way to lose an hour wondering why a new field isn't in the response.

`WSDL_CACHE_NONE` turns the cache off, which is the right setting during development. The other options:

- `WSDL_CACHE_DISK`: cache to files
- `WSDL_CACHE_MEMORY`: cache in process memory
- `WSDL_CACHE_BOTH`: both

In production you might switch to `WSDL_CACHE_DISK` (or `BOTH`) for speed, but then you have to clear the cache whenever the WSDL changes.

---

## Line 2: Plug in the object that answers the calls

```php
$server->setObject(new TaskService(new ControllerTask(new Task($db))));
```

### Read it from the inside out (dependency injection)

1. **`new Task($db)`**: the **Model**. It gets the PDO connection `$db` from `config/database.php` and runs the SQL.
2. **`new ControllerTask(...)`**: the **Controller**, the core of the project. It returns `['status' => 200, 'body' => [...]]`-style arrays.
3. **`new TaskService(...)`**: the **SOAP adapter** ([`soap/TaskService.php`](../../soap/TaskService.php)). It converts the controller's status/body result into either a SOAP response or a `SoapFault`.

So the business logic stays in the Controller and Model, and SOAP is just a thin adapter on top. That's the main reason `TaskService` exists: `ControllerTask` doesn't need to know anything about SOAP.

### What `setObject` does

It tells the server: "when operation X is called, call method X **on this instance**." The WSDL operation name `getTask` maps to `TaskService::getTask()`. The match is by name, so the method name has to match the WSDL operation.

Compared with the alternatives:

- **`setClass(TaskService::class, $arg1, ...)`**: the server builds the object itself, and only when a request arrives. That's awkward when the constructor needs a chain of dependencies like this one.
- **`addFunction('getTask')`**: exposes plain global functions instead of methods.
- **`setObject($instance)`**: you build the object yourself, fully wired. It fits constructor injection best, and it makes testing easy: `tests/Unit/Soap/TaskServiceTest.php` can hand a `SoapServer` a `TaskService` built around a mocked controller, with no database.

### Why the `$params` object in `getTask($params)`

In **document/literal wrapped** style, the whole `<getTask>` element is the single message part (`<part name="parameters" element="tns:getTask"/>`). So PHP passes the method **one object**, a `stdClass` with an `id` property, not the bare `id` value. That's why `TaskService` does `$params->id`.

The return value works the same way in reverse. The method returns `['task' => [...]]` because the WSDL says `getTaskResponse` contains a `task` element, and `SoapServer` turns that array into `<getTaskResponse><task>...</task></getTaskResponse>`.

---

## Line 3: Process the request

```php
$server->handle();
```

This does the actual work for the current HTTP request:

1. **Reads the raw POST body.** With no argument it reads `php://input`. You can also pass an XML string, e.g. `$server->handle($xml)`, which is how the unit test drives the server without HTTP.
2. **Parses the SOAP envelope** and finds `<getTask>` inside `<Body>`.
3. **Decodes** the XML into PHP using the WSDL types (`<id>5</id>` becomes `$params->id = "5"`).
4. **Calls** `$taskService->getTask($params)`.
5. **Encodes** the return value into a `getTaskResponse` envelope, typed per the WSDL (`id` as `xsd:int`, and so on).
6. **Sends** it: it sets `Content-Type: text/xml` and echoes the XML.

If the method throws a **`SoapFault`**, `handle()` catches it and writes a Fault envelope instead, with HTTP status **500**. That's the normal SOAP 1.1 convention: faults go out as 500, and the client tells "Client" faults from "Server" faults by `<faultcode>`. That's why `TaskService` picks `'Client'` for 4xx-type errors and `'Server'` for 5xx-type errors.

One limitation: `handle()` doesn't check the HTTP method, so a GET would get a confusing response. That's why `server.php` rejects anything that isn't POST with a 405 **before** these three lines run (and before connecting to the database). The integration test `tests/Integration/Soap/ServerTest.php` checks that 405.

---

## A full request, start to finish

**Client sends:**

```xml
POST /soap/server.php
Content-Type: text/xml; charset=utf-8
SOAPAction: "urn:TaskService#getTask"

<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                   xmlns:tns="urn:TaskService">
  <SOAP-ENV:Body>
    <tns:getTask><tns:id>5</tns:id></tns:getTask>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

**Inside PHP:**

```
handle()
  → matches <getTask> to the WSDL operation "getTask"
  → TaskService::getTask( stdClass{ id: "5" } )
      → checks "5" is all digits
      → ControllerTask::getTask("5")
          → Task model → SELECT ... WHERE id = 5
      ← ['status' => 200, 'body' => [id=>5, title=>..., ...]]
  ← ['task' => [...]]
  → encodes the array as <getTaskResponse>
```

**Server responds (200):**

```xml
<SOAP-ENV:Envelope ...>
  <SOAP-ENV:Body>
    <ns1:getTaskResponse>
      <ns1:task><ns1:id>5</ns1:id><ns1:title>...</ns1:title>...</ns1:task>
    </ns1:getTaskResponse>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

**If the task doesn't exist (500):**

```xml
<SOAP-ENV:Fault>
  <faultcode>SOAP-ENV:Client</faultcode>
  <faultstring>Task not found</faultstring>
</SOAP-ENV:Fault>
```

---

## In short

| Line | Role |
|---|---|
| `new SoapServer(wsdl, options)` | Loads the **contract**: what operations exist and what the data looks like. |
| `setObject(...)` | Plugs in the **implementation**: a fully wired object whose methods match the operations. |
| `handle()` | Runs **one request**: XML in, PHP method call, XML (or Fault) out. |
