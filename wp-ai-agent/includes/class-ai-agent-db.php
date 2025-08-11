<?php
/**
 * Database utilities.
 */

class Ai_Agent_DB {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ai_agent_chats';
    }

    public static function create_tables() {
        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (\n            id INT NOT NULL AUTO_INCREMENT,\n            visitor_id VARCHAR(64) NOT NULL,\n            timestamp DATETIME NOT NULL,\n            conversation LONGTEXT NOT NULL,\n            PRIMARY KEY  (id),\n            KEY visitor_id (visitor_id)\n        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function drop_tables() {
        global $wpdb;
        $table = self::table_name();
        $wpdb->query( "DROP TABLE IF EXISTS $table" );
    }

    public static function save_chat( $visitor_id, $conversation ) {
        global $wpdb;
        $wpdb->insert( self::table_name(), [
            'visitor_id'  => $visitor_id,
            'timestamp'   => current_time( 'mysql' ),
            'conversation'=> wp_json_encode( $conversation ),
        ] );
    }

    public static function get_chats() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM " . self::table_name() . " ORDER BY timestamp DESC" );
    }

    public static function delete_chat( $id ) {
        global $wpdb;
        $wpdb->delete( self::table_name(), [ 'id' => (int) $id ] );
    }

    public static function schedule_cron() {
        if ( ! wp_next_scheduled( 'wp_ai_agent_retention_event' ) ) {
            wp_schedule_event( time(), 'daily', 'wp_ai_agent_retention_event' );
        }
        add_action( 'wp_ai_agent_retention_event', [ __CLASS__, 'purge_old_chats' ] );
    }

    public static function clear_cron() {
        $ts = wp_next_scheduled( 'wp_ai_agent_retention_event' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'wp_ai_agent_retention_event' );
        }
    }

    public static function purge_old_chats() {
        $settings = wp_ai_agent_get_settings();
        $days = isset( $settings['retention'] ) ? (int) $settings['retention'] : 30;
        if ( $days <= 0 ) {
            return;
        }
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days );
        $wpdb->query( $wpdb->prepare( "DELETE FROM " . self::table_name() . " WHERE timestamp < %s", $cutoff ) );
    }
}
