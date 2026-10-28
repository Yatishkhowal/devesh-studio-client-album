<?php
/**
 * Client login – every wedding client opens their album with their own username and password.
 */
require_once __DIR__ . '/album-core/bootstrap.php';

album_session_start();

if (current_client()) {
    redirect('album.php');
}

$error = '';
$notice = '';
$username = '';

if (isset($_GET['bye'])) {
    $notice = 'You have been logged out. Come back anytime to relive your memories!';
} elseif (!empty($_SESSION['expired'])) {
    $notice = 'For your privacy you were logged out after a while. Please log in again.';
}
unset($_SESSION['expired']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = clip((string) ($_POST['username'] ?? ''), 60);
    $password = (string) ($_POST['password'] ?? '');
    $lockMinutes = max(1, (int) cfg('lockout_minutes', 10));

    if (!csrf_ok()) {
        $error = 'This page was open for a long time. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $keys = throttle_keys($username);
        $wait = throttle_wait($keys);
        if ($wait > 0) {
            $error = 'Too many wrong attempts. Please wait ' . (int) ceil($wait / 60) . ' minute(s) and try again, or message us on WhatsApp.';
        } else {
            $client = find_client_by_username($username);
            if ($client && password_verify($password, (string) $client['password_hash'])) {
                if (!$client['enabled']) {
                    $error = 'This album is not open right now. Please contact Devesh Studio.';
                } else {
                    throttle_clear($keys);
                    login_client($client);
                    redirect('album.php');
                }
            } else {
                if (!$client) {
                    password_hash($password, PASSWORD_DEFAULT);   // same delay as a real check, so usernames cannot be guessed
                }
                $left = throttle_fail($keys);
                if ($left <= 0) {
                    $error = 'Too many wrong attempts. Please wait ' . $lockMinutes . ' minutes and try again.';
                } else {
                    $error = 'Username or password is not correct.' . ($left <= 2 ? ' ' . $left . ($left === 1 ? ' try' : ' tries') . ' left.' : '');
                }
            }
        }
    }
}

$csrf = csrf_token();
$studio = (string) cfg('studio_name', 'Devesh Studio');
page_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#202120">
<title>Client Login · <?= e($studio) ?></title>
<link rel="icon" href="<?= e(cfg('favicon')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@300;400;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="album-assets/css/login.css?v=<?= ASSET_VERSION ?>">
</head>
<body>
<div class="login-bg" aria-hidden="true">
    <span class="glow glow-gold"></span>
    <span class="glow glow-pink"></span>
    <span class="glow glow-purple"></span>
    <span class="sparkles"></span>
</div>

<main class="login-wrap">
    <div class="login-card">
        <div class="login-toran" aria-hidden="true"></div>

        <a class="login-brand" href="<?= e(cfg('website_home')) ?>">
            <img src="<?= e(cfg('logo')) ?>" alt="" onerror="this.remove()">
            <span class="brand-name"><?= e($studio) ?></span>
            <span class="brand-tag"><?= e(cfg('studio_tagline')) ?></span>
        </a>

        <h1>Welcome to Your <span>Wedding Album</span></h1>
        <p class="login-sub">Log in with the username and password shared with you by <?= e($studio) ?>.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($error) ?></span></div>
        <?php elseif ($notice !== ''): ?>
            <div class="alert alert-info" role="status"><i class="fa-solid fa-heart"></i><span><?= e($notice) ?></span></div>
        <?php endif; ?>

        <form method="post" action="client-login.php" class="login-form" id="loginForm" novalidate>
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <div class="field">
                <label for="username">Username</label>
                <div class="field-box">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="username"
                           autocapitalize="none" autocorrect="off" spellcheck="false" required <?= $username === '' ? 'autofocus' : '' ?>>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="field-box">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" autocomplete="current-password" required <?= $username !== '' ? 'autofocus' : '' ?>>
                    <button type="button" class="eye" id="togglePassword" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="loginButton">
                <span>Login</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        <p class="login-help">
            Forgot password?
            <a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Contact us on WhatsApp</a>
        </p>
        <a class="back-link" href="<?= e(cfg('website_home')) ?>"><i class="fa-solid fa-arrow-left"></i> Back to website</a>
    </div>
</main>

<script>
(function () {
    var input = document.getElementById('password');
    var eye = document.getElementById('togglePassword');
    eye.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        eye.setAttribute('aria-pressed', show);
        eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        eye.firstElementChild.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        input.focus();
    });

    var form = document.getElementById('loginForm');
    form.addEventListener('submit', function (event) {
        var user = document.getElementById('username');
        if (!user.value.trim() || !input.value) {
            event.preventDefault();
            (user.value.trim() ? input : user).focus();
            form.classList.remove('shake');
            void form.offsetWidth;
            form.classList.add('shake');
            return;
        }
        document.getElementById('loginButton').classList.add('is-busy');
    });
})();
</script>
</body>
</html>
