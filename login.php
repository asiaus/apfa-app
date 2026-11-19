<?php
declare(strict_types=1);

// Creator login for standalone installs. Enabled by APFA_CREATOR_PASSWORD_HASH in .env
// (bin/create-creator-password.php prints the line). Host applications that sign users in
// themselves leave it unset and register their own provider through APFA_HOST_BOOTSTRAP.

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/bootstrap.php';

use APFA\Api\Auth\CreatorSession;

header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

function login_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Where to go after login: a same-origin absolute path only (no scheme, no //host), else the creator. */
function login_next(mixed $next): string
{
    $default = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/apfa-app/login.php'))), '/') . '/ProcessFlow.php';
    if (!is_string($next) || strlen($next) > 512
        || preg_match('#^/(?![/\\\\])[A-Za-z0-9._~!$&\'()*+,;=:@%/-]*(\?[A-Za-z0-9._~!$&\'()*+,;=:@%/?-]*)?$#', $next) !== 1) {
        return $default;
    }
    return $next;
}

function login_redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

$enabled = CreatorSession::isEnabled();
$error = null;
$status = 200;
$next = login_next($_POST['next'] ?? $_GET['next'] ?? null);

if ($enabled) {
    CreatorSession::start();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'POST') {
        $csrf_ok = CreatorSession::checkCsrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null);
        $action = (string) ($_POST['action'] ?? 'login');
        if (!$csrf_ok) {
            $status = 400;
            $error = 'Your form expired. Please try again.';
        } elseif ($action === 'logout') {
            CreatorSession::logout();
            login_redirect(login_next(null) === $next ? 'login.php' : 'login.php?next=' . rawurlencode($next));
        } else {
            $user = is_string($_POST['user'] ?? null) ? $_POST['user'] : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (CreatorSession::login($user, $password)) {
                login_redirect($next);
            }
            usleep(random_int(800000, 1200000)); // slow down password guessing
            error_log('APFA creator: failed login from ' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $status = 401;
            $error = 'Wrong user name or password.';
        }
    } elseif (CreatorSession::isAuthenticated() && isset($_GET['next'])) {
        login_redirect($next);
    }
    $csrf_token = CreatorSession::csrfToken();
    $logged_in_as = CreatorSession::isAuthenticated() ? CreatorSession::user() : null;
} else {
    $status = 503;
}

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>APFA creator: sign in</title>
<style>
body { font-family: system-ui, sans-serif; background: #f4f5f7; color: #222; margin: 0; }
main { max-width: 360px; margin: 10vh auto; background: #fff; padding: 24px 28px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
h1 { font-size: 1.25rem; margin: 0 0 16px; }
label { display: block; margin: 12px 0 4px; font-size: .9rem; }
input[type=text], input[type=password] { width: 100%; box-sizing: border-box; padding: 8px; font-size: 1rem; }
button { margin-top: 18px; padding: 8px 16px; font-size: 1rem; cursor: pointer; }
.error { color: #a00; background: #fdecea; padding: 8px; border-radius: 4px; }
.note { font-size: .85rem; color: #555; }
</style>
</head>
<body>
<main>
<h1>APFA process creator</h1>
<?php if (!$enabled): ?>
    <p class="error">The creator login is not configured.</p>
    <p class="note">Set <code>APFA_CREATOR_PASSWORD_HASH</code> in <code>.env</code>
    (run <code>php bin/create-creator-password.php</code>), or open the creator from the host application that signs you in.</p>
<?php elseif ($logged_in_as !== null): ?>
    <p>Signed in as <strong><?= login_e($logged_in_as) ?></strong>.</p>
    <p><a href="<?= login_e($next) ?>">Open the creator</a></p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= login_e($csrf_token) ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit">Sign out</button>
    </form>
<?php else: ?>
    <?php if ($error !== null): ?><p class="error" role="alert"><?= login_e($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= login_e($csrf_token) ?>">
        <input type="hidden" name="next" value="<?= login_e($next) ?>">
        <label for="user">User name</label>
        <input type="text" id="user" name="user" required autocomplete="username">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" autofocus>
        <button type="submit">Sign in</button>
    </form>
<?php endif; ?>
</main>
</body>
</html>
