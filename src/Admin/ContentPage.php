<?php
/**
 * Seite „b83 → Alert“: Mitteilungen bearbeiten (ab Redakteur).
 *
 * @package B83\Alert
 */

declare(strict_types=1);

namespace B83\Alert\Admin;

use B83\Alert\Capabilities;

defined( 'ABSPATH' ) || exit;

final class ContentPage {

	public const SLUG = 'b83-alert';

	private string $hook = '';

	public function __construct( private ContentForm $form ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'b83_admin_plugin_links', array( $this, 'overview_link' ) );
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public function add_menu(): void {
		$this->hook = (string) add_submenu_page(
			B83Menu::SLUG,
			__( 'Alert – Mitteilung', 'b83-alert' ),
			__( 'Alert', 'b83-alert' ),
			Capabilities::edit(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue( string $hook ): void {
		if ( $hook === $this->hook ) {
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
		echo '<div class="wrap b83-alert-page">';
		echo '<h1>' . esc_html__( 'Alert – Mitteilung', 'b83-alert' ) . '</h1>';
		echo '<p class="b83-alert-page__intro">' . esc_html__( 'Wichtige Mitteilungen für die Website-Besucher: als Pop-up und/oder als Laufschrift. Angezeigt wird nur, was befüllt und aktiv ist.', 'b83-alert' ) . '</p>';
		ContentForm::notice();
		$this->form->render( 'page', false );
		if ( Capabilities::can_config() ) {
			echo '<p><a href="' . esc_url( SettingsPage::url() ) . '">' . esc_html__( 'Anzeige und Design einstellen →', 'b83-alert' ) . '</a></p>';
		}
		echo '</div>';
	}
}
