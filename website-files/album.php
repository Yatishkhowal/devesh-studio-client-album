<?php
/**
 * The private wedding album: banner, PDF flip book, films and photos.
 * Only the logged-in client – or the admin, in preview – can open it.
 */
require_once __DIR__ . '/album-core/bootstrap.php';

album_session_start();

$preview = false;
$client = null;
if (isset($_GET['preview']) && is_admin()) {
    $client = load_client((string) $_GET['preview']);
    $preview = $client !== null;
}
if (!$client) {
    $client = current_client();
}
if (!$client) {
    redirect('client-login.php');
}
$celebrate = !$preview && !empty($_SESSION['just_logged_in']);
unset($_SESSION['just_logged_in']);
session_write_close();

if ($preview) {
    $GLOBALS['ALBUM_PREVIEW'] = $client['slug'];
}

$studio = (string) cfg('studio_name', 'Devesh Studio');
$names = couple_names($client);
$date = pretty_date((string) $client['wedding_date']);
$place = implode(', ', array_filter([trim((string) $client['venue']), trim((string) $client['city'])], 'strlen'));
$message = trim((string) $client['message']) !== '' ? trim((string) $client['message']) : (string) cfg('default_message', '');
$coverFile = $client['cover'] !== '' ? client_file($client, (string) $client['cover']) : null;
$pdfFile = $client['pdf'] !== '' ? client_file($client, (string) $client['pdf']) : null;
$videos = video_cards($client);
$gallery = list_photos($client);
$pdfDownload = $pdfFile && $client['allow_download'] ? media_url('pdf', ['v' => filemtime($pdfFile), 'dl' => 1]) : '';

$albumData = [
    'pdf'           => $pdfFile ? media_url('pdf', ['v' => filemtime($pdfFile)]) : '',
    'pdfLayout'     => $client['pdf_layout'],
    'allowDownload' => $client['allow_download'],
    'photoBase'     => media_url('photo'),
    'events'        => $gallery['events'],
    'photos'        => $gallery['photos'],
    'videos'        => $videos,
    'celebrate'     => $celebrate,
];
$json = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
$tones = ['pink', 'orange', 'purple', 'teal', 'yellow'];
$v = ASSET_VERSION;

page_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#FF4D8D">
<title><?= e($names) ?> · Wedding Album · <?= e($studio) ?></title>
<link rel="icon" href="<?= e(cfg('favicon')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Josefin+Sans:wght@300;400;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.css">
<link rel="stylesheet" href="album-assets/css/album.css?v=<?= $v ?>">
</head>
<body>

<header class="topbar" id="topbar">
    <a class="brand" href="#top">
        <img src="<?= e(cfg('logo')) ?>" alt="" onerror="this.remove()">
        <span><?= e($studio) ?></span>
    </a>
    <nav class="top-links" aria-label="Album sections">
        <a href="#album">Album</a>
        <?php if ($videos): ?><a href="#films">Films</a><?php endif; ?>
        <a href="#photos">Photos</a>
    </nav>
    <?php if ($preview): ?>
        <a class="btn-logout" href="admin.php?view=edit&amp;c=<?= e(rawurlencode($client['slug'])) ?>"><i class="fa-solid fa-arrow-left"></i><span>Back to admin</span></a>
    <?php else: ?>
        <a class="btn-logout" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    <?php endif; ?>
</header>

<main>
    <!-- ============ 1. Banner ============ -->
    <section class="hero" id="top">
        <?php if ($coverFile): ?>
            <img class="hero-img" src="<?= e(media_url('cover', ['v' => filemtime($coverFile)])) ?>" alt="" fetchpriority="high">
        <?php endif; ?>
        <div class="toran" aria-hidden="true"></div>
        <canvas class="petals" id="petals" aria-hidden="true"></canvas>

        <div class="hero-content">
            <p class="hero-kicker">The wedding celebration of</p>
            <h1 class="couple-names">
                <?php if (trim((string) $client['bride']) !== '' && trim((string) $client['groom']) !== ''): ?>
                    <span class="name"><?= e(trim((string) $client['bride'])) ?></span>
                    <i class="fa-solid fa-heart heart" aria-label="and"></i>
                    <span class="name"><?= e(trim((string) $client['groom'])) ?></span>
                <?php else: ?>
                    <span class="name"><?= e($names) ?></span>
                <?php endif; ?>
            </h1>

            <?php if ($date !== '' || $place !== ''): ?>
                <div class="hero-meta">
                    <?php if ($date !== ''): ?><span><i class="fa-regular fa-calendar"></i><?= e($date) ?></span><?php endif; ?>
                    <?php if ($place !== ''): ?><span><i class="fa-solid fa-location-dot"></i><?= e($place) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($client['events']): ?>
                <ul class="event-pills" aria-label="Wedding events">
                    <?php foreach ($client['events'] as $i => $event): ?>
                        <li class="pill tone-<?= e(event_tone((string) $event, $i)) ?>"><?= e($event) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($message !== ''): ?>
                <p class="hero-thanks"><?= nl2br(e($message)) ?><span>— <?= e($studio) ?></span></p>
            <?php endif; ?>
        </div>

        <a class="scroll-down" href="#album"><span>Scroll down</span><i class="fa-solid fa-chevron-down"></i></a>
    </section>

    <!-- ============ 2. PDF album as a book ============ -->
    <section class="section section-book" id="album">
        <div class="section-head reveal">
            <span class="kicker">Flip through</span>
            <h2>Your Wedding Album</h2>
            <div class="divider"><?= flower_svg() ?></div>
            <p>Turn the pages just like a real album – drag a page corner, swipe, or use the arrows.</p>
        </div>

        <?php if ($pdfFile): ?>
            <div class="book-stage" id="bookStage">
                <div class="book-loading" id="bookLoading" role="status">
                    <div class="book-loader" aria-hidden="true"><span></span><span></span><span></span></div>
                    <p>Opening your album… <b id="bookProgress">0%</b></p>
                </div>

                <div class="book-wrap" id="bookWrap" hidden>
                    <div class="book" id="book"></div>
                </div>

                <div class="book-error" id="bookError" hidden>
                    <i class="fa-solid fa-book-open"></i>
                    <p>Sorry, the album could not be opened right now. Please refresh the page.</p>
                    <?php if ($pdfDownload !== ''): ?>
                        <a class="btn-soft" href="<?= e($pdfDownload) ?>"><i class="fa-solid fa-download"></i> Download the PDF instead</a>
                    <?php endif; ?>
                </div>

                <div class="book-toolbar" id="bookToolbar" hidden>
                    <button class="tb-btn" type="button" data-book="prev" aria-label="Previous page" title="Previous page"><i class="fa-solid fa-chevron-left"></i></button>
                    <span class="book-count" aria-live="polite"><b id="pageNow">1</b> / <span id="pageTotal">1</span></span>
                    <button class="tb-btn" type="button" data-book="next" aria-label="Next page" title="Next page"><i class="fa-solid fa-chevron-right"></i></button>
                    <span class="tb-sep" aria-hidden="true"></span>
                    <button class="tb-btn" type="button" data-book="zoom" aria-label="Zoom into this page" title="Zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                    <button class="tb-btn" type="button" data-book="fullscreen" aria-label="Full screen" title="Full screen"><i class="fa-solid fa-expand"></i></button>
                    <button class="tb-btn" type="button" data-book="sound" aria-label="Page turn sound" aria-pressed="true" title="Page turn sound"><i class="fa-solid fa-volume-high"></i></button>
                    <?php if ($pdfDownload !== ''): ?>
                        <a class="tb-btn" href="<?= e($pdfDownload) ?>" aria-label="Download PDF" title="Download PDF"><i class="fa-solid fa-download"></i></a>
                    <?php endif; ?>
                </div>
                <p class="book-hint" id="bookHint" hidden><i class="fa-regular fa-hand-pointer"></i> Tip: tap the magnifier to see a page up close.</p>
            </div>
        <?php else: ?>
            <div class="coming-soon reveal">
                <i class="fa-solid fa-book-open"></i>
                <h3>Your album is being designed</h3>
                <p>We are giving your album its final touches. It will appear here very soon!</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- ============ 3. Films ============ -->
    <?php if ($videos): ?>
        <section class="section section-films" id="films">
            <div class="section-head reveal">
                <span class="kicker">Watch again</span>
                <h2>Your Wedding Films</h2>
                <div class="divider"><?= flower_svg() ?></div>
                <p>Tap a film to play it.</p>
            </div>
            <div class="video-grid">
                <?php foreach ($videos as $i => $video): ?>
                    <button type="button" class="video-card reveal tone-<?= $tones[$i % count($tones)] ?>" data-video="<?= $i ?>"<?= $video['kind'] === 'mp4' && $video['duration'] === '' ? ' data-probe' : '' ?>>
                        <span class="video-thumb">
                            <?php if ($video['thumb'] !== ''): ?>
                                <img src="<?= e($video['thumb']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="video-fallback"><i class="fa-solid fa-film"></i></span>
                            <?php endif; ?>
                            <span class="play-btn" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                            <span class="video-duration"<?= $video['duration'] === '' ? ' hidden' : '' ?>><?= e($video['duration']) ?></span>
                        </span>
                        <span class="video-info">
                            <span class="video-title"><?= e($video['title']) ?></span>
                            <span class="video-meta"><i class="fa-solid fa-circle-play"></i> Tap to watch</span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ============ 4. Photos ============ -->
    <section class="section section-photos" id="photos">
        <div class="section-head reveal">
            <span class="kicker">Moments</span>
            <h2>Your Wedding Photos</h2>
            <div class="divider"><?= flower_svg() ?></div>
            <?php if ($gallery['photos']): ?>
                <p><?= plural(count($gallery['photos']), 'photo') ?> · tap any photo to see it big</p>
            <?php endif; ?>
        </div>

        <?php if ($gallery['photos']): ?>
            <?php if (count($gallery['events']) > 1): ?>
                <div class="photo-tabs" id="photoTabs" role="tablist" aria-label="Show photos of">
                    <button type="button" class="tab tone-all is-active" data-event="*" role="tab" aria-selected="true">All <span class="count"><?= count($gallery['photos']) ?></span></button>
                    <?php foreach ($gallery['events'] as $i => $event): ?>
                        <button type="button" class="tab tone-<?= e(event_tone($event['name'], $i)) ?>" data-event="<?= e($event['key']) ?>" role="tab" aria-selected="false"><?= e($event['name']) ?> <span class="count"><?= (int) $event['count'] ?></span></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="photo-grid" id="photoGrid"></div>
            <div class="photo-sentinel" id="photoSentinel" aria-hidden="true"></div>
            <noscript><p class="coming-soon">Please turn on JavaScript to see your photos.</p></noscript>
        <?php else: ?>
            <div class="coming-soon reveal">
                <i class="fa-solid fa-camera-retro"></i>
                <h3>Your photos are on their way</h3>
                <p>We are carefully editing every moment. Your photos will appear here soon!</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- ============ 5. Footer ============ -->
<footer class="album-footer">
    <div class="toran toran-footer" aria-hidden="true"></div>
    <p class="footer-thanks">Thank you for choosing <?= e($studio) ?></p>
    <p class="footer-sub">It was an honour to capture your special day. We would love to hear from you!</p>
    <div class="footer-social">
        <a href="<?= e(whatsapp_link('Hello ' . $studio . '! We just saw our wedding album.')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
        <a href="<?= e(cfg('instagram')) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
        <a href="<?= e(cfg('facebook')) ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="<?= e(cfg('youtube')) ?>" target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
    </div>
    <?php if (!$preview): ?>
        <a class="btn-logout btn-logout-light" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    <?php endif; ?>
    <small>© <?= date('Y') ?> <?= e($studio) ?> · Made with love</small>
</footer>

<button class="to-top" type="button" id="toTop" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></button>

<?php if ($preview): ?>
    <div class="preview-bar"><i class="fa-solid fa-eye"></i><span>Admin preview</span>
        <a href="admin.php?view=edit&amp;c=<?= e(rawurlencode($client['slug'])) ?>">Back to admin</a></div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js"></script>
<?php if ($celebrate): ?>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<?php endif; ?>
<?php if ($pdfFile): ?>
<script src="https://cdn.jsdelivr.net/npm/page-flip@2.0.7/dist/js/page-flip.browser.min.js"></script>
<?php endif; ?>
<script>window.ALBUM = <?= json_encode($albumData, $json) ?>;</script>
<script src="album-assets/js/album.js?v=<?= $v ?>"></script>
<?php if ($pdfFile): ?>
<script type="module" src="album-assets/js/flipbook.js?v=<?= $v ?>"></script>
<?php endif; ?>
</body>
</html>
