<?php
$dir = wp_get_upload_dir();
$folder = trailingslashit( $dir['basedir'] ) . 'ai-agent-handbooks/';
$files = is_dir( $folder ) ? glob( $folder . '*', GLOB_NOSORT ) : [];
?>
<h2><?php _e( 'Upload Handbook', 'wp-ai-agent' ); ?></h2>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
<?php wp_nonce_field( 'ai_agent_upload' ); ?>
<input type="hidden" name="action" value="ai_agent_upload" />
<input type="file" name="handbook" />
<?php submit_button( __( 'Upload', 'wp-ai-agent' ) ); ?>
</form>
<h2><?php _e( 'Existing Handbooks', 'wp-ai-agent' ); ?></h2>
<ul>
<?php foreach ( $files as $file ) : $name = basename( $file ); ?>
<li><?php echo esc_html( $name ); ?> <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=ai_agent_delete_handbook&file=' . urlencode( $name ) ), 'ai_agent_delete' ); ?>"><?php _e( 'Remove', 'wp-ai-agent' ); ?></a></li>
<?php endforeach; ?>
</ul>
