<?php
/**
 * Helper functions.
 */

if ( ! function_exists( 'wp_ai_agent_get_settings' ) ) {
    function wp_ai_agent_get_settings() {
        $defaults = [
            'openai_key' => '',
            'alt_key'    => '',
            'base_url'   => 'https://api.deepseek.com/chat/completions',
            'model'      => 'deepseek-chat', // or 'deepseek-reasoner'
            'quote_rules'=> '',
            'chat_expiry_minutes' => 20,
            'agent_profiles' => "Olivia Nguyen |\nJack Wilson |\nLiam O'Connor |\nChloe Smith |\nNoah Patel |",
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

if ( ! function_exists( 'ai_agent_uuidv4' ) ) {
    function ai_agent_uuidv4(): string {
        $data = random_bytes( 16 );
        $data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
        $data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
    }
}

if ( ! function_exists( 'ai_agent_ensure_vid_cookie' ) ) {
    function ai_agent_ensure_vid_cookie(): string {
        $vid = isset( $_COOKIE['ai_agent_vid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ai_agent_vid'] ) ) : '';
        if ( empty( $vid ) ) {
            $vid = ai_agent_uuidv4();
            setcookie(
                'ai_agent_vid',
                $vid,
                [
                    'expires'  => time() + YEAR_IN_SECONDS,
                    'path'     => COOKIEPATH,
                    'domain'   => COOKIE_DOMAIN,
                    'secure'   => is_ssl(),
                    'samesite' => 'Lax',
                ]
            );
            $_COOKIE['ai_agent_vid'] = $vid;
        }
        return $vid;
    }
}

if ( ! function_exists( 'ai_agent_get_client_ip' ) ) {
    function ai_agent_get_client_ip(): string {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        foreach ( $keys as $k ) {
            if ( ! empty( $_SERVER[ $k ] ) ) {
                $iplist = explode( ',', (string) $_SERVER[ $k ] );
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
    function ai_agent_ip_hash( ?string $ip = null ): string {
        $ip   = $ip ?: ai_agent_get_client_ip();
        $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : wp_salt( 'auth' );
        return hash_hmac( 'sha256', $ip, $salt );
    }
}

add_action( 'init', 'ai_agent_ensure_vid_cookie', 0 );

// 2) PARSE A MULTI-LINE TEXT INTO [ ['name'=>'...','bg'=>'...'], ... ]
if ( ! function_exists( 'wp_ai_agent_parse_agent_profiles' ) ) {
    function wp_ai_agent_parse_agent_profiles( $raw ) {
        $out = [];
        foreach ( preg_split( "/\r\n|\n|\r/", (string) $raw ) as $line ) {
            $line = trim( $line );
            if ( $line === '' ) continue;
            $parts = array_map( 'trim', explode( '|', $line, 2 ) );
            $name = sanitize_text_field( $parts[0] ?? '' );
            $bg   = isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '';
            if ( $name !== '' ) {
                $out[] = [ 'name' => $name, 'bg' => $bg ];
            }
        }
        if ( empty( $out ) ) {
            $out[] = [ 'name' => __( 'Agent', 'wp-ai-agent' ), 'bg' => '' ];
        }
        return $out;
    }
}

if ( ! function_exists( 'wp_ai_agent_get_agent_profiles' ) ) {
    function wp_ai_agent_get_agent_profiles() {
        $s = wp_ai_agent_get_settings();
        return wp_ai_agent_parse_agent_profiles( $s['agent_profiles'] ?? '' );
    }
}
