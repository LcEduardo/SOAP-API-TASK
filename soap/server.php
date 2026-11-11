<?php

// Endpoint SOAP. Com "php -S localhost:8000":
//   POST http://localhost:8000/soap/server.php

require __DIR__ . '/../vendor/autoload.php';

// SOAP só aceita POST. Sem isso, o SoapServer responde a GET/PUT/... com 200.
// Fica antes do database.php para não abrir conexão à toa.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>'
       . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
       . '<SOAP-ENV:Body><SOAP-ENV:Fault>'
       . '<faultcode>SOAP-ENV:Client</faultcode>'
       . '<faultstring>Method not allowed. Use POST</faultstring>'
       . '</SOAP-ENV:Fault></SOAP-ENV:Body>'
       . '</SOAP-ENV:Envelope>';
    exit;
}

require __DIR__ . '/../config/database.php';

use App\Controller\ControllerTask;
use App\Model\Task;
use App\Soap\TaskService;

$server = new SoapServer(__DIR__ . '/tasks.wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
$server->setObject(new TaskService(new ControllerTask(new Task($db))));
$server->handle();
