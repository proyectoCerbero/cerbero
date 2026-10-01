<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../config/env.php';

ApiHelper::init(['POST', 'OPTIONS']);
AuthHelper::initSession();

const CERBERO_CONTACT_EMAIL = 'cerberoSIGERU@gmail.com';

function contacto_responder(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'status' => $status,
        'data' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function contacto_enviar_smtp(string $to, string $replyTo, string $subject, string $body): bool
{
    if (!function_exists('curl_init')) {
        return false;
    }

    $host = trim((string) (getenv('SMTP_HOST') ?: ''));
    $username = trim((string) (getenv('SMTP_USERNAME') ?: ''));
    $password = (string) (getenv('SMTP_PASSWORD') ?: '');
    $port = (int) (getenv('SMTP_PORT') ?: 587);

    if ($host === '' || $username === '' || $password === '') {
        return false;
    }

    $scheme = $port === 465 ? 'smtps' : 'smtp';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $payload = implode("\r\n", [
        'Date: ' . date(DATE_RFC2822),
        'To: <' . $to . '>',
        'From: Cerbero <' . $username . '>',
        'Reply-To: <' . $replyTo . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $body,
        '',
    ]);

    $offset = 0;
    $curl = curl_init(sprintf('%s://%s:%d', $scheme, $host, $port));
    curl_setopt_array($curl, [
        CURLOPT_USERNAME => $username,
        CURLOPT_PASSWORD => $password,
        CURLOPT_USE_SSL => CURLUSESSL_ALL,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAIL_FROM => '<' . $username . '>',
        CURLOPT_MAIL_RCPT => ['<' . $to . '>'],
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILESIZE => strlen($payload),
        CURLOPT_TIMEOUT => 25,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_READFUNCTION => static function ($curlHandle, $fileHandle, int $length) use (&$payload, &$offset): string {
            $chunk = substr($payload, $offset, $length);
            $offset += strlen($chunk);
            return $chunk;
        },
    ]);

    $result = curl_exec($curl);
    $error = curl_error($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($result === false || $status >= 400) {
        error_log('[CERBERO contacto] Error SMTP: ' . ($error ?: 'estado ' . $status));
        return false;
    }

    return true;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    contacto_responder(false, 'Método no permitido.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    contacto_responder(false, 'Los datos enviados no son válidos.', 400);
}

if (trim((string) ($input['website'] ?? '')) !== '') {
    contacto_responder(true, 'Tu consulta fue enviada correctamente.');
}

$email = trim((string) ($input['email'] ?? ''));
$consulta = trim((string) ($input['consulta'] ?? ''));
$consultaLength = function_exists('mb_strlen') ? mb_strlen($consulta) : strlen($consulta);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
    contacto_responder(false, 'Ingresá un correo electrónico válido.', 422);
}

if ($consultaLength < 10 || $consultaLength > 2000) {
    contacto_responder(false, 'La consulta debe tener entre 10 y 2000 caracteres.', 422);
}

$lastContact = (int) ($_SESSION['cerbero_last_contact'] ?? 0);
if ($lastContact > 0 && time() - $lastContact < 30) {
    contacto_responder(false, 'Esperá unos segundos antes de enviar otra consulta.', 429);
}

$safeEmail = str_replace(["\r", "\n"], '', $email);
$subject = 'Nueva consulta desde CERBERO';
$body = "Nueva consulta recibida desde el sitio CERBERO.\n\n";
$body .= "Correo de contacto: {$safeEmail}\n";
$body .= "Fecha: " . date('d/m/Y H:i:s') . "\n\n";
$body .= "Consulta:\n{$consulta}\n";

$sent = contacto_enviar_smtp(CERBERO_CONTACT_EMAIL, $safeEmail, $subject, $body);

if (!$sent) {
    $from = trim((string) (getenv('CERBERO_MAIL_FROM') ?: 'no-reply@cerbero.local'));
    $from = str_replace(["\r", "\n"], '', $from);
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: Cerbero <' . $from . '>',
        'Reply-To: ' . $safeEmail,
    ];
    $sent = @mail(CERBERO_CONTACT_EMAIL, $subject, $body, implode("\r\n", $headers));
}

if (!$sent) {
    contacto_responder(
        false,
        'No se pudo enviar el mensaje desde el servidor. Escribinos directamente a cerberoSIGERU@gmail.com.',
        503
    );
}

$_SESSION['cerbero_last_contact'] = time();
contacto_responder(true, 'Tu consulta fue enviada correctamente a Cerbero.');
