# Reloo – Sharing-Kommune Webapp (PHP 8.4.11)

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
- Upload-Validierung (MIME + Größe + Mindestauflösung + randomisierte Dateinamen)
- Login-Throttling bei Fehlversuchen
- Hash-basierte, zeitlich begrenzte Passwort-Reset-Token

## UX / SEO
- Dashboard mit Quick-Actions und Empty-State-Hinweisen für bessere Orientierung
- Semantische Struktur mit `<main>` sowie canonical URL im Layout
- Modernes, minimalistisches Farbschema mit harmonischen Kontrasten

## E-Mail-Benachrichtigungen & Verifizierung
- Neue Konten müssen ihre E-Mail-Adresse per Bestätigungslink verifizieren.
- Login ist nur möglich, wenn E-Mail verifiziert **und** (falls aktiv) Admin-Freigabe erfolgt ist.
- Passwort-Reset sowie System-Benachrichtigungen werden per E-Mail und In-App erfasst.
- SMTP ist shared-hosting-tauglich direkt integriert (inkl. SMTPS/SSL auf Port 465, AUTH, UTF-8, Text+HTML).
- Konfiguration in `config/config.php` unter `mail` mit den Feldern `host`, `port`, `encryption`, `auth`, `username`, `password`, `from_address`, `from_name`, `sender`, `reply_to`, `timeout`.
- SMTP-Passwort nicht hardcodieren: per `RELOO_SMTP_PASSWORD` (ENV) oder in `config.php` setzen.
- Es gibt keinen Fallback auf `mail()`, `sendmail` oder lokale Mailer; fehlgeschlagene SMTP-Zustellung wird geloggt und als Fehler behandelt.

## Erweiterungen (V2)
- QR-Codes pro Gegenstand
- Mehrsprachigkeit (i18n)
- Kalenderansicht
- Vertrauenspunkte/Bewertungen
- Erinnerungsjobs (Cron)
- CSV-Import + REST-API
- WordPress-Bridge via SSO-Link oder Plugin-Wrapper
