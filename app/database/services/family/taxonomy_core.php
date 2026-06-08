<?php
declare(strict_types=1);

function db_family_taxonomy_normalize_token(?string $value): string
{
    $value = strtolower(trim((string)$value));
    if ($value === '') {
        return '';
    }
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function db_family_taxonomy_normalized_label_tokens(?string $value): array
{
    $raw = preg_split('/[^a-z0-9]+/i', strtolower((string)$value)) ?: [];
    $tokens = [];
    foreach ($raw as $part) {
        $part = db_family_taxonomy_normalize_token($part);
        if ($part !== '') {
            $tokens[] = $part;
        }
    }
    return array_values(array_unique($tokens));
}

function db_family_taxonomy_source_batch_supports_catalog_family(?string $sourceBatchLabel, ?string $catalogLabel): bool
{
    $catalogNorm = db_family_taxonomy_normalize_token($catalogLabel);
    if ($catalogNorm === '') {
        return false;
    }

    $batchTokens = db_family_taxonomy_normalized_label_tokens($sourceBatchLabel);
    if ($batchTokens === []) {
        return false;
    }

    if (in_array($catalogNorm, $batchTokens, true)) {
        return true;
    }

    foreach ($batchTokens as $token) {
        if (in_array($catalogNorm, db_family_taxonomy_governed_signal_targets($token), true)) {
            return true;
        }
    }

    return false;
}

function db_family_taxonomy_signal_secondary_tokens_include_catalog_family(?string $catalogLabel, ?string $vtSuggestedLabel, ?string $signalLabel = null): bool
{
    $catalogNorm = db_family_taxonomy_normalize_token($catalogLabel);
    if ($catalogNorm === '') {
        return false;
    }

    $tokens = db_family_taxonomy_normalized_label_tokens($vtSuggestedLabel);
    if ($tokens === []) {
        return false;
    }

    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    $generic = db_family_taxonomy_generic_tokens();
    $filtered = [];
    foreach ($tokens as $token) {
        if ($token === '' || in_array($token, $generic, true)) {
            continue;
        }
        $filtered[] = $token;
    }
    $filtered = array_values(array_unique($filtered));

    if ($filtered === []) {
        return false;
    }

    $matched = in_array($catalogNorm, $filtered, true);
    if (!$matched) {
        foreach ($filtered as $token) {
            if (in_array($catalogNorm, db_family_taxonomy_governed_signal_targets($token), true)) {
                $matched = true;
                break;
            }
        }
    }

    if (!$matched) {
        return false;
    }

    if ($signalNorm !== '' && $catalogNorm === $signalNorm) {
        return false;
    }

    return count($filtered) >= 2;
}

function db_family_taxonomy_signal_has_noisy_secondary_tokens(?string $signalLabel, ?string $vtSuggestedLabel): bool
{
    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    if ($signalNorm === '') {
        return false;
    }

    $tokens = db_family_taxonomy_normalized_label_tokens($vtSuggestedLabel);
    if ($tokens === [] || !in_array($signalNorm, $tokens, true)) {
        return false;
    }

    $secondary = [];
    foreach ($tokens as $token) {
        if ($token === $signalNorm) {
            continue;
        }
        $secondary[] = $token;
    }
    $secondary = array_values(array_unique($secondary));
    if ($secondary === []) {
        return false;
    }

    $primaryGovernedTargets = db_family_taxonomy_governed_signal_targets($signalLabel);
    $generic = db_family_taxonomy_generic_tokens();
    foreach ($secondary as $token) {
        if (in_array($token, $generic, true)) {
            continue;
        }
        if (db_family_taxonomy_signal_token_is_unstable($token)) {
            continue;
        }
        if (db_family_taxonomy_signal_token_is_weak_short($token)) {
            continue;
        }
        if (in_array($token, $primaryGovernedTargets, true)) {
            continue;
        }
        $governedTargets = db_family_taxonomy_governed_signal_targets($token);
        if (in_array($signalNorm, $governedTargets, true)) {
            continue;
        }
        return false;
    }

    return true;
}

function db_family_taxonomy_generic_tokens(): array
{
    return [
        'trojan',
        'adware',
        'spyware',
        'android',
        'malware',
        'masqueradingmalware',
        'riskware',
        'generic',
        'unknown',
        'ransomware',
        'infostealer',
        'stalkerware',
        'cryptominers',
        'bankertrojan',
        'fraudfinancialapps',
        '2fastealer',
        'btcturk',
        'trezorfakewallet',
        'jiotargets',
        'tiktok',
        'indiabanker2026',
        'androidmixi44origin',
        'badpack',
        'zombinder',
        'scarletmimic',
        'domestickitten',
        'starcruft',
        'hiddenadware',
        'cryptostealer',
        'wormablemalware',
        'aggresiveads',
        'tiktokspyware',
        'fraudpushnotifications',
        'spanishbanker',
        'andr',
        'msil',
        'drop',
        'fakeapp',
        'scamapp',
        'genericfca',
        'generickdq',
        'java',
        'corrupted',
        'rootkit',
        'vbransom',
        'w97m',
        'o97m',
        'hiddenadhrxja',
    ];
}

function db_family_taxonomy_governed_family_token_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }

    $map = [];
    $rows = db_all(
        'SELECT family_name, family_slug
         FROM ' . db_catalog_table('android_malware_family') . "
         WHERE NULLIF(TRIM(COALESCE(family_name, '')), '') IS NOT NULL
            OR NULLIF(TRIM(COALESCE(family_slug, '')), '') IS NOT NULL"
    );
    foreach ($rows as $row) {
        $tokens = [
            db_family_taxonomy_normalize_token((string)($row['family_name'] ?? '')),
            db_family_taxonomy_normalize_token((string)($row['family_slug'] ?? '')),
        ];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            $map[$token] = true;
        }
    }

    return $map;
}

function db_family_taxonomy_known_specific_signal_families(): array
{
    return [
        'congur',
        'dingwe',
    ];
}

function db_family_taxonomy_is_governed_family_token(string $token): bool
{
    $token = db_family_taxonomy_normalize_token($token);
    if ($token === '') {
        return false;
    }

    return isset(db_family_taxonomy_governed_family_token_map()[$token]);
}

function db_family_taxonomy_hold_signal_tokens(): array
{
    return [
        'agentb',
        'autopay',
        'bankbot',
        'bank',
        'dhtqjn',
        'driverpack',
        'fakewallet',
        'fakecop',
        'flhk',
        'flji',
        'gfdk',
        'hqwar',
        'hiddenad',
        'hiddenads',
        'hiddenapp',
        'jiagu',
        'knobot',
        'kylk',
        'smsspy',
        'metasploit',
        'meterpreter',
        'locker',
        'molerats',
        'blacklister',
        'dorxor',
        'cryptinject',
        'dump',
        'donot',
        'penguin',
        'polph',
        'python',
        'malformed',
        'pnsms',
        'secimage',
        'shell',
        'smforw',
        'spyagent',
        'smsthief',
        'androrat',
        'subscriber',
        'tiny',
        'twmobo',
        'hidden',
        'hiddapp',
        'office',
        'obfuse',
        'tencentprotect',
        'smssend',
        'lazarus',
        'clipbanker',
    ];
}

function db_family_taxonomy_signal_token_is_weak_short(string $signalNorm): bool
{
    if ($signalNorm === '' || strlen($signalNorm) > 4) {
        return false;
    }

    if (in_array($signalNorm, db_family_taxonomy_generic_tokens(), true)) {
        return false;
    }

    if (in_array($signalNorm, db_family_taxonomy_hold_signal_tokens(), true)) {
        return false;
    }

    $inventory = db_family_taxonomy_catalog_label_inventory();
    if (isset($inventory[$signalNorm])) {
        return false;
    }

    $aliases = db_family_taxonomy_signal_alias_map();
    if (isset($aliases[$signalNorm])) {
        return false;
    }

    $governedLabels = db_family_taxonomy_governed_signal_family_labels();
    if (isset($governedLabels[$signalNorm])) {
        return false;
    }

    return preg_match('/^[a-z0-9]{1,4}$/', $signalNorm) === 1;
}

function db_family_taxonomy_signal_token_is_unstable(string $signalNorm): bool
{
    if ($signalNorm === '') {
        return false;
    }

    if (db_family_taxonomy_is_governed_family_token($signalNorm)) {
        return false;
    }

    if (in_array($signalNorm, db_family_taxonomy_known_specific_signal_families(), true)) {
        return false;
    }

    if (preg_match('/^ag\d{6,8}$/', $signalNorm) === 1) {
        return true;
    }

    if (in_array($signalNorm, db_family_taxonomy_generic_tokens(), true)) {
        return true;
    }

    if (in_array($signalNorm, db_family_taxonomy_hold_signal_tokens(), true)) {
        return true;
    }

    return db_family_taxonomy_signal_token_is_opaque_singleton_noise($signalNorm)
        || db_family_taxonomy_signal_token_is_low_support_opaque_noise($signalNorm)
        || db_family_taxonomy_signal_token_is_diffuse_cross_family_noise($signalNorm);
}

function db_family_taxonomy_signal_token_is_opaque_singleton_noise(string $signalNorm): bool
{
    static $cache = [];

    if ($signalNorm === '') {
        return false;
    }
    if (array_key_exists($signalNorm, $cache)) {
        return $cache[$signalNorm];
    }
    if (preg_match('/^[a-z]{5,6}$/', $signalNorm) !== 1) {
        $cache[$signalNorm] = false;
        return false;
    }

    $inventory = db_family_taxonomy_catalog_label_inventory();
    if (isset($inventory[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $aliases = db_family_taxonomy_signal_alias_map();
    if (isset($aliases[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $governedLabels = db_family_taxonomy_governed_signal_family_labels();
    if (isset($governedLabels[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $distribution = db_family_taxonomy_signal_family_distribution([$signalNorm]);
    $familyRows = $distribution[$signalNorm] ?? [];
    if ($familyRows === []) {
        $cache[$signalNorm] = false;
        return false;
    }

    $totalRows = 0;
    $distinctFamilies = 0;
    foreach ($familyRows as $row) {
        $count = (int)($row['row_count'] ?? 0);
        if ($count <= 0) {
            continue;
        }
        $totalRows += $count;
        $distinctFamilies += 1;
    }

    $cache[$signalNorm] = $totalRows > 0 && $totalRows <= 2 && $distinctFamilies === 1;
    return $cache[$signalNorm];
}

function db_family_taxonomy_signal_token_is_low_support_opaque_noise(string $signalNorm): bool
{
    static $cache = [];

    if ($signalNorm === '') {
        return false;
    }
    if (array_key_exists($signalNorm, $cache)) {
        return $cache[$signalNorm];
    }
    if (preg_match('/^[a-z][a-z0-9]{4,15}$/', $signalNorm) !== 1) {
        $cache[$signalNorm] = false;
        return false;
    }
    if (preg_match('/(rat|bot|worm|bank|steal|spy)/', $signalNorm) === 1) {
        $cache[$signalNorm] = false;
        return false;
    }

    if (in_array($signalNorm, db_family_taxonomy_generic_tokens(), true) || in_array($signalNorm, db_family_taxonomy_hold_signal_tokens(), true)) {
        $cache[$signalNorm] = false;
        return false;
    }

    $inventory = db_family_taxonomy_catalog_label_inventory();
    if (isset($inventory[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $aliases = db_family_taxonomy_signal_alias_map();
    if (isset($aliases[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $governedLabels = db_family_taxonomy_governed_signal_family_labels();
    if (isset($governedLabels[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $distribution = db_family_taxonomy_signal_family_distribution([$signalNorm]);
    $familyRows = $distribution[$signalNorm] ?? [];
    if ($familyRows === []) {
        $cache[$signalNorm] = false;
        return false;
    }

    $totalRows = 0;
    $concreteFamilies = 0;
    foreach ($familyRows as $row) {
        $count = (int)($row['row_count'] ?? 0);
        $familyNorm = db_family_taxonomy_normalize_token((string)($row['catalog_family'] ?? ''));
        if ($count <= 0) {
            continue;
        }
        $totalRows += $count;
        if ($familyNorm !== '' && !in_array($familyNorm, db_family_taxonomy_generic_tokens(), true)) {
            $concreteFamilies += 1;
        }
    }

    $cache[$signalNorm] = $totalRows > 0 && $totalRows <= 6 && $concreteFamilies <= 1;
    return $cache[$signalNorm];
}

function db_family_taxonomy_signal_token_is_diffuse_cross_family_noise(string $signalNorm): bool
{
    static $cache = [];

    if ($signalNorm === '') {
        return false;
    }
    if (array_key_exists($signalNorm, $cache)) {
        return $cache[$signalNorm];
    }
    if (preg_match('/^[a-z][a-z0-9]{3,15}$/', $signalNorm) !== 1) {
        $cache[$signalNorm] = false;
        return false;
    }

    $inventory = db_family_taxonomy_catalog_label_inventory();
    if (isset($inventory[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $aliases = db_family_taxonomy_signal_alias_map();
    if (isset($aliases[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $governedLabels = db_family_taxonomy_governed_signal_family_labels();
    if (isset($governedLabels[$signalNorm])) {
        $cache[$signalNorm] = false;
        return false;
    }

    $distribution = db_family_taxonomy_signal_family_distribution([$signalNorm]);
    $familyRows = $distribution[$signalNorm] ?? [];
    if ($familyRows === []) {
        $cache[$signalNorm] = false;
        return false;
    }

    $totalRows = 0;
    $topCount = 0;
    $concreteFamilies = 0;
    foreach ($familyRows as $row) {
        $count = (int)($row['row_count'] ?? 0);
        $familyNorm = db_family_taxonomy_normalize_token((string)($row['catalog_family'] ?? ''));
        if ($count <= 0) {
            continue;
        }
        if ($familyNorm === '' || in_array($familyNorm, db_family_taxonomy_generic_tokens(), true)) {
            continue;
        }
        $totalRows += $count;
        $concreteFamilies += 1;
        if ($count > $topCount) {
            $topCount = $count;
        }
    }

    $dominance = $totalRows > 0 ? ($topCount / $totalRows) : 0.0;
    $cache[$signalNorm] = $totalRows >= 2 && $totalRows <= 20 && $concreteFamilies >= 2 && $dominance < 0.60;
    return $cache[$signalNorm];
}

function db_family_taxonomy_has_distinct_inventory_anchors(string $catalogNorm, string $signalNorm, ?array $inventory = null): bool
{
    if ($catalogNorm === '' || $signalNorm === '' || $catalogNorm === $signalNorm) {
        return false;
    }

    $inventory = is_array($inventory) ? $inventory : db_family_taxonomy_catalog_label_inventory();
    if (!isset($inventory[$catalogNorm], $inventory[$signalNorm])) {
        return false;
    }

    return strcasecmp((string)($inventory[$catalogNorm]['label'] ?? ''), (string)($inventory[$signalNorm]['label'] ?? '')) !== 0;
}

function db_family_taxonomy_signal_catalog_dominance(?string $signalLabel): array
{
    static $cache = [];

    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    if ($signalNorm === '') {
        return [
            'signal_norm' => '',
            'top_family_norm' => '',
            'top_family_label' => '',
            'top_count' => 0,
            'total_rows' => 0,
            'concrete_families' => 0,
            'dominance' => 0.0,
        ];
    }
    if (isset($cache[$signalNorm])) {
        return $cache[$signalNorm];
    }

    $distribution = db_family_taxonomy_signal_family_distribution([$signalNorm]);
    $familyRows = $distribution[$signalNorm] ?? [];
    $topFamilyNorm = '';
    $topFamilyLabel = '';
    $topCount = 0;
    $totalRows = 0;
    $concreteFamilies = 0;

    foreach ($familyRows as $row) {
        $familyLabel = (string)($row['catalog_family'] ?? '');
        $familyNorm = db_family_taxonomy_normalize_token($familyLabel);
        $count = (int)($row['row_count'] ?? 0);
        if ($count <= 0 || $familyNorm === '' || in_array($familyNorm, db_family_taxonomy_generic_tokens(), true)) {
            continue;
        }
        $totalRows += $count;
        $concreteFamilies++;
        if ($count > $topCount) {
            $topCount = $count;
            $topFamilyNorm = $familyNorm;
            $topFamilyLabel = $familyLabel;
        }
    }

    $cache[$signalNorm] = [
        'signal_norm' => $signalNorm,
        'top_family_norm' => $topFamilyNorm,
        'top_family_label' => $topFamilyLabel,
        'top_count' => $topCount,
        'total_rows' => $totalRows,
        'concrete_families' => $concreteFamilies,
        'dominance' => $totalRows > 0 ? ($topCount / $totalRows) : 0.0,
    ];
    return $cache[$signalNorm];
}

function db_family_taxonomy_signal_alias_map(): array
{
    return [
        'banbra' => 'pixpirate',
        'brats' => 'pixpirate',
        'hawkshaw' => 'cosmosrat',
        'cookiethief' => 'cookiestealer',
        'shopper' => 'shopaholic',
        'asacub' => 'comebot',
        'necro' => 'camscannernecron',
        'basdoor' => 'irata',
        'axespy' => 'actionspy',
        'arsink' => 'arsinkrat',
        'abarw' => 'arsinkrat',
        'aversefalc' => 'clayrat',
        'airrat' => 'arsinkrat',
        'cryxos' => 'pixpirate',
        'fsco' => 'arsinkrat',
        'cloput' => 'hiddenads',
        'codoor' => 'infostealer',
        'covidspy' => 'projectspy',
        'dfbmr' => 'teabot',
        'fhii' => 'terracotta',
        'goodnews' => 'smsworm',
        'boxter' => 'coyote',
        'keylogger' => 'godfather',
        'knobot' => 'eventbot',
        'kylk' => 'donot',
        'realrat' => 'irata',
        'teddad' => 'scylla',
        'trickbotcrypt' => 'trickbot',
        'vncspy' => 'promptspy',
        'wannalocker' => 'slockerwannacry',
        'wroba' => 'roamingmantis',
        'bankurt' => 'klopatra',
        'basbanke' => 'copybara',
        'jocker' => 'joker',
        'ag1552983' => 'clayrat',
        'ag1553283' => 'clayrat',
        'spymax' => 'spynote',
        'fqfv' => 'spyc23',
        'fakecalls' => 'fakecall',
        'exod' => 'exodus',
        'darkkomet' => 'darkcomet',
        'gravity' => 'gravityrat',
        'monocle' => 'monokle',
        'ahmyth' => 'ahmythspyware',
        'androrat' => 'caprarat',
        'monitorminor' => 'monitormirror',
        'mamont' => 'clayrat',
        'mobistar' => 'zazdi',
        'oscorp' => 'ubel',
        'polph' => 'flubot',
        'refalrat' => 'rafel',
        'sagnt' => 'spyloan',
        'youmi' => 'gigabud',
        'yaats' => 'pixbankbot',
        'albaniiutas' => 'bluetraveller',
    ];
}

function db_family_taxonomy_governed_signal_family_labels(): array
{
    static $labels = null;
    if (is_array($labels)) {
        return $labels;
    }

    $labels = [
        'dwphon' => 'Dwphon',
        'fakeplayer' => 'FakePlayer',
        'fakeadblocker' => 'FakeAdBlocker',
        'phonespy' => 'PhoneSpy',
        'rewardsteal' => 'RewardSteal',
        'rootnik' => 'Rootnik',
    ];

    try {
        $rows = db_all(
            'SELECT family_name
             FROM ' . db_catalog_table('android_malware_family') . "
             WHERE is_active = 1
               AND LOWER(COALESCE(family_status, 'active')) = 'active'"
        );
        foreach ($rows as $row) {
            $familyName = trim((string)($row['family_name'] ?? ''));
            $familyNorm = db_family_taxonomy_normalize_token($familyName);
            if ($familyNorm === '' || isset($labels[$familyNorm])) {
                continue;
            }
            $labels[$familyNorm] = $familyName;
        }
    } catch (Throwable $e) {
        // Fall back to the curated static seed map if the family table is unavailable.
    }

    return $labels;
}

function db_family_taxonomy_governed_signal_target(?string $signalLabel): string
{
    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    if ($signalNorm === '') {
        return '';
    }
    $aliases = db_family_taxonomy_signal_alias_map();
    return $aliases[$signalNorm] ?? '';
}

function db_family_taxonomy_governed_signal_targets(?string $signalLabel): array
{
    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    if ($signalNorm === '') {
        return [];
    }

    $targets = [];
    $primary = db_family_taxonomy_governed_signal_target($signalLabel);
    if ($primary !== '') {
        $targets[] = db_family_taxonomy_normalize_token($primary);
    }

    $secondaryMap = [
        'coper' => ['octo'],
        'wroba' => ['xloader'],
    ];

    foreach ($secondaryMap[$signalNorm] ?? [] as $target) {
        $targetNorm = db_family_taxonomy_normalize_token($target);
        if ($targetNorm !== '') {
            $targets[] = $targetNorm;
        }
    }

    return array_values(array_unique($targets));
}

function db_family_taxonomy_governed_signal_family_label(?string $signalLabel): string
{
    $signalNorm = db_family_taxonomy_normalize_token($signalLabel);
    if ($signalNorm === '') {
        return '';
    }

    $labels = db_family_taxonomy_governed_signal_family_labels();
    return $labels[$signalNorm] ?? '';
}

function db_family_taxonomy_sql_governed_alias_expr(string $catalogExpr, string $signalExpr): string
{
    $clauses = [];
    foreach (db_family_taxonomy_signal_alias_map() as $signalToken => $catalogToken) {
        $signalToken = str_replace("'", "''", strtolower($signalToken));
        $catalogToken = str_replace("'", "''", strtolower($catalogToken));
        $clauses[] = "(LOWER(TRIM(COALESCE({$signalExpr}, ''))) = '{$signalToken}' AND LOWER(TRIM(COALESCE({$catalogExpr}, ''))) = '{$catalogToken}')";
    }
    foreach ([
        'coper' => ['octo'],
        'wroba' => ['xloader'],
    ] as $signalToken => $targets) {
        $signalToken = str_replace("'", "''", strtolower($signalToken));
        foreach ($targets as $catalogToken) {
            $catalogToken = str_replace("'", "''", strtolower($catalogToken));
            $clauses[] = "(LOWER(TRIM(COALESCE({$signalExpr}, ''))) = '{$signalToken}' AND LOWER(TRIM(COALESCE({$catalogExpr}, ''))) = '{$catalogToken}')";
        }
    }
    if ($clauses === []) {
        return '0';
    }
    return '(' . implode(' OR ', $clauses) . ')';
}

function db_family_taxonomy_sql_vt_secondary_alias_expr(
    string $catalogExpr,
    string $vtLabelExpr,
    string $signalExpr = ''
): string
{
    $clauses = [];
    if ($signalExpr !== '') {
        $catalogTokenExpr = "LOWER(TRIM(COALESCE({$catalogExpr}, '')))";
        $signalTokenExpr = "LOWER(TRIM(COALESCE({$signalExpr}, '')))";
        $clauses[] = "(
            {$catalogTokenExpr} <> ''
            AND {$signalTokenExpr} <> {$catalogTokenExpr}
            AND LOWER(COALESCE({$vtLabelExpr}, '')) REGEXP CONCAT('(^|[^a-z0-9])', {$catalogTokenExpr}, '([^a-z0-9]|$)')
        )";
    }
    foreach (db_family_taxonomy_signal_alias_map() as $signalToken => $catalogToken) {
        $signalToken = str_replace("'", "''", strtolower($signalToken));
        $catalogToken = str_replace("'", "''", strtolower($catalogToken));
        $clauses[] = "(
            LOWER(TRIM(COALESCE({$catalogExpr}, ''))) = '{$catalogToken}'
            AND LOWER(COALESCE({$vtLabelExpr}, '')) REGEXP '(^|[^a-z0-9]){$signalToken}([^a-z0-9]|$)'
        )";
    }
    foreach ([
        'coper' => ['octo'],
        'wroba' => ['xloader'],
    ] as $signalToken => $targets) {
        $signalToken = str_replace("'", "''", strtolower($signalToken));
        foreach ($targets as $catalogToken) {
            $catalogToken = str_replace("'", "''", strtolower($catalogToken));
            $clauses[] = "(
                LOWER(TRIM(COALESCE({$catalogExpr}, ''))) = '{$catalogToken}'
                AND LOWER(COALESCE({$vtLabelExpr}, '')) REGEXP '(^|[^a-z0-9]){$signalToken}([^a-z0-9]|$)'
            )";
        }
    }
    if ($clauses === []) {
        return '0';
    }
    return '(' . implode(' OR ', $clauses) . ')';
}

function db_family_taxonomy_sql_source_batch_family_expr(string $catalogExpr, string $sourceBatchExpr): string
{
    $clauses = [];
    $seen = [];

    $familyRows = db_all(
        'SELECT family_name, family_slug
         FROM ' . db_catalog_table('android_malware_family') . "
         WHERE is_active = 1"
    );

    foreach ($familyRows as $row) {
        $catalogTokens = [
            db_family_taxonomy_normalize_token((string)($row['family_name'] ?? '')),
            db_family_taxonomy_normalize_token((string)($row['family_slug'] ?? '')),
        ];
        $catalogTokens = array_values(array_unique(array_filter($catalogTokens, static fn(string $v): bool => $v !== '')));
        if ($catalogTokens === []) {
            continue;
        }

        $sourceTokens = $catalogTokens;
        foreach (db_family_taxonomy_signal_alias_map() as $signalToken => $catalogToken) {
            $catalogTokenNorm = db_family_taxonomy_normalize_token($catalogToken);
            if (in_array($catalogTokenNorm, $catalogTokens, true)) {
                $sourceTokens[] = db_family_taxonomy_normalize_token($signalToken);
            }
        }
        $sourceTokens = array_values(array_unique(array_filter($sourceTokens, static fn(string $v): bool => $v !== '')));

        foreach ($catalogTokens as $catalogToken) {
            foreach ($sourceTokens as $sourceToken) {
                $key = $catalogToken . '|' . $sourceToken;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $catalogSql = str_replace("'", "''", strtolower($catalogToken));
                $sourceSql = str_replace("'", "''", strtolower($sourceToken));
                $clauses[] = "(
                    LOWER(TRIM(COALESCE({$catalogExpr}, ''))) = '{$catalogSql}'
                    AND LOWER(COALESCE({$sourceBatchExpr}, '')) REGEXP '(^|[^a-z0-9]){$sourceSql}([^a-z0-9]|$)'
                )";
            }
        }
    }

    if ($clauses === []) {
        return '0';
    }
    return '(' . implode(' OR ', $clauses) . ')';
}

function db_family_taxonomy_sql_exact_hold_signal_expr(string $signalExpr): string
{
    $tokens = array_values(array_unique(array_map(
        static fn(string $value): string => strtolower(trim($value)),
        array_merge(db_family_taxonomy_generic_tokens(), db_family_taxonomy_hold_signal_tokens())
    )));
    if ($tokens === []) {
        return '0';
    }

    return 'LOWER(TRIM(COALESCE(' . $signalExpr . ", ''))) IN (" . db_family_taxonomy_sql_in_list($tokens) . ')';
}

function db_family_taxonomy_sql_authority_typed_hold_alignment_expr(
    string $catalogExpr,
    string $signalExpr,
    string $authorityBucketExpr,
    string $authorityFamilySlugExpr,
    string $authorityFamilyNameExpr = ''
): string {
    $catalogNormExpr = db_family_taxonomy_sql_normalize_expr($catalogExpr);
    $authoritySlugNormExpr = db_family_taxonomy_sql_normalize_expr($authorityFamilySlugExpr);
    $familyMatchExpr = "{$catalogNormExpr} = {$authoritySlugNormExpr}";
    if ($authorityFamilyNameExpr !== '') {
        $authorityNameNormExpr = db_family_taxonomy_sql_normalize_expr($authorityFamilyNameExpr);
        $familyMatchExpr = '(' . $familyMatchExpr
            . " OR {$catalogNormExpr} = {$authorityNameNormExpr}"
            . ')';
    }

    return "(
        LOWER(TRIM(COALESCE({$authorityBucketExpr}, ''))) = 'authority_family_typed'
        AND {$familyMatchExpr}
        AND " . db_family_taxonomy_sql_exact_hold_signal_expr($signalExpr) . '
    )';
}

function db_family_taxonomy_sql_authority_typed_alias_expr(
    string $signalExpr,
    string $authorityBucketExpr,
    string $authorityFamilySlugExpr,
    string $authorityFamilyNameExpr = ''
): string {
    $clauses = [];
    foreach (db_family_taxonomy_signal_alias_map() as $signalToken => $catalogToken) {
        $signalSql = str_replace("'", "''", strtolower(trim($signalToken)));
        $catalogSql = str_replace("'", "''", strtolower(trim($catalogToken)));
        $authoritySlugNormExpr = db_family_taxonomy_sql_normalize_expr($authorityFamilySlugExpr);
        $familyMatchExpr = "{$authoritySlugNormExpr} = '{$catalogSql}'";
        if ($authorityFamilyNameExpr !== '') {
            $authorityNameNormExpr = db_family_taxonomy_sql_normalize_expr($authorityFamilyNameExpr);
            $familyMatchExpr = '(' . $familyMatchExpr
                . " OR {$authorityNameNormExpr} = '{$catalogSql}'"
                . ')';
        }
        $clauses[] = "(
            LOWER(TRIM(COALESCE({$authorityBucketExpr}, ''))) = 'authority_family_typed'
            AND LOWER(TRIM(COALESCE({$signalExpr}, ''))) = '{$signalSql}'
            AND {$familyMatchExpr}
        )";
    }

    if ($clauses === []) {
        return '0';
    }
    return '(' . implode(' OR ', $clauses) . ')';
}

function db_family_taxonomy_sql_authority_typed_secondary_alias_expr(
    string $vtLabelExpr,
    string $authorityBucketExpr,
    string $authorityFamilySlugExpr,
    string $authorityFamilyNameExpr = ''
): string {
    $clauses = [];
    foreach (db_family_taxonomy_signal_alias_map() as $signalToken => $catalogToken) {
        $signalSql = str_replace("'", "''", strtolower(trim($signalToken)));
        $catalogSql = str_replace("'", "''", strtolower(trim($catalogToken)));
        $authoritySlugNormExpr = db_family_taxonomy_sql_normalize_expr($authorityFamilySlugExpr);
        $familyMatchExpr = "{$authoritySlugNormExpr} = '{$catalogSql}'";
        if ($authorityFamilyNameExpr !== '') {
            $authorityNameNormExpr = db_family_taxonomy_sql_normalize_expr($authorityFamilyNameExpr);
            $familyMatchExpr = '(' . $familyMatchExpr
                . " OR {$authorityNameNormExpr} = '{$catalogSql}'"
                . ')';
        }
        $clauses[] = "(
            LOWER(TRIM(COALESCE({$authorityBucketExpr}, ''))) = 'authority_family_typed'
            AND LOWER(COALESCE({$vtLabelExpr}, '')) REGEXP '(^|[^a-z0-9]){$signalSql}([^a-z0-9]|$)'
            AND {$familyMatchExpr}
        )";
    }

    foreach ([
        'coper' => ['octo'],
        'wroba' => ['xloader'],
    ] as $signalToken => $targets) {
        $signalSql = str_replace("'", "''", strtolower(trim($signalToken)));
        foreach ($targets as $catalogToken) {
            $catalogSql = str_replace("'", "''", strtolower(trim($catalogToken)));
            $authoritySlugNormExpr = db_family_taxonomy_sql_normalize_expr($authorityFamilySlugExpr);
            $familyMatchExpr = "{$authoritySlugNormExpr} = '{$catalogSql}'";
            if ($authorityFamilyNameExpr !== '') {
                $authorityNameNormExpr = db_family_taxonomy_sql_normalize_expr($authorityFamilyNameExpr);
                $familyMatchExpr = '(' . $familyMatchExpr
                    . " OR {$authorityNameNormExpr} = '{$catalogSql}'"
                    . ')';
            }
            $clauses[] = "(
                LOWER(TRIM(COALESCE({$authorityBucketExpr}, ''))) = 'authority_family_typed'
                AND LOWER(COALESCE({$vtLabelExpr}, '')) REGEXP '(^|[^a-z0-9]){$signalSql}([^a-z0-9]|$)'
                AND {$familyMatchExpr}
            )";
        }
    }

    if ($clauses === []) {
        return '0';
    }
    return '(' . implode(' OR ', $clauses) . ')';
}

function db_family_taxonomy_sql_normalize_expr(string $expr): string
{
    $sql = "LOWER(TRIM(COALESCE({$expr}, '')))";
    foreach ([' ', '.', '-', '_', '/', '(', ')'] as $char) {
        $escaped = str_replace("'", "''", $char);
        $sql = "REPLACE({$sql}, '{$escaped}', '')";
    }
    return $sql;
}

function db_family_taxonomy_sql_in_list(array $values): string
{
    return implode(', ', array_map(
        static fn(string $value): string => "'" . str_replace("'", "''", strtolower($value)) . "'",
        $values
    ));
}
