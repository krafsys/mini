<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home.index', [
            'title' => 'Welcome',
        ]);
    }

    // Route: GET /users/{id} — param name must match this argument's name.
    public function show(Request $request, string $id): Response
    {
        return $this->view('home.show', [
            'title' => "User #{$id}",
            'id' => $id,
        ]);
    }
}
