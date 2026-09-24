<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;

$app = new App(dirname(__DIR__));
$app->run();
