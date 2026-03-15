# Reloo – Sharing-Kommune Webapp (PHP 8.4)

Reloo ist eine produktionsnahe, shared-hosting-fähige Webanwendung für lokale Gemeinschaften (Freunde, Nachbarschaften, Hausprojekte), um Gegenstände zu teilen, zu verschenken, zu tauschen und Reparaturen zu organisieren.

## Highlights
- MVC-nahe Struktur ohne Node.js/Docker-Zwang
- PHP 8.4 + PDO + MySQL/MariaDB
- Sichere Sessions, CSRF-Schutz, Passwort-Hashing, Output-Escaping
- Gruppenbasierte Sichtbarkeit (nur Mitglieder sehen interne Gegenstände)
- Module: Auth, Gruppen, Inventar, Anfragen/Ausleihen, Reparaturen, Admin
- Plesk-/FTP-/phpMyAdmin-kompatible Installation

## Struktur
- `public/` Einstiegspunkt, CSS, Rewrite
- `app/` Core, Controller, Models, Helper
- `views/` deutsche UI
- `sql/` vollständiges DB-Schema
- `docs/` Installationsanleitung
- `uploads/` Bilder
- `storage/` Logs

## Schnellstart lokal
1. Datenbank anlegen und `sql/schema.sql` importieren.
2. `config/config.example.php` nach `config/config.php` kopieren und Zugangsdaten eintragen.
3. Webroot auf `public/` setzen (oder App in Unterordner deployen und `base_path` setzen).
4. Mit PHP Built-in Server testen:
   `php -S 127.0.0.1:8080 -t public`

Mehr Details: `docs/INSTALLATION.md`

## Sicherheit / OWASP-Basics
- Prepared Statements (PDO) gegen SQL Injection
- CSRF-Token in allen mutierenden Formularen
- `htmlspecialchars`-Escaping für Ausgabe
- Sichere Session-Cookies (`httponly`, `samesite`, `secure` bei HTTPS)
- Upload-Validierung (MIME + Größe + randomisierte Dateinamen)

## Erweiterungen (V2)
- QR-Codes pro Gegenstand
- Mehrsprachigkeit (i18n)
- Kalenderansicht
- Vertrauenspunkte/Bewertungen
- Erinnerungsjobs (Cron)
- CSV-Import + REST-API
- WordPress-Bridge via SSO-Link oder Plugin-Wrapper
