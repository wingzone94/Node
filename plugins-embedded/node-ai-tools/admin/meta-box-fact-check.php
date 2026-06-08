<?php
/**
 * ファクトチェックメタボックス UI
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param WP_Post $post 投稿オブジェクト。
 */
function node_ai_render_fact_check_meta_box( WP_Post $post ): void {
	$data = node_ai_get_fact_check_data( $post->ID );
	$approved = (bool) get_post_meta( $post->ID, '_node_ai_fact_check_approved', true );

	wp_nonce_field( 'node_ai_fact_check_action', 'node_ai_fact_check_nonce' );
	?>
	<div class="node-fact-check-meta-box">
		<p class="description">
			Gemini API + Google Search Grounding + Luminous Core ガイドライン（Google ドキュメント）によるファクトチェック補助です。
			「編集者が結果を確認済み」にチェックを入れると、記事ページ（1ページ目）に公開表示されます。
		</p>

		<div id="node_fact_check_results">
			<?php
			if ( null !== $data ) {
				node_ai_render_fact_check_results( $data );
			} else {
				echo '<p class="node-fact-check__empty">まだファクトチェックは実行されていません。</p>';
			}
			?>
		</div>

		<p>
			<label>
				<input type="checkbox" name="node_ai_fact_check_approved" value="1" <?php checked( $approved ); ?> />
				編集者が結果を確認済み（フロント公開）
			</label>
		</p>

		<p>
			<button type="button" id="node_fact_check_btn" class="button button-secondary" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
				ファクトチェックを実行
			</button>
			<span id="node_fact_check_status" style="margin-left:10px;font-weight:bold;"></span>
		</p>
	</div>

	<style>
		.node-fact-check-meta-box .node-fact-check__claims,
		.node-fact-check-meta-box .node-fact-check__sources-list {
			margin: 12px 0 0;
			padding: 0;
			list-style: none;
		}
		.node-fact-check-meta-box .node-fact-check__sources-list { list-style: disc; padding-left: 1.2rem; }
		.node-fact-check-meta-box .node-fact-check__claim {
			margin: 0 0 10px;
			padding: 12px;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			background: #fff;
		}
		.node-fact-check-meta-box .node-fact-check__claim--likely_correct { border-left: 4px solid #00a32a; }
		.node-fact-check-meta-box .node-fact-check__claim--uncertain { border-left: 4px solid #dba617; }
		.node-fact-check-meta-box .node-fact-check__claim--likely_incorrect { border-left: 4px solid #d63638; }
		.node-fact-check-meta-box .node-fact-check__claim--unverifiable { border-left: 4px solid #787c82; }
		.node-fact-check-meta-box .node-fact-check__claim-text { margin: 0 0 6px; font-weight: 600; }
		.node-fact-check-meta-box .node-fact-check__claim-meta { margin: 0; font-size: 12px; color: #50575e; }
		.node-fact-check-meta-box .node-fact-check__claim-note { margin: 6px 0 0; font-size: 13px; }
		.node-fact-check-meta-box .node-fact-check__risk-badge {
			display: inline-block;
			padding: 2px 8px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
		}
		.node-fact-check-meta-box .node-fact-check__risk-badge--low { background: #d5f5e3; color: #1e4620; }
		.node-fact-check-meta-box .node-fact-check__risk-badge--medium { background: #fcf0cd; color: #614200; }
		.node-fact-check-meta-box .node-fact-check__risk-badge--high { background: #fce4e4; color: #611418; }
		.node-fact-check-meta-box .node-fact-check__grounded { color: #2271b1; }
	</style>

	<script>
	jQuery(function($) {
		var statusLabels = <?php echo wp_json_encode( node_ai_fact_check_status_labels(), JSON_UNESCAPED_UNICODE ); ?>;
		var riskLabels = <?php echo wp_json_encode( node_ai_fact_check_risk_labels(), JSON_UNESCAPED_UNICODE ); ?>;

		function renderSources(sources) {
			if (!sources || !sources.length) return '';
			var html = '<div class="node-fact-check__sources"><p><strong>参照元 (Google Search)</strong></p><ul class="node-fact-check__sources-list">';
			sources.forEach(function(source) {
				var title = source.title || source.url;
				html += '<li><a href="' + $('<div>').text(source.url).html() + '" target="_blank" rel="noopener noreferrer">' + $('<div>').text(title).html() + '</a></li>';
			});
			html += '</ul></div>';
			return html;
		}

		function renderResults(data) {
			var html = '<div class="node-fact-check__results">';
			html += '<p class="node-fact-check__summary"><strong>全体所見:</strong> ' + $('<div>').text(data.summary || '').html() + '</p>';
			var risk = data.overall_risk || 'medium';
			html += '<p class="node-fact-check__risk"><strong>リスク:</strong> <span class="node-fact-check__risk-badge node-fact-check__risk-badge--' + risk + '">' + (riskLabels[risk] || risk) + '</span></p>';
			if (data.grounded) {
				html += '<p class="node-fact-check__grounded"><small>Google Search Grounding 有効</small></p>';
			}
			if (data.guidelines_used) {
				html += '<p class="node-fact-check__grounded"><small>Luminous Core ガイドライン参照済み</small></p>';
			}
			if (data.checked_at) {
				html += '<p class="node-fact-check__meta"><small>最終チェック: ' + $('<div>').text(data.checked_at).html() + '</small></p>';
			}
			html += '<ul class="node-fact-check__claims">';
			(data.claims || []).forEach(function(claim) {
				var status = claim.status || 'uncertain';
				html += '<li class="node-fact-check__claim node-fact-check__claim--' + status + '">';
				html += '<p class="node-fact-check__claim-text">' + $('<div>').text(claim.claim || '').html() + '</p>';
				html += '<p class="node-fact-check__claim-meta"><span class="node-fact-check__status">' + (statusLabels[status] || status) + '</span> / 確信度: ' + (claim.confidence || '') + '</p>';
				if (claim.note) {
					html += '<p class="node-fact-check__claim-note">' + $('<div>').text(claim.note).html() + '</p>';
				}
				html += '</li>';
			});
			html += '</ul>';
			html += renderSources(data.sources || []);
			html += '</div>';
			$('#node_fact_check_results').html(html);
			$('input[name="node_ai_fact_check_approved"]').prop('checked', false);
		}

		$('#node_fact_check_btn').on('click', function(e) {
			e.preventDefault();
			var btn = $(this);
			var status = $('#node_fact_check_status');
			btn.prop('disabled', true);
			status.text('チェック中...').css('color', '#FF9900');

			$.post(ajaxurl, {
				action: 'node_ai_fact_check',
				post_id: btn.data('post-id'),
				nonce: $('#node_ai_fact_check_nonce').val()
			}, function(response) {
				btn.prop('disabled', false);
				if (response.success) {
					renderResults(response.data);
					status.text('完了').css('color', 'green');
				} else {
					status.text('エラー: ' + (response.data && response.data.message ? response.data.message : '不明')).css('color', 'red');
				}
			}).fail(function() {
				btn.prop('disabled', false);
				status.text('通信エラーが発生しました。').css('color', 'red');
			});
		});
	});
	</script>
	<?php
}
