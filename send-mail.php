<?php
// Verarbeitet das Kontaktformular serverseitig und verschickt die Anfrage per
// authentifiziertem SMTP-Versand (kein Weiterleiten zu einem E-Mail-Programm,
// keine dauerhafte Speicherung der Formulardaten).

header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$recipient = 'm.reinhard01@web.de';

function respond(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Fängt Fatal Errors (z. B. Syntaxfehler in mail-config.php) ab, damit die
// Antwort nie leer bleibt, sondern immer eine auswertbare JSON-Fehlermeldung ist.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('send-mail.php Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
        }
        echo json_encode(['success' => false, 'message' => 'Interner Fehler beim Versand. Bitte rufen Sie uns direkt an.']);
    }
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Ungültige Anfrage.', 405);
}

// Honeypot-Feld: für Menschen unsichtbar, Bots füllen es oft trotzdem aus.
if (!empty($_POST['hp_check'])) {
    respond(true, 'Danke für Ihre Anfrage!');
}

function cleanField(string $value): string {
    $value = trim($value);
    // Zeilenumbrüche entfernen, um Header-Injection in E-Mail-Headern zu verhindern.
    return preg_replace('/[\r\n]+/', ' ', $value);
}

$name = isset($_POST['name']) ? cleanField($_POST['name']) : '';
$phone = isset($_POST['phone']) ? cleanField($_POST['phone']) : '';
$vehicle = isset($_POST['vehicle']) ? cleanField($_POST['vehicle']) : '';
$service = isset($_POST['service']) ? cleanField($_POST['service']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($name === '' || $phone === '') {
    respond(false, 'Bitte Name und Telefonnummer angeben.', 422);
}

if (mb_strlen($name) > 200 || mb_strlen($phone) > 100 || mb_strlen($vehicle) > 200 || mb_strlen($service) > 200 || mb_strlen($message) > 5000) {
    respond(false, 'Eingabe zu lang.', 422);
}

$subject = 'Neue Terminanfrage über die Website – ' . ($service !== '' ? $service : 'Aufbereitung');

$body = "Neue Anfrage über das Kontaktformular auf mr-cardetailing.de\n\n";
$body .= "Name: {$name}\n";
$body .= "Telefon: {$phone}\n";
$body .= "Fahrzeug: {$vehicle}\n";
$body .= "Gewünschte Leistung: {$service}\n\n";
$body .= "Nachricht:\n{$message}\n";

/**
 * Minimaler SMTP-Client ohne externe Abhängigkeiten.
 * Gibt [true, 'OK'] bei Erfolg zurück, sonst [false, 'Fehlermeldung'].
 */
function smtpSend(array $cfg, string $to, string $subject, string $body): array {
    $prefix = $cfg['encryption'] === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client($prefix . $cfg['host'] . ':' . $cfg['port'], $errno, $errstr, 15);
    if (!$socket) {
        return [false, "Verbindung fehlgeschlagen: {$errstr}"];
    }
    stream_set_timeout($socket, 15);

    $read = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };
    $expect = function (string $code) use ($read): array {
        $resp = $read();
        return [substr($resp, 0, 3) === $code, $resp];
    };

    [$ok, $resp] = $expect('220');
    if (!$ok) {
        fclose($socket);
        return [false, "Kein SMTP-Gruß: {$resp}"];
    }

    $write('EHLO mr-cardetailing.de');
    [$ok, $resp] = $expect('250');
    if (!$ok) {
        fclose($socket);
        return [false, "EHLO fehlgeschlagen: {$resp}"];
    }

    if ($cfg['encryption'] === 'tls') {
        $write('STARTTLS');
        [$ok, $resp] = $expect('220');
        if (!$ok) {
            fclose($socket);
            return [false, "STARTTLS fehlgeschlagen: {$resp}"];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return [false, 'TLS-Verschlüsselung fehlgeschlagen.'];
        }
        $write('EHLO mr-cardetailing.de');
        [$ok, $resp] = $expect('250');
        if (!$ok) {
            fclose($socket);
            return [false, "EHLO (TLS) fehlgeschlagen: {$resp}"];
        }
    }

    $write('AUTH LOGIN');
    [$ok, $resp] = $expect('334');
    if (!$ok) {
        fclose($socket);
        return [false, "AUTH LOGIN fehlgeschlagen: {$resp}"];
    }

    $write(base64_encode($cfg['username']));
    [$ok, $resp] = $expect('334');
    if (!$ok) {
        fclose($socket);
        return [false, "Benutzername abgelehnt: {$resp}"];
    }

    $write(base64_encode($cfg['password']));
    [$ok, $resp] = $expect('235');
    if (!$ok) {
        fclose($socket);
        return [false, "Login fehlgeschlagen: {$resp}"];
    }

    $write('MAIL FROM:<' . $cfg['from_email'] . '>');
    [$ok, $resp] = $expect('250');
    if (!$ok) {
        fclose($socket);
        return [false, "MAIL FROM fehlgeschlagen: {$resp}"];
    }

    $write('RCPT TO:<' . $to . '>');
    $resp = $read();
    if (!in_array(substr($resp, 0, 3), ['250', '251'], true)) {
        fclose($socket);
        return [false, "RCPT TO fehlgeschlagen: {$resp}"];
    }

    $write('DATA');
    [$ok, $resp] = $expect('354');
    if (!$ok) {
        fclose($socket);
        return [false, "DATA fehlgeschlagen: {$resp}"];
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'From: ' . $cfg['from_name'] . ' <' . $cfg['from_email'] . '>',
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Date: ' . date('r'),
    ];

    // SMTP-Dot-Stuffing: Zeilen, die mit einem Punkt beginnen, verdoppeln.
    $bodyLines = explode("\n", str_replace("\r\n", "\n", $body));
    foreach ($bodyLines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);

    $fullMessage = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $bodyLines) . "\r\n.";
    $write($fullMessage);
    [$ok, $resp] = $expect('250');
    if (!$ok) {
        fclose($socket);
        return [false, "Senden fehlgeschlagen: {$resp}"];
    }

    $write('QUIT');
    fclose($socket);
    return [true, 'OK'];
}

$configPath = __DIR__ . '/mail-config.php';
if (!file_exists($configPath)) {
    error_log('send-mail.php: mail-config.php fehlt – siehe mail-config.example.php.');
    respond(false, 'Der Formular-Versand ist noch nicht vollständig eingerichtet. Bitte rufen Sie uns direkt an.', 500);
}

try {
    $config = require $configPath;
} catch (\Throwable $e) {
    error_log('send-mail.php: Fehler beim Laden von mail-config.php: ' . $e->getMessage());
    respond(false, 'Der Formular-Versand ist falsch konfiguriert. Bitte rufen Sie uns direkt an.', 500);
}

if (!is_array($config) || empty($config['host']) || empty($config['username']) || empty($config['password'])) {
    error_log('send-mail.php: mail-config.php liefert kein gültiges Konfigurations-Array.');
    respond(false, 'Der Formular-Versand ist falsch konfiguriert. Bitte rufen Sie uns direkt an.', 500);
}

try {
    [$success, $info] = smtpSend($config, $recipient, $subject, $body);
} catch (\Throwable $e) {
    error_log('send-mail.php: Ausnahme beim SMTP-Versand: ' . $e->getMessage());
    respond(false, 'Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es später erneut oder rufen Sie uns an.', 500);
}

if ($success) {
    respond(true, 'Danke für Ihre Anfrage! Wir melden uns zeitnah bei Ihnen.');
}

error_log('send-mail.php SMTP-Fehler: ' . $info);
respond(false, 'Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es später erneut oder rufen Sie uns an.', 500);
