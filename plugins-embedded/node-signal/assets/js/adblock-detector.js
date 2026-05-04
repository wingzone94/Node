/**
 * Node Signal - AdBlock Detector
 * 広告ブロッカーの検知とモーダル制御
 */

document.addEventListener('DOMContentLoaded', function() {
    // 信頼性の高い検知方法: 一般的な広告スクリプトのURLへHEADリクエストを送る
    const adUrl = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';
    
    fetch(adUrl, { method: 'HEAD', mode: 'no-cors' })
        .then(() => {
            // リクエストが成功した場合はブロックされていない
            // 追加の念押しとしてDOMベースのチェックも行う
            checkDomBlock();
        })
        .catch(() => {
            // fetchが失敗（ブロックされた）場合
            showAdBlockModal();
        });

    function checkDomBlock() {
        var dummyAd = document.createElement('div');
        dummyAd.innerHTML = '&nbsp;';
        dummyAd.className = 'ad-banner adsbox doubleclick ad-placement';
        dummyAd.style.position = 'absolute';
        dummyAd.style.top = '-999px';
        dummyAd.style.left = '-999px';
        document.body.appendChild(dummyAd);

        setTimeout(function() {
            var isBlocked = false;
            if (dummyAd.offsetHeight === 0 || dummyAd.style.display === 'none') {
                isBlocked = true;
            }
            document.body.removeChild(dummyAd);

            if (isBlocked) {
                showAdBlockModal();
            }
        }, 300);
    }

    // モーダルの表示とカウントダウン制御
    function showAdBlockModal() {
        var modal = document.getElementById('ns-adblock-modal');
        var countdownEl = document.getElementById('ns-countdown-timer');
        var btnAllowAds = document.getElementById('ns-btn-allow-ads');
        var btnContinue = document.getElementById('ns-btn-continue-without-ads');
        var progressCircle = document.querySelector('.ns-adblock-progress-ring__circle');
        
        if (!modal) return;

        // モーダルを表示
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden'; // 背面のスクロールを禁止

        var timeLeft = 15;
        var totalTime = 15;
        var circumference = 2 * Math.PI * 44; // 2 * PI * r
        
        if (progressCircle) {
            progressCircle.style.strokeDasharray = `${circumference} ${circumference}`;
            progressCircle.style.strokeDashoffset = 0;
            progressCircle.style.transition = 'stroke-dashoffset 1s linear';
        }

        var timerId = setInterval(function() {
            timeLeft--;
            
            if (countdownEl) {
                countdownEl.textContent = timeLeft;
            }

            if (progressCircle) {
                const offset = circumference - (timeLeft / totalTime) * circumference;
                progressCircle.style.strokeDashoffset = offset;
            }

            if (timeLeft <= 0) {
                clearInterval(timerId);
                
                // 15秒経過で「広告なしで続ける」を有効化し、自動的に適用（モーダルを閉じる）
                if (btnContinue) {
                    btnContinue.disabled = false;
                    btnContinue.classList.remove('is-disabled');
                    btnContinue.click(); // 自動適用
                }
            }
        }, 1000);

        // ボタンのイベント
        if (btnAllowAds) {
            btnAllowAds.addEventListener('click', function() {
                // 広告を表示して読み進める（リロードまたはホワイトリスト追加のお願い）
                window.location.reload();
            });
        }

        if (btnContinue) {
            btnContinue.addEventListener('click', function() {
                if (btnContinue.disabled) return;
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = ''; // スクロール禁止を解除
            });
        }
    }
});
