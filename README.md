# Tiny MVC

A very small, dependency-free PHP MVC framework: routing with parameters,
`.env` config, PDO (prepared statements only), CSRF protection, and a
hand-rolled raw-socket SMTP mailer. No Composer packages required to run it.

## Requirements

- PHP 8.1+
- `pdo` extension (plus the driver for your DB, e.g. `pdo_mysql`)
- `openssl` extension (only needed if your SMTP server uses TLS/SSL, which is almost always)

## Setup

**Via Packagist / Composer (once published — see below):**

```bash
composer create-project krafsys/mini
cd my-app
# .env is created automatically from .env.example by the post-create-project script
# edit .env with your DB + SMTP credentials
php -S localhost:8000 -t public
```

**From a manual clone/download (e.g. this zip):**

```bash
composer install          # sets up the PSR-4 autoloader (no third-party packages)
cp .env.example .env      # already done for you in this download
# edit .env with your DB + SMTP credentials
php -S localhost:8000 -t public
```

Visit `http://localhost:8000`.

On a real Apache host, point the document root at `public/` — `.htaccess`
routes everything through `index.php` and blocks direct access to dotfiles.
On Nginx, add an equivalent `try_files $uri $uri/ /index.php;` rule yourself
(not included, since this repo assumes Apache's `.htaccess`).

## Directory structure

```
app/
  Controllers/   Your controllers (extend App\Core\Controller)
  Models/        Plain PHP models — talk to App\Core\Database as you like
  Views/         Plain PHP templates, rendered via $this->view()
  Core/          The framework itself (App, Router, Request, Response,
                 Controller, Database, Env, Csrf, Mailer, helpers.php)
config/          app.php, database.php, mail.php — read via config('file.key')
public/          Web root: index.php (front controller) + .htaccess
routes/web.php   Route table
storage/logs/    app.log — uncaught exceptions and PHP errors land here
```

## Routing

Only `GET` and `POST` are supported — no method spoofing, no PUT/PATCH/DELETE.

```php
// routes/web.php
$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [HomeController::class, 'show']);
$router->post('/contact', [ContactController::class, 'store']);
```

Route `{placeholders}` are passed to your controller method **as named
arguments**, so the parameter name in your method signature must match the
placeholder name exactly:

```php
// /users/{id}  ->  public function show(Request $request, string $id)
```

A route that matches the URL but not the HTTP method returns `405`; no
match at all returns `404`.

## Controllers & views

```php
class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home.index', ['title' => 'Welcome']);
    }
}
```

`view('home.index', $data)` renders `app/Views/home/index.php` and wraps it
in `app/Views/layouts/main.php` by default (the layout receives `$content`
plus your `$data`). Pass `$layout = null` as the third argument to skip the
layout. Views are plain PHP — use the `e()` helper to escape output.

## CSRF

Every `POST` request is checked automatically in `App::run()`, before your
controller ever runs — no per-route middleware to remember. Add the token
to any form with the `csrf_field()` helper:

```php
<form method="POST" action="/contact">
    <?= csrf_field() ?>
    ...
</form>
```

A missing/invalid token returns `419` instead of hitting your controller.

## Database (PDO)

`App\Core\Database` is a lazy-loaded PDO singleton, configured from
`config/database.php` / your `.env`. It always uses prepared statements —
there's no raw-query helper, on purpose.

```php
use App\Core\Database;

$user = Database::query(
    'SELECT * FROM users WHERE email = ?',
    [$email]
)->fetch();
```

## Mail (SMTP)

`App\Core\Mailer` speaks SMTP directly over a socket — no PHPMailer or
similar. Supports implicit SSL (port 465), STARTTLS (port 587), and
AUTH LOGIN, configured via `.env` (`MAIL_*`):

```php
use App\Core\Mailer;

(new Mailer())->send(
    to: 'someone@example.com',
    subject: 'Hello',
    body: '<p>Hi there</p>',
    options: ['html' => true, 'cc' => ['other@example.com' => 'Other Person']]
);
```

Note: it sends plain single-part text/HTML mail — there's no attachment
support. If you need attachments, that's the one place you'd reach for a
real library (PHPMailer/Symfony Mailer) instead.

## Config & env helpers

- `env('KEY', $default)` — reads `.env` values directly.
- `config('file.key', $default)` — dot-notation reader for `config/*.php`
  (e.g. `config('mail.host')`), cached per request.
- `e($value)` — `htmlspecialchars()` shortcut for views.
- `old('field')` — repopulate a form field after `Controller::backWithInput()`.

## Errors & logging

`APP_DEBUG=true` in `.env` shows a stack trace on uncaught exceptions;
`false` shows a generic 500 page. Either way, everything is logged to
`storage/logs/app.log`.


## What's deliberately left out

This is meant to stay tiny, not become another framework. No middleware
pipeline, no dependency injection container, no ORM, no query builder, no
console/CLI, no asset pipeline, no attachment/multipart mail. Add what you
need on top — the core classes are small enough to read in one sitting.
