<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], bool $withLayout = true): void
    {
        $content = self::capture($template, $data);

        if (!$withLayout) {
            echo $content;

            return;
        }

        echo self::capture('layout', array_merge($data, ['content' => $content]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $template, array $data): string
    {
        $path = dirname(__DIR__, 2) . '/templates/' . $template . '.phtml';
        if (!is_file($path)) {
            throw new RuntimeException('Template nao encontrado: ' . $template);
        }

        $render = static function (string $__path, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__path;

            return (string) ob_get_clean();
        };

        return $render($path, $data);
    }
}
