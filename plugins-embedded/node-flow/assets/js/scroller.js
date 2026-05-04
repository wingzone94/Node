/**
 * Node Flow - Hybrid Scroller
 * 無限スクロールとプログレッシブ・エンハンスメントの両立
 */

document.addEventListener('DOMContentLoaded', function() {
    if (typeof nodeFlowSettings === 'undefined') {
        return;
    }

    // 記事コンテナと標準のページネーション要素を取得
    // テーマ (Node) の構造に依存（.m3-post-grid__container）
    const container = document.querySelector('.m3-post-grid__container:last-of-type');
    const pagination = document.querySelector('.m3-navigation');
    const archivePill = document.querySelector('.m3-archive-pill-wrapper');

    if (!container) return;

    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;

    // 【プログレッシブ・エンハンスメント】
    // JSが実行されたので、標準のページネーションリンクやアーカイブボタンを非表示にする
    if (pagination) pagination.style.display = 'none';
    if (archivePill) archivePill.style.display = 'none';

    // トリガー（ローディングUI）要素を作成してコンテナの後ろに配置
    const triggerEl = document.createElement('div');
    triggerEl.className = 'node-flow-loader';
    triggerEl.innerHTML = '<span class="material-symbols-outlined" style="animation: spin 1s linear infinite;">autorenew</span> 読み込み中...';
    triggerEl.style.textAlign = 'center';
    triggerEl.style.padding = '2rem 0';
    triggerEl.style.color = 'var(--md-sys-color-outline, #857362)';
    
    // スタイル定義 (spinアニメーション)
    if (!document.getElementById('node-flow-styles')) {
        const style = document.createElement('style');
        style.id = 'node-flow-styles';
        style.innerHTML = '@keyframes spin { 100% { transform: rotate(360deg); } }';
        document.head.appendChild(style);
    }

    container.parentNode.insertBefore(triggerEl, container.nextSibling);

    // REST API で次のページを読み込む関数
    async function loadNextPage() {
        if (isLoading || !hasMore) return;
        isLoading = true;
        currentPage++;

        try {
            // クエリパラメータの構築
            const params = new URLSearchParams();
            params.append('page', currentPage);
            
            if (nodeFlowSettings.query) {
                // オブジェクトをパラメータに変換
                for (const key in nodeFlowSettings.query) {
                    if (nodeFlowSettings.query[key]) {
                        // 配列やオブジェクトの場合への簡易対応として JSON.stringify もありだが、
                        // 基本的にはスカラー値が渡ってくる想定
                        let val = nodeFlowSettings.query[key];
                        if (typeof val === 'object') {
                            val = JSON.stringify(val);
                        }
                        params.append(`query[${key}]`, val);
                    }
                }
            }

            const response = await fetch(`${nodeFlowSettings.restUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': nodeFlowSettings.nonce,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json();

            if (data.html) {
                // DOMパーサーを使って安全に要素を追加する
                const parser = new DOMParser();
                const doc = parser.parseFromString(data.html, 'text/html');
                const newItems = doc.body.childNodes;
                
                // 追加される要素をループでappendChild
                Array.from(newItems).forEach(item => {
                    container.appendChild(item.cloneNode(true));
                });
            }

            hasMore = data.hasMore;

            if (!hasMore) {
                triggerEl.style.display = 'none';
                observer.disconnect();
            }
        } catch (error) {
            console.error('Node Flow: Error loading posts.', error);
            triggerEl.innerHTML = '読み込みに失敗しました。ページをリロードしてください。';
        } finally {
            isLoading = false;
        }
    }

    // Intersection Observer の設定
    const observerOptions = {
        root: null,
        rootMargin: '200px', // トリガーの200px手前で発火
        threshold: 0
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                loadNextPage();
            }
        });
    }, observerOptions);

    // トリガー要素の監視を開始
    observer.observe(triggerEl);
});
