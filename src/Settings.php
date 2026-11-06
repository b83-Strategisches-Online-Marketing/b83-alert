<?php
/**
 * Einstellungen: Lesen, Standardwerte und zentrale Validierung.
 *
 * Zwei Gruppen in einer Option:
 *  - Inhalt (CONTENT_KEYS): pflegt der Kunde im Dashboard-Widget bzw. auf
 *    der Seite „Alert“ – Texte, aktiv, Zeitraum.
 *  - Konfiguration (alle übrigen): pflegt der Administrator unter
 *    „Alert-Einstellungen“ – Anzeigeort, Seiten, Design, Tempo.
 *
 * Zeitpunkte werden als Unix-Zeitstempel gespeichert (0 = nicht gesetzt).
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'b83_alert_settings';

	/** Erlaubte Werte (Whitelist). */
	public const LEVELS          = array( 'info', 'warning', 'alarm' );
	public const POPUP_WHERE     = array( 'front', 'all', 'pages', 'shortcode' );
	public const POPUP_FREQUENCY = array( 'session', 'once', 'always' );
	public const SHADOWS         = array( 'none', 'soft', 'strong' );
	public const TICKER_WHERE    = array( 'shortcode', 'top', 'bottom' );
	public const DASHBOARD       = array( 'wide', 'widget' );

	/** Felder, die der Kunde bearbeitet. */
	public const CONTENT_KEYS = array(
		'popup_active',
		'popup_content',
		'popup_start',
		'popup_end',
		'popup_level',
		'ticker_active',
		'ticker_text',
		'ticker_link',
		'ticker_start',
		'ticker_end',
		'ticker_level',
	);

	/** Längenbegrenzungen. */
	public const MAX_CONTENT = 20000;
	public const MAX_TICKER  = 300;
	public const MAX_LABEL   = 60;
	public const MAX_PAGES   = 300;

	/** @var array<string,mixed>|null Zwischenspeicher je Seitenaufruf. */
	private static ?array $cache = null;

	/**
	 * Standardwerte.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Inhalt Pop-up.
			'popup_active'        => false,
			'popup_content'       => '',
			'popup_start'         => 0,
			'popup_end'           => 0,
			'popup_level'         => 'warning',

			// Inhalt Laufschrift.
			'ticker_active'       => false,
			'ticker_text'         => '',
			'ticker_link'         => '',
			'ticker_start'        => 0,
			'ticker_end'          => 0,
			'ticker_level'        => 'warning',

			// Pop-up: Anzeige.
			'popup_where'         => 'front',
			'popup_pages'         => array(),
			'popup_frequency'     => 'session',
			'popup_delay'         => 1,
			'popup_button_label'  => '',

			// Pop-up: Design.
			'popup_bg'            => '#ffffff',
			'popup_text'          => '#1d2327',
			'popup_shadow'        => 'soft',
			'popup_radius'        => 12,
			'popup_width'         => 560,
			'popup_overlay'       => 55,

			// Laufschrift: Anzeige und Design.
			'ticker_where'        => 'shortcode',
			'ticker_speed'        => 60,
			'ticker_separator'    => '+++',
			'ticker_bg'           => '',
			'ticker_color'        => '#ffffff',
			'ticker_font_size'    => 16,

			// Farben der Stufen.
			'color_info'          => '#1f6fb2',
			'color_warning'       => '#b85a12',
			'color_alarm'         => '#c62828',

			// Weiteres.
			'dashboard_layout'    => 'wide',
			'dashboard_side'      => 'b83_wl_notice',
			'purge_cache'         => true,
			'delete_on_uninstall' => false,
		);
	}

	/** @return array<string,mixed> */
	public static function get(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * Übernimmt nur die angegebenen Schlüssel, der Rest bleibt unverändert.
	 *
	 * @param array<string,mixed> $values Neue Werte (roh).
	 * @param string[]            $keys   Erlaubte Schlüssel.
	 */
	public static function update( array $values, array $keys ): void {
		$merged = self::get();
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $values ) ) {
				$merged[ $key ] = $values[ $key ];
			}
		}
		$clean = self::sanitize( $merged );
		update_option( self::OPTION, $clean, true );
		self::$cache = $clean;
	}

	/** Alle Schlüssel, die nicht zum Inhalt gehören. */
	public static function config_keys(): array {
		return array_values( array_diff( array_keys( self::defaults() ), self::CONTENT_KEYS ) );
	}

	public static function flush(): void {
		self::$cache = null;
	}

	/** Farbe einer Stufe (info, warning, alarm). */
	public static function level_color( string $level ): string {
		$key = 'color_' . ( in_array( $level, self::LEVELS, true ) ? $level : 'warning' );
		return (string) self::get()[ $key ];
	}

	/** Bezeichnungen der Stufen. @return array<string,string> */
	public static function level_labels(): array {
		return array(
			'info'    => __( 'Information', 'b83-alert' ),
			'warning' => __( 'Hinweis / Warnung', 'b83-alert' ),
			'alarm'   => __( 'Alarm / dringend', 'b83-alert' ),
		);
	}

	/**
	 * Zentrale Validierung (Whitelist). Unbekannte Schlüssel fallen weg.
	 *
	 * @param array<string,mixed> $input Rohdaten.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$d     = self::defaults();
		$clean = array();

		foreach ( array( 'popup_active', 'ticker_active', 'purge_cache', 'delete_on_uninstall' ) as $key ) {
			$clean[ $key ] = array_key_exists( $key, $input ) ? (bool) $input[ $key ] : (bool) $d[ $key ];
		}

		// Pop-up-Inhalt: erlaubtes HTML wie in Beiträgen, Länge begrenzt.
		$content                = is_string( $input['popup_content'] ?? null ) ? $input['popup_content'] : '';
		$clean['popup_content'] = trim( wp_kses_post( mb_substr( $content, 0, self::MAX_CONTENT ) ) );

		// Laufschrift: eine Zeile reiner Text.
		$text                 = is_string( $input['ticker_text'] ?? null ) ? $input['ticker_text'] : '';
		$clean['ticker_text'] = mb_substr( sanitize_text_field( $text ), 0, self::MAX_TICKER );

		$separator                 = is_string( $input['ticker_separator'] ?? null ) ? $input['ticker_separator'] : $d['ticker_separator'];
		$clean['ticker_separator'] = mb_substr( sanitize_text_field( $separator ), 0, 10 );

		$label                       = is_string( $input['popup_button_label'] ?? null ) ? $input['popup_button_label'] : '';
		$clean['popup_button_label'] = mb_substr( sanitize_text_field( $label ), 0, self::MAX_LABEL );

		$clean['ticker_link'] = self::sanitize_url( $input['ticker_link'] ?? '' );

		// Zeitpunkte.
		foreach ( array( 'popup', 'ticker' ) as $type ) {
			$start = self::sanitize_timestamp( $input[ $type . '_start' ] ?? 0 );
			$end   = self::sanitize_timestamp( $input[ $type . '_end' ] ?? 0 );
			if ( $start > 0 && $end > 0 && $end <= $start ) {
				$end = 0; // Ende vor dem Beginn ist ungültig.
			}
			$clean[ $type . '_start' ] = $start;
			$clean[ $type . '_end' ]   = $end;
		}

		$clean['popup_level']     = self::pick( $input['popup_level'] ?? null, self::LEVELS, $d['popup_level'] );
		$clean['ticker_level']    = self::pick( $input['ticker_level'] ?? null, self::LEVELS, $d['ticker_level'] );
		$clean['popup_where']     = self::pick( $input['popup_where'] ?? null, self::POPUP_WHERE, $d['popup_where'] );
		$clean['popup_frequency'] = self::pick( $input['popup_frequency'] ?? null, self::POPUP_FREQUENCY, $d['popup_frequency'] );
		$clean['popup_shadow']    = self::pick( $input['popup_shadow'] ?? null, self::SHADOWS, $d['popup_shadow'] );
		$clean['ticker_where']    = self::pick( $input['ticker_where'] ?? null, self::TICKER_WHERE, $d['ticker_where'] );
		$clean['dashboard_layout'] = self::pick( $input['dashboard_layout'] ?? null, self::DASHBOARD, $d['dashboard_layout'] );

		// Widget neben der Mitteilung: Widget-ID oder leer für keines.
		$side                    = is_string( $input['dashboard_side'] ?? null ) ? $input['dashboard_side'] : (string) $d['dashboard_side'];
		$clean['dashboard_side'] = substr( (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', $side ), 0, 100 );

		// Seiten-IDs für „ausgewählte Seiten“.
		$pages                = isset( $input['popup_pages'] ) && is_array( $input['popup_pages'] ) ? $input['popup_pages'] : array();
		$pages                = array_values( array_unique( array_filter( array_map( 'absint', $pages ) ) ) );
		$clean['popup_pages'] = array_slice( $pages, 0, self::MAX_PAGES );

		$clean['popup_delay']      = self::clamp( $input['popup_delay'] ?? $d['popup_delay'], 0, 60 );
		$clean['popup_radius']     = self::clamp( $input['popup_radius'] ?? $d['popup_radius'], 0, 40 );
		$clean['popup_width']      = self::clamp( $input['popup_width'] ?? $d['popup_width'], 320, 960 );
		$clean['popup_overlay']    = self::clamp( $input['popup_overlay'] ?? $d['popup_overlay'], 0, 90 );
		$clean['ticker_speed']     = self::clamp( $input['ticker_speed'] ?? $d['ticker_speed'], 20, 300 );
		$clean['ticker_font_size'] = self::clamp( $input['ticker_font_size'] ?? $d['ticker_font_size'], 12, 32 );

		foreach ( array( 'color_info', 'color_warning', 'color_alarm', 'ticker_color', 'popup_bg', 'popup_text' ) as $key ) {
			$color         = self::sanitize_color( $input[ $key ] ?? '' );
			$clean[ $key ] = '' !== $color ? $color : (string) $d[ $key ];
		}
		// Hintergrund der Laufschrift: leer = Farbe der Stufe.
		$clean['ticker_bg'] = self::sanitize_color( $input['ticker_bg'] ?? '' );

		return $clean;
	}

	/**
	 * @param mixed    $value   Eingabe.
	 * @param string[] $allowed Erlaubte Werte.
	 */
	private static function pick( $value, array $allowed, string $fallback ): string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/** @param mixed $value Eingabe. */
	private static function clamp( $value, int $min, int $max ): int {
		$number = is_numeric( $value ) ? (int) $value : $min;
		return max( $min, min( $max, $number ) );
	}

	/** @param mixed $value Eingabe. */
	public static function sanitize_timestamp( $value ): int {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}
		$ts = (int) $value;
		// Plausibel: 2020 bis 2100.
		return ( $ts >= 1577836800 && $ts <= 4102444800 ) ? $ts : 0;
	}

	/** @param mixed $value Eingabe. */
	public static function sanitize_color( $value ): string {
		$color = is_string( $value ) ? sanitize_hex_color( trim( $value ) ) : null;
		return is_string( $color ) ? strtolower( $color ) : '';
	}

	/** @param mixed $value Eingabe. */
	public static function sanitize_url( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( str_starts_with( $value, '/' ) && ! str_starts_with( $value, '//' ) ) {
			return mb_substr( esc_url_raw( home_url( $value ) ), 0, 500 );
		}
		return mb_substr( esc_url_raw( $value, array( 'http', 'https' ) ), 0, 500 );
	}

	/** Datum/Uhrzeit aus dem Formular (Y-m-d\TH:i, Zeitzone der Website). */
	public static function parse_datetime( $value ): int {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
			return 0;
		}
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		return $date ? self::sanitize_timestamp( $date->getTimestamp() ) : 0;
	}

	/** Zeitstempel für ein datetime-local-Feld ('' wenn nicht gesetzt). */
	public static function format_datetime( int $ts ): string {
		return $ts > 0 ? wp_date( 'Y-m-d\TH:i', $ts ) : '';
	}
}
