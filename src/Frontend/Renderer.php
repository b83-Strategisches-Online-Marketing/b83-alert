<?php
/**
 * Erzeugt das Markup für Pop-up, Laufschrift und Hinweisbox.
 *
 * Alle Ausgaben werden hier escaped. Farben und Maße kommen als
 * CSS-Variablen im style-Attribut (validierte Werte aus Settings).
 * Zeitfenster stehen in data-start / data-end, damit auch gecachte Seiten
 * die Mitteilung pünktlich ein- und ausblenden.
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Frontend;

use B83\Alert\Schedule;
use B83\Alert\Settings;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	/** Schattenstufen → CSS. */
	private const SHADOWS = array(
		'none'   => 'none',
		'soft'   => '0 12px 32px rgba(0,0,0,.18)',
		'strong' => '0 24px 64px rgba(0,0,0,.38)',
	);

	/**
	 * Pop-up.
	 *
	 * @param array<string,mixed> $s    Einstellungen.
	 * @param array<string,mixed> $opts preview: sofort sichtbar, statisch.
	 */
	public static function popup( array $s, array $opts = array() ): string {
		$preview = ! empty( $opts['preview'] );
		$level   = (string) $s['popup_level'];
		$labels  = Settings::level_labels();
		$button  = '' !== (string) $s['popup_button_label'] ? (string) $s['popup_button_label'] : __( 'Verstanden', 'b83-alert' );

		$style = self::vars(
			array(
				'--b83a-accent'  => Settings::level_color( $level ),
				'--b83a-bg'      => (string) $s['popup_bg'],
				'--b83a-text'    => (string) $s['popup_text'],
				'--b83a-radius'  => (int) $s['popup_radius'] . 'px',
				'--b83a-width'   => (int) $s['popup_width'] . 'px',
				'--b83a-overlay' => (string) round( (int) $s['popup_overlay'] / 100, 2 ),
				'--b83a-shadow'  => self::SHADOWS[ (string) $s['popup_shadow'] ] ?? self::SHADOWS['soft'],
			)
		);

		$attrs = array(
			'id'             => $preview ? 'b83-alert-popup-preview' : 'b83-alert-popup',
			'class'          => 'b83-alert-popup b83-alert--' . $level . ( $preview ? ' is-preview is-open' : '' ),
			'style'          => $style,
			'data-start'     => (string) (int) $s['popup_start'],
			'data-end'       => (string) (int) $s['popup_end'],
			'data-frequency' => (string) $s['popup_frequency'],
			'data-delay'     => (string) (int) $s['popup_delay'],
			'data-version'   => Schedule::version( 'popup', $s ),
		);
		if ( ! empty( $opts['force'] ) ) {
			$attrs['data-force'] = '1';
		}

		$html  = '<div' . self::attrs( $attrs ) . ( $preview ? '' : ' hidden' ) . '>';
		$html .= '<div class="b83-alert-popup__backdrop" data-b83-alert-close></div>';
		$html .= '<div class="b83-alert-popup__dialog" role="alertdialog" aria-modal="' . ( $preview ? 'false' : 'true' ) . '" aria-labelledby="' . esc_attr( $attrs['id'] ) . '-label" tabindex="-1">';
		$html .= '<div class="b83-alert-popup__bar">' . self::icon( $level ) . '<span id="' . esc_attr( $attrs['id'] ) . '-label">' . esc_html( $labels[ $level ] ?? '' ) . '</span>';
		$html .= '<button type="button" class="b83-alert-popup__x" data-b83-alert-close aria-label="' . esc_attr__( 'Schließen', 'b83-alert' ) . '"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button></div>';
		$html .= '<div class="b83-alert-popup__content">' . self::content( (string) $s['popup_content'] ) . '</div>';
		$html .= '<div class="b83-alert-popup__actions"><button type="button" class="b83-alert-popup__btn" data-b83-alert-close>' . esc_html( $button ) . '</button></div>';
		$html .= '</div></div>';

		/**
		 * Filtert das Pop-up-Markup.
		 *
		 * @param string              $html     Markup.
		 * @param array<string,mixed> $settings Einstellungen.
		 */
		return (string) apply_filters( 'b83_alert_popup_html', $html, $s );
	}

	/**
	 * Laufschrift.
	 *
	 * @param array<string,mixed> $s    Einstellungen.
	 * @param array<string,mixed> $opts position (inline|top|bottom), speed, class, preview.
	 */
	public static function ticker( array $s, array $opts = array() ): string {
		$preview  = ! empty( $opts['preview'] );
		$level    = (string) $s['ticker_level'];
		$position = in_array( $opts['position'] ?? 'inline', array( 'inline', 'top', 'bottom' ), true ) ? (string) ( $opts['position'] ?? 'inline' ) : 'inline';
		$speed    = isset( $opts['speed'] ) ? max( 20, min( 300, (int) $opts['speed'] ) ) : (int) $s['ticker_speed'];
		$text     = (string) $s['ticker_text'];
		$sep      = (string) $s['ticker_separator'];
		$link     = (string) $s['ticker_link'];
		$bg       = '' !== (string) $s['ticker_bg'] ? (string) $s['ticker_bg'] : Settings::level_color( $level );

		$classes = array( 'b83-alert-ticker', 'b83-alert--' . $level, 'is-' . $position );
		if ( $preview ) {
			$classes[] = 'is-preview';
		}
		foreach ( preg_split( '/\s+/', (string) ( $opts['class'] ?? '' ) ) ?: array() as $class ) {
			$class = sanitize_html_class( $class );
			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}

		$attrs = array(
			'class'      => implode( ' ', array_slice( $classes, 0, 12 ) ),
			'role'       => 'region',
			'aria-label' => __( 'Aktuelle Mitteilung', 'b83-alert' ),
			'style'      => self::vars(
				array(
					'--b83a-ticker-bg'    => $bg,
					'--b83a-ticker-color' => (string) $s['ticker_color'],
					'--b83a-ticker-size'  => (int) $s['ticker_font_size'] . 'px',
				)
			),
			'data-start' => (string) (int) $s['ticker_start'],
			'data-end'   => (string) (int) $s['ticker_end'],
			'data-speed' => (string) $speed,
		);
		if ( ! empty( $opts['force'] ) ) {
			$attrs['data-force'] = '1';
		}

		$item  = '<span class="b83-alert-ticker__item">' . esc_html( $text ) . '</span>';
		$item .= '' !== $sep ? '<span class="b83-alert-ticker__sep" aria-hidden="true">' . esc_html( $sep ) . '</span>' : '<span class="b83-alert-ticker__sep is-empty" aria-hidden="true"></span>';

		$inner  = '<span class="b83-alert-ticker__icon" aria-hidden="true">' . self::icon( $level ) . '</span>';
		$inner .= '<span class="b83-alert-screen-reader">' . esc_html( $text ) . '</span>';
		$inner .= '<span class="b83-alert-ticker__viewport" aria-hidden="true"><span class="b83-alert-ticker__track">' . $item . '</span></span>';

		if ( '' !== $link ) {
			$inner = '<a class="b83-alert-ticker__link" href="' . esc_url( $link ) . '">' . $inner . '</a>';
		}

		$html = '<div' . self::attrs( $attrs ) . self::hidden_until_start( (int) $s['ticker_start'], $opts ) . '>' . $inner . '</div>';

		/**
		 * Filtert das Laufschrift-Markup.
		 *
		 * @param string              $html     Markup.
		 * @param array<string,mixed> $settings Einstellungen.
		 */
		return (string) apply_filters( 'b83_alert_ticker_html', $html, $s );
	}

	/**
	 * Pop-up-Text als Hinweisbox im Inhalt.
	 *
	 * @param array<string,mixed> $s    Einstellungen.
	 * @param array<string,mixed> $opts force: Zeitplan im Browser übergehen (Vorschau).
	 */
	public static function box( array $s, array $opts = array() ): string {
		$level = (string) $s['popup_level'];
		$attrs = array(
			'class'      => 'b83-alert-box b83-alert--' . $level,
			'role'       => 'note',
			'style'      => self::vars(
				array(
					'--b83a-accent' => Settings::level_color( $level ),
					'--b83a-bg'     => (string) $s['popup_bg'],
					'--b83a-text'   => (string) $s['popup_text'],
					'--b83a-radius' => (int) $s['popup_radius'] . 'px',
				)
			),
			'data-start' => (string) (int) $s['popup_start'],
			'data-end'   => (string) (int) $s['popup_end'],
		);
		if ( ! empty( $opts['force'] ) ) {
			$attrs['data-force'] = '1';
		}
		$html  = '<div' . self::attrs( $attrs ) . self::hidden_until_start( (int) $s['popup_start'], $opts ) . '>';
		$html .= '<div class="b83-alert-box__bar">' . self::icon( $level ) . '<span>' . esc_html( Settings::level_labels()[ $level ] ?? '' ) . '</span></div>';
		$html .= '<div class="b83-alert-box__content">' . self::content( (string) $s['popup_content'] ) . '</div></div>';

		/**
		 * Filtert das Markup der Hinweisbox.
		 *
		 * @param string              $html     Markup.
		 * @param array<string,mixed> $settings Einstellungen.
		 */
		return (string) apply_filters( 'b83_alert_box_html', $html, $s );
	}

	/**
	 * Geplante Mitteilung zunächst verbergen – das Skript blendet sie zum
	 * Beginn ein (auch auf gecachten Seiten).
	 *
	 * @param array<string,mixed> $opts Optionen.
	 */
	private static function hidden_until_start( int $start, array $opts ): string {
		return ( $start > time() && empty( $opts['force'] ) && empty( $opts['preview'] ) ) ? ' hidden' : '';
	}

	/** Formatierter Inhalt, spät escaped. */
	private static function content( string $content ): string {
		return wp_kses_post( wpautop( $content ) );
	}

	/** Symbol je Art. */
	private static function icon( string $level ): string {
		$paths = array(
			'info'    => '<circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 11v6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="7.5" r="1.3" fill="currentColor"/>',
			'warning' => '<path d="M12 3.5 2.8 19.5h18.4L12 3.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17" r="1.25" fill="currentColor"/>',
			'alarm'   => '<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15L6 16.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M10 20.5a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 9a8 8 0 0 1 2.5-5M21 9a8 8 0 0 0-2.5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		);
		return '<svg class="b83-alert-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">' . ( $paths[ $level ] ?? $paths['warning'] ) . '</svg>';
	}

	/**
	 * CSS-Variablen als style-Wert. Nur validierte Werte; zusätzlich werden
	 * Zeichen entfernt, die aus einer Deklaration ausbrechen könnten.
	 *
	 * @param array<string,string> $vars Variablen.
	 */
	private static function vars( array $vars ): string {
		$out = array();
		foreach ( $vars as $name => $value ) {
			$out[] = $name . ':' . str_replace( array( ';', '{', '}', '<', '>', '"' ), '', $value );
		}
		return implode( ';', $out );
	}

	/** @param array<string,string> $attrs Attribute. */
	private static function attrs( array $attrs ): string {
		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		return $html;
	}
}
