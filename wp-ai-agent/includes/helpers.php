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
