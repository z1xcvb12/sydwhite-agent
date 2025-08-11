<?php
/**
 * Elementor integration.
 */

class Ai_Agent_Elementor {
    public function __construct() {
        add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widget' ] );
    }

    public function register_widget() {
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }
        require_once WP_AI_AGENT_DIR . 'templates/widget-template.php';
        \Elementor\Plugin::instance()->widgets_manager->register( new Ai_Agent_Elementor_Widget() );
    }
}
