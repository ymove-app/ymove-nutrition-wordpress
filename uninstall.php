<?php
/**
 * Uninstall: only removes data when the site owner opted in.
 *
 * @package YMove_Nutrition
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ymove_nutrition_settings = get_option( 'ymove_nutrition_settings', array() );
if ( empty( $ymove_nutrition_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'log', 'targets', 'food_cache', 'leads' ) as $ymove_nutrition_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'ymove_' . $ymove_nutrition_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
}
delete_option( 'ymove_nutrition_settings' );
delete_option( 'ymove_nutrition_api_key' );
delete_option( 'ymove_nutrition_db_version' );
delete_option( 'ymove_nutrition_counters' );
delete_metadata( 'user', 0, 'ymove_nutrition_dismissed', '', true );
delete_metadata( 'user', 0, 'ymove_photo_consent', '', true );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ymn_%' OR option_name LIKE '_transient_timeout_ymn_%' OR option_name LIKE '_transient_ymove_nutrition_%' OR option_name LIKE '_transient_timeout_ymove_nutrition_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
