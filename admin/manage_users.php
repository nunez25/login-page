<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

requireUserManager();
$currentUser = requireAuthenticated();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid request. Please reload the page and try again.');
    }
    if (($_POST['action'] ?? null) !== 'delete') {
        http_response_code(400);
        exit('Invalid user management action.');
    }

    $id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
    if (preg_match('/\A[1-9][0-9]*\z/', $id) !== 1) {
        denyUserNotFound();
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare(
            'SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1 FOR UPDATE'
        );
        $select->execute([$id]);
        $target = $select->fetch();
        if (!$target || !canDeleteUser($target)) {
            $pdo->rollBack();
            denyUserNotFound();
        }

        $delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $delete->execute([$id]);
        $pdo->commit();
        redirect('/admin/manage_users.php');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

$roles = isSuperAdmin() ? ['Admin', 'User'] : ['User'];
$placeholders = implode(', ', array_fill(0, count($roles), '?'));
$selectUsers = db()->prepare(
    "SELECT id, username, email, role FROM users
     WHERE role IN ($placeholders) ORDER BY username, id"
);
$selectUsers->execute($roles);
$users = $selectUsers->fetchAll();

$pageTitle = 'Manage users';
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Manage users</h1>
            <p class="text-muted mb-0">Signed in as <?= e($currentUser['username']) ?> (<?= e($currentUser['role']) ?>)</p>
        </div>
        <a class="btn btn-primary" href="/admin/create_user.php">Create user</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead>
            <tr>
                <th scope="col">Username</th>
                <th scope="col">Email</th>
                <th scope="col">Role</th>
                <th scope="col">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $managedUser): ?>
                <tr>
                    <td><?= e($managedUser['username']) ?></td>
                    <td><?= e($managedUser['email'] ?? '') ?></td>
                    <td><?= e($managedUser['role']) ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary"
                           href="/admin/edit_user.php?id=<?= e($managedUser['id']) ?>">Edit</a>
                        <?php if (canDeleteUser($managedUser)): ?>
                            <form method="post" action="/admin/manage_users.php" class="d-inline"
                                  onsubmit="return confirm('Delete this account?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($managedUser['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users === []): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No users to display.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
