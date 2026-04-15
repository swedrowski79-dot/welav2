<?php

class DeltaRunnerService
{
    private array $configKeys;

    public function __construct(
        private PDO $stageDb,
        private array $deltaConfig,
        private ?SyncMonitor $monitor = null,
        private ?int $runId = null
    ) {
        $configKeys = $this->deltaConfig['export_queue_entities'] ?? ['product_export_queue'];
        $this->configKeys = array_values(array_filter(
            is_array($configKeys) ? $configKeys : [],
            static fn (mixed $key): bool => is_string($key) && $key !== ''
        ));

        if ($this->configKeys === []) {
            $this->configKeys = ['product_export_queue'];
        }
    }

    public function run(): array
    {
        $aggregate = [
            'processed' => 0,
            'insert' => 0,
            'update' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'deduplicated' => 0,
            'errors' => 0,
            'changed' => 0,
            'entities' => [],
        ];

        foreach ($this->configKeys as $configKey) {
            $stats = (new ProductDeltaService(
                $this->stageDb,
                $this->deltaConfig,
                $this->monitor,
                $this->runId,
                $configKey
            ))->run();

            $entityType = (string) ($stats['entity_type'] ?? $configKey);
            $aggregate['entities'][$entityType] = $stats;

            foreach (['processed', 'insert', 'update', 'removed', 'unchanged', 'deduplicated', 'errors', 'changed'] as $field) {
                $aggregate[$field] += (int) ($stats[$field] ?? 0);
            }
        }

        return $aggregate;
    }
}
