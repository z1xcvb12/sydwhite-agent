<?php
/**
 * Helper functions.
 */

if ( ! function_exists( 'wp_ai_agent_get_settings' ) ) {
    function wp_ai_agent_get_settings() {
        $defaults = [
            'openai_key' => '',
            'alt_key'    => '',
            'base_url'   => 'https://api.openai.com/v1/chat/completions',
            'model'      => 'gpt-4o-mini',
            'quote_rules'=> '',
            'chat_expiry_minutes' => 20,
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
        $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : wp_salt( 'auth' ) );
        return hash_hmac( 'sha256', $ip, $salt );
    }
}

if ( ! function_exists( 'ai_agent_uuidv4' ) ) {
    function ai_agent_uuidv4(): string {
        $d = random_bytes( 16 );
        $d[6] = chr( ( ord( $d[6] ) & 0x0f ) | 0x40 );
        $d[8] = chr( ( ord( $d[8] ) & 0x3f ) | 0x80 );
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $d ), 4 ) );
    }
}

if ( ! function_exists( 'ai_agent_ensure_vid_cookie' ) ) {
    function ai_agent_ensure_vid_cookie(): string {
        $name = 'ai_agent_vid';
        $vid  = isset( $_COOKIE[ $name ] ) ? (string) $_COOKIE[ $name ] : '';
        if ( ! preg_match( '/^[a-f0-9-]{36}$/', $vid ) ) {
            $vid = ai_agent_uuidv4();
            if ( ! headers_sent() ) {
                $cookie = $name . '=' . rawurlencode( $vid ) . '; Max-Age=' . YEAR_IN_SECONDS . '; Path=/; SameSite=Lax';
                if ( is_ssl() ) {
                    $cookie .= '; Secure';
                }
                header( 'Set-Cookie: ' . $cookie, false );
            }
            $_COOKIE[ $name ] = $vid;
        }
        return $vid;
    }
}
add_action( 'init', 'ai_agent_ensure_vid_cookie', 0 );
