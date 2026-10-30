<?php
/**
 * Leert den Seiten-Cache, wenn sich eine Mitteilung ändert: nach dem
 * Speichern sowie zu Beginn und Ende (per WP-Cron).
 *
 * Pop-up und Laufschrift können auf allen Seiten erscheinen – deshalb wird
 * der gesamte Cache geleert.
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Integrations;

use B83\Alert\Settings;

defined( 'ABSPATH' ) || exit;

final class CachePurge {

	public function register(): void {
		add_action( 'b83_alert_saved', array( $this, 'purge_all' ) );
		add_action( 'b83_alert_changed', array( $this, 'purge_all' ) );
	}

	public function purge_all(): void {
		if ( ! Settings::get()['purge_cache'] ) {
			return;
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain(); // WP Rocket.
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all(); // W3 Total Cache.
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache(); // WP Super Cache.
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache(); // SiteGround.
		}
		do_action( 'litespeed_purge_all' ); // LiteSpeed Cache.

		/**
		 * Nach dem Leeren des Caches – für weitere Cache-Lösungen.
		 */
		do_action( 'b83_alert_purge_cache' );
	}

	/** Name des erkannten Cache-Plugins für die Anzeige im Backend ('' wenn keins). */
	public static function detected_plugin(): string {
		return match ( true ) {
			function_exists( 'rocket_clean_domain' )       => 'WP Rocket',
			defined( 'LSCWP_V' )                           => 'LiteSpeed Cache',
			function_exists( 'w3tc_flush_all' )            => 'W3 Total Cache',
			function_exists( 'wp_cache_clear_cache' )      => 'WP Super Cache',
			function_exists( 'sg_cachepress_purge_cache' ) => 'SiteGround Speed Optimizer',
			default                                        => '',
		};
	}
}
