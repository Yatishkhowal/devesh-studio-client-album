<?php
/**
 * Admin form handlers. Each one ends with a redirect (normal forms) or a JSON reply (uploads).
 */

function edit_url(string $slug, string $anchor = ''): string
{
    return 'admin.php?view=edit&c=' . rawurlencode($slug) . ($anchor !== '' ? '#' . $anchor : '');
}

/** The client named in the form, or back to the dashboard. */
function posted_client(): array
{
    $client = load_client((string) ($_POST['slug'] ?? ''));
    if (!$client) {
        flash('Client not found.', 'error');
        redirect('admin.php');
    }
    return $client;
}

function posted_text(string $key, int $max): string
{
    return clip((string) preg_replace('/\s+/u', ' ', (string) ($_POST[$key] ?? '')), $max);
}

/** Remember what was typed so the form is not empty after an error. */
function keep_form(): void
{
    $old = $_POST;
    unset($old['password'], $old['csrf']);
    $_SESSION['old'] = $old;
}

function unique_slug(string $base): string
{
    $base = substr($base !== '' ? $base : 'client', 0, 50);
    $base = trim($base, '-') ?: 'client';
    $slug = $base;
    for ($i = 2; is_dir(client_dir($slug)); $i++) {
        $slug = $base . '-' . $i;
    }
    return $slug;
}

/* ---------------------------------------------------------------- login */

function handle_setup(): void
{
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 8) {
        flash('Please use at least 8 characters for the admin password.', 'error');
    } elseif ($password !== (string) ($_POST['password2'] ?? '')) {
        flash('The two passwords are not the same.', 'error');
    } elseif (!admin_save_password($password)) {
        flash('Could not save the password. Please make sure the "clients" folder can be written to.', 'error');
    } else {
        admin_login_session();
        flash('Admin password created. Welcome!');
    }
    redirect('admin.php');
}

function handle_login(): void
{
    $keys = throttle_keys('#admin');
    $wait = throttle_wait($keys);
    if ($wait > 0) {
        flash('Too many wrong attempts. Please try again in ' . (int) ceil($wait / 60) . ' minute(s).', 'error');
    } elseif (password_verify((string) ($_POST['password'] ?? ''), admin_password_hash())) {
        throttle_clear($keys);
        admin_login_session();
    } else {
        throttle_fail($keys);
        flash('Wrong password.', 'error');
    }
    redirect('admin.php');
}

function handle_change_password(): void
{
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), admin_password_hash())) {
        flash('Your current password is not correct.', 'error');
    } elseif (strlen($new) < 8) {
        flash('Please use at least 8 characters.', 'error');
    } elseif ($new !== (string) ($_POST['new_password2'] ?? '')) {
        flash('The two new passwords are not the same.', 'error');
    } elseif (!admin_save_password($new)) {
        flash('Could not save the new password.', 'error');
    } else {
        admin_login_session();
        flash('Admin password changed.');
    }
    redirect('admin.php?view=settings');
}

/* ---------------------------------------------------------------- clients */

function handle_save_client(): void
{
    $slug = (string) ($_POST['slug'] ?? '');
    $isNew = $slug === '';
    $client = $isNew ? client_defaults() : load_client($slug);
    if (!$client) {
        flash('Client not found.', 'error');
        redirect('admin.php');
    }
    $back = $isNew ? 'admin.php?view=new' : edit_url($slug);
    $stop = function (string $message) use ($back) {
        flash($message, 'error');
        keep_form();
        redirect($back);
    };

    $bride = posted_text('bride', 60);
    $groom = posted_text('groom', 60);
    if ($bride === '' && $groom === '') {
        $stop('Please enter the bride and groom names.');
    }
    $date = posted_text('wedding_date', 10);
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = '';
    }
    $username = strtolower(posted_text('username', 40));
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) {
        $stop('Username must be 3 to 40 letters or numbers (dot and dash are fine, no spaces).');
    }
    $other = find_client_by_username($username);
    if ($other && $other['slug'] !== $slug) {
        $stop('The username "' . $username . '" is already used by ' . couple_names($other) . '. Please choose another one.');
    }
    $password = trim((string) ($_POST['password'] ?? ''));
    if ($isNew && $password === '') {
        $stop('Please set a password for the client.');
    }
    if ($password !== '' && strlen($password) < 6) {
        $stop('The password should have at least 6 characters.');
    }

    $events = [];
    foreach ((array) ($_POST['events'] ?? []) as $event) {
        $events[] = clip((string) $event, 30);
    }
    foreach (explode(',', (string) ($_POST['events_other'] ?? '')) as $event) {
        $events[] = clip($event, 30);
    }
    $unique = [];
    foreach ($events as $event) {
        if ($event !== '') {
            $unique[event_key($event) ?: $event] = $event;
        }
    }

    $client['bride'] = $bride;
    $client['groom'] = $groom;
    $client['wedding_date'] = $date;
    $client['venue'] = posted_text('venue', 80);
    $client['city'] = posted_text('city', 60);
    $client['events'] = array_values($unique);
    $client['message'] = clip(str_replace("\r", '', (string) ($_POST['message'] ?? '')), 500);
    $client['username'] = $username;
    $client['enabled'] = !empty($_POST['enabled']);
    $client['allow_download'] = !empty($_POST['allow_download']);
    $client['watermark'] = !empty($_POST['watermark']);
    $layout = (string) ($_POST['pdf_layout'] ?? 'auto');
    $client['pdf_layout'] = in_array($layout, ['auto', 'single', 'spreads'], true) ? $layout : 'auto';
    if ($password !== '') {
        $client['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($isNew) {
        $slug = unique_slug(slugify($bride . '-' . $groom) ?: slugify($username));
        $client['created'] = date('Y-m-d');
        ensure_dir(client_dir($slug) . '/photos');
        ensure_dir(client_dir($slug) . '/videos');
    }
    if (!save_client($slug, $client)) {
        $stop('Could not save. Please make sure the "clients" folder can be written to.');
    }
    if ($password !== '') {
        // Shown once on the next page so it can be sent to the couple.
        $_SESSION['share'] = ['slug' => $slug, 'names' => couple_names($client), 'username' => $username, 'password' => $password];
    }
    flash($isNew ? 'Client created! Now add the cover photo, PDF album, photos and films below.' : 'Changes saved.');
    redirect(edit_url($slug));
}

function handle_toggle_client(): void
{
    $client = posted_client();
    $client['enabled'] = !$client['enabled'];
    save_client($client['slug'], $client);
    flash($client['enabled'] ? 'Album is open for the client again.' : 'Album is closed. The client cannot log in now.');
    redirect(edit_url($client['slug']));
}

function handle_delete_client(): void
{
    $client = posted_client();
    if (trim((string) ($_POST['confirm'] ?? '')) !== $client['slug']) {
        flash('To delete, type the folder name exactly: ' . $client['slug'], 'error');
        redirect(edit_url($client['slug'], 'danger'));
    }
    remove_tree(client_dir($client['slug']));
    flash('Deleted ' . couple_names($client) . ' and all their files.');
    redirect('admin.php');
}

/* ---------------------------------------------------------------- films */

function handle_add_video(): void
{
    $client = posted_client();
    $src = trim((string) ($_POST['video_link'] ?? ''));
    if (youtube_id($src)) {
        $type = 'youtube';
    } elseif (vimeo_parts($src)) {
        $type = 'vimeo';
    } else {
        flash('That does not look like a YouTube or Vimeo link.', 'error');
        redirect(edit_url($client['slug'], 'films'));
    }
    $client['videos'][] = [
        'title'    => posted_text('video_title', 80) ?: 'Wedding Film',
        'type'     => $type,
        'src'      => $src,
        'duration' => posted_text('video_duration', 10),
    ];
    save_client($client['slug'], $client);
    flash('Film added.');
    redirect(edit_url($client['slug'], 'films'));
}

function handle_video_change(string $action): void
{
    $client = posted_client();
    $videos = $client['videos'];
    $i = (int) ($_POST['index'] ?? -1);
    if (isset($videos[$i])) {
        if ($action === 'remove_video') {
            if (in_array($videos[$i]['type'] ?? '', ['mp4', 'video'], true)) {
                foreach ([(string) ($videos[$i]['src'] ?? ''), (string) ($videos[$i]['thumb'] ?? '')] as $rel) {
                    $file = $rel !== '' ? client_file($client, $rel) : null;
                    if ($file) {
                        @unlink($file);
                    }
                }
            }
            array_splice($videos, $i, 1);
            flash('Film removed.');
        } elseif ($action === 'video_up' && $i > 0) {
            [$videos[$i - 1], $videos[$i]] = [$videos[$i], $videos[$i - 1]];
        }
        $client['videos'] = array_values($videos);
        save_client($client['slug'], $client);
    }
    redirect(edit_url($client['slug'], 'films'));
}

/* ---------------------------------------------------------------- photos */

function handle_delete_event(): void
{
    $client = posted_client();
    $folder = (string) ($_POST['folder'] ?? '');
    if ($folder !== '' && $folder[0] !== '.' && $folder[0] !== '_' && strpbrk($folder, "/\\\0") === false && strpos($folder, '..') === false) {
        remove_tree(client_dir($client['slug']) . '/photos/' . $folder);
        foreach (glob(client_dir($client['slug']) . '/_cache/*', GLOB_ONLYDIR) ?: [] as $cache) {
            remove_tree($cache . '/' . $folder);
        }
        flash('Deleted the photos of "' . $folder . '".');
    }
    redirect(edit_url($client['slug'], 'photos'));
}

/** Photos whose fast-loading copies are not made yet (used by "Prepare photos"). */
function handle_photo_jobs(): void
{
    $client = load_client((string) ($_POST['slug'] ?? ''));
    if (!$client) {
        json_out(['ok' => false, 'error' => 'Client not found.'], 404);
    }
    $cache = client_dir($client['slug']) . '/_cache/';
    $jobs = [];
    foreach (list_photos($client)['photos'] as $photo) {
        foreach (photo_cache_names($client, $photo['f']) as $name) {
            if (!is_file($cache . $name) || filemtime($cache . $name) < $photo['v']) {
                $jobs[] = $photo['f'];
                break;
            }
        }
    }
    json_out(['ok' => true, 'jobs' => $jobs, 'gd' => function_exists('imagecreatetruecolor')]);
}

function handle_make_thumb(): void
{
    $client = load_client((string) ($_POST['slug'] ?? ''));
    $rel = (string) ($_POST['f'] ?? '');
    if (!$client || !photo_variant($client, $rel, 'thumb')) {
        json_out(['ok' => false, 'error' => 'Photo not found.'], 404);
    }
    photo_variant($client, $rel, 'large');
    json_out(['ok' => true]);
}

/* ---------------------------------------------------------------- uploads */

function upload_error_text(int $code): string
{
    $texts = [
        UPLOAD_ERR_INI_SIZE   => 'The file part is bigger than your hosting allows.',
        UPLOAD_ERR_FORM_SIZE  => 'The file part is too big.',
        UPLOAD_ERR_PARTIAL    => 'The upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was received.',
        UPLOAD_ERR_NO_TMP_DIR => 'The hosting has no temporary folder for uploads.',
        UPLOAD_ERR_CANT_WRITE => 'The hosting could not save the upload (disk full?).',
    ];
    return $texts[$code] ?? 'The upload failed.';
}

/**
 * Receives one piece of a file. Big files (PDF albums, films) arrive in several pieces
 * so the hosting's upload limit never gets in the way. The last piece puts the file in place.
 */
function handle_upload_chunk(): void
{
    $client = load_client((string) ($_POST['slug'] ?? ''));
    if (!$client) {
        json_out(['ok' => false, 'error' => 'Client not found.'], 404);
    }
    $kinds = ['cover' => PHOTO_TYPES, 'photo' => PHOTO_TYPES, 'poster' => PHOTO_TYPES, 'pdf' => ['pdf'], 'video' => VIDEO_TYPES];
    $kind = (string) ($_POST['kind'] ?? '');
    if (!isset($kinds[$kind])) {
        json_out(['ok' => false, 'error' => 'Unknown upload type.'], 400);
    }
    $name = safe_filename((string) ($_POST['name'] ?? ''), $kinds[$kind]);
    if (!$name) {
        json_out(['ok' => false, 'error' => 'This type of file is not allowed here.'], 400);
    }
    $id = (string) ($_POST['upload_id'] ?? '');
    $index = (int) ($_POST['index'] ?? -1);
    $total = (int) ($_POST['total'] ?? 0);
    $offset = (int) ($_POST['offset'] ?? -1);
    $size = (int) ($_POST['size'] ?? -1);
    if (!preg_match('/^[a-z0-9]{16,40}$/', $id) || $index < 0 || $total < 1 || $index >= $total || $offset < 0 || $size < 0) {
        json_out(['ok' => false, 'error' => 'Bad upload data.'], 400);
    }
    $piece = $_FILES['chunk'] ?? null;
    $error = (int) ($piece['error'] ?? UPLOAD_ERR_NO_FILE);
    if (!$piece || $error !== UPLOAD_ERR_OK || !is_uploaded_file($piece['tmp_name'])) {
        json_out(['ok' => false, 'error' => upload_error_text($error)], 400);
    }

    $dir = DATA_DIR . '/uploads';
    if (!ensure_dir($dir)) {
        json_out(['ok' => false, 'error' => 'The upload folder cannot be written to.'], 500);
    }
    $part = "$dir/$id.part";
    if ($index === 0) {
        foreach (glob("$dir/*.part") ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                @unlink($old);   // forgotten half uploads
            }
        }
        @unlink($part);
    }
    clearstatcache();
    $have = is_file($part) ? (int) filesize($part) : 0;
    if ($have !== $offset) {
        json_out(['ok' => false, 'error' => 'The upload got mixed up. Please try again.', 'have' => $have], 409);
    }
    $in = fopen($piece['tmp_name'], 'rb');
    $out = fopen($part, 'ab');
    if (!$in || !$out) {
        json_out(['ok' => false, 'error' => 'Could not save the upload.'], 500);
    }
    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);

    if ($index < $total - 1) {
        json_out(['ok' => true]);
    }
    clearstatcache();
    if ((int) filesize($part) !== $size) {
        @unlink($part);
        json_out(['ok' => false, 'error' => 'The upload was not complete. Please try again.'], 400);
    }
    $result = finish_upload($client, $kind, $name, $part);
    json_out($result, $result['ok'] ? 200 : 400);
}

function finish_upload(array $client, string $kind, string $name, string $part): array
{
    $dir = client_dir($client['slug']);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $fail = function (string $message) use ($part) {
        @unlink($part);
        return ['ok' => false, 'error' => $message];
    };
    $head = (string) @file_get_contents($part, false, null, 0, 1024);
    $extra = [];

    switch ($kind) {
        case 'cover':
            if (!@getimagesize($part)) {
                return $fail('This does not look like a photo.');
            }
            $old = $client['cover'] !== '' ? client_file($client, (string) $client['cover']) : null;
            if ($old) {
                @unlink($old);
            }
            if (!@rename($part, "$dir/cover.$ext")) {
                return $fail('Could not save the cover photo.');
            }
            $client['cover'] = "cover.$ext";
            break;

        case 'pdf':
            if (strpos($head, '%PDF') === false) {
                return $fail('This does not look like a PDF file.');
            }
            @unlink("$dir/album.pdf");
            if (!@rename($part, "$dir/album.pdf")) {
                return $fail('Could not save the PDF.');
            }
            $client['pdf'] = 'album.pdf';
            break;

        case 'photo':
            if (!@getimagesize($part)) {
                return $fail('This does not look like a photo.');
            }
            $event = clip((string) ($_POST['event'] ?? ''), 30);
            $folder = event_key($event) ?: 'photos';
            if (!ensure_dir("$dir/photos/$folder")) {
                return $fail('Could not create the photo folder.');
            }
            $dest = unique_path("$dir/photos/$folder", $name);
            if (!@rename($part, $dest)) {
                return $fail('Could not save the photo.');
            }
            $rel = $folder . '/' . basename($dest);
            photo_variant($client, $rel, 'thumb');   // ready for fast viewing straight away
            photo_variant($client, $rel, 'large');
            if ($event === '' || in_array(event_key($event), array_map('event_key', $client['events']), true)) {
                return ['ok' => true];
            }
            $client = load_client($client['slug']) ?: $client;   // fresh copy: other photos may be saving too
            $client['events'][] = $event;
            break;

        case 'video':
            if (substr($head, 4, 4) !== 'ftyp' && strncmp($head, "\x1A\x45\xDF\xA3", 4) !== 0) {
                return $fail('This does not look like an MP4 video.');
            }
            if (!ensure_dir("$dir/videos")) {
                return $fail('Could not create the films folder.');
            }
            $dest = unique_path("$dir/videos", $name);
            if (!@rename($part, $dest)) {
                return $fail('Could not save the film.');
            }
            $client['videos'][] = [
                'title'    => posted_text('title', 80) ?: 'Wedding Film',
                'type'     => 'mp4',
                'src'      => 'videos/' . basename($dest),
                'duration' => posted_text('duration', 10),
            ];
            $extra['video'] = count($client['videos']) - 1;
            break;

        case 'poster':
            $i = (int) ($_POST['video'] ?? -1);
            if (!isset($client['videos'][$i]) || !@getimagesize($part)) {
                return $fail('Could not add the film picture.');
            }
            $base = pathinfo((string) ($client['videos'][$i]['src'] ?? 'film'), PATHINFO_FILENAME) ?: 'film';
            $dest = unique_path("$dir/videos", $base . '-poster.' . $ext);
            if (!ensure_dir("$dir/videos") || !@rename($part, $dest)) {
                return $fail('Could not save the film picture.');
            }
            $client['videos'][$i]['thumb'] = 'videos/' . basename($dest);
            break;
    }

    if (!save_client($client['slug'], $client)) {
        return ['ok' => false, 'error' => 'The file was saved, but the client details could not be updated.'];
    }
    return ['ok' => true] + $extra;
}
