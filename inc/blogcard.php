<?php
/**
 * Node テーマ組み込みブログカード
 *
 * OGP 取得・ショートコード・URL 単独行の自動変換をテーマ側で提供する。
 * Luminous Nexus プラグインが未読み込みでも動作する。
 *
 * @package Node
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL から OGP 情報を取得する（キャッシュ対応）
 *
 * @param string $url 対象 URL。
 * @return array<string, mixed>|false
 */
function node_get_ogp_data( string $url ) {
	$url = esc_url_raw( $url );
	if ( empty( $url ) ) {
		return false;
	}

	$transient_key = 'node_ogp_' . md5( $url );
	$cached        = get_transient( $transient_key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$ogp = array(
		'title'       => '',
		'description' => '',
		'image'       => '',
		'favicon'     => '',
		'site_name'   => '',
		'is_internal' => false,
	);

	$home_url = home_url();
	if ( str_contains( $url, $home_url ) ) {
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			$ogp['title']       = get_the_title( $post_id );
			$ogp['description'] = get_the_excerpt( $post_id );
			$ogp['image']       = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
			$ogp['site_name']   = get_bloginfo( 'name' );
			$ogp['is_internal'] = true;
			$ogp['favicon']     = get_site_icon_url( 32 ) ?: '';
			set_transient( $transient_key, $ogp, WEEK_IN_SECONDS );
			return $ogp;
		}
	}

	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'    => 15,
			'sslverify'  => false,
			'user-agent' => 'Mozilla/5.0 (compatible; LuminousCore/1.0; +https://luminous-core.net/)',
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$html = wp_remote_retrieve_body( $response );
	if ( empty( $html ) ) {
		return false;
	}

	$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
	if ( str_contains( $content_type, 'shift_jis' ) || str_contains( $content_type, 'sjis' ) ) {
		$html = mb_convert_encoding( $html, 'UTF-8', 'SJIS' );
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
	libxml_clear_errors();

	$xpath = new DOMXPath( $dom );

	$ogp['title']       = trim( (string) $xpath->evaluate( 'string(//meta[@property="og:title"]/@content)' ) ) ?: trim( (string) $xpath->evaluate( 'string(//title)' ) );
	$ogp['description'] = trim( (string) $xpath->evaluate( 'string(//meta[@property="og:description"]/@content)' ) ) ?: trim( (string) $xpath->evaluate( 'string(//meta[@name="description"]/@content)' ) );
	$ogp['image']       = trim( (string) $xpath->evaluate( 'string(//meta[@property="og:image"]/@content)' ) );
	$ogp['site_name']   = trim( (string) $xpath->evaluate( 'string(//meta[@property="og:site_name"]/@content)' ) ) ?: (string) parse_url( $url, PHP_URL_HOST );
	$ogp['favicon']     = 'https://www.google.com/s2/favicons?domain=' . rawurlencode( (string) parse_url( $url, PHP_URL_HOST ) ) . '&sz=64';

	set_transient( $transient_key, $ogp, WEEK_IN_SECONDS );
	return $ogp;
}

/**
 * ブログカード HTML を生成する
 *
 * @param string $url リンク先 URL。
 * @return string
 */
function node_render_blogcard( string $url ): string {
	$url = esc_url_raw( $url );
	if ( empty( $url ) ) {
		return '';
	}

	$ogp = node_get_ogp_data( $url );
	if ( ! $ogp ) {
		return '<a class="m3-blogcard__fallback" href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
	}

	// Amazon アフィリエイト ID
	$amazon_id = get_option( 'luminous_nexus_amazon_id' );
	if ( $amazon_id && str_contains( $url, 'amazon.co.jp' ) && ! str_contains( $url, 'tag=' ) ) {
		$separator = str_contains( $url, '?' ) ? '&' : '?';
		$url      .= "{$separator}tag={$amazon_id}";
	}

	$is_internal = ! empty( $ogp['is_internal'] );
	$modifier    = $is_internal ? 'm3-blogcard--internal' : 'm3-blogcard--external';
	$aria_label  = sprintf(
		/* translators: %s: linked page title */
		__( '%s へのリンク', 'node' ),
		$ogp['title']
	);

	ob_start();
	?>
	<a href="<?php echo esc_url( $url ); ?>"
	   class="m3-blogcard m3-blogcard__link m3-reveal <?php echo esc_attr( $modifier ); ?>"
	   aria-label="<?php echo esc_attr( $aria_label ); ?>"
	   <?php echo $is_internal ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>>
		<?php if ( ! $is_internal ) : ?>
			<span class="m3-blogcard__external-icon material-symbols-outlined" aria-hidden="true">open_in_new</span>
		<?php endif; ?>
		<div class="m3-blogcard__content">
			<div class="m3-blogcard__text">
				<h4 class="m3-blogcard__title"><?php echo esc_html( $ogp['title'] ); ?></h4>
				<?php if ( ! empty( $ogp['description'] ) ) : ?>
					<p class="m3-blogcard__description"><?php echo esc_html( wp_trim_words( $ogp['description'], 40 ) ); ?></p>
				<?php endif; ?>
				<div class="m3-blogcard__footer">
					<?php if ( ! empty( $ogp['favicon'] ) ) : ?>
						<img src="<?php echo esc_url( $ogp['favicon'] ); ?>" class="m3-blogcard__favicon" alt="" loading="lazy" decoding="async" width="16" height="16">
					<?php endif; ?>
					<span class="m3-blogcard__sitename"><?php echo esc_html( $ogp['site_name'] ); ?></span>
					<?php if ( $is_internal ) : ?>
						<span class="m3-blogcard__internal-badge">
							<span class="material-symbols-outlined m3-blogcard__internal-icon" aria-hidden="true">home</span>
							<?php esc_html_e( '内部記事', 'node' ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( ! empty( $ogp['image'] ) ) : ?>
				<div class="m3-blogcard__image">
					<img src="<?php echo esc_url( $ogp['image'] ); ?>" alt="" loading="lazy" decoding="async">
				</div>
			<?php endif; ?>
		</div>
	</a>
	<?php
	return (string) ob_get_clean();
}

/**
 * ブログカードショートコード
 *
 * @param array<string, string>|string $atts ショートコード属性。
 * @return string
 */
function node_blogcard_shortcode( $atts ): string {
	$atts = shortcode_atts( array( 'url' => '' ), $atts, 'blogcard' );
	return node_render_blogcard( (string) $atts['url'] );
}

/**
 * 本文中の URL 単独行をブログカードへ自動変換
 *
 * @param string $content 投稿本文。
 * @return string
 */
function node_auto_blogcard( string $content ): string {
	if ( ! is_singular() || is_admin() || wp_doing_ajax() ) {
		return $content;
	}

	$pattern = '/^(<p>)?(https?:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)(<\/p>)?$/im';

	return (string) preg_replace_callback(
		$pattern,
		static function ( array $matches ): string {
			return node_render_blogcard( $matches[2] );
		},
		$content
	);
}

/**
 * Luminous Nexus 互換エイリアス
 */
if ( ! function_exists( 'luminous_nexus_get_ogp_data' ) ) {
	function luminous_nexus_get_ogp_data( $url ) {
		return node_get_ogp_data( (string) $url );
	}
}

if ( ! function_exists( 'luminous_nexus_blogcard_shortcode' ) ) {
	function luminous_nexus_blogcard_shortcode( $atts ) {
		return node_blogcard_shortcode( $atts );
	}
}

add_shortcode( 'blogcard', 'node_blogcard_shortcode' );
add_filter( 'the_content', 'node_auto_blogcard', 11 );
