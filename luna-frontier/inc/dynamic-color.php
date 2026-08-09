<?php

declare( strict_types=1 );
/**
 * Luna Frontier 2.0 / SkyAlow — Dynamic Color（Phase 7）
 *
 * 指示書 §36〜§42。
 *
 * 設計:
 * - seed は「記事の人格」。Featured Image 由来を第一とする。
 * - Category Color は Wayfinding（分類）なので seed で上書きしない（§39）。
 *   親テーマは「カテゴリ色 → 画像色」の順で seed を決めるが、Luna Frontier では
 *   Dynamic Color と Category Color を役割として分離し、画像を優先する。
 * - 生 RGB を全 Surface へ直貼りしない（§37）。HSL トーナルスケールを作り、
 *   Light / Dark で別のトーンを選ぶ。
 * - 表示は PHP が <head> にインライン出力するため、初回描画から正しい色になる（§41）。
 *   JS は meta 未保存記事のフォールバックに降格させる（このプロトタイプでは
 *   PHP 側が毎回算出できるため JS 経路は不要 = 通常経路に JS を置かない）。
 * - 既存メタは rename しない。新規に _lc_seed_color を「追加」するだけで、
 *   Node 1.3 へ戻しても無視されるだけ（不可逆 migration をしない。§61）。
 *
 * @package LunaFrontier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * hex を [r,g,b] へ。
 *
 * @return array{0:int,1:int,2:int}|null
 */
function luna_frontier_hex_to_rgb( string $hex ): ?array {
	$hex = ltrim( trim( $hex ), '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
		return null;
	}

	return array(
		(int) hexdec( substr( $hex, 0, 2 ) ),
		(int) hexdec( substr( $hex, 2, 2 ) ),
		(int) hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * RGB → HSL（h: 0-360, s: 0-1, l: 0-1）。
 *
 * @param array{0:int,1:int,2:int} $rgb RGB。
 * @return array{h:float,s:float,l:float}
 */
function luna_frontier_rgb_to_hsl( array $rgb ): array {
	$r = $rgb[0] / 255;
	$g = $rgb[1] / 255;
	$b = $rgb[2] / 255;

	$max   = max( $r, $g, $b );
	$min   = min( $r, $g, $b );
	$delta = $max - $min;
	$l     = ( $max + $min ) / 2;

	if ( 0.0 === (float) $delta ) {
		return array(
			'h' => 0.0,
			's' => 0.0,
			'l' => $l,
		);
	}

	$s = $l > 0.5 ? $delta / ( 2 - $max - $min ) : $delta / ( $max + $min );

	if ( $max === $r ) {
		$h = ( $g - $b ) / $delta + ( $g < $b ? 6 : 0 );
	} elseif ( $max === $g ) {
		$h = ( $b - $r ) / $delta + 2;
	} else {
		$h = ( $r - $g ) / $delta + 4;
	}

	return array(
		'h' => $h * 60,
		's' => $s,
		'l' => $l,
	);
}

/**
 * HSL → hex。
 */
function luna_frontier_hsl_to_hex( float $h, float $s, float $l ): string {
	$h = fmod( fmod( $h, 360 ) + 360, 360 );
	$s = min( 1.0, max( 0.0, $s ) );
	$l = min( 1.0, max( 0.0, $l ) );

	$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
	$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
	$m = $l - $c / 2;

	if ( $h < 60 ) {
		$rgb = array( $c, $x, 0 );
	} elseif ( $h < 120 ) {
		$rgb = array( $x, $c, 0 );
	} elseif ( $h < 180 ) {
		$rgb = array( 0, $c, $x );
	} elseif ( $h < 240 ) {
		$rgb = array( 0, $x, $c );
	} elseif ( $h < 300 ) {
		$rgb = array( $x, 0, $c );
	} else {
		$rgb = array( $c, 0, $x );
	}

	return sprintf(
		'#%02x%02x%02x',
		(int) round( ( $rgb[0] + $m ) * 255 ),
		(int) round( ( $rgb[1] + $m ) * 255 ),
		(int) round( ( $rgb[2] + $m ) * 255 )
	);
}

/**
 * 相対輝度（WCAG）。
 *
 * @param array{0:int,1:int,2:int} $rgb RGB。
 */
function luna_frontier_relative_luminance( array $rgb ): float {
	$channels = array();

	foreach ( $rgb as $value ) {
		$v          = $value / 255;
		$channels[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}

	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * 2 色のコントラスト比。
 */
function luna_frontier_contrast_ratio( string $fg, string $bg ): float {
	$fg_rgb = luna_frontier_hex_to_rgb( $fg );
	$bg_rgb = luna_frontier_hex_to_rgb( $bg );

	if ( null === $fg_rgb || null === $bg_rgb ) {
		return 1.0;
	}

	$l1 = luna_frontier_relative_luminance( $fg_rgb );
	$l2 = luna_frontier_relative_luminance( $bg_rgb );

	$lighter = max( $l1, $l2 );
	$darker  = min( $l1, $l2 );

	return ( $lighter + 0.05 ) / ( $darker + 0.05 );
}

/**
 * 背景に対して WCAG AA を満たす on-color を選ぶ（白固定にしない。§42 / §49）。
 * 同系色の極端に暗い / 明るいトーンを候補にし、足りなければ白黒へ落とす。
 */
function luna_frontier_on_color( string $background, float $hue, float $saturation ): string {
	$candidates = array(
		luna_frontier_hsl_to_hex( $hue, min( $saturation, 0.35 ), 0.12 ),
		luna_frontier_hsl_to_hex( $hue, min( $saturation, 0.25 ), 0.96 ),
		'#000000',
		'#ffffff',
	);

	$best       = '#000000';
	$best_ratio = 0.0;

	foreach ( $candidates as $candidate ) {
		$ratio = luna_frontier_contrast_ratio( $candidate, $background );

		if ( $ratio >= 4.5 ) {
			return $candidate;
		}

		if ( $ratio > $best_ratio ) {
			$best_ratio = $ratio;
			$best       = $candidate;
		}
	}

	return $best;
}

/**
 * 現在の表示対象から seed 色を解決する。
 *
 * 優先順位:
 *   1. 保存済み _lc_seed_color（Luna Frontier が保存したもの）
 *   2. 投稿個別カラー _m3_primary_color（既存メタ。編集者の明示指定を尊重）
 *   3. Featured Image 由来（親の node_get_image_seed_color。attachment meta にキャッシュ）
 *   4. ブランドオレンジ（フォールバック）
 *
 * カテゴリ色は使わない。Category Color は分類として別に生き続ける（§39）。
 *
 * @return array{seed:string,source:string}
 */
function luna_frontier_resolve_seed(): array {
	$fallback = array(
		'seed'   => '#FF9900',
		'source' => 'brand',
	);

	if ( ! is_singular( array( 'post', 'page', 'node_library' ) ) ) {
		return $fallback;
	}

	$post_id = (int) get_the_ID();
	if ( ! $post_id ) {
		return $fallback;
	}

	$stored = sanitize_hex_color( (string) get_post_meta( $post_id, '_lc_seed_color', true ) );
	if ( $stored ) {
		return array(
			'seed'   => $stored,
			'source' => 'meta',
		);
	}

	$manual = sanitize_hex_color( (string) get_post_meta( $post_id, '_m3_primary_color', true ) );
	if ( $manual ) {
		return array(
			'seed'   => $manual,
			'source' => 'manual',
		);
	}

	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb_id && function_exists( 'node_get_image_seed_color' ) ) {
		$extracted = sanitize_hex_color( (string) node_get_image_seed_color( $thumb_id ) );
		if ( $extracted ) {
			return array(
				'seed'   => $extracted,
				'source' => 'image',
			);
		}
	}

	return $fallback;
}

/**
 * seed から Material 3 相当のロールトークンを導出する。
 *
 * 生 RGB をそのまま面へ流さず、seed の色相・彩度だけを引き継いで
 * Light / Dark それぞれのトーンを選び直す（§37 / §42）。
 *
 * @return array<string, array<string, string>> scheme => tokens。
 */
function luna_frontier_build_roles( string $seed ): array {
	$rgb = luna_frontier_hex_to_rgb( $seed );

	if ( null === $rgb ) {
		$rgb = array( 255, 153, 0 );
	}

	$hsl = luna_frontier_rgb_to_hsl( $rgb );
	$h   = $hsl['h'];

	// 極端に無彩色・高彩度な画像でも破綻しないよう彩度を実用域へ寄せる。
	$s = min( 0.72, max( 0.18, $hsl['s'] ) );

	$light_primary = luna_frontier_hsl_to_hex( $h, $s, 0.46 );
	$light_second  = luna_frontier_hsl_to_hex( $h + 18, $s * 0.72, 0.52 );
	$light_accent  = luna_frontier_hsl_to_hex( $h, min( 0.85, $s * 1.05 ), 0.32 );

	$dark_primary = luna_frontier_hsl_to_hex( $h, min( 0.78, $s * 0.94 ), 0.68 );
	$dark_second  = luna_frontier_hsl_to_hex( $h + 18, $s * 0.7, 0.7 );
	$dark_accent  = luna_frontier_hsl_to_hex( $h, min( 0.8, $s ), 0.74 );

	$light = array(
		'--lc-seed'                => $seed,
		'--lc-primary'             => $light_primary,
		'--lc-on-primary'          => luna_frontier_on_color( $light_primary, $h, $s ),
		'--lc-primary-container'   => luna_frontier_hsl_to_hex( $h, $s * 0.55, 0.90 ),
		'--lc-on-primary-container' => luna_frontier_hsl_to_hex( $h, min( 0.6, $s ), 0.16 ),
		'--lc-secondary'           => $light_second,
		'--lc-on-secondary'        => luna_frontier_on_color( $light_second, $h, $s ),
		'--lc-surface'             => luna_frontier_hsl_to_hex( $h, $s * 0.26, 0.95 ),
		'--lc-surface-variant'     => luna_frontier_hsl_to_hex( $h, $s * 0.3, 0.915 ),
		'--lc-on-surface'          => luna_frontier_hsl_to_hex( $h, min( 0.28, $s * 0.4 ), 0.13 ),
		'--lc-accent'              => $light_accent,
		'--lc-outline'             => luna_frontier_hsl_to_hex( $h, $s * 0.24, 0.74 ),
		'--lf-paper'               => luna_frontier_hsl_to_hex( $h, $s * 0.1, 0.995 ),
		'--lf-paper-sunken'        => luna_frontier_hsl_to_hex( $h, $s * 0.26, 0.93 ),
	);

	$dark = array(
		'--lc-seed'                => $seed,
		'--lc-primary'             => $dark_primary,
		'--lc-on-primary'          => luna_frontier_on_color( $dark_primary, $h, $s ),
		'--lc-primary-container'   => luna_frontier_hsl_to_hex( $h, $s * 0.5, 0.24 ),
		'--lc-on-primary-container' => luna_frontier_hsl_to_hex( $h, $s * 0.4, 0.9 ),
		'--lc-secondary'           => $dark_second,
		'--lc-on-secondary'        => luna_frontier_on_color( $dark_second, $h, $s ),
		'--lc-surface'             => luna_frontier_hsl_to_hex( $h, $s * 0.18, 0.075 ),
		'--lc-surface-variant'     => luna_frontier_hsl_to_hex( $h, $s * 0.18, 0.14 ),
		'--lc-on-surface'          => luna_frontier_hsl_to_hex( $h, $s * 0.12, 0.93 ),
		'--lc-accent'              => $dark_accent,
		'--lc-outline'             => luna_frontier_hsl_to_hex( $h, $s * 0.2, 0.42 ),
		'--lf-paper'               => luna_frontier_hsl_to_hex( $h, $s * 0.16, 0.135 ),
		'--lf-paper-sunken'        => luna_frontier_hsl_to_hex( $h, $s * 0.16, 0.06 ),
	);

	// on-surface が面に対して AA を割らないよう最終確認する。
	if ( luna_frontier_contrast_ratio( $light['--lc-on-surface'], $light['--lf-paper'] ) < 7.0 ) {
		$light['--lc-on-surface'] = '#141110';
	}
	if ( luna_frontier_contrast_ratio( $dark['--lc-on-surface'], $dark['--lf-paper'] ) < 7.0 ) {
		$dark['--lc-on-surface'] = '#f2ede9';
	}

	return array(
		'light' => $light,
		'dark'  => $dark,
	);
}

/**
 * <head> へ初期 CSS Token をインライン出力する。
 *
 * ヘッダー / フッターへは流し込まない（§23 / §38）。
 * body.lf-theme スコープに閉じるため、header / footer 内の要素も継承はするが、
 * ブランドクロームは親テーマの --md-sys-color-* を使い続けるので影響しない。
 */
function luna_frontier_print_dynamic_color(): void {
	$resolved = luna_frontier_resolve_seed();
	$roles    = luna_frontier_build_roles( $resolved['seed'] );

	$to_css = static function ( array $tokens ): string {
		$out = '';
		foreach ( $tokens as $name => $value ) {
			$out .= $name . ':' . $value . ';';
		}
		return $out;
	};

	// 子テーマの静的トークン（body.lf-theme）より必ず後で勝つよう、
	// 属性セレクタ 1 つぶん specificity を上げる。
	printf(
		'<style id="luna-frontier-dynamic-color">body.lf-theme[class]{%s}body.lf-theme[data-theme="dark"]{%s}</style>' . "\n",
		esc_html( $to_css( $roles['light'] ) ),
		esc_html( $to_css( $roles['dark'] ) )
	);
}
// 親の node_generate_m3_colors（優先度 10）より後に出す。
add_action( 'wp_head', 'luna_frontier_print_dynamic_color', 20 );

/**
 * 保存時に seed を投稿メタへ書き出す（§40 の「保存時抽出」）。
 *
 * 既存キーは触らず _lc_seed_color を追加するだけ。Node 1.3 はこのキーを読まないため、
 * テーマを戻しても影響しない（不可逆 migration にしない。§61）。
 */
function luna_frontier_store_seed_on_save( int $post_id, WP_Post $post ): void {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}

	$thumb_id = (int) get_post_thumbnail_id( $post_id );

	if ( ! $thumb_id || ! function_exists( 'node_get_image_seed_color' ) ) {
		delete_post_meta( $post_id, '_lc_seed_color' );
		return;
	}

	$seed = sanitize_hex_color( (string) node_get_image_seed_color( $thumb_id ) );

	if ( $seed ) {
		update_post_meta( $post_id, '_lc_seed_color', $seed );
	} else {
		delete_post_meta( $post_id, '_lc_seed_color' );
	}
}
add_action( 'save_post', 'luna_frontier_store_seed_on_save', 20, 2 );
