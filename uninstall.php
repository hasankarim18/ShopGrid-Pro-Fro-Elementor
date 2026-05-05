<?php
/**
 * Uninstall script — runs when plugin is deleted from WP Admin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop wishlist table
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pw_wishlist" );

// Remove any stored options
delete_option( 'pw_version' );

// Clear any transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pw_query_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_pw_query_%'" );
