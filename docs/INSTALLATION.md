# Installation auf Plesk / klassischem WordPress-Hosting

> Zielumgebung: **PHP 8.4.11**, MySQL/MariaDB, Apache mit `mod_rewrite`.

## Express-Setup (Plesk in 10 Minuten)
1. Dateien in z. B. `/httpdocs/sharing-app/` hochladen.
2. Dokumentenstamm auf `/sharing-app/public` setzen.
3. Datenbank erstellen und `sql/schema.sql` importieren.
4. `config/config.example.php` → `config/config.php`, Zugangsdaten eintragen.
5. `base_path` auf `'/sharing-app'` setzen.
6. Erstes Konto registrieren, danach per SQL Admin-Rolle vergeben.

## 1) Dateien hochladen
- Projekt per FTP oder Plesk-Dateimanager hochladen, z. B. in `/httpdocs/sharing-app/`.
- Wichtig: Webroot sollte auf `public/` zeigen.
  - In Plesk: Domain > Hosting-Einstellungen > Dokumentenstamm auf `sharing-app/public` setzen.
  - Falls nicht möglich: Inhalte aus `public/` in den Webroot legen und Pfade im Projekt anpassen.

## 2) Datenbank einrichten
- In Plesk oder phpMyAdmin eine MySQL/MariaDB-Datenbank erstellen.
- `sql/schema.sql` vollständig importieren.

## 3) Konfiguration
- `config/config.example.php` nach `config/config.php` kopieren.
- DB-Daten eintragen.
- `base_path` setzen:
  - `''` für Domain-Root
  - `'/sharing-app'` für Unterordnerbetrieb

## 4) Schreibrechte
- Ordner `uploads/` und optional `storage/` beschreibbar machen (755/775 je nach Hosting).

## 5) Rewrite / Routing
- `.htaccess` in `public/` ist enthalten.
- Apache `mod_rewrite` muss aktiv sein (bei Plesk i. d. R. Standard).

## 6) Erstes Admin-Konto
- Erstes Konto normal registrieren.
- In phpMyAdmin Rolle auf `admin` setzen:
  `UPDATE users SET role='admin' WHERE email='deine@email.de';`

## 7) WordPress nebenan betreiben
- WordPress kann parallel auf derselben Domain laufen:
  - WP im Root, Reloo im Unterordner `/sharing-app`
  - im WP-Menü einen externen Link zur App setzen
- Optional später: kleines WP-Plugin als Menü-/SSO-Bridge.

## 8) E-Mail-Benachrichtigungen & Verifizierung
- Für Registrierung/Verifizierung und Benachrichtigungen ist SMTP vorkonfiguriert:
  - Host: `mandela.sui-inter.net`
  - Port: `465`
  - Verschlüsselung: `ssl`
  - Auth: `true`
  - Username/From: `notify@reloo.ch`
  - From-Name: `Reloo`
- Wichtig: SMTP-Passwort in `config/config.php` oder per ENV `RELOO_SMTP_PASSWORD` setzen (nicht im Code hinterlegen).
- Nach Registrierung wird ein 24h-Link versendet; ohne bestätigte E-Mail ist kein Login möglich.

## 9) SEO und Performance
- Semantische HTML-Struktur + Meta-Description bereits enthalten.
- Für bessere SEO: eigene Landingpage im WP-Root, App für eingeloggte Mitglieder im Unterordner.
