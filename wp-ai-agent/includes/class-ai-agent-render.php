<?php
/**
 * Frontend rendering.
 */

class Ai_Agent_Render {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'wp_footer', [ $this, 'container' ] );
    }

    public function assets() {
        $settings = wp_ai_agent_get_settings();

        wp_enqueue_style( 'wp-ai-agent', WP_AI_AGENT_URL . 'assets/css/chat.css', [], WP_AI_AGENT_VERSION );

        wp_register_script( 'wp-ai-agent-transport', WP_AI_AGENT_URL . 'assets/js/chat-transport.js', [], WP_AI_AGENT_VERSION, true );
        wp_register_script( 'wp-ai-agent-chat', WP_AI_AGENT_URL . 'assets/js/chat.js', [ 'wp-ai-agent-transport' ], WP_AI_AGENT_VERSION, true );
        wp_localize_script( 'wp-ai-agent-chat', 'WPAIAgent', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'wpai_agent' ),
            'expiryMinutes' => max( 1, (int) ( $settings['chat_expiry_minutes'] ?? 20 ) ),
            'i18n'          => [
                'finished' => __( 'This chat has finished due to inactivity. Click “Start new chat” to continue.', 'wp-ai-agent' ),
                'startNew' => __( 'Start new chat', 'wp-ai-agent' ),
            ],
        ] );
        wp_enqueue_script( 'wp-ai-agent-chat' );
    }

    public function admin_assets( $hook ) {
        if ( 'toplevel_page_wp-ai-agent' !== $hook ) {
            return;
        }

        $settings = wp_ai_agent_get_settings();

        wp_enqueue_style( 'wp-ai-agent', WP_AI_AGENT_URL . 'assets/css/chat.css', [], WP_AI_AGENT_VERSION );

        wp_register_script( 'wp-ai-agent-transport', WP_AI_AGENT_URL . 'assets/js/chat-transport.js', [], WP_AI_AGENT_VERSION, true );
        wp_register_script( 'wp-ai-agent-chat', WP_AI_AGENT_URL . 'assets/js/chat.js', [ 'wp-ai-agent-transport' ], WP_AI_AGENT_VERSION, true );
        wp_localize_script( 'wp-ai-agent-chat', 'WPAIAgent', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'wpai_agent' ),
            'expiryMinutes' => max( 1, (int) ( $settings['chat_expiry_minutes'] ?? 20 ) ),
            'i18n'          => [
                'finished' => __( 'This chat has finished due to inactivity. Click “Start new chat” to continue.', 'wp-ai-agent' ),
                'startNew' => __( 'Start new chat', 'wp-ai-agent' ),
            ],
        ] );
        wp_enqueue_script( 'wp-ai-agent-chat' );
    }

    public function container() {
        echo '<div id="wp-ai-agent-root" data-wpai-chat-root aria-live="polite"></div>';
    }
}
