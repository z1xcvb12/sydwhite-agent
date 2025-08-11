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

    private function config() {
        $settings = wp_ai_agent_get_settings();

        return [
            'ajax'       => admin_url( 'admin-ajax.php' ),
            'assets'     => WP_AI_AGENT_URL . 'assets',
            'enterSend'  => ! empty( $settings['enter_send'] ),
            'sound'      => ! empty( $settings['sound'] ),
            'agentNames' => [
                'Jack Wilson',
                'Olivia Nguyen',
                'Liam O\'Connor',
                'Chloe Smith',
                'Noah Patel',
            ],
            'debug'      => ! empty( $settings['debug'] ),
            'selectors'  => [
                'chatRoot'    => '[data-wpai-chat-root]',
                'messageList' => '[data-wpai-message-list]',
            ],
        ];
    }

    public function assets() {
        wp_enqueue_style( 'wp-ai-agent', WP_AI_AGENT_URL . 'assets/css/chat.css', [], WP_AI_AGENT_VERSION );

        wp_register_script( 'wp-ai-agent-transport', WP_AI_AGENT_URL . 'assets/js/chat-transport.js', [], WP_AI_AGENT_VERSION, true );
        wp_register_script( 'wp-ai-agent-frontend', WP_AI_AGENT_URL . 'assets/js/chat-frontend.js', [ 'wp-ai-agent-transport' ], WP_AI_AGENT_VERSION, true );
        wp_localize_script( 'wp-ai-agent-frontend', 'WPAI_CONFIG', $this->config() );
        wp_enqueue_script( 'wp-ai-agent-frontend' );
    }

    public function admin_assets( $hook ) {
        if ( 'toplevel_page_wp-ai-agent' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-ai-agent', WP_AI_AGENT_URL . 'assets/css/chat.css', [], WP_AI_AGENT_VERSION );

        wp_register_script( 'wp-ai-agent-transport', WP_AI_AGENT_URL . 'assets/js/chat-transport.js', [], WP_AI_AGENT_VERSION, true );
        wp_register_script( 'wp-ai-agent-admin', WP_AI_AGENT_URL . 'assets/js/chat-admin.js', [ 'wp-ai-agent-transport' ], WP_AI_AGENT_VERSION, true );
        wp_localize_script( 'wp-ai-agent-admin', 'WPAI_CONFIG', $this->config() );
        wp_enqueue_script( 'wp-ai-agent-admin' );
    }

    public function container() {
        echo '<div id="wp-ai-agent-root" data-wpai-chat-root aria-live="polite"></div>';
    }
}
