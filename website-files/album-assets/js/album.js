/*
 * Devesh Studio – client album page
 * Top bar, scroll-in animations, petals, confetti, films and the photo gallery.
 * (The PDF flip book lives in flipbook.js.)
 */
(function () {
    'use strict';

    var A = window.ALBUM || {};
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = window.jQuery;

    function debounce(fn, wait) {
        var timer;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, wait);
        };
    }

    /* ---------------- top bar, back-to-top, active link ---------------- */
    var topbar = document.getElementById('topbar');
    var toTop = document.getElementById('toTop');

    function onScroll() {
        var y = window.scrollY || window.pageYOffset;
        topbar.classList.toggle('is-scrolled', y > 10);
        toTop.classList.toggle('is-visible', y > 700);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    toTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });

    if ('IntersectionObserver' in window) {
        var links = Array.prototype.slice.call(document.querySelectorAll('.top-links a'));
        var spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                links.forEach(function (a) {
                    a.classList.toggle('is-active', a.getAttribute('href') === '#' + entry.target.id);
                });
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        document.querySelectorAll('main section[id]').forEach(function (s) { spy.observe(s); });
    }

    /* ---------------- scroll-in animations ---------------- */
    var revealer = 'IntersectionObserver' in window && !reduceMotion
        ? new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px' })
        : null;
    document.querySelectorAll('.reveal').forEach(function (el) {
        if (revealer) revealer.observe(el); else el.classList.add('is-visible');
    });

    /* ---------------- falling petals and hearts in the banner ---------------- */
    (function petals() {
        var canvas = document.getElementById('petals');
        if (!canvas || reduceMotion || !canvas.getContext) return;
        var ctx = canvas.getContext('2d');
        var colors = ['#FFB703', '#FB8500', '#FF4D8D', '#FF8FB1', '#FFD166', '#FFFFFF'];
        var count = window.innerWidth < 700 ? 12 : 22;
        var items = [];
        var width = 0, height = 0, frame = 0, visible = true;

        function resize() {
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            width = canvas.clientWidth;
            height = canvas.clientHeight;
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }

        function make(anywhere) {
            return {
                x: Math.random() * width,
                y: anywhere ? Math.random() * height : -20,
                size: 6 + Math.random() * 8,
                speed: 0.35 + Math.random() * 0.8,
                drift: -0.3 + Math.random() * 0.6,
                angle: Math.random() * Math.PI * 2,
                spin: -0.02 + Math.random() * 0.04,
                sway: Math.random() * Math.PI * 2,
                color: colors[Math.floor(Math.random() * colors.length)],
                heart: Math.random() < 0.28
            };
        }

        function draw(p) {
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.angle);
            ctx.globalAlpha = 0.85;
            ctx.fillStyle = p.color;
            ctx.beginPath();
            if (p.heart) {
                var s = p.size / 9;
                ctx.moveTo(0, 7 * s);
                ctx.bezierCurveTo(-10 * s, 0, -7 * s, -9 * s, 0, -4 * s);
                ctx.bezierCurveTo(7 * s, -9 * s, 10 * s, 0, 0, 7 * s);
                ctx.fill();
            } else {
                ctx.ellipse(0, 0, p.size, p.size * 0.55, 0, 0, Math.PI * 2);
                ctx.fill();
                ctx.globalAlpha = 0.35;
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(-p.size * 0.7, 0);
                ctx.lineTo(p.size * 0.7, 0);
                ctx.stroke();
            }
            ctx.restore();
        }

        function tick() {
            frame = 0;
            if (!visible || document.hidden) return;
            ctx.clearRect(0, 0, width, height);
            items.forEach(function (p, i) {
                p.sway += 0.02;
                p.x += p.drift + Math.sin(p.sway) * 0.4;
                p.y += p.speed;
                p.angle += p.spin;
                if (p.y > height + 20 || p.x < -30 || p.x > width + 30) items[i] = p = make(false);
                draw(p);
            });
            frame = requestAnimationFrame(tick);
        }

        function start() {
            if (!frame) frame = requestAnimationFrame(tick);
        }

        resize();
        for (var i = 0; i < count; i++) items.push(make(true));
        start();
        window.addEventListener('resize', debounce(resize, 150));
        document.addEventListener('visibilitychange', start);
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                visible = entries[0].isIntersecting;
                if (visible) start();
            }).observe(canvas);
        }
    })();

    /* ---------------- confetti right after login ---------------- */
    if (A.celebrate && !reduceMotion && typeof window.confetti === 'function') {
        var palette = ['#FFB703', '#FF4D8D', '#FB8500', '#06D6A0', '#8338EC'];
        setTimeout(function () {
            window.confetti({ particleCount: 140, spread: 90, startVelocity: 45, origin: { y: 0.6 }, colors: palette });
            setTimeout(function () {
                window.confetti({ particleCount: 70, angle: 60, spread: 70, origin: { x: 0, y: 0.75 }, colors: palette });
                window.confetti({ particleCount: 70, angle: 120, spread: 70, origin: { x: 1, y: 0.75 }, colors: palette });
            }, 380);
        }, 500);
    }

    /* ---------------- lightbox defaults ---------------- */
    if ($ && $.fancybox) {
        $.extend($.fancybox.defaults, {
            hash: false,
            backFocus: false,
            loop: true,
            idleTime: 4,
            animationEffect: 'zoom-in-out',
            transitionEffect: 'slide',
            slideShow: { autoStart: false, speed: 3500 },
            thumbs: { autoStart: false },
            mobile: { clickSlide: 'close', dblclickContent: 'zoom' }
        });
        $.fancybox.defaults.video.tpl =
            '<video class="fancybox-video" controls controlsList="nodownload" playsinline poster="{{poster}}">' +
            '<source src="{{src}}" type="{{format}}" />Sorry, this video cannot play in your browser.</video>';
        $.fancybox.defaults.btnTpl.dl =
            '<a download data-fancybox-dl class="fancybox-button fancybox-button--download" title="Download" href="javascript:;">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M18.62 17.09V19H5.38v-1.91zm-2.97-6.96L17 11.45l-5 4.87-5-4.87 1.36-1.32 2.68 2.64V5h1.92v7.77z"/></svg></a>';
    }

    /* ---------------- films ---------------- */
    var videos = A.videos || [];

    function formatTime(seconds) {
        seconds = Math.round(seconds);
        var h = Math.floor(seconds / 3600), m = Math.floor(seconds % 3600 / 60), s = seconds % 60;
        return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
    }

    document.querySelectorAll('.video-card').forEach(function (card) {
        var video = videos[+card.getAttribute('data-video')];
        if (!video) return;

        card.addEventListener('click', function () {
            if (!$ || !$.fancybox) {
                window.open(video.src, '_blank');
                return;
            }
            if (video.kind === 'mp4') {
                $.fancybox.open({ src: video.src, type: 'video', opts: { caption: video.title, thumb: video.thumb, videoFormat: video.mime || 'video/mp4' } });
            } else {
                $.fancybox.open({ src: video.src, opts: { caption: video.title } });   // YouTube / Vimeo
            }
        });

        // Show the length of uploaded films that have no duration written.
        if (card.hasAttribute('data-probe')) {
            var probe = document.createElement('video');
            probe.preload = 'metadata';
            probe.muted = true;
            probe.addEventListener('loadedmetadata', function () {
                var badge = card.querySelector('.video-duration');
                if (isFinite(probe.duration) && probe.duration > 0) {
                    badge.textContent = formatTime(probe.duration);
                    badge.hidden = false;
                }
                probe.removeAttribute('src');
                probe.load();
            }, { once: true });
            probe.src = video.src;
        }
    });

    /* ---------------- photo gallery ---------------- */
    var grid = document.getElementById('photoGrid');
    if (grid) gallery();

    function gallery() {
        var all = A.photos || [];
        var names = {};
        (A.events || []).forEach(function (ev) { names[ev.key] = ev.name; });

        var BATCH = 36;
        var list = all, shown = 0, columns = [], heights = [];
        var sentinel = document.getElementById('photoSentinel');
        var tabs = Array.prototype.slice.call(document.querySelectorAll('#photoTabs .tab'));

        function url(photo, size) {
            return A.photoBase + '&s=' + size + '&f=' + encodeURIComponent(photo.f) + '&v=' + (photo.v || 0);
        }

        function columnCount() {
            var w = grid.clientWidth;
            return w < 560 ? 2 : w < 900 ? 3 : w < 1250 ? 4 : 5;
        }

        function makeColumns() {
            grid.innerHTML = '';
            columns = [];
            heights = [];
            for (var i = 0, n = columnCount(); i < n; i++) {
                var col = document.createElement('div');
                col.className = 'photo-col';
                grid.appendChild(col);
                columns.push(col);
                heights.push(0);
            }
        }

        // Each new photo goes into the shortest column, so nothing jumps around while scrolling.
        function addPhoto(photo, index) {
            var link = document.createElement('a');
            link.className = 'photo';
            link.href = url(photo, 'large');
            link.setAttribute('data-index', index);
            link.setAttribute('aria-label', (names[photo.e] || 'Wedding') + ' photo ' + (index + 1));
            link.style.animationDelay = (index % BATCH) * 25 + 'ms';

            var img = new Image();
            img.alt = '';
            img.decoding = 'async';
            img.loading = 'lazy';
            img.width = photo.w;
            img.height = photo.h;
            img.onload = img.onerror = function () { img.classList.add('is-loaded'); };
            img.src = url(photo, 'thumb');
            link.appendChild(img);

            var k = 0;
            for (var i = 1; i < heights.length; i++) if (heights[i] < heights[k] - 0.01) k = i;
            columns[k].appendChild(link);
            heights[k] += photo.h / photo.w + 0.06;
        }

        function renderMore() {
            var end = Math.min(list.length, shown + BATCH);
            for (var i = shown; i < end; i++) addPhoto(list[i], i);
            shown = end;
            sentinel.hidden = shown >= list.length;
        }

        function rebuild(keep) {
            makeColumns();
            var target = Math.max(keep || 0, Math.min(BATCH, list.length));
            shown = 0;
            while (shown < target) renderMore();
        }

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting && shown < list.length) renderMore();
            }, { rootMargin: '900px 0px' }).observe(sentinel);
        } else {
            BATCH = 100000;   // very old browser: show everything at once
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) {
                    t.classList.toggle('is-active', t === tab);
                    t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });
                var key = tab.getAttribute('data-event');
                list = key === '*' ? all : all.filter(function (p) { return p.e === key; });
                rebuild(0);
            });
        });

        var lastCount = columnCount();
        window.addEventListener('resize', debounce(function () {
            if (columnCount() !== lastCount) {
                lastCount = columnCount();
                rebuild(shown);
            }
        }, 200));

        grid.addEventListener('click', function (event) {
            var link = event.target.closest('a.photo');
            if (!link || !$ || !$.fancybox) return;
            event.preventDefault();
            openPhotos(+link.getAttribute('data-index'));
        });

        if (!A.allowDownload) {
            grid.addEventListener('contextmenu', function (event) {
                if (event.target.tagName === 'IMG') event.preventDefault();
            });
        }

        function openPhotos(start) {
            var items = list.map(function (photo, i) {
                return {
                    src: url(photo, 'large'),
                    type: 'image',
                    opts: {
                        thumb: url(photo, 'thumb'),
                        caption: (names[photo.e] || 'Photos') + ' &middot; ' + (i + 1) + ' / ' + list.length,
                        dl: A.allowDownload ? url(photo, 'original') + '&dl=1' : ''
                    }
                };
            });
            var buttons = ['zoom', 'slideShow', 'fullScreen'];
            if (A.allowDownload) buttons.push('dl');
            buttons.push('thumbs', 'close');
            $.fancybox.open(items, {
                buttons: buttons,
                protect: !A.allowDownload,
                afterShow: function (instance, current) {
                    instance.$refs.toolbar.find('[data-fancybox-dl]').attr('href', current.opts.dl || current.src);
                }
            }, start);
        }

        rebuild(0);
    }
})();
