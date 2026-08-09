<?php
/**
 * Luna Frontier 2.0 / SkyAlow Prototype — bootstrap
 *
 * 親テーマ: Node 1.3（Template: node）
 *
 * 原則:
 * - 親テーマのファイル・assets を書き換えない。差分だけをここから足す。
 * - 親の内部データ（_node_* / node_* / lc_* 等）は rename しない。互換のまま読む。
 * - Node 1.3 へ不可逆な DB migration を行わない（管理画面で戻すだけで復帰できること）。
 *
 * @package LunaFrontier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUNA_FRONTIER_DIR', get_stylesheet_directory() );
define( 'LUNA_FRONTIER_URI', get_stylesheet_directory_uri() );
define( 'LUNA_FRONTIER_CODENAME', 'SkyAlow' );

/**
 * 子テーマ自身のバージョン（style.css の Version）。
 * 親のバージョンは NODE_THEME_VERSION が保持する。
 */
function luna_frontier_version(): string {
	$version = wp_get_theme( get_stylesheet() )->get( 'Version' );

	return ( is_string( $version ) && '' !== $version ) ? $version : '0.0.0';
}

/**
 * ビルド成果物のパスを Vite manifest から解決する。
 *
 * @param string $entry manifest のキー（例: 'src/styles/luna.css'）。
 * @return array{file:string,css:string[]}|null
 */
function luna_frontier_manifest_entry( string $entry ): ?array {
	static $manifest = null;

	if ( null === $manifest ) {
		$path     = LUNA_FRONTIER_DIR . '/assets/.vite/manifest.json';
		$manifest = array();

		if ( is_readable( $path ) ) {
			$decoded = json_decode( (string) file_get_contents( $path ), true );
			if ( is_array( $decoded ) ) {
				$manifest = $decoded;
			}
		}
	}

	if ( ! isset( $manifest[ $entry ] ) || ! is_array( $manifest[ $entry ] ) ) {
		return null;
	}

	$file = $manifest[ $entry ]['file'] ?? '';
	if ( ! is_string( $file ) || '' === $file ) {
		return null;
	}

	$css = $manifest[ $entry ]['css'] ?? array();

	return array(
		'file' => $file,
		'css'  => is_array( $css ) ? $css : array(),
	);
}

/**
 * ファイル更新時刻をバージョン文字列に使う（ハッシュなしビルドのキャッシュ対策）。
 */
function luna_frontier_asset_version( string $relative_file ): string {
	$absolute = LUNA_FRONTIER_DIR . '/assets/' . ltrim( $relative_file, '/' );
	$mtime    = is_readable( $absolute ) ? filemtime( $absolute ) : false;

	return false !== $mtime ? luna_frontier_version() . '.' . $mtime : luna_frontier_version();
}

/**
 * 2.0 固有アセットの読み込み。
 *
 * 親テーマは NodeTheme\Setup\ThemeSupport::enqueueAssets() を優先度 10 で登録するため、
 * こちらは 20 で走らせて必ず親の後に載せる（= 低い specificity でも上書きが効く）。
 */
function luna_frontier_enqueue_assets(): void {
	// Manrope / Inter / Noto Sans JP は親 header.php が読み込み済み。
	// Luna Frontier が追加で必要なのは Orbitron（短い機能ラベル専用）だけ。
	// 用途が限られるため 600 の 1 ウェイトに絞る（§50: 必要な Font Weight だけ）。
	wp_enqueue_style(
		'luna-frontier-orbitron',
		'https://fonts.googleapis.com/css2?family=Orbitron:wght@600&display=swap',
		array(),
		null
	);

	$style = luna_frontier_manifest_entry( 'src/styles/luna.css' );

	if ( null !== $style ) {
		// CSS entry は Vite の版により 'file' 直接／'css' 配列経由のどちらにもなり得る。
		$style_file = $style['css'][0] ?? $style['file'];

		if ( '.css' === substr( $style_file, -4 ) ) {
			wp_enqueue_style(
				'luna-frontier',
				LUNA_FRONTIER_URI . '/assets/' . $style_file,
				array(),
				luna_frontier_asset_version( $style_file )
			);
		}
	}

	$script = luna_frontier_manifest_entry( 'src/luna.js' );

	if ( null !== $script ) {
		wp_enqueue_script(
			'luna-frontier',
			LUNA_FRONTIER_URI . '/assets/' . $script['file'],
			array(),
			luna_frontier_asset_version( $script['file'] ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'luna_frontier_enqueue_assets', 20 );

/**
 * 子テーマの script を type="module" で配信する（Vite 出力は ESM）。
 */
function luna_frontier_script_module_type( string $tag, string $handle ): string {
	if ( 'luna-frontier' !== $handle ) {
		return $tag;
	}

	if ( false !== strpos( $tag, ' type=' ) ) {
		return str_replace( ' type=\'text/javascript\'', ' type=\'module\'', str_replace( ' type="text/javascript"', ' type="module"', $tag ) );
	}

	return str_replace( '<script ', '<script type="module" ', $tag );
}
add_filter( 'script_loader_tag', 'luna_frontier_script_module_type', 10, 2 );

/**
 * body_class に Luna Frontier の識別子を足す。
 * 親テーマ由来のクラスは一切外さない。
 *
 * @param string[] $classes body class 一覧。
 * @return string[]
 */
function luna_frontier_body_class( array $classes ): array {
	$classes[] = 'lf-theme';

	return $classes;
}
add_filter( 'body_class', 'luna_frontier_body_class' );
