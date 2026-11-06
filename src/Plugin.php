<?php
/**
 * Einstiegspunkt: verdrahtet alle Komponenten.
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert;

use B83\Alert\Admin\ContentForm;
use B83\Alert\Admin\ContentPage;
use B83\Alert\Admin\DashboardWidget;
use B83\Alert\Admin\SettingsPage;
use B83\Alert\Frontend\Display;
use B83\Alert\Integrations\CachePurge;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( Migration::class, 'maybe_run' ) );

		( new Schedule() )->register();
		( new CachePurge() )->register();
		( new Updater() )->register();
		( new Display() )->register();

		if ( is_admin() ) {
			$form = new ContentForm();
			$form->register();
			// Ein Menüpunkt „Alert“ mit den Reitern Mitteilung und Einstellungen.
			$settings = new SettingsPage();
			$settings->register();
			( new ContentPage( $form, $settings ) )->register();
			( new DashboardWidget( $form ) )->register();
		}

		/**
		 * Nach dem Laden aller Komponenten. Erweiterungen können sich hier einhängen.
		 *
		 * @param Plugin $plugin Instanz.
		 */
		do_action( 'b83_alert_loaded', $this );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'b83-alert', false, dirname( plugin_basename( B83_ALERT_FILE ) ) . '/languages' );
	}
}
