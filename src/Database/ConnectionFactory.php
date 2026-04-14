<?php

class ConnectionFactory
{
    public static function create(array $config): PDO
    {
        switch ($config['type']) {
            case 'mssql':
                $dsn = "sqlsrv:Server={$config['connection']['host']};Database={$config['connection']['database']}";
                $pdo = new PDO($dsn, $config['connection']['username'], $config['connection']['password']);
                break;

            case 'mysql':
                $port = $config['connection']['port'] ?? 3306;
                $charset = $config['connection']['charset'] ?? 'utf8mb4';
                $dsn = "mysql:host={$config['connection']['host']};port={$port};dbname={$config['connection']['database']};charset={$charset}";
                $pdo = new PDO($dsn, $config['connection']['username'], $config['connection']['password']);
                break;

            case 'sqlite':
                $dsn = "sqlite:" . $config['connection']['path'];
                $pdo = new PDO($dsn);
                break;

            default:
                throw new InvalidArgumentException("Unsupported DB type: " . $config['type']);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}
