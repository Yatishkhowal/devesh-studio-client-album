<?php
/**
 * Devesh Studio – Client Album
 * Shared code for client-login.php, album.php, media.php, logout.php and admin.php.
 * Plain PHP 7.4+ with no database: every client is one folder inside /clients.
 */

define('ALBUM_ROOT', dirname(__DIR__));
define('CLIENTS_DIR', ALBUM_ROOT . '/clients');
define('DATA_DIR', CLIENTS_DIR . '/_data');
define('ASSET_VERSION', '1.0.0');

const PHOTO_TYPES = ['jpg', 'jpeg', 'png', 'webp'];
const VIDEO_TYPES = ['mp4', 'm4v', 'mov', 'webm'];

$GLOBALS['ALBUM_CONFIG'] = require __DIR__ . '/config.php';
@date_default_timezone_set((string) ($GLOBALS['ALBUM_CONFIG']['timezone'] ?? 'Asia/Kolkata'));


/* =====================================================================
   Small helpers
   ===================================================================== */

function cfg(string $key, $default = null)
{
    return $GLOBALS['ALBUM_CONFIG'][$key] ?? $default;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Shorten text without breaking Hindi letters or emoji. */
function clip(string $text, int $max): string
{
    $text = trim($text);
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

function slugify(string $text): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Web folder of these pages: "/" (or "/site/" if the website lives in a sub-folder). */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    return rtrim($dir, '/') . '/';
}

function site_url(string $page = ''): string
{
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return (is_https() ? 'https' : 'http') . '://' . $host . base_path() . $page;
}

function redirect(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Headers for every private HTML page: never cached, never indexed by Google. */
function page_headers(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');   // YouTube needs the site origin to play embeds
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function ensure_dir(string $dir): bool
{
    return is_dir($dir) || @mkdir($dir, 0755, true) || is_dir($dir);
}

function remove_tree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            remove_tree($path . '/' . $entry);
        }
    }
    @rmdir($path);
}

function read_json(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/** Save JSON safely: write a temporary file first, then swap it in. */
function write_json(string $file, array $data, bool $pretty = true): bool
{
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | ($pretty ? JSON_PRETTY_PRINT : 0);
    $json = json_encode($data, $flags);
    if ($json === false || !ensure_dir(dirname($file))) {
        return false;
    }
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        return false;
    }
    if (@rename($tmp, $file)) {
        return true;
    }
    @unlink($tmp);
    return false;
}

function ini_bytes(string $key): int
{
    $value = trim((string) ini_get($key));
    if ($value === '' || $value === '-1') {
        return 0;
    }
    $number = (float) $value;
    switch (strtolower(substr($value, -1))) {
        case 'g': $number *= 1024;   // no break
        case 'm': $number *= 1024;   // no break
        case 'k': $number *= 1024;
    }
    return (int) $number;
}

function whatsapp_link(string $text = ''): string
{
    $number = preg_replace('/\D+/', '', (string) cfg('whatsapp_number', ''));
    $text = $text !== '' ? $text : (string) cfg('whatsapp_message', '');
    return 'https://wa.me/' . $number . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function couple_names(array $client, string $joiner = ' & '): string
{
    $names = array_filter([trim((string) $client['bride']), trim((string) $client['groom'])], 'strlen');
    return $names ? implode($joiner, $names) : 'Our Wedding';
}

/** "1 photo", "26 photos" */
function plural(int $count, string $word): string
{
    return $count . ' ' . $word . ($count === 1 ? '' : 's');
}

function pretty_date(string $ymd, string $format = 'l, j F Y'): string
{
    $time = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) ? strtotime($ymd) : false;
    return $time ? date($format, $time) : '';
}

function event_key(string $name): string
{
    return slugify($name);
}

/** Colour of an event tag. Common Rajasthani wedding events get their own colour. */
function event_tone(string $name, int $index): string
{
    $map = [
        'haldi' => 'yellow', 'mayra' => 'yellow', 'bhaat' => 'yellow',
        'mehendi' => 'teal', 'mehndi' => 'teal', 'engagement' => 'teal', 'sagai' => 'teal',
        'sangeet' => 'purple', 'ring-ceremony' => 'purple', 'pre-wedding' => 'purple',
        'wedding' => 'pink', 'shaadi' => 'pink', 'vivah' => 'pink', 'pheras' => 'pink', 'phere' => 'pink',
        'reception' => 'orange', 'baraat' => 'orange', 'tilak' => 'orange',
    ];
    $key = event_key($name);
    if (isset($map[$key])) {
        return $map[$key];
    }
    $cycle = ['pink', 'orange', 'teal', 'purple', 'yellow'];
    return $cycle[$index % count($cycle)];
}

/** Small marigold used as a section divider. */
function flower_svg(): string
{
    $petals = '';
    for ($angle = 0; $angle < 360; $angle += 45) {
        $fill = ($angle / 45) % 2 ? '#FB8500' : '#FF4D8D';
        $petals .= '<ellipse cx="0" cy="-9" rx="4" ry="7" fill="' . $fill . '" transform="rotate(' . $angle . ')"/>';
    }
    return '<svg viewBox="-17 -17 34 34" width="32" height="32" aria-hidden="true">' . $petals
        . '<circle r="5.5" fill="#FFB703"/><circle r="2.2" fill="#E85D04"/></svg>';
}


/* =====================================================================
   Sessions
   ===================================================================== */

function album_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $minutes = max(10, (int) cfg('session_timeout_minutes', 120));
    // Our own session folder, so shared hosting never deletes a session too early.
    $dir = DATA_DIR . '/sessions';
    if (ensure_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '50');
    }
    ini_set('session.gc_maxlifetime', (string) ($minutes * 60 + 600));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_cache_limiter('');   // each page sends its own cache headers
    session_name('DS_ALBUM');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path(),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Changes whenever the client's password or username changes, which logs out old sessions. */
function password_sig(array $client): string
{
    return substr(hash('sha256', $client['password_hash'] . '|' . strtolower((string) $client['username'])), 0, 24);
}

function login_client(array $client): void
{
    session_regenerate_id(true);
    $_SESSION['client'] = $client['slug'];
    $_SESSION['pw_sig'] = password_sig($client);
    $_SESSION['last_seen'] = time();
    $_SESSION['just_logged_in'] = true;
}

function logout_client(): void
{
    unset($_SESSION['client'], $_SESSION['pw_sig'], $_SESSION['last_seen'], $_SESSION['just_logged_in']);
}

/** The logged-in client, or null. Also applies the idle timeout. */
function current_client(): ?array
{
    $slug = $_SESSION['client'] ?? '';
    if (!is_string($slug) || $slug === '') {
        return null;
    }
    $timeout = max(10, (int) cfg('session_timeout_minutes', 120)) * 60;
    if (time() - (int) ($_SESSION['last_seen'] ?? 0) > $timeout) {
        logout_client();
        $_SESSION['expired'] = true;
        return null;
    }
    $client = load_client($slug);
    if (!$client || !$client['enabled'] || !hash_equals(password_sig($client), (string) ($_SESSION['pw_sig'] ?? ''))) {
        logout_client();
        return null;
    }
    if (time() - (int) $_SESSION['last_seen'] > 60) {
        $_SESSION['last_seen'] = time();
    }
    return $client;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}


/* =====================================================================
   Admin login
   ===================================================================== */

function admin_password_hash(): string
{
    return (string) (read_json(DATA_DIR . '/admin.json')['password_hash'] ?? '');
}

function admin_save_password(string $plain): bool
{
    return write_json(DATA_DIR . '/admin.json', [
        'password_hash' => password_hash($plain, PASSWORD_DEFAULT),
        'updated'       => date('c'),
    ]);
}

function admin_sig(): string
{
    return substr(hash('sha256', admin_password_hash() . '|admin'), 0, 24);
}

function admin_login_session(): void
{
    session_regenerate_id(true);
    $_SESSION['admin'] = admin_sig();
    $_SESSION['admin_seen'] = time();
}

function is_admin(): bool
{
    $sig = $_SESSION['admin'] ?? '';
    if (!is_string($sig) || $sig === '' || admin_password_hash() === '' || !hash_equals(admin_sig(), $sig)) {
        return false;
    }
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > 4 * 3600) {
        unset($_SESSION['admin'], $_SESSION['admin_seen']);
        return false;
    }
    $_SESSION['admin_seen'] = time();
    return true;
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}


/* =====================================================================
   Wrong-password protection
   ===================================================================== */

function client_ip(): string
{
    $header = (string) cfg('real_ip_header', '');
    if ($header !== '' && !empty($_SERVER[$header])) {
        $ip = trim(explode(',', (string) $_SERVER[$header])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** One counter per (IP + username) and a looser one per IP. */
function throttle_keys(string $username): array
{
    $ip = client_ip();
    return [
        'user' => 'u' . substr(sha1($ip . '|' . strtolower(trim($username))), 0, 20),
        'ip'   => 'i' . substr(sha1($ip), 0, 20),
    ];
}

function throttle_update(callable $change)
{
    ensure_dir(DATA_DIR);
    $fh = @fopen(DATA_DIR . '/login-attempts.json', 'c+');
    if (!$fh) {
        $data = [];
        return $change($data);
    }
    flock($fh, LOCK_EX);
    $data = json_decode((string) stream_get_contents($fh), true);
    $data = is_array($data) ? $data : [];
    $now = time();
    foreach ($data as $key => $row) {
        if ((int) ($row['t'] ?? 0) < $now - 86400 && (int) ($row['until'] ?? 0) < $now) {
            unset($data[$key]);
        }
    }
    $result = $change($data);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string) json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

/** Seconds the visitor still has to wait, or 0. */
function throttle_wait(array $keys): int
{
    return (int) throttle_update(function (array &$data) use ($keys) {
        $wait = 0;
        foreach ($keys as $key) {
            $wait = max($wait, (int) ($data[$key]['until'] ?? 0) - time());
        }
        return max(0, $wait);
    });
}

/** Count a wrong password. Returns how many tries are left for this username. */
function throttle_fail(array $keys): int
{
    $max = max(3, (int) cfg('max_login_attempts', 5));
    $lock = max(1, (int) cfg('lockout_minutes', 10)) * 60;
    return (int) throttle_update(function (array &$data) use ($keys, $max, $lock) {
        $now = time();
        $left = $max;
        foreach ($keys as $type => $key) {
            $limit = $type === 'ip' ? $max * 6 : $max;
            $row = $data[$key] ?? ['n' => 0, 't' => 0, 'until' => 0];
            if ((int) $row['t'] < $now - $lock) {
                $row['n'] = 0;   // old mistakes are forgotten
            }
            $row['n'] = (int) $row['n'] + 1;
            $row['t'] = $now;
            if ($row['n'] >= $limit) {
                $row['until'] = $now + $lock;
                $row['n'] = 0;
            }
            $data[$key] = $row;
            if ($type === 'user') {
                $left = (int) $row['until'] > $now ? 0 : $limit - $row['n'];
            }
        }
        return $left;
    });
}

function throttle_clear(array $keys): void
{
    throttle_update(function (array &$data) use ($keys) {
        unset($data[$keys['user']]);
        return null;
    });
}


/* =====================================================================
   Clients (one folder per wedding, details in data.json)
   ===================================================================== */

function valid_slug(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $slug);
}

function client_dir(string $slug): string
{
    return CLIENTS_DIR . '/' . $slug;
}

function client_defaults(): array
{
    return [
        'username'       => '',
        'password_hash'  => '',
        'enabled'        => true,
        'bride'          => '',
        'groom'          => '',
        'wedding_date'   => '',
        'venue'          => '',
        'city'           => '',
        'events'         => [],
        'message'        => '',
        'cover'          => '',
        'pdf'            => '',
        'pdf_layout'     => 'auto',
        'allow_download' => false,
        'watermark'      => false,
        'videos'         => [],
        'created'        => '',
    ];
}

function load_client(string $slug): ?array
{
    if (!valid_slug($slug)) {
        return null;
    }
    $file = client_dir($slug) . '/data.json';
    $data = read_json($file);
    if (!$data) {
        return null;
    }
    // A plain "password" typed into data.json by hand is turned into a secure hash straight away.
    if (isset($data['password']) && (string) $data['password'] !== '') {
        $data['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        unset($data['password']);
        save_client($slug, $data);
    }
    unset($data['password']);
    $client = array_merge(client_defaults(), $data);
    $client['slug'] = $slug;
    foreach (['enabled', 'allow_download', 'watermark'] as $flag) {
        $client[$flag] = (bool) $client[$flag];
    }
    foreach (['events', 'videos'] as $list) {
        $client[$list] = is_array($client[$list]) ? array_values($client[$list]) : [];
    }
    if (!in_array($client['pdf_layout'], ['auto', 'single', 'spreads'], true)) {
        $client['pdf_layout'] = 'auto';
    }
    return $client;
}

function save_client(string $slug, array $data): bool
{
    if (!valid_slug($slug)) {
        return false;
    }
    unset($data['slug']);
    return write_json(client_dir($slug) . '/data.json', $data);
}

function all_clients(): array
{
    $clients = [];
    foreach (glob(CLIENTS_DIR . '/*/data.json') ?: [] as $file) {
        $client = load_client(basename(dirname($file)));
        if ($client) {
            $clients[] = $client;
        }
    }
    usort($clients, function ($a, $b) {
        return strcmp((string) $b['wedding_date'], (string) $a['wedding_date']) ?: strcmp($a['slug'], $b['slug']);
    });
    return $clients;
}

function find_client_by_username(string $username): ?array
{
    $username = strtolower(trim($username));
    if ($username === '') {
        return null;
    }
    foreach (all_clients() as $client) {
        if (strtolower((string) $client['username']) === $username) {
            return $client;
        }
    }
    return null;
}

/** Full path of a file inside the client's folder, or null if it is missing or not allowed. */
function client_file(array $client, string $relative): ?string
{
    $relative = str_replace('\\', '/', trim($relative));
    if ($relative === '' || strpos($relative, "\0") !== false || strpos($relative, '..') !== false) {
        return null;
    }
    foreach (explode('/', $relative) as $part) {
        if ($part === '' || $part[0] === '.' || $part[0] === '_') {
            return null;   // hidden files and our own _cache / _data folders are never served
        }
    }
    if (strtolower($relative) === 'data.json') {
        return null;
    }
    $base = realpath(client_dir($client['slug']));
    $path = $base ? realpath($base . '/' . $relative) : false;
    if (!$base || !$path || !is_file($path) || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $path;
}

/** Link to a private file, served by media.php after the login check. */
function media_url(string $type, array $params = []): string
{
    $query = ['t' => $type] + $params;
    if (!empty($GLOBALS['ALBUM_PREVIEW'])) {
        $query['c'] = $GLOBALS['ALBUM_PREVIEW'];   // admin preview of a client's album
    }
    return 'media.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function mime_for(string $path): string
{
    $types = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
    ];
    return $types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}


/* =====================================================================
   Photos
   ===================================================================== */

function is_photo_name(string $name): bool
{
    return $name !== '' && $name[0] !== '.' && $name[0] !== '_'
        && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), PHOTO_TYPES, true);
}

/**
 * All photos of a client, grouped by event folder (photos/haldi, photos/wedding, …).
 * Picture sizes are remembered in _cache/index.json so the page stays fast.
 */
function list_photos(array $client): array
{
    $root = client_dir($client['slug']) . '/photos';
    $result = ['events' => [], 'photos' => []];
    if (!is_dir($root)) {
        return $result;
    }

    $labels = [];
    foreach ($client['events'] as $name) {
        $labels[event_key((string) $name)] = trim((string) $name);
    }
    $order = array_flip(array_keys($labels));

    $folders = [];
    $loose = [];
    foreach (scandir($root) ?: [] as $entry) {
        if ($entry === '' || $entry[0] === '.' || $entry[0] === '_') {
            continue;
        }
        if (is_dir("$root/$entry")) {
            $folders[] = $entry;
        } elseif (is_photo_name($entry)) {
            $loose[] = $entry;
        }
    }
    usort($folders, function ($a, $b) use ($order) {
        $ia = $order[event_key($a)] ?? 999;
        $ib = $order[event_key($b)] ?? 999;
        return $ia === $ib ? strnatcasecmp($a, $b) : $ia - $ib;
    });

    $indexFile = client_dir($client['slug']) . '/_cache/index.json';
    $index = read_json($indexFile);
    $seen = [];
    $changed = false;

    $collect = function (string $key, string $label, string $prefix, array $files) use (&$result, &$index, &$seen, &$changed, $root) {
        natcasesort($files);
        $count = 0;
        foreach ($files as $file) {
            $rel = $prefix . $file;
            $mtime = (int) @filemtime("$root/$rel");
            $row = $index[$rel] ?? null;
            if (!is_array($row) || (int) ($row[2] ?? 0) !== $mtime) {
                $size = image_size_oriented("$root/$rel");
                if (!$size) {
                    continue;
                }
                $row = [$size[0], $size[1], $mtime];
                $index[$rel] = $row;
                $changed = true;
            }
            $seen[$rel] = true;
            $result['photos'][] = ['e' => $key, 'f' => $rel, 'w' => (int) $row[0], 'h' => (int) $row[1], 'v' => $mtime];
            $count++;
        }
        if ($count) {
            $result['events'][] = ['key' => $key, 'name' => $label, 'count' => $count];
        }
    };

    foreach ($folders as $folder) {
        $files = array_values(array_filter(scandir("$root/$folder") ?: [], 'is_photo_name'));
        $label = $labels[event_key($folder)] ?? ucwords(trim(str_replace(['-', '_'], ' ', $folder)));
        $collect(event_key($folder) ?: 'photos', $label, $folder . '/', $files);
    }
    if ($loose) {
        $collect('more', 'More Photos', '', $loose);
    }

    foreach (array_keys($index) as $rel) {
        if (!isset($seen[$rel])) {
            unset($index[$rel]);
            $changed = true;
        }
    }
    if ($changed) {
        write_json($indexFile, $index, false);
    }
    return $result;
}

function exif_orientation(string $path): int
{
    if (!function_exists('exif_read_data') || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true)) {
        return 1;
    }
    $exif = @exif_read_data($path);
    $value = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    return $value >= 1 && $value <= 8 ? $value : 1;
}

/** Width and height as the photo is shown (phone photos can be stored sideways). */
function image_size_oriented(string $path): ?array
{
    $info = @getimagesize($path);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return null;
    }
    return in_array(exif_orientation($path), [5, 6, 7, 8], true) ? [$info[1], $info[0]] : [$info[0], $info[1]];
}

function apply_orientation($image, int $orientation)
{
    if (in_array($orientation, [2, 5, 7], true) && function_exists('imageflip')) {
        imageflip($image, IMG_FLIP_HORIZONTAL);
    }
    if ($orientation === 4 && function_exists('imageflip')) {
        imageflip($image, IMG_FLIP_VERTICAL);
    }
    $angle = [3 => 180, 5 => 90, 6 => 270, 7 => 270, 8 => 90][$orientation] ?? 0;
    if ($angle) {
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated) {
            imagedestroy($image);
            $image = $rotated;
        }
    }
    return $image;
}

function memory_limit_bytes(): int
{
    return ini_bytes('memory_limit') ?: PHP_INT_MAX;
}

/** Ask for more memory when a big photo needs it. False if the hosting does not allow it. */
function ensure_memory(int $bytes): bool
{
    $need = memory_get_usage() + $bytes;
    if ($need <= memory_limit_bytes()) {
        return true;
    }
    @ini_set('memory_limit', (int) ceil($need / 1048576 + 32) . 'M');
    return $need <= memory_limit_bytes();
}

function watermark_file(): ?string
{
    $file = ALBUM_ROOT . '/album-assets/img/watermark.png';
    return is_file($file) ? $file : null;
}

function stamp_watermark($image): void
{
    $file = watermark_file();
    $mark = $file && function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false;
    if (!$mark) {
        return;
    }
    $width = imagesx($image);
    $height = imagesy($image);
    $markWidth = (int) max(150, round($width * 0.24));
    $markHeight = (int) round(imagesy($mark) * $markWidth / imagesx($mark));
    imagealphablending($image, true);
    imagecopyresampled(
        $image, $mark,
        $width - $markWidth - (int) round($width * 0.03), $height - $markHeight - (int) round($height * 0.03),
        0, 0, $markWidth, $markHeight, imagesx($mark), imagesy($mark)
    );
    imagedestroy($mark);
}

/**
 * Make a smaller JPEG copy of a photo.
 * $mode "width": limit the width (gallery grid). "box": limit the longest side (full-screen view).
 */
function make_resized(string $src, string $dest, int $limit, string $mode, bool $watermark = false): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $info = @getimagesize($src);
    if (!$info) {
        return false;
    }
    [$width, $height, $type] = $info;
    $orientation = exif_orientation($src);
    if (!ensure_memory((int) ($width * $height * ($orientation > 1 ? 9 : 5.5)) + 12 * 1048576)) {
        return false;
    }
    switch ($type) {
        case IMAGETYPE_JPEG: $image = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $image = @imagecreatefrompng($src); break;
        case IMAGETYPE_WEBP: $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
        default:             $image = false;
    }
    if (!$image) {
        return false;
    }
    $image = apply_orientation($image, $orientation);
    $width = imagesx($image);
    $height = imagesy($image);
    $scale = $mode === 'width' ? min(1, $limit / $width) : min(1, $limit / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));

    $out = imagecreatetruecolor($newWidth, $newHeight);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // transparent PNGs get a white background
    imagecopyresampled($out, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($image);
    if ($watermark) {
        stamp_watermark($out);
    }
    imageinterlace($out, true);

    if (!ensure_dir(dirname($dest))) {
        imagedestroy($out);
        return false;
    }
    $tmp = $dest . '.' . bin2hex(random_bytes(3)) . '.tmp';
    $ok = imagejpeg($out, $tmp, max(40, min(95, (int) cfg('jpeg_quality', 82))));
    imagedestroy($out);
    if (!$ok || !@rename($tmp, $dest)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/** A resized copy, made once and then reused. Falls back to the original if it cannot be made. */
function resized_copy(array $client, string $src, string $cacheName, int $limit, string $mode, bool $watermark = false): string
{
    $dest = client_dir($client['slug']) . '/_cache/' . $cacheName;
    if (is_file($dest) && filemtime($dest) >= filemtime($src)) {
        return $dest;
    }
    return make_resized($src, $dest, $limit, $mode, $watermark) ? $dest : $src;
}

/** Cache file names of a photo's grid and full-screen copies. */
function photo_cache_names(array $client, string $rel): array
{
    $watermark = $client['watermark'] && watermark_file() !== null;
    return [
        'thumb' => 'thumb/' . $rel . '.jpg',
        'large' => ($watermark ? 'large-wm/' : 'large/') . $rel . '.jpg',
    ];
}

/** Path of a photo in the asked size: "thumb", "large" or "original". */
function photo_variant(array $client, string $rel, string $size): ?string
{
    $src = client_file($client, 'photos/' . $rel);
    if (!$src || !is_photo_name(basename($src))) {
        return null;
    }
    if ($size === 'original') {
        return $src;
    }
    $names = photo_cache_names($client, $rel);
    if ($size === 'thumb') {
        return resized_copy($client, $src, $names['thumb'], (int) cfg('thumb_width', 640), 'width');
    }
    $watermark = strpos($names['large'], 'large-wm/') === 0;
    return resized_copy($client, $src, $names['large'], (int) cfg('large_size', 1800), 'box', $watermark);
}


/* =====================================================================
   Films
   ===================================================================== */

function youtube_id(string $url): ?string
{
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return preg_match('/^[A-Za-z0-9_-]{11}$/', trim($url)) ? trim($url) : null;
}

/** [id, privacy-hash] of a Vimeo link (unlisted Vimeo links carry a hash). */
function vimeo_parts(string $url): ?array
{
    if (!preg_match('~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)(?:/([0-9a-f]{6,}))?~i', $url, $m)) {
        return null;
    }
    $hash = $m[2] ?? '';
    if ($hash === '' && preg_match('~[?&]h=([0-9a-f]+)~i', $url, $h)) {
        $hash = $h[1];
    }
    return [$m[1], $hash];
}

/** The "videos" list of data.json turned into ready-to-show cards. */
function video_cards(array $client): array
{
    $cards = [];
    foreach ($client['videos'] as $video) {
        if (!is_array($video)) {
            continue;
        }
        $type = strtolower((string) ($video['type'] ?? ''));
        $src = trim((string) ($video['src'] ?? ''));
        $card = [
            'title'    => clip((string) ($video['title'] ?? ''), 80) ?: 'Wedding Film',
            'duration' => clip((string) ($video['duration'] ?? ''), 10),
            'kind'     => $type,
            'src'      => '',
            'thumb'    => '',
            'mime'     => '',
        ];
        if ($type === 'youtube' && ($id = youtube_id($src))) {
            $card['src'] = 'https://www.youtube.com/watch?v=' . $id;
            $card['thumb'] = 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
        } elseif ($type === 'vimeo' && ($parts = vimeo_parts($src))) {
            $card['src'] = $parts[1] !== '' ? 'https://player.vimeo.com/video/' . $parts[0] . '?h=' . $parts[1] : 'https://vimeo.com/' . $parts[0];
        } elseif (in_array($type, ['mp4', 'video'], true) && ($file = client_file($client, $src))) {
            $card['kind'] = 'mp4';
            $card['src'] = media_url('video', ['f' => $src, 'v' => filemtime($file)]);
            $card['mime'] = mime_for($file) === 'video/quicktime' ? 'video/mp4' : mime_for($file);
        } else {
            continue;
        }
        $thumb = trim((string) ($video['thumb'] ?? ''));
        if ($thumb !== '' && ($file = client_file($client, $thumb))) {
            $card['thumb'] = media_url('poster', ['f' => $thumb, 'v' => filemtime($file)]);
        } elseif (preg_match('~^https://~i', $thumb)) {
            $card['thumb'] = $thumb;
        }
        $cards[] = $card;
    }
    return $cards;
}


/* =====================================================================
   Sending private files (with resume / seek support for PDF and video)
   ===================================================================== */

function send_file(string $path, string $mime, array $options = []): void
{
    $size = (int) filesize($path);
    $mtime = (int) filemtime($path);
    $etag = '"' . substr(md5($path . '|' . $size . '|' . $mtime), 0, 20) . '"';

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    @ini_set('zlib.output_compression', 'Off');

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, max-age=' . (int) ($options['max_age'] ?? 86400));
    header('Vary: Cookie');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Accept-Ranges: bytes');
    $download = (string) ($options['download'] ?? '');
    if ($download !== '') {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '-', $download);
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($download));
    } else {
        header('Content-Disposition: inline');
    }

    if (empty($_SERVER['HTTP_RANGE']) && strpos((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $etag) !== false) {
        http_response_code(304);
        exit;
    }

    $start = 0;
    $end = $size - 1;
    $range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
    if ($range !== '' && $size > 0) {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) || ($m[1] === '' && $m[2] === '')) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        if ($m[1] === '') {
            $start = max(0, $size - (int) $m[2]);   // "last N bytes"
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = min((int) $m[2], $size - 1);
            }
        }
        // Films are sent in pieces so a slow phone never keeps the server busy for long.
        if (!empty($options['range_cap'])) {
            $end = min($end, $start + (int) $options['range_cap'] - 1);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }

    $length = max(0, $end - $start + 1);
    header('Content-Length: ' . $length);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD' || $length === 0) {
        exit;
    }

    @set_time_limit(0);
    $fh = fopen($path, 'rb');
    if (!$fh) {
        exit;
    }
    fseek($fh, $start);
    $left = $length;
    while ($left > 0 && !feof($fh) && !connection_aborted()) {
        $chunk = fread($fh, (int) min(1048576, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        flush();
        $left -= strlen($chunk);
    }
    fclose($fh);
    exit;
}


/* =====================================================================
   Uploads (used by the admin page)
   ===================================================================== */

/** Clean file name with an allowed extension, or null. */
function safe_filename(string $name, array $allowed): ?string
{
    $name = basename(str_replace('\\', '/', $name));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return null;
    }
    $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($name, PATHINFO_FILENAME)), '-_');
    return substr($base !== '' ? $base : 'file', 0, 80) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
}

/** "photo.jpg", or "photo-2.jpg" if that name is taken. */
function unique_path(string $dir, string $file): string
{
    $base = pathinfo($file, PATHINFO_FILENAME);
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $path = "$dir/$file";
    for ($i = 2; file_exists($path); $i++) {
        $path = "$dir/$base-$i.$ext";
    }
    return $path;
}

/** Biggest upload piece the hosting accepts (the admin page sends big files in pieces). */
function upload_chunk_size(): int
{
    $limits = array_filter([ini_bytes('upload_max_filesize'), ini_bytes('post_max_size')]);
    $limit = $limits ? min($limits) : 8 * 1048576;
    return (int) max(256 * 1024, min(8 * 1048576, $limit - 128 * 1024));
}
