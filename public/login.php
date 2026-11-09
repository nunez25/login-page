<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/captcha.php';
require_once __DIR__ . '/../includes/login_rate_limit.php';
require_once __DIR__ . '/../includes/auth_check.php';

start_secure_session();
header('Cache-Control: no-store');
$authenticatedUser = current_user();
if ($authenticatedUser !== null) {
    redirect(dashboardPathForRole($authenticatedUser['role']));
}

$feedback = $_SESSION['login_feedback'] ?? [];
unset($_SESSION['login_feedback']);
$error = is_array($feedback) && is_string($feedback['error'] ?? null) ? $feedback['error'] : '';
$username = is_array($feedback) && is_string($feedback['username'] ?? null) ? $feedback['username'] : '';
$captchaError = is_array($feedback) && ($feedback['captcha_error'] ?? false) === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $captcha = is_string($_POST['captcha'] ?? null) ? trim($_POST['captcha']) : '';

    $is_captcha_valid = captcha_verify($captcha);
    $captchaError = false;

    if (!csrf_verify()) {
        $error = APP_NAME . ': invalid request. Please reload the page and try again.';
    } else {
        $result = authenticate_login(db(), $username, $password, client_ip(), $is_captcha_valid);
        $user = $result['user'];

        if ($result['retry_after'] > 0) {
            $_SESSION['lockout_username'] = mb_check_encoding($username, 'UTF-8') && mb_strlen($username, 'UTF-8') <= 50
                ? $username : '';
            $error = '';
        } elseif ($user !== null) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            redirect(dashboardPathForRole($user['role']));
        } elseif (!$is_captcha_valid) {
            $error = 'Invalid CAPTCHA code. Please try again.';
            $captchaError = true;
        } elseif ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            $error = 'Invalid username or password, or account is inactive.';
        }
    }

    $_SESSION['login_feedback'] = [
        'error' => $error,
        'username' => $username,
        'captcha_error' => $captchaError,
    ];
    redirect('/login.php');
}

unset($_SESSION['captcha']);
$lockedUsername = is_string($_SESSION['lockout_username'] ?? null) ? $_SESSION['lockout_username'] : '';
$remainingLockout = login_retry_after(db(), $lockedUsername, client_ip());
if ($remainingLockout > 0) {
    http_response_code(429);
    header('Retry-After: ' . $remainingLockout);
    if ($username === '') {
        $username = $lockedUsername;
    }
} else {
    unset($_SESSION['lockout_username']);
}

$pageTitle   = 'Login';
$hideNavbar  = true;
$pageScripts = ['/assets/js/login.js?v=' . filemtime(__DIR__ . '/assets/js/login.js')];
$logo        = logo_url();
require __DIR__ . '/../includes/header.php';
?>
<div class="login-wrapper d-flex align-items-center justify-content-center py-5 px-3">
    <div class="card login-card shadow-sm w-100">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                <?php if ($logo !== null): ?>
                    <img src="<?= e($logo) ?>" alt="<?= e(SCHOOL_SHORT_NAME) ?> logo" class="login-logo mb-3">
                <?php endif; ?>
                <h1 class="h4 mb-1 brand-heading"><?= e(SCHOOL_NAME) ?></h1>
                <p class="text-muted mb-0">Student Information System</p>
            </div>

            <?php if ($error !== ''): ?>
                <div id="login-error" class="alert alert-danger py-2" role="alert"
                     data-error-type="<?= $captchaError ? 'captcha' : 'login' ?>"><?= e($error) ?></div>
            <?php endif; ?>

            <div id="login-lockout" class="alert alert-warning py-2" role="status"
                 data-remaining-seconds="<?= $remainingLockout ?>" <?= $remainingLockout > 0 ? '' : 'hidden' ?>>
                Account suspended. Try again in
                <span id="lockout-time" role="timer" aria-live="off"><?= sprintf('%02d:%02d', intdiv($remainingLockout, 60), $remainingLockout % 60) ?></span>.
            </div>

            <form id="login-form" method="post" action="/login.php" autocomplete="off" novalidate>
                <fieldset id="login-fields" class="border-0 p-0 m-0" <?= $remainingLockout > 0 ? 'disabled' : '' ?>>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= e($username) ?>" maxlength="50" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password"
                           maxlength="255" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label for="captcha" class="form-label">Enter the characters shown</label>
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <img src="/captcha_image.php?v=<?= bin2hex(random_bytes(8)) ?>" alt="Captcha image" id="captcha-img"
                             class="captcha-img border rounded" width="200" height="60">
                        <a href="/login.php" id="captcha-refresh" class="small">Refresh captcha</a>
                    </div>
                    <input type="text" class="form-control text-uppercase" id="captcha" name="captcha"
                           maxlength="6" required autocomplete="off" autocapitalize="characters" spellcheck="false">
                </div>
                <button type="submit" class="btn btn-brand w-100">Login</button>
                </fieldset>
            </form>
            <p class="mt-3 mb-0 text-center"><a href="/register.php">Create a new User account</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>