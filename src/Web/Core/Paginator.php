<?php

declare(strict_types=1);

namespace App\Web\Core;

final class Paginator
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->total > $this->perPage;
    }
}
