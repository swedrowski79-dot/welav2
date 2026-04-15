<?php

declare(strict_types=1);

final class XtCompositeWriter implements XtQueueWriter
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
}
