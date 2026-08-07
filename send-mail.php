<?php
// Verarbeitet das Kontaktformular serverseitig und leitet die Anfrage per E-Mail weiter.
// Es findet keine dauerhafte Speicherung der Formulardaten statt.

header('Content-Type: application/json; charset=UTF-8');

$recipient = 'm.reinhard01@web.de';

function respond(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

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

$headers = [];
$headers[] = 'From: MR Car Detailing Website <noreply@mr-cardetailing.de>';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';

$sent = mail($recipient, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));

if ($sent) {
    respond(true, 'Danke für Ihre Anfrage! Wir melden uns zeitnah bei Ihnen.');
} else {
    respond(false, 'Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es später erneut oder rufen Sie uns an.', 500);
}
