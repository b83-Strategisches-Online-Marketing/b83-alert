<?php
/**
 * Berechtigungen.
 *
 * Inhalt (Texte, aktiv, Zeitraum): standardmäßig ab Redakteur (edit_pages).
 * Konfiguration (Ort, Design, Tempo): nur Administratoren (manage_options).
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	/** Wer darf die Mitteilungen bearbeiten? */
	public static function edit(): string {
		/**
		 * Filtert die Berechtigung zum Bearbeiten der Mitteilungen.
		 *
		 * @param string $capability Standard: edit_pages (Redakteur).
		 */
		$cap = (string) apply_filters( 'b83_alert_edit_capability', 'edit_pages' );
		return '' !== $cap ? $cap : 'edit_pages';
	}

	/** Wer darf Anzeige und Design einstellen? */
	public static function config(): string {
		/**
		 * Filtert die Berechtigung für die Alert-Einstellungen.
		 *
		 * @param string $capability Standard: manage_options (Administrator).
		 */
		$cap = (string) apply_filters( 'b83_alert_config_capability', 'manage_options' );
		return '' !== $cap ? $cap : 'manage_options';
	}

	public static function can_edit(): bool {
		return current_user_can( self::edit() );
	}

	public static function can_config(): bool {
		return current_user_can( self::config() );
	}
}
