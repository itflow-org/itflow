<?php

namespace DirectoryTree\ImapEngine\Pagination;

use DirectoryTree\ImapEngine\Support\ForwardsCalls;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @template-implements Arrayable<TKey, TValue>
 */
class LengthAwarePaginator implements Arrayable, JsonSerializable
{
    use ForwardsCalls;

    /**
     * Constructor.
     */
    public function __construct(
        protected Collection $items,
        protected int $total,
        protected int $perPage,
        protected int $currentPage = 1,
        protected string $path = '',
        protected array $query = [],
        protected string $pageName = 'page',
    ) {
        $this->currentPage = max($currentPage, 1);

        $this->path = rtrim($path, '/');
    }

    /**
     * Handle dynamic method calls on the paginator.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->items, $method, $parameters);
    }

    /**
     * Get the items being paginated.
     *
     * @return Collection<TKey, TValue>
     */
    public function items(): Collection
    {
        return $this->items;
    }

    /**
     * Get the total number of items.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get the number of items per page.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the current page number.
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the last page (total pages).
     */
    public function lastPage(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    /**
     * Determine if there are enough items to split into multiple pages.
     */
    public function hasPages(): bool
    {
        return $this->total() > $this->perPage();
    }

    /**
     * Determine if there is a next page.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage() < $this->lastPage();
    }

    /**
     * Generate the URL for a given page.
     */
    public function url(int $page): string
    {
        $params = array_merge($this->query, [$this->pageName => $page]);

        $queryString = http_build_query($params);

        return $this->path.($queryString ? '?'.$queryString : '');
    }

    /**
     * Get the URL for the next page, or null if none.
     */
    public function nextPageUrl(): ?string
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }

        return null;
    }

    /**
     * Get the URL for the previous page, or null if none.
     */
    public function previousPageUrl(): ?string
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }

        return null;
    }

    /**
     * Get the array representation of the paginator.
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'total' => $this->total(),
            'to' => $this->calculateTo(),
            'per_page' => $this->perPage(),
            'last_page' => $this->lastPage(),
            'first_page_url' => $this->url(1),
            'data' => $this->items()->toArray(),
            'current_page' => $this->currentPage(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
            'last_page_url' => $this->url($this->lastPage()),
            'from' => $this->total() ? ($this->currentPage() - 1) * $this->perPage() + 1 : null,
        ];
    }

    /**
     * Calculate the "to" index for the current page.
     */
    protected function calculateTo(): ?int
    {
        if (! $this->total()) {
            return null;
        }

        $to = $this->currentPage() * $this->perPage();

        return min($to, $this->total());
    }

    /**
     * Get the JSON representation of the paginator.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
