/*
 * Devesh Studio – the PDF album shown as a real book.
 * PDF.js draws each page, StPageFlip turns them with a page-curl animation.
 * Wide pages (album "spreads") are cut into a left and a right page automatically.
 * Pages are drawn only when they are about to be seen, so even big albums open fast.
 */
import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/legacy/build/pdf.min.mjs';

pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/legacy/build/pdf.worker.min.mjs';

const A = window.ALBUM || {};
const stage = document.getElementById('bookStage');
const wrap = document.getElementById('bookWrap');
const bookEl = document.getElementById('book');
const loading = document.getElementById('bookLoading');
const progressEl = document.getElementById('bookProgress');
const toolbar = document.getElementById('bookToolbar');
const nowEl = document.getElementById('pageNow');
const totalEl = document.getElementById('pageTotal');
const MIN_PAGE = 260;   // narrower than two of these and the book shows one page at a time (phones)

let pdf = null;
let flip = null;
let pages = [];         // one entry per book page: { n: pdf page number, half: -1 whole | 0 left | 1 right }
let aspect = 0.75;      // page width / height
let showCover = true;
let queue = [];
let rendering = false;
let audio = null;
let soundOn = readPref('ds-album-sound', '1') === '1';

if (stage && A.pdf && window.St && window.St.PageFlip) {
    start().catch(fail);
} else if (stage) {
    fail(new Error('Flip book library did not load'));
}

async function start() {
    const task = pdfjsLib.getDocument({
        url: A.pdf,
        disableStream: true,      // fetch only the parts that are needed…
        disableAutoFetch: true,   // …and nothing in the background
        rangeChunkSize: 262144,
        isEvalSupported: false
    });
    task.onProgress = (p) => { if (p.total) setProgress(Math.min(0.5, (p.loaded / p.total) * 0.5)); };
    pdf = await task.promise;
    setProgress(0.55);

    // 1. Look at every page's shape – wide pages are two-page spreads.
    let done = 0;
    const sizes = await Promise.all(Array.from({ length: pdf.numPages }, (_, i) =>
        pdf.getPage(i + 1).then((page) => {
            const vp = page.getViewport({ scale: 1 });
            setProgress(0.55 + 0.35 * (++done / pdf.numPages));
            return { w: vp.width, h: vp.height };
        })
    ));
    const layout = A.pdfLayout || 'auto';
    const isSpread = (s) => layout === 'single' ? false : layout === 'spreads' ? s.w / s.h > 1.2 : s.w / s.h >= 1.55;
    sizes.forEach((s, i) => {
        if (isSpread(s)) {
            pages.push({ n: i + 1, half: 0 }, { n: i + 1, half: 1 });
        } else {
            pages.push({ n: i + 1, half: -1 });
        }
    });
    // A single first page is a cover and is shown alone, like a closed book.
    showCover = pages.length > 1 && pages[0].half === -1;
    const ref = sizes[pages[Math.min(1, pages.length - 1)].n - 1];
    aspect = pages[Math.min(1, pages.length - 1)].half >= 0 ? ref.w / 2 / ref.h : ref.w / ref.h;

    // 2. Build the book.
    pages.forEach((p, i) => {
        const el = document.createElement('div');
        const hard = showCover && p.half === -1 && (i === 0 || i === pages.length - 1);
        el.className = 'page' + (hard ? ' page-cover' : '');
        if (hard) el.setAttribute('data-density', 'hard');
        el.innerHTML = '<div class="page-inner"><span class="page-spinner"></span></div>';
        bookEl.appendChild(el);
        p.el = el;
    });

    window.addEventListener('resize', fit);   // registered before StPageFlip so the size is ready first
    loading.hidden = true;
    wrap.hidden = false;
    fit();

    flip = new window.St.PageFlip(bookEl, {
        width: 600,
        height: Math.round(600 / aspect),
        size: 'stretch',
        minWidth: MIN_PAGE,
        maxWidth: 1100,
        minHeight: Math.round(MIN_PAGE / aspect),
        maxHeight: Math.round(1100 / aspect),
        showCover,
        usePortrait: true,
        mobileScrollSupport: true,
        maxShadowOpacity: 0.45,
        flippingTime: 850,
        showPageCorners: true,
        autoSize: true
    });
    flip.on('flip', (e) => { updateCount(); queueAround(e.data); centerClosedBook(); });
    flip.on('changeOrientation', () => { updateCount(); queueAround(flip.getCurrentPageIndex()); centerClosedBook(); });
    flip.on('changeState', (e) => {
        if (e.data === 'flipping') playFlipSound();
        if ((e.data === 'flipping' || e.data === 'user_fold') && isClosed()) wrap.style.transform = '';   // opening: slide to the middle
        if (e.data === 'read') centerClosedBook();
    });
    flip.loadFromHTML(bookEl.querySelectorAll('.page'));

    totalEl.textContent = pages.length;
    toolbar.hidden = false;
    document.getElementById('bookHint').hidden = false;
    setSoundButton();
    updateCount();
    centerClosedBook();
    queueAround(0);
}

/** A closed book (front or back cover alone) sits in the middle instead of on one half. */
function isClosed() {
    if (!flip || !showCover || flip.getOrientation() !== 'landscape') return false;
    const i = flip.getCurrentPageIndex();
    return i === 0 || (i === pages.length - 1 && pages.length % 2 === 0);
}

function centerClosedBook() {
    const front = isClosed() && flip.getCurrentPageIndex() === 0;
    wrap.style.transform = isClosed() ? 'translateX(' + (front ? -25 : 25) + '%)' : '';
}

/* ---------------- drawing pages ---------------- */

function queueAround(index) {
    const order = [index, index + 1, index - 1, index + 2, index + 3, index - 2, index + 4, index + 5];
    const wanted = order.filter((k) => k >= 0 && k < pages.length && !pages[k].done);
    queue = wanted.concat(queue.filter((k) => !wanted.includes(k))).slice(0, 12);
    pump();
}

async function pump() {
    if (rendering) return;
    rendering = true;
    while (queue.length) {
        const k = queue.shift();
        if (pages[k].done) continue;
        try {
            await renderPage(k, false);
        } catch (err) {
            console.warn('Album page ' + (k + 1) + ' could not be drawn', err);
            pages[k].done = true;
            pages[k].el.querySelector('.page-inner').innerHTML = '<p class="page-broken">This page could not be shown.</p>';
        }
    }
    rendering = false;
}

function pageCssWidth() {
    const rect = flip && typeof flip.getBoundsRect === 'function' ? flip.getBoundsRect() : null;
    if (rect && rect.pageWidth) return rect.pageWidth;
    const w = wrap.clientWidth;
    return w >= MIN_PAGE * 2 ? w / 2 : w;
}

function targetWidth(zoom) {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const wanted = pageCssWidth() * dpr * (zoom ? 2.2 : 1);
    return Math.round(Math.min(zoom ? 2600 : 1800, Math.max(zoom ? 1600 : 520, wanted)));
}

/** Draw book page k (a spread draws both halves at once). Returns the image URL of page k. */
async function renderPage(k, zoom) {
    const p = pages[k];
    const page = await pdf.getPage(p.n);
    const base = page.getViewport({ scale: 1 });
    const width = targetWidth(zoom) * (p.half >= 0 ? 2 : 1);
    const viewport = page.getViewport({ scale: Math.min(width / base.width, 8) });

    const canvas = document.createElement('canvas');
    canvas.width = Math.ceil(viewport.width);
    canvas.height = Math.ceil(viewport.height);
    const ctx = canvas.getContext('2d', { alpha: false });
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    await page.render({ canvasContext: ctx, viewport }).promise;

    if (p.half < 0) return finish(k, canvas, zoom);

    const half = Math.floor(canvas.width / 2);
    let mine = '';
    for (const side of [0, 1]) {
        const idx = pages.findIndex((q) => q.n === p.n && q.half === side);
        const part = document.createElement('canvas');
        part.width = half;
        part.height = canvas.height;
        part.getContext('2d', { alpha: false }).drawImage(canvas, side * half, 0, half, canvas.height, 0, 0, half, canvas.height);
        const url = await finish(idx, part, zoom);
        if (idx === k) mine = url;
    }
    canvas.width = canvas.height = 0;
    return mine;
}

function finish(k, canvas, zoom) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            const width = canvas.width;
            canvas.width = canvas.height = 0;
            if (!blob) return reject(new Error('Could not make page image'));
            const url = URL.createObjectURL(blob);
            const p = pages[k];
            if (zoom) {
                p.zoomUrl = url;
                return resolve(url);
            }
            if (p.url) URL.revokeObjectURL(p.url);
            p.url = url;
            p.width = width;
            p.done = true;
            const img = new Image();
            img.alt = 'Album page ' + (k + 1);
            img.draggable = false;
            img.src = url;
            const inner = p.el.querySelector('.page-inner');
            inner.innerHTML = '';
            inner.appendChild(img);
            resolve(url);
        }, 'image/jpeg', zoom ? 0.92 : 0.88);
    });
}

/* ---------------- size and page counter ---------------- */

function isFullscreen() {
    return stage.classList.contains('is-fs') || stage.classList.contains('is-pseudo-fs');
}

/** Keep the whole book visible on screen: limit its width by the screen height. */
function fit() {
    const reserve = isFullscreen() ? 110 : 200;
    const availH = Math.max(240, window.innerHeight - reserve);
    const availW = stage.clientWidth - (isFullscreen() ? 32 : 0);
    let maxW = Math.floor(availH * aspect * 2);
    if (availW < MIN_PAGE * 2 || maxW < MIN_PAGE * 2) maxW = Math.floor(availH * aspect);
    wrap.style.maxWidth = Math.max(MIN_PAGE, maxW) + 'px';
}

function relayout() {
    fit();
    window.dispatchEvent(new Event('resize'));
    // Pages drawn for a smaller book are drawn again, sharper.
    setTimeout(() => {
        const need = targetWidth(false) * 0.8;
        visiblePages().forEach((k) => {
            if (pages[k].done && pages[k].width < need) pages[k].done = false;
        });
        queueAround(flip.getCurrentPageIndex());
    }, 300);
}

function visiblePages() {
    if (!flip) return [0];
    const i = flip.getCurrentPageIndex();
    if (flip.getOrientation() === 'portrait') return [i];
    if (showCover && (i === 0 || (i === pages.length - 1 && pages.length % 2 === 0))) return [i];
    return i + 1 < pages.length ? [i, i + 1] : [i];
}

function updateCount() {
    const shown = visiblePages();
    nowEl.textContent = shown.length > 1 ? (shown[0] + 1) + '–' + (shown[1] + 1) : String(shown[0] + 1);
    toolbar.querySelector('[data-book="prev"]').disabled = shown[0] <= 0;
    toolbar.querySelector('[data-book="next"]').disabled = shown[shown.length - 1] >= pages.length - 1;
}

function setProgress(value) {
    progressEl.textContent = Math.round(value * 100) + '%';
}

function fail(err) {
    console.error(err);
    loading.hidden = true;
    wrap.hidden = true;
    toolbar.hidden = true;
    document.getElementById('bookError').hidden = false;
}

/* ---------------- toolbar ---------------- */

toolbar.addEventListener('click', (event) => {
    const btn = event.target.closest('button[data-book]');
    if (!btn || !flip) return;
    const action = btn.getAttribute('data-book');
    if (action === 'prev') flip.flipPrev();
    if (action === 'next') flip.flipNext();
    if (action === 'zoom') zoom(btn);
    if (action === 'fullscreen') toggleFullscreen();
    if (action === 'sound') {
        soundOn = !soundOn;
        writePref('ds-album-sound', soundOn ? '1' : '0');
        setSoundButton();
        if (soundOn) playFlipSound();
    }
});

document.addEventListener('keydown', (event) => {
    if (!flip || event.altKey || event.ctrlKey || event.metaKey) return;
    if (/^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '')) return;
    if (window.jQuery && window.jQuery.fancybox && window.jQuery.fancybox.getInstance()) return;
    if (event.key === 'Escape' && stage.classList.contains('is-pseudo-fs')) return toggleFullscreen();
    const rect = stage.getBoundingClientRect();
    if (rect.bottom < 80 || rect.top > window.innerHeight - 80) return;   // book is not on screen
    if (event.key === 'ArrowRight') { flip.flipNext(); event.preventDefault(); }
    if (event.key === 'ArrowLeft') { flip.flipPrev(); event.preventDefault(); }
});

async function zoom(btn) {
    const $ = window.jQuery;
    if (!$ || !$.fancybox) return;
    btn.classList.add('is-busy');
    try {
        const items = [];
        for (const k of visiblePages()) {
            const src = pages[k].zoomUrl || await renderPage(k, true);
            items.push({ src, type: 'image', opts: { caption: 'Page ' + (k + 1) + ' of ' + pages.length } });
        }
        $.fancybox.open(items, {
            buttons: ['zoom', 'fullScreen', 'close'],
            loop: false,
            protect: !A.allowDownload,
            clickContent: 'zoom',
            wheel: false
        }, 0);
    } catch (err) {
        console.warn(err);
    }
    btn.classList.remove('is-busy');
}

function toggleFullscreen() {
    const doc = document;
    if (doc.fullscreenElement || doc.webkitFullscreenElement) {
        (doc.exitFullscreen || doc.webkitExitFullscreen).call(doc);
        return;
    }
    if (stage.classList.contains('is-pseudo-fs')) return pseudoFullscreen(false);
    const request = stage.requestFullscreen || stage.webkitRequestFullscreen;
    if (!request) return pseudoFullscreen(true);   // iPhone: no real full screen for pages, so fill the window instead
    let answered = false;
    const result = request.call(stage);
    if (result && result.then) {
        result.then(() => { answered = true; }, () => { answered = true; pseudoFullscreen(true); });
    }
    // Some in-app browsers never answer the request – fill the window instead.
    setTimeout(() => {
        if (!answered && !document.fullscreenElement && !document.webkitFullscreenElement) pseudoFullscreen(true);
    }, 900);
}

function pseudoFullscreen(on) {
    stage.classList.toggle('is-pseudo-fs', on);
    document.documentElement.classList.toggle('no-scroll', on);
    setFullscreenButton(on);
    relayout();
}

function onFullscreenChange() {
    const on = (document.fullscreenElement || document.webkitFullscreenElement) === stage;
    stage.classList.toggle('is-fs', on);
    setFullscreenButton(on);
    relayout();
}
document.addEventListener('fullscreenchange', onFullscreenChange);
document.addEventListener('webkitfullscreenchange', onFullscreenChange);

function setFullscreenButton(on) {
    const btn = toolbar.querySelector('[data-book="fullscreen"]');
    btn.firstElementChild.className = on ? 'fa-solid fa-compress' : 'fa-solid fa-expand';
    btn.setAttribute('aria-label', on ? 'Exit full screen' : 'Full screen');
}

/* ---------------- soft paper sound (made in the browser, no file needed) ---------------- */

function setSoundButton() {
    const btn = toolbar.querySelector('[data-book="sound"]');
    btn.classList.toggle('is-off', !soundOn);
    btn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
    btn.firstElementChild.className = soundOn ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-xmark';
}

function playFlipSound() {
    if (!soundOn) return;
    try {
        audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        if (audio.state === 'suspended') audio.resume();
        const now = audio.currentTime;
        const length = 0.42;
        const buffer = audio.createBuffer(1, Math.floor(audio.sampleRate * length), audio.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < data.length; i++) {
            const t = i / data.length;
            data[i] = (Math.random() * 2 - 1) * Math.pow(1 - t, 2) * (0.55 + 0.45 * Math.sin(t * 38));
        }
        const source = audio.createBufferSource();
        source.buffer = buffer;
        const filter = audio.createBiquadFilter();
        filter.type = 'bandpass';
        filter.Q.value = 0.9;
        filter.frequency.setValueAtTime(2600, now);
        filter.frequency.exponentialRampToValueAtTime(800, now + length);
        const gain = audio.createGain();
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.2, now + 0.05);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + length);
        source.connect(filter).connect(gain).connect(audio.destination);
        source.start(now);
    } catch (err) {
        /* sound is only a nice extra */
    }
}

function readPref(key, fallback) {
    try {
        return localStorage.getItem(key) || fallback;
    } catch (err) {
        return fallback;
    }
}

function writePref(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch (err) {
        /* private mode – fine */
    }
}
