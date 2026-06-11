<?php
/**
 * OGP share image generator (GD).
 *
 * @package Node_SEO_Tools
 */

namespace Node\SEO\Tools\Share;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Image_Generator {
	/**
	 * 描画ロジックの世代。レイアウト変更時に上げると既存画像が自動再生成される。
	 */
	public const GENERATOR_VERSION = '2026-06-11-insightbase-v4';

	/** Threads 等の上下トリミングを考慮したセーフゾーン（1200x630 基準） */
	private const SNS_SAFE_TOP    = 90;
	private const SNS_SAFE_BOTTOM = 540;
	/** Instagram 1:1 中央クロップでも切れない幅（1200 幅の中央 630px 内） */
	private const SNS_SAFE_WIDTH  = 620;

	private const META_GENERATOR_VERSION = '_node_ogp_generator_version';

	private const UNIFY_ALL_LINE_WEIGHT   = true;
	private const UNIFY_MIXED_LINE_WEIGHT = true;
	private const MAX_TITLE_LINES         = 3;
	private const TITLE_MAX_FONT_SIZE     = 48;
	private const TITLE_MIN_FONT_SIZE     = 34;
	private const MIN_ORPHAN_LINE_CHARS   = 10;
	private const BOLD_PIXEL_OFFSETS      = array(
		array( 0.0, 0.0 ),
		array( 1.0, 0.0 ),
		array( 0.0, 1.0 ),
		array( 1.0, 1.0 ),
		array( 2.0, 0.0 ),
		array( 0.0, 2.0 ),
	);

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'save_post', array( $this, 'generate_on_save' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'maybe_regenerate_stale' ) );
	}

	/**
	 * 旧ロジックで生成済みのOGP画像を、記事閲覧時に1回だけ作り直す。
	 * 世代メタが現行と一致していれば何もしない。
	 */
	public function maybe_regenerate_stale(): void {
		if ( is_admin() || wp_doing_ajax() || ! is_singular( 'post' ) ) {
			return;
		}
		if ( ! get_option( 'node_ogp_enabled' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		if ( self::GENERATOR_VERSION === get_post_meta( $post_id, self::META_GENERATOR_VERSION, true ) ) {
			return;
		}

		try {
			$this->generate_ogp( $post_id );
		} catch ( \Exception $e ) {
			error_log( 'Node SEO Tools OGP regenerate error: ' . $e->getMessage() );
		}
	}

	public function generate_on_save( int $post_id, \WP_Post $post ): void {
		if ( ! get_option( 'node_ogp_enabled' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status || 'post' !== $post->post_type ) {
			return;
		}

		try {
			$this->generate_ogp( $post_id );
		} catch ( \Exception $e ) {
			error_log( 'Node SEO Tools OGP error: ' . $e->getMessage() );
		}
	}

	public function generate_ogp( int $post_id ): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			error_log( 'Node SEO Tools: GD is not available. OGP generation skipped.' );
			return;
		}

		$title = get_the_title( $post_id );
		if ( '' === $title ) {
			return;
		}

		$assets = Asset_Syncer::ensure_assets();

		$width  = 1200;
		$height = 630;

		$image = imagecreatetruecolor( $width, $height );
		if ( false === $image ) {
			return;
		}

		$this->apply_background( $image, $assets['background'], $width, $height );
		$this->apply_title(
			$image,
			$title,
			$assets['font_jp'] ?? '',
			$assets['font_latin'] ?? '',
			$width,
			$height
		);
		$this->apply_branding_left_top(
			$image,
			$assets['logo'],
			$width,
			$height
		);

		$upload_dir = wp_upload_dir();
		$ogp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'ogp';
		if ( ! is_dir( $ogp_dir ) ) {
			wp_mkdir_p( $ogp_dir );
		}

		$filename = 'ogp-' . $post_id . '.png';
		$filepath = trailingslashit( $ogp_dir ) . $filename;

		imagepng( $image, $filepath, 6 );
		if ( function_exists( 'imagedestroy' ) ) {
			imagedestroy( $image );
		}

		$mtime = is_file( $filepath ) ? (int) filemtime( $filepath ) : time();

		update_post_meta(
			$post_id,
			'_node_ogp_image_url',
			trailingslashit( $upload_dir['baseurl'] ) . 'ogp/' . $filename
		);
		update_post_meta( $post_id, '_node_ogp_image_mtime', $mtime );
		update_post_meta( $post_id, self::META_GENERATOR_VERSION, self::GENERATOR_VERSION );
	}

	/**
	 * @param \GdImage|resource $image
	 */
	private function apply_background( $image, string $bg_path, int $width, int $height ): void {
		if ( ! Asset_Syncer::is_valid_image( $bg_path ) ) {
			$white = imagecolorallocate( $image, 255, 255, 255 );
			imagefill( $image, 0, 0, $white );
			return;
		}

		$info = getimagesize( $bg_path );
		if ( ! is_array( $info ) ) {
			return;
		}

		$source = match ( $info[2] ) {
			IMAGETYPE_JPEG => imagecreatefromjpeg( $bg_path ),
			IMAGETYPE_PNG  => imagecreatefrompng( $bg_path ),
			IMAGETYPE_WEBP => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $bg_path ) : false,
			default        => false,
		};

		if ( false === $source ) {
			return;
		}

		imagecopyresampled( $image, $source, 0, 0, 0, 0, $width, $height, imagesx( $source ), imagesy( $source ) );
		if ( function_exists( 'imagedestroy' ) ) {
			imagedestroy( $source );
		}
	}

	/**
	 * @param \GdImage|resource $image
	 */
	private function apply_logo( $image, string $logo_path, int $width, int $height ): void {
		if ( ! Asset_Syncer::is_valid_image( $logo_path ) ) {
			return;
		}

		$logo_info = getimagesize( $logo_path );
		if ( ! is_array( $logo_info ) ) {
			return;
		}

		$logo_src = match ( $logo_info[2] ) {
			IMAGETYPE_JPEG => imagecreatefromjpeg( $logo_path ),
			IMAGETYPE_PNG  => imagecreatefrompng( $logo_path ),
			IMAGETYPE_WEBP => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $logo_path ) : false,
			default         => false,
		};

		if ( false === $logo_src ) {
			return;
		}

		$max_h  = 100;
		$orig_w = imagesx( $logo_src );
		$orig_h = imagesy( $logo_src );
		$scale  = $max_h / $orig_h;
		$new_w  = (int) ( $orig_w * $scale );
		$new_h  = $max_h;
		$pos_x  = $width - $new_w - 50;
		$pos_y  = $height - $new_h - 60;

		imagecopyresampled( $image, $logo_src, $pos_x, $pos_y, 0, 0, $new_w, $new_h, $orig_w, $orig_h );
		if ( function_exists( 'imagedestroy' ) ) {
			imagedestroy( $logo_src );
		}
	}

	/**
	 * Left-top brand lockup (icon + text) with top-gradient cutout.
	 *
	 * @param \GdImage|resource $image
	 */
	private function apply_branding_left_top( $image, string $logo_path, int $width, int $height ): void {
		if ( ! Asset_Syncer::is_valid_image( $logo_path ) ) {
			return;
		}

		$logo_info = getimagesize( $logo_path );
		if ( ! is_array( $logo_info ) ) {
			return;
		}

		$logo_src = match ( $logo_info[2] ) {
			IMAGETYPE_JPEG => imagecreatefromjpeg( $logo_path ),
			IMAGETYPE_PNG  => imagecreatefrompng( $logo_path ),
			IMAGETYPE_WEBP => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $logo_path ) : false,
			default         => false,
		};

		if ( false === $logo_src ) {
			return;
		}

		$left_margin   = 46;
		$top_margin    = self::SNS_SAFE_TOP + 6;
		$icon_h        = 44;

		$orig_w = imagesx( $logo_src );
		$orig_h = imagesy( $logo_src );
		$scale  = $icon_h / $orig_h;
		$icon_w = (int) round( $orig_w * $scale );

		$icon_x = $left_margin;
		$icon_y = $top_margin;

		// Cut out the top gradient under the logo lockup.
		$cut_pad_x = 10;
		$cut_pad_y = 8;
		$cut_w     = $icon_w + ( $cut_pad_x * 2 );
		$cut_h     = $icon_h + ( $cut_pad_y * 2 );
		$white     = imagecolorallocate( $image, 255, 255, 255 );
		imagefilledrectangle(
			$image,
			$icon_x - $cut_pad_x,
			$icon_y - $cut_pad_y,
			$icon_x - $cut_pad_x + $cut_w,
			$icon_y - $cut_pad_y + $cut_h,
			$white
		);

		imagecopyresampled( $image, $logo_src, $icon_x, $icon_y, 0, 0, $icon_w, $icon_h, $orig_w, $orig_h );
		if ( function_exists( 'imagedestroy' ) ) {
			imagedestroy( $logo_src );
		}
	}

	/**
	 * @param \GdImage|resource $image
	 */
	private function apply_title( $image, string $title, string $font_jp, string $font_latin, int $width, int $height ): void {
		if ( '' === $font_jp || ! Asset_Syncer::is_valid_font( $font_jp ) ) {
			error_log( 'Node SEO Tools: no valid Japanese font resolved. Title skipped.' );
			return;
		}
		if ( '' === $font_latin || ! Asset_Syncer::is_valid_font( $font_latin ) ) {
			// Fallback: if Inter cannot be resolved, draw all glyphs with Japanese font.
			$font_latin = $font_jp;
		}

		if ( ! function_exists( 'imagettftext' ) ) {
			error_log( 'Node SEO Tools: FreeType is not available. Title skipped.' );
			return;
		}

		$title         = self::normalize_text( $title );
		$text_color    = imagecolorallocate( $image, 56, 56, 56 );
		$max_width     = self::SNS_SAFE_WIDTH;
		$line_spacing  = 1.38;
		$kerning_ratio = 0.012; // auto kerning based on font size.
		$title_box_y   = self::SNS_SAFE_TOP + 12;
		$title_box_h   = self::SNS_SAFE_BOTTOM - self::SNS_SAFE_TOP - 24;

		$layout = $this->select_title_layout(
			$title,
			$font_jp,
			$font_latin,
			$max_width,
			$title_box_h,
			$line_spacing,
			$kerning_ratio
		);

		$font_size    = $layout['font_size'];
		$lines        = $layout['lines'];
		$line_height  = $layout['line_height'];
		$total_height = count( $lines ) * $line_height;
		$y            = $title_box_y + ( ( $title_box_h - $total_height ) / 2 ) + ( $line_height / 1.15 );
		$kerning_px   = $font_size * $kerning_ratio;

		foreach ( $lines as $line ) {
			$line_width = $this->measure_line_width( $line, $font_size, $font_jp, $font_latin, $kerning_px );
			$x          = ( $width - $line_width ) / 2;
			$this->draw_mixed_line( $image, $line, $font_size, (float) $x, (float) $y, $text_color, $font_jp, $font_latin, $kerning_px );
			$y += $line_height;
		}
	}

	/**
	 * Pick the largest font size that minimizes line count and avoids orphan lines.
	 *
	 * @return array{font_size:int,lines:array<int,string>,line_height:float}
	 */
	private function select_title_layout(
		string $title,
		string $font_jp,
		string $font_latin,
		int $max_width,
		int $title_box_h,
		float $line_spacing,
		float $kerning_ratio
	): array {
		$best = null;

		for ( $size = self::TITLE_MAX_FONT_SIZE; $size >= self::TITLE_MIN_FONT_SIZE; $size -= 2 ) {
			$kerning_px = $size * $kerning_ratio;
			$lines      = $this->wrap_text_with_rules( $title, $size, $font_jp, $font_latin, $max_width, $kerning_px );
			$bbox_char  = imagettfbbox( $size, 0, $font_latin, 'A' );
			if ( ! is_array( $bbox_char ) ) {
				continue;
			}

			$line_height  = ( $bbox_char[1] - $bbox_char[7] ) * $line_spacing;
			$total_height = count( $lines ) * $line_height;
			if ( count( $lines ) > self::MAX_TITLE_LINES || $total_height > $title_box_h ) {
				continue;
			}
			if ( $this->has_orphan_lines( $lines ) ) {
				continue;
			}

			if (
				null === $best
				|| count( $lines ) < count( $best['lines'] )
				|| ( count( $lines ) === count( $best['lines'] ) && $size > $best['font_size'] )
			) {
				$best = array(
					'font_size'   => $size,
					'lines'       => $lines,
					'line_height' => $line_height,
				);
			}
		}

		if ( null !== $best ) {
			return $best;
		}

		for ( $size = self::TITLE_MIN_FONT_SIZE; $size >= 28; $size -= 2 ) {
			$kerning_px = $size * $kerning_ratio;
			$lines      = $this->wrap_text_with_rules( $title, $size, $font_jp, $font_latin, $max_width, $kerning_px );
			$bbox_char  = imagettfbbox( $size, 0, $font_latin, 'A' );
			if ( ! is_array( $bbox_char ) ) {
				continue;
			}
			$line_height  = ( $bbox_char[1] - $bbox_char[7] ) * $line_spacing;
			$total_height = count( $lines ) * $line_height;
			if ( count( $lines ) <= self::MAX_TITLE_LINES && $total_height <= $title_box_h ) {
				return array(
					'font_size'   => $size,
					'lines'       => $lines,
					'line_height' => $line_height,
				);
			}
		}

		$fallback_size = 28;
		$kerning_px    = $fallback_size * $kerning_ratio;
		$lines         = $this->wrap_text_with_rules( $title, $fallback_size, $font_jp, $font_latin, $max_width, $kerning_px );
		$bbox_char     = imagettfbbox( $fallback_size, 0, $font_latin, 'A' );
		$line_height   = is_array( $bbox_char )
			? ( $bbox_char[1] - $bbox_char[7] ) * $line_spacing
			: (float) $fallback_size * $line_spacing;

		return array(
			'font_size'   => $fallback_size,
			'lines'       => $lines,
			'line_height' => $line_height,
		);
	}

	/**
	 * @param array<int, string> $lines
	 */
	/**
	 * @param array<int, string> $lines
	 * @return array<int, string>
	 */
	private function merge_orphan_tail_lines( array $lines ): array {
		while ( count( $lines ) > 1 ) {
			$last    = trim( (string) end( $lines ) );
			$visible = preg_replace( '/\s+/u', '', $last );
			$length  = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $visible ) : strlen( (string) $visible );
			if ( $length >= self::MIN_ORPHAN_LINE_CHARS ) {
				break;
			}
			$tail = (string) array_pop( $lines );
			$lines[ count( $lines ) - 1 ] .= $tail;
		}
		return $lines;
	}

	/**
	 * @param array<int, string> $lines
	 */
	private function has_orphan_lines( array $lines ): bool {
		if ( count( $lines ) <= 1 ) {
			return false;
		}

		foreach ( $lines as $line ) {
			$visible = preg_replace( '/\s+/u', '', $line );
			if ( null === $visible ) {
				continue;
			}
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $visible ) : strlen( $visible );
			if ( $length > 0 && $length < self::MIN_ORPHAN_LINE_CHARS ) {
				return true;
			}
		}

		return false;
	}

	private static function normalize_text( string $text ): string {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
		}
		return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	}

	private function wrap_text_with_rules(
		string $text,
		int $font_size,
		string $font_jp,
		string $font_latin,
		int $max_width,
		float $kerning_px
	): array {
		$tokens = $this->tokenize_text( $text );
		if ( empty( $tokens ) ) {
			return array( $text );
		}

		$lines   = array();
		$current = '';

		foreach ( $tokens as $token ) {
			if ( '' === $current ) {
				$current = $token;
				continue;
			}

			$test = $current . $token;
			$w    = $this->measure_line_width( $test, $font_size, $font_jp, $font_latin, $kerning_px );
			if ( $w <= $max_width ) {
				$current = $test;
				continue;
			}

			$split = $this->split_long_token_for_width( $token, $font_size, $font_jp, $font_latin, $max_width, $kerning_px );
			$lines[] = $this->trim_line_end_forbidden( $current );
			$current = array_shift( $split ) ?? '';
			foreach ( $split as $fragment ) {
				$lines[] = $this->trim_line_end_forbidden( $current );
				$current = $fragment;
			}
		}

		// 最終行の句読点はタイトル末尾として保持する。
		$lines[] = $current;
		$lines   = $this->fix_line_start_forbidden( $lines );
		$lines   = $this->merge_orphan_tail_lines( $lines );
		$lines   = array_map( static fn( string $line ): string => trim( $line ), $lines );
		return array_values( array_filter( $lines, static fn( string $line ): bool => '' !== $line ) );
	}

	private function tokenize_text( string $text ): array {
		$chars = function_exists( 'mb_str_split' ) ? mb_str_split( $text ) : preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) ) {
			return array( $text );
		}

		$tokens = array();
		$buffer = '';
		$type   = null;
		foreach ( $chars as $idx => $ch ) {
			$is_latin = (bool) preg_match( '/[A-Za-z0-9]/u', $ch );
			$is_space = (bool) preg_match( '/\s/u', $ch );
			$ch_type  = $is_latin ? 'latin' : 'other';

			if ( '.' === $ch && 'latin' === $type ) {
				$next = $chars[ $idx + 1 ] ?? '';
				if ( preg_match( '/[0-9]$/u', $buffer ) && is_string( $next ) && preg_match( '/^[0-9]/u', $next ) ) {
					$buffer .= $ch;
					continue;
				}
			}

			if ( $is_space && 'latin' === $type ) {
				$next = $chars[ $idx + 1 ] ?? '';
				if ( is_string( $next ) && preg_match( '/[A-Za-z0-9]/u', $next ) ) {
					$buffer .= $ch;
					continue;
				}
			}

			if ( null === $type ) {
				$type   = $ch_type;
				$buffer = $ch;
				continue;
			}
			if ( $type === $ch_type && 'latin' === $type ) {
				$buffer .= $ch;
				continue;
			}
			$tokens[] = $buffer;
			$buffer   = $ch;
			$type     = $ch_type;
		}
		if ( '' !== $buffer ) {
			$tokens[] = $buffer;
		}
		return $tokens;
	}

	private function split_long_token_for_width(
		string $token,
		int $font_size,
		string $font_jp,
		string $font_latin,
		int $max_width,
		float $kerning_px
	): array {
		$token_width = $this->measure_line_width( $token, $font_size, $font_jp, $font_latin, $kerning_px );
		if ( $token_width <= $max_width ) {
			return array( $token );
		}

		$chars = function_exists( 'mb_str_split' ) ? mb_str_split( $token ) : preg_split( '//u', $token, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) ) {
			return array( $token );
		}

		$fragments = array();
		$current   = '';
		foreach ( $chars as $ch ) {
			$test = $current . $ch;
			$w    = $this->measure_line_width( $test, $font_size, $font_jp, $font_latin, $kerning_px );
			if ( '' !== $current && $w > $max_width ) {
				$fragments[] = $current;
				$current     = $ch;
			} else {
				$current = $test;
			}
		}
		if ( '' !== $current ) {
			$fragments[] = $current;
		}
		return $fragments;
	}

	private function trim_line_end_forbidden( string $line ): string {
		$forbidden = '、。，．！？!?)）]｝〕〉》」』】';
		while ( '' !== $line ) {
			$last = mb_substr( $line, -1, 1 );
			if ( false === mb_strpos( $forbidden, $last ) ) {
				break;
			}
			$line = mb_substr( $line, 0, mb_strlen( $line ) - 1 );
		}
		return $line;
	}

	private function fix_line_start_forbidden( array $lines ): array {
		$forbidden_start = '、。，．！？!?)）]｝〕〉》」』】';
		for ( $i = 1; $i < count( $lines ); $i++ ) {
			if ( '' === $lines[ $i ] || '' === $lines[ $i - 1 ] ) {
				continue;
			}
			$first = mb_substr( $lines[ $i ], 0, 1 );
			if ( false !== mb_strpos( $forbidden_start, $first ) ) {
				$lines[ $i - 1 ] .= $first;
				$lines[ $i ]      = mb_substr( $lines[ $i ], 1 );
			}
		}
		return $lines;
	}

	private function measure_line_width(
		string $line,
		int $font_size,
		string $font_jp,
		string $font_latin,
		float $kerning_px
	): float {
		$chars = function_exists( 'mb_str_split' ) ? mb_str_split( $line ) : preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) ) {
			return 0.0;
		}
		$width = 0.0;
		$count = count( $chars );
		$line_font = '';
		if ( self::UNIFY_ALL_LINE_WEIGHT ) {
			$line_font = $font_jp;
		} elseif ( self::UNIFY_MIXED_LINE_WEIGHT && $this->has_mixed_scripts( $line ) ) {
			$line_font = $font_jp;
		}
		foreach ( $chars as $idx => $ch ) {
			$font = '' !== $line_font ? $line_font : ( preg_match( '/[A-Za-z0-9]/u', $ch ) ? $font_latin : $font_jp );
			$bbox = imagettfbbox( $font_size, 0, $font, $ch );
			if ( is_array( $bbox ) ) {
				$width += ( $bbox[2] - $bbox[0] );
			}
			if ( $idx < $count - 1 ) {
				$width += $kerning_px;
			}
		}
		return $width;
	}

	/**
	 * @param \GdImage|resource $image
	 */
	private function draw_mixed_line(
		$image,
		string $line,
		int $font_size,
		float $x,
		float $y,
		int $color,
		string $font_jp,
		string $font_latin,
		float $kerning_px
	): void {
		$chars = function_exists( 'mb_str_split' ) ? mb_str_split( $line ) : preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) ) {
			return;
		}
		$line_font = '';
		if ( self::UNIFY_ALL_LINE_WEIGHT ) {
			$line_font = $font_jp;
		} elseif ( self::UNIFY_MIXED_LINE_WEIGHT && $this->has_mixed_scripts( $line ) ) {
			$line_font = $font_jp;
		}
		$count = count( $chars );
		foreach ( $chars as $idx => $ch ) {
			$font = '' !== $line_font ? $line_font : ( preg_match( '/[A-Za-z0-9]/u', $ch ) ? $font_latin : $font_jp );
			$bbox = imagettfbbox( $font_size, 0, $font, $ch );
			if ( ! is_array( $bbox ) ) {
				continue;
			}
			foreach ( self::BOLD_PIXEL_OFFSETS as $offset ) {
				imagettftext(
					$image,
					$font_size,
					0,
					(int) round( $x + $offset[0] ),
					(int) round( $y + $offset[1] ),
					$color,
					$font,
					$ch
				);
			}
			$x += ( $bbox[2] - $bbox[0] );
			if ( $idx < $count - 1 ) {
				$x += $kerning_px;
			}
		}
	}

	private function has_mixed_scripts( string $line ): bool {
		$has_latin = (bool) preg_match( '/[A-Za-z0-9]/u', $line );
		$has_jp    = (bool) preg_match( '/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $line );
		return $has_latin && $has_jp;
	}
}
