<?php

use App\Controllers\HomeController;
use App\Controllers\ContactController;

/** @var App\Core\Router $router */

$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [HomeController::class, 'show']);

$router->get('/contact', [ContactController::class, 'create']);
$router->post('/contact', [ContactController::class, 'store']);
