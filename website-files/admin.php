<?php
/**
 * Devesh Studio – Client Album admin
 * Add clients, set their login, upload the cover photo, PDF album, photos and films.
 * First visit: create the admin password. Keep this page's address to yourself.
 */
require_once __DIR__ . '/album-core/bootstrap.php';
require_once __DIR__ . '/album-core/admin-actions.php';

album_session_start();

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action = $isPost ? (string) ($_POST['action'] ?? '') : '';
$ajaxActions = ['upload_chunk', 'photo_jobs', 'make_thumb'];
$isAjax = in_array($action, $ajaxActions, true);
$hasAdmin = admin_password_hash() !== '';

/* ------------------------------------------------------------ form posts */
if ($isPost) {
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        json_out(['ok' => false, 'error' => 'The upload is bigger than your hosting allows. Please reload the page and try again.'], 413);
    }
    if (!csrf_ok()) {
        if ($isAjax) {
            json_out(['ok' => false, 'error' => 'Your admin session has ended. Please reload the page.'], 403);
        }
        flash('The page was open for too long. Please try again.', 'error');
        redirect('admin.php');
    }
    if ($action === 'setup') {
        if ($hasAdmin) {
            redirect('admin.php');
        }
        handle_setup();
    }
    if ($action === 'login') {
        handle_login();
    }
    if (!is_admin()) {
        if ($isAjax) {
            json_out(['ok' => false, 'error' => 'Please log in to the admin page again.'], 401);
        }
        redirect('admin.php');
    }
    if ($isAjax) {
        session_write_close();   // several uploads can now run at the same time
    }
    switch ($action) {
        case 'logout':
            unset($_SESSION['admin'], $_SESSION['admin_seen']);
            redirect('admin.php');
            break;
        case 'save_client':     handle_save_client(); break;
        case 'toggle_client':   handle_toggle_client(); break;
        case 'delete_client':   handle_delete_client(); break;
        case 'add_video':       handle_add_video(); break;
        case 'remove_video':
        case 'video_up':        handle_video_change($action); break;
        case 'delete_event':    handle_delete_event(); break;
        case 'change_password': handle_change_password(); break;
        case 'upload_chunk':    handle_upload_chunk(); break;
        case 'photo_jobs':      handle_photo_jobs(); break;
        case 'make_thumb':      handle_make_thumb(); break;
    }
    redirect('admin.php');
}

/* ------------------------------------------------------------ pages */
$view = !$hasAdmin ? 'setup' : (!is_admin() ? 'login' : (string) ($_GET['view'] ?? 'dashboard'));
if (!in_array($view, ['setup', 'login', 'dashboard', 'new', 'edit', 'settings'], true)) {
    $view = 'dashboard';
}
$client = null;
if ($view === 'edit') {
    $client = load_client((string) ($_GET['c'] ?? ''));
    if (!$client) {
        flash('Client not found.', 'error');
        redirect('admin.php');
    }
}
if ($view === 'new') {
    $client = client_defaults();
    $client['slug'] = '';
}
$clients = $view === 'dashboard' ? all_clients() : [];
if ($view === 'dashboard') {
    @file_put_contents(DATA_DIR . '/probe.txt', 'PROTECTION-PROBE');   // the page checks this file cannot be opened
}
$old = $_SESSION['old'] ?? null;
unset($_SESSION['old']);
$share = $view === 'edit' && isset($_SESSION['share']['slug']) && $_SESSION['share']['slug'] === $client['slug'] ? $_SESSION['share'] : null;
if ($share) {
    unset($_SESSION['share']);
}
$flashes = take_flash();
$csrf = csrf_token();
$studio = (string) cfg('studio_name', 'Devesh Studio');
$loginUrl = site_url('client-login.php');

/** Value for a form field: what was just typed (after an error) or what is saved. */
function field($key)
{
    global $old, $client;
    if (is_array($old) && array_key_exists($key, $old)) {
        return $old[$key];
    }
    return $client[$key] ?? '';
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

page_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Client Albums · Admin · <?= e($studio) ?></title>
<link rel="icon" href="<?= e(cfg('favicon')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="album-assets/css/admin.css?v=<?= ASSET_VERSION ?>">
</head>
<body class="adm">

<?php if ($view === 'setup' || $view === 'login'): ?>
<!-- ============================================================ setup / login -->
<main class="auth">
    <form class="auth-card" method="post" action="admin.php">
        <?= csrf_field() ?>
        <div class="auth-logo"><i class="fa-solid fa-images"></i></div>
        <h1><?= $view === 'setup' ? 'Create your admin password' : 'Admin login' ?></h1>
        <p class="muted"><?= e($studio) ?> · Client Albums</p>
        <?php foreach ($flashes as [$type, $text]): ?>
            <div class="flash flash-<?= e($type) ?>"><?= e($text) ?></div>
        <?php endforeach; ?>
        <?php if ($view === 'setup'): ?>
            <input type="hidden" name="action" value="setup">
            <p class="hint">This is the first visit. Choose a strong password (at least 8 characters). Only you will use it.</p>
            <label>New password <input type="password" name="password" minlength="8" autocomplete="new-password" required autofocus></label>
            <label>Type it again <input type="password" name="password2" minlength="8" autocomplete="new-password" required></label>
            <button class="btn btn-primary btn-wide">Create password</button>
        <?php else: ?>
            <input type="hidden" name="action" value="login">
            <label>Password <input type="password" name="password" autocomplete="current-password" required autofocus></label>
            <button class="btn btn-primary btn-wide">Log in</button>
        <?php endif; ?>
    </form>
</main>

<?php else: ?>
<!-- ============================================================ header -->
<header class="adm-top">
    <a class="adm-brand" href="admin.php"><span class="adm-dot"></span><?= e($studio) ?> <small>Client Albums</small></a>
    <nav>
        <a href="admin.php" class="<?= $view === 'dashboard' ? 'is-active' : '' ?>"><i class="fa-solid fa-users"></i><span>All clients</span></a>
        <a href="admin.php?view=settings" class="<?= $view === 'settings' ? 'is-active' : '' ?>"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
        <form method="post" action="admin.php">
            <?= csrf_field() ?><input type="hidden" name="action" value="logout">
            <button class="linklike"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></button>
        </form>
    </nav>
</header>

<main class="adm-main">
    <?php foreach ($flashes as [$type, $text]): ?>
        <div class="flash flash-<?= e($type) ?>"><?= e($text) ?></div>
    <?php endforeach; ?>

<?php if ($view === 'dashboard'): ?>
    <!-- ======================================================== dashboard -->
    <div class="adm-head">
        <div>
            <h1>Client albums</h1>
            <p class="muted">Every client gets a private page with a banner, their PDF album as a book, films and photos.</p>
        </div>
        <a class="btn btn-primary" href="admin.php?view=new"><i class="fa-solid fa-plus"></i> New client</a>
    </div>

    <div class="notice" id="protectCheck" data-probe="clients/_data/probe.txt"><i class="fa-solid fa-spinner fa-spin"></i> Checking that client files are private…</div>

    <div class="linkbox">
        <span><i class="fa-solid fa-link"></i> Client login page:</span>
        <code id="loginUrl"><?= e($loginUrl) ?></code>
        <button type="button" class="btn btn-small" data-copy="#loginUrl"><i class="fa-regular fa-copy"></i> Copy</button>
    </div>

    <?php if (!$clients): ?>
        <div class="card empty">
            <i class="fa-solid fa-heart"></i>
            <h2>No clients yet</h2>
            <p class="muted">Add your first wedding client to create their private album.</p>
            <a class="btn btn-primary" href="admin.php?view=new"><i class="fa-solid fa-plus"></i> Add a client</a>
        </div>
    <?php else: ?>
        <div class="client-grid">
            <?php foreach ($clients as $c):
                $photos = count(list_photos($c)['photos']);
                $hasCover = $c['cover'] !== '' && client_file($c, (string) $c['cover']);
                $hasPdf = $c['pdf'] !== '' && client_file($c, (string) $c['pdf']);
                $GLOBALS['ALBUM_PREVIEW'] = $c['slug'];
                $coverUrl = $hasCover ? media_url('cover', ['v' => filemtime(client_file($c, (string) $c['cover']))]) : '';
                $GLOBALS['ALBUM_PREVIEW'] = '';
            ?>
                <article class="client-card<?= $c['enabled'] ? '' : ' is-off' ?>">
                    <a class="cc-cover" href="<?= e(edit_url($c['slug'])) ?>"<?= $coverUrl ? ' style="background-image:url(\'' . e($coverUrl) . '\')"' : '' ?>>
                        <span class="status <?= $c['enabled'] ? 'status-on' : 'status-off' ?>"><?= $c['enabled'] ? 'Active' : 'Closed' ?></span>
                    </a>
                    <div class="cc-body">
                        <h2><a href="<?= e(edit_url($c['slug'])) ?>"><?= e(couple_names($c)) ?></a></h2>
                        <p class="muted">
                            <?php if ($c['wedding_date']): ?><i class="fa-regular fa-calendar"></i> <?= e(pretty_date((string) $c['wedding_date'], 'j M Y')) ?> &nbsp;<?php endif; ?>
                            <i class="fa-regular fa-user"></i> <?= e($c['username']) ?>
                        </p>
                        <ul class="cc-stats">
                            <li class="<?= $hasPdf ? 'ok' : '' ?>"><i class="fa-solid fa-book-open"></i> <?= $hasPdf ? 'Album' : 'No album' ?></li>
                            <li class="<?= $photos ? 'ok' : '' ?>"><i class="fa-solid fa-image"></i> <?= plural($photos, 'photo') ?></li>
                            <li class="<?= $c['videos'] ? 'ok' : '' ?>"><i class="fa-solid fa-film"></i> <?= plural(count($c['videos']), 'film') ?></li>
                        </ul>
                    </div>
                    <div class="cc-actions">
                        <a class="btn btn-small" href="<?= e(edit_url($c['slug'])) ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                        <a class="btn btn-small btn-ghost" href="album.php?preview=<?= e(rawurlencode($c['slug'])) ?>" target="_blank"><i class="fa-solid fa-eye"></i> Preview</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($view === 'settings'): ?>
    <!-- ======================================================== settings -->
    <div class="adm-head"><div><h1>Settings</h1><p class="muted">Studio name, WhatsApp number and links are in <code>album-core/config.php</code>.</p></div></div>
    <form class="card narrow" method="post" action="admin.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
        <h2>Change admin password</h2>
        <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
        <label>New password <input type="password" name="new_password" minlength="8" autocomplete="new-password" required></label>
        <label>New password again <input type="password" name="new_password2" minlength="8" autocomplete="new-password" required></label>
        <button class="btn btn-primary">Save new password</button>
    </form>
    <div class="card narrow">
        <h2>Your hosting</h2>
        <ul class="facts">
            <li>PHP version: <b><?= e(PHP_VERSION) ?></b></li>
            <li>Photo resizing (GD): <b><?= function_exists('imagecreatetruecolor') ? 'Available ✔' : 'Not available – photos are shown at full size' ?></b></li>
            <li>Upload limit per piece: <b><?= e(format_bytes(upload_chunk_size())) ?></b> (bigger files are sent in pieces automatically)</li>
            <li>Watermark picture: <b><?= watermark_file() ? 'album-assets/img/watermark.png ✔' : 'Not found' ?></b></li>
        </ul>
    </div>

<?php else: ?>
    <!-- ======================================================== new / edit client -->
    <?php
    $isNew = $view === 'new';
    $slug = (string) $client['slug'];
    $standardEvents = ['Haldi', 'Mehendi', 'Sangeet', 'Wedding', 'Reception'];
    $chosenEvents = is_array($old) ? (array) ($old['events'] ?? []) : $client['events'];
    $chosenKeys = array_map('event_key', array_map('strval', $chosenEvents));
    $otherEvents = is_array($old)
        ? (string) ($old['events_other'] ?? '')
        : implode(', ', array_filter($client['events'], function ($ev) use ($standardEvents) {
            return !in_array(event_key((string) $ev), array_map('event_key', $standardEvents), true);
        }));
    $checked = function (string $key, bool $default) use ($old, $client) {
        if (is_array($old)) {
            return !empty($old[$key]) ? 'checked' : '';
        }
        return ($client[$key] ?? $default) ? 'checked' : '';
    };
    ?>
    <div class="adm-head">
        <div>
            <a class="back" href="admin.php"><i class="fa-solid fa-arrow-left"></i> All clients</a>
            <h1><?= $isNew ? 'New client' : e(couple_names($client)) ?></h1>
            <?php if (!$isNew): ?><p class="muted">Folder: <code>clients/<?= e($slug) ?></code></p><?php endif; ?>
        </div>
        <?php if (!$isNew): ?>
            <a class="btn btn-ghost" href="album.php?preview=<?= e(rawurlencode($slug)) ?>" target="_blank"><i class="fa-solid fa-eye"></i> Preview album</a>
        <?php endif; ?>
    </div>

    <?php if ($share):
        $shareText = "Namaste " . $share['names'] . "! 🎉\nYour wedding album from " . $studio . " is ready.\n\nOpen: " . $loginUrl
            . "\nUsername: " . $share['username'] . "\nPassword: " . $share['password'] . "\n\nWith love,\n" . $studio;
    ?>
        <div class="card share">
            <h2><i class="fa-solid fa-paper-plane"></i> Login details for <?= e($share['names']) ?></h2>
            <p class="muted">Send these to the couple. The password is shown only this one time.</p>
            <dl>
                <dt>Website</dt><dd><?= e($loginUrl) ?></dd>
                <dt>Username</dt><dd><?= e($share['username']) ?></dd>
                <dt>Password</dt><dd><?= e($share['password']) ?></dd>
            </dl>
            <div class="row">
                <button type="button" class="btn btn-small" data-copy="<?= e($shareText) ?>"><i class="fa-regular fa-copy"></i> Copy message</button>
                <a class="btn btn-small btn-wa" href="https://wa.me/?text=<?= e(rawurlencode($shareText)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Send on WhatsApp</a>
            </div>
        </div>
    <?php endif; ?>

    <form class="card" method="post" action="admin.php" id="clientForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_client">
        <input type="hidden" name="slug" value="<?= e($slug) ?>">

        <h2><span class="step">1</span> Wedding details</h2>
        <div class="grid">
            <label>Bride's name <input type="text" name="bride" value="<?= e(field('bride')) ?>" maxlength="60" required></label>
            <label>Groom's name <input type="text" name="groom" value="<?= e(field('groom')) ?>" maxlength="60" required></label>
            <label>Wedding date <input type="date" name="wedding_date" value="<?= e(field('wedding_date')) ?>"></label>
            <label>Venue <input type="text" name="venue" value="<?= e(field('venue')) ?>" maxlength="80" placeholder="Hotel or venue name"></label>
            <label>City <input type="text" name="city" value="<?= e(field('city')) ?>" maxlength="60" placeholder="e.g. Jaipur"></label>
        </div>
        <fieldset class="events">
            <legend>Events <small>(shown as colourful tags on the banner)</small></legend>
            <?php foreach ($standardEvents as $event): ?>
                <label class="check"><input type="checkbox" name="events[]" value="<?= e($event) ?>" <?= in_array(event_key($event), $chosenKeys, true) ? 'checked' : '' ?>> <?= e($event) ?></label>
            <?php endforeach; ?>
            <label class="wide">Other events <small>(separate with commas)</small>
                <input type="text" name="events_other" value="<?= e($otherEvents) ?>" placeholder="e.g. Mayra, Tilak, Baraat"></label>
        </fieldset>
        <label>Message for the couple <small>(optional – shown on the banner)</small>
            <textarea name="message" rows="3" maxlength="500" placeholder="<?= e(cfg('default_message')) ?>"><?= e(field('message')) ?></textarea></label>

        <h2><span class="step">2</span> Client login</h2>
        <div class="grid">
            <label>Username
                <input type="text" name="username" id="usernameInput" value="<?= e(field('username')) ?>" maxlength="40" pattern="[A-Za-z0-9._\-]{3,40}" autocomplete="off" autocapitalize="none" spellcheck="false" required <?= $isNew ? 'data-suggest' : '' ?>>
                <small>Letters and numbers, no spaces. Example: priyarahul</small></label>
            <label><?= $isNew ? 'Password' : 'New password <small>(leave empty to keep the current one)</small>' ?>
                <span class="pw-row">
                    <input type="text" name="password" minlength="6" maxlength="60" autocomplete="new-password" spellcheck="false" <?= $isNew ? 'required' : '' ?>>
                    <button type="button" class="btn btn-small" data-genpass><i class="fa-solid fa-wand-magic-sparkles"></i> Make one</button>
                </span>
                <small>You will see it once after saving, to send to the couple.</small></label>
        </div>

        <h2><span class="step">3</span> Album settings</h2>
        <div class="toggles">
            <label class="switch"><input type="checkbox" name="enabled" value="1" <?= $checked('enabled', true) ?>><span></span> Album is open (client can log in)</label>
            <label class="switch"><input type="checkbox" name="allow_download" value="1" <?= $checked('allow_download', false) ?>><span></span> Allow downloads (photos, films and PDF)</label>
            <label class="switch"><input type="checkbox" name="watermark" value="1" <?= $checked('watermark', false) ?>><span></span> "Devesh Studio" watermark on full-size photos</label>
        </div>
        <label class="narrow-field">PDF pages
            <select name="pdf_layout">
                <?php foreach (['auto' => 'Automatic (recommended)', 'spreads' => 'Every page is a 2-page spread – cut in half', 'single' => 'Every page is a single page'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= field('pdf_layout') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></label>

        <div class="form-actions">
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= $isNew ? 'Create client' : 'Save changes' ?></button>
        </div>
    </form>

    <?php if (!$isNew):
        $GLOBALS['ALBUM_PREVIEW'] = $slug;
        $coverFile = $client['cover'] !== '' ? client_file($client, (string) $client['cover']) : null;
        $pdfFile = $client['pdf'] !== '' ? client_file($client, (string) $client['pdf']) : null;
        $gallery = list_photos($client);
        $eventNames = array_column($gallery['events'], 'name', 'key');
        $folders = [];   // photo folder => [name, photos]
        foreach ($gallery['photos'] as $photo) {
            $folder = strpos($photo['f'], '/') !== false ? strstr($photo['f'], '/', true) : '';
            $folders[$folder]['name'] = $eventNames[$photo['e']] ?? $folder;
            $folders[$folder]['photos'][] = $photo;
        }
        $eventOptions = [];
        foreach (array_merge($client['events'], array_column($gallery['events'], 'name')) as $name) {
            $eventOptions[event_key((string) $name)] = (string) $name;
        }
    ?>
        <section class="card" id="cover">
            <h2><span class="step">4</span> Cover photo <small>(the big banner picture)</small></h2>
            <div class="media-row">
                <div class="cover-preview"<?= $coverFile ? ' style="background-image:url(\'' . e(media_url('cover', ['v' => filemtime($coverFile)])) . '\')"' : '' ?>>
                    <?php if (!$coverFile): ?><span>No cover yet</span><?php endif; ?>
                </div>
                <div class="uploader" data-kind="cover" data-anchor="cover">
                    <label class="drop"><input type="file" accept="image/jpeg,image/png,image/webp" hidden>
                        <i class="fa-solid fa-cloud-arrow-up"></i><b><?= $coverFile ? 'Change cover photo' : 'Choose cover photo' ?></b><span>or drag it here · a wide (landscape) photo looks best</span></label>
                    <div class="up-list"></div>
                </div>
            </div>
        </section>

        <section class="card" id="pdf">
            <h2><span class="step">5</span> PDF album <small>(shown as a book with turning pages)</small></h2>
            <?php if ($pdfFile): ?>
                <p class="file-line"><i class="fa-solid fa-file-pdf"></i> album.pdf · <?= e(format_bytes((int) filesize($pdfFile))) ?> · uploaded <?= e(date('j M Y, g:i a', (int) filemtime($pdfFile))) ?></p>
            <?php endif; ?>
            <div class="uploader" data-kind="pdf" data-anchor="pdf">
                <label class="drop"><input type="file" accept="application/pdf,.pdf" hidden>
                    <i class="fa-solid fa-book-open"></i><b><?= $pdfFile ? 'Replace PDF album' : 'Choose PDF album' ?></b><span>or drag it here · big files are fine, they upload in pieces</span></label>
                <div class="up-list"></div>
            </div>
        </section>

        <section class="card" id="photos">
            <h2><span class="step">6</span> Photos <small>(<?= plural(count($gallery['photos']), 'photo') ?>)</small></h2>
            <?php if ($folders): ?>
                <div class="event-list">
                    <?php foreach ($folders as $folder => $info): ?>
                        <div class="event-row">
                            <div class="event-name"><b><?= e($info['name'] ?: 'More Photos') ?></b> <span class="muted"><?= plural(count($info['photos']), 'photo') ?></span></div>
                            <div class="thumbs">
                                <?php foreach (array_slice($info['photos'], 0, 8) as $photo): ?>
                                    <img src="<?= e(media_url('photo', ['s' => 'thumb', 'f' => $photo['f'], 'v' => $photo['v']])) ?>" alt="" loading="lazy">
                                <?php endforeach; ?>
                                <?php if (count($info['photos']) > 8): ?><span class="more">+<?= count($info['photos']) - 8 ?></span><?php endif; ?>
                            </div>
                            <?php if ($folder !== ''): ?>
                                <form method="post" action="admin.php" data-confirm="Delete all <?= count($info['photos']) ?> photos of &quot;<?= e($info['name'] ?: $folder) ?>&quot;? This cannot be undone.">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="folder" value="<?= e((string) $folder) ?>">
                                    <button class="btn btn-small btn-danger-ghost" title="Delete these photos"><i class="fa-regular fa-trash-can"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="uploader" data-kind="photo" data-anchor="photos" data-multiple>
                <div class="event-pick">
                    <label>Event for these photos
                        <select data-event-select>
                            <option value="">— choose event —</option>
                            <?php foreach ($eventOptions as $name): ?><option><?= e($name) ?></option><?php endforeach; ?>
                            <option value="__new">+ New event…</option>
                        </select></label>
                    <label data-event-new-wrap hidden>New event name <input type="text" data-event-new maxlength="30" placeholder="e.g. Mayra"></label>
                </div>
                <label class="drop"><input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
                    <i class="fa-solid fa-images"></i><b>Choose photos</b><span>or drag many photos here · JPG photos about 2000–2500 px wide load fastest</span></label>
                <div class="up-list"></div>
            </div>
            <?php if ($gallery['photos']): ?>
                <p class="prep"><button type="button" class="btn btn-small btn-ghost" data-prepare-photos><i class="fa-solid fa-bolt"></i> Prepare photos for fast loading</button>
                    <span class="muted" id="prepStatus">Use this after copying photos by FTP or File Manager.</span></p>
            <?php endif; ?>
        </section>

        <section class="card" id="films">
            <h2><span class="step">7</span> Films</h2>
            <?php if ($client['videos']): ?>
                <ul class="film-list">
                    <?php foreach (video_cards($client) as $i => $card): $raw = $client['videos'][$i] ?? []; ?>
                        <li>
                            <span class="film-thumb"<?= $card['thumb'] ? ' style="background-image:url(\'' . e($card['thumb']) . '\')"' : '' ?>><i class="fa-solid fa-play"></i></span>
                            <span class="film-info"><b><?= e($card['title']) ?></b>
                                <span class="muted"><?= e(strtoupper($card['kind'] === 'mp4' ? 'MP4 upload' : $card['kind'])) ?><?= $card['duration'] ? ' · ' . e($card['duration']) : '' ?></span></span>
                            <?php if ($i > 0): ?>
                                <form method="post" action="admin.php"><?= csrf_field() ?><input type="hidden" name="action" value="video_up"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="index" value="<?= $i ?>">
                                    <button class="btn btn-small btn-ghost" title="Move up"><i class="fa-solid fa-arrow-up"></i></button></form>
                            <?php endif; ?>
                            <form method="post" action="admin.php" data-confirm="Remove the film &quot;<?= e($card['title']) ?>&quot;?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_video"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="index" value="<?= $i ?>">
                                <button class="btn btn-small btn-danger-ghost" title="Remove"><i class="fa-regular fa-trash-can"></i></button></form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="film-add">
                <form class="sub-card" method="post" action="admin.php">
                    <?= csrf_field() ?><input type="hidden" name="action" value="add_video"><input type="hidden" name="slug" value="<?= e($slug) ?>">
                    <h3><i class="fa-brands fa-youtube"></i> Add a YouTube / Vimeo link <small>(best for long films)</small></h3>
                    <label>Title <input type="text" name="video_title" maxlength="80" placeholder="e.g. Wedding Highlight"></label>
                    <label>Link <input type="url" name="video_link" placeholder="https://youtu.be/…" required></label>
                    <label>Length <small>(optional)</small> <input type="text" name="video_duration" maxlength="10" placeholder="e.g. 4:30"></label>
                    <button class="btn btn-small btn-primary"><i class="fa-solid fa-plus"></i> Add film</button>
                    <p class="hint">Tip: upload on YouTube as <b>Unlisted</b> – only people with the link can watch.</p>
                </form>
                <div class="sub-card uploader" data-kind="video" data-anchor="films">
                    <h3><i class="fa-solid fa-film"></i> Upload an MP4 film</h3>
                    <label>Title <input type="text" data-video-title maxlength="80" placeholder="e.g. Haldi Ceremony"></label>
                    <label class="drop"><input type="file" accept="video/mp4,video/quicktime,video/webm,.mp4,.m4v,.mov,.webm" hidden>
                        <i class="fa-solid fa-cloud-arrow-up"></i><b>Choose MP4 film</b><span>a cover picture is taken from the film automatically</span></label>
                    <div class="up-list"></div>
                </div>
            </div>
        </section>

        <section class="card danger" id="danger">
            <h2><i class="fa-solid fa-triangle-exclamation"></i> Close or delete</h2>
            <div class="danger-row">
                <form method="post" action="admin.php">
                    <?= csrf_field() ?><input type="hidden" name="action" value="toggle_client"><input type="hidden" name="slug" value="<?= e($slug) ?>">
                    <button class="btn btn-ghost"><?= $client['enabled'] ? '<i class="fa-solid fa-lock"></i> Close album (client cannot log in)' : '<i class="fa-solid fa-lock-open"></i> Open album again' ?></button>
                </form>
                <form method="post" action="admin.php" class="delete-form" data-confirm="Delete <?= e(couple_names($client)) ?> and ALL their photos, films and PDF? This cannot be undone.">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete_client"><input type="hidden" name="slug" value="<?= e($slug) ?>">
                    <label>Type <code><?= e($slug) ?></code> to delete everything <input type="text" name="confirm" autocomplete="off" required></label>
                    <button class="btn btn-danger"><i class="fa-regular fa-trash-can"></i> Delete client</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
</main>
<?php endif; ?>

<script>window.ADMIN = <?= json_encode([
    'csrf'      => $csrf,
    'slug'      => $client['slug'] ?? '',
    'chunkSize' => upload_chunk_size(),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="album-assets/js/admin.js?v=<?= ASSET_VERSION ?>"></script>
</body>
</html>
