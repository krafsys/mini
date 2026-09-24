<?php

namespace App\Core;

class Response
{
    public function __construct(
        protected string $content = '',
        protected int $status = 200,
        protected array $headers = []
    ) {
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->content;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json']
        );
    }
}
