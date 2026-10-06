<div id="cookie-banner" class="cookie" role="region" aria-label="Уведомление о cookie" hidden>
    <p>
        Мы используем только технические cookie, без которых не работает вход в кабинет.
        Рекламы и аналитики на сайте нет.
        <a href="{{ route('privacy') }}">Подробнее</a>
    </p>
    <button type="button" class="btn btn-primary btn-sm" id="cookie-ok">Понятно</button>
</div>

<script>
    (function () {
        var KEY = 'cookie-notice-ok';
        var banner = document.getElementById('cookie-banner');
        var seen = false;

        try { seen = localStorage.getItem(KEY) === '1'; } catch (e) {}
        if (seen) return;

        banner.hidden = false;
        document.getElementById('cookie-ok').addEventListener('click', function () {
            try { localStorage.setItem(KEY, '1'); } catch (e) {}
            banner.classList.add('out');
            setTimeout(function () { banner.hidden = true; }, 300);
        });
    })();
</script>
