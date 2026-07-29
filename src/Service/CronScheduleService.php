<?php

declare(strict_types=1);

final class CronScheduleService
{
    private const SETTINGS_ID = 1;

    public function __construct(
        private PDO $db,
        private array $config = []
    ) {
    }

    public function settings(): array
    {
        $this->ensureSchema();

        $statement = $this->db->prepare(
            'SELECT id, enabled, interval_minutes, last_started_at, last_finished_at,
                    last_status, last_message, created_at, updated_at
             FROM cron_settings
             WHERE id = :id'
        );
        $statement->execute(['id' => self::SETTINGS_ID]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException('Cron-Einstellungen konnten nicht geladen werden.');
        }

        return $this->normalizeSettings($row);
    }

    public function updateSettings(bool $enabled, int $intervalMinutes): array
    {
        $this->assertInterval($intervalMinutes);
        $current = $this->settings();
        $resetLastStart = !$current['enabled'] && $enabled;

        $statement = $this->db->prepare(
            'UPDATE cron_settings
             SET enabled = :enabled,
                 interval_minutes = :interval_minutes,
                 last_started_at = CASE
                     WHEN :reset_last_start = 1 THEN NULL
                     ELSE last_started_at
                 END
             WHERE id = :id'
        );
        $statement->execute([
            'enabled' => $enabled ? 1 : 0,
            'interval_minutes' => $intervalMinutes,
            'reset_last_start' => $resetLastStart ? 1 : 0,
            'id' => self::SETTINGS_ID,
        ]);

        return $this->settings();
    }

    public function claimIfDue(?DateTimeImmutable $now = null): array
    {
        $this->ensureSchema();
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowValue = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $statement = $this->db->prepare(
            'UPDATE cron_settings
             SET last_started_at = :started_at,
                 last_status = :status,
                 last_message = NULL
             WHERE id = :id
               AND enabled = 1
               AND (
                   last_started_at IS NULL
                   OR DATE_ADD(last_started_at, INTERVAL interval_minutes MINUTE) <= :due_at
               )'
        );
        $statement->execute([
            'started_at' => $nowValue,
            'status' => 'running',
            'id' => self::SETTINGS_ID,
            'due_at' => $nowValue,
        ]);

        $settings = $this->settings();

        if ($statement->rowCount() === 1) {
            return [
                'due' => true,
                'reason' => 'due',
                'settings' => $settings,
            ];
        }

        return [
            'due' => false,
            'reason' => $settings['enabled'] ? 'not_due' : 'disabled',
            'settings' => $settings,
        ];
    }

    public function markFinished(bool $successful, string $message, ?DateTimeImmutable $now = null): void
    {
        $this->ensureSchema();
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $statement = $this->db->prepare(
            'UPDATE cron_settings
             SET last_finished_at = :finished_at,
                 last_status = :status,
                 last_message = :message
             WHERE id = :id'
        );
        $statement->execute([
            'finished_at' => $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'status' => $successful ? 'success' : 'failed',
            'message' => substr(trim($message), 0, 1000),
            'id' => self::SETTINGS_ID,
        ]);
    }

    public function minimumInterval(): int
    {
        return max(1, (int) ($this->config['min_interval_minutes'] ?? 1));
    }

    public function maximumInterval(): int
    {
        return max(
            $this->minimumInterval(),
            (int) ($this->config['max_interval_minutes'] ?? 1440)
        );
    }

    private function ensureSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS cron_settings (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                interval_minutes INT UNSIGNED NOT NULL DEFAULT 5,
                last_started_at DATETIME NULL,
                last_finished_at DATETIME NULL,
                last_status VARCHAR(20) NOT NULL DEFAULT 'never',
                last_message VARCHAR(1000) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $statement = $this->db->prepare(
            'INSERT IGNORE INTO cron_settings (id, enabled, interval_minutes)
             VALUES (:id, :enabled, :interval_minutes)'
        );
        $statement->execute([
            'id' => self::SETTINGS_ID,
            'enabled' => $this->defaultEnabled() ? 1 : 0,
            'interval_minutes' => $this->defaultInterval(),
        ]);
    }

    private function normalizeSettings(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? self::SETTINGS_ID);
        $row['enabled'] = (int) ($row['enabled'] ?? 0) === 1;
        $row['interval_minutes'] = (int) ($row['interval_minutes'] ?? $this->defaultInterval());
        $row['next_run_at'] = $this->nextRunAt(
            $row['enabled'],
            $row['last_started_at'] ?? null,
            $row['interval_minutes']
        );

        return $row;
    }

    private function nextRunAt(bool $enabled, mixed $lastStartedAt, int $intervalMinutes): ?string
    {
        if (!$enabled || !is_string($lastStartedAt) || trim($lastStartedAt) === '') {
            return null;
        }

        $lastStart = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $lastStartedAt,
            new DateTimeZone('UTC')
        );

        if (!$lastStart instanceof DateTimeImmutable) {
            return null;
        }

        return $lastStart
            ->modify(sprintf('+%d minutes', $intervalMinutes))
            ->format('Y-m-d H:i:s');
    }

    private function assertInterval(int $intervalMinutes): void
    {
        if ($intervalMinutes < $this->minimumInterval() || $intervalMinutes > $this->maximumInterval()) {
            throw new InvalidArgumentException(sprintf(
                'Das Cron-Intervall muss zwischen %d und %d Minuten liegen.',
                $this->minimumInterval(),
                $this->maximumInterval()
            ));
        }
    }

    private function defaultInterval(): int
    {
        $default = (int) ($this->config['default_interval_minutes'] ?? 5);

        return min(max($default, $this->minimumInterval()), $this->maximumInterval());
    }

    private function defaultEnabled(): bool
    {
        return (bool) ($this->config['default_enabled'] ?? true);
    }
}
