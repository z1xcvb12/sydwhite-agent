<?php
/**
 * Plugin Name: AI Agent (Chat + Quotes)
 * Description: WhatsApp-style AI chat with quote generation.
 * Version: 1.0.0
 * Author: AI Agent Bot
 * Text Domain: wp-ai-agent
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants.
define( 'WP_AI_AGENT_VERSION', '1.0.0' );
define( 'WP_AI_AGENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_AI_AGENT_URL', plugin_dir_url( __FILE__ ) );

require_once WP_AI_AGENT_DIR . 'includes/helpers.php';
require_once WP_AI_AGENT_DIR . 'includes/class-ai-agent-encryption.php';
require_once WP_AI_AGENT_DIR . 'includes/class-ai-agent-db.php';
require_once WP_AI_AGENT_DIR . 'includes/class-ai-agent-admin.php';
require_once WP_AI_AGENT_DIR . 'includes/class-ai-agent-ajax.php';
require_once WP_AI_AGENT_DIR . 'includes/class-ai-agent-handbooks.php';
require_once WP_AI_AGENT_DIR . 'includes/class-ai-agent-elementor.php';
require_once WP_AI_AGENT_DIR . 'includes/class-ai-agent-render.php';

/**
 * Activation hook.
 */
function wp_ai_agent_activate() {
    Ai_Agent_DB::create_tables();
    Ai_Agent_DB::maybe_upgrade_schema();
    Ai_Agent_DB::schedule_cron();
}
register_activation_hook( __FILE__, 'wp_ai_agent_activate' );

/**
 * Deactivation hook.
 */
function wp_ai_agent_deactivate() {
    Ai_Agent_DB::clear_cron();
}
register_deactivation_hook( __FILE__, 'wp_ai_agent_deactivate' );

// Initialize plugin pieces.
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'wp-ai-agent', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    Ai_Agent_DB::maybe_upgrade_schema();
    new Ai_Agent_Admin();
    new Ai_Agent_AJAX();
    new Ai_Agent_Handbooks();
    new Ai_Agent_Elementor();
    new Ai_Agent_Render();
} );
