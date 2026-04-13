document.addEventListener('DOMContentLoaded', () => {
    // 1. ダークモード切り替え
    const themeToggleBtn = document.getElementById('theme-toggle');
    const setIcon = (theme) => {
        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        const lightIcon = document.getElementById('theme-toggle-light-icon');
        if (theme === 'dark') {
            if (darkIcon) darkIcon.classList.add('hidden');
            if (lightIcon) lightIcon.classList.remove('hidden');
        } else {
            if (lightIcon) lightIcon.classList.add('hidden');
            if (darkIcon) darkIcon.classList.remove('hidden');
        }
    };

    const currentTheme = localStorage.getItem('theme') || 
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', currentTheme);
    setIcon(currentTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            setIcon(theme);
        });
    }

    // 2. トースト通知システム
    function showToast(message, icon = 'check_circle') {
        let toast = document.querySelector('.m3-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'm3-toast';
            toast.innerHTML = `
                <span class="material-symbols-outlined m3-toast__icon"></span>
                <span class="m3-toast__text"></span>
            `;
            document.body.appendChild(toast);
        }
        
        toast.querySelector('.m3-toast__icon').textContent = icon;
        toast.querySelector('.m3-toast__text').textContent = message;
        toast.classList.remove('is-visible');
        void toast.offsetWidth; // Force reflow
        toast.classList.add('is-visible');
        
        setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 3000);
    }

    // 3. シェア機能 (URLコピー & アニメーション)
    const copyBtn = document.getElementById('m3-copy-trigger');
    if (copyBtn) {
        const copyIcon = copyBtn.querySelector('.m3-copy-icon');
        const copyLabel = copyBtn.querySelector('.m3-copy-label');

        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(window.location.href);
                
                // 成功状態へ遷移
                copyBtn.classList.add('is-success');
                if (copyIcon) copyIcon.textContent = 'content_paste'; // 貼り付けアイコン
                if (copyLabel) copyLabel.textContent = 'コピーしました'; 
                
                // ボタンのアニメーション
                copyBtn.style.transform = 'scale(0.9) translateY(0)';
                setTimeout(() => copyBtn.style.transform = '', 200);
                
                showToast('コピーしました', 'content_paste');

                // 数秒後に元に戻す
                setTimeout(() => {
                    copyBtn.classList.remove('is-success');
                    if (copyIcon) copyIcon.textContent = 'content_copy';
                    if (copyLabel) copyLabel.textContent = 'リンクをコピー';
                }, 3000);

            } catch (err) {
                showToast('コピーに失敗しました', 'error');
            }
        });
    }

    // 4. リッチテキストコメントエディタ
    const commentTextarea = document.getElementById('comment');
    const toolbar = document.querySelector('.comment-toolbar');

    if (commentTextarea && toolbar) {
        // contenteditableなラッパーを作成
        const editor = document.createElement('div');
        editor.className = 'm3-comment-editor';
        editor.contentEditable = true;
        editor.innerHTML = commentTextarea.value;
        commentTextarea.style.display = 'none';
        commentTextarea.parentNode.insertBefore(editor, commentTextarea.nextSibling);

        // 同期ロジック
        editor.addEventListener('input', () => {
            commentTextarea.value = editor.innerHTML;
        });

        // ツールバーボタンの処理
        toolbar.addEventListener('click', (e) => {
            const btn = e.target.closest('.toolbar-button');
            if (!btn) return;

            const tag = btn.dataset.tag;
            document.execCommand('styleWithCSS', false, false);

            if (tag === 'link') {
                const url = prompt('リンク先URLを入力してください:', 'https://');
                if (url) document.execCommand('createLink', false, url);
            } else {
                document.execCommand(tag, false, null);
            }
            editor.focus();
        });

        // キーボードショートカット
        editor.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key.toLowerCase()) {
                    case 'b': e.preventDefault(); document.execCommand('bold', false, null); break;
                    case 'i': e.preventDefault(); document.execCommand('italic', false, null); break;
                    case 'u': e.preventDefault(); document.execCommand('underline', false, null); break;
                    case 'k': e.preventDefault(); 
                        e.preventDefault();
                        const url = prompt('リンク先URLを入力してください:', 'https://');
                        if (url) document.execCommand('createLink', false, url);
                        break;
                }
            }
        });
    }

    // 5. 目次の開閉
    const stickyToc = document.querySelector('.m3-sticky-toc');
    if (stickyToc) {
        stickyToc.addEventListener('click', (e) => {
            if (!e.target.closest('a')) {
                stickyToc.classList.toggle('is-expanded');
            }
        });
    }

    // 6. 自動目次生成
    const articleBody = document.querySelector('.m3-article__body');
    const tocContainer = document.getElementById('m3-toc-container');
    const stickyNav = document.querySelector('.m3-sticky-navigation');

    if (articleBody && tocContainer) {
        const headings = articleBody.querySelectorAll('h2, h3');
        if (headings.length > 0) {
            const tocList = document.createElement('ul');
            headings.forEach((heading, index) => {
                const id = `heading-${index}`;
                heading.id = id;
                const li = document.createElement('li');
                li.className = `toc-level-${heading.tagName.toLowerCase()}`;
                const a = document.createElement('a');
                a.href = `#${id}`;
                a.textContent = heading.textContent;
                li.appendChild(a);
                tocList.appendChild(li);
            });
            tocContainer.appendChild(tocList);
            if (stickyNav) stickyNav.classList.remove('hidden');
        }
    }

    // 7. FAB表示制御
    const commentSection = document.getElementById('comments');
    const stickyComments = document.getElementById('m3-sticky-comments');

    window.addEventListener('scroll', () => {
        const scrollY = window.scrollY;
        const windowHeight = window.innerHeight;

        if (stickyComments && commentSection) {
            const rect = commentSection.getBoundingClientRect();
            if (rect.top < windowHeight - 100) {
                stickyComments.classList.remove('is-visible');
            } else if (scrollY > 600) {
                stickyComments.classList.add('is-visible');
            } else {
                stickyComments.classList.remove('is-visible');
            }
        }
    });
});