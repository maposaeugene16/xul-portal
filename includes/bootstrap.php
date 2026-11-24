<?php
declare(strict_types=1);
date_default_timezone_set(getenv('SCHOOL_TIMEZONE') ?: 'Africa/Harare');
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = require __DIR__ . '/../config.php';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
