<?php
declare(strict_types=1);

require_once __DIR__ . '/header.php';
?>
<section class="container py-5">
    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="text-uppercase text-muted small mb-2"><?= e($currentUser['role']) ?> portal</p>
                    <h1 class="h2 mb-2"><?= e($dashboardTitle) ?></h1>
                    <p class="text-muted mb-0"><?= e($dashboardDescription) ?></p>
                </div>
                <form method="post" action="/logout.php">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-secondary" type="submit">Log out</button>
                </form>
            </div>
            <hr class="my-4">
            <dl class="row mb-0">
                <dt class="col-sm-3">Username</dt>
                <dd class="col-sm-9"><?= e($currentUser['username']) ?></dd>
                <dt class="col-sm-3">Account role</dt>
                <dd class="col-sm-9"><?= e($currentUser['role']) ?></dd>
                <?php if (!empty($currentUser['email'])): ?>
                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9"><?= e($currentUser['email']) ?></dd>
                <?php endif; ?>
            </dl>
            <?php if ($dashboardActions !== []): ?>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <?php foreach ($dashboardActions as $action): ?>
                        <a class="btn btn-primary" href="<?= e($action['href']) ?>"><?= e($action['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
