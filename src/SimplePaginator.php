<?php
/**
 * SimplePaginator — framework-free pagination for arrays of results.
 *
 * Usage:
 *   $paginator = new SimplePaginator($totalItems, $perPage = 10, $currentPage = 1);
 *   $pageItems = array_slice($allItems, $paginator->offset(), $paginator->perPage());
 *   echo $paginator->render('/blog?page={page}');   // HTML page links
 */

class SimplePaginator
{
    private int $total;
    private int $perPage;
    private int $currentPage;

    public function __construct(int $totalItems, int $perPage = 10, int $currentPage = 1)
    {
        $this->total = max(0, $totalItems);
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, min($currentPage, $this->totalPages()));
    }

    /** Total number of pages. */
    public function totalPages(): int
    {
        return (int) max(1, ceil($this->total / $this->perPage));
    }

    /** Current page number (clamped to valid range). */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /** Rows per page. */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /** SQL/array offset for the current page. */
    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function hasPrevious(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNext(): bool
    {
        return $this->currentPage < $this->totalPages();
    }

    /**
     * Render simple HTML page links.
     * $urlPattern must contain a {page} placeholder, e.g. "/blog?page={page}".
     */
    public function render(string $urlPattern, int $window = 2): string
    {
        if ($this->totalPages() <= 1) {
            return '';
        }

        $url = fn(int $page): string => str_replace('{page}', (string) $page, $urlPattern);
        $html = '<nav class="pagination">';

        if ($this->hasPrevious()) {
            $html .= '<a href="' . htmlspecialchars($url($this->currentPage - 1)) . '">&laquo; Prev</a> ';
        }

        $start = max(1, $this->currentPage - $window);
        $end = min($this->totalPages(), $this->currentPage + $window);

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $this->currentPage) {
                $html .= '<span class="current">' . $i . '</span> ';
            } else {
                $html .= '<a href="' . htmlspecialchars($url($i)) . '">' . $i . '</a> ';
            }
        }

        if ($this->hasNext()) {
            $html .= '<a href="' . htmlspecialchars($url($this->currentPage + 1)) . '">Next &raquo;</a>';
        }

        return trim($html) . '</nav>';
    }
}
