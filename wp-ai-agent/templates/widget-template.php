<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Ai_Agent_Elementor_Widget extends Widget_Base {
    public function get_name() {
        return 'ai-agent-chat';
    }

    public function get_title() {
        return __( 'AI Agent Chat', 'wp-ai-agent' );
    }

    public function get_icon() {
        return 'eicon-chat';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        $this->start_controls_section( 'section_content', [
            'label' => __( 'Settings', 'wp-ai-agent' ),
        ] );
        $this->add_control( 'show_button', [
            'label' => __( 'Show Floating Button', 'wp-ai-agent' ),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );
        $this->add_control( 'position', [
            'label' => __( 'Position', 'wp-ai-agent' ),
            'type' => Controls_Manager::SELECT,
            'default' => 'right',
            'options' => [ 'right' => __( 'Bottom Right', 'wp-ai-agent' ), 'left' => __( 'Bottom Left', 'wp-ai-agent' ) ],
        ] );
        $this->add_control( 'label', [
            'label' => __( 'Label', 'wp-ai-agent' ),
            'type' => Controls_Manager::TEXT,
            'default' => __( 'Chat', 'wp-ai-agent' ),
        ] );
        $this->add_control( 'color', [
            'label' => __( 'Color', 'wp-ai-agent' ),
            'type' => Controls_Manager::COLOR,
            'default' => '#25D366',
        ] );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if ( 'yes' !== $settings['show_button'] ) {
            return;
        }
        echo '<div class="ai-agent-el" data-position="' . esc_attr( $settings['position'] ) . '" data-color="' . esc_attr( $settings['color'] ) . '">' . esc_html( $settings['label'] ) . '</div>';
    }
}
