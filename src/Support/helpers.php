<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_cell')) {
    function format_cell(mixed $value): string
    {
        if ($value === null) {
            return '<span class="italic text-muted-foreground">NULL</span>';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return e($value);
    }
}
