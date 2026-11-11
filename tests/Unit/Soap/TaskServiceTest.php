<?php

declare(strict_types=1);

namespace Tests\Unit\Soap;

use App\Controller\ControllerTask;
use App\Model\Task;
use App\Soap\TaskService;
use Exception;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use SoapFault;
use SoapServer;

#[RequiresPhpExtension('soap')]
final class TaskServiceTest extends TestCase
{
    private const WSDL = __DIR__ . '/../../../soap/tasks.wsdl';

    private const TASK = [
        'id'          => 1,
        'title'       => 'Create login page',
        'description' => 'Build the login form with validation.',
        'status'      => 'pending',
        'created'     => '2026-11-01 10:00:00',
        'modified'    => '2026-11-01 10:00:00',
        'worker'      => 'Ana',
        'boss'        => 'Carlos',
    ];

    public static function invalidIds(): array
    {
        return [
            'texto'    => ['abc'],
            'vazio'    => [''],
            'negativo' => ['-1'],
            'decimal'  => ['1.5'],
        ];
    }

    // Regressão: id inválido não pode chegar no Postgres (viraria "Database error")
    #[DataProvider('invalidIds')]
    public function testGetTaskThrowsClientFaultWhenIdIsNotNumeric(string $id): void
    {
        $task = $this->createMock(Task::class);
        $task->expects($this->never())->method('getById');

        try {
            (new TaskService(new ControllerTask($task)))->getTask((object) ['id' => $id]);
            $this->fail('SoapFault esperado');
        } catch (SoapFault $e) {
            $this->assertSame('Client', $e->faultcode);
            $this->assertSame('The id must be a positive integer', $e->getMessage());
        }
    }

    // Regressão: requisição sem <id> não pode estourar erro de PHP
    public function testGetTaskThrowsClientFaultWhenIdIsMissing(): void
    {
        $task = $this->createMock(Task::class);
        $task->expects($this->never())->method('getById');

        $this->expectException(SoapFault::class);
        (new TaskService(new ControllerTask($task)))->getTask((object) []);
    }

    // Regressão: task inexistente vira fault Client com a mensagem do model
    public function testGetTaskThrowsClientFaultWhenTaskDoesNotExist(): void
    {
        $task = $this->createStub(Task::class);
        $task->method('getById')->willThrowException(new Exception('Task with id 999 not found', 404));

        try {
            (new TaskService(new ControllerTask($task)))->getTask((object) ['id' => '999']);
            $this->fail('SoapFault esperado');
        } catch (SoapFault $e) {
            $this->assertSame('Client', $e->faultcode);
            $this->assertSame('Task with id 999 not found', $e->getMessage());
        }
    }

    // Regressão: erro de banco é fault Server e não vaza detalhes do PDO
    public function testGetTaskThrowsServerFaultOnDatabaseError(): void
    {
        $task = $this->createStub(Task::class);
        $task->method('getById')->willThrowException(new PDOException('SQLSTATE[08006] connection refused'));

        try {
            (new TaskService(new ControllerTask($task)))->getTask((object) ['id' => '1']);
            $this->fail('SoapFault esperado');
        } catch (SoapFault $e) {
            $this->assertSame('Server', $e->faultcode);
            $this->assertSame('Database error', $e->getMessage());
        }
    }

    public function testGetTaskReturnsTaskWrapped(): void
    {
        $task = $this->createStub(Task::class);
        $task->method('getById')->willReturn(self::TASK);

        $resposta = (new TaskService(new ControllerTask($task)))->getTask((object) ['id' => '1']);

        $this->assertSame(['task' => self::TASK], $resposta);
    }

    // Ponta a ponta pelo SoapServer com o WSDL real (sem HTTP e sem banco)
    public function testSoapServerAnswersGetTaskEnvelope(): void
    {
        $task = $this->createStub(Task::class);
        $task->method('getById')->willReturn(self::TASK);

        $xml = $this->handle($task, '1');

        $this->assertStringContainsString('<ns1:getTaskResponse>', $xml);
        $this->assertStringContainsString('<ns1:title>Create login page</ns1:title>', $xml);
        $this->assertStringContainsString('<ns1:boss>Carlos</ns1:boss>', $xml);
    }

    public function testSoapServerReturnsFaultEnvelopeWhenTaskDoesNotExist(): void
    {
        $task = $this->createStub(Task::class);
        $task->method('getById')->willThrowException(new Exception('Task with id 999 not found', 404));

        $xml = $this->handle($task, '999');

        $this->assertStringContainsString('<faultcode>SOAP-ENV:Client</faultcode>', $xml);
        $this->assertStringContainsString('<faultstring>Task with id 999 not found</faultstring>', $xml);
    }

    private function handle(Task $task, string $id): string
    {
        $envelope = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="urn:TaskService">
                <soapenv:Body>
                    <tns:getTask><tns:id>{$id}</tns:id></tns:getTask>
                </soapenv:Body>
            </soapenv:Envelope>
            XML;

        $server = new SoapServer(self::WSDL, ['cache_wsdl' => WSDL_CACHE_NONE]);
        $server->setObject(new TaskService(new ControllerTask($task)));

        ob_start();
        $server->handle($envelope);
        return (string) ob_get_clean();
    }
}
