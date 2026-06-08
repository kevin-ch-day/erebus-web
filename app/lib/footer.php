<?php
// app/lib/footer.php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/url.php';
?>
</div> <!-- /content -->
</main>
</div> <!-- /layout -->

<?php $appShellAsset = app_shell_asset_url(); ?>
<?php if ($appShellAsset !== null): ?>
    <script src="<?= htmlspecialchars($appShellAsset) ?>"></script>
<?php else: ?>
    <script src="<?= htmlspecialchars(app_url('assets/js/app_core.js')) ?>"></script>
    <script src="<?= htmlspecialchars(app_url('assets/js/db_status_pill.js')) ?>"></script>
    <script src="<?= htmlspecialchars(app_url('assets/js/nav_sections.js')) ?>"></script>
    <script src="<?= htmlspecialchars(app_url('assets/js/topbar_clock.js')) ?>"></script>
<?php endif; ?>
<?php
$routePageKey = isset($currentPage) && is_string($currentPage) ? $currentPage : current_page('landing');
$resolvedPageScripts = (!empty($pageScripts) && is_array($pageScripts))
    ? $pageScripts
    : app_route_scripts($routePageKey);
?>
<?php if (!empty($resolvedPageScripts) && is_array($resolvedPageScripts)): ?>
    <?php foreach ($resolvedPageScripts as $script): ?>
        <?php $resolvedScript = resolve_page_script_path((string)$script, $appShellAsset !== null); ?>
        <?php if ($resolvedScript !== null): ?>
            <script src="<?= htmlspecialchars($resolvedScript) ?>"></script>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
</body>

</html>
