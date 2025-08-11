<?php
/**
 * Uninstall script for AI Agent plugin.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-ai-agent-db.php';
require_once __DIR__ . '/includes/helpers.php';

$remove = (int) get_option( 'wp_ai_agent_remove_uploads', 0 );
if ( $remove ) {
    $dir = wp_get_upload_dir();
    $folder = trailingslashit( $dir['basedir'] ) . 'ai-agent-handbooks/';
    if ( is_dir( $folder ) ) {
        foreach ( glob( $folder . '*', GLOB_NOSORT ) as $file ) {
            @unlink( $file );
        }
        @rmdir( $folder );
    }
}

Ai_Agent_DB::drop_tables();
delete_option( 'wp_ai_agent_settings' );
delete_option( 'wp_ai_agent_remove_uploads' );
Ai_Agent_DB::clear_cron();
