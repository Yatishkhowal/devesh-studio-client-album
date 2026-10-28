/*
 * Devesh Studio – admin page helpers:
 * uploads in pieces (any file size), password maker, copy buttons, "prepare photos" and safety checks.
 */
(() => {
    'use strict';

    const A = window.ADMIN || {};
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    /* ---------------- confirm before deleting ---------------- */
    $$('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
        });
    });

    /* ---------------- copy buttons ---------------- */
    $$('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const value = btn.getAttribute('data-copy');
            const text = value.charAt(0) === '#' ? $(value).textContent.trim() : value;
            try {
                await navigator.clipboard.writeText(text);
            } catch (err) {
                const area = document.createElement('textarea');
                area.value = text;
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }
            const old = btn.innerHTML;
            btn.textContent = 'Copied ✔';
            setTimeout(() => { btn.innerHTML = old; }, 1600);
        });
    });

    /* ---------------- easy-to-share passwords ---------------- */
    const WORDS = ['gulab', 'kamal', 'mogra', 'chameli', 'genda', 'tara', 'chanda', 'sitara', 'moti', 'heera',
        'sona', 'chandi', 'mehndi', 'haldi', 'kesar', 'kangan', 'payal', 'jhumka', 'doli', 'baraat',
        'shehnai', 'dhol', 'mithai', 'laddoo', 'jalebi', 'rasgulla', 'pyaar', 'khushi', 'sapna', 'basant',
        'sawan', 'mor', 'koyal', 'titli', 'jharna', 'nadiya', 'sagar', 'pahad', 'suraj', 'roshni',
        'diya', 'rangoli', 'phool', 'patang', 'lehenga', 'sehra', 'mandap', 'toran', 'gajra', 'bindi',
        'chudi', 'anmol', 'sundar', 'madhur', 'shubh', 'mangal', 'aangan', 'jaipur', 'pushkar', 'amer',
        'hawa', 'barkha', 'chaand', 'gagan'];
    const random = (n) => window.crypto.getRandomValues(new Uint32Array(1))[0] % n;

    $$('[data-genpass]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = btn.closest('.pw-row').querySelector('input');
            input.value = WORDS[random(WORDS.length)] + '-' + WORDS[random(WORDS.length)] + '-' + (1000 + random(9000));
            input.focus();
            input.select();
        });
    });

    // New client: suggest a username from the two names until the admin types one.
    const userInput = $('#usernameInput[data-suggest]');
    if (userInput) {
        const form = userInput.form;
        let touched = userInput.value !== '';
        userInput.addEventListener('input', () => { touched = true; });
        ['bride', 'groom'].forEach((name) => {
            form.elements[name].addEventListener('input', () => {
                if (touched) return;
                userInput.value = (form.elements.bride.value + form.elements.groom.value).toLowerCase().replace(/[^a-z0-9]/g, '').slice(0, 30);
            });
        });
    }

    /* ---------------- are client files really private? ---------------- */
    const probe = $('#protectCheck');
    if (probe) {
        const show = (ok) => {
            probe.className = 'notice ' + (ok ? 'ok' : 'bad');
            probe.innerHTML = ok
                ? '<i class="fa-solid fa-shield-halved"></i> Client files are private – they cannot be opened without logging in.'
                : '<i class="fa-solid fa-triangle-exclamation"></i> Warning: client files can be opened directly! Make sure the file <code>clients/.htaccess</code> was uploaded, or ask your hosting support.';
        };
        fetch(probe.getAttribute('data-probe') + '?t=' + Date.now(), { cache: 'no-store', credentials: 'omit' })
            .then((r) => (r.ok ? r.text() : ''))
            .then((text) => show(text.indexOf('PROTECTION-PROBE') === -1))
            .catch(() => show(true));
    }

    /* ---------------- uploads ---------------- */
    function randomId() {
        return Array.from(window.crypto.getRandomValues(new Uint8Array(12)), (b) => b.toString(16).padStart(2, '0')).join('');
    }

    function formatTime(seconds) {
        seconds = Math.round(seconds);
        const h = Math.floor(seconds / 3600), m = Math.floor((seconds % 3600) / 60), s = seconds % 60;
        return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
    }

    async function post(fields) {
        const body = new FormData();
        body.append('csrf', A.csrf);
        Object.keys(fields).forEach((key) => body.append(key, fields[key]));
        const response = await fetch('admin.php', { method: 'POST', body, credentials: 'same-origin' });
        let data;
        try {
            data = await response.json();
        } catch (err) {
            data = { ok: false, error: 'The server sent an unexpected answer (' + response.status + ').' };
        }
        data.status = response.status;
        return data;
    }

    /** Send one file in pieces. Each piece is retried a few times if the internet drops. */
    async function uploadFile(file, kind, fields, onProgress) {
        const size = A.chunkSize || 2 * 1024 * 1024;
        const total = Math.max(1, Math.ceil(file.size / size));
        const id = randomId();
        let result = null;
        for (let i = 0; i < total; i++) {
            const start = i * size;
            const piece = file.slice(start, start + size);
            for (let attempt = 1; ; attempt++) {
                let data;
                try {
                    data = await post(Object.assign({}, fields, {
                        action: 'upload_chunk', slug: A.slug, kind, name: file.name, size: file.size,
                        index: i, total, offset: start, upload_id: id, chunk: piece
                    }));
                } catch (err) {
                    data = { ok: false, network: true, error: 'Internet problem – please check your connection.' };
                }
                if (data.status === 409 && data.have === start + piece.size) {
                    data = { ok: true };   // the earlier try did reach the server
                }
                if (data.ok) {
                    result = data;
                    break;
                }
                if (!data.network || attempt >= 4) throw new Error(data.error || 'Upload failed');
                await sleep(1500 * attempt);
            }
            onProgress((i + 1) / total);
        }
        return result;
    }

    function addRow(list, file) {
        const row = document.createElement('div');
        row.className = 'up-item';
        row.innerHTML = '<span class="name"></span><span class="state">Waiting…</span><span class="bar"><i></i></span>';
        row.querySelector('.name').textContent = file.name;
        list.appendChild(row);
        return row;
    }

    function setRow(row, progress, text, error) {
        row.querySelector('.bar i').style.width = Math.round(progress * 100) + '%';
        row.querySelector('.state').textContent = text || Math.round(progress * 100) + '%';
        row.classList.toggle('is-done', progress >= 1 && !error);
        row.classList.toggle('is-error', !!error);
    }

    /** Length of a local film, read before uploading. */
    function filmLength(file) {
        return new Promise((resolve) => {
            const video = document.createElement('video');
            const url = URL.createObjectURL(file);
            let finished = false;
            const done = (value) => {
                if (finished) return;
                finished = true;
                URL.revokeObjectURL(url);
                resolve(value);
            };
            video.preload = 'metadata';
            video.muted = true;
            video.onloadedmetadata = () => done(isFinite(video.duration) ? formatTime(video.duration) : '');
            video.onerror = () => done('');
            setTimeout(() => done(''), 8000);
            video.src = url;
        });
    }

    /** A picture from a few seconds into the film, used as its cover. */
    function filmPicture(file) {
        return new Promise((resolve) => {
            const video = document.createElement('video');
            const url = URL.createObjectURL(file);
            let finished = false;
            const done = (blob) => {
                if (finished) return;
                finished = true;
                URL.revokeObjectURL(url);
                resolve(blob);
            };
            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';
            video.onerror = () => done(null);
            video.onloadeddata = () => { video.currentTime = Math.min(4, (video.duration || 10) * 0.2); };
            video.onseeked = () => {
                if (!video.videoWidth) return done(null);
                const canvas = document.createElement('canvas');
                canvas.width = 1280;
                canvas.height = Math.round(1280 * video.videoHeight / video.videoWidth);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => done(blob), 'image/jpeg', 0.85);
            };
            setTimeout(() => done(null), 15000);
            video.src = url;
        });
    }

    function eventName(box) {
        const select = $('[data-event-select]', box);
        if (!select) return '';
        return select.value === '__new' ? ($('[data-event-new]', box).value || '').trim() : select.value;
    }

    $$('[data-event-select]').forEach((select) => {
        select.addEventListener('change', () => {
            const box = select.closest('.uploader');
            const wrap = $('[data-event-new-wrap]', box);
            wrap.hidden = select.value !== '__new';
            if (!wrap.hidden) $('[data-event-new]', box).focus();
        });
    });

    $$('.uploader').forEach((box) => {
        const input = $('input[type="file"]', box);
        const list = $('.up-list', box);
        const kind = box.getAttribute('data-kind');
        const many = box.hasAttribute('data-multiple');

        const pick = (fileList) => {
            let files = Array.from(fileList || []);
            if (!files.length) return;
            if (!many) files = files.slice(0, 1);
            run(files);
        };

        input.addEventListener('change', () => { pick(input.files); input.value = ''; });
        box.addEventListener('dragover', (event) => { event.preventDefault(); box.classList.add('is-drag'); });
        box.addEventListener('dragleave', () => box.classList.remove('is-drag'));
        box.addEventListener('drop', (event) => {
            event.preventDefault();
            box.classList.remove('is-drag');
            pick(event.dataTransfer.files);
        });

        async function run(files) {
            const fields = {};
            if (kind === 'photo') {
                fields.event = eventName(box);
                if (!fields.event) {
                    window.alert('Please choose the event first (for example Haldi or Wedding).');
                    $('[data-event-select]', box).focus();
                    return;
                }
            }
            if (kind === 'video') fields.title = ($('[data-video-title]', box).value || '').trim();

            box.classList.add('is-busy');
            list.innerHTML = '';
            const rows = files.map((file) => addRow(list, file));
            let next = 0;
            let failed = 0;

            const worker = async () => {
                while (next < files.length) {
                    const i = next++;
                    const file = files[i];
                    try {
                        const extra = Object.assign({}, fields);
                        if (kind === 'video') extra.duration = await filmLength(file);
                        const result = await uploadFile(file, kind, extra, (p) => setRow(rows[i], p, p < 1 ? null : 'Finishing…'));
                        if (kind === 'video' && result && typeof result.video === 'number') {
                            setRow(rows[i], 1, 'Making film cover…');
                            const picture = await filmPicture(file);
                            if (picture) {
                                await uploadFile(new File([picture], 'cover.jpg', { type: 'image/jpeg' }), 'poster', { video: result.video }, () => {}).catch(() => {});
                            }
                        }
                        setRow(rows[i], 1, 'Done ✔');
                    } catch (err) {
                        failed++;
                        setRow(rows[i], 1, err.message || 'Failed', true);
                    }
                }
            };
            await Promise.all(Array.from({ length: Math.min(kind === 'photo' ? 3 : 1, files.length) }, worker));
            box.classList.remove('is-busy');

            if (!failed) {
                const anchor = box.getAttribute('data-anchor');
                history.replaceState(null, '', location.pathname + location.search + (anchor ? '#' + anchor : ''));
                setTimeout(() => location.reload(), 700);
            }
        }
    });

    /* ---------------- make fast-loading copies of photos copied by FTP ---------------- */
    const prepare = $('[data-prepare-photos]');
    if (prepare) {
        prepare.addEventListener('click', async () => {
            const status = $('#prepStatus');
            prepare.disabled = true;
            status.textContent = 'Checking photos…';
            const data = await post({ action: 'photo_jobs', slug: A.slug }).catch(() => ({ ok: false }));
            if (!data.ok) {
                status.textContent = data.error || 'Could not check the photos.';
                prepare.disabled = false;
                return;
            }
            if (!data.gd) {
                status.textContent = 'Your hosting cannot resize photos (GD is off) – photos will be shown at full size.';
                return;
            }
            const jobs = data.jobs;
            let done = 0;
            let next = 0;
            const worker = async () => {
                while (next < jobs.length) {
                    await post({ action: 'make_thumb', slug: A.slug, f: jobs[next++] }).catch(() => null);
                    status.textContent = 'Preparing photos… ' + (++done) + ' / ' + jobs.length;
                }
            };
            await Promise.all([worker(), worker()]);
            status.textContent = jobs.length ? 'All ' + jobs.length + ' photos are ready ✔' : 'All photos were already ready ✔';
            prepare.disabled = false;
        });
    }
})();
