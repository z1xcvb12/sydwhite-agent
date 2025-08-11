<?php
defined( 'ABSPATH' ) || exit;

class AI_Agent_Render {
  public function enqueue_assets() {
    $ver = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : WP_AI_AGENT_VERSION;
    wp_enqueue_script( 'wp-ai-agent-chat', plugins_url( '../assets/js/chat.js', __FILE__ ), array(), $ver, true );
    $settings = get_option( 'ai_agent_settings', array() );
    $enter_to_send = isset( $settings['enter_to_send'] ) ? (bool) $settings['enter_to_send'] : true;
    $localized = array( 'enterToSend' => $enter_to_send );
    // Merge safely with any already-localized data
    if ( wp_scripts()->get_data( 'wp-ai-agent-chat', 'data' ) ) {
      wp_add_inline_script(
        'wp-ai-agent-chat',
        'window.WPAIAgent = Object.assign({}, window.WPAIAgent || {}, ' . wp_json_encode( $localized ) . ' );',
        'after'
      );
    } else {
      wp_localize_script( 'wp-ai-agent-chat', 'WPAIAgent', $localized );
    }
  }
}
