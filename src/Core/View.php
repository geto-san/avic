<?php
declare(strict_types=1);

namespace Avic\Core;

final class View
{
    /** Render templates/$template.php inside the shared layout. */
    public static function render(string $template, array $vars = [], int $status = 200): void
    {
        http_response_code($status);
        $user = Auth::user();
        $content = self::partial($template, $vars + ['user' => $user]);
        echo self::partial('layout', $vars + [
            'content' => $content,
            'title' => $vars['title'] ?? 'AVIC',
            'user' => $user,
            'flashes' => Flash::pull(),
        ]);
    }

    public static function partial(string $template, array $vars = []): string
    {
        $file = AVIC_ROOT . '/templates/' . $template . '.php';
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }

    public static function redirect(string $to): never
    {
        header('Location: ' . $to, true, 303);
        exit;
    }
}
