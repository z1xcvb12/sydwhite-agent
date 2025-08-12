<?php
/**
 * Database utilities.
 */

class Ai_Agent_DB {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ai_agent_chats';
    }

    public static function maybe_upgrade_schema() {
        global $wpdb;
        $table = self::table_name();
        $cols  = $wpdb->get_col( "SHOW COLUMNS FROM $table", 0 );
        if ( ! in_array( 'ip_hash', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN ip_hash VARCHAR(128) NOT NULL DEFAULT '' AFTER visitor_id" );
        }
        if ( ! in_array( 'last_activity', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN last_activity DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER timestamp" );
        }
        if ( ! in_array( 'ended', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN ended TINYINT(1) NOT NULL DEFAULT 0" );
        }
    }

    public static function create_tables() {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE $table (\n            id INT NOT NULL AUTO_INCREMENT,\n            visitor_id VARCHAR(64) NOT NULL,\n            ip_hash VARCHAR(128) NOT NULL DEFAULT '',\n            timestamp DATETIME NOT NULL,\n            last_activity DATETIME NOT NULL,\n            conversation LONGTEXT NOT NULL,\n            ended TINYINT(1) NOT NULL DEFAULT 0,\n            PRIMARY KEY  (id),\n            KEY visitor_id (visitor_id),\n            KEY ip_hash (ip_hash)\n        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function get_active_by_vid_or_ip( $visitor_id, $ip_hash, $expiry ) {
        global $wpdb;
        $table = self::table_name();
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE visitor_id=%s AND ended=0 ORDER BY id DESC LIMIT 1", $visitor_id ) );
        $now   = current_time( 'timestamp', true );
        $limit = (int) $expiry * MINUTE_IN_SECONDS;
        if ( $row ) {
            $expired = $row->last_activity && ( $now - strtotime( $row->last_activity ) > $limit );
            return [ 'row' => $row, 'expired' => $expired ];
        }
        if ( $ip_hash ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE ip_hash=%s AND ended=0 ORDER BY id DESC LIMIT 1", $ip_hash ) );
            if ( $row ) {
                $expired = $row->last_activity && ( $now - strtotime( $row->last_activity ) > $limit );
                return [ 'row' => $row, 'expired' => $expired ];
            }
        }
        return null;
    }

    public static function create_session( $visitor_id, $ip_hash ) {
        global $wpdb;
        $wpdb->insert( self::table_name(), [
            'visitor_id'   => $visitor_id,
            'ip_hash'      => $ip_hash,
            'timestamp'    => current_time( 'mysql', true ),
            'last_activity'=> current_time( 'mysql', true ),
            'conversation' => wp_json_encode( [] ),
            'ended'        => 0,
        ], [ '%s', '%s', '%s', '%s', '%s', '%d' ] );
        return $wpdb->insert_id;
    }

    public static function update_session( $id, $conversation, $ended = 0, $visitor_id = null ) {
        global $wpdb;
        $data = [
            'conversation'  => wp_json_encode( $conversation ),
            'last_activity' => current_time( 'mysql', true ),
            'ended'         => $ended ? 1 : 0,
        ];
        $format = [ '%s', '%s', '%d' ];
        if ( null !== $visitor_id ) {
            $data['visitor_id'] = $visitor_id;
            $format[]           = '%s';
        }
        $wpdb->update( self::table_name(), $data, [ 'id' => (int) $id ], $format, [ '%d' ] );
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
