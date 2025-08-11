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
        wp_register_style( 'wp-ai-agent', WP_AI_AGENT_URL . 'assets/css/chat.css', [], WP_AI_AGENT_VERSION );
        wp_register_script( 'wp-ai-agent', WP_AI_AGENT_URL . 'assets/js/chat.js', [ 'jquery' ], WP_AI_AGENT_VERSION, true );
        $settings = wp_ai_agent_get_settings();
        wp_localize_script( 'wp-ai-agent', 'wpAiAgent', [
            'ajax'      => admin_url( 'admin-ajax.php' ),
            'assets'    => WP_AI_AGENT_URL . 'assets',
            'enterSend' => ! empty( $settings['enter_send'] ),
            'sound'     => ! empty( $settings['sound'] ),
            'debug'     => ! empty( $settings['debug'] ),
        ] );
        wp_localize_script(
            'wp-ai-agent',
            'wpaiAgentNames',
            [ 'Jack Wilson', 'Olivia Nguyen', "Liam O'Connor", 'Chloe Smith', 'Noah Patel' ]
        );
        wp_enqueue_style( 'wp-ai-agent' );
        wp_enqueue_script( 'wp-ai-agent' );
    }

    public function admin_assets( $hook ) {
        if ( 'toplevel_page_wp-ai-agent' !== $hook ) {
            return;
        }
        $this->assets();
    }

    public function container() {
        echo '<div id="wp-ai-agent-root" aria-live="polite"></div>';
    }
}
