# Installation auf Plesk / klassischem WordPress-Hosting

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

## 8) E-Mail-Benachrichtigungen
- Standardtreiber ist `mail()`.
- SMTP kann in `config/config.php` vorbereitet werden.

## 9) SEO und Performance
- Semantische HTML-Struktur + Meta-Description bereits enthalten.
- Für bessere SEO: eigene Landingpage im WP-Root, App für eingeloggte Mitglieder im Unterordner.

