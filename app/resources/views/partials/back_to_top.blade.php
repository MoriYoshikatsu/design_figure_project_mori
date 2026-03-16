<style>
    .back-to-top-btn {
        position: fixed;
        right: 16px;
        bottom: 16px;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: 999px;
        background: #111827;
        color: #fff;
        font-size: 18px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
        opacity: 0;
        visibility: hidden;
        transform: translateY(8px);
        transition: opacity 0.16s ease, transform 0.16s ease, visibility 0.16s ease;
        z-index: 1000;
    }
    .back-to-top-btn.is-visible {
        opacity: 0.92;
        visibility: visible;
        transform: translateY(0);
    }
    .back-to-top-btn:hover {
        opacity: 1;
        background: #1f2937;
    }
    .back-to-top-btn:focus-visible {
        outline: 2px solid #2563eb;
        outline-offset: 2px;
    }
    @media (max-width: 640px) {
        .back-to-top-btn {
            right: 12px;
            bottom: 12px;
            width: 40px;
            height: 40px;
            font-size: 16px;
        }
    }
</style>

<button type="button" class="back-to-top-btn" data-back-to-top aria-label="ページの先頭へ戻る" title="ページの先頭へ戻る">↑</button>

<script>
    (() => {
        const button = document.querySelector('[data-back-to-top]');
        if (!button) {
            return;
        }

        const updateVisibility = () => {
            button.classList.toggle('is-visible', window.scrollY > 240);
        };

        button.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        window.addEventListener('scroll', updateVisibility, { passive: true });
        updateVisibility();
    })();
</script>
