<?php
/**
 * Installation, Deaktivierung und Datenbank-Versionen.
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert;

defined( 'ABSPATH' ) || exit;

final class Migration {

	public const DB_VERSION        = 1;
	public const OPTION_DB_VERSION = 'b83_alert_db_version';

	public static function on_activation(): void {
		self::run();
		Schedule::reschedule();
	}

	public static function on_deactivation(): void {
		Schedule::unschedule();
	}

	/** Läuft im Backend, tut aber nur bei Versionswechsel etwas. */
	public static function maybe_run(): void {
		if ( (int) get_option( self::OPTION_DB_VERSION, 0 ) < self::DB_VERSION ) {
			self::run();
		}
	}

	private static function run(): void {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::sanitize( Settings::defaults() ), '', true );
		}
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
	}
}
