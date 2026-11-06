<?php
/**
 * Mitteilung im Dashboard: der schnelle Weg für den Kunden.
 *
 * Zwei Darstellungen (Alert-Einstellungen → Dashboard):
 *  - wide:   Panel über die volle Breite oberhalb der Widgets, Pop-up links,
 *            Laufschrift rechts (Standard)
 *  - widget: normales, verschiebbares Dashboard-Widget
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Admin;

use B83\Alert\Capabilities;
use B83\Alert\Settings;

defined( 'ABSPATH' ) || exit;

final class DashboardWidget {

	public const ID = 'b83_alert_dashboard';

	public function __construct( private ContentForm $form ) {}

	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'render_wide' ), 1 );
	}

	/** Mitteilung auf dem Dashboard zeigen? */
	private function enabled(): bool {
		if ( ! Capabilities::can_edit() ) {
			return false;
		}
		/**
		 * Mitteilung auf dem Dashboard anzeigen?
		 *
		 * @param bool $show Standard: true.
		 */
		return (bool) apply_filters( 'b83_alert_dashboard_widget', true );
	}

	private function is_wide(): bool {
		return 'wide' === Settings::get()['dashboard_layout'];
	}

	private function on_dashboard(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'dashboard' === $screen->id;
	}

	/* ---------------------------------------------------------------------
	 * Normales Widget
	 * ------------------------------------------------------------------- */

	public function add(): void {
		if ( ! $this->enabled() || $this->is_wide() ) {
			return;
		}
		wp_add_dashboard_widget( self::ID, __( 'Mitteilung (Pop-up & Laufschrift)', 'b83-alert' ), array( $this, 'render' ) );

		// Ganz nach oben in die erste Spalte, damit der Kunde es sofort findet.
		global $wp_meta_boxes;
		$normal = $wp_meta_boxes['dashboard']['normal']['core'] ?? null;
		if ( is_array( $normal ) && isset( $normal[ self::ID ] ) ) {
			$widget = array( self::ID => $normal[ self::ID ] );
			unset( $normal[ self::ID ] );
			$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $widget, $normal ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- übliche Methode zum Sortieren.
		}
	}

	public function render(): void {
		ContentForm::notice();
		$this->form->render( 'dashboard', true );
	}

	/* ---------------------------------------------------------------------
	 * Volle Breite
	 * ------------------------------------------------------------------- */

	/**
	 * Wird über admin_notices ausgegeben (dort für alle Rollen verfügbar) und
	 * per Skript direkt unter die Überschrift „Dashboard“ gesetzt – noch bevor
	 * der Editor startet.
	 */
	public function render_wide(): void {
		if ( ! $this->on_dashboard() || ! $this->enabled() || ! $this->is_wide() ) {
			return;
		}
		echo '<div id="b83-alert-wide" class="b83-alert-wide postbox">';
		echo '<details class="b83-alert-wide__details" open>';
		echo '<summary class="b83-alert-wide__summary"><span class="b83-alert-wide__title">' . esc_html__( 'Mitteilung (Pop-up & Laufschrift)', 'b83-alert' ) . '</span>';
		echo '<span class="b83-alert-wide__state">';
		ContentForm::badge( 'popup', Settings::get(), __( 'Pop-up', 'b83-alert' ) );
		ContentForm::badge( 'ticker', Settings::get(), __( 'Laufschrift', 'b83-alert' ) );
		echo '</span><span class="b83-alert-wide__toggle" aria-hidden="true"></span></summary>';
		echo '<div class="b83-alert-wide__inside">';
		ContentForm::notice();
		$this->form->render( 'dashboard', false, true );
		echo '</div></details></div>';
	}

	public function enqueue( string $hook ): void {
		if ( 'index.php' === $hook && $this->enabled() ) {
			ContentForm::enqueue();
		}
	}
}
