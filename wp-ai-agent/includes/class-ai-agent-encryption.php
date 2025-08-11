<?php
/**
 * Encryption helpers.
 */

class Ai_Agent_Encryption {
    protected static function get_key() {
        $key = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
        return hash( 'sha256', $key, true );
    }

    public static function encrypt( $plain ) {
        if ( empty( $plain ) ) {
            return '';
        }
        $iv = random_bytes( 16 );
        $cipher = openssl_encrypt( $plain, 'AES-256-CBC', self::get_key(), 0, $iv );
        return base64_encode( $iv . $cipher );
    }

    public static function decrypt( $encoded ) {
        if ( empty( $encoded ) ) {
            return '';
        }
        $data = base64_decode( $encoded );
        $iv = substr( $data, 0, 16 );
        $cipher = substr( $data, 16 );
        return openssl_decrypt( $cipher, 'AES-256-CBC', self::get_key(), 0, $iv );
    }
}
