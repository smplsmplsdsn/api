<?php

$dsn = 'mysql:host=mysql;dbname=' . getenv('MYSQL_DATABASE') . ';charset=utf8mb4';

try {
    $pdo = new PDO(
        $dsn,
        getenv('MYSQL_USER'),
        getenv('MYSQL_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    echo 'API OK<br>';
    echo 'MySQL OK';
} catch (PDOException $e) {
    echo 'MySQL ERROR: ' . $e->getMessage();
}