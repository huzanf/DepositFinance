<?php

namespace DepositFinance;

/**
 * Sends the OTP login email. Three drivers:
 *  - 'log'      writes the code to storage/otp.log (no network needed; for local dev).
 *  - 'smtp'     sends a real email over a plain SMTP socket (no external libraries required).
 *  - 'php_mail' sends via PHP's built-in mail(), which hands off to the server's local
 *               mail system instead of opening a direct outbound connection — the driver
 *               to use if 'smtp' can't get through (shared hosts often block or firewall
 *               outbound SMTP ports even when the local mail() path works fine).
 */
class Mailer
{
    public static function sendOtp(string $toEmail, string $code): void
    {
        $subject = 'Your DepositFinance login code';
        $body = "Your login code is: {$code}\n\nThis code expires in 10 minutes. If you didn't request this, you can ignore this email.";

        self::dispatch($toEmail, $subject, $body, fn () => self::appendLog('otp.log', "OTP for {$toEmail}: {$code}"));
    }

    /** Generic send, used by the maturity reminder cron and anything else beyond OTP. */
    public static function send(string $toEmail, string $subject, string $body): void
    {
        self::dispatch($toEmail, $subject, $body, fn () => self::appendLog('mail.log', "To: {$toEmail} | Subject: {$subject}\n{$body}"));
    }

    private static function dispatch(string $toEmail, string $subject, string $body, callable $logFallback): void
    {
        $driver = config('mail.driver');

        if ($driver === 'smtp') {
            self::sendSmtp($toEmail, $subject, $body);
            return;
        }

        if ($driver === 'php_mail') {
            self::sendPhpMail($toEmail, $subject, $body);
            return;
        }

        $logFallback();
    }

    private static function sendPhpMail(string $toEmail, string $subject, string $body): void
    {
        $fromEmail = config('mail.from_email');
        $fromName = config('mail.from_name');

        $headers = "From: " . self::encodeHeader($fromName) . " <{$fromEmail}>\r\n"
            . "Reply-To: {$fromEmail}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "X-Mailer: PHP/" . phpversion();

        $sent = mail($toEmail, self::encodeHeader($subject), $body, $headers);

        if (!$sent) {
            throw new \RuntimeException("PHP mail() reported failure sending to {$toEmail}.");
        }
    }

    private static function appendLog(string $filename, string $message): void
    {
        $dir = __DIR__ . '/../storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        file_put_contents($dir . '/' . $filename, $line, FILE_APPEND | LOCK_EX);
    }

    private static function sendSmtp(string $toEmail, string $subject, string $body): void
    {
        $host = config('mail.smtp_host');
        $port = (int) config('mail.smtp_port');
        $user = config('mail.smtp_user');
        $pass = config('mail.smtp_pass');
        $encryption = config('mail.smtp_encryption');
        $fromEmail = config('mail.from_email');
        $fromName = config('mail.from_name');

        if (empty($host)) {
            throw new \RuntimeException('mail.driver is "smtp" but mail.smtp_host is not configured.');
        }

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;

        $socket = @stream_socket_client(
            $transport . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new \RuntimeException("Could not connect to SMTP server {$host}:{$port} - {$errstr}");
        }

        self::expect($socket, 220);
        self::command($socket, "EHLO localhost", 250);

        if ($encryption === 'tls') {
            self::command($socket, "STARTTLS", 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Failed to enable TLS for SMTP connection.');
            }
            self::command($socket, "EHLO localhost", 250);
        }

        if (!empty($user)) {
            self::command($socket, "AUTH LOGIN", 334);
            self::command($socket, base64_encode($user), 334);
            self::command($socket, base64_encode($pass), 235);
        }

        self::command($socket, "MAIL FROM:<{$fromEmail}>", 250);
        self::command($socket, "RCPT TO:<{$toEmail}>", 250);
        self::command($socket, "DATA", 354);

        $headers = [
            'From: ' . self::encodeHeader($fromName) . " <{$fromEmail}>",
            "To: <{$toEmail}>",
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $escapedBody = preg_replace('/^\./m', '..', $body);
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $escapedBody . "\r\n.";

        self::command($socket, $message, 250);
        self::command($socket, "QUIT", 221);

        fclose($socket);
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function command($socket, string $line, int $expectedCode): string
    {
        fwrite($socket, $line . "\r\n");
        return self::expect($socket, $expectedCode);
    }

    private static function expect($socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // Multi-line responses use "code-text"; the final line uses "code text".
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("Unexpected SMTP response (expected {$expectedCode}): {$response}");
        }

        return $response;
    }
}
