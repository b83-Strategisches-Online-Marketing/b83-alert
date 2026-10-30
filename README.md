# b83 Alert

Wichtige Mitteilungen schnell auf die Website bringen – als **Pop-up** mit Formatierung (Überschriften, Fett, Listen, Links) und/oder als **Laufschrift** (Ticker). Angezeigt wird nur, was befüllt und aktiv ist, auf Wunsch zeitgesteuert.

Beispiel: „Starker Schneefall in Abtenau“ – Text eintragen, „aktiv“ einschalten, optional „von/bis“ setzen, speichern. Fertig.

Entwickelt von [b83 – Strategisches Online Marketing](https://b83.at).

## Zwei Ebenen

| Wer | Wo | Was |
|---|---|---|
| Kunde (ab Redakteur) | **Dashboard-Widget „Mitteilung“** und **b83 → Alert** | Pop-up-Text, Laufschrift, Art (Information / Warnung / Alarm), aktiv, anzeigen ab / bis, Vorschau |
| Administrator | **b83 → Alert-Einstellungen** | Wo das Pop-up erscheint, Häufigkeit, Verzögerung, Design, Laufschrift-Position und -Tempo, Farben, Cache |

Redakteure sehen im b83-Menü nur „Alert“, nicht die Einstellungen.

## Pop-up

- **Erscheint auf:** Startseite · allen Seiten · ausgewählten Seiten (Häkchenliste mit Filter) · nur per Shortcode
- **Häufigkeit:** einmal pro Besuch · nur einmal (bis zu einer neuen Mitteilung) · bei jedem Seitenaufruf
- **Design:** Hintergrund, Textfarbe, Schatten (kein / dezent / kräftig), Ecken, Breite, Abdunklung – mit Live-Vorschau
- Responsiv: am Smartphone als Fenster von unten mit großem Button
- Barrierearm: `role="alertdialog"`, Fokus bleibt im Fenster, Escape schließt, Hintergrund scrollt nicht

## Laufschrift

- Eine Zeile Text, optional mit Link
- Position: per Shortcode · Leiste ganz oben auf allen Seiten · fixiert unten
- Tempo (px/s), Schriftgröße, Farben, Trennzeichen (Standard `+++`)
- Läuft nahtlos, hält bei Maus darüber an; bei „weniger Bewegung“ im Betriebssystem steht der Text still

## Shortcodes

| Shortcode | Wirkung |
|---|---|
| `[b83_alert_popup]` | Pop-up auf dieser Seite einblenden (zusätzlich zum eingestellten Ort) |
| `[b83_alert_ticker speed="80" class=""]` | Laufschrift an dieser Stelle |
| `[b83_alert_box]` | Pop-up-Text als Hinweisbox direkt im Inhalt |

Funktioniert in Elementor über das Shortcode-Widget.

## Zeitsteuerung und Cache

- Zeiten leer = sofort bzw. unbegrenzt (solange aktiv)
- Das Zeitfenster wird zusätzlich im Browser geprüft (`data-start` / `data-end`), damit auch gecachte Seiten pünktlich ein- und ausblenden
- Nach dem Speichern sowie zu Beginn und Ende wird der Seiten-Cache geleert (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround)

## Vorschau

`https://website/?b83_alert_preview=1` – zeigt angemeldeten Redakteuren Pop-up und Laufschrift sofort, auch wenn sie ausgeschaltet oder geplant sind.

## Hooks

**Filter**

| Filter | Zweck |
|---|---|
| `b83_alert_edit_capability` | Berechtigung zum Bearbeiten der Mitteilungen (Standard `edit_pages`) |
| `b83_alert_config_capability` | Berechtigung für die Einstellungen (Standard `manage_options`) |
| `b83_alert_dashboard_widget` | Dashboard-Widget anzeigen (bool) |
| `b83_alert_show_popup` | Pop-up auf der aktuellen Seite zeigen (bool, Einstellungen) |
| `b83_alert_show_ticker_bar` | Laufschrift-Leiste auf der aktuellen Seite zeigen (bool, Einstellungen) |
| `b83_alert_page_post_types` | Inhaltstypen in der Seitenauswahl (Standard `page`) |
| `b83_alert_popup_html` / `b83_alert_ticker_html` / `b83_alert_box_html` | Markup anpassen |

**Actions**

| Action | Zeitpunkt |
|---|---|
| `b83_alert_loaded` | Plugin geladen |
| `b83_alert_saved` | Mitteilung oder Einstellungen gespeichert |
| `b83_alert_settings_saved` | Einstellungen gespeichert |
| `b83_alert_changed` | Eine Mitteilung beginnt oder endet (WP-Cron) |
| `b83_alert_purge_cache` | Nach dem Leeren des Caches – für weitere Cache-Lösungen |

## CSS-Klassen

`.b83-alert-popup`, `.b83-alert-ticker` (`.is-top`, `.is-bottom`, `.is-inline`), `.b83-alert-box`, je Art `.b83-alert--info|warning|alarm`. Body-Klasse bei Leiste: `b83-alert-has-ticker-top|bottom`.

## Daten

Alles in der Option `b83_alert_settings`, Datenbank-Version in `b83_alert_db_version`. Beim Löschen des Plugins werden die Daten nur entfernt, wenn das in den Einstellungen aktiviert ist.

## Updates

Über GitHub-Releases (`Update URI`). Neue Version: Header, `B83_ALERT_VERSION` und `CHANGELOG.md` erhöhen, auf `main` pushen – die GitHub Action baut `b83-alert.zip`.

## Voraussetzungen

WordPress 6.2+, PHP 8.1+. Die Leiste „ganz oben“ nutzt `wp_body_open`; fehlt das im Theme, setzt das Skript sie automatisch an den Seitenanfang.
