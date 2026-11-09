<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

$user = current_user();
if ($user !== null) {
    redirect(dashboardPathForRole($user['role']));
}
redirect('/login.php');
