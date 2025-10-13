<?php
// Agents tab
$settings = wp_ai_agent_get_settings();
?>
<form method="post" action="options.php">
<?php settings_fields( 'wp_ai_agent' ); ?>
<table class="form-table" role="presentation">

<tr>
  <th scope="row">
    <label for="agent_profiles"><?php _e( 'Agent Profiles', 'wp-ai-agent' ); ?></label>
  </th>
  <td>
    <textarea id="agent_profiles"
              name="wp_ai_agent_settings[agent_profiles]"
              rows="10"
              class="large-text code"
              placeholder="Name | https://your-site.com/uploads/background.jpg (one per line)"><?php
        echo esc_textarea( $settings['agent_profiles'] ?? '' );
    ?></textarea>
    <p class="description">
      <?php _e( 'One per line. Format: Name | Background image URL. The URL is optional.', 'wp-ai-agent' ); ?><br>
      <?php _e( 'Example:', 'wp-ai-agent' ); ?>
      <code>Alice Chen | https://your-site.com/uploads/bg-alice.jpg</code>
    </p>
  </td>
</tr>

</table>
<?php submit_button( __( 'Save Changes', 'wp-ai-agent' ) ); ?>
</form>