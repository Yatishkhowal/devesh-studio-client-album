<?php
/**
 * Sends a client's private files (cover, PDF, photos, films) – only to that logged-in client.
 * Direct links into /clients are blocked by .htaccess, so every file passes through here.
 */
require_once __DIR__ . '/album-core/bootstrap.php';

album_session_start();
$client = null;
if (isset($_GET['c']) && is_admin()) {
    $client = load_client((string) $_GET['c']);   // admin preview
}
if (!$client) {
    $client = current_client();
}
session_write_close();   // let many photos load at the same time

if (!$client) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Please log in to view this album.');
}

$type = (string) ($_GET['t'] ?? '');
$rel = (string) ($_GET['f'] ?? '');
$download = !empty($_GET['dl']) && $client['allow_download'];
$niceName = slugify(couple_names($client, ' ')) ?: 'wedding';
$path = null;
$options = [];

switch ($type) {
    case 'cover':
        $src = $client['cover'] !== '' ? client_file($client, (string) $client['cover']) : null;
        if ($src && is_photo_name(basename($src))) {
            $path = resized_copy($client, $src, 'cover/' . basename($src) . '.jpg', 2400, 'box');
            $options = ['max_age' => 604800];
        }
        break;

    case 'pdf':
        $src = $client['pdf'] !== '' ? client_file($client, (string) $client['pdf']) : null;
        if ($src && strtolower(pathinfo($src, PATHINFO_EXTENSION)) === 'pdf') {
            $path = $src;
            $options = ['max_age' => 3600, 'download' => $download ? $niceName . '-wedding-album.pdf' : ''];
        }
        break;

    case 'photo':
        $size = (string) ($_GET['s'] ?? 'large');
        if (!in_array($size, ['thumb', 'large', 'original'], true) || ($size === 'original' && !$client['allow_download'])) {
            $size = 'large';
        }
        $path = photo_variant($client, $rel, $size);
        $options = ['max_age' => 604800, 'download' => $download ? $niceName . '-' . basename($rel) : ''];
        break;

    case 'poster':
        $src = client_file($client, $rel);
        if ($src && is_photo_name(basename($src))) {
            $path = $src;
            $options = ['max_age' => 604800];
        }
        break;

    case 'video':
        $src = client_file($client, $rel);
        if ($src && in_array(strtolower(pathinfo($src, PATHINFO_EXTENSION)), VIDEO_TYPES, true)) {
            $path = $src;
            $options = ['max_age' => 86400, 'range_cap' => 8 * 1048576, 'download' => $download ? $niceName . '-' . basename($src) : ''];
        }
        break;
}

if (!$path) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('File not found.');
}
send_file($path, mime_for($path), $options);
