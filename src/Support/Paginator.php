<?php

declare(strict_types=1);

namespace App\Support;

final class Paginator
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $offset;
    public readonly int $totalRows;
    public readonly int $totalPages;

    public function __construct(int $page, int $perPage, int $totalRows)
    {
        $this->perPage = max(1, min(500, $perPage));
        $this->totalRows = max(0, $totalRows);
        $this->totalPages = max(1, (int) ceil($this->totalRows / $this->perPage));
        $this->page = max(1, min($page, $this->totalPages));
        $this->offset = ($this->page - 1) * $this->perPage;
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages;
    }
}
