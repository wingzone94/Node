<?php
/**
 * OGP Image Generator
 * 
 * PHP's GD library is used to generate OGP images with article titles.
 * No external APIs (like Gemini) are used.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Node_OGP_Generator {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'save_post', [ $this, 'generate_on_save' ], 10, 2 );
        add_action( 'wp_head', [ $this, 'inject_ogp_tags' ], 5 );
    }

    /**
     * Generate OGP image when a post is saved
     */
    public function generate_on_save( $post_id, $post ) {
        // 基本チェック
        if ( ! get_option( 'node_ogp_enabled' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( $post->post_status !== 'publish' || $post->post_type !== 'post' ) return;

        // エラーが発生しても保存プロセスを止めない
        try {
            $this->generate_ogp( $post_id );
        } catch ( Exception $e ) {
            error_log( 'OGP Generation Error: ' . $e->getMessage() );
        }
    }

    /**
     * Core generation logic using GD
     */
    public function generate_ogp( $post_id ) {
        // GDライブラリの存在確認
        if ( ! function_exists( 'imagecreatetruecolor' ) ) {
            error_log( 'Luminous Core Error: GD Library is not installed on this server. OGP generation skipped.' );
            return;
        }

        $title = get_the_title( $post_id );
        if ( empty( $title ) ) return;

        // Image dimensions
        $width = 1200;
        $height = 630;

        $image = imagecreatetruecolor( $width, $height );

        // --- Background (Fixed to Luminous Core Official Design) ---
        $bg_path = get_template_directory() . '/assets/images/ogp-bg.png';

        if ( file_exists( $bg_path ) ) {
            $info = getimagesize( $bg_path );
            switch ( $info[2] ) {
                case IMAGETYPE_JPEG: $source = imagecreatefromjpeg( $bg_path ); break;
                case IMAGETYPE_PNG:  $source = imagecreatefrompng( $bg_path ); break;
                case IMAGETYPE_WEBP: $source = imagecreatefromwebp( $bg_path ); break;
                default: $source = false;
            }
            if ( $source ) {
                imagecopyresampled( $image, $source, 0, 0, 0, 0, $width, $height, imagesx( $source ), imagesy( $source ) );
                imagedestroy( $source );
            }
        } else {
            // Fallback: White Background
            $white = imagecolorallocate( $image, 255, 255, 255 );
            imagefill( $image, 0, 0, $white );
        }

        // --- Logo Overlay (Fixed to Luminous Core Official Logo) ---
        $logo_path = get_template_directory() . '/assets/images/ogp-logo.png';
        if ( file_exists( $logo_path ) ) {
            $logo_info = getimagesize( $logo_path );
            $logo_src = false;
            switch ( $logo_info[2] ) {
                case IMAGETYPE_JPEG: $logo_src = imagecreatefromjpeg( $logo_path ); break;
                case IMAGETYPE_PNG:  $logo_src = imagecreatefrompng( $logo_path ); break;
                case IMAGETYPE_WEBP: $logo_src = imagecreatefromwebp( $logo_path ); break;
            }

            if ( $logo_src ) {
                // Resize logo (Height 100px)
                $max_h = 100;
                $orig_w = imagesx( $logo_src );
                $orig_h = imagesy( $logo_src );
                $scale = $max_h / $orig_h;
                $new_w = $orig_w * $scale;
                $new_h = $max_h;

                // Position: Bottom Right (with 50px margin)
                $pos_x = $width - $new_w - 50;
                $pos_y = $height - $new_h - 60; // Adjusted for line

                imagecopyresampled( $image, $logo_src, $pos_x, $pos_y, 0, 0, $new_w, $new_h, $orig_w, $orig_h );
                imagedestroy( $logo_src );
            }
        }

        // --- Text Drawing (Article Title) ---
        $text_color = imagecolorallocate( $image, 51, 51, 51 ); // Dark Gray (#333)
        $font_path = get_template_directory() . '/assets/fonts/ogp-font.ttf';

        if ( file_exists( $font_path ) ) {
            $font_size = 42;
            $wrapped_text = $this->wrap_text( $title, $font_size, $font_path, $width - 250 );
            
            $bbox_char = imagettfbbox( $font_size, 0, $font_path, "A" );
            $line_height = ( $bbox_char[1] - $bbox_char[7] ) * 1.5;
            $lines = explode( "\n", $wrapped_text );
            $total_height = count( $lines ) * $line_height;
            
            $y = ( $height - $total_height ) / 2 + ( $line_height / 1.2 );

            foreach ( $lines as $line ) {
                $line_bbox = imagettfbbox( $font_size, 0, $font_path, $line );
                $line_width = $line_bbox[2] - $line_bbox[0];
                $x = ( $width - $line_width ) / 2;
                imagettftext( $image, $font_size, 0, $x, $y, $text_color, $font_path, $line );
                $y += $line_height;
            }
        } else {
            error_log( 'Luminous Core: OGP font file not found at ' . $font_path . '. Title text rendering skipped to prevent encoding corruption.' );
        }

        // Save to uploads
        $upload_dir = wp_upload_dir();
        $ogp_dir = $upload_dir['basedir'] . '/ogp';
        if ( ! file_exists( $ogp_dir ) ) {
            wp_mkdir_p( $ogp_dir );
        }

        $filename = 'ogp-' . $post_id . '.png';
        $filepath = $ogp_dir . '/' . $filename;
        imagepng( $image, $filepath );
        imagedestroy( $image );

        update_post_meta( $post_id, '_node_ogp_image_url', $upload_dir['baseurl'] . '/ogp/' . $filename );
    }

    /**
     * Wrap text for Japanese (Simple logic)
     */
    private function wrap_text( $text, $font_size, $font_path, $max_width ) {
        $words = mb_str_split( $text );
        $wrapped = "";
        $line = "";

        foreach ( $words as $word ) {
            $test_line = $line . $word;
            $bbox = imagettfbbox( $font_size, 0, $font_path, $test_line );
            if ( ( $bbox[2] - $bbox[0] ) > $max_width ) {
                $wrapped .= $line . "\n";
                $line = $word;
            } else {
                $line = $test_line;
            }
        }
        $wrapped .= $line;
        return $wrapped;
    }

    /**
     * Inject OGP tags into head
     */
    public function inject_ogp_tags() {
        if ( ! is_single() ) return;

        $ogp_url = get_post_meta( get_the_ID(), '_node_ogp_image_url', true );
        if ( $ogp_url ) {
            echo '<meta property="og:image" content="' . esc_url( $ogp_url ) . '" />' . "\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url( $ogp_url ) . '" />' . "\n";
        }
    }
}

/**
 * Japanese mb_str_split fallback
 */
if ( ! function_exists( 'mb_str_split' ) ) {
    function mb_str_split( $str ) {
        return preg_split( '//u', $str, -1, PREG_SPLIT_NO_EMPTY );
    }
}

Node_OGP_Generator::instance();
