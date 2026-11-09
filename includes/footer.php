<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
?>
</main>
<footer class="site-footer text-center py-3 small">
    &copy; <?= e(date('Y')) ?> <?= e(SCHOOL_NAME) ?> All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php foreach (($pageScripts ?? []) as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
