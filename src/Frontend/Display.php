<?php
/**
 * Ausgabe im Frontend: automatisches Pop-up, Laufschrift-Leiste und
 * Shortcodes.
 *
 * Shortcodes:
 *  [b83_alert_popup]                    – Pop-up auf dieser Seite einblenden
 *  [b83_alert_ticker speed="" class=""] – Laufschrift an dieser Stelle
 *  [b83_alert_box]                      – Pop-up-Text als Hinweisbox im Inhalt
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Frontend;

use B83\Alert\Capabilities;
use B83\Alert\Schedule;
use B83\Alert\Settings;

defined( 'ABSPATH' ) || exit;

final class Display {

	/** Pop-up per Shortcode angefordert. */
	private bool $popup_requested = false;

	/** Pop-up bereits ausgegeben. */
	private bool $popup_done = false;

	/** Leiste oben bereits ausgegeben. */
	private bool $top_done = false;

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_body_open', array( $this, 'top_bar' ), 5 );
		add_action( 'wp_footer', array( $this, 'footer' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ) );

		add_shortcode( 'b83_alert_popup', array( $this, 'shortcode_popup' ) );
		add_shortcode( 'b83_alert_ticker', array( $this, 'shortcode_ticker' ) );
		add_shortcode( 'b83_alert_box', array( $this, 'shortcode_box' ) );
	}

	/* ---------------------------------------------------------------------
	 * Entscheidungen
	 * ------------------------------------------------------------------- */

	/** Vorschau für angemeldete Bearbeiter (?b83_alert_preview=1). */
	public static function is_preview(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige für berechtigte Nutzer.
		return isset( $_GET['b83_alert_preview'] ) && is_user_logged_in() && Capabilities::can_edit();
	}

	/** Im Elementor-Editor kein Pop-up über dem Arbeitsbereich. */
	private function in_builder(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Erkennung des Editors.
		if ( isset( $_GET['elementor-preview'] ) ) {
			return true;
		}
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode();
	}

	/** Soll das Pop-up auf dieser Seite erscheinen? */
	public function popup_applies(): bool {
		if ( is_admin() || is_feed() || $this->in_builder() ) {
			return false;
		}
		$s = Settings::get();
		if ( self::is_preview() ) {
			return Schedule::has_content( 'popup', $s );
		}
		if ( ! Schedule::should_render( 'popup', $s ) ) {
			return false;
		}

		$applies = $this->popup_requested || match ( (string) $s['popup_where'] ) {
			'all'   => true,
			'front' => is_front_page(),
			'pages' => is_singular() && in_array( (int) get_queried_object_id(), array_map( 'intval', (array) $s['popup_pages'] ), true ),
			default => false,
		};

		/**
		 * Filtert, ob das Pop-up auf der aktuellen Seite erscheint.
		 *
		 * @param bool                $applies  Ergebnis.
		 * @param array<string,mixed> $settings Einstellungen.
		 */
		return (bool) apply_filters( 'b83_alert_show_popup', $applies, $s );
	}

	/** Position der automatischen Leiste (top, bottom) oder '' für keine. */
	private function bar_position(): string {
		if ( is_admin() || is_feed() ) {
			return '';
		}
		$s     = Settings::get();
		$where = (string) $s['ticker_where'];
		if ( self::is_preview() ) {
			if ( ! Schedule::has_content( 'ticker', $s ) ) {
				return '';
			}
			return 'shortcode' === $where ? 'top' : $where;
		}
		if ( 'shortcode' === $where || ! Schedule::should_render( 'ticker', $s ) ) {
			return '';
		}

		/**
		 * Filtert, ob die Laufschrift-Leiste auf der aktuellen Seite erscheint.
		 *
		 * @param bool                $show     Ergebnis.
		 * @param array<string,mixed> $settings Einstellungen.
		 */
		return apply_filters( 'b83_alert_show_ticker_bar', true, $s ) ? $where : '';
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------- */

	public function enqueue(): void {
		wp_register_style( 'b83-alert', B83_ALERT_URL . 'assets/css/alert.css', array(), B83_ALERT_VERSION );
		wp_register_script( 'b83-alert', B83_ALERT_URL . 'assets/js/alert.js', array(), B83_ALERT_VERSION, true );

		$needed = $this->popup_applies() || '' !== $this->bar_position();
		if ( ! $needed && is_singular() ) {
			$content = (string) get_post_field( 'post_content', get_queried_object_id() );
			$needed  = has_shortcode( $content, 'b83_alert_popup' ) || has_shortcode( $content, 'b83_alert_ticker' ) || has_shortcode( $content, 'b83_alert_box' );
		}
		if ( $needed ) {
			$this->load_assets();
		}
	}

	private function load_assets(): void {
		wp_enqueue_style( 'b83-alert' );
		wp_enqueue_script( 'b83-alert' );
	}

	/** @param string[] $classes Body-Klassen. */
	public function body_class( array $classes ): array {
		$position = $this->bar_position();
		if ( '' !== $position ) {
			$classes[] = 'b83-alert-has-ticker-' . $position;
		}
		return $classes;
	}

	/* ---------------------------------------------------------------------
	 * Ausgabe
	 * ------------------------------------------------------------------- */

	public function top_bar(): void {
		if ( 'top' !== $this->bar_position() || $this->top_done ) {
			return;
		}
		$this->top_done = true;
		echo Renderer::ticker( Settings::get(), $this->force_opts( array( 'position' => 'top' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
	}

	public function footer(): void {
		$position = $this->bar_position();
		// Theme ohne wp_body_open: Leiste im Footer ausgeben, das Skript setzt sie nach oben.
		if ( 'top' === $position && ! $this->top_done ) {
			$this->top_done = true;
			echo Renderer::ticker( Settings::get(), $this->force_opts( array( 'position' => 'top', 'class' => 'is-moved' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
		}
		if ( 'bottom' === $position ) {
			echo Renderer::ticker( Settings::get(), $this->force_opts( array( 'position' => 'bottom' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
		}
		if ( ! $this->popup_done && $this->popup_applies() ) {
			$this->popup_done = true;
			$this->load_assets();
			echo Renderer::popup( Settings::get(), $this->force_opts() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
		}
	}

	/**
	 * In der Vorschau Zeitplan und Häufigkeit im Browser übergehen.
	 *
	 * @param array<string,mixed> $opts Optionen.
	 * @return array<string,mixed>
	 */
	private function force_opts( array $opts = array() ): array {
		if ( self::is_preview() ) {
			$opts['force'] = true;
		}
		return $opts;
	}

	/* ---------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------- */

	/** Fordert das Pop-up an; ausgegeben wird es im Footer. */
	public function shortcode_popup(): string {
		$this->popup_requested = true;
		if ( Schedule::should_render( 'popup' ) || self::is_preview() ) {
			$this->load_assets();
		}
		return '';
	}

	/** @param array<string,string>|string $atts Attribute. */
	public function shortcode_ticker( $atts ): string {
		$atts = shortcode_atts(
			array(
				'speed' => '',
				'class' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'b83_alert_ticker'
		);
		$s     = Settings::get();
		$force = self::is_preview() && Schedule::has_content( 'ticker', $s );
		if ( ! $force && ! Schedule::should_render( 'ticker', $s ) ) {
			return '';
		}
		$this->load_assets();

		$opts = array(
			'position' => 'inline',
			'class'    => (string) $atts['class'],
		);
		if ( '' !== $atts['speed'] ) {
			$opts['speed'] = absint( $atts['speed'] );
		}
		return Renderer::ticker( $s, $this->force_opts( $opts ) );
	}

	public function shortcode_box(): string {
		$s     = Settings::get();
		$force = self::is_preview() && Schedule::has_content( 'popup', $s );
		if ( ! $force && ! Schedule::should_render( 'popup', $s ) ) {
			return '';
		}
		$this->load_assets();
		return Renderer::box( $s, $this->force_opts() );
	}
}
