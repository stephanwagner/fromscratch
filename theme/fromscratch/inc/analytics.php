<?php

/**
 * Single Matomo bulk fetch dimensions (dashboard widget, Analytics screen, weekly email).
 */
const FS_MATOMO_STATS_CANONICAL_DAYS = 14;
const FS_MATOMO_STATS_CANONICAL_WEEKS = 9;

/**
 * Transient holding raw today/yesterday visit totals for the wp-admin dashboard widget.
 * Derived from FS_MATOMO_STATS_CANONICAL_* daily series (no separate Matomo requests).
 */
const FS_MATOMO_DASHBOARD_VISITS_TRANSIENT = 'fs_dashboard_matomo_stats_counts_v3';

/** Prevents stacking multiple wp-cron jobs for the same dashboard refresh. */
const FS_MATOMO_BACKGROUND_REFRESH_LOCK = 'fs_matomo_bg_refresh_lock';

/**
 * Settings
 */
function fs_dashboard_get_analytics_settings(): array
{
    return [
        'lineChart' => [
            'borderWidth' => 2,
            'tension' => 0.3,
            'pointRadius' => 3,
            'pointHoverRadius' => 4,
        ],
        'barChart' => [
            'borderWidth' => 2,
            'maxBarThickness' => 56,
            'borderRadius' => 8,
        ],
        'colors' => [
            [
                'fill' => '#2284e5',
                'transparent' => '#2284e535',
            ],
            [
                'fill' => '#8f70cc',
                'transparent' => '#8f70cc35',
            ],
            [
                'fill' => '#ff6673',
                'transparent' => '#ff667340',
            ],
        ],
    ];
}

/**
 * Dashboard > Analytics page.
 */
function fs_dashboard_can_access_statistics(): bool
{
    if (!is_user_logged_in()) {
        return false;
    }
    if (current_user_can('manage_options')) {
        return true;
    }
    return function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id());
}

add_action('admin_menu', function (): void {
    if (!fs_dashboard_can_access_statistics()) {
        return;
    }
    add_submenu_page(
        'index.php',
        __('Analytics', 'fromscratch'),
        __('Analytics', 'fromscratch'),
        'read',
        fs_dashboard_stats_page_slug(),
        'fs_render_dashboard_statistics_page'
    );
});


/**
 * Get Matomo tracking settings when available.
 *
 * Uses Developer → System options when the Matomo feature is enabled, or ACF option fields
 * (`matomo-url`, `matomo-site-id`, `matomo-token-auth`) when Advanced Custom Fields is present.
 *
 * @return array{url:string,site_id:int,token:string}|null
 */
function fs_dashboard_matomo_settings(): ?array
{
    if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('matomo')) {
        return null;
    }

    $url = '';
    $site_id = 0;
    $token = '';

    if (function_exists('get_field')) {
        $url = trim((string) call_user_func('get_field', 'matomo-url', 'option'));
        $site_id = (int) call_user_func('get_field', 'matomo-site-id', 'option');
        $token = trim((string) call_user_func('get_field', 'matomo-token-auth', 'option'));
    }

    if ($url === '' || $site_id <= 0 || $token === '') {
        $url = trim((string) get_option('fromscratch_matomo_url', ''));
        $site_id = (int) get_option('fromscratch_matomo_site_id', 1);
        $token = trim((string) get_option('fromscratch_matomo_token_auth', ''));
    }

    if ($url === '' || $site_id <= 0 || $token === '') {
        return null;
    }

    return [
        'url' => trailingslashit($url),
        'site_id' => $site_id,
        'token' => $token,
    ];
}

/**
 * Store last Matomo API error for admin display (request lifecycle only).
 */
function fs_dashboard_set_last_matomo_error(string $message): void
{
    $GLOBALS['fs_dashboard_last_matomo_error'] = $message;
}

function fs_dashboard_get_last_matomo_error(): string
{
    return (string) ($GLOBALS['fs_dashboard_last_matomo_error'] ?? '');
}

/**
 * Transient key for the Matomo day/week bulk response (must match fs_dashboard_get_matomo_daily_and_weekly).
 */
function fs_dashboard_matomo_bulk_cache_key(int $days, int $weeks): string
{
    $settings = fs_dashboard_matomo_settings();
    if ($settings === null) {
        return '';
    }
    $days = max(1, min(365, $days));
    $weeks = max(1, min(104, $weeks));

    return 'fromscratch_matomo_day_week_' . md5(wp_json_encode([
        'url' => $settings['url'],
        'site_id' => (int) $settings['site_id'],
        'token' => $settings['token'],
        'days' => $days,
        'weeks' => $weeks,
        'bulk_schema' => 17,
    ]));
}

/**
 * Store today / yesterday visit counts for the admin home screen (integers, 1h TTL).
 *
 * @param array<int, array{date:string,unique:int,visits:int,pageviews:int}> $daily
 */
function fs_matomo_sync_dashboard_quick_stats_from_daily(array $daily): void
{
    $n = count($daily);
    if ($n === 0) {
        set_transient(FS_MATOMO_DASHBOARD_VISITS_TRANSIENT, ['today' => 0, 'yesterday' => 0], HOUR_IN_SECONDS);

        return;
    }
    $today = (int) ($daily[$n - 1]['visits'] ?? 0);
    $yesterday = $n >= 2 ? (int) ($daily[$n - 2]['visits'] ?? 0) : 0;
    set_transient(FS_MATOMO_DASHBOARD_VISITS_TRANSIENT, ['today' => $today, 'yesterday' => $yesterday], HOUR_IN_SECONDS);
}

/**
 * Invalidate canonical bulk cache and fetch everything in one Matomo round-trip.
 *
 * @return array<string, mixed>
 */
function fs_matomo_statistics_refresh_full(): array
{
    $key = fs_dashboard_matomo_bulk_cache_key(FS_MATOMO_STATS_CANONICAL_DAYS, FS_MATOMO_STATS_CANONICAL_WEEKS);
    if ($key !== '') {
        delete_transient($key);
    }

    return fs_matomo_get_statistics();
}

/**
 * Single entry point for Matomo analytics (one bulk API request, cached 1 hour).
 * Dashboard “today / yesterday” are derived from the `daily` series — no second HTTP call.
 *
 * @return array<string, mixed>
 */
function fs_matomo_get_statistics(): array
{
    return fs_dashboard_get_matomo_daily_and_weekly(FS_MATOMO_STATS_CANONICAL_DAYS, FS_MATOMO_STATS_CANONICAL_WEEKS);
}

/** wp-cron: runs full Matomo refresh requested from the dashboard home (non-blocking). */
add_action('fs_matomo_background_statistics_refresh', static function (): void {
    try {
        if (function_exists('fs_matomo_statistics_refresh_full')) {
            fs_matomo_statistics_refresh_full();
        }
    } finally {
        delete_transient(FS_MATOMO_BACKGROUND_REFRESH_LOCK);
    }
});

/**
 * Last N calendar dates ending on "today" in the WordPress site timezone (oldest first).
 * Matomo day keys follow the tracked site's calendar; WP timezone usually matches that site.
 *
 * @return array<int, string> Y-m-d
 */
function fs_dashboard_matomo_site_calendar_dates(int $days): array
{
    $days = max(1, min(365, $days));
    $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
    try {
        $today = new \DateTimeImmutable('today', $tz);
    } catch (\Exception $e) {
        $today = (new \DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
    }
    $keys = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $keys[] = $today->modify('-' . $i . ' days')->format('Y-m-d');
    }

    return $keys;
}

/**
 * Normalize a VisitsSummary.get API payload to a single stats row.
 */
function fs_dashboard_matomo_normalize_visits_summary_payload($payload): ?array
{
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['value']) && is_array($payload['value'])) {
        $payload = $payload['value'];
    }
    if (isset($payload['result']) && $payload['result'] === 'error') {
        return null;
    }
    if (isset($payload['nb_visits']) || isset($payload['avg_time_on_site'])) {
        return $payload;
    }
    foreach ($payload as $v) {
        if (is_array($v) && (isset($v['nb_visits']) || isset($v['avg_time_on_site']))) {
            return $v;
        }
    }

    return null;
}

/**
 * Metrics from VisitsSummary.get (range last90), same request as average time.
 *
 * @return array{avg_time_on_site:int,nb_visits:int,nb_actions:int,nb_uniq_visitors:int,bounce_count:int}
 */
function fs_dashboard_matomo_parse_visits_summary_90d($payload): array
{
    $empty = [
        'avg_time_on_site' => 0,
        'nb_visits' => 0,
        'nb_actions' => 0,
        'nb_uniq_visitors' => 0,
        'bounce_count' => 0,
    ];
    $stats = fs_dashboard_matomo_normalize_visits_summary_payload($payload);
    if ($stats === null) {
        return $empty;
    }

    return [
        'avg_time_on_site' => max(0, (int) round((float) ($stats['avg_time_on_site'] ?? 0))),
        'nb_visits' => max(0, (int) ($stats['nb_visits'] ?? 0)),
        'nb_actions' => max(0, (int) ($stats['nb_actions'] ?? 0)),
        'nb_uniq_visitors' => max(0, (int) ($stats['nb_uniq_visitors'] ?? 0)),
        'bounce_count' => max(0, (int) ($stats['bounce_count'] ?? 0)),
    ];
}

/**
 * @return array{avg_time_on_site:int,nb_visits:int,nb_actions:int,nb_uniq_visitors:int,bounce_count:int}
 */
function fs_dashboard_default_visits_summary_90d(): array
{
    return [
        'avg_time_on_site' => 0,
        'nb_visits' => 0,
        'nb_actions' => 0,
        'nb_uniq_visitors' => 0,
        'bounce_count' => 0,
    ];
}

/**
 * VisitFrequency.get (previous 90 days): new vs returning visits and actions per visit.
 *
 * @return array{
 *   nb_visits_new:int,
 *   nb_visits_returning:int,
 *   returning_visits_pct:int|null,
 *   nb_actions_per_visit_new:float|null,
 *   nb_actions_per_visit_returning:float|null
 * }
 */
function fs_dashboard_matomo_parse_visit_frequency_90d($payload): array
{
    $empty = [
        'nb_visits_new' => 0,
        'nb_visits_returning' => 0,
        'returning_visits_pct' => null,
        'nb_actions_per_visit_new' => null,
        'nb_actions_per_visit_returning' => null,
    ];
    if (!is_array($payload)) {
        return $empty;
    }
    if (isset($payload['value']) && is_array($payload['value'])) {
        $payload = $payload['value'];
    }
    if (isset($payload['result']) && $payload['result'] === 'error') {
        return $empty;
    }

    $n = max(0, (int) ($payload['nb_visits_new'] ?? 0));
    $r = max(0, (int) ($payload['nb_visits_returning'] ?? 0));
    $total = $n + $r;
    $returning_pct = $total > 0 ? (int) round(($r / $total) * 100) : null;

    $apv_new = null;
    if (isset($payload['nb_actions_per_visit_new']) && $payload['nb_actions_per_visit_new'] !== '') {
        $apv_new = round((float) $payload['nb_actions_per_visit_new'], 2);
    }
    $apv_ret = null;
    if (isset($payload['nb_actions_per_visit_returning']) && $payload['nb_actions_per_visit_returning'] !== '') {
        $apv_ret = round((float) $payload['nb_actions_per_visit_returning'], 2);
    }

    return [
        'nb_visits_new' => $n,
        'nb_visits_returning' => $r,
        'returning_visits_pct' => $returning_pct,
        'nb_actions_per_visit_new' => $apv_new,
        'nb_actions_per_visit_returning' => $apv_ret,
    ];
}

/**
 * @return array{
 *   nb_visits_new:int,
 *   nb_visits_returning:int,
 *   returning_visits_pct:int|null,
 *   nb_actions_per_visit_new:float|null,
 *   nb_actions_per_visit_returning:float|null
 * }
 */
function fs_dashboard_default_visit_frequency_90d(): array
{
    return [
        'nb_visits_new' => 0,
        'nb_visits_returning' => 0,
        'returning_visits_pct' => null,
        'nb_actions_per_visit_new' => null,
        'nb_actions_per_visit_returning' => null,
    ];
}

/**
 * Boxed list: 90-day site summary under the devices chart.
 *
 * @param array{avg_time_on_site?:int,nb_visits?:int,nb_actions?:int,nb_uniq_visitors?:int,bounce_count?:int} $s
 * @param array{
 *   nb_visits_new?:int,
 *   nb_visits_returning?:int,
 *   returning_visits_pct?:int|null,
 *   nb_actions_per_visit_new?:float|null,
 *   nb_actions_per_visit_returning?:float|null
 * } $visit_frequency
 */
function fs_dashboard_render_visits_summary_90d_box(array $s, array $visit_frequency = []): void
{
    $s = array_merge(fs_dashboard_default_visits_summary_90d(), $s);
    $vf = array_merge(fs_dashboard_default_visit_frequency_90d(), $visit_frequency);
    $avg = (int) $s['avg_time_on_site'];
    $visits = (int) $s['nb_visits'];
    $actions = (int) $s['nb_actions'];
    $bounce = (int) $s['bounce_count'];
    $bounce_pct = $visits > 0 ? (int) round(($bounce / $visits) * 100) : null;
    $apv = $visits > 0 ? round($actions / $visits, 1) : null;
    $ret_pct = isset($vf['returning_visits_pct']) && $vf['returning_visits_pct'] !== null ? (int) $vf['returning_visits_pct'] : null;
    $apv_new = $vf['nb_actions_per_visit_new'] ?? null;
    $apv_ret = $vf['nb_actions_per_visit_returning'] ?? null;
?>
    <div class="fs-chart-container">
        <ul class="fs-visits-summary-list">
            <li>
                <span class="fs-visits-summary-list__label"><?= esc_html__('Average visit duration', 'fromscratch') ?></span>
                <span class="fs-visits-summary-list__value"><?= esc_html(fs_dashboard_format_duration_seconds($avg)) ?></span>
            </li>
            <li>
                <span class="fs-visits-summary-list__label"><?= esc_html__('Bounce rate', 'fromscratch') ?></span>
                <span class="fs-visits-summary-list__value"><?= $bounce_pct === null ? '–' : esc_html(sprintf(__('%d%%', 'fromscratch'), $bounce_pct)) ?></span>
            </li>
            <li>
                <span class="fs-visits-summary-list__label"><?= esc_html__(
                    /* translators: VisitFrequency: share of all visits (90 days) that are from returning visitors; value column shows e.g. 25%%. */
                    'Returning visitors',
                    'fromscratch'
                ) ?></span>
                <span class="fs-visits-summary-list__value"><?= $ret_pct === null ? '–' : esc_html($ret_pct) . ' %' ?></span>
            </li>
            <li>
                <span class="fs-visits-summary-list__label"><?= esc_html__('Actions per visit', 'fromscratch') ?></span>
                <span class="fs-visits-summary-list__value"><?= $apv === null ? '–' : esc_html(number_format_i18n($apv, 1)) ?></span>
            </li>
            <li>
                <span class="fs-visits-summary-list__label"><?= esc_html__('Actions per visit (new visitors)', 'fromscratch') ?></span>
                <span class="fs-visits-summary-list__value"><?= $apv_new === null ? '–' : esc_html(number_format_i18n((float) $apv_new, 1)) ?></span>
            </li>
            <li>
                <span class="fs-visits-summary-list__label"><?= esc_html__('Actions per visit (returning visitors)', 'fromscratch') ?></span>
                <span class="fs-visits-summary-list__value"><?= $apv_ret === null ? '–' : esc_html(number_format_i18n((float) $apv_ret, 1)) ?></span>
            </li>
        </ul>
    </div>
<?php
}

/**
 * Human-readable duration for Matomo seconds (e.g. average time on site).
 */
function fs_dashboard_format_duration_seconds(int $seconds): string
{
    if ($seconds <= 0) {
        return '–';
    }

    if ($seconds < 60) {
        return sprintf(
            /* translators: %d: seconds */
            __('%d s', 'fromscratch'),
            $seconds
        );
    }

    $m = intdiv($seconds, 60);
    $s = $seconds % 60;

    if ($m < 60) {
        return $s > 0
            ? sprintf(
                /* translators: 1: minutes, 2: seconds */
                __('%1$d min %2$d s', 'fromscratch'),
                $m,
                $s
            )
            : sprintf(
                /* translators: %d: whole minutes */
                __('%d min', 'fromscratch'),
                $m
            );
    }

    $h = intdiv($m, 60);
    $m = $m % 60;

    return sprintf(
        /* translators: 1: hours, 2: minutes */
        __('%1$d h %2$d min', 'fromscratch'),
        $h,
        $m
    );
}

/**
 * @param array<int, array{hits?:int}> $pages
 */
function fs_dashboard_top_pages_max_hits(array $pages): int
{
    $m = 0;
    foreach ($pages as $row) {
        $m = max($m, (int) ($row['hits'] ?? 0));
    }

    return $m;
}

/**
 * @param array<int, array{label:string,url:string,hits:int}> $pages
 */
function fs_dashboard_render_top_pages_table(array $pages): void
{
    if ($pages === []) {
        return;
    }
    $max_hits = fs_dashboard_top_pages_max_hits($pages);
?>
    <table class="widefat striped fs-stats-table fs-top-pages-table" style="margin: 0;">
        <thead>
            <tr>
                <th scope="col" class="fs-top-pages-table__rank">
                    <span class="screen-reader-text"><?= esc_html__('Rank', 'fromscratch') ?></span>
                </th>
                <th scope="col" class="fs-top-pages-table__page"><?= esc_html__('Page', 'fromscratch') ?></th>
                <th scope="col" class="fs-stats-metric fs-stats-metric--pageviews"><?= esc_html__('Hits', 'fromscratch') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 0;
            foreach ($pages as $row) :
                $i++;
                $label = (string) ($row['label'] ?? '');
                $url = (string) ($row['url'] ?? '');
                $hits = (int) ($row['hits'] ?? 0);
                $text = $label !== '' ? $label : $url;
                $w = $max_hits <= 0 ? 0 : (int) min(100, max(0, (int) round(($hits / $max_hits) * 100)));
            ?>
                <tr>
                    <td class="fs-top-pages-table__rank"><?= esc_html((string) $i) ?></td>
                    <td class="fs-top-pages-table__page">
                        <?php if ($url !== '') : ?>
                            <a href="<?= esc_url($url) ?>" target="_blank" rel="noopener noreferrer" title="<?= esc_attr($text) ?>"><?= esc_html($text) ?></a>
                        <?php else : ?>
                            <span title="<?= esc_attr($text) ?>"><?= esc_html($text) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="fs-stats-metric fs-stats-metric--pageviews">
                        <span class="fs-stats-metric__value"><?= esc_html(number_format_i18n($hits)) ?></span>
                        <span class="fs-stats-metric__bar" aria-hidden="true">
                            <span class="fs-stats-metric__track">
                                <span class="fs-stats-metric__fill fs-stats-metric__fill--pageviews" style="width: <?= $w ?>%; background-color: <?= esc_attr(fs_dashboard_get_analytics_settings()['colors'][2]['fill']) ?>;"></span>
                            </span>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * @param array<int, array{label:string,url:string,hits:int}> $referrers
 */
function fs_dashboard_render_top_referrers_table(array $referrers): void
{
    if ($referrers === []) {
        return;
    }
    $max_hits = fs_dashboard_top_pages_max_hits($referrers);
?>
    <table class="widefat striped fs-stats-table fs-top-pages-table" style="margin: 0;">
        <thead>
            <tr>
                <th scope="col" class="fs-top-pages-table__rank">
                    <span class="screen-reader-text"><?= esc_html__('Rank', 'fromscratch') ?></span>
                </th>
                <th scope="col" class="fs-top-pages-table__page"><?= esc_html__('Referrer', 'fromscratch') ?></th>
                <th scope="col" class="fs-stats-metric fs-stats-metric--pageviews"><?= esc_html__('Visits', 'fromscratch') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 0;
            foreach ($referrers as $row) :
                $i++;
                $label = (string) ($row['label'] ?? '');
                $url = (string) ($row['url'] ?? '');
                $hits = (int) ($row['hits'] ?? 0);
                $text = $label !== '' ? $label : $url;
                $w = $max_hits <= 0 ? 0 : (int) min(100, max(0, (int) round(($hits / $max_hits) * 100)));
            ?>
                <tr>
                    <td class="fs-top-pages-table__rank"><?= esc_html((string) $i) ?></td>
                    <td class="fs-top-pages-table__page">
                        <?php if ($url !== '') : ?>
                            <a href="<?= esc_url($url) ?>" target="_blank" rel="noopener noreferrer" title="<?= esc_attr($text) ?>"><?= esc_html($text) ?></a>
                        <?php else : ?>
                            <span title="<?= esc_attr($text) ?>"><?= esc_html($text) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="fs-stats-metric fs-stats-metric--pageviews">
                        <span class="fs-stats-metric__value"><?= esc_html(number_format_i18n($hits)) ?></span>
                        <span class="fs-stats-metric__bar" aria-hidden="true">
                            <span class="fs-stats-metric__track">
                                <span class="fs-stats-metric__fill fs-stats-metric__fill--pageviews" style="width: <?= $w ?>%; background-color: <?= esc_attr(fs_dashboard_get_analytics_settings()['colors'][2]['fill']) ?>;"></span>
                            </span>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Skip WordPress Customizer / preview URLs that pollute top pages (e.g. customize_changeset_uuid=…).
 */
function fs_dashboard_matomo_is_excluded_top_page_row(string $url, string $label): bool
{
    $haystack = strtolower($url . ' ' . $label);
    $markers = [
        'customize_changeset_uuid',
        'customize_messenger_channel',
        'customize_autosaved=',
        'wp_customize=',
    ];
    foreach ($markers as $m) {
        if (str_contains($haystack, $m)) {
            return true;
        }
    }
    // Theme-switching inside the customizer (often with other customize_* params).
    if (str_contains($haystack, 'customize_theme=')) {
        return true;
    }

    return false;
}

/**
 * Rows from Referrers.getWebsites or Referrers.getSearchEngines (nb_visits). Omits rows with no URL after normalization.
 *
 * @param mixed $payload
 *
 * @return array<int, array{label:string,url:string,hits:int}>
 */
function fs_dashboard_matomo_parse_referrer_table_rows($payload): array
{
    if (!is_array($payload)) {
        return [];
    }
    if (isset($payload['value']) && is_array($payload['value'])) {
        $payload = $payload['value'];
    }
    if (isset($payload['result']) && $payload['result'] === 'error') {
        return [];
    }
    $out = [];
    foreach ($payload as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = isset($row['label']) ? trim((string) $row['label']) : '';
        $url = isset($row['url']) ? trim((string) $row['url']) : '';
        if ($label === '') {
            continue;
        }
        if ($url === '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/iu', $label)) {
            $url = 'https://' . $label;
        }
        if ($url === '') {
            continue;
        }
        $visits = (int) ($row['nb_visits'] ?? $row['sum_nb_visits'] ?? 0);
        $out[] = [
            'label' => $label,
            'url' => $url,
            'hits' => max(0, $visits),
        ];
    }

    return $out;
}

/**
 * Merge website + search-engine referrer rows by highest nb_visits per URL, then global top $limit.
 *
 * @return array<int, array{label:string,url:string,hits:int}>
 */
function fs_dashboard_matomo_merge_top_referrers(array $website_rows, array $engine_rows, int $limit = 10): array
{
    $by_url = [];
    foreach (array_merge($website_rows, $engine_rows) as $row) {
        if (!is_array($row) || !isset($row['url'], $row['hits'])) {
            continue;
        }
        $key = strtolower((string) $row['url']);
        if ($key === '') {
            continue;
        }
        $h = (int) $row['hits'];
        if (!isset($by_url[$key]) || (int) $by_url[$key]['hits'] < $h) {
            $by_url[$key] = $row;
        }
    }
    $merged = array_values($by_url);
    if ($merged === []) {
        return [];
    }
    usort($merged, static function (array $a, array $b): int {
        return ((int) ($b['hits'] ?? 0)) <=> ((int) ($a['hits'] ?? 0));
    });

    return array_slice($merged, 0, max(0, $limit));
}

/**
 * First day of recording from SitesManager.getSiteFromId: prefers start_date (tracking from), else ts_created.
 *
 * @param mixed $payload
 */
function fs_dashboard_matomo_parse_site_first_recording_ts($payload): ?int
{
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['result']) && $payload['result'] === 'error') {
        return null;
    }
    if (isset($payload['value']) && is_array($payload['value'])) {
        $payload = $payload['value'];
    }
    $row = $payload;
    if (isset($payload[0]) && is_array($payload[0])) {
        $row = $payload[0];
    }
    if (!is_array($row)) {
        return null;
    }
    $start = isset($row['start_date']) ? trim((string) $row['start_date']) : '';
    if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $start, wp_timezone());
        if ($dt !== false) {
            return $dt->setTime(0, 0, 0)->getTimestamp();
        }
    }
    if (isset($row['ts_created']) && $row['ts_created'] !== '') {
        $tc = $row['ts_created'];
        if (is_numeric($tc)) {
            $ts = (int) $tc;
            return $ts > 0 ? $ts : null;
        }
        $parsed = strtotime((string) $tc);
        return $parsed > 0 ? $parsed : null;
    }

    return null;
}

/**
 * @return array{nb_visits:int,since_ts:int|null}
 */
function fs_dashboard_default_alltime_summary(): array
{
    return [
        'nb_visits' => 0,
        'since_ts' => null,
    ];
}

/**
 * Bulk Matomo request (single HTTP round-trip): daily/weekly series, devices, pages, referrers, summaries.
 * Results are cached for one hour (HOUR_IN_SECONDS). Prefer {@see fs_matomo_get_statistics()} for the canonical bundle instead of arbitrary dimensions here.
 *
 * @return array{
 *   daily: array<int, array{date:string,unique:int,visits:int,pageviews:int}>,
 *   weekly: array<int, array{date:string,unique:int,visits:int,pageviews:int}>,
 *   devices: array{desktop:int,mobile:int,tablet:int},
 *   pages: array<int, array{label:string,url:string,hits:int}>,
 *   referrers: array<int, array{label:string,url:string,hits:int}>,
 *   visits_summary_90d: array{avg_time_on_site:int,nb_visits:int,nb_actions:int,nb_uniq_visitors:int,bounce_count:int},
 *   visit_frequency_90d: array{nb_visits_new:int,nb_visits_returning:int,returning_visits_pct:int|null,nb_actions_per_visit_new:float|null,nb_actions_per_visit_returning:float|null},
 *   alltime_summary: array{nb_visits:int,since_ts:int|null}
 * }
 */
function fs_dashboard_get_matomo_daily_and_weekly(int $days = 7, int $weeks = 8): array
{
    $days = max(1, min(365, $days));
    $weeks = max(1, min(104, $weeks));
    $settings = fs_dashboard_matomo_settings();
    if ($settings === null || !function_exists('wp_remote_get')) {
        return [
            'daily' => [],
            'weekly' => [],
            'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0],
            'pages' => [],
            'referrers' => [],
            'visits_summary_90d' => fs_dashboard_default_visits_summary_90d(),
            'visit_frequency_90d' => fs_dashboard_default_visit_frequency_90d(),
            'alltime_summary' => fs_dashboard_default_alltime_summary(),
        ];
    }

    $cache_key = fs_dashboard_matomo_bulk_cache_key($days, $weeks);

    $bypass_cache = is_admin()
        && current_user_can('manage_options')
        && isset($_GET['no_cache'])
        && (string) $_GET['no_cache'] !== '';
    if (!$bypass_cache) {
        $cached = get_transient($cache_key);
        if (
            is_array($cached)
            && isset($cached['daily'], $cached['weekly'], $cached['devices'], $cached['pages'], $cached['referrers'], $cached['visits_summary_90d'], $cached['visit_frequency_90d'], $cached['alltime_summary'])
            && is_array($cached['daily'])
            && is_array($cached['weekly'])
            && is_array($cached['devices'])
            && is_array($cached['pages'])
            && is_array($cached['referrers'])
            && is_array($cached['alltime_summary'])
        ) {
            if ($days === FS_MATOMO_STATS_CANONICAL_DAYS && $weeks === FS_MATOMO_STATS_CANONICAL_WEEKS) {
                fs_matomo_sync_dashboard_quick_stats_from_daily($cached['daily']);
            }

            return $cached;
        }
    }

    // IMPORTANT: each `urls[n]` value must be URL-encoded, otherwise the inner `&...`
    // will be treated as top-level query parameters and Matomo returns wrong data.
    $bulk_base = $settings['url'] . 'index.php';
    $bulk_query = [
        'module' => 'API',
        'method' => 'API.getBulkRequest',
        'format' => 'JSON',
        'token_auth' => $settings['token'],
        'urls[0]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=day&date=last%d&idSite=%d',
            $days,
            (int) $settings['site_id']
        )),
        // Weekly: last N weeks (includes current week).
        'urls[1]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=week&date=last%d&idSite=%d',
            $weeks,
            (int) $settings['site_id']
        )),
        // Devices: previous 90 days (range).
        // Desktop.
        'urls[2]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=range&date=previous90&idSite=%d&segment=%s',
            (int) $settings['site_id'],
            'deviceType==desktop'
        )),
        // Mobile: smartphone OR mobile (matches working Matomo segments; comma = OR).
        'urls[3]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=range&date=previous90&idSite=%d&segment=%s',
            (int) $settings['site_id'],
            'deviceType==smartphone'
        )),
        // Tablet (tablet + phablet).
        'urls[4]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=range&date=previous90&idSite=%d&segment=%s',
            (int) $settings['site_id'],
            'deviceType==tablet,deviceType==phablet'
        )),
        // Top pages: previous 90 days.
        'urls[5]' => rawurlencode(sprintf(
            'method=Actions.getPageUrls&period=range&date=previous90&idSite=%d&flat=1&filter_limit=30',
            (int) $settings['site_id']
        )),
        // Site-wide average visit duration (seconds), previous 90 days.
        'urls[6]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=range&date=previous90&idSite=%d',
            (int) $settings['site_id']
        )),
        // Referrers: websites + search engines (merged client-side into one top 10).
        'urls[7]' => rawurlencode(sprintf(
            'method=Referrers.getWebsites&period=range&date=previous90&idSite=%d&filter_limit=30',
            (int) $settings['site_id']
        )),
        'urls[8]' => rawurlencode(sprintf(
            'method=Referrers.getSearchEngines&period=range&date=previous90&idSite=%d&filter_limit=30',
            (int) $settings['site_id']
        )),
        'urls[9]' => rawurlencode(sprintf(
            'method=SitesManager.getSiteFromId&idSite=%d',
            (int) $settings['site_id']
        )),
        'urls[10]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=range&date=2000-01-01,today&idSite=%d',
            (int) $settings['site_id']
        )),
        // New vs returning visitors (VisitFrequency), previous 90 days.
        'urls[11]' => rawurlencode(sprintf(
            'method=VisitFrequency.get&period=range&date=previous90&idSite=%d',
            (int) $settings['site_id']
        )),
    ];
    $bulk_url = $bulk_base . '?' . http_build_query($bulk_query, '', '&', PHP_QUERY_RFC3986);

    $response = wp_remote_get($bulk_url, [
        'timeout' => 10,
        'headers' => ['Accept' => 'application/json'],
    ]);
    $empty = [
        'daily' => [],
        'weekly' => [],
        'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0],
        'pages' => [],
        'referrers' => [],
        'visits_summary_90d' => fs_dashboard_default_visits_summary_90d(),
        'visit_frequency_90d' => fs_dashboard_default_visit_frequency_90d(),
        'alltime_summary' => fs_dashboard_default_alltime_summary(),
    ];
    if (is_wp_error($response)) {
        fs_dashboard_set_last_matomo_error($response->get_error_message());
        set_transient($cache_key, $empty, 5 * MINUTE_IN_SECONDS);
        return $empty;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status_code === 401) {
        $msg = is_array($data) ? (string) ($data['message'] ?? '') : '';
        if ($msg === '') {
            $msg = 'HTTP 401 (Unauthorized)';
        }
        fs_dashboard_set_last_matomo_error(__('Matomo: invalid or missing auth token.', 'fromscratch') . ' ' . $msg);
        set_transient($cache_key, $empty, 5 * MINUTE_IN_SECONDS);
        return $empty;
    }
    if (is_array($data) && (($data['result'] ?? '') === 'error')) {
        fs_dashboard_set_last_matomo_error((string) ($data['message'] ?? __('Matomo API error', 'fromscratch')));
        set_transient($cache_key, $empty, 5 * MINUTE_IN_SECONDS);
        return $empty;
    }
    if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
        fs_dashboard_set_last_matomo_error(__('Unexpected Matomo response format.', 'fromscratch'));
        set_transient($cache_key, $empty, 5 * MINUTE_IN_SECONDS);
        return $empty;
    }

    $map_rows = static function ($payload, int $daysOrWeeks, string $mode): array {
        if (!is_array($payload)) {
            return [];
        }
        if (isset($payload['value']) && is_array($payload['value'])) {
            $payload = $payload['value'];
        }

        // For daily we prefill missing dates with 0 (site timezone, same as Matomo day labels).
        // For weekly we do NOT prefill: API keys are week ranges; `date=lastN` includes the current week.
        $series = [];
        if ($mode !== 'week') {
            foreach (fs_dashboard_matomo_site_calendar_dates($daysOrWeeks) as $key) {
                $series[$key] = ['unique' => 0, 'visits' => 0, 'pageviews' => 0];
            }
        }

        foreach ($payload as $date => $value) {
            // Matomo can return:
            // - associative keys like "YYYY-MM-DD" or "YYYY-MM-DD,YYYY-MM-DD"
            // - numeric keys with rows containing a "label" field
            $key = is_string($date) ? $date : '';
            if ($key === '' && is_array($value) && isset($value['label']) && is_string($value['label'])) {
                $key = $value['label'];
            }
            if ($key === '') {
                continue;
            }

            // Normalize range keys and other variants to a single start date.
            if (str_contains($key, ',')) {
                $parts = explode(',', $key, 2);
                $key = (string) ($parts[0] ?? $key);
            }
            if (strlen($key) >= 10) {
                $key = substr($key, 0, 10);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
                continue;
            }

            $unique = 0;
            $visits = 0;
            $pageviews = 0;
            if (is_array($value)) {
                $stats = $value;
                if (isset($value['value']) && is_array($value['value'])) {
                    $stats = $value['value'];
                }
                $unique = (int) ($stats['nb_uniq_visitors'] ?? 0);
                $visits = (int) ($stats['nb_visits'] ?? 0);
                $pageviews = (int) ($stats['nb_actions'] ?? 0);
            } elseif (is_numeric($value)) {
                $visits = (int) $value;
            }

            $series[$key] = [
                'unique' => max(0, $unique),
                'visits' => max(0, $visits),
                'pageviews' => max(0, $pageviews),
            ];
        }

        if (!empty($series)) {
            ksort($series);
        }
        $out = [];
        foreach ($series as $date => $row) {
            $out[] = [
                'date' => $date,
                'unique' => (int) ($row['unique'] ?? 0),
                'visits' => (int) ($row['visits'] ?? 0),
                'pageviews' => (int) ($row['pageviews'] ?? 0),
            ];
        }
        // Keep at most N most recent periods (e.g. if the API returns an extra row).
        if (count($out) > $daysOrWeeks) {
            $out = array_slice($out, -$daysOrWeeks);
        }
        return $out;
    };

    $out = [
        'daily' => $map_rows($data[0] ?? [], $days, 'day'),
        'weekly' => $map_rows($data[1] ?? [], $weeks, 'week'),
        'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0],
        'pages' => [],
        'referrers' => [],
        'visits_summary_90d' => fs_dashboard_default_visits_summary_90d(),
        'visit_frequency_90d' => fs_dashboard_default_visit_frequency_90d(),
        'alltime_summary' => fs_dashboard_default_alltime_summary(),
    ];

    $range_visits = static function ($payload): int {
        if (!is_array($payload)) {
            return 0;
        }
        if (isset($payload['value']) && is_array($payload['value'])) {
            $payload = $payload['value'];
        }
        if (isset($payload['result']) && $payload['result'] === 'error') {
            return 0;
        }
        if (isset($payload['nb_visits'])) {
            return max(0, (int) $payload['nb_visits']);
        }
        if (is_numeric($payload)) {
            return max(0, (int) $payload);
        }
        return 0;
    };

    // Devices: range previous 90 days
    $out['devices']['desktop'] = $range_visits($data[2] ?? []);
    $out['devices']['mobile'] = $range_visits($data[3] ?? []);
    $out['devices']['tablet'] = $range_visits($data[4] ?? []);

    // Top pages: range previous 90 days
    $pages_payload = $data[5] ?? [];
    if (is_array($pages_payload) && isset($pages_payload['value']) && is_array($pages_payload['value'])) {
        $pages_payload = $pages_payload['value'];
    }
    if (is_array($pages_payload)) {
        $pages = [];
        foreach ($pages_payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = isset($row['label']) ? (string) $row['label'] : '';
            $url = isset($row['url']) ? (string) $row['url'] : '';
            $hits = (int) ($row['nb_hits'] ?? 0);
            if ($label === '' && $url === '') {
                continue;
            }
            if (fs_dashboard_matomo_is_excluded_top_page_row($url, $label)) {
                continue;
            }
            $pages[] = [
                'label' => $label,
                'url' => $url,
                'hits' => max(0, $hits),
            ];
        }
        $out['pages'] = array_slice($pages, 0, 10);
    }

    $website_refs = fs_dashboard_matomo_parse_referrer_table_rows($data[7] ?? []);
    $engine_refs = fs_dashboard_matomo_parse_referrer_table_rows($data[8] ?? []);
    $out['referrers'] = fs_dashboard_matomo_merge_top_referrers($website_refs, $engine_refs, 10);

    $out['visits_summary_90d'] = fs_dashboard_matomo_parse_visits_summary_90d($data[6] ?? []);
    $out['visit_frequency_90d'] = fs_dashboard_matomo_parse_visit_frequency_90d($data[11] ?? []);

    $alltime_stats = fs_dashboard_matomo_parse_visits_summary_90d($data[10] ?? []);
    $out['alltime_summary'] = [
        'nb_visits' => (int) ($alltime_stats['nb_visits'] ?? 0),
        'since_ts' => fs_dashboard_matomo_parse_site_first_recording_ts($data[9] ?? []),
    ];

    fs_dashboard_set_last_matomo_error('');
    set_transient($cache_key, $out, HOUR_IN_SECONDS);
    if ($days === FS_MATOMO_STATS_CANONICAL_DAYS && $weeks === FS_MATOMO_STATS_CANONICAL_WEEKS) {
        fs_matomo_sync_dashboard_quick_stats_from_daily($out['daily'] ?? []);
    }

    return $out;
}

/**
 * Format Monday–Sunday week range for labels (e.g. "2. – 9. März 2026").
 */
function fs_dashboard_format_week_date_range(DateTimeImmutable $monday): string
{
    $monday = $monday->setTimezone(wp_timezone());
    $sunday = $monday->modify('+6 days');
    $same_month = $monday->format('Y-m') === $sunday->format('Y-m');

    if ($same_month) {
        return sprintf(
            /* translators: 1: start day with period, 2: end day, month, and year */
            __('%1$s – %2$s', 'fromscratch'),
            wp_date('j.', $monday->getTimestamp()),
            wp_date('j. F Y', $sunday->getTimestamp())
        );
    }

    if ($monday->format('Y') === $sunday->format('Y')) {
        return sprintf(
            /* translators: 1: start date, 2: end date */
            __('%1$s – %2$s', 'fromscratch'),
            wp_date('j. F', $monday->getTimestamp()),
            wp_date('j. F Y', $sunday->getTimestamp())
        );
    }

    return sprintf(
        /* translators: 1: start date, 2: end date */
        __('%1$s – %2$s', 'fromscratch'),
        wp_date((string) get_option('date_format'), $monday->getTimestamp()),
        wp_date((string) get_option('date_format'), $sunday->getTimestamp())
    );
}

/**
 * Whether a Y-m-d row key is “today” in the site timezone (analytics tables / charts).
 */
function fs_dashboard_analytics_row_is_today(string $date_ymd): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) {
        return false;
    }
    $tz = wp_timezone();
    $row = DateTimeImmutable::createFromFormat('Y-m-d', $date_ymd, $tz);
    if ($row === false) {
        return false;
    }
    $row = $row->setTime(12, 0);
    $today = new DateTimeImmutable('now', $tz);

    return $row->format('Y-m-d') === $today->format('Y-m-d');
}

/**
 * Whether a Y-m-d row is calendar yesterday in the site timezone.
 */
function fs_dashboard_analytics_row_is_yesterday(string $date_ymd): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) {
        return false;
    }
    $tz = wp_timezone();
    $row = DateTimeImmutable::createFromFormat('Y-m-d', $date_ymd, $tz);
    if ($row === false) {
        return false;
    }
    $row = $row->setTime(12, 0);
    $yesterday = (new DateTimeImmutable('now', $tz))->modify('-1 day');

    return $row->format('Y-m-d') === $yesterday->format('Y-m-d');
}

/**
 * Whether a Y-m-d row belongs to the current ISO-style week (Monday week start) in the site timezone.
 */
function fs_dashboard_analytics_row_is_current_week(string $date_ymd): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) {
        return false;
    }
    $tz = wp_timezone();
    $row = DateTimeImmutable::createFromFormat('Y-m-d', $date_ymd, $tz);
    if ($row === false) {
        return false;
    }
    $row = $row->setTime(12, 0);
    $row_monday = $row->modify('monday this week');
    $now_monday = (new DateTimeImmutable('now', $tz))->modify('monday this week');

    return $row_monday->format('Y-m-d') === $now_monday->format('Y-m-d');
}

/**
 * Per-metric maxima across table rows (each series scales to its own column peak).
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array{unique:int,visits:int,pageviews:int}
 */
function fs_dashboard_stats_metric_maxima(array $rows): array
{
    $maxU = 0;
    $maxV = 0;
    $maxP = 0;
    foreach ($rows as $r) {
        $maxU = max($maxU, (int) ($r['unique'] ?? 0));
        $maxV = max($maxV, (int) ($r['visits'] ?? 0));
        $maxP = max($maxP, (int) ($r['pageviews'] ?? 0));
    }

    return [
        'unique' => $maxU,
        'visits' => $maxV,
        'pageviews' => $maxP,
    ];
}

/**
 * Bar widths (0–100): each value relative to that metric’s max in this table (not row-vs-row across metrics).
 *
 * @param array<string, mixed> $row
 * @param array{unique:int,visits:int,pageviews:int} $maxima
 * @return array{unique:int,visits:int,pageviews:int}
 */
function fs_dashboard_stats_metric_bar_widths(array $row, array $maxima): array
{
    $u = (int) ($row['unique'] ?? 0);
    $v = (int) ($row['visits'] ?? 0);
    $p = (int) ($row['pageviews'] ?? 0);
    $pct = static function (int $val, int $max): int {
        if ($max <= 0) {
            return 0;
        }

        return (int) min(100, max(0, (int) round(($val / $max) * 100)));
    };

    return [
        'unique' => $pct($u, $maxima['unique']),
        'visits' => $pct($v, $maxima['visits']),
        'pageviews' => $pct($p, $maxima['pageviews']),
    ];
}

/**
 * Output three metric cells (unique, visits, page views) with counts and inline bars (per-column scale).
 *
 * @param array<string, mixed> $row
 * @param array{unique:int,visits:int,pageviews:int} $maxima
 */
function fs_dashboard_render_stats_metric_cells(array $row, array $maxima): void
{
    $w = fs_dashboard_stats_metric_bar_widths($row, $maxima);
    $cells = [
        ['key' => 'unique', 'class' => 'unique'],
        ['key' => 'visits', 'class' => 'visits'],
        ['key' => 'pageviews', 'class' => 'pageviews'],
    ];
    foreach ($cells as $index => $cell) {
        $k = $cell['key'];
        $val = (int) ($row[$k] ?? 0);
        $width = (int) ($w[$k] ?? 0);
        $cls = $cell['class'];
    ?>
        <td class="fs-stats-metric fs-stats-metric--<?= esc_attr($cls) ?>">
            <span class="fs-stats-metric__value"><?= esc_html(number_format_i18n($val)) ?></span>
            <span class="fs-stats-metric__bar" aria-hidden="true">
                <span class="fs-stats-metric__track">
                    <span
                        class="fs-stats-metric__fill fs-stats-metric__fill--<?= esc_attr($cls) ?>"
                        style="width: <?= $width ?>%; background-color: <?= esc_attr(fs_dashboard_get_analytics_settings()['colors'][$index]['fill']) ?>;"></span>
                </span>
            </span>
        </td>
    <?php
    }
}

/**
 * Second line of daily chart x-axis labels: day with period, abbreviated month, year (locale-aware).
 */
function fs_dashboard_analytics_chart_date_label(int $timestamp): string
{
    return wp_date('j. M Y', $timestamp);
}

/**
 * Weekly chart x-axis second line only: week start (Monday), no range — day with period, abbreviated month, year.
 */
function fs_dashboard_analytics_week_chart_axis_date_line(int $monday_timestamp): string
{
    return wp_date('j. M Y', $monday_timestamp);
}

/**
 * Daily chart x-axis: two lines — “Today” / “Yesterday” / weekday, then date (site timezone).
 *
 * @return array{0:string,1:string}|string
 */
function fs_dashboard_analytics_daily_axis_label(array $r)
{
    $date = (string) ($r['date'] ?? '');
    $ts = $date !== '' ? strtotime($date . ' 00:00:00') : false;
    if (!$ts) {
        return $date;
    }
    $ts = (int) $ts;
    $date_line = fs_dashboard_analytics_chart_date_label($ts);
    if (fs_dashboard_analytics_row_is_today($date)) {
        return [__('Today', 'fromscratch'), $date_line];
    }
    if (fs_dashboard_analytics_row_is_yesterday($date)) {
        return [__('Yesterday', 'fromscratch'), $date_line];
    }

    return [wp_date('l', $ts), $date_line];
}

/**
 * Weekly chart x-axis: “This week” or “Week N”, then Monday as d. M Y (never a range on the chart).
 *
 * @return array{0:string,1:string}|string
 */
function fs_dashboard_analytics_weekly_axis_label(array $r)
{
    $date = (string) ($r['date'] ?? '');
    $ts = $date !== '' ? strtotime($date . ' 00:00:00') : false;
    if (!$ts) {
        return $date;
    }
    $monday = (new DateTimeImmutable('@' . (int) $ts))->setTimezone(wp_timezone())->modify('monday this week');
    $week_no = (int) $monday->format('W');
    $date_line = fs_dashboard_analytics_week_chart_axis_date_line($monday->getTimestamp());
    if (fs_dashboard_analytics_row_is_current_week($date)) {
        return [
            __('This week', 'fromscratch'),
            $date_line,
        ];
    }

    return [
        sprintf(__('Week %d', 'fromscratch'), $week_no),
        $date_line,
    ];
}

/**
 * Reusable Chart.js line chart config.
 *
 * @param array<int, string|array<int, string>> $labels
 * @param array<int, array<string, mixed>> $datasets
 * @return array<string, mixed>
 */
function fs_dashboard_line_chart_config(array $labels, array $datasets): array
{
    return [
        'type' => 'line',
        'data' => [
            'labels' => $labels,
            'datasets' => $datasets,
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'boxPadding' => 4,
                    'padding' => 8
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'color' => '#64748b',
                    ],
                    'grid' => [
                        'color' => '#8888884d',
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'color' => '#64748b',
                    ],
                    'grid' => [
                        'color' => '#8888884d',
                    ],
                ],
            ],
        ],
    ];
}

function fs_dashboard_stats_page_slug(): string
{
    return 'fs-analytics';
}

function fs_dashboard_statistics_url(): string
{
    return admin_url('index.php?page=' . fs_dashboard_stats_page_slug());
}

/**
 * Analytics URL with cache bypass for administrators (skips Matomo transients).
 */
function fs_dashboard_statistics_reload_url(): string
{
    return add_query_arg('no_cache', '1', fs_dashboard_statistics_url());
}

function fs_render_dashboard_statistics_page(): void
{
    if (!fs_dashboard_can_access_statistics()) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
    }
    // Loads immediately on cache miss / ?no_cache=1 (blocking); same data as dashboard after refresh.
    $series = fs_matomo_get_statistics();
    $matomo_rows = array_slice($series['daily'] ?? [], -8);

    $rows = $matomo_rows;
    $labels = array_map('fs_dashboard_analytics_daily_axis_label', $rows);
    $unique = array_map(static fn($r) => (int) ($r['unique'] ?? 0), $rows);
    $visits = array_map(static fn($r) => (int) ($r['visits'] ?? 0), $rows);
    $pageviews = array_map(static fn($r) => (int) ($r['pageviews'] ?? 0), $rows);
    $datasets = [
        [
            'label' => __('Unique visitors', 'fromscratch'),
            'data' => $unique,
            'borderWidth' => fs_dashboard_get_analytics_settings()['lineChart']['borderWidth'],
            'tension' => fs_dashboard_get_analytics_settings()['lineChart']['tension'],
            'pointRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointRadius'],
            'pointHoverRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointHoverRadius'],
            'fill' => true,
            'backgroundColor' => fs_dashboard_get_analytics_settings()['colors'][0]['transparent'],
            'borderColor' => fs_dashboard_get_analytics_settings()['colors'][0]['fill'],
            'fillColor' => fs_dashboard_get_analytics_settings()['colors'][0]['transparent'],
            'pointBackgroundColor' => fs_dashboard_get_analytics_settings()['colors'][0]['fill'],
        ],
        [
            'label' => __('Visits', 'fromscratch'),
            'data' => $visits,
            'borderWidth' => fs_dashboard_get_analytics_settings()['lineChart']['borderWidth'],
            'tension' => fs_dashboard_get_analytics_settings()['lineChart']['tension'],
            'pointRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointRadius'],
            'pointHoverRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointHoverRadius'],
            'fill' => true,
            'backgroundColor' => fs_dashboard_get_analytics_settings()['colors'][1]['transparent'],
            'borderColor' => fs_dashboard_get_analytics_settings()['colors'][1]['fill'],
            'fillColor' => fs_dashboard_get_analytics_settings()['colors'][1]['transparent'],
            'pointBackgroundColor' => fs_dashboard_get_analytics_settings()['colors'][1]['fill'],
        ],
        [
            'label' => __('Page views', 'fromscratch'),
            'data' => $pageviews,
            'borderWidth' => fs_dashboard_get_analytics_settings()['lineChart']['borderWidth'],
            'tension' => fs_dashboard_get_analytics_settings()['lineChart']['tension'],
            'pointRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointRadius'],
            'pointHoverRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointHoverRadius'],
            'fill' => true,
            'backgroundColor' => fs_dashboard_get_analytics_settings()['colors'][2]['transparent'],
            'borderColor' => fs_dashboard_get_analytics_settings()['colors'][2]['fill'],
            'fillColor' => fs_dashboard_get_analytics_settings()['colors'][2]['transparent'],
            'pointBackgroundColor' => fs_dashboard_get_analytics_settings()['colors'][2]['fill'],
        ],
    ];
    $line_chart_config = fs_dashboard_line_chart_config($labels, $datasets);

    $week_rows = array_slice($series['weekly'] ?? [], -8);
    $week_chart_config = [];
    if (!empty($week_rows)) {
        $week_labels = array_map('fs_dashboard_analytics_weekly_axis_label', $week_rows);

        $week_unique = array_map(static fn($r) => (int) ($r['unique'] ?? 0), $week_rows);
        $week_visits = array_map(static fn($r) => (int) ($r['visits'] ?? 0), $week_rows);
        $week_pageviews = array_map(static fn($r) => (int) ($r['pageviews'] ?? 0), $week_rows);
        $week_datasets = [
            [
                'label' => __('Unique visitors', 'fromscratch'),
                'data' => $week_unique,
                'borderWidth' => fs_dashboard_get_analytics_settings()['lineChart']['borderWidth'],
                'tension' => fs_dashboard_get_analytics_settings()['lineChart']['tension'],
                'pointRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointRadius'],
                'pointHoverRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointHoverRadius'],
                'fill' => true,
                'backgroundColor' => fs_dashboard_get_analytics_settings()['colors'][0]['transparent'],
                'borderColor' => fs_dashboard_get_analytics_settings()['colors'][0]['fill'],
                'pointBackgroundColor' => fs_dashboard_get_analytics_settings()['colors'][0]['fill'],
            ],
            [
                'label' => __('Visits', 'fromscratch'),
                'data' => $week_visits,
                'borderWidth' => fs_dashboard_get_analytics_settings()['lineChart']['borderWidth'],
                'tension' => fs_dashboard_get_analytics_settings()['lineChart']['tension'],
                'pointRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointRadius'],
                'pointHoverRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointHoverRadius'],
                'fill' => true,
                'backgroundColor' => fs_dashboard_get_analytics_settings()['colors'][1]['transparent'],
                'borderColor' => fs_dashboard_get_analytics_settings()['colors'][1]['fill'],
                'pointBackgroundColor' => fs_dashboard_get_analytics_settings()['colors'][1]['fill'],
            ],
            [
                'label' => __('Page views', 'fromscratch'),
                'data' => $week_pageviews,
                'borderWidth' => fs_dashboard_get_analytics_settings()['lineChart']['borderWidth'],
                'tension' => fs_dashboard_get_analytics_settings()['lineChart']['tension'],
                'pointRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointRadius'],
                'pointHoverRadius' => fs_dashboard_get_analytics_settings()['lineChart']['pointHoverRadius'],
                'fill' => true,
                'backgroundColor' => fs_dashboard_get_analytics_settings()['colors'][2]['transparent'],
                'borderColor' => fs_dashboard_get_analytics_settings()['colors'][2]['fill'],
                'pointBackgroundColor' => fs_dashboard_get_analytics_settings()['colors'][2]['fill'],
            ],
        ];
        $week_chart_config = fs_dashboard_line_chart_config($week_labels, $week_datasets);
    }

    $devices = is_array($series['devices'] ?? null) ? $series['devices'] : ['desktop' => 0, 'mobile' => 0, 'tablet' => 0];
    $device_desktop = (int) ($devices['desktop'] ?? 0);
    $device_mobile = (int) ($devices['mobile'] ?? 0);
    $device_tablet = (int) ($devices['tablet'] ?? 0);
    $device_total = max(0, $device_desktop + $device_mobile + $device_tablet);
    $device_pct = static function (int $v) use ($device_total): int {
        if ($device_total <= 0) {
            return 0;
        }
        return (int) round(($v / $device_total) * 100);
    };
    $devices_chart_config = [
        'type' => 'bar',
        'data' => [
            'labels' => [
                [__('Desktop', 'fromscratch'), sprintf(__('%d%%', 'fromscratch'), $device_pct($device_desktop))],
                [__('Mobile', 'fromscratch'), sprintf(__('%d%%', 'fromscratch'), $device_pct($device_mobile))],
                [__('Tablet', 'fromscratch'), sprintf(__('%d%%', 'fromscratch'), $device_pct($device_tablet))],
            ],
            'datasets' => [
                [
                    'data' => [
                        $device_desktop,
                        $device_mobile,
                        $device_tablet,
                    ],
                    'backgroundColor' => [
                        fs_dashboard_get_analytics_settings()['colors'][2]['transparent'],
                        fs_dashboard_get_analytics_settings()['colors'][0]['transparent'],
                        fs_dashboard_get_analytics_settings()['colors'][1]['transparent'],
                    ],
                    'borderColor' => [
                        fs_dashboard_get_analytics_settings()['colors'][2]['fill'],
                        fs_dashboard_get_analytics_settings()['colors'][0]['fill'],
                        fs_dashboard_get_analytics_settings()['colors'][1]['fill'],
                    ],
                    'borderWidth' => fs_dashboard_get_analytics_settings()['barChart']['borderWidth'],
                    'borderRadius' => [
                        'topLeft' => fs_dashboard_get_analytics_settings()['barChart']['borderRadius'],
                        'topRight' => fs_dashboard_get_analytics_settings()['barChart']['borderRadius'],
                    ],
                ],
            ],
        ],
        'options' => [
            'maxBarThickness' => fs_dashboard_get_analytics_settings()['barChart']['maxBarThickness'],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'boxPadding' => 4,
                    'padding' => 8,
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => ['color' => '#64748b'],
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['color' => '#64748b'],
                    'grid' => ['color' => '#8888884d'],
                ],
            ],
        ],
    ];

    $top_pages = is_array($series['pages'] ?? null) ? $series['pages'] : [];
    $top_referrers = is_array($series['referrers'] ?? null) ? $series['referrers'] : [];
    $visits_summary_90d = is_array($series['visits_summary_90d'] ?? null)
        ? array_merge(fs_dashboard_default_visits_summary_90d(), $series['visits_summary_90d'])
        : fs_dashboard_default_visits_summary_90d();
    $visit_frequency_90d = is_array($series['visit_frequency_90d'] ?? null)
        ? array_merge(fs_dashboard_default_visit_frequency_90d(), $series['visit_frequency_90d'])
        : fs_dashboard_default_visit_frequency_90d();
    ?>
    <div class="wrap fs-analytics-page">
        <h1><?= esc_html__('Analytics', 'fromscratch') ?></h1>
        <?php
        $matomo_err = fs_dashboard_get_last_matomo_error();
        if ($matomo_err !== '') :
            ?>
            <div class="notice notice-error">
                <p><strong><?= esc_html__('Matomo error', 'fromscratch') ?></strong></p>
                <p><?= esc_html($matomo_err) ?></p>
            </div>
            <?php
        endif;
        $matomo_settings = fs_dashboard_matomo_settings();
        $matomo_login_url = $matomo_settings ? $matomo_settings['url'] : '';
        $alltime = is_array($series['alltime_summary'] ?? null)
            ? array_merge(fs_dashboard_default_alltime_summary(), $series['alltime_summary'])
            : fs_dashboard_default_alltime_summary();
        $alltime_visits = (int) ($alltime['nb_visits'] ?? 0);
        $since_ts = isset($alltime['since_ts']) && $alltime['since_ts'] !== null ? (int) $alltime['since_ts'] : 0;
        ?>
        <div class="notice inline fs-analytics-summary-notice">
            <div style="margin: 0; display: flex; flex-wrap: wrap; gap: 8px;">
                <div>
                    <strong><?= esc_html__('Total', 'fromscratch') ?>:</strong>
                    <?= esc_html(sprintf(__('%1$s visits', 'fromscratch'), number_format_i18n($alltime_visits))) ?>
                    <?php if ($matomo_login_url !== '') : ?>
                        · <a href="<?= esc_url($matomo_login_url) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html__('Open Matomo', 'fromscratch') ?></a>
                    <?php endif; ?>
                    <?php if ($since_ts > 0) : ?>
                        <div class="fs-analytics-summary-since">
                            <?= esc_html(sprintf(
                                /* translators: %s: first date visits are recorded from (localized) */
                                __('Since %s', 'fromscratch'),
                                wp_date((string) get_option('date_format'), $since_ts)
                            )) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (current_user_can('manage_options')) : ?>
                    <span style="margin-left: auto;"><a href="<?= esc_url(fs_dashboard_statistics_reload_url()) ?>"><?= esc_html__('Clear cache', 'fromscratch') ?></a></span>
                <?php endif; ?>
            </div>
        </div>

        <h2 style="margin-top: 32px; margin-bottom: 12px;"><?= esc_html__('Daily visits and page views (last 8 days)', 'fromscratch') ?></h2>

        <div class="fs-chart-container">
            <canvas
                id="fs-stats-chart"
                height="250"
                data-chart="line"
                data-chart-config="<?= esc_attr(wp_json_encode($line_chart_config)) ?>"></canvas>
        </div>
        <?php $daily_metric_maxima = fs_dashboard_stats_metric_maxima($rows); ?>
        <div class="fs-chart-container fs-chart-container--table">
            <table class="widefat striped fs-stats-table" style="margin: 0;">
                <thead>
                    <tr>
                        <th scope="col"><?= esc_html__('Date', 'fromscratch') ?></th>
                        <th scope="col" class="fs-stats-metric fs-stats-metric--unique"><?= esc_html__('Unique visitors', 'fromscratch') ?></th>
                        <th scope="col" class="fs-stats-metric fs-stats-metric--visits"><?= esc_html__('Visits', 'fromscratch') ?></th>
                        <th scope="col" class="fs-stats-metric fs-stats-metric--pageviews"><?= esc_html__('Page views', 'fromscratch') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($rows) as $r) :
                        $date = (string) ($r['date'] ?? '');
                        $ts = $date !== '' ? strtotime($date . ' 00:00:00') : false;
                        $label = $ts ? wp_date((string) get_option('date_format'), $ts) : $date;
                        $is_today = $date !== '' && fs_dashboard_analytics_row_is_today($date);
                    ?>
                        <tr>
                            <td>
                                <?= esc_html($label) ?>
                                <?php if ($is_today) : ?>
                                    <span class="fs-stats-period-current"><?= esc_html__('(Today)', 'fromscratch') ?></span>
                                <?php endif; ?>
                            </td>
                            <?php fs_dashboard_render_stats_metric_cells($r, $daily_metric_maxima); ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 style="margin-top: 32px; margin-bottom: 12px;"><?= esc_html__('Weekly visits and page views (last 8 weeks)', 'fromscratch') ?></h2>
        <?php if (!empty($week_chart_config)) : ?>
            <div class="fs-chart-container">
                <canvas
                    id="fs-stats-chart-weeks"
                    height="250"
                    data-chart="line"
                    data-chart-config="<?= esc_attr(wp_json_encode($week_chart_config)) ?>"></canvas>
            </div>
            <?php $weekly_metric_maxima = fs_dashboard_stats_metric_maxima($week_rows); ?>
            <div class="fs-chart-container fs-chart-container--table">
                <table class="widefat striped fs-stats-table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc_html__('Week', 'fromscratch') ?></th>
                            <th scope="col" class="fs-stats-metric fs-stats-metric--unique"><?= esc_html__('Unique visitors', 'fromscratch') ?></th>
                            <th scope="col" class="fs-stats-metric fs-stats-metric--visits"><?= esc_html__('Visits', 'fromscratch') ?></th>
                            <th scope="col" class="fs-stats-metric fs-stats-metric--pageviews"><?= esc_html__('Page views', 'fromscratch') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($week_rows) as $r) :
                            $date = (string) ($r['date'] ?? '');
                            $ts = $date !== '' ? strtotime($date . ' 00:00:00') : false;
                            $week_label = $date;
                            $week_range = '';
                            if ($ts) {
                                $monday = (new DateTimeImmutable('@' . $ts))->setTimezone(wp_timezone())->modify('monday this week');
                                $week_no = (int) $monday->format('W');
                                $week_label = sprintf(__('Week %d', 'fromscratch'), $week_no);
                                $week_range = fs_dashboard_format_week_date_range($monday);
                            }
                            $is_current_week = $date !== '' && fs_dashboard_analytics_row_is_current_week($date);
                        ?>
                            <tr>
                                <td>
                                    <?php if ($week_range !== '') : ?>
                                        <?= esc_html($week_label) ?>
                                        <?php if ($is_current_week) : ?>
                                            <span class="fs-stats-period-current"><?= esc_html__('(Current)', 'fromscratch') ?></span>
                                        <?php endif; ?>
                                        <br>
                                        <span class="fs-week-range"><?= esc_html($week_range) ?></span>
                                    <?php else : ?>
                                        <?= esc_html($week_label) ?>
                                        <?php if ($is_current_week) : ?>
                                            <span class="fs-stats-period-current"><?= esc_html__('(Current)', 'fromscratch') ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php fs_dashboard_render_stats_metric_cells($r, $weekly_metric_maxima); ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="fs-chart-wrapper-flex">
            <div class="fs-chart-container-flex">
                <h2 style="margin-top: 0; margin-bottom: 12px;"><?= esc_html__('Devices (last 90 days)', 'fromscratch') ?></h2>
                <div class="fs-chart-container">
                    <canvas
                        id="fs-stats-chart-devices"
                        height="236"
                        data-chart="bar"
                        data-chart-config="<?= esc_attr(wp_json_encode($devices_chart_config)) ?>"></canvas>
                </div>
            </div>
            <div class="fs-chart-container-flex">
                <h2 style="margin-top: 0; margin-bottom: 12px;"><?= esc_html__('Overview (last 90 days)', 'fromscratch') ?></h2>
                <?php fs_dashboard_render_visits_summary_90d_box($visits_summary_90d, $visit_frequency_90d); ?>
            </div>
        </div>

        <div class="fs-chart-wrapper-flex fs-chart-wrapper-flex--pages-referrers">
            <div class="fs-chart-container-flex">
                <h2 style="margin-top: 0; margin-bottom: 12px;"><?= esc_html__('Top 10 pages (last 90 days)', 'fromscratch') ?></h2>
                <div class="fs-chart-container fs-chart-container--table">
                    <?php if (!empty($top_pages)) : ?>
                        <?php fs_dashboard_render_top_pages_table($top_pages); ?>
                    <?php else : ?>
                        <p class="fs-top-pages-empty"><?= esc_html__('No data available.', 'fromscratch') ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="fs-chart-container-flex">
                <h2 style="margin-top: 0; margin-bottom: 12px;"><?= esc_html__('Top 10 referrers (last 90 days)', 'fromscratch') ?></h2>
                <div class="fs-chart-container fs-chart-container--table">
                    <?php if (!empty($top_referrers)) : ?>
                        <?php fs_dashboard_render_top_referrers_table($top_referrers); ?>
                    <?php else : ?>
                        <p class="fs-top-pages-empty"><?= esc_html__('No data available.', 'fromscratch') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
}
