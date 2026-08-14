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
 * URL が自サイト（同一ホスト）かどうかを判定する。
 *
 * `str_contains($url, home_url())` はスキーム依存で、HTTPS リクエスト時に home_url() が
 * https を返す一方で本文中の URL が http だと内部判定に失敗する。ホスト同士を比較して
 * スキームに依存しないようにする。
 *
 * @param string $url 判定対象 URL。
 * @return bool
 */
function node_is_internal_url( string $url ): bool {
	$url_host  = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
	$home_host = strtolower( (string) parse_url( home_url(), PHP_URL_HOST ) );

	return '' !== $url_host && $url_host === $home_host;
}

/**
 * 自サイト URL を投稿 ID へ変換する（スキーム差異を吸収）。
 *
 * @param string $url 自サイト URL。
 * @return int 投稿 ID（見つからなければ 0）。
 */
function node_internal_url_to_postid( string $url ): int {
	$post_id = url_to_postid( $url );
	if ( ! $post_id ) {
		// home 側のスキームへ揃えて再試行（http/https 混在対策）。
		$post_id = url_to_postid( set_url_scheme( $url ) );
	}

	return (int) $post_id;
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

	if ( node_is_internal_url( $url ) ) {
		$post_id = node_internal_url_to_postid( $url );
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
 * Luminous Core ブランドカード用の画像URLを返す。
 *
 * ブランドオレンジ背景にロゴ＋ワードマークを合成済みの静的アセット。
 * `[blogcard]` ショートコードで明示的に luminous-core.net を指定した場合のみ使用する
 * （単独行URL・core/embedブロック等の自動検出では、実際のOGP画像をそのまま表示する —
 * 本番の記事ページは Node_SEO_Tools\Share\Image_Generator が記事タイトル入りのOGP画像を
 * 既に生成しているため、自動検出時にこちらで上書きする必要はない）。
 *
 * @return string
 */
function node_get_luminous_core_card_image(): string {
	return get_template_directory_uri() . '/assets/images/luminous-core-card-image.png';
}

/**
 * URL のホストが Luminous Core 自身のドメインかどうかを判定する。
 *
 * 同一ブランドへの外部リンク（本番ドメイン違い・dev環境からの参照等）では、
 * 記事ごとのOGP画像ではなく常にサイトロゴを表示する。
 *
 * @param string $host 判定対象ホスト。
 * @return bool
 */
function node_is_luminous_core_host( string $host ): bool {
	$host = strtolower( $host );
	return in_array( $host, array( 'luminous-core.net', 'www.luminous-core.net' ), true );
}

/**
 * ブログカード HTML マークアップを生成する。
 *
 * @param array<string, mixed> $ogp OGP 情報。
 * @param string               $url リンク先 URL。
 * @return string
 */
function node_blogcard_markup( array $ogp, string $url ): string {
	$url = esc_url_raw( $url );
	if ( empty( $url ) || empty( $ogp['title'] ) ) {
		return '';
	}

	$is_internal = ! empty( $ogp['is_internal'] );
	$modifier    = $is_internal ? 'm3-blogcard--internal' : 'm3-blogcard--external';
	if ( ! empty( $ogp['is_brand'] ) ) {
		// ブランドバナー（1200x630 のロゴ＋ワードマーク一体画像）は通常のサムネイル枠だと
		// 中央クロップで文字が切れるため、専用の表示ルールを当てる。
		$modifier .= ' m3-blogcard--brand';
	}
	$title       = (string) $ogp['title'];
	$share_title = wp_strip_all_tags( $title );
	$aria_label  = sprintf(
		/* translators: %s: linked page title */
		__( '%s へのリンク', 'node' ),
		$share_title
	);

	// NOTE: このカードは2経路で挿入される — `[blogcard]` ショートコード（the_content 優先度11、
	// wpautop の後）と oEmbed/autoembed フック（優先度8、wpautop の前）。後者は wpautop に
	// 破壊されるため node_blogcard_defer()/node_blogcard_hydrate()（優先度20）で遅延挿入する。
	// どちらの経路でも壊れないよう、末尾でタグ間空白を潰して1行 HTML にする。
	//
	// カード全体は「ストレッチリンク」でクリック可能にする（.m3-blogcard__overlay の ::after が
	// カード全面を覆う）。コピー/シェアのアクションボタンはそのオーバーレイより上（z-index）に置き、
	// <a> の中にインタラクティブ要素を入れ子にせずに独立してクリックできるようにする。
	ob_start();
	?>
	<div class="m3-blogcard-wrap">
		<div class="m3-blogcard m3-reveal <?php echo esc_attr( $modifier ); ?>">
			<div class="m3-blogcard__body">
				<?php if ( ! empty( $ogp['image'] ) ) : ?>
					<div class="m3-blogcard__image">
						<img src="<?php echo esc_url( $ogp['image'] ); ?>" alt="" loading="lazy" decoding="async">
					</div>
				<?php endif; ?>
				<div class="m3-blogcard__text">
					<a class="m3-blogcard__overlay" href="<?php echo esc_url( $url ); ?>"
					   aria-label="<?php echo esc_attr( $aria_label ); ?>"
					   <?php echo $is_internal ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>>
						<span class="m3-blogcard__title"><?php echo esc_html( $title ); ?></span>
						<?php if ( ! $is_internal ) : ?>
							<span class="m3-blogcard__external-icon material-symbols-outlined" aria-hidden="true">open_in_new</span>
						<?php endif; ?>
					</a>
					<?php if ( ! empty( $ogp['description'] ) ) : ?>
						<p class="m3-blogcard__description"><?php echo esc_html( wp_trim_words( $ogp['description'], 40 ) ); ?></p>
					<?php endif; ?>
					<div class="m3-blogcard__footer">
						<span class="m3-blogcard__source">
							<?php if ( ! empty( $ogp['favicon'] ) ) : ?>
								<img src="<?php echo esc_url( $ogp['favicon'] ); ?>" class="m3-blogcard__favicon" alt="" loading="lazy" decoding="async" width="16" height="16">
							<?php endif; ?>
							<span class="m3-blogcard__sitename"><?php echo esc_html( $ogp['site_name'] ); ?></span>
						</span>
						<span class="m3-blogcard__actions">
							<button type="button" class="m3-blogcard__action m3-blogcard__action--copy" data-url="<?php echo esc_url( $url ); ?>" data-share-title="<?php echo esc_attr( $share_title ); ?>" title="<?php esc_attr_e( 'リンクをコピー', 'node' ); ?>" aria-label="<?php esc_attr_e( 'リンクをコピー', 'node' ); ?>">
								<span class="material-symbols-outlined m3-blogcard__action-icon" aria-hidden="true">content_copy</span>
							</button>
							<button type="button" class="m3-blogcard__action m3-blogcard__action--share" data-url="<?php echo esc_url( $url ); ?>" data-share-title="<?php echo esc_attr( $share_title ); ?>" title="<?php esc_attr_e( 'シェア', 'node' ); ?>" aria-label="<?php esc_attr_e( 'シェアする', 'node' ); ?>">
								<span class="material-symbols-outlined m3-blogcard__action-icon" aria-hidden="true">share</span>
							</button>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
	$html = (string) ob_get_clean();

	// wpautop は改行を <br>/<p> に変換するため、カード内の改行を完全に除去する。
	// (1) 改行＋前後インデントを単一スペースへ（属性間 `"\n\t class` → `" class` も含め安全）。
	// (2) タグ間の空白のみを潰す（`> <` → `><`）。テキストノード（`>Luminous Core<`）は
	//     空白以外を含むためマッチせず内容は保持される。結果として改行ゼロの1行HTMLになり、
	//     wpautop 前（autoembed）でも後（ショートコード）でも破壊されない。
	$html = (string) preg_replace( '/\s*\n\s*/', ' ', $html );
	$html = (string) preg_replace( '/>\s+</', '><', $html );

	return trim( $html );
}

/**
 * ブログカード HTML を生成する。
 *
 * @param string $url           リンク先 URL。
 * @param bool   $brand_override luminous-core.net の場合にブランド画像（ロゴ＋ワードマーク）を
 *                                強制表示するか。`[blogcard]` ショートコードからのみ true を渡す。
 * @return string
 */
function node_render_blogcard( string $url, bool $brand_override = false ): string {
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

	if ( $brand_override && node_is_luminous_core_host( (string) parse_url( $url, PHP_URL_HOST ) ) ) {
		$ogp['image']    = node_get_luminous_core_card_image();
		$ogp['is_brand'] = true;
	}

	return node_blogcard_markup( $ogp, $url );
}

/**
 * oEmbed のネイティブ埋め込みを維持するプロバイダーかどうかを判定する。
 *
 * @param string $url  oEmbed 元 URL。
 * @param object $data oEmbed レスポンスデータ。
 * @return bool
 */
function node_is_excluded_oembed_provider( string $url, object $data ): bool {
	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );

	foreach ( array( 'twitter.com', 'x.com', 'youtube.com', 'youtu.be' ) as $domain ) {
		if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
			return true;
		}
	}

	$provider_name = strtolower( (string) ( $data->provider_name ?? '' ) );
	return str_contains( $provider_name, 'twitter' ) || str_contains( $provider_name, 'youtube' );
}

/**
 * 生成済みカード HTML を後段（wpautop の後）で差し込むためのプレースホルダに置き換える。
 *
 * autoembed 由来（oembed_dataparse / embed_maybe_make_link）のカードは `the_content` 優先度8、
 * つまり wpautop(優先度10) より前に挿入される。カードは <a> がブロック <div> を内包する構造の
 * ため、wpautop に通すと <p>/<br> が差し込まれて崩れる。そこでこの段階では base64 化した
 * プレースホルダ <div> のみを返し、実カード HTML は node_blogcard_hydrate() が wpautop 後
 * （優先度20）に復元する。
 *
 * 重要: カード HTML をトークンではなく base64 で「プレースホルダ自身」に埋め込む。WordPress は
 * oEmbed 結果（＝このプレースホルダ）をキャッシュするため、リクエスト固有トークン方式だと
 * キャッシュヒット時にトークンが失効し、hydrate が空に置換してカードが消えてしまう。自己完結型に
 * することで、キャッシュ済みプレースホルダでも別リクエストで確実に復元できる。base64 の文字集合は
 * [A-Za-z0-9+/=] のみで、改行も山括弧も無いため wpautop / texturize に破壊されない。
 *
 * @param string $html カード HTML。
 * @return string プレースホルダ HTML。
 */
function node_blogcard_defer( string $html ): string {
	if ( '' === $html ) {
		return '';
	}

	return '<div class="node-blogcard-slot">' . base64_encode( $html ) . '</div>';
}

/**
 * wpautop 後にプレースホルダを実カード HTML へ復元する。
 *
 * @param string $content 投稿本文。
 * @return string
 */
function node_blogcard_hydrate( string $content ): string {
	if ( ! str_contains( $content, 'node-blogcard-slot' ) ) {
		return $content;
	}

	return (string) preg_replace_callback(
		'#<div class="node-blogcard-slot">([A-Za-z0-9+/=]+)</div>#',
		static function ( array $m ): string {
			$decoded = base64_decode( $m[1], true );
			return false === $decoded ? '' : $decoded;
		},
		$content
	);
}

/**
 * X(Twitter) / Google マップ / YouTube 等、URL 貼り付け時に「カード」ではなく
 * 「埋め込み」を表示すべきプロバイダーの HTML を返す。該当しなければ空文字。
 *
 * WordPress 標準の oEmbed が機能しないケース（X は API 廃止で oEmbed 不可、x.com と
 * Google マップは未登録プロバイダー）を、テーマ側で明示的に埋め込みへ変換する。
 *
 * @param string $url 貼り付けられた URL。
 * @return string 埋め込み HTML（該当しなければ空文字）。
 */
function node_special_embed( string $url ): string {
	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
	$host = (string) preg_replace( '/^www\./', '', $host );
	$path = (string) parse_url( $url, PHP_URL_PATH );

	// --- X (Twitter): 公式 blockquote 埋め込み（oEmbed API 不要で常に動作） ---
	if ( in_array( $host, array( 'twitter.com', 'x.com', 'mobile.twitter.com' ), true )
		&& preg_match( '#/status(?:es)?/\d+#', $path ) ) {
		node_mark_twitter_widgets();
		return '<div class="node-embed node-embed--x"><blockquote class="twitter-tweet" data-dnt="true"><a href="' . esc_url( $url ) . '"></a></blockquote></div>';
	}

	// --- YouTube: youtu.be / shorts 等、標準 oEmbed が拾えない形式のフォールバック ---
	$youtube_id = node_youtube_video_id( $url, $host, $path );
	if ( '' !== $youtube_id ) {
		return '<div class="node-embed node-embed--video"><iframe src="https://www.youtube.com/embed/' . rawurlencode( $youtube_id ) . '" title="YouTube video player" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';
	}

	// --- Google マップ: output=embed の iframe（API キー不要） ---
	$map_embed = node_maps_embed_html( $url );
	if ( '' !== $map_embed ) {
		return $map_embed;
	}

	return '';
}

/**
 * URL が Google マップ系（本体・短縮）かどうかを判定する。
 *
 * @param string $url 判定対象 URL。
 * @return bool
 */
function node_is_google_maps_url( string $url ): bool {
	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
	$host = (string) preg_replace( '/^www\./', '', $host );
	$path = (string) parse_url( $url, PHP_URL_PATH );

	if ( in_array( $host, array( 'maps.app.goo.gl', 'goo.gl' ), true ) ) {
		return true;
	}

	// maps.google.* はサブドメイン自体がマップなのでパス不問。google.com は /maps 配下のみ。
	if ( str_starts_with( $host, 'maps.google.' ) ) {
		return true;
	}

	return str_ends_with( $host, 'google.com' ) && str_contains( $path, '/maps' );
}

/**
 * Google マップ URL をレスポンシブ埋め込み iframe HTML に変換する。
 *
 * @param string $url Google マップ URL。
 * @return string 埋め込み HTML（変換できなければ空文字）。
 */
function node_maps_embed_html( string $url ): string {
	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
	$host = (string) preg_replace( '/^www\./', '', $host );
	$path = (string) parse_url( $url, PHP_URL_PATH );

	$map_src = node_google_maps_embed_src( $url, $host, $path );
	if ( '' === $map_src ) {
		return '';
	}

	return '<div class="node-embed node-embed--map"><iframe src="' . esc_url( $map_src ) . '" title="Google Maps" frameborder="0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
}

/**
 * URL から YouTube 動画 ID を抽出する（youtu.be / watch / shorts / embed 対応）。
 *
 * @param string $url  元 URL。
 * @param string $host www を除いたホスト。
 * @param string $path パス。
 * @return string 動画 ID（該当しなければ空文字）。
 */
function node_youtube_video_id( string $url, string $host, string $path ): string {
	if ( 'youtu.be' === $host ) {
		return (string) preg_replace( '#^/([\w-]{6,})\b.*#', '$1', $path );
	}

	if ( in_array( $host, array( 'youtube.com', 'm.youtube.com' ), true ) ) {
		if ( preg_match( '#^/(?:shorts|embed|v)/([\w-]{6,})#', $path, $m ) ) {
			return $m[1];
		}
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $params );
		if ( ! empty( $params['v'] ) && preg_match( '/^[\w-]{6,}$/', (string) $params['v'] ) ) {
			return (string) $params['v'];
		}
	}

	return '';
}

/**
 * Google マップ URL から output=embed 版の iframe src を組み立てる。
 *
 * 短縮 URL（maps.app.goo.gl / goo.gl）はリダイレクト解決してから座標・地名を抽出する。
 * `.../maps?q=...&output=embed` はブラウザ側で frameable な `/maps/embed?pb=...` へ
 * 301 転送されるため、そのまま iframe に載せて表示できる。
 *
 * @param string $url  元 URL。
 * @param string $host www を除いたホスト。
 * @param string $path パス。
 * @return string 埋め込み用 src（該当しなければ空文字）。
 */
function node_google_maps_embed_src( string $url, string $host, string $path ): string {
	$is_short = in_array( $host, array( 'maps.app.goo.gl', 'goo.gl' ), true );
	$is_maps  = ( str_ends_with( $host, 'google.com' ) || str_starts_with( $host, 'maps.google.' ) )
		&& ( str_starts_with( $path, '/maps' ) || '' !== (string) parse_url( $url, PHP_URL_QUERY ) );

	if ( ! $is_short && ! $is_maps ) {
		return '';
	}

	if ( $is_short ) {
		$resolved = node_resolve_redirect( $url );
		if ( '' === $resolved ) {
			return '';
		}
		$url  = $resolved;
		$path = (string) parse_url( $url, PHP_URL_PATH );
	}

	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $params );

	$query = '';
	$zoom  = '15';

	// /place/NAME/@lat,lng,zoom
	if ( preg_match( '#/place/([^/@]+)#', $path, $m ) ) {
		$query = trim( rawurldecode( str_replace( '+', ' ', $m[1] ) ) );
	}

	// @lat,lng,zoomz（座標・ズーム）
	if ( preg_match( '#@(-?\d+\.\d+),(-?\d+\.\d+)(?:,(\d+(?:\.\d+)?)z)?#', $url, $mm ) ) {
		if ( '' === $query ) {
			$query = $mm[1] . ',' . $mm[2];
		}
		if ( ! empty( $mm[3] ) ) {
			$zoom = (string) (int) $mm[3];
		}
	}

	if ( '' === $query && ! empty( $params['q'] ) ) {
		$query = (string) $params['q'];
	}
	if ( '' === $query && ! empty( $params['ll'] ) ) {
		$query = (string) $params['ll'];
	}

	if ( '' === $query ) {
		return '';
	}

	return add_query_arg(
		array(
			'q'      => rawurlencode( $query ),
			'z'      => $zoom,
			'hl'     => 'ja',
			'output' => 'embed',
		),
		'https://maps.google.com/maps'
	);
}

/**
 * URL のリダイレクト先（Location ヘッダ）を1週間キャッシュ付きで解決する。
 *
 * @param string $url 短縮 URL 等。
 * @return string 解決先 URL（取得できなければ空文字）。
 */
function node_resolve_redirect( string $url ): string {
	$key    = 'node_redir_' . md5( $url );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return (string) $cached;
	}

	$response = wp_safe_remote_head(
		$url,
		array(
			'timeout'     => 8,
			'redirection' => 0,
			// sslverify は既定(true)を維持する（絶対原則: 検証無効化の新規追加は禁止）
		)
	);

	$location = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'location' );
	set_transient( $key, $location, WEEK_IN_SECONDS );

	return $location;
}

/**
 * X(Twitter) の widgets.js をこのリクエストのフッターで一度だけ読み込むフラグを立てる。
 */
function node_mark_twitter_widgets(): void {
	$GLOBALS['node_needs_twitter_widgets'] = true;
}

/**
 * ツイート埋め込みがある場合のみ widgets.js をフッターへ出力する。
 */
function node_print_twitter_widgets(): void {
	if ( ! empty( $GLOBALS['node_needs_twitter_widgets'] ) ) {
		echo '<script async src="https://platform.x.com/widgets.js" charset="utf-8"></script>' . "\n";
	}
}

/**
 * oEmbed 成功時の HTML を Node ブログカードへ差し替える。
 *
 * X(Twitter) と YouTube は WordPress 標準のネイティブ埋め込みを維持する。
 *
 * @param string $return WordPress 標準が生成した HTML。
 * @param object $data   oEmbed レスポンスデータ。
 * @param string $url    oEmbed 元 URL。
 * @return string
 */
function node_oembed_dataparse( string $return, object $data, string $url ): string {
	if ( node_is_excluded_oembed_provider( $url, $data ) ) {
		return $return;
	}

	if ( node_is_internal_url( $url ) ) {
		$ogp = node_get_ogp_data( $url );
		if ( ! $ogp ) {
			return $return;
		}

		$card = node_blogcard_markup( $ogp, $url );
		return '' !== $card ? node_blogcard_defer( $card ) : $return;
	}

	$host = (string) parse_url( $url, PHP_URL_HOST );
	$ogp  = array(
		'title'       => (string) ( $data->title ?? $url ),
		'description' => '',
		'image'       => (string) ( $data->thumbnail_url ?? '' ),
		'favicon'     => 'https://www.google.com/s2/favicons?domain=' . rawurlencode( $host ) . '&sz=64',
		'site_name'   => (string) ( $data->provider_name ?? $host ),
		'is_internal' => false,
	);

	if ( '' === $ogp['title'] ) {
		$ogp['title'] = $url;
	}

	if ( '' === $ogp['site_name'] ) {
		$ogp['site_name'] = $host;
	}

	$card = node_blogcard_markup( $ogp, $url );
	return '' !== $card ? node_blogcard_defer( $card ) : $return;
}

/**
 * oEmbed 失敗時の通常リンクを Node ブログカードへ差し替える。
 *
 * @param string $output WordPress 標準が生成したリンク HTML。
 * @param string $url    元 URL。
 * @return string
 */
function node_embed_maybe_make_link( string $output, string $url ): string {
	// X / Google マップ / YouTube 等は「カード」ではなく「埋め込み」を優先。
	$embed = node_special_embed( $url );
	if ( '' !== $embed ) {
		return node_blogcard_defer( $embed );
	}

	$card = node_render_blogcard( $url );
	return '' !== $card ? node_blogcard_defer( $card ) : $output;
}

/**
 * ブログカードショートコード
 *
 * @param array<string, string>|string $atts ショートコード属性。
 * @return string
 */
function node_blogcard_shortcode( $atts ): string {
	$atts = shortcode_atts( array( 'url' => '' ), $atts, 'blogcard' );
	return node_render_blogcard( (string) $atts['url'], true );
}

/**
 * 本文中の URL 単独行をブログカードへ自動変換
 *
 * @param string $content 投稿本文。
 * @return string
 */
function node_auto_blogcard( string $content ): string {
	return $content;
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

/**
 * Google マップを oEmbed プロバイダーとして登録する。
 *
 * Google マップは公式 oEmbed を持たないため、ブロックエディタでは URL を貼っても
 * 埋め込みブロックにできない。テーマ内に自前の oEmbed エンドポイント
 * （/wp-json/node/v1/maps-oembed）を用意し、それを指すプロバイダーを登録することで、
 * エディタでもフロントでも「埋め込みブロック」として扱えるようにする
 * （X・YouTube は WordPress 標準の oEmbed / ディスカバリで既にブロック化できる）。
 */
function node_register_map_oembed_provider(): void {
	$endpoint = home_url( '/wp-json/node/v1/maps-oembed' );
	wp_oembed_add_provider( '#https?://(www\.)?google\.[a-z.]+/maps/.*#i', $endpoint, true );
	wp_oembed_add_provider( '#https?://maps\.google\.[a-z.]+/.*#i', $endpoint, true );
	wp_oembed_add_provider( '#https?://maps\.app\.goo\.gl/.*#i', $endpoint, true );
	wp_oembed_add_provider( '#https?://goo\.gl/maps/.*#i', $endpoint, true );
}

/**
 * 自前 Google マップ oEmbed エンドポイントを登録する。
 */
function node_register_maps_oembed_route(): void {
	register_rest_route(
		'node/v1',
		'/maps-oembed',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'node_maps_oembed_response',
			'args'                => array(
				'url' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);
}

/**
 * Google マップ oEmbed エンドポイントのレスポンス（rich タイプ）。
 *
 * @param WP_REST_Request $request REST リクエスト。
 * @return array<string, mixed>|WP_Error
 */
function node_maps_oembed_response( WP_REST_Request $request ) {
	$url = esc_url_raw( (string) $request->get_param( 'url' ) );

	if ( ! node_is_google_maps_url( $url ) ) {
		return new WP_Error( 'node_not_maps', 'Not a Google Maps URL', array( 'status' => 404 ) );
	}

	$html = node_maps_embed_html( $url );
	if ( '' === $html ) {
		return new WP_Error( 'node_no_embed', 'Could not build a Google Maps embed', array( 'status' => 404 ) );
	}

	return array(
		'version'       => '1.0',
		'type'          => 'rich',
		'provider_name' => 'Google Maps',
		'provider_url'  => 'https://www.google.com/maps',
		'width'         => 800,
		'height'        => 450,
		'html'          => $html,
	);
}

/**
 * フロント側の oEmbed 取得（get_html）を Google マップだけ短絡して自前 iframe を返す。
 *
 * プロバイダー経由のループバック取得を避けつつ、WordPress の oEmbed サニタイズで
 * iframe が改変されるのも防ぐ。エディタ側（get_data）は登録プロバイダーを使う。
 *
 * @param string|null          $result 既存の結果。
 * @param string               $url    対象 URL。
 * @param array<string, mixed> $args   引数。
 * @return string|null
 */
function node_pre_oembed_maps_result( $result, $url, $args ) {
	unset( $args );
	if ( null === $result && node_is_google_maps_url( (string) $url ) ) {
		$html = node_maps_embed_html( (string) $url );
		if ( '' !== $html ) {
			return $html;
		}
	}
	return $result;
}

/**
 * 自サイト URL の oEmbed 取得を短絡し、内部カードを直接返す。
 *
 * 自サイト埋め込みは WordPress の自己 oEmbed（ループバック HTTP 要求）に依存し、要求の
 * 成否・タイミングでカードが出たり出なかったりする。ここで url_to_postid ベースに短絡して
 * ループバックを完全に排除し、内部カードを毎回確実に生成する。node_blogcard_defer() で
 * base64 プレースホルダ化しているため、キャッシュ後の別リクエストでも復元できる。
 *
 * @param string|null          $result 既存の結果。
 * @param string               $url    対象 URL。
 * @param array<string, mixed> $args   引数。
 * @return string|null
 */
function node_pre_oembed_internal_result( $result, $url, $args ) {
	unset( $args );
	if ( null !== $result || ! node_is_internal_url( (string) $url ) || ! node_internal_url_to_postid( (string) $url ) ) {
		return $result;
	}

	$card = node_render_blogcard( (string) $url );
	if ( '' === $card || str_contains( $card, 'm3-blogcard__fallback' ) ) {
		return $result;
	}

	return node_blogcard_defer( $card );
}

add_shortcode( 'blogcard', 'node_blogcard_shortcode' );
add_action( 'init', 'node_register_map_oembed_provider' );
add_action( 'rest_api_init', 'node_register_maps_oembed_route' );
add_filter( 'pre_oembed_result', 'node_pre_oembed_internal_result', 10, 3 );
add_filter( 'pre_oembed_result', 'node_pre_oembed_maps_result', 10, 3 );
add_filter( 'oembed_dataparse', 'node_oembed_dataparse', 10, 3 );
add_filter( 'embed_maybe_make_link', 'node_embed_maybe_make_link', 10, 2 );
add_filter( 'the_content', 'node_auto_blogcard', 11 );
// wpautop(10) の後にプレースホルダを実カード/埋め込みへ復元する。autoembed(8) 由来の
// 破壊を回避するため、優先度は必ず 10 より大きくすること。
// 21固定: lightbox(20)より必ず後に走らせ、カード内画像がlightboxリンクで包まれるのを防ぐ
// （従来はrequire順でのみ保たれていた脆い不変条件の明示化。STRUCTURAL-REVIEW-1.2 F-8）
add_filter( 'the_content', 'node_blogcard_hydrate', 21 );
add_action( 'wp_footer', 'node_print_twitter_widgets' );
