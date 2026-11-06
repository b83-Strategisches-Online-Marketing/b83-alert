<?php
/**
 * Eingabeformular für den Kunden: Pop-up-Text und Laufschrift.
 *
 * Wird im Dashboard-Widget und auf der Seite „b83 → Alert“ verwendet und
 * speichert nur die Inhaltsfelder (Settings::CONTENT_KEYS).
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Admin;

use B83\Alert\Capabilities;
use B83\Alert\Schedule;
use B83\Alert\Settings;

defined( 'ABSPATH' ) || exit;

final class ContentForm {

	public const ACTION = 'b83_alert_save_content';

	/** Erlaubte Rücksprungziele (fester Schlüssel). */
	private const TARGETS = array( 'page', 'dashboard' );

	/** Erlaubte Hinweise (fester Schlüssel in der URL). */
	public const NOTICES = array( 'saved', 'invalid_time' );

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
	}

	/* ---------------------------------------------------------------------
	 * Speichern
	 * ------------------------------------------------------------------- */

	public function handle_save(): void {
		if ( ! Capabilities::can_edit() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-alert' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$raw = isset( $_POST['b83_alert'] ) && is_array( $_POST['b83_alert'] ) ? wp_unslash( $_POST['b83_alert'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- zentral in Settings::sanitize().

		$values = array();
		$notice = 'saved';
		foreach ( Schedule::TYPES as $type ) {
			$values[ $type . '_active' ] = ! empty( $raw[ $type . '_active' ] );
			$values[ $type . '_level' ]  = (string) ( $raw[ $type . '_level' ] ?? '' );
			$values[ $type . '_start' ]  = Settings::parse_datetime( $raw[ $type . '_start' ] ?? '' );
			$values[ $type . '_end' ]    = Settings::parse_datetime( $raw[ $type . '_end' ] ?? '' );
			if ( $values[ $type . '_start' ] > 0 && $values[ $type . '_end' ] > 0 && $values[ $type . '_end' ] <= $values[ $type . '_start' ] ) {
				$notice = 'invalid_time';
			}
		}
		$values['popup_content'] = (string) ( $raw['popup_content'] ?? '' );
		$values['ticker_text']   = (string) ( $raw['ticker_text'] ?? '' );
		$values['ticker_link']   = (string) ( $raw['ticker_link'] ?? '' );

		Settings::update( $values, Settings::CONTENT_KEYS );
		Schedule::reschedule();

		/**
		 * Nach dem Speichern der Mitteilungen.
		 *
		 * @param array<string,mixed> $settings Einstellungen.
		 */
		do_action( 'b83_alert_saved', Settings::get() );

		$target = isset( $raw['target'] ) && in_array( $raw['target'], self::TARGETS, true ) ? $raw['target'] : 'page';
		$url    = 'dashboard' === $target ? admin_url( 'index.php' ) : ContentPage::url();
		wp_safe_redirect( add_query_arg( 'b83_alert_notice', $notice, $url ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Ausgabe
	 * ------------------------------------------------------------------- */

	/**
	 * @param string $target  page oder dashboard.
	 * @param bool   $compact Kompakte Darstellung (schmales Dashboard-Widget).
	 * @param bool   $wide    Zwei Spalten: links Pop-up, rechts Laufschrift.
	 */
	public function render( string $target, bool $compact, bool $wide = false ): void {
		$settings = Settings::get();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="b83-alert-form' . ( $compact ? ' is-compact' : '' ) . ( $wide ? ' is-wide' : '' ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="b83_alert[target]" value="' . esc_attr( in_array( $target, self::TARGETS, true ) ? $target : 'page' ) . '">';
		wp_nonce_field( self::ACTION );

		echo '<div class="b83-alert-form__cols">';

		// Pop-up.
		echo '<div class="b83-alert-form__section is-popup">';
		$this->section_head( 'popup', __( 'Pop-up-Mitteilung', 'b83-alert' ), $settings );
		echo '<div class="b83-alert-form__body">';
		$editor_id = 'dashboard' === $target ? 'b83alertwidgeteditor' : 'b83alertpageeditor';
		wp_editor(
			(string) $settings['popup_content'],
			$editor_id,
			array(
				'textarea_name' => 'b83_alert[popup_content]',
				'textarea_rows' => $compact ? 7 : ( $wide ? 9 : 12 ),
				'media_buttons' => ! $compact,
				'teeny'         => false,
				'quicktags'     => true,
				'tinymce'       => array(
					'toolbar1'      => 'formatselect,bold,italic,underline,forecolor,bullist,numlist,alignleft,aligncenter,link,unlink,undo,redo',
					'toolbar2'      => '',
					'block_formats' => 'Absatz=p;Überschrift groß=h2;Überschrift mittel=h3;Überschrift klein=h4',
				),
			)
		);
		echo '<p class="description">' . esc_html__( 'Für eine Überschrift links in der Werkzeugleiste das Auswahlfeld „Absatz“ öffnen und „Überschrift groß“, „mittel“ oder „klein“ wählen. Ist das Feld leer, erscheint kein Pop-up.', 'b83-alert' ) . '</p>';
		$this->level_and_time( 'popup', $settings );
		echo '</div></div>';

		// Laufschrift.
		echo '<div class="b83-alert-form__section is-ticker">';
		$this->section_head( 'ticker', __( 'Laufschrift (Ticker)', 'b83-alert' ), $settings );
		echo '<div class="b83-alert-form__body">';
		printf(
			'<p><label class="b83-alert-form__label" for="b83-alert-ticker-text-%1$s">%2$s</label><input type="text" id="b83-alert-ticker-text-%1$s" class="large-text" name="b83_alert[ticker_text]" value="%3$s" maxlength="%4$d" placeholder="%5$s"></p>',
			esc_attr( $target ),
			esc_html__( 'Text (eine Zeile)', 'b83-alert' ),
			esc_attr( (string) $settings['ticker_text'] ),
			(int) Settings::MAX_TICKER,
			esc_attr__( 'z. B. Starker Schneefall in Abtenau – bitte Winterausrüstung mitführen', 'b83-alert' )
		);
		printf(
			'<p><label class="b83-alert-form__label" for="b83-alert-ticker-link-%1$s">%2$s</label><input type="url" id="b83-alert-ticker-link-%1$s" class="large-text" name="b83_alert[ticker_link]" value="%3$s" placeholder="https://"></p>',
			esc_attr( $target ),
			esc_html__( 'Link (optional – die Laufschrift wird anklickbar)', 'b83-alert' ),
			esc_attr( (string) $settings['ticker_link'] )
		);
		$this->level_and_time( 'ticker', $settings );
		echo '</div></div>';

		echo '</div>';

		echo '<p class="b83-alert-form__actions">';
		submit_button( __( 'Speichern', 'b83-alert' ), 'primary', 'submit', false );
		printf(
			' <a class="button" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( add_query_arg( 'b83_alert_preview', '1', home_url( '/' ) ) ),
			esc_html__( 'Vorschau auf der Website', 'b83-alert' )
		);
		echo '</p>';
		echo '</form>';
	}

	/** @param array<string,mixed> $settings Einstellungen. */
	private function section_head( string $type, string $title, array $settings ): void {
		echo '<div class="b83-alert-form__head">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		self::badge( $type, $settings );
		printf(
			'<label class="b83-alert-switch"><input type="checkbox" name="b83_alert[%1$s_active]" value="1"%2$s><span class="b83-alert-switch__ui" aria-hidden="true"></span><span class="b83-alert-switch__text">%3$s</span></label>',
			esc_attr( $type ),
			checked( (bool) $settings[ $type . '_active' ], true, false ),
			esc_html__( 'aktiv', 'b83-alert' )
		);
		echo '</div>';
	}

	/** @param array<string,mixed> $settings Einstellungen. */
	private function level_and_time( string $type, array $settings ): void {
		echo '<div class="b83-alert-form__row">';

		echo '<p><label class="b83-alert-form__label" for="b83-alert-' . esc_attr( $type ) . '-level">' . esc_html__( 'Art', 'b83-alert' ) . '</label>';
		echo '<select id="b83-alert-' . esc_attr( $type ) . '-level" name="b83_alert[' . esc_attr( $type ) . '_level]">';
		foreach ( Settings::level_labels() as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $settings[ $type . '_level' ], $value, false ), esc_html( $label ) );
		}
		echo '</select></p>';

		foreach ( array(
			'start' => __( 'Anzeigen ab', 'b83-alert' ),
			'end'   => __( 'Anzeigen bis', 'b83-alert' ),
		) as $suffix => $label ) {
			printf(
				'<p><label class="b83-alert-form__label" for="b83-alert-%1$s-%2$s">%3$s</label><input type="datetime-local" id="b83-alert-%1$s-%2$s" name="b83_alert[%1$s_%2$s]" value="%4$s"></p>',
				esc_attr( $type ),
				esc_attr( $suffix ),
				esc_html( $label ),
				esc_attr( Settings::format_datetime( (int) $settings[ $type . '_' . $suffix ] ) )
			);
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Zeiten leer lassen = sofort bzw. unbegrenzt anzeigen, solange „aktiv“ eingeschaltet ist.', 'b83-alert' ) . '</p>';
	}

	/**
	 * Statusanzeige einer Mitteilung.
	 *
	 * @param array<string,mixed> $settings Einstellungen.
	 * @param string              $prefix   Optionale Bezeichnung vor dem Status.
	 */
	public static function badge( string $type, array $settings, string $prefix = '' ): void {
		$state  = Schedule::state( $type, $settings );
		$format = get_option( 'date_format' ) . ', ' . get_option( 'time_format' );
		$label  = match ( $state ) {
			Schedule::LIVE      => (int) $settings[ $type . '_end' ] > 0
				/* translators: %s: Datum und Uhrzeit */
				? sprintf( __( 'wird angezeigt bis %s', 'b83-alert' ), wp_date( $format, (int) $settings[ $type . '_end' ] ) )
				: __( 'wird angezeigt', 'b83-alert' ),
			/* translators: %s: Datum und Uhrzeit */
			Schedule::SCHEDULED => sprintf( __( 'geplant ab %s', 'b83-alert' ), wp_date( $format, (int) $settings[ $type . '_start' ] ) ),
			Schedule::EXPIRED   => __( 'abgelaufen', 'b83-alert' ),
			Schedule::INACTIVE  => __( 'ausgeschaltet', 'b83-alert' ),
			default             => __( 'leer', 'b83-alert' ),
		};
		printf( '<span class="b83-alert-badge is-%1$s">%2$s</span>', esc_attr( $state ), esc_html( ( '' !== $prefix ? $prefix . ': ' : '' ) . $label ) );
	}

	/** Gespeichert-Hinweis (fester Schlüssel aus der URL). */
	public static function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige eines festen Hinweises.
		$key = isset( $_GET['b83_alert_notice'] ) ? sanitize_key( wp_unslash( $_GET['b83_alert_notice'] ) ) : '';
		if ( ! in_array( $key, self::NOTICES, true ) ) {
			return;
		}
		if ( 'invalid_time' === $key ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Gespeichert. Das Ende lag vor dem Beginn und wurde entfernt.', 'b83-alert' ) . '</p></div>';
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mitteilung gespeichert.', 'b83-alert' ) . '</p></div>';
	}

	/** Stile und Skript für das Formular. */
	public static function enqueue(): void {
		wp_enqueue_style( 'b83-alert-admin', B83_ALERT_URL . 'assets/css/admin.css', array(), B83_ALERT_VERSION );
		wp_enqueue_script( 'b83-alert-admin', B83_ALERT_URL . 'assets/js/admin.js', array( 'jquery' ), B83_ALERT_VERSION, true );
	}
}
