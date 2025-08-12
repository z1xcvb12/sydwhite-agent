<?php
$settings = wp_ai_agent_get_settings();
?>
<form method="post" action="options.php">
<?php settings_fields( 'wp_ai_agent' ); ?>
<table class="form-table" role="presentation">
<tr>
<th scope="row"><label for="openai_key"><?php _e( 'OpenAI API Key', 'wp-ai-agent' ); ?></label></th>
<td><input type="password" id="openai_key" name="wp_ai_agent_settings[openai_key]" value="" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="alt_key"><?php _e( 'Alternate API Key (Probex)', 'wp-ai-agent' ); ?></label></th>
<td><input type="password" id="alt_key" name="wp_ai_agent_settings[alt_key]" value="" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="model"><?php _e( 'Model', 'wp-ai-agent' ); ?></label></th>
<td><select id="model" name="wp_ai_agent_settings[model]">
<option value="gpt-4o-mini" <?php selected( $settings['model'], 'gpt-4o-mini' ); ?>>gpt-4o-mini</option>
<option value="deepseek-r1" <?php selected( $settings['model'], 'deepseek-r1' ); ?>>deepseek-r1</option>
<option value="deepseek-v3" <?php selected( $settings['model'], 'deepseek-v3' ); ?>>deepseek-v3</option>
</select></td>
</tr>
<tr>
<th scope="row"><label for="quote_rules"><?php _e( 'Quote Rules (JSON)', 'wp-ai-agent' ); ?></label></th>
<td><textarea id="quote_rules" name="wp_ai_agent_settings[quote_rules]" rows="5" class="large-text"><?php echo esc_textarea( $settings['quote_rules'] ); ?></textarea></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Sound Notifications', 'wp-ai-agent' ); ?></th>
<td><label><input type="checkbox" name="wp_ai_agent_settings[sound]" value="1" <?php checked( ! empty( $settings['sound'] ) ); ?> /> <?php _e( 'Enable', 'wp-ai-agent' ); ?></label></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Enter to send', 'wp-ai-agent' ); ?></th>
<td><label><input type="checkbox" name="wp_ai_agent_settings[enter_send]" value="1" <?php checked( ! empty( $settings['enter_send'] ) ); ?> /> <?php _e( 'Enable', 'wp-ai-agent' ); ?></label></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Debug Mode', 'wp-ai-agent' ); ?></th>
<td><label><input type="checkbox" name="wp_ai_agent_settings[debug]" value="1" <?php checked( ! empty( $settings['debug'] ) ); ?> /> <?php _e( 'Enable', 'wp-ai-agent' ); ?></label></td>
</tr>
<tr>
<th scope="row"><label for="chat_expiry_minutes"><?php _e( 'Conversation inactivity timeout (minutes)', 'wp-ai-agent' ); ?></label></th>
<td>
<input type="number" min="1" id="chat_expiry_minutes" name="wp_ai_agent_settings[chat_expiry_minutes]" value="<?php echo esc_attr( max( 1, (int) ( $settings['chat_expiry_minutes'] ?? 20 ) ) ); ?>" />
<p class="description"><?php _e( 'If a visitor stops replying for this many minutes, the chat is marked finished. Returning users can start a new chat while the old transcript remains.', 'wp-ai-agent' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="retention"><?php _e( 'Retention days', 'wp-ai-agent' ); ?></label></th>
<td><input type="number" id="retention" name="wp_ai_agent_settings[retention]" value="<?php echo esc_attr( $settings['retention'] ?? 30 ); ?>" /></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Remove uploaded handbooks on uninstall', 'wp-ai-agent' ); ?></th>
<td><label><input type="checkbox" name="wp_ai_agent_remove_uploads" value="1" <?php checked( get_option( 'wp_ai_agent_remove_uploads' ), 1 ); ?> /> <?php _e( 'Enable', 'wp-ai-agent' ); ?></label></td>
</tr>
</table>
<?php submit_button(); ?>
</form>
