<?php
/**
 * Mitteilung im Dashboard: der schnelle Weg für den Kunden.
 *
 * Zwei Darstellungen (Alert-Einstellungen → Dashboard):
 *  - wide:   eigene Zeile oberhalb der Widgets (Standard). Links optional ein
 *            anderes Dashboard-Widget (Standard: Kundeninformation aus
 *            b83 White Label), rechts die Mitteilung mit Pop-up und
 *            Laufschrift. Fehlt das Widget, nimmt die Mitteilung die ganze
 *            Breite ein.
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

	/** Bekannte Dashboard-Widgets (für die Auswahl in den Einstellungen). */
	public const OPTION_KNOWN = 'b83_alert_known_widgets';

	/** Kennung des Kundeninformations-Widgets von b83 White Label. */
	public const WHITELABEL_WIDGET = 'b83_wl_notice';

	public function __construct( private ContentForm $form ) {}

	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'render_wide' ), 1 );
		add_action( 'admin_head-index.php', array( $this, 'remember_widgets' ) );
	}

	/**
	 * Merkt sich die Widgets, die nach allen Filtern (z. B. White Label) auf
	 * dem Dashboard übrig sind – nur bei Administratoren, nur bei Änderungen.
	 */
	public function remember_widgets(): void {
		if ( ! Capabilities::can_config() ) {
			return;
		}
		global $wp_meta_boxes;
		$known = array();
		foreach ( (array) ( $wp_meta_boxes['dashboard'] ?? array() ) as $priorities ) {
			foreach ( (array) $priorities as $widgets ) {
				foreach ( (array) $widgets as $id => $box ) {
					if ( is_array( $box ) && self::ID !== $id ) {
						$known[ (string) $id ] = mb_substr( trim( wp_strip_all_tags( (string) ( $box['title'] ?? $id ) ) ), 0, 80 );
					}
				}
			}
		}
		if ( get_option( self::OPTION_KNOWN, array() ) !== $known ) {
			update_option( self::OPTION_KNOWN, $known, false );
		}
	}

	/**
	 * Auswahl für „Neben der Mitteilung“ (ID => Titel).
	 *
	 * @return array<string,string>
	 */
	public static function side_choices(): array {
		$known = get_option( self::OPTION_KNOWN, array() );
		$known = is_array( $known ) ? $known : array();
		if ( ! isset( $known[ self::WHITELABEL_WIDGET ] ) ) {
			$known = array( self::WHITELABEL_WIDGET => __( 'Kundeninformation (b83 White Label)', 'b83-alert' ) ) + $known;
		}
		return array_map( 'strval', $known );
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
		/**
		 * Kennung des Dashboard-Widgets links neben der Mitteilung ('' = keines).
		 *
		 * @param string $id Standard: Einstellung „Neben der Mitteilung“.
		 */
		$side = (string) apply_filters( 'b83_alert_dashboard_side', (string) Settings::get()['dashboard_side'] );

		// Die Zeile; das Skript holt das gewählte Widget in die linke Spalte.
		printf( '<div id="b83-alert-row" class="b83-alert-row" data-side="%s">', esc_attr( $side ) );
		echo '<div class="b83-alert-row__side"></div>';
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
		echo '</div>';
	}

	public function enqueue( string $hook ): void {
		if ( 'index.php' === $hook && $this->enabled() ) {
			ContentForm::enqueue();
		}
	}
}
