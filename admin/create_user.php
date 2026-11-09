<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

requireUserManager();
$currentUser = requireAuthenticated();
$allowedRoles = isSuperAdmin() ? ['Admin', 'User'] : ['User'];
$errors = [];
$username = '';
$email = '';
$role = 'User';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!csrf_verify()) {
        $errors[] = 'Invalid request. Please reload the page and try again.';
    } else {
        if (preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) !== 1) {
            $errors[] = 'Username must be 3-50 characters using letters, numbers, dots, underscores, or hyphens.';
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!canAssignRole($role)) {
            $errors[] = 'You cannot assign that role.';
        }
        if (strlen($password) < 12 || strlen($password) > 72) {
            $errors[] = 'Password must be 12-72 bytes long.';
        }

        if ($errors === []) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('Unable to hash account password.');
            }

            try {
                $insert = db()->prepare(
                    'INSERT INTO users (username, email, password_hash, role, created_by)
                     VALUES (:username, :email, :password_hash, :role, :created_by)'
                );
                $insert->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'role' => $role,
                    'created_by' => $currentUser['id'],
                ]);
                redirect('/admin/manage_users.php');
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

$pageTitle = 'Create user';
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width: 640px">
    <h1 class="h3 mb-1">Create user</h1>
    <p class="text-muted mb-4">Signed in as <?= e($currentUser['username']) ?> (<?= e($currentUser['role']) ?>)</p>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="post" action="/admin/create_user.php" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input class="form-control" id="username" name="username" maxlength="50"
                   value="<?= e($username) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" maxlength="254"
                   value="<?= e($email) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="role">Role</label>
            <select class="form-select" id="role" name="role" required>
                <?php foreach ($allowedRoles as $allowedRole): ?>
                    <option value="<?= e($allowedRole) ?>" <?= $role === $allowedRole ? 'selected' : '' ?>>
                        <?= e($allowedRole) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Temporary password</label>
            <input class="form-control" type="password" id="password" name="password"
                   minlength="12" maxlength="72" autocomplete="new-password" required>
            <div class="form-text">Use 12-72 characters.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Create account</button>
            <a class="btn btn-outline-secondary" href="/admin/manage_users.php">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
