<?php
/**
 * AJAX proxy for chat.
 */

class Ai_Agent_AJAX {
    public function __construct() {
        add_action( 'wp_ajax_ai_agent_chat', [ $this, 'chat' ] );
        add_action( 'wp_ajax_nopriv_ai_agent_chat', [ $this, 'chat' ] );
    }

    protected function rate_limit( $visitor_id ) {
        $key = 'ai_agent_last_' . $visitor_id;
        $last = get_transient( $key );
        if ( $last ) {
            wp_send_json_error( [ 'error' => 'rate_limited' ], 429 );
        }
        set_transient( $key, time(), 2 );
    }

    public function chat() {
        nocache_headers();
        $input = json_decode( file_get_contents( 'php://input' ), true );
        $visitor = sanitize_text_field( $_POST['visitor'] ?? ( $input['visitor'] ?? '' ) );
        $message = sanitize_textarea_field( $_POST['message'] ?? ( $input['message'] ?? '' ) );
        $conversation = isset( $_POST['conversation'] ) ? json_decode( wp_unslash( $_POST['conversation'] ), true ) : ( $input['conversation'] ?? [] );
        $this->rate_limit( $visitor );

        $settings = wp_ai_agent_get_settings();
        $handbook = Ai_Agent_Handbooks::get_prompt();
        $system = $handbook;
        if ( ! empty( $settings['quote_rules'] ) ) {
            $system .= "\nQuote rules:\n" . $settings['quote_rules'];
        }
        $messages = [ [ 'role' => 'system', 'content' => $system ] ];
        foreach ( $conversation as $c ) {
            $messages[] = [ 'role' => $c['role'], 'content' => $c['content'] ];
        }
        $messages[] = [ 'role' => 'user', 'content' => $message ];

        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'X-Accel-Buffering: no' );

        $response = $this->stream_api( $settings, $messages );

        Ai_Agent_DB::save_chat( $visitor, array_merge( $conversation, [ [ 'role' => 'user', 'content' => $message, 'ts' => time() ], [ 'role' => 'assistant', 'content' => $response, 'ts' => time() ] ] ) );
        wp_die();
    }

    protected function stream_api( $settings, $messages ) {
        $alt = wp_ai_agent_decrypt( $settings['alt_key'] );
        $open = wp_ai_agent_decrypt( $settings['openai_key'] );
        $model = $settings['model'] ?: 'gpt-4o-mini';
        $url = $alt ? 'https://api.probex.top/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions';
        if ( ! empty( $settings['base_url'] ) && ! $alt ) {
            $url = $settings['base_url'];
        }
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . ( $alt ? $alt : $open ),
        ];
        $body = [
            'model'    => $alt ? $model : $model,
            'messages' => $messages,
            'stream'   => true,
        ];
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
        $buffer = '';
        curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $ch, $data ) use ( &$buffer ) {
            $buffer .= $data;
            echo $data;
            @ob_flush();
            flush();
            return strlen( $data );
        } );
        curl_exec( $ch );
        curl_close( $ch );
        $text = '';
        foreach ( explode( "\\n", $buffer ) as $line ) {
            if ( 0 === strpos( $line, 'data:' ) ) {
                $payload = trim( substr( $line, 5 ) );
                if ( '[DONE]' === $payload ) {
                    break;
                }
                $json = json_decode( $payload, true );
                if ( isset( $json['choices'][0]['delta']['content'] ) ) {
                    $text .= $json['choices'][0]['delta']['content'];
                }
            }
        }
        return $text;
    }
}
