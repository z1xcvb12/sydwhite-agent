<?php
/**
 * Admin interface.
 */

class Ai_Agent_Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function menu() {
        add_menu_page(
            __( 'AI Agent', 'wp-ai-agent' ),
            __( 'AI Agent', 'wp-ai-agent' ),
            'manage_options',
            'wp-ai-agent',
            [ $this, 'render_page' ],
            'dashicons-format-chat'
        );
    }

    public function register_settings() {
        register_setting( 'wp_ai_agent', 'wp_ai_agent_settings', [ $this, 'sanitize_settings' ] );
        register_setting( 'wp_ai_agent', 'wp_ai_agent_remove_uploads' );
    }

    public function sanitize_settings( $input ) {
        $out = [];
        $out['openai_key'] = wp_ai_agent_encrypt( sanitize_text_field( $input['openai_key'] ?? '' ) );
        $out['alt_key']    = wp_ai_agent_encrypt( sanitize_text_field( $input['alt_key'] ?? '' ) );
        $out['base_url']   = esc_url_raw( $input['base_url'] ?? '' );
        $out['model']      = sanitize_text_field( $input['model'] ?? '' );
        $out['quote_rules']= wp_kses_post( $input['quote_rules'] ?? '' );
        $out['retention']  = isset( $input['retention'] ) ? (int) $input['retention'] : 30;
        $out['chat_expiry_minutes'] = isset( $input['chat_expiry_minutes'] ) ? max( 1, (int) $input['chat_expiry_minutes'] ) : 20;
        $out['enter_send'] = ! empty( $input['enter_send'] ) ? 1 : 0;
        $out['debug']      = ! empty( $input['debug'] ) ? 1 : 0;
        $out['sound']      = ! empty( $input['sound'] ) ? 1 : 0;
        $out['remove']     = ! empty( $input['remove'] ) ? 1 : 0;
        return $out;
    }

    public function render_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'api';
        echo '<div class="wrap"><h1>' . esc_html__( 'AI Agent', 'wp-ai-agent' ) . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        $tabs = [
            'api'       => __( 'API Settings', 'wp-ai-agent' ),
            'handbooks' => __( 'AI Handbook', 'wp-ai-agent' ),
            'history'   => __( 'Chat History', 'wp-ai-agent' ),
            'test'      => __( 'Test Chat', 'wp-ai-agent' ),
        ];
        foreach ( $tabs as $id => $label ) {
            $class = ( $id === $tab ) ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr( $class ) . '" href="?page=wp-ai-agent&tab=' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</h2>';
        switch ( $tab ) {
            case 'handbooks':
                include WP_AI_AGENT_DIR . 'templates/admin-handbooks.php';
                break;
            case 'history':
                include WP_AI_AGENT_DIR . 'templates/admin-history.php';
                break;
            case 'test':
                include WP_AI_AGENT_DIR . 'templates/admin-test-chat.php';
                break;
            case 'api':
            default:
                include WP_AI_AGENT_DIR . 'templates/admin-settings.php';
                break;
        }
        echo '</div>';
    }
}
