<?php
if ( isset( $_GET['export'] ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        exit;
    }
    header( 'Content-Type: text/plain' );
    $out = '';
    foreach ( Ai_Agent_DB::get_chats() as $chat ) {
        $out .= '[' . $chat->timestamp . ' ' . $chat->visitor_id . "]\n";
        $conv = json_decode( $chat->conversation, true );
        foreach ( $conv as $c ) {
            $out .= strtoupper( $c['role'] ) . ': ' . $c['content'] . "\n";
        }
        $out .= "\n";
    }
    echo $out;
    exit;
}
if ( isset( $_GET['delete'] ) ) {
    Ai_Agent_DB::delete_chat( (int) $_GET['delete'] );
    wp_safe_redirect( remove_query_arg( [ 'delete' ] ) );
    exit;
}
$chats = Ai_Agent_DB::get_chats();
?>
<p><a href="<?php echo esc_url( add_query_arg( 'export', 1 ) ); ?>" class="button"><?php _e( 'Export all as .txt', 'wp-ai-agent' ); ?></a></p>
<table class="widefat">
<thead><tr><th><?php _e( 'Date/Time', 'wp-ai-agent' ); ?></th><th><?php _e( 'Visitor ID', 'wp-ai-agent' ); ?></th><th><?php _e( 'Preview', 'wp-ai-agent' ); ?></th><th><?php _e( 'Action', 'wp-ai-agent' ); ?></th></tr></thead>
<tbody>
<?php foreach ( $chats as $chat ) : $conv = json_decode( $chat->conversation, true ); $preview = isset( $conv[0]['content'] ) ? $conv[0]['content'] : ''; ?>
<tr>
<td><?php echo esc_html( $chat->timestamp ); ?></td>
<td><?php echo esc_html( $chat->visitor_id ); ?></td>
<td><?php echo esc_html( wp_trim_words( $preview, 12 ) ); ?></td>
<td><a href="<?php echo esc_url( add_query_arg( 'delete', $chat->id ) ); ?>" class="button"><?php _e( 'Delete', 'wp-ai-agent' ); ?></a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
