<?php
/**
 * Luminous Nexus - Blog Card Shortcode
 * 
 * OGP情報を取得し、Material 3 デザインのブログカードを生成します。
 * URL単体行の自動変換にも対応。
 *
 * @package Luminous_Nexus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URLからOGP情報を取得する（キャッシュ対応）
 */
function luminous_nexus_get_ogp_data( $url ) {
	$transient_key = 'luminous_ogp_' . md5( $url );
	$cached = get_transient( $transient_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$ogp = [
		'title'       => '',
		'description' => '',
		'image'       => '',
		'favicon'     => '',
		'site_name'   => '',
		'is_internal' => false,
	];

	// 内部記事の判定とデータ取得
	$home_url = home_url();
	if ( str_contains( $url, $home_url ) ) {
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			$ogp['title']       = get_the_title( $post_id );
			$ogp['description'] = get_the_excerpt( $post_id );
			$ogp['image']       = get_the_post_thumbnail_url( $post_id, 'large' );
			$ogp['site_name']   = get_bloginfo( 'name' );
			$ogp['is_internal'] = true;
			$ogp['favicon']     = get_site_icon_url( 32 );
			set_transient( $transient_key, $ogp, WEEK_IN_SECONDS );
			return $ogp;
		}
	}

	// 外部サイトからの取得
	$response = wp_safe_remote_get( $url, [
		'timeout'    => 15,
		'sslverify'  => false,
		'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
	] );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$html = wp_remote_retrieve_body( $response );
	if ( empty( $html ) ) {
		return false;
	}

	// 文字化け対策
	$content_type = wp_remote_retrieve_header( $response, 'content-type' );
	if ( str_contains( $content_type, 'shift_jis' ) || str_contains( $content_type, 'sjis' ) ) {
		$html = mb_convert_encoding( $html, 'UTF-8', 'SJIS' );
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	@$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	
	$xpath = new DOMXPath( $dom );

	$ogp['title']       = $xpath->evaluate( 'string(//meta[@property="og:title"]/@content)' ) ?: $xpath->evaluate( 'string(//title)' );
	$ogp['description'] = $xpath->evaluate( 'string(//meta[@property="og:description"]/@content)' ) ?: $xpath->evaluate( 'string(//meta[@name="description"]/@content)' );
	$ogp['image']       = $xpath->evaluate( 'string(//meta[@property="og:image"]/@content)' );
	$ogp['site_name']   = $xpath->evaluate( 'string(//meta[@property="og:site_name"]/@content)' ) ?: parse_url( $url, PHP_URL_HOST );
	$ogp['favicon']     = 'https://www.google.com/s2/favicons?domain=' . parse_url( $url, PHP_URL_HOST ) . '&sz=64';

	// 空文字のクリーニング
	$ogp['title'] = trim( $ogp['title'] );

	set_transient( $transient_key, $ogp, WEEK_IN_SECONDS );
	return $ogp;
}

/**
 * ブログカードショートコード本体
 */
function luminous_nexus_blogcard_shortcode( $atts ) {
	$atts = shortcode_atts( [ 'url' => '' ], $atts, 'blogcard' );
	if ( empty( $atts['url'] ) ) {
		return '';
	}

	$ogp = luminous_nexus_get_ogp_data( $atts['url'] );
	if ( ! $ogp ) {
		return '<a href="' . esc_url( $atts['url'] ) . '">' . esc_html( $atts['url'] ) . '</a>';
	}

	// Amazon アフィリエイト ID の付与 (Nexus設定から取得)
	$amazon_id = get_option( 'luminous_nexus_amazon_id' );
	if ( $amazon_id && str_contains( $atts['url'], 'amazon.co.jp' ) && ! str_contains( $atts['url'], 'tag=' ) ) {
		$separator = str_contains( $atts['url'], '?' ) ? '&' : '?';
		$atts['url'] .= "{$separator}tag={$amazon_id}";
	}

	ob_start();
	?>
	<div class="m3-blogcard m3-reveal" onclick="window.open('<?php echo esc_url( $atts['url'] ); ?>', '_blank')">
		<div class="m3-blogcard__content">
			<div class="m3-blogcard__text">
				<h4 class="m3-blogcard__title"><?php echo esc_html( $ogp['title'] ); ?></h4>
				<p class="m3-blogcard__description"><?php echo esc_html( wp_trim_words( $ogp['description'], 40 ) ); ?></p>
				<div class="m3-blogcard__footer">
					<?php if ( $ogp['favicon'] ) : ?>
						<img src="<?php echo esc_url( $ogp['favicon'] ); ?>" class="m3-blogcard__favicon" alt="" loading="lazy">
					<?php endif; ?>
					<span class="m3-blogcard__sitename"><?php echo esc_html( $ogp['site_name'] ); ?></span>
					<?php if ( $ogp['is_internal'] ) : ?>
						<span class="m3-blogcard__internal-badge">内部記事</span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $ogp['image'] ) : ?>
				<div class="m3-blogcard__image">
					<img src="<?php echo esc_url( $ogp['image'] ); ?>" alt="" loading="lazy">
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
// 自動変換フィルターを削除（ユーザーの意図しない変換を防止）
// add_filter( 'the_content', 'luminous_nexus_auto_blogcard', 11 );
