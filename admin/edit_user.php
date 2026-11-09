<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

requireUserManager();
$currentUser = requireAuthenticated();
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
if (preg_match('/\A[1-9][0-9]*\z/', $id) !== 1) {
    denyUserNotFound();
}

$loadUser = db()->prepare(
    'SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1'
);
$loadUser->execute([$id]);
$target = $loadUser->fetch();
if (!$target || !canManageUser($target)) {
    // Hide the existence of accounts outside the current administrator's scope.
    denyUserNotFound();
}

$isOwnAccount = $target['role'] === $currentUser['role']
    && (string) $target['id'] === (string) $currentUser['id'];
$allowedRoles = isSuperAdmin() ? ['Admin', 'User'] : ['User'];
$errors = [];
$username = $target['username'];
$email = (string) ($target['email'] ?? '');
$role = $target['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    $newPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $submittedRole = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';

    if (!csrf_verify()) {
        $errors[] = 'Invalid request. Please reload the page and try again.';
    } else {
        if (preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) !== 1) {
            $errors[] = 'Username must be 3-50 characters using letters, numbers, dots, underscores, or hyphens.';
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($newPassword !== '' && (strlen($newPassword) < 12 || strlen($newPassword) > 72)) {
            $errors[] = 'A new password must be 12-72 bytes long.';
        }

        if ($isOwnAccount) {
            $role = $target['role'];
        } elseif (canAssignRole($submittedRole)) {
            $role = $submittedRole;
        } else {
            $errors[] = 'You cannot assign that role.';
        }

        if ($errors === []) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $select = $pdo->prepare(
                    'SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1 FOR UPDATE'
                );
                $select->execute([$id]);
                $lockedTarget = $select->fetch();
                if (!$lockedTarget || !canManageUser($lockedTarget)) {
                    $pdo->rollBack();
                    denyUserNotFound();
                }

                $lockedOwnAccount = $lockedTarget['role'] === $currentUser['role']
                    && (string) $lockedTarget['id'] === (string) $currentUser['id'];
                if ($lockedOwnAccount) {
                    if ($newPassword !== '') {
                        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        if ($passwordHash === false) {
                            throw new RuntimeException('Unable to hash account password.');
                        }
                        $update = $pdo->prepare(
                            'UPDATE users SET username = ?, email = ?, password_hash = ? WHERE id = ?'
                        );
                        $update->execute([$username, $email, $passwordHash, $id]);
                    } else {
                        $update = $pdo->prepare(
                            'UPDATE users SET username = ?, email = ? WHERE id = ?'
                        );
                        $update->execute([$username, $email, $id]);
                    }
                } else {
                    if (!canAssignRole($role)) {
                        $pdo->rollBack();
                        denyUserNotFound();
                    }
                    $updateSql = 'UPDATE users SET username = ?, email = ?, role = ?';
                    $parameters = [$username, $email, $role];
                    if ($newPassword !== '') {
                        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        if ($passwordHash === false) {
                            throw new RuntimeException('Unable to hash account password.');
                        }
                        $updateSql .= ', password_hash = ?';
                        $parameters[] = $passwordHash;
                    }
                    $updateSql .= ' WHERE id = ?';
                    $parameters[] = $id;
                    $update = $pdo->prepare($updateSql);
                    $update->execute($parameters);
                }

                $pdo->commit();
                redirect('/admin/manage_users.php');
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception->getCode() === '23000') {
                    $errors[] = 'That username or email address is already registered.';
                } else {
                    throw $exception;
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    }
}

$pageTitle = 'Edit user';
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width: 640px">
    <h1 class="h3 mb-1">Edit user</h1>
    <p class="text-muted mb-4">Signed in as <?= e($currentUser['username']) ?> (<?= e($currentUser['role']) ?>)</p>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="post" action="/admin/edit_user.php?id=<?= e($id) ?>" autocomplete="off">
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
        <?php if ($isOwnAccount): ?>
            <div class="mb-3">
                <label class="form-label" for="role_readonly">Role</label>
                <input class="form-control" id="role_readonly" value="<?= e($target['role']) ?>" readonly>
                <div class="form-text">Your role cannot be changed from your account settings.</div>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="password">New password (optional)</label>
            <input class="form-control" type="password" id="password" name="password"
                   minlength="12" maxlength="72" autocomplete="new-password">
            <div class="form-text">Leave blank to keep the current password. If set, use 12-72 characters.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Save changes</button>
            <a class="btn btn-outline-secondary" href="/admin/manage_users.php">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
