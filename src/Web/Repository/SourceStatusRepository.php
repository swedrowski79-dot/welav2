<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class SourceStatusRepository
{
    public function statuses(): array
    {
        $sources = \web_config('sources')['sources'];

        return [
            'afs' => $this->checkConnection('AFS', $sources['afs']),
            'extra' => $this->checkConnection('AFS Extras', $sources['extra']),
            'extra_sqlite_bootstrap' => $this->checkSqlite($sources['extra_sqlite_bootstrap']),
            'xt' => $this->checkXtApi($sources['xt']),
            'stage' => $this->checkConnection('Stage', $sources['stage']),
        ];
    }

    private function checkConnection(string $label, array $config): array
    {
        try {
            $pdo = \ConnectionFactory::create($config);
            $pdo->query('SELECT 1');

            return [
                'label' => $label,
                'status' => 'reachable',
                'message' => 'Verbindung erfolgreich.',
            ];
        } catch (\Throwable $exception) {
            return [
                'label' => $label,
                'status' => 'unreachable',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function checkSqlite(array $config): array
    {
        $path = $config['connection']['path'] ?? '';

        if (!is_file($path)) {
            return [
                'label' => 'SQLite Bootstrap',
                'status' => 'unreachable',
                'message' => "Datei nicht gefunden: {$path}",
            ];
        }

        return $this->checkConnection('SQLite Bootstrap', $config);
    }

    private function checkXtApi(array $config): array
    {
        try {
            $client = new XtApiClient(
                (string) ($config['connection']['url'] ?? ''),
                (string) ($config['connection']['key'] ?? '')
            );
            $response = $client->health();

            return [
                'label' => 'XT',
                'status' => 'reachable',
                'message' => (string) ($response['message'] ?? 'XT-API erreichbar.'),
            ];
        } catch (\Throwable $exception) {
            return [
                'label' => 'XT',
                'status' => 'unreachable',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
