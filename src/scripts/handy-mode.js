export function initHandyMode() {
    console.log('HandyMode: Initializing...');
    
    // 2. Comments Trigger
    const commentsBtn = document.getElementById('m3-bottom-comments-trigger');
    if (commentsBtn) {
        commentsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('HandyMode: Comments Clicked');
            const comments = document.getElementById('comments') || document.getElementById('respond');
            if (comments) {
                const headerOffset = 80; // Adjusted for mobile header
                const elementPosition = comments.getBoundingClientRect().top + window.pageYOffset;
                window.scrollTo({ 
                    top: elementPosition - headerOffset, 
                    behavior: 'smooth' 
                });
            }
        });
    }

    // 3. Back to Top Trigger
    const topBtn = document.getElementById('m3-back-to-top-handy');
    if (topBtn) {
        topBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('HandyMode: Top Clicked');
            window.scrollTo({ 
                top: 0, 
                behavior: 'smooth' 
            });
            
            // Fallback for some mobile browsers
            if (window.scrollY > 0) {
                setTimeout(() => {
                    if (window.scrollY > 0) {
                        window.scrollTo(0, 0);
                    }
                }, 500);
            }
        });
    }
}
