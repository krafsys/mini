<?php

namespace App\Core;

abstract class Controller
{
    /**
     * Render a view, wrapped in a layout by default.
     * $view uses dot notation, e.g. 'home.index' => Views/home/index.php.
     * Pass $layout = null to render standalone with no layout wrapper.
     */
    protected function view(string $view, array $data = [], ?string $layout = 'main'): Response
    {
        $content = self::renderFile(self::resolveView($view), $data);

        if ($layout !== null) {
            $layoutPath = self::resolveView("layouts/{$layout}");

            if (is_file($layoutPath)) {
                $content = self::renderFile($layoutPath, array_merge($data, ['content' => $content]));
            }
        }

        return new Response($content);
    }

    protected static function resolveView(string $view): string
    {
        $view = str_replace('.', '/', $view);
        $path = dirname(__DIR__) . "/Views/{$view}.php";

        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        return $path;
    }

    protected static function renderFile(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Stash submitted input in the session so old('field') can repopulate
     * a form after a failed submission, then return a redirect.
     */
    protected function backWithInput(string $url, array $input): Response
    {
        $_SESSION['_old_input'] = $input;
        return $this->redirect($url);
    }
}
