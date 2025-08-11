<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wpai-chatbox">
  <div class="wpai-composer">
    <textarea rows="1" placeholder="<?php echo esc_attr__( 'Type a message', 'wp-ai-agent' ); ?>" enterkeyhint="send"></textarea>
    <button type="button" class="wpai-btn-send" aria-label="<?php echo esc_attr__( 'Send', 'wp-ai-agent' ); ?>">➤</button>
  </div>
</div>
