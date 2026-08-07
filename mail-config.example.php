<?php
// Vorlage für die SMTP-Zugangsdaten des Kontaktformulars.
//
// So richtest du es ein:
// 1. In Hostinger hPanel: Websites -> mr-cardetailing.de -> E-Mails -> E-Mail-Konto erstellen
//    (z. B. kontakt@mr-cardetailing.de) und ein Passwort vergeben.
// 2. Auf der E-Mail-Konto-Seite in hPanel unter "Konfiguration"/"E-Mail-Client einrichten"
//    die genauen SMTP-Werte (Host, Port, Verschlüsselung) nachschauen - die können je nach
//    Hostinger-Plan leicht abweichen. Meist: smtp.hostinger.com, Port 465, Verschlüsselung ssl.
// 3. Diese Datei im Hostinger-Dateimanager (NICHT über Git!) als "mail-config.php" im selben
//    Ordner wie send-mail.php ablegen und die Platzhalter unten mit den echten Werten ersetzen.
// 4. mail-config.php steht in .gitignore und wird nie ins Repository/GitHub hochgeladen -
//    das Passwort bleibt privat.

return [
    'host' => 'smtp.hostinger.com',
    'port' => 465,
    'encryption' => 'ssl', // 'ssl' fuer Port 465, 'tls' fuer Port 587
    'username' => 'kontakt@mr-cardetailing.de',
    'password' => 'HIER-ECHTES-PASSWORT-EINTRAGEN',
    'from_email' => 'kontakt@mr-cardetailing.de',
    'from_name' => 'MR Car Detailing Website',
];
