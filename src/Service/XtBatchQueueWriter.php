<?php

declare(strict_types=1);

interface XtBatchQueueWriter extends XtQueueWriter
{
    public function supportsBatch(string $entityType): bool;

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array{
     *   done: array<int, true>,
     *   failed: array<int, Throwable>
     * }
     */
    public function writeBatch(string $entityType, array $entries): array;
}
