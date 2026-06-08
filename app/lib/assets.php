<?php
// app/lib/assets.php
declare(strict_types=1);

require_once __DIR__ . '/url.php';

function public_root_path(): string
{
    return dirname(__DIR__, 2) . '/public';
}

function public_asset_exists(string $relativePath): bool
{
    $path = ltrim($relativePath, '/');
    return is_file(public_root_path() . '/' . $path);
}

function app_shell_asset_url(): ?string
{
    $relative = 'assets/build/app-shell.js';
    if (!public_asset_exists($relative)) {
        return null;
    }
    return app_url($relative);
}

function resolve_page_script_path(string $relativePath, bool $shellBundleEnabled = false): ?string
{
    $normalized = ltrim($relativePath, '/');

    if ($shellBundleEnabled) {
        $handledByBundle = [
            'assets/js/permission_intel_shared.js',
            'assets/js/pages/analysis_fusion_page.js',
            'assets/js/pages/permissions_review_page.js',
            'assets/js/pages/vt_confidence_page.js',
        ];
        if (in_array($normalized, $handledByBundle, true)) {
            return null;
        }
    }

    return app_url($normalized);
}
