<?php

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * Minimal SMTP client over a raw socket — no PHPMailer/Swiftmailer/Symfony
 * Mailer dependency. Supports implicit SSL (port 465), STARTTLS (port 587),
 * and AUTH LOGIN. Good for plain-text/HTML mail; no attachment support by
 * design — extend buildHeaders()/send() if you need multipart.
 */
class Mailer
{
    protected string $host;
    protected int $port;
    protected string $encryption; // 'ssl', 'tls', or ''
    protected string $username;
    protected string $password;
    protected string $fromAddress;
    protected string $fromName;
    protected int $timeout = 15;

    /** @var resource|null */
    protected $socket = null;

    public function __construct(?array $config = null)
    {
        $config ??= config('mail');

        $this->host = $config['host'];
        $this->port = (int) $config['port'];
        $this->encryption = strtolower((string) ($config['encryption'] ?? ''));
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->fromAddress = $config['from_address'] ?? $this->username;
        $this->fromName = $config['from_name'] ?? '';
    }

    /**
     * @param string|array $to      A single address, or ['email' => 'Name', ...]
     * @param array        $options ['html' => bool, 'cc' => .., 'bcc' => .., 'reply_to' => string]
     */
    public function send(string|array $to, string $subject, string $body, array $options = []): bool
    {
        $isHtml = $options['html'] ?? true;
        $cc = $this->normalizeAddresses($options['cc'] ?? []);
        $bcc = $this->normalizeAddresses($options['bcc'] ?? []);
        $recipients = $this->normalizeAddresses($to);

        $envelopeTargets = $recipients + $cc + $bcc;

        if ($envelopeTargets === []) {
            throw new InvalidArgumentException('No recipients specified.');
        }

        try {
            $this->connect();
            $this->hello();

            if ($this->encryption === 'tls') {
                $this->startTls();
                $this->hello();
            }

            if ($this->username !== '') {
                $this->authenticate();
            }

            $this->command('MAIL FROM:<' . $this->fromAddress . '>', 250);

            foreach (array_keys($envelopeTargets) as $address) {
                $this->command('RCPT TO:<' . $address . '>', [250, 251]);
            }

            $this->command('DATA', 354);

            $headers = $this->buildHeaders($recipients, $cc, $subject, $isHtml, $options['reply_to'] ?? null);
            $this->write($headers . "\r\n" . $this->dotStuff($body) . "\r\n.");
            $this->readResponse(250);

            $this->command('QUIT', 221);
        } finally {
            $this->disconnect();
        }

        return true;
    }

    protected function connect(): void
    {
        $address = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;

        $context = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $this->socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($this->socket === false) {
            throw new RuntimeException("Could not connect to SMTP host {$this->host}:{$this->port} ({$errstr})");
        }

        stream_set_timeout($this->socket, $this->timeout);
        $this->readResponse(220);
    }

    protected function hello(): void
    {
        $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);
    }

    protected function startTls(): void
    {
        $this->command('STARTTLS', 220);

        if (stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new RuntimeException('Could not start TLS encryption with SMTP server.');
        }
    }

    protected function authenticate(): void
    {
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    protected function buildHeaders(array $to, array $cc, string $subject, bool $isHtml, ?string $replyTo): string
    {
        $lines = [
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->host . '>',
            'From: ' . $this->formatAddress($this->fromAddress, $this->fromName),
            'To: ' . $this->formatAddressList($to),
        ];

        if ($cc !== []) {
            $lines[] = 'Cc: ' . $this->formatAddressList($cc);
        }

        if ($replyTo) {
            $lines[] = 'Reply-To: ' . $replyTo;
        }

        $lines[] = 'Subject: ' . $this->encodeHeader($subject);
        $lines[] = 'MIME-Version: 1.0';
        $lines[] = 'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8';
        $lines[] = 'Content-Transfer-Encoding: 8bit';

        return implode("\r\n", $lines) . "\r\n";
    }

    protected function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    protected function formatAddress(string $email, string $name = ''): string
    {
        return $name !== '' ? $this->encodeHeader($name) . " <{$email}>" : $email;
    }

    protected function formatAddressList(array $addresses): string
    {
        $parts = [];
        foreach ($addresses as $email => $name) {
            $parts[] = $this->formatAddress($email, is_string($name) ? $name : '');
        }
        return implode(', ', $parts);
    }

    /** Normalizes 'a@b.com', ['a@b.com'], or ['a@b.com' => 'Name'] into ['email' => 'name']. */
    protected function normalizeAddresses(string|array $addresses): array
    {
        if ($addresses === '' || $addresses === []) {
            return [];
        }

        if (is_string($addresses)) {
            return [$addresses => ''];
        }

        $normalized = [];
        foreach ($addresses as $key => $value) {
            is_int($key) ? $normalized[$value] = '' : $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** RFC 5321: a line consisting of a single "." must be escaped as "..". */
    protected function dotStuff(string $body): string
    {
        return preg_replace('/^\./m', '..', $body);
    }

    protected function command(string $command, int|array $expectedCodes): string
    {
        $this->write($command);
        return $this->readResponse($expectedCodes);
    }

    protected function write(string $data): void
    {
        fwrite($this->socket, $data . "\r\n");
    }

    protected function readResponse(int|array $expectedCodes): string
    {
        $expectedCodes = (array) $expectedCodes;
        $response = '';

        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            // Multiline SMTP replies use "250-"; the final line uses "250 ".
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException(
                'SMTP error: expected ' . implode('/', $expectedCodes) . ", got: {$response}"
            );
        }

        return $response;
    }

    protected function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
