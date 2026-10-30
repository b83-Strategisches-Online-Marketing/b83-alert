<?php
/**
 * Dashboard-Widget „Mitteilung“: der schnelle Weg für den Kunden.
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Admin;

use B83\Alert\Capabilities;

defined( 'ABSPATH' ) || exit;

final class DashboardWidget {

	public const ID = 'b83_alert_dashboard';

	public function __construct( private ContentForm $form ) {}

	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add(): void {
		if ( ! Capabilities::can_edit() ) {
			return;
		}
		/**
		 * Dashboard-Widget anzeigen?
		 *
		 * @param bool $show Standard: true.
		 */
		if ( ! apply_filters( 'b83_alert_dashboard_widget', true ) ) {
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

	public function enqueue( string $hook ): void {
		if ( 'index.php' === $hook && Capabilities::can_edit() ) {
			ContentForm::enqueue();
		}
	}

	public function render(): void {
		ContentForm::notice();
		$this->form->render( 'dashboard', true );
	}
}
