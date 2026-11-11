<?php

use Dotenv\Dotenv;

try {
    // Carrega o .env da raiz (se existir) e exige as variáveis do banco
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
    $dotenv->required(['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS']);

    $dsn = "pgsql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']}";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];

    $db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], $options);
}catch(Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Database connection failed',
        'code' => $e->getCode()
    ]);
    exit;
}
