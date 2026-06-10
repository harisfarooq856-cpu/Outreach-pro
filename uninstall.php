<?php
// Uninstall: remove all plugin data
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = [
    $wpdb->prefix . 'seo_outreach_leads',
    $wpdb->prefix . 'seo_outreach_logs',
    $wpdb->prefix . 'seo_outreach_schedules',
    $wpdb->prefix . 'seo_outreach_runs',
    $wpdb->prefix . 'seo_outreach_notifications',
];
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `$table`" );
}

delete_option( 'seo_outreach_settings' );
delete_option( 'seo_outreach_version' );

wp_clear_scheduled_hook( 'seo_outreach_run_scheduled' );
wp_clear_scheduled_hook( 'seo_outreach_daily_summary' );
