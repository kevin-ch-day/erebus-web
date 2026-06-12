<?php
// app/lib/header.php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/url.php';   // app_url(), page_url(), nav_link()
require_once __DIR__ . '/time.php';  // tz_current_id(), tz_current_key()
require_once __DIR__ . '/theme.php'; // theme_current()

// Normalize page key for nav highlighting (router allowlists, but be defensive)
$currentPage = current_page('landing');

$currentTzId = tz_current_id();
$currentTzKey = tz_current_key();
$secondaryTz = tz_current_secondary_id();
$secondaryTzKey = tz_current_secondary_key() ?? '';
$currentTheme = theme_current();

$currentPageMeta = app_route_meta($currentPage) ?? ['label' => $title, 'section' => 'Operator Console', 'nav_section' => null];
$routeTitle = trim((string)($currentPageMeta['label'] ?? ''));
$defaultTitle = defined('APP_DISPLAY_NAME') ? (string)APP_DISPLAY_NAME : (string)APP_NAME;
$title = $routeTitle !== '' ? ($routeTitle . ' | ' . APP_NAME) : ($title ?? $defaultTitle);
$currentNavSection = (string)($currentPageMeta['nav_section'] ?? '');
$navSections = app_nav_sections_manifest();
$activeSections = [];
foreach ($navSections as $section) {
    $sectionKey = (string)($section['key'] ?? '');
    if ($sectionKey === '') {
        continue;
    }
    $activeSections[$sectionKey] = $currentNavSection === $sectionKey;
}
?>
<!doctype html>
<html lang="en"
      data-theme="<?= h($currentTheme) ?>"
      data-display-tz-key="<?= h($currentTzKey) ?>"
      data-display-tz="<?= h($currentTzId) ?>"
      data-secondary-tz-key="<?= h($secondaryTzKey) ?>"
      data-secondary-tz="<?= h($secondaryTz ?? '') ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= h($title) ?></title>

    <!-- Single entrypoint CSS (style.css imports the rest) -->
    <link rel="stylesheet" href="<?= h(app_url('assets/css/style.css')) ?>">

    <!-- v1: minimal meta; can add icons/manifest later -->
</head>

<body>
    <div class="layout">

        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">
                    <img class="brand-logo"
                        src="<?= h(app_url('assets/img/erebus_web_logo_mark.png')) ?>"
                        alt="<?= h(APP_NAME) ?> logo" />
                    <div class="brand-title"><?= h(APP_NAME) ?></div>
                </div>
                <div class="brand-sub muted">Erebus Operator Console</div>
            </div>

            <nav class="nav">
                <?php foreach ($navSections as $section): ?>
                    <?php
                    $sectionKey = (string)$section['key'];
                    $sectionLabel = (string)$section['label'];
                    $defaultCollapsed = (bool)($section['default_collapsed'] ?? true);
                    $isActive = $activeSections[$sectionKey] ?? false;
                    ?>
                    <div class="nav-section" data-section="<?= h($sectionKey) ?>"
                        x-data="navSection('<?= h($sectionKey) ?>', <?= $isActive ? 'true' : 'false' ?>, <?= $defaultCollapsed ? 'true' : 'false' ?>)"
                        :class="{ 'is-collapsed': collapsed }">
                        <button class="nav-section-toggle" type="button" :aria-expanded="(!collapsed).toString()" @click="toggle()">
                            <span><?= h($sectionLabel) ?></span>
                            <span class="nav-section-chevron" aria-hidden="true"></span>
                        </button>
                        <div class="nav-section-links">
                            <?php foreach (($section['groups'] ?? []) as $group): ?>
                                <?php $groupLabel = $group['label'] ?? null; ?>
                                <div class="nav-group">
                                    <?php if ($groupLabel !== null && $groupLabel !== ''): ?>
                                        <div class="nav-group-label"><?= h((string)$groupLabel) ?></div>
                                    <?php endif; ?>
                                    <?php foreach (($group['links'] ?? []) as $link): ?>
                                        <?= nav_link((string)$link['label'], (string)$link['route'], $currentPage) ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-foot">
                <div class="muted">
                    <?= h(defined('APP_VERSION') ? (string)APP_VERSION : 'v1') ?> | trusted internal tool
                </div>
            </div>
        </aside>

        <main class="main">
            <div class="topbar">
                <div class="topbar-page">
                    <div class="topbar-eyebrow"><?= h((string)$currentPageMeta['section']) ?></div>
                    <div class="topbar-title"><?= h((string)$currentPageMeta['label']) ?></div>
                </div>

                <div class="topbar-right">
                    <div class="topbar-clock" id="topbar-clock">
                        <div class="topbar-clock-block">
                            <div class="topbar-clock-label muted" id="topbar-time-display-label">Primary</div>
                            <div class="topbar-clock-value" id="topbar-time-display">--</div>
                        </div>
                        <?php if ($secondaryTz !== null): ?>
                            <div class="topbar-clock-block" id="topbar-time-secondary-block">
                                <div class="topbar-clock-label muted" id="topbar-time-secondary-label">Second</div>
                                <div class="topbar-clock-value" id="topbar-time-secondary">--</div>
                            </div>
                        <?php endif; ?>
                        <div class="topbar-clock-block">
                            <div class="topbar-clock-label muted">UTC</div>
                            <div class="topbar-clock-value" id="topbar-time-utc">--</div>
                        </div>
                    </div>

                    <div class="topbar-status" id="mini-status"
                        data-db-status="1"
                        data-health-url="<?= h(api_url('health.php')) ?>"
                        data-health-interval="<?= (int)DASHBOARD_REFRESH_SECONDS ?>">
                        DB: ...
                    </div>
                </div>
            </div>

            <div class="content">
