<?php

class ConnectionFactory
{
    public static function create(array $config): PDO
    {
        switch ($config['type']) {
            case 'mssql':
                $port = $config['connection']['port'] ?? 1433;
                $server = "{$config['connection']['host']},{$port}";
                $encrypt = $config['connection']['encrypt'] ?? true;
                $trustServerCertificate = $config['connection']['trust_server_certificate'] ?? true;
                $dsn = "sqlsrv:Server={$server};Database={$config['connection']['database']};Encrypt=" . ($encrypt ? 'yes' : 'no') . ";TrustServerCertificate=" . ($trustServerCertificate ? 'yes' : 'no');
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
