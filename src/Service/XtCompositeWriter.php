<?php

declare(strict_types=1);

final class XtCompositeWriter implements XtBatchQueueWriter
{
    /**
     * @param XtQueueWriter[] $writers
     */
    public function __construct(
        private array $writers
    ) {
    }

    public function supports(string $entityType): bool
    {
        foreach ($this->writers as $writer) {
            if ($writer->supports($entityType)) {
                return true;
            }
        }

        return false;
    }

    public function write(string $entityType, array $entry, array $payload): void
    {
        foreach ($this->writers as $writer) {
            if (!$writer->supports($entityType)) {
                continue;
            }

            $writer->write($entityType, $entry, $payload);

            return;
        }
    }

    public function supportsBatch(string $entityType): bool
    {
        foreach ($this->writers as $writer) {
            if (!$writer instanceof XtBatchQueueWriter) {
                continue;
            }

            if ($writer->supportsBatch($entityType)) {
                return true;
            }
        }

        return false;
    }

    public function writeBatch(string $entityType, array $entries): array
    {
        foreach ($this->writers as $writer) {
            if (!$writer instanceof XtBatchQueueWriter || !$writer->supportsBatch($entityType)) {
                continue;
            }

            return $writer->writeBatch($entityType, $entries);
        }

        throw new RuntimeException("Kein Batch-Writer fuer Entity-Typ '{$entityType}' verfuegbar.");
    }
}
