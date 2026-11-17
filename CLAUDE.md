# b83 Alert – Projektregeln für Claude Code

> Diese Datei liest Claude Code bei jedem Start. Sie gilt für alle im Team.
> Persönliche Notizen gehören in `CLAUDE.local.md` (wird nicht eingecheckt).

## Über dieses Plugin

- **Name:** b83 Alert
- **Slug / Text-Domain:** `b83-alert`
- **Namespace:** `B83\Alert` (PSR-4-Autoloader auf `src/`)
- **Präfix für Funktionen, Optionen, Hooks, Capabilities, Nonces:** `b83_alert_`, Konstanten `B83_ALERT_`
- **Repository:** github.com/b83-Strategisches-Online-Marketing/b83-alert (öffentlich)
- **Vertrieb:** intern, nur für b83-Kundenseiten, vorerst kein Verkauf geplant
- **Im Einsatz auf:** offen (bei Bedarf ergänzen)
- **Zweck in einem Satz:** Wichtige Mitteilungen schnell als Pop-up und/oder Laufschrift (Ticker) veröffentlichen, zeitgesteuert und nur sichtbar, wenn befüllt und aktiv.

## b83-Standards (gelten für alle b83-Plugins)

### Plugin-Header
```
Author:            Wolfgang Grabs-Schrempf / b83
Author URI:        https://b83.at
Requires PHP:      8.1
Requires at least: 6.0
Text Domain:       b83-alert
License:           GPL-2.0-or-later
```
Kontakt in Doku und Support-Texten: office@b83.at

### Technik
- PHP 8.1 oder neuer. Typdeklarationen verwenden, wo sinnvoll.
- WordPress Coding Standards (WPCS). Kein schließendes `?>` am Dateiende.
- Jede PHP-Datei beginnt mit `defined( 'ABSPATH' ) || exit;`
- Klassen-Guards (`if ( ! class_exists( '...' ) )`) nur bei Klassen ohne Namespace.
- Einstellungen über die Settings API, Optionen gesammelt in einem Array unter `b83_alert_settings`.
- **Admin-Menü:** Einstellungen hängen unter dem gemeinsamen Menü „b83“ (Slug `b83`). Wenn das Menü noch nicht existiert, legt das Plugin es selbst an. Kein eigenes Top-Level-Menü.
- Muss ohne Elementor und ohne SEOPress funktionieren. Integrationen nur, wenn das jeweilige Plugin aktiv ist.
- Assets nur dort laden, wo sie gebraucht werden (nicht global im Frontend).

### Struktur
- Eine Zuständigkeit → eine Datei reicht.
- Zwei bis drei Bereiche → `includes/` mit einer Klasse pro Bereich.
- Vier oder mehr → tiefere Ordnerstruktur mit Autoloader.
- Assets in `assets/css`, `assets/js`, `assets/img`. Sprachdateien in `languages/`.

### Sicherheit (nicht verhandelbar)
- Jede Formular- und AJAX-Aktion: Nonce prüfen **und** `current_user_can()`.
- Eingaben mit `sanitize_*` bereinigen, Ausgaben mit `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` escapen.
- Datenbank nur über `$wpdb->prepare()`.
- AJAX: vor `wp_send_json_*()` den Output-Buffer leeren (`ob_clean()` falls aktiv), sonst kaputtes JSON.
- Keine Secrets im Code. Tokens kommen aus `wp-config.php` (z. B. `B83_GITHUB_TOKEN`).

### Updates und Lizenz
- Öffentliche Repos: Updates direkt über GitHub-Releases.
- Private Repos: Update-Abfrage mit `B83_GITHUB_TOKEN` aus der `wp-config.php` (Fine-grained Token, nur Lesen).
- Freemius-Plugins: SDK im Ordner `freemius/`, nicht verändern. Freemius-Secrets nie in öffentliche Repos.

### Sprache und Texte
- Code-Kommentare und Bezeichner auf Englisch.
- Oberfläche übersetzbar (`__()`, `esc_html__()` usw.). Verkaufs-Plugins: Englisch als Quellsprache, Deutsch als Übersetzung.
- Beschreibungen, readme.txt, Doku und Marketingtexte ohne typische KI-Schreibmuster formulieren (Humanizer-Regeln): keine Floskeln, keine Übertreibungen, keine Gedankenstrich-Ketten.

## Arbeitsweise

- Vor größeren Änderungen kurz den Plan nennen, dann umsetzen.
- Nach jeder Änderung: `php -l` auf geänderte Dateien.
- Bestehende Shortcodes, Optionen und Hooks bleiben abwärtskompatibel, außer es ist ausdrücklich anders entschieden.
- Bei Reviews trennen: **kritisch** (Sicherheit, Datenverlust, Fatal Errors) vs. **sollte** vs. **wäre schön**.
- Versionsnummer nur beim Release erhöhen (siehe `/release`).
- Commit-Nachrichten auf Deutsch, kurz, im Präsens („Fügt Ticker-Tempo hinzu“).

## Entscheidungen in diesem Plugin

> Hier festhalten, was entschieden wurde, damit niemand es neu diskutiert.
> Wo eine Entscheidung von den b83-Standards oben abweicht, gilt für dieses Plugin die Entscheidung.

### Header und Kennzeichnung (abweichend von der Vorlage)
- Header bleibt wie bisher: `Author: b83 – Wolfgang Grabs-Schrempf`, `Plugin URI: https://b83.at`, `Requires at least: 6.2`, `Update URI: https://github.com/b83-Strategisches-Online-Marketing/b83-alert`.

### Struktur (abweichend von der Vorlage: `src/` statt `includes/`)
- Hauptdatei `b83-alert.php` enthält nur Header, Konstanten (`B83_ALERT_VERSION`, `_FILE`, `_DIR`, `_URL`), PSR-4-Autoloader und Bootstrap.
- Logik in Klassen unter `src/`, Backend in `src/Admin/`, Ausgabe in `src/Frontend/`, Fremd-Plugins in `src/Integrations/`.
- CSS/JS als eigene Dateien unter `assets/`, kein Inline-Code außer kleiner Konfiguration (`wp_localize_script`).
- `uninstall.php` löscht Daten nur, wenn der Nutzer das in den Einstellungen aktiviert hat (`delete_on_uninstall`).
- `SECURITY.md` (Meldeweg für Lücken) und `.github/dependabot.yml` (Actions aktuell halten) bleiben im Repository.
- `README.md` und `CHANGELOG.md` pflegen; Version in Header, `B83_ALERT_VERSION` und Changelog gleich halten.

### Backend-Menü
- `src/Admin/B83Menu.php` ist unverändert aus dem Schneebericht übernommen (nur Namespace und Text Domain angepasst), dazu `assets/img/b83-logo.svg`, `assets/img/b83-menu-icon.svg`, `assets/css/b83-menu.css`.
- Eigene Seite mit `add_submenu_page( B83Menu::SLUG, … )` einhängen, Link über Filter `b83_admin_plugin_links` melden.
- Bei Änderungen an B83Menu `MENU_VERSION` erhöhen – die neueste Version auf einer Website baut das Menü.
- Nur ein Menüpunkt „Alert“ mit den Reitern „Mitteilung“ (ab Redakteur) und „Einstellungen“ (nur Administratoren). Alte Links auf „Alert-Einstellungen“ werden weitergeleitet.

### Sicherheit (Ergänzungen)
- Eingaben: `wp_unslash`, dann zentral validieren (Whitelist statt Blacklist), Längen begrenzen.
- Ausgaben immer spät escapen.
- REST-Routen immer mit `permission_callback`.
- Uploads: `is_uploaded_file`, Größen- und Formatprüfung.
- Keine Daten im Plugin-Ordner speichern (wird bei Updates ersetzt).
- Hinweise per festem Schlüssel in der URL, nie Freitext aus der URL ausgeben.

### Erweiterbarkeit
- Eigene Actions/Filter mit Präfix `b83_alert_` an sinnvollen Stellen, im README dokumentieren.
- Datenbank-Version in der Option `b83_alert_db_version`, Migrationen in `src/Migration.php`.
- Bestehende Shortcodes (`[b83_alert_popup]`, `[b83_alert_ticker]`, `[b83_alert_box]`), CSS-Klassen und Endpunkte bei Umbauten kompatibel halten.

### Sprache (abweichend von der Vorlage)
- Benutzeroberfläche Deutsch, alle Texte über `__()` / `esc_html__()` mit Text Domain `b83-alert` übersetzbar.
- Kommentare auf Deutsch (bestehender Code ist durchgehend deutsch kommentiert).

### Updates und Releases
- Eigene `Updater`-Klasse (`src/Updater.php`, Filter `update_plugins_github.com`), Release-Asset heißt `b83-alert.zip`.
- `.github/workflows/release.yml` erstellt das Release automatisch, sobald eine neue Version auf `main` liegt.
- Cache-relevante Daten: Nach dem Speichern sowie zu Beginn und Ende einer Mitteilung den Seiten-Cache leeren (`src/Integrations/CachePurge.php`).

### Versionen und Commits
- Version im Header, in `B83_ALERT_VERSION` und im CHANGELOG gleichzeitig erhöhen (Semantic Versioning: Fehlerbehebung = x.y.Z, neue Funktion = x.Y.0, inkompatibel = X.0.0).
