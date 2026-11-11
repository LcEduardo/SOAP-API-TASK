<?php

namespace App\Soap;

use App\Controller\ControllerTask;
use SoapFault;

// Adaptador entre o SoapServer e o ControllerTask: o controller continua
// falando status/body, e aqui isso vira resposta SOAP ou SoapFault.
class TaskService
{
    private ControllerTask $controllerTask;

    public function __construct(ControllerTask $controllerTask) {
        $this->controllerTask = $controllerTask;
    }

    // Em document/literal wrapped o SoapServer entrega o <getTask> como objeto ($params->id).
    // Sem WSDL (rpc) ele entrega o <id> direto, e um <getTask/> vazio chama sem argumentos:
    // o "= null" evita o ArgumentCountError e deixa a validação abaixo responder.
    public function getTask($params = null): array {
        $id = is_object($params) ? ($params->id ?? null) : $params;
        $id = is_string($id) ? trim($id) : $id;

        // Mesma regra do bot: "abc" ou "1.5" chegariam no Postgres como erro de tipo
        if (!is_string($id) || !ctype_digit($id)) {
            throw new SoapFault('Client', 'The id must be a positive integer');
        }

        $response = $this->controllerTask->getTask($id);

        if ($response['status'] !== 200) {
            // 4xx é culpa de quem chamou (Client), 5xx é do servidor (Server)
            $code = $response['status'] >= 500 ? 'Server' : 'Client';
            throw new SoapFault($code, $response['body']['error']);
        }

        return ['task' => $response['body']];
    }
}
