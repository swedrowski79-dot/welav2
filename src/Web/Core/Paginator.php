<?php

declare(strict_types=1);

namespace App\Web\Core;

final class Paginator
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $total;

    public function __construct(
        int $page,
        int $perPage,
        int $total
    ) {
        $this->perPage = max(1, $perPage);
        $this->total = max(0, $total);
        $this->page = max(1, min($page, $this->totalPages()));
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

    public function previousPage(): ?int
    {
        return $this->page > 1 ? $this->page - 1 : null;
    }

    public function nextPage(): ?int
    {
        return $this->page < $this->totalPages() ? $this->page + 1 : null;
    }

    /**
     * @return array<int|string>
     */
    public function compactPages(int $radius = 1): array
    {
        $totalPages = $this->totalPages();

        if ($totalPages <= 7) {
            return range(1, $totalPages);
        }

        $pages = [1, $totalPages];

        for ($page = $this->page - $radius; $page <= $this->page + $radius; $page++) {
            if ($page >= 1 && $page <= $totalPages) {
                $pages[] = $page;
            }
        }

        if ($this->page <= 3) {
            $pages = array_merge($pages, [2, 3, 4]);
        }

        if ($this->page >= $totalPages - 2) {
            $pages = array_merge($pages, [$totalPages - 3, $totalPages - 2, $totalPages - 1]);
        }

        $pages = array_values(array_unique(array_filter($pages, static fn (int $page): bool => $page >= 1 && $page <= $totalPages)));
        sort($pages);

        $items = [];
        $previous = null;

        foreach ($pages as $page) {
            if ($previous !== null && $page - $previous > 1) {
                $items[] = 'ellipsis';
            }

            $items[] = $page;
            $previous = $page;
        }

        return $items;
    }
}
