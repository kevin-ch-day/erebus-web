<?php
// app/lib/url.php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/routes.php';

/**
 * Escape for HTML output (consistent everywhere).
 */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * BASE URL (points to /public)
 */
function base_url(): string
{
    return rtrim(BASE_URL, '/');
}

/**
 * Build URLs consistently (no hardcoded folder names).
 * BASE_URL should point to your /public directory.
 *
 * Examples:
 *  app_url() -> /web/erebus-web/public
 *  app_url('assets/css/style.css') -> /web/erebus-web/public/assets/css/style.css
 */
function app_url(string $path = ''): string
{
    $base = base_url();
    $path = ltrim($path, '/');
    return $path === '' ? $base : ($base . '/' . $path);
}

/**
 * Assets under /public/assets
 */
function asset_url(string $path): string
{
    return app_url('assets/' . ltrim($path, '/'));
}

/**
 * API endpoint URL under the public proxy.
 * This keeps browser requests inside /public so the app works with the repo's
 * built-in PHP server as well as Apache-style deployments.
 */
function api_url(string $file): string
{
    return app_url('api.php/' . ltrim($file, '/'));
}

/**
 * Build a page link for index.php routing.
 */
function page_url(string $pageKey, array $extraQuery = []): string
{
    $q = array_merge(['p' => app_resolve_route_key($pageKey, $pageKey)], $extraQuery);
    return app_url('index.php?' . http_build_query($q));
}

/**
 * Normalize/resolve current page key (for nav highlighting).
 */
function current_page(string $default = 'landing'): string
{
    $raw = $_GET['p'] ?? '';
    if ($raw === '' && isset($_GET['page'])) {
        $legacy = trim((string)$_GET['page']);
        if ($legacy !== '' && !ctype_digit($legacy)) {
            $raw = $legacy;
        }
    }
    if ($raw === '') {
        $raw = $default;
    }
    $p = strtolower(trim((string)$raw));
    if ($p === '') {
        return $default;
    }

    return app_resolve_route_key($p, $default);
}

/**
 * Render a nav link with active state.
 * Optional $extraQuery allows preserving filters when needed later.
 */
function nav_link_html(string $label, string $pageKey, string $currentPage, string $baseClass = 'nav-link', array $extraQuery = []): string
{
    $href = page_url($pageKey, $extraQuery);
    $active = ($currentPage === $pageKey) ? ' nav-link-active' : '';
    return '<a class="' . h($baseClass . $active) . '" href="' . h($href) . '">' . h($label) . '</a>';
}

function nav_link(string $label, string $pageKey, string $currentPage, array $extraQuery = []): string
{
    return nav_link_html($label, $pageKey, $currentPage, 'nav-link', $extraQuery);
}

/**
 * Resolve a safe in-app return URL for workflow pages.
 */
function resolve_internal_return_url(string $candidate, string $default): string
{
    $candidate = trim($candidate);
    if ($candidate === '') {
        return $default;
    }

    $parts = parse_url($candidate);
    if ($parts === false) {
        return $default;
    }

    if (isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return $default;
    }

    $path = (string)($parts['path'] ?? '');
    if ($path === '') {
        return $default;
    }

    $normalizedPath = ltrim($path, '/');
    if ($normalizedPath !== 'index.php') {
        return $default;
    }

    $resolved = '/' . $normalizedPath;
    if (isset($parts['query']) && $parts['query'] !== '') {
        $resolved .= '?' . $parts['query'];
    }
    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $resolved .= '#' . $parts['fragment'];
    }
    return $resolved;
}

/**
 * Simple redirect helper.
 */
function redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}
