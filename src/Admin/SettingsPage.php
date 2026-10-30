<?php
/**
 * Seite „b83 → Alert-Einstellungen“: Anzeigeort, Design und Tempo
 * (nur Administratoren).
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Admin;

use B83\Alert\Capabilities;
use B83\Alert\Frontend\Renderer;
use B83\Alert\Integrations\CachePurge;
use B83\Alert\Schedule;
use B83\Alert\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public const SLUG = 'b83-alert-settings';

	private const ACTION = 'b83_alert_save_settings';

	/** Erlaubte Hinweise (fester Schlüssel in der URL). */
	private const NOTICES = array( 'saved' );

	private string $hook = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( B83_ALERT_FILE ), array( $this, 'action_links' ) );

		( new B83Menu( B83_ALERT_URL, B83_ALERT_DIR ) )->register();
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public function add_menu(): void {
		$this->hook = (string) add_submenu_page(
			B83Menu::SLUG,
			__( 'Alert-Einstellungen', 'b83-alert' ),
			__( 'Alert-Einstellungen', 'b83-alert' ),
			Capabilities::config(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue( string $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'b83-alert-admin', B83_ALERT_URL . 'assets/css/admin.css', array(), B83_ALERT_VERSION );
		// Frontend-Stile für die Live-Vorschau.
		wp_enqueue_style( 'b83-alert', B83_ALERT_URL . 'assets/css/alert.css', array(), B83_ALERT_VERSION );
		wp_enqueue_script( 'b83-alert', B83_ALERT_URL . 'assets/js/alert.js', array(), B83_ALERT_VERSION, true );
		wp_enqueue_script( 'b83-alert-settings', B83_ALERT_URL . 'assets/js/settings.js', array( 'jquery', 'wp-color-picker', 'b83-alert' ), B83_ALERT_VERSION, true );
	}

	/**
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( ContentPage::url() ) . '">' . esc_html__( 'Mitteilung', 'b83-alert' ) . '</a>',
			'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Einstellungen', 'b83-alert' ) . '</a>'
		);
		return $links;
	}

	/* ---------------------------------------------------------------------
	 * Speichern
	 * ------------------------------------------------------------------- */

	public function handle_save(): void {
		if ( ! Capabilities::can_config() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-alert' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$raw = isset( $_POST['b83_alert'] ) && is_array( $_POST['b83_alert'] ) ? wp_unslash( $_POST['b83_alert'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- zentral in Settings::sanitize().

		$values = array();
		foreach ( Settings::config_keys() as $key ) {
			if ( in_array( $key, array( 'purge_cache', 'delete_on_uninstall' ), true ) ) {
				$values[ $key ] = ! empty( $raw[ $key ] );
				continue;
			}
			if ( 'popup_pages' === $key ) {
				// Nur Seiten übernehmen, die es wirklich gibt.
				$ids             = isset( $raw['popup_pages'] ) && is_array( $raw['popup_pages'] ) ? array_map( 'absint', $raw['popup_pages'] ) : array();
				$values[ $key ] = array_values( array_intersect( $ids, array_keys( $this->available_pages() ) ) );
				continue;
			}
			if ( array_key_exists( $key, $raw ) ) {
				$values[ $key ] = $raw[ $key ];
			}
		}

		Settings::update( $values, Settings::config_keys() );

		/**
		 * Nach dem Speichern der Alert-Einstellungen.
		 *
		 * @param array<string,mixed> $settings Einstellungen.
		 */
		do_action( 'b83_alert_settings_saved', Settings::get() );
		do_action( 'b83_alert_saved', Settings::get() );

		wp_safe_redirect( add_query_arg( 'b83_alert_notice', 'saved', self::url() ) );
		exit;
	}

	/**
	 * Seiten für die Auswahl (ID => eingerückter Titel).
	 *
	 * @return array<int,string>
	 */
	private function available_pages(): array {
		/**
		 * Inhaltstypen für die Auswahl „ausgewählte Seiten“.
		 *
		 * @param string[] $post_types Standard: page.
		 */
		$types = (array) apply_filters( 'b83_alert_page_post_types', array( 'page' ) );
		$list  = array();
		foreach ( $types as $type ) {
			$type = sanitize_key( (string) $type );
			if ( ! post_type_exists( $type ) ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => array( 'publish', 'private' ),
					'posts_per_page' => 500,
					'orderby'        => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'no_found_rows'  => true,
				)
			);
			// Hierarchie: Unterseiten eingerückt unter ihren Eltern.
			$walk = static function ( int $parent, int $depth ) use ( &$walk, $posts, &$list ): void {
				foreach ( $posts as $post ) {
					if ( (int) $post->post_parent === $parent ) {
						$title             = '' !== $post->post_title ? $post->post_title : __( '(ohne Titel)', 'b83-alert' );
						$list[ $post->ID ] = str_repeat( '— ', $depth ) . $title;
						$walk( (int) $post->ID, $depth + 1 );
					}
				}
			};
			$walk( 0, 0 );
			// Seiten, deren Eltern fehlen (z. B. Entwurf), trotzdem anbieten.
			foreach ( $posts as $post ) {
				if ( ! isset( $list[ $post->ID ] ) ) {
					$list[ $post->ID ] = '' !== $post->post_title ? $post->post_title : __( '(ohne Titel)', 'b83-alert' );
				}
			}
		}
		return $list;
	}

	/* ---------------------------------------------------------------------
	 * Ausgabe
	 * ------------------------------------------------------------------- */

	public function render(): void {
		if ( ! Capabilities::can_config() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-alert' ), '', array( 'response' => 403 ) );
		}
		$s = Settings::get();

		echo '<div class="wrap b83-alert-settings">';
		echo '<h1>' . esc_html__( 'Alert-Einstellungen', 'b83-alert' ) . '</h1>';
		$this->notice();

		echo '<div class="b83-alert-status">';
		foreach ( array(
			'popup'  => __( 'Pop-up', 'b83-alert' ),
			'ticker' => __( 'Laufschrift', 'b83-alert' ),
		) as $type => $label ) {
			echo '<div class="b83-alert-status__card"><span>' . esc_html( $label ) . '</span>';
			ContentForm::badge( $type, $s );
			echo '</div>';
		}
		$cache = CachePurge::detected_plugin();
		echo '<div class="b83-alert-status__card"><span>' . esc_html__( 'Cache-Plugin', 'b83-alert' ) . '</span><strong>' . esc_html( '' !== $cache ? $cache : __( 'keines erkannt', 'b83-alert' ) ) . '</strong></div>';
		echo '<div class="b83-alert-status__card"><a class="button" href="' . esc_url( ContentPage::url() ) . '">' . esc_html__( 'Mitteilung bearbeiten', 'b83-alert' ) . '</a></div>';
		echo '</div>';

		echo '<div class="b83-alert-settings__layout"><div class="b83-alert-settings__main">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="b83-alert-settings-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );

		/* Pop-up: Anzeige */
		echo '<h2>' . esc_html__( 'Pop-up – wo und wie oft', 'b83-alert' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->radio_row(
			'popup_where',
			__( 'Erscheint auf', 'b83-alert' ),
			array(
				'front'     => __( 'Startseite', 'b83-alert' ),
				'all'       => __( 'Allen Seiten der Website', 'b83-alert' ),
				'pages'     => __( 'Ausgewählten Seiten', 'b83-alert' ),
				'shortcode' => __( 'Nur wo der Shortcode [b83_alert_popup] steht', 'b83-alert' ),
			),
			(string) $s['popup_where'],
			__( 'Der Shortcode [b83_alert_popup] löst das Pop-up zusätzlich auf jeder Seite aus, auf der er steht (z. B. im Elementor-Shortcode-Widget).', 'b83-alert' )
		);

		echo '<tr class="b83-alert-pages-row"><th scope="row">' . esc_html__( 'Seiten', 'b83-alert' ) . '</th><td>';
		echo '<input type="search" class="regular-text b83-alert-pages__filter" placeholder="' . esc_attr__( 'Seiten filtern …', 'b83-alert' ) . '" aria-label="' . esc_attr__( 'Seiten filtern', 'b83-alert' ) . '">';
		echo '<div class="b83-alert-pages">';
		$front    = (int) get_option( 'page_on_front' );
		$selected = array_map( 'intval', (array) $s['popup_pages'] );
		foreach ( $this->available_pages() as $id => $title ) {
			printf(
				'<label><input type="checkbox" name="b83_alert[popup_pages][]" value="%1$d"%2$s> %3$s%4$s</label>',
				(int) $id,
				checked( in_array( (int) $id, $selected, true ), true, false ),
				esc_html( $title ),
				$id === $front ? ' <em>(' . esc_html__( 'Startseite', 'b83-alert' ) . ')</em>' : ''
			);
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Nur wirksam bei „Ausgewählten Seiten“.', 'b83-alert' ) . '</p>';
		echo '</td></tr>';

		$this->radio_row(
			'popup_frequency',
			__( 'Häufigkeit', 'b83-alert' ),
			array(
				'session' => __( 'Einmal pro Besuch (bis der Browser geschlossen wird)', 'b83-alert' ),
				'once'    => __( 'Nur einmal – erst bei einer neuen Mitteilung wieder', 'b83-alert' ),
				'always'  => __( 'Bei jedem Seitenaufruf', 'b83-alert' ),
			),
			(string) $s['popup_frequency']
		);

		$this->number_row( 'popup_delay', __( 'Verzögerung', 'b83-alert' ), (int) $s['popup_delay'], 0, 60, __( 'Sekunden', 'b83-alert' ) );
		$this->text_row( 'popup_button_label', __( 'Beschriftung Schließen-Button', 'b83-alert' ), (string) $s['popup_button_label'], __( 'Verstanden', 'b83-alert' ) );
		echo '</tbody></table>';

		/* Pop-up: Design */
		echo '<h2>' . esc_html__( 'Pop-up – Design', 'b83-alert' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->color_row( 'popup_bg', __( 'Hintergrund', 'b83-alert' ), (string) $s['popup_bg'], '#ffffff' );
		$this->color_row( 'popup_text', __( 'Textfarbe', 'b83-alert' ), (string) $s['popup_text'], '#1d2327' );
		$this->radio_row(
			'popup_shadow',
			__( 'Schatten', 'b83-alert' ),
			array(
				'none'   => __( 'Kein Schatten', 'b83-alert' ),
				'soft'   => __( 'Dezent', 'b83-alert' ),
				'strong' => __( 'Kräftig', 'b83-alert' ),
			),
			(string) $s['popup_shadow']
		);
		$this->range_row( 'popup_radius', __( 'Ecken abrunden', 'b83-alert' ), (int) $s['popup_radius'], 0, 40, 'px' );
		$this->range_row( 'popup_width', __( 'Maximale Breite', 'b83-alert' ), (int) $s['popup_width'], 320, 960, 'px', 20 );
		$this->range_row( 'popup_overlay', __( 'Hintergrund abdunkeln', 'b83-alert' ), (int) $s['popup_overlay'], 0, 90, '%', 5 );
		echo '</tbody></table>';

		/* Laufschrift */
		echo '<h2>' . esc_html__( 'Laufschrift (Ticker)', 'b83-alert' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->radio_row(
			'ticker_where',
			__( 'Position', 'b83-alert' ),
			array(
				'shortcode' => __( 'Nur wo der Shortcode [b83_alert_ticker] steht', 'b83-alert' ),
				'top'       => __( 'Als Leiste ganz oben auf allen Seiten', 'b83-alert' ),
				'bottom'    => __( 'Als fixierte Leiste am unteren Bildschirmrand', 'b83-alert' ),
			),
			(string) $s['ticker_where'],
			__( 'Die Leiste oben braucht ein Theme mit wp_body_open (Hello Elementor, Astra, GeneratePress u. a. haben das).', 'b83-alert' )
		);
		$this->range_row( 'ticker_speed', __( 'Tempo', 'b83-alert' ), (int) $s['ticker_speed'], 20, 300, 'px/s', 5 );
		$this->range_row( 'ticker_font_size', __( 'Schriftgröße', 'b83-alert' ), (int) $s['ticker_font_size'], 12, 32, 'px' );
		$this->color_row( 'ticker_bg', __( 'Hintergrund', 'b83-alert' ), (string) $s['ticker_bg'], '', __( 'Leer = Farbe der gewählten Art (Information, Warnung, Alarm).', 'b83-alert' ) );
		$this->color_row( 'ticker_color', __( 'Textfarbe', 'b83-alert' ), (string) $s['ticker_color'], '#ffffff' );
		$this->text_row( 'ticker_separator', __( 'Trennzeichen', 'b83-alert' ), (string) $s['ticker_separator'], '+++' );
		echo '</tbody></table>';

		/* Farben */
		echo '<h2>' . esc_html__( 'Farben der Arten', 'b83-alert' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->color_row( 'color_info', __( 'Information', 'b83-alert' ), (string) $s['color_info'], '#1f6fb2' );
		$this->color_row( 'color_warning', __( 'Hinweis / Warnung', 'b83-alert' ), (string) $s['color_warning'], '#b85a12' );
		$this->color_row( 'color_alarm', __( 'Alarm / dringend', 'b83-alert' ), (string) $s['color_alarm'], '#c62828' );
		echo '</tbody></table>';

		/* Weiteres */
		echo '<h2>' . esc_html__( 'Weiteres', 'b83-alert' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->checkbox_row( 'purge_cache', __( 'Cache leeren', 'b83-alert' ), __( 'Nach dem Speichern sowie zu Beginn und Ende einer Mitteilung den Seiten-Cache leeren (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround).', 'b83-alert' ), (bool) $s['purge_cache'] );
		$this->checkbox_row( 'delete_on_uninstall', __( 'Beim Löschen', 'b83-alert' ), __( 'Beim Löschen des Plugins alle Mitteilungen und Einstellungen entfernen.', 'b83-alert' ), (bool) $s['delete_on_uninstall'] );
		echo '</tbody></table>';

		submit_button( __( 'Einstellungen speichern', 'b83-alert' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Shortcodes', 'b83-alert' ) . '</h2>';
		echo '<p><code>[b83_alert_popup]</code> – ' . esc_html__( 'Pop-up auf dieser Seite einblenden.', 'b83-alert' ) . '<br>';
		echo '<code>[b83_alert_ticker]</code> – ' . esc_html__( 'Laufschrift an dieser Stelle (Optionen: speed="80", class="").', 'b83-alert' ) . '<br>';
		echo '<code>[b83_alert_box]</code> – ' . esc_html__( 'Pop-up-Text als Hinweisbox direkt im Seiteninhalt.', 'b83-alert' ) . '</p>';

		echo '</div>';

		// Live-Vorschau mit Beispieltext.
		echo '<aside class="b83-alert-settings__preview" aria-label="' . esc_attr__( 'Vorschau', 'b83-alert' ) . '">';
		echo '<h2>' . esc_html__( 'Vorschau', 'b83-alert' ) . '</h2>';
		echo '<div class="b83-alert-preview" id="b83-alert-preview">';
		$sample               = $s;
		$sample['ticker_text'] = '' !== $s['ticker_text'] ? $s['ticker_text'] : __( 'Starker Schneefall in Abtenau – bitte Winterausrüstung mitführen', 'b83-alert' );
		if ( ! Schedule::has_content( 'popup', $s ) ) {
			$sample['popup_content'] = '<h3>' . __( 'Starker Schneefall', 'b83-alert' ) . '</h3><p>' . __( 'Bitte rechnen Sie mit Verzögerungen bei der Anreise. Schneeketten werden empfohlen.', 'b83-alert' ) . '</p>';
		}
		echo Renderer::ticker( $sample, array( 'preview' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
		echo '<div class="b83-alert-preview__stage">';
		echo Renderer::popup( $sample, array( 'preview' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
		echo '</div></div>';
		echo '<p class="description">' . esc_html__( 'Design-Änderungen sind sofort sichtbar; gespeichert wird erst mit „Einstellungen speichern“.', 'b83-alert' ) . '</p>';
		echo '</aside>';

		echo '</div></div>';
	}

	private function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige eines festen Hinweises.
		$key = isset( $_GET['b83_alert_notice'] ) ? sanitize_key( wp_unslash( $_GET['b83_alert_notice'] ) ) : '';
		if ( in_array( $key, self::NOTICES, true ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Einstellungen gespeichert.', 'b83-alert' ) . '</p></div>';
		}
	}

	/** @param array<string,string> $options Optionen. */
	private function radio_row( string $key, string $label, array $options, string $value, string $description = '' ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><fieldset class="b83-alert-radios" data-key="' . esc_attr( $key ) . '">';
		foreach ( $options as $option => $text ) {
			printf(
				'<label><input type="radio" name="b83_alert[%1$s]" value="%2$s"%3$s> %4$s</label><br>',
				esc_attr( $key ),
				esc_attr( $option ),
				checked( $value, $option, false ),
				esc_html( $text )
			);
		}
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</fieldset></td></tr>';
	}

	private function color_row( string $key, string $label, string $value, string $default, string $description = '' ): void {
		printf(
			'<tr><th scope="row"><label for="b83-alert-%1$s">%2$s</label></th><td><input type="text" id="b83-alert-%1$s" class="b83-alert-color" name="b83_alert[%3$s]" value="%4$s" data-default-color="%5$s" data-key="%3$s">%6$s</td></tr>',
			esc_attr( str_replace( '_', '-', $key ) ),
			esc_html( $label ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $default ),
			'' !== $description ? '<p class="description">' . esc_html( $description ) . '</p>' : ''
		);
	}

	private function range_row( string $key, string $label, int $value, int $min, int $max, string $unit, int $step = 1 ): void {
		printf(
			'<tr><th scope="row"><label for="b83-alert-%1$s">%2$s</label></th><td class="b83-alert-range"><input type="range" id="b83-alert-%1$s" name="b83_alert[%3$s]" value="%4$d" min="%5$d" max="%6$d" step="%7$d" data-key="%3$s"> <output for="b83-alert-%1$s">%4$d</output> %8$s</td></tr>',
			esc_attr( str_replace( '_', '-', $key ) ),
			esc_html( $label ),
			esc_attr( $key ),
			(int) $value,
			(int) $min,
			(int) $max,
			(int) $step,
			esc_html( $unit )
		);
	}

	private function number_row( string $key, string $label, int $value, int $min, int $max, string $unit ): void {
		printf(
			'<tr><th scope="row"><label for="b83-alert-%1$s">%2$s</label></th><td><input type="number" class="small-text" id="b83-alert-%1$s" name="b83_alert[%3$s]" value="%4$d" min="%5$d" max="%6$d"> %7$s</td></tr>',
			esc_attr( str_replace( '_', '-', $key ) ),
			esc_html( $label ),
			esc_attr( $key ),
			(int) $value,
			(int) $min,
			(int) $max,
			esc_html( $unit )
		);
	}

	private function text_row( string $key, string $label, string $value, string $placeholder ): void {
		printf(
			'<tr><th scope="row"><label for="b83-alert-%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="b83-alert-%1$s" name="b83_alert[%3$s]" value="%4$s" placeholder="%5$s" maxlength="%6$d" data-key="%3$s"></td></tr>',
			esc_attr( str_replace( '_', '-', $key ) ),
			esc_html( $label ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			(int) Settings::MAX_LABEL
		);
	}

	private function checkbox_row( string $key, string $label, string $description, bool $checked ): void {
		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="b83_alert[%2$s]" value="1"%3$s> %4$s</label></td></tr>',
			esc_html( $label ),
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $description )
		);
	}
}
