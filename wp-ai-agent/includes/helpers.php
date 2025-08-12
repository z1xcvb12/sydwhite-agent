<?php
/**
 * Helper functions.
 */

if ( ! function_exists( 'wp_ai_agent_get_settings' ) ) {
    function wp_ai_agent_get_settings() {
        $defaults = [
            'openai_key'            => '',
            'alt_key'               => '',
            'base_url'              => 'https://api.openai.com/v1/chat/completions',
            'model'                 => 'gpt-4o-mini',
            'quote_rules'           => '',
            'chat_expiry_minutes'   => 20,
        ];
        $opts = get_option( 'wp_ai_agent_settings', [] );
        return wp_parse_args( $opts, $defaults );
    }
}

if ( ! function_exists( 'wp_ai_agent_encrypt' ) ) {
    function wp_ai_agent_encrypt( $value ) {
        return Ai_Agent_Encryption::encrypt( $value );
    }
}

if ( ! function_exists( 'wp_ai_agent_decrypt' ) ) {
    function wp_ai_agent_decrypt( $value ) {
        return Ai_Agent_Encryption::decrypt( $value );
    }
}

if ( ! function_exists( 'ai_agent_get_client_ip' ) ) {
    function ai_agent_get_client_ip() {
        $keys = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ];
        foreach ( $keys as $k ) {
            if ( ! empty( $_SERVER[ $k ] ) ) {
                $iplist = explode( ',', $_SERVER[ $k ] );
                $ip     = trim( $iplist[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}

if ( ! function_exists( 'ai_agent_ip_hash' ) ) {
    function ai_agent_ip_hash( $ip = null ) {
        $ip   = $ip ? $ip : ai_agent_get_client_ip();
        $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : wp_salt( 'auth' ) );
        return hash_hmac( 'sha256', $ip, $salt );
    }
}
