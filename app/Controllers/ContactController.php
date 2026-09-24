<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;

class ContactController extends Controller
{
    public function create(Request $request): Response
    {
        return $this->view('contact.index', [
            'title' => 'Contact Us',
            'sent' => false,
        ]);
    }

    // CSRF on this POST is already verified by App::run() before we get here.
    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name'));
        $email = trim((string) $request->input('email'));
        $message = trim((string) $request->input('message'));

        $errors = [];
        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if ($message === '') {
            $errors[] = 'Message is required.';
        }

        if ($errors !== []) {
            return $this->view('contact.index', [
                'title' => 'Contact Us',
                'sent' => false,
                'errors' => $errors,
                'old' => $request->all(),
            ]);
        }

        try {
            (new Mailer())->send(
                to: config('mail.from_address'),
                subject: 'New contact form submission from ' . $name,
                body: nl2br(e($message)),
                options: ['reply_to' => $email]
            );
        } catch (\Throwable $e) {
            error_log('Mail send failed: ' . $e->getMessage());
            return $this->view('contact.index', [
                'title' => 'Contact Us',
                'sent' => false,
                'errors' => ['Could not send your message right now. Please try again later.'],
                'old' => $request->all(),
            ]);
        }

        return $this->view('contact.index', [
            'title' => 'Contact Us',
            'sent' => true,
        ]);
    }
}
