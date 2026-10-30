<?php
/**
 * Deinstallation. Daten werden nur entfernt, wenn das in den Einstellungen
 * ausdrücklich aktiviert wurde.
 *
 * @package B83\Alert
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_unschedule_hook( 'b83_alert_switch' );
delete_transient( 'b83_alert_release' );

$b83_alert_settings = get_option( 'b83_alert_settings', array() );

if ( is_array( $b83_alert_settings ) && ! empty( $b83_alert_settings['delete_on_uninstall'] ) ) {
	delete_option( 'b83_alert_settings' );
	delete_option( 'b83_alert_db_version' );
}
