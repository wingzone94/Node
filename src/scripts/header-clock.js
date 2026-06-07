export function initHeaderClock() {
    const clock = document.getElementById('m3-header-clock');
    if (!clock) return;

    const update = () => {
        const now = new Date();
        const greetingEl = document.getElementById('m3-header-greeting');
        const dateEl = document.getElementById('m3-header-date');
        const timeEl = document.getElementById('m3-header-time');

        if (greetingEl) {
            const hour = now.getHours();
            let greeting = 'Hello';
            if (hour < 5) greeting = 'Good night';
            else if (hour < 12) greeting = 'Good morning';
            else if (hour < 18) greeting = 'Good afternoon';
            else greeting = 'Good evening';
            greetingEl.textContent = greeting;
        }

        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            });
        }

        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }

        const isHomePage = document.body.classList.contains('home') || document.body.classList.contains('front-page');
        if (!isHomePage) {
            setTimeout(() => {
                if (typeof gsap !== 'undefined') {
                    gsap.to(clock, {
                        opacity: 0,
                        y: 10,
                        duration: 1.5,
                        ease: 'power3.inOut',
                        onComplete: () => {
                            clock.style.display = 'none';
                        }
                    });
                } else {
                    clock.style.display = 'none';
                }
            }, 5000);
        }
    };

    setInterval(update, 1000);
    update();
}
