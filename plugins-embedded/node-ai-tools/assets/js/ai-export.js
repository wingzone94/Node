/**
 * AI結果の持ち出し共通処理（コピー / .md ダウンロード）
 *
 * ファクトチェックと校正の両パネルから使う。
 *
 * @package Node_AI_Tools
 */
( function ( window ) {
	/**
	 * クリップボードへコピーする。
	 *
	 * Clipboard API はセキュアコンテキスト限定で、http のローカル検証環境では
	 * navigator.clipboard 自体が存在しない。そのため textarea + execCommand へ退避する。
	 *
	 * @param {string} text コピーする文字列。
	 * @return {Promise} 成否を解決する Promise。
	 */
	function copyText( text ) {
		if ( window.navigator.clipboard && window.navigator.clipboard.writeText ) {
			return window.navigator.clipboard.writeText( text );
		}

		return new window.Promise( function ( resolve, reject ) {
			var textarea = window.document.createElement( 'textarea' );

			textarea.value = text;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'fixed';
			textarea.style.top = '-1000px';
			textarea.style.opacity = '0';
			window.document.body.appendChild( textarea );
			textarea.select();

			var ok = false;
			try {
				ok = window.document.execCommand( 'copy' );
			} catch ( e ) {
				ok = false;
			}

			window.document.body.removeChild( textarea );

			if ( ok ) {
				resolve();
			} else {
				reject();
			}
		} );
	}

	/**
	 * Markdown を .md ファイルとしてダウンロードさせる。
	 *
	 * @param {string} markdown 本文。
	 * @param {string} filename 拡張子なしのファイル名。
	 */
	function downloadMarkdown( markdown, filename ) {
		var blob = new window.Blob( [ markdown ], {
			type: 'text/markdown;charset=utf-8',
		} );
		var url = window.URL.createObjectURL( blob );
		var link = window.document.createElement( 'a' );

		link.href = url;
		link.download = ( filename || 'node-ai' ) + '.md';
		window.document.body.appendChild( link );
		link.click();
		window.document.body.removeChild( link );
		window.URL.revokeObjectURL( url );
	}

	/**
	 * Markdown 表のセルを安全にする（改行とパイプが表を壊すため）。
	 *
	 * @param {*} value 値。
	 * @return {string} エスケープ済みの文字列。
	 */
	function cell( value ) {
		return String( value || '' )
			.replace( /\|/g, '\\|' )
			.replace( /\r?\n/g, ' ' );
	}

	window.nodeAiExport = {
		copyText: copyText,
		downloadMarkdown: downloadMarkdown,
		cell: cell,
	};
} )( window );
