<?php

namespace App\Core;

use Throwable;

class App
{
    public Router $router;
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');

        Env::load($this->basePath . '/.env');
        date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));

        $this->configureErrorHandling();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->router = new Router();
    }

    protected function configureErrorHandling(): void
    {
        $debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
        $logDir = $this->basePath . '/storage/logs';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '0'); // we render our own error output below
        ini_set('log_errors', '1');
        ini_set('error_log', $logDir . '/app.log');

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e) use ($debug) {
            error_log(sprintf(
                "[%s] Uncaught %s: %s in %s:%d\n%s",
                date('Y-m-d H:i:s'),
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));

            if (!headers_sent()) {
                http_response_code(500);
            }

            if ($debug) {
                printf(
                    '<h1>500 — %s</h1><p>%s</p><p><small>%s:%d</small></p><pre>%s</pre>',
                    e(get_class($e)),
                    e($e->getMessage()),
                    e($e->getFile()),
                    $e->getLine(),
                    e($e->getTraceAsString())
                );
            } else {
                echo '<h1>500</h1><p>Something went wrong. Please try again later.</p>';
            }
        });
    }

    public function run(): void
    {
        $router = $this->router;
        require $this->basePath . '/routes/web.php';

        $request = new Request();

        if ($request->isPost() && !Csrf::verify($request->input('csrf_token'))) {
            (new Response('<h1>419</h1><p>Your session expired. Please go back and try again.</p>', 419))->send();
            return;
        }

        $this->router->dispatch($request)->send();
    }
}
