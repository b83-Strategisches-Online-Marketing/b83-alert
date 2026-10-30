<?php
/**
 * Zeitsteuerung der beiden Mitteilungen.
 *
 * Zustände: empty (nichts eingetragen), inactive (ausgeschaltet),
 * scheduled (Beginn liegt in der Zukunft), live (wird angezeigt),
 * expired (Ende ist vorbei).
 *
 * Wegen Seiten-Caches wird zusätzlich im Browser geprüft (data-start /
 * data-end), und zu Beginn und Ende wird der Cache per WP-Cron geleert.
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert;

defined( 'ABSPATH' ) || exit;

final class Schedule {

	public const EMPTY     = 'empty';
	public const INACTIVE  = 'inactive';
	public const SCHEDULED = 'scheduled';
	public const LIVE      = 'live';
	public const EXPIRED   = 'expired';

	public const TYPES = array( 'popup', 'ticker' );

	/** Cron-Hook für Beginn/Ende einer Mitteilung. */
	public const CRON_HOOK = 'b83_alert_switch';

	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'on_switch' ) );
	}

	/**
	 * Zustand einer Mitteilung.
	 *
	 * @param string                   $type     popup oder ticker.
	 * @param array<string,mixed>|null $settings Einstellungen (Standard: gespeicherte).
	 * @param int|null                 $now      Zeitpunkt (Standard: jetzt).
	 */
	public static function state( string $type, ?array $settings = null, ?int $now = null ): string {
		$settings = $settings ?? Settings::get();
		$now      = $now ?? time();

		if ( ! self::has_content( $type, $settings ) ) {
			return self::EMPTY;
		}
		if ( empty( $settings[ $type . '_active' ] ) ) {
			return self::INACTIVE;
		}
		$start = (int) ( $settings[ $type . '_start' ] ?? 0 );
		$end   = (int) ( $settings[ $type . '_end' ] ?? 0 );
		if ( $end > 0 && $end <= $now ) {
			return self::EXPIRED;
		}
		if ( $start > 0 && $start > $now ) {
			return self::SCHEDULED;
		}
		return self::LIVE;
	}

	/**
	 * Soll das Markup ausgegeben werden? Auch bei „geplant“, damit gecachte
	 * Seiten die Mitteilung pünktlich im Browser einblenden.
	 *
	 * @param array<string,mixed>|null $settings Einstellungen.
	 */
	public static function should_render( string $type, ?array $settings = null, ?int $now = null ): bool {
		return in_array( self::state( $type, $settings, $now ), array( self::LIVE, self::SCHEDULED ), true );
	}

	/** @param array<string,mixed> $settings Einstellungen. */
	public static function has_content( string $type, array $settings ): bool {
		if ( 'popup' === $type ) {
			// Nur Leerzeichen, &nbsp; oder leere Absätze zählen nicht.
			$text = trim( str_replace( "\xc2\xa0", ' ', html_entity_decode( wp_strip_all_tags( (string) ( $settings['popup_content'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) ) );
			return '' !== $text || str_contains( (string) ( $settings['popup_content'] ?? '' ), '<img' );
		}
		return '' !== trim( (string) ( $settings['ticker_text'] ?? '' ) );
	}

	/**
	 * Fingerabdruck einer Mitteilung: ändert sich mit dem Inhalt, damit ein
	 * bereits weggeklicktes Pop-up bei neuer Mitteilung wieder erscheint.
	 *
	 * @param array<string,mixed>|null $settings Einstellungen.
	 */
	public static function version( string $type, ?array $settings = null ): string {
		$settings = $settings ?? Settings::get();
		$parts    = 'popup' === $type
			? array( $settings['popup_content'], $settings['popup_start'], $settings['popup_level'] )
			: array( $settings['ticker_text'], $settings['ticker_start'] );
		return substr( md5( (string) wp_json_encode( $parts ) ), 0, 10 );
	}

	/* ---------------------------------------------------------------------
	 * Cron: Cache zu Beginn und Ende leeren
	 * ------------------------------------------------------------------- */

	/** Plant für alle künftigen Beginn-/End-Zeitpunkte einen Lauf ein. */
	public static function reschedule(): void {
		self::unschedule();
		$settings = Settings::get();
		$now      = time();
		$times    = array();
		foreach ( self::TYPES as $type ) {
			if ( empty( $settings[ $type . '_active' ] ) ) {
				continue;
			}
			foreach ( array( '_start', '_end' ) as $suffix ) {
				$ts = (int) $settings[ $type . $suffix ];
				if ( $ts > $now ) {
					$times[] = $ts;
				}
			}
		}
		foreach ( array_unique( $times ) as $ts ) {
			wp_schedule_single_event( $ts + 5, self::CRON_HOOK, array( $ts ) );
		}
	}

	public static function unschedule(): void {
		wp_unschedule_hook( self::CRON_HOOK );
	}

	/** @param int $timestamp Geplanter Zeitpunkt (nur zur Unterscheidung der Läufe). */
	public function on_switch( $timestamp = 0 ): void {
		/**
		 * Eine Mitteilung beginnt oder endet jetzt.
		 *
		 * @param int $timestamp Zeitpunkt.
		 */
		do_action( 'b83_alert_changed', (int) $timestamp );
	}
}
