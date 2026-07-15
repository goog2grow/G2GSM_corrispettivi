<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    private const VIEWS_PATH = __DIR__ . '/../../views';

    public static function render(string $template, array $data = []): void
    {
        $viewFile = self::resolve($template);
        extract($data, EXTR_SKIP);
        require $viewFile;
    }

    public static function renderWithLayout(string $template, array $data = [], string $layout = 'layout'): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require self::resolve($template);
        $content = ob_get_clean();

        require self::resolve($layout);
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    private static function resolve(string $template): string
    {
        $viewFile = self::VIEWS_PATH . '/' . $template . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException("Vista non trovata: {$template}");
        }

        return $viewFile;
    }
}
