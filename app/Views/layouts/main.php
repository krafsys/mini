<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config('app.name')) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; color: #222; }
        header, footer { color: #666; }
        input, textarea { display: block; width: 100%; padding: 8px; margin: 4px 0 12px; box-sizing: border-box; }
        button { padding: 8px 16px; }
        ul.errors { color: #b00020; }
    </style>
</head>
<body>
    <header>
        <strong><?= e(config('app.name')) ?></strong>
        <nav> — <a href="/">Home</a> · <a href="/contact">Contact</a></nav>
    </header>

    <main>
        <?= $content ?>
    </main>

    <footer>
        <hr>
        <p><small>&copy; <?= date('Y') ?> <?= e(config('app.name')) ?></small></p>
    </footer>
</body>
</html>
