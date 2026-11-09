<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

start_secure_session();
header('Cache-Control: no-store');

$authenticatedUser = current_user();
if ($authenticatedUser !== null) {
    redirect(dashboardPathForRole($authenticatedUser['role']));
}

$errors = [];
$username = '';
$email = '';
$success = (($_SESSION['registration_success'] ?? false) === true);
unset($_SESSION['registration_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null)
        ? $_POST['password_confirmation'] : '';

    if (!csrf_verify()) {
        $errors[] = 'Invalid request. Please reload the page and try again.';
    } else {
        if (preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) !== 1) {
            $errors[] = 'Username must be 3-50 characters using letters, numbers, dots, underscores, or hyphens.';
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 12 || strlen($password) > 72) {
            $errors[] = 'Password must be 12-72 bytes long.';
        }
        if (!hash_equals($password, $passwordConfirmation)) {
            $errors[] = 'Passwords do not match.';
        }

        if ($errors === []) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('Unable to hash registration password.');
            }

            try {
                // Role is fixed server-side; registration can never grant privileges.
                $statement = db()->prepare(
                    "INSERT INTO users (username, email, password_hash, role)
                     VALUES (:username, :email, :password_hash, 'User')"
                );
                $statement->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                ]);
                $_SESSION['registration_success'] = true;
                redirect('/register.php');
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $errors[] = 'That username or email address is already registered.';
                } else {
                    throw $exception;
                }
            }
        }
    }
}

$pageTitle = 'Create account';
$hideNavbar = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="max-width: 560px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-3">Create an account</h1>
            <?php if ($success): ?>
                <div class="alert alert-success" role="status">
                    Your account is ready. <a href="/login.php">Sign in</a>.
                </div>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="/register.php" autocomplete="on">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" id="username" name="username" maxlength="50"
                           value="<?= e($username) ?>" autocomplete="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" maxlength="254"
                           value="<?= e($email) ?>" autocomplete="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password"
                           minlength="12" maxlength="72" autocomplete="new-password" required>
                    <div class="form-text">Use 12-72 characters.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Confirm password</label>
                    <input class="form-control" type="password" id="password_confirmation"
                           name="password_confirmation" minlength="12" maxlength="72"
                           autocomplete="new-password" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Register</button>
            </form>
            <p class="mt-3 mb-0 text-center"><a href="/login.php">Already have an account? Sign in</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
