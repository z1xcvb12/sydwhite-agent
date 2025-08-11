<?php
/**
 * Handbook management.
 */

class Ai_Agent_Handbooks {
    protected static $cache = null;

    public function __construct() {
        add_action( 'admin_post_ai_agent_upload', [ $this, 'handle_upload' ] );
        add_action( 'admin_post_ai_agent_delete_handbook', [ $this, 'handle_delete' ] );
    }

    public function handle_upload() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        check_admin_referer( 'ai_agent_upload' );
        if ( empty( $_FILES['handbook']['name'] ) ) {
            wp_safe_redirect( wp_get_referer() );
            exit;
        }
        $file = $_FILES['handbook'];
        $dir = wp_get_upload_dir();
        $folder = trailingslashit( $dir['basedir'] ) . 'ai-agent-handbooks/';
        wp_mkdir_p( $folder );
        $dest = $folder . sanitize_file_name( $file['name'] );
        move_uploaded_file( $file['tmp_name'], $dest );
        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    public function handle_delete() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        check_admin_referer( 'ai_agent_delete' );
        $file = sanitize_file_name( $_GET['file'] ?? '' );
        $dir = wp_get_upload_dir();
        $folder = trailingslashit( $dir['basedir'] ) . 'ai-agent-handbooks/';
        $path = $folder . $file;
        if ( is_file( $path ) ) {
            unlink( $path );
        }
        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    public static function get_prompt() {
        if ( self::$cache !== null ) {
            return self::$cache;
        }
        $dir = wp_get_upload_dir();
        $folder = trailingslashit( $dir['basedir'] ) . 'ai-agent-handbooks/';
        $summary = '';
        if ( is_dir( $folder ) ) {
            foreach ( glob( $folder . '*', GLOB_NOSORT ) as $file ) {
                $summary .= self::summarize_file( $file ) . "\n";
            }
        }
        if ( empty( trim( $summary ) ) ) {
            $summary = 'You are a helpful assistant.';
        }
        self::$cache = substr( $summary, 0, 4000 );
        return self::$cache;
    }

    protected static function summarize_file( $path ) {
        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        $content = '';
        if ( 'txt' === $ext ) {
            $content = file_get_contents( $path );
        } elseif ( 'pdf' === $ext ) {
            // Very naive PDF extraction.
            if ( function_exists( 'shell_exec' ) ) {
                $content = shell_exec( 'pdftotext ' . escapeshellarg( $path ) . ' -' );
            }
        } elseif ( 'docx' === $ext ) {
            $zip = new ZipArchive();
            if ( $zip->open( $path ) === true ) {
                $data = $zip->getFromName( 'word/document.xml' );
                $zip->close();
                $content = strip_tags( $data );
            }
        }
        if ( ! $content ) {
            return '';
        }
        $content = preg_replace( '/\s+/', ' ', $content );
        return substr( $content, 0, 1000 );
    }
}
