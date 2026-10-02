<?php
/**
 * Generic PHP helper functions. Dependency-free.
 */

if (!function_exists('slugify')) {
    /**
     * Convert a string to a URL-friendly slug.
     */
    function slugify(string $text): string {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($text));
        return trim($text, '-');
    }
}

if (!function_exists('time_ago')) {
    /**
     * Human-readable relative time, e.g. "3 hours ago".
     */
    function time_ago(int $timestamp): string {
        $diff = time() - $timestamp;
        if ($diff < 60) return 'just now';
        $units = [
            31536000 => 'year', 2592000 => 'month', 604800 => 'week',
            86400 => 'day', 3600 => 'hour', 60 => 'minute',
        ];
        foreach ($units as $secs => $name) {
            if ($diff >= $secs) {
                $n = (int) floor($diff / $secs);
                return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
            }
        }
        return 'just now';
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Format bytes as KB / MB / GB.
     */
    function format_bytes(int $bytes, int $precision = 1): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('esc')) {
    /**
     * Escape output for HTML. Use on EVERYTHING you print from user input.
     */
    function esc($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
