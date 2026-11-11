<?php

declare(strict_types=1);

namespace Tests\Unit\Soap;

use App\Controller\ControllerTask;
use App\Model\Task;
use App\Soap\TaskService;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use SoapServer;

// Mesmo TaskService, mas com o SoapServer em modo sem WSDL (rpc/encoded),
// igual ao soap/server.php desta branch.
#[RequiresPhpExtension('soap')]
final class TaskServiceNoWsdlTest extends TestCase
{
    private const TASK = [
        'id'          => 1,
        'title'       => 'Create login page',
        'description' => null,
        'status'      => 'pending',
        'created'     => '2026-11-01 10:00:00',
        'modified'    => '2026-11-01 10:00:00',
        'worker'      => 'Ana',
        'boss'        => 'Carlos',
    ];

    // Sem WSDL não há tipo Task: o array associativo vira um ns2:Map de <item><key/><value/></item>
    public function testResponseIsEncodedAsMap(): void
    {
        $task = $this->createStub(Task::class);
        $task->method('getById')->willReturn(self::TASK);

        $xml = $this->handle($task, '<tns:getTask><id>1</id></tns:getTask>');

        $this->assertStringContainsString('<ns1:getTaskResponse>', $xml);
        $this->assertStringContainsString('<return xsi:type="ns2:Map">', $xml);
        $this->assertStringContainsString(
            '<key xsi:type="xsd:string">title</key><value xsi:type="xsd:string">Create login page</value>',
            $xml
        );
    }

    // Regressão: sem WSDL, um <getTask/> vazio chamava getTask() sem argumentos e o
    // fault Server vazava "Too few arguments to function App\Soap\TaskService::getTask()"
    public function testMissingIdIsClientFaultWithoutLeakingPhpError(): void
    {
        $task = $this->createMock(Task::class);
        $task->expects($this->never())->method('getById');

        $xml = $this->handle($task, '<tns:getTask/>');

        $this->assertStringContainsString('<faultcode>SOAP-ENV:Client</faultcode>', $xml);
        $this->assertStringContainsString('<faultstring>The id must be a positive integer</faultstring>', $xml);
        $this->assertStringNotContainsString('Too few arguments', $xml);
    }

    private function handle(Task $task, string $body): string
    {
        $envelope = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="urn:TaskService">
                <soapenv:Body>{$body}</soapenv:Body>
            </soapenv:Envelope>
            XML;

        $server = new SoapServer(null, ['uri' => 'urn:TaskService']);
        $server->setObject(new TaskService(new ControllerTask($task)));

        ob_start();
        $server->handle($envelope);
        return (string) ob_get_clean();
    }
}
