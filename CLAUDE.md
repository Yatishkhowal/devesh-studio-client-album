# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A private "Client Album" add-on for the photography studio website https://deveshstudio.com (Devesh Studio). The live site is a static HTML template (Annie theme) on Hostinger (LiteSpeed server + hcdn CDN). **The site's own HTML pages are not in this repo.** Everything in `website-files/` is uploaded into the site's `public_html`, next to the existing pages. Each wedding client logs in with their own username and password and sees only their own album: a banner, their PDF shown as a flip book, films and photos.

- Plain PHP 7.4+. No Composer, no database, no build step. The front end is vanilla JS plus pinned CDN libraries.
- `README.md` is the guide for the studio owner (a non-programmer), written in Hinglish (Roman Hindi). Keep user-facing text in that style; the user also chats in Hinglish.
- `website-files.zip` is what the user uploads (README §1). Rebuild it after any change to `website-files/`.

## Commands

PHP is **not installed** on this machine. For local work, ask the user first, then download the official portable builds from `https://downloads.php.net/~windows/releases/latest/`:
- `php-8.3-nts-Win32-vs16-x64-latest.zip`
- `php-7.4-nts-Win32-vc15-x64-latest.zip`

Give each one a `php.ini` with:
- an absolute `extension_dir`
- `extension=mbstring`, `exif`, `gd` (`gd2` on 7.4) and `fileinfo`
- `upload_max_filesize = 2M` and `post_max_size = 3M` (small limits exercise the chunked uploader)

```bash
# Lint: the code must stay PHP 7.4 compatible, so lint every *.php with both versions
php74/php.exe -l website-files/admin.php
php83/php.exe -l website-files/admin.php

# JS syntax (flipbook.js is an ES module with CDN imports: copy it to a .mjs file first)
node --check website-files/album-assets/js/album.js
node --check website-files/album-assets/js/admin.js

# Local server: ALWAYS run it on a copy. The app writes sessions, admin.json and image caches
# into clients/, and hashes the sample password, so none of that may end up in the deliverable.
cp -r website-files <scratch>/test-site
php83/php.exe -S 127.0.0.1:8083 -t <scratch>/test-site tools/dev-router.php
#   http://localhost:8083/client-login.php
#   http://localhost:8083/admin.php   (the first visit creates the admin password)

# Upload zip: use tar.exe, NOT Compress-Archive (Windows PowerShell 5.1 writes "\" paths that break when extracted on Linux)
tar.exe -a -c -f website-files.zip -C website-files <each top-level item, without a "./" prefix>
```

- `tools/dev-router.php` stands in for the `.htaccess` files, which PHP's built-in server ignores. It returns 403 for `/clients/*` and `/album-core/*`.
- `.claude/launch.json` may still point at a temporary PHP path from an earlier session. Fix the path before using `preview_start`.
- In the desktop app's browser pane, a window in the background can stop painting. That freezes IntersectionObserver, lazy-loaded images and smooth scrolling, so don't mistake it for a bug.

There is no automated test suite. Verify by hand in the browser:
- **Login:** wrong password, and the lock after 5 wrong tries.
- **Album:** flip book, zoom, full screen, films, gallery tabs, lightbox, download.
- **Admin:** create a client, uploads, preview.
- **Security:**
  - `media.php` without a session returns 403.
  - A direct `/clients/...` link returns 403.
  - One client cannot read another client's files.

The sample client's login details are in README §7.

After changing CSS or JS, bump `ASSET_VERSION` in `album-core/bootstrap.php` (pages add it as `?v=`).

## Architecture

**Request flow.**
- The entry points sit at the web root: `client-login.php`, `album.php`, `media.php`, `logout.php` and `admin.php`.
- All of them `require_once album-core/bootstrap.php`, which holds every shared helper: config, sessions, CSRF, login throttling, client storage, photo listing and resizing, file streaming, film cards and upload helpers.
- `admin.php` also loads `album-core/admin-actions.php`, which has the form handlers. Its AJAX actions (`upload_chunk`, `photo_jobs`, `make_thumb`) reply with JSON.
- Settings are in `album-core/config.php`: studio details, WhatsApp number, links, timeouts, image sizes, timezone.

**Storage (no database).**
- **Per client:** one folder each. `clients/<slug>/data.json` holds the details, next to `cover.*`, `album.pdf`, `photos/<event-folder>/…` and `videos/…`.
- **Runtime state:** kept in `clients/_data/`:
  - its own `sessions/` folder
  - `admin.json` and `login-attempts.json`
  - half-finished `uploads/*.part`
  - `probe.txt`
- **Generated images:** kept in `clients/<slug>/_cache/`: `thumb/`, `large/`, `large-wm/`, `cover/`, plus `index.json` with photo sizes.
- **Reserved names:** any path part starting with `_` or `.` is reserved and is never served (`client_file()`). Slugs must match `^[a-z0-9][a-z0-9-]*$`.

**Privacy model.**
- `.htaccess` denies all access to `clients/` and `album-core/`.
- Every private file is streamed by `media.php` after the session check, using `t=cover|pdf|photo|poster|video`. Range requests are supported: PDF.js loads the album piece by piece, and films are capped at 8 MB per response.
- Responses carry `Cache-Control: private` and `Vary: Cookie`, so the Hostinger CDN never caches them.
- The admin dashboard fetches `clients/_data/probe.txt` and shows a warning if `.htaccess` is not working.
- **Admin preview:** `album.php?preview=<slug>` sets `$GLOBALS['ALBUM_PREVIEW']`. `media_url()` then adds `c=<slug>`, which `media.php` accepts only for a logged-in admin.

**Sessions and passwords.**
- The session cookie is named `DS_ALBUM`. Sessions are saved under `clients/_data/sessions`, so shared-hosting cleanup cannot remove them early. The idle timeout comes from config.
- The client session stores `pw_sig` (a hash of the password hash plus the username). The admin session stores `admin_sig` the same way. Changing a password therefore logs out existing sessions.
- `album.php`, `media.php` and the admin AJAX actions call `session_write_close()` early. This keeps parallel image and upload requests from waiting on the session lock.
- A plain `"password"` typed into a `data.json` is replaced by a `password_hash` the first time `load_client()` reads it. The sample client ships this way, and it is how clients can be added by FTP.

**Photos.**
- An event is a sub-folder of `photos/`. Display names and order come from the client's `events` list, matched with `slugify()`.
- `media.php?t=photo&s=…` serves three sizes:
  - `thumb`: a copy 640 px wide, for the grid.
  - `large`: a copy 1800 px on the long side. It gets `album-assets/img/watermark.png` when the client has `watermark: true`.
  - `original`: the uploaded file, served only when `allow_download` is on.
- GD fixes EXIF orientation. If GD is missing or there is not enough memory, the original file is served instead.

**Uploads.**
- `admin.js` sends every file in pieces. `upload_chunk_size()` sets the piece size from `upload_max_filesize` and `post_max_size`.
- The server checks each piece's offset. The last piece calls `finish_upload()`, which handles the kinds `cover`, `pdf`, `photo`, `video` and `poster`.
- For MP4 files, the browser reads the length before the upload and afterwards uploads a captured frame as the film's poster.

**Front end.** `album.php` puts everything the page needs into `window.ALBUM` as JSON.

`album.js`:
- **Gallery:** a masonry layout built in JS. Each photo goes into the shortest column; photos load in batches of 36 through IntersectionObserver, and the grid is rebuilt for each event tab.
- **Lightbox:** Fancybox 3, with a custom download button.
- **Film cards:** YouTube and Vimeo open through Fancybox's built-in support; MP4 files open as `type: 'video'`.
- **Effects:** falling petals in the banner, and confetti once after login.

`flipbook.js` (an ES module):
- **Rendering:** PDF.js draws pages only when needed, as JPEG blobs inside StPageFlip HTML pages.
- **Spreads:** pages wider than 1.55:1 are split into left and right halves. `pdf_layout` can override this: `auto`, `spreads` or `single`.
- **Cover:** a single first page becomes a hard cover shown alone. While the book is closed it is shifted ±25 % so it sits in the middle.
- **Zoom:** re-draws the visible pages at high resolution inside Fancybox.
- **Full screen:** falls back to a fixed "pseudo full screen" on iPhone and in in-app browsers.
- **Page sound:** made with WebAudio, so there is no sound file.

Pinned CDN versions:
- **cdnjs:** jQuery 3.7.1, Fancybox 3.5.7, Font Awesome 6.5.2.
- **jsDelivr:** page-flip 2.0.7, pdfjs-dist 4.10.38 (legacy build), canvas-confetti 1.9.3.
- **Google Fonts:** Josefin Sans and Great Vibes.

## Constraints and conventions

- Keep PHP 7.4 syntax: no `match`, no `str_contains`/`str_starts_with`, no nullsafe `?->`, no named arguments, no union types.
- Design rules from the client's brief:
  - Great Vibes only for the couple's names; Josefin Sans for everything else.
  - Palette: marigold `#FFB703`, pink `#FF4D8D`, orange `#FB8500`, teal `#06D6A0` and purple `#8338EC`, on cream `#FFF8EE`.
  - The login page matches the main site's dark `#202120`.
  - Event tag colours come from `event_tone()`, which knows common Rajasthani wedding events.
- Private pages send `noindex` and must never include the main site's AdSense script.
- Keep `Referrer-Policy: strict-origin-when-cross-origin`: YouTube embeds refuse to play without a referrer.
- The "Client Login" menu link has to be added by hand to the 7 static pages (`<nav class="annie-main-menu">`, README §2), because those files are not in this repo.
- `clients/priya-rahul` is the demo client. Its pictures are generated placeholders with no real people. Keep its plain `"password"` in `data.json` so it hashes itself on first use, and keep README §7 matching it.
- The WhatsApp number in `config.php` was taken from the live contact page. The user has been asked to confirm it.
