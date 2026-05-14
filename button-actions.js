document.addEventListener('DOMContentLoaded', () => {
    const backToTopBtn = document.getElementById('btn-back-to-top');
    const commentsBtn = document.getElementById('btn-comments');
    const aiBtn = document.getElementById('btn-ai');

    // 1. 最上部に戻るボタンの追従・表示制御
    if (backToTopBtn) {
        // スクロール時の処理
        window.addEventListener('scroll', () => {
            // 画面を200px以上スクロールしたらボタンを表示
            if (window.scrollY > 200) {
                backToTopBtn.classList.add('is-visible');
            } else {
                backToTopBtn.classList.remove('is-visible');
            }
        });

        // クリックで最上部へスムーズにスクロール
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // 2. コメントボタンの処理
    if (commentsBtn) {
        commentsBtn.addEventListener('click', () => {
            console.log('コメントアクションを実行');
            // ここにコメントエリアへの移動やモーダル展開の処理を記述
        });
    }

    // 3. AIボタンの処理（スマホではCSSで非表示になります）
    if (aiBtn) {
        aiBtn.addEventListener('click', () => {
            console.log('AIアクションを実行');
            // ここにAI機能の処理を記述
        });
    }
});
