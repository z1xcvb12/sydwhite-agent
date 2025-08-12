<?php
/**
 * AJAX proxy for chat.
 */

class Ai_Agent_AJAX {
    public function __construct() {
        add_action( 'wp_ajax_ai_agent_chat', [ $this, 'chat' ] );
        add_action( 'wp_ajax_nopriv_ai_agent_chat', [ $this, 'chat' ] );
        add_action( 'wp_ajax_ai_agent_get_session', [ $this, 'get_session' ] );
        add_action( 'wp_ajax_nopriv_ai_agent_get_session', [ $this, 'get_session' ] );
        add_action( 'wp_ajax_ai_agent_end_session', [ $this, 'end_session' ] );
        add_action( 'wp_ajax_nopriv_ai_agent_end_session', [ $this, 'end_session' ] );
    }

    protected function rate_limit( $visitor_id ) {
        $key = 'ai_agent_last_' . $visitor_id;
        $last = get_transient( $key );
        if ( $last ) {
            wp_send_json_error( [ 'error' => 'rate_limited' ], 429 );
        }
        set_transient( $key, time(), 2 );
    }

    public function get_session() {
        check_ajax_referer( 'wpai_agent', 'nonce' );
        $settings = wp_ai_agent_get_settings();
        $expiry   = isset( $settings['chat_expiry_minutes'] ) ? (int) $settings['chat_expiry_minutes'] : 20;
        $visitor  = isset( $_COOKIE['ai_agent_vid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ai_agent_vid'] ) ) : '';
        $ip_hash  = ai_agent_ip_hash();
        $found    = Ai_Agent_DB::get_active_by_vid_or_ip( $visitor, $ip_hash, $expiry );
        if ( ! $found ) {
            wp_send_json_success( [ 'status' => 'none' ] );
        }
        $row  = $found['row'];
        $conv = json_decode( $row->conversation, true ) ?: [];
        if ( $found['expired'] && ! $row->ended ) {
            $finish = [
                'role'    => 'assistant',
                'content' => __( 'This chat has finished due to inactivity. Click “Start new chat” to continue.', 'wp-ai-agent' ),
                'ts'      => time(),
                'system'  => true,
            ];
            $conv[] = $finish;
            Ai_Agent_DB::update_session( $row->id, $conv, 1, $visitor ?: $row->visitor_id );
            wp_send_json_success( [ 'status' => 'expired', 'conversation' => $conv ] );
        }
        if ( empty( $row->visitor_id ) && $visitor ) {
            Ai_Agent_DB::update_session( $row->id, $conv, 0, $visitor );
        }
        wp_send_json_success( [ 'status' => 'active', 'conversation' => $conv ] );
    }

    public function end_session() {
        check_ajax_referer( 'wpai_agent', 'nonce' );
        $visitor = isset( $_COOKIE['ai_agent_vid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ai_agent_vid'] ) ) : '';
        $ip_hash = ai_agent_ip_hash();
        $settings = wp_ai_agent_get_settings();
        $expiry   = isset( $settings['chat_expiry_minutes'] ) ? (int) $settings['chat_expiry_minutes'] : 20;
        $found    = Ai_Agent_DB::get_active_by_vid_or_ip( $visitor, $ip_hash, $expiry );
        if ( $found && ! $found['row']->ended ) {
            $conv = json_decode( $found['row']->conversation, true ) ?: [];
            $conv[] = [
                'role'    => 'assistant',
                'content' => __( 'This chat has finished due to inactivity. Click “Start new chat” to continue.', 'wp-ai-agent' ),
                'ts'      => time(),
                'system'  => true,
            ];
            Ai_Agent_DB::update_session( $found['row']->id, $conv, 1, $visitor ?: $found['row']->visitor_id );
        }
        wp_send_json_success();
    }

    public function chat() {
        nocache_headers();
        $input  = file_get_contents( 'php://input' );
        $json   = json_decode( $input, true );
        $visitor = sanitize_text_field( $json['visitor'] ?? '' );
        $message = sanitize_textarea_field( $json['message'] ?? '' );
        $force   = ! empty( $json['new_session'] );
        $nonce   = sanitize_text_field( $json['nonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'wpai_agent' ) ) {
            wp_send_json_error( [ 'error' => 'invalid_nonce' ], 403 );
        }
        $this->rate_limit( $visitor );

        $settings = wp_ai_agent_get_settings();
        $expiry   = isset( $settings['chat_expiry_minutes'] ) ? (int) $settings['chat_expiry_minutes'] : 20;
        $handbook = Ai_Agent_Handbooks::get_prompt();
        $system   = $handbook;
        if ( ! empty( $settings['quote_rules'] ) ) {
            $system .= "\nQuote rules:\n" . $settings['quote_rules'];
        }
        $ip_hash = ai_agent_ip_hash();
        $found   = Ai_Agent_DB::get_active_by_vid_or_ip( $visitor, $ip_hash, $expiry );
        if ( $found && $found['expired'] ) {
            $old = json_decode( $found['row']->conversation, true ) ?: [];
            if ( ! $found['row']->ended ) {
                $old[] = [ 'role' => 'assistant', 'content' => __( 'This chat has finished due to inactivity. Click “Start new chat” to continue.', 'wp-ai-agent' ), 'ts' => time(), 'system' => true ];
                Ai_Agent_DB::update_session( $found['row']->id, $old, 1, $visitor ?: $found['row']->visitor_id );
            }
            $found = null;
        }
        if ( $force || ! $found ) {
            $session_id  = Ai_Agent_DB::create_session( $visitor, $ip_hash );
            $conversation = [];
        } else {
            $session_id  = $found['row']->id;
            $conversation = json_decode( $found['row']->conversation, true ) ?: [];
            if ( empty( $found['row']->visitor_id ) && $visitor ) {
                Ai_Agent_DB::update_session( $session_id, $conversation, 0, $visitor );
            }
        }

        $conversation[] = [ 'role' => 'user', 'content' => $message, 'ts' => time() ];
        Ai_Agent_DB::update_session( $session_id, $conversation, 0, $visitor );

        $messages = [ [ 'role' => 'system', 'content' => $system ] ];
        foreach ( $conversation as $c ) {
            $messages[] = [ 'role' => $c['role'], 'content' => $c['content'] ];
        }

        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'X-Accel-Buffering: no' );

        $response = $this->stream_api( $settings, $messages );

        $conversation[] = [ 'role' => 'assistant', 'content' => $response, 'ts' => time() ];
        Ai_Agent_DB::update_session( $session_id, $conversation, 0, $visitor );
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
