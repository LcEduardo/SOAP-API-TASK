<?php

declare(strict_types=1);

namespace Tests\Integration\Soap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// Sobe o servidor embutido do PHP de verdade, porque o que importa aqui é o status HTTP.
// Não usa o banco: a checagem do método roda antes da conexão.
#[RequiresPhpExtension('soap')]
final class ServerTest extends TestCase
{
    private static $process;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        // Porta livre escolhida pelo sistema operacional
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $root = dirname(__DIR__, 3);
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, '-t', $root];
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        self::$process = proc_open($command, [1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, $root);

        // Espera o servidor aceitar conexões (até 5s)
        for ($i = 0; $i < 50; $i++) {
            $conn = @fsockopen('127.0.0.1', self::$port);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(100_000);
        }

        self::fail('O servidor embutido do PHP não subiu');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
    }

    public static function nonPostMethods(): array
    {
        return [
            'GET'    => ['GET'],
            'PUT'    => ['PUT'],
            'DELETE' => ['DELETE'],
            'PATCH'  => ['PATCH'],
        ];
    }

    // Regressão: sem a checagem, o SoapServer respondia 500 com um Fault genérico
    #[DataProvider('nonPostMethods')]
    public function testNonPostMethodReturns405(string $method): void
    {
        [$status, $headers, $body] = $this->request($method);

        $this->assertSame(405, $status);
        $this->assertSame('POST', $headers['allow'] ?? null);
        $this->assertStringContainsString('<faultcode>SOAP-ENV:Client</faultcode>', $body);
        $this->assertStringContainsString('<faultstring>Method not allowed. Use POST</faultstring>', $body);
    }

    private function request(string $method): array
    {
        $context = stream_context_create(['http' => [
            'method'        => $method,
            'ignore_errors' => true, // não lança erro em 4xx/5xx, para podermos ler a resposta
            'timeout'       => 5,
        ]]);

        $body = (string) @file_get_contents('http://127.0.0.1:' . self::$port . '/soap/server.php', false, $context);
        $rawHeaders = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : $http_response_header;

        preg_match('#^HTTP/\S+ (\d{3})#', $rawHeaders[0] ?? '', $m);

        $headers = [];
        foreach (array_slice($rawHeaders, 1) as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            $headers[strtolower($name)] = $value;
        }

        return [(int) ($m[1] ?? 0), $headers, $body];
    }
}
