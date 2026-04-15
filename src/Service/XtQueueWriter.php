<?php

declare(strict_types=1);

interface XtQueueWriter
{
    public function supports(string $entityType): bool;

    public function write(string $entityType, array $entry, array $payload): void;
}
