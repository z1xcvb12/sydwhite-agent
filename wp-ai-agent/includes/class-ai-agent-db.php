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
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE $table (\n            id INT NOT NULL AUTO_INCREMENT,\n            visitor_id VARCHAR(64) NOT NULL,\n            ip_hash VARCHAR(128) NOT NULL DEFAULT '',\n            timestamp DATETIME NOT NULL,\n            last_activity DATETIME NOT NULL,\n            ended TINYINT(1) NOT NULL DEFAULT 0,\n            conversation LONGTEXT NOT NULL,\n            PRIMARY KEY  (id),\n            KEY visitor_id (visitor_id),\n            KEY ip_hash (ip_hash)\n        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function drop_tables() {
        global $wpdb;
        $table = self::table_name();
        $wpdb->query( "DROP TABLE IF EXISTS $table" );
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

    public static function save_chat( $visitor_id, $ip_hash, $conversation, $chat_id = null, $ended = 0 ) {
        global $wpdb;
        $table = self::table_name();
        $now   = current_time( 'mysql', true );
        $data  = [
            'visitor_id'    => $visitor_id,
            'ip_hash'       => $ip_hash,
            'conversation'  => wp_json_encode( $conversation ),
            'last_activity' => $now,
            'ended'         => $ended ? 1 : 0,
        ];
        if ( $chat_id ) {
            $wpdb->update( $table, $data, [ 'id' => (int) $chat_id ], [ '%s','%s','%s','%s','%d' ], [ '%d' ] );
            return $chat_id;
        }
        $data['timestamp'] = $now;
        $wpdb->insert( $table, $data, [ '%s','%s','%s','%s','%d','%s' ] );
        return (int) $wpdb->insert_id;
    }

    public static function get_active_by_vid_or_ip( $visitor_id, $ip_hash, $expiry_minutes ) {
        global $wpdb;
        $table = self::table_name();
        $now   = current_time( 'timestamp', true );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE visitor_id = %s AND ended = 0 ORDER BY id DESC LIMIT 1", $visitor_id ) );
        if ( ! $row ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE ip_hash = %s AND ended = 0 ORDER BY id DESC LIMIT 1", $ip_hash ) );
            if ( ! $row ) {
                return null;
            }
        }
        $expired = false;
        if ( $row->last_activity && ( $now - strtotime( $row->last_activity ) ) > (int) $expiry_minutes * 60 ) {
            $expired = true;
        }
        return (object) [ 'row' => $row, 'expired' => $expired ];
    }

    public static function touch_activity( $id ) {
        global $wpdb;
        $wpdb->update( self::table_name(), [ 'last_activity' => current_time( 'mysql', true ) ], [ 'id' => (int) $id ], [ '%s' ], [ '%d' ] );
    }

    public static function end_chat( $id, $conversation = null ) {
        global $wpdb;
        $table  = self::table_name();
        $data   = [
            'ended'         => 1,
            'last_activity' => current_time( 'mysql', true ),
        ];
        $format = [ '%d', '%s' ];
        if ( null !== $conversation ) {
            $data['conversation'] = wp_json_encode( $conversation );
            $format[] = '%s';
        }
        $wpdb->update( $table, $data, [ 'id' => (int) $id ], $format, [ '%d' ] );
    }

    public static function mark_finished_once( $id, $message ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT ended, conversation FROM ' . self::table_name() . ' WHERE id = %d', $id ) );
        if ( ! $row ) {
            return [];
        }
        if ( (int) $row->ended ) {
            return json_decode( (string) $row->conversation, true ) ?: [];
        }
        $conv = json_decode( (string) $row->conversation, true ) ?: [];
        $conv[] = [
            'role'    => 'assistant',
            'content' => $message,
            'ts'      => time(),
            'system'  => true,
        ];
        $wpdb->update(
            self::table_name(),
            [
                'conversation'  => wp_json_encode( $conv ),
                'ended'         => 1,
                'last_activity' => current_time( 'mysql', true ),
            ],
            [ 'id' => (int) $id ],
            [ '%s', '%d', '%s' ],
            [ '%d' ]
        );
        return $conv;
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
