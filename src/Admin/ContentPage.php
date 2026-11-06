<?php
/**
 * Seite „b83 → Alert“ mit zwei Reitern:
 *  - Mitteilung    (ab Redakteur): Texte, aktiv, Zeitraum
 *  - Einstellungen (nur Administratoren): Anzeigeort, Design, Tempo
 *
 * Ein einziger Menüpunkt, wie bei den anderen b83-Plugins.
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Admin;

use B83\Alert\Capabilities;

defined( 'ABSPATH' ) || exit;

final class ContentPage {

	public const SLUG = 'b83-alert';

	public const TAB_CONTENT  = 'content';
	public const TAB_SETTINGS = 'settings';

	public function __construct( private ContentForm $form, private SettingsPage $settings ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'b83_admin_plugin_links', array( $this, 'overview_link' ) );
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/** Ist der Admin-Hook unsere Seite? */
	public static function is_page( string $hook ): bool {
		return str_ends_with( $hook, '_page_' . self::SLUG );
	}

	/** Aktiver Reiter (Whitelist; Einstellungen nur mit Berechtigung). */
	public static function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Auswahl des Reiters.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		return ( self::TAB_SETTINGS === $tab && Capabilities::can_config() ) ? self::TAB_SETTINGS : self::TAB_CONTENT;
	}

	public function add_menu(): void {
		add_submenu_page(
			B83Menu::SLUG,
			__( 'Alert', 'b83-alert' ),
			__( 'Alert', 'b83-alert' ),
			Capabilities::edit(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue( string $hook ): void {
		if ( self::is_page( $hook ) && self::TAB_CONTENT === self::current_tab() ) {
			ContentForm::enqueue();
		}
	}

	/**
	 * @param array<string,string> $links Links.
	 * @return array<string,string>
	 */
	public function overview_link( array $links ): array {
		$links[ plugin_basename( B83_ALERT_FILE ) ] = self::url();
		return $links;
	}

	public function render(): void {
		if ( ! Capabilities::can_edit() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-alert' ), '', array( 'response' => 403 ) );
		}
		$tab = self::current_tab();

		echo '<div class="wrap b83-alert-page">';
		echo '<h1>' . esc_html__( 'b83 Alert', 'b83-alert' ) . '</h1>';

		if ( Capabilities::can_config() ) {
			echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr__( 'Bereiche', 'b83-alert' ) . '">';
			foreach ( array(
				self::TAB_CONTENT  => array( self::url(), __( 'Mitteilung', 'b83-alert' ) ),
				self::TAB_SETTINGS => array( SettingsPage::url(), __( 'Einstellungen', 'b83-alert' ) ),
			) as $key => $item ) {
				printf(
					'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
					esc_url( $item[0] ),
					$key === $tab ? ' nav-tab-active' : '',
					$key === $tab ? ' aria-current="page"' : '',
					esc_html( $item[1] )
				);
			}
			echo '</nav>';
		}

		if ( self::TAB_SETTINGS === $tab ) {
			$this->settings->render_tab();
		} else {
			echo '<p class="b83-alert-page__intro">' . esc_html__( 'Wichtige Mitteilungen für die Website-Besucher: als Pop-up und/oder als Laufschrift. Angezeigt wird nur, was befüllt und aktiv ist.', 'b83-alert' ) . '</p>';
			ContentForm::notice();
			$this->form->render( 'page', false, true );
		}
		echo '</div>';
	}
}
