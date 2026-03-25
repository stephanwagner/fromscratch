<?php

/**
 * Dashboard > Analytics page.
 */
add_action('admin_menu', function (): void {
    if (!current_user_can('edit_posts')) {
        return;
    }
    add_submenu_page(
        'index.php',
        __('Analytics', 'fromscratch'),
        __('Analytics', 'fromscratch'),
        'edit_posts',
        fs_dashboard_stats_page_slug(),
        'fs_render_dashboard_statistics_page'
    );
});


/**
 * Get Matomo tracking settings when available.
 *
 * @return array{url:string,site_id:int,token:string}|null
 */
function fs_dashboard_matomo_settings(): ?array
{
    if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('matomo')) {
        return null;
    }
    $url = trim((string) get_option('fromscratch_matomo_url', ''));
    $site_id = (int) get_option('fromscratch_matomo_site_id', 1);
    $token = trim((string) get_option('fromscratch_matomo_token_auth', ''));
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
 * Fetch daily Matomo visits for last N days.
 *
 * @return array<int, array{date:string,unique:int,visits:int,pageviews:int}>
 */
function fs_dashboard_get_matomo_daily_visits(int $days = 7): array
{
    $days = max(1, min(365, $days));
    $settings = fs_dashboard_matomo_settings();
    if ($settings === null || !function_exists('wp_remote_get')) {
        return [];
    }

    $cache_key = 'fromscratch_matomo_daily_' . md5(wp_json_encode([
        'url' => $settings['url'],
        'site_id' => (int) $settings['site_id'],
        'token' => $settings['token'],
        'days' => $days,
    ]));
    $bypass_cache = is_admin()
        && current_user_can('manage_options')
        && isset($_GET['fs_no_cache'])
        && (string) $_GET['fs_no_cache'] !== '';
    if (!$bypass_cache) {
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $key = gmdate('Y-m-d', time() - ($i * DAY_IN_SECONDS));
        $series[$key] = ['unique' => 0, 'visits' => 0, 'pageviews' => 0];
    }

    // Use bulk request to reliably get nb_uniq_visitors, nb_visits and nb_actions.
    $bulk_url = add_query_arg([
        'module' => 'API',
        'method' => 'API.getBulkRequest',
        'format' => 'JSON',
        'token_auth' => $settings['token'],
        'urls[0]' => sprintf(
            'method=VisitsSummary.get&period=day&date=last%d&idSite=%d',
            $days,
            (int) $settings['site_id']
        ),
    ], $settings['url'] . 'index.php');

    $response = wp_remote_get($bulk_url, [
        'timeout' => 10,
        'headers' => ['Accept' => 'application/json'],
    ]);
    if (is_wp_error($response)) {
        set_transient($cache_key, [], 5 * MINUTE_IN_SECONDS);
        return [];
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
        set_transient($cache_key, [], 5 * MINUTE_IN_SECONDS);
        return [];
    }

    foreach ($data[0] as $date => $value) {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }
        $unique = 0;
        $visits = 0;
        $pageviews = 0;
        if (is_array($value)) {
            $unique = (int) ($value['nb_uniq_visitors'] ?? 0);
            $visits = (int) ($value['nb_visits'] ?? 0);
            $pageviews = (int) ($value['nb_actions'] ?? 0);
        } elseif (is_numeric($value)) {
            $visits = (int) $value;
        }
        if (array_key_exists($date, $series)) {
            $series[$date]['unique'] = max(0, $unique);
            $series[$date]['visits'] = max(0, $visits);
            $series[$date]['pageviews'] = max(0, $pageviews);
        }
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

    set_transient($cache_key, $out, HOUR_IN_SECONDS);
    return $out;
}

/**
 * Reusable Chart.js line chart config.
 *
 * @param string[] $labels
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
    return 'fromscratch-analytics';
}

function fs_dashboard_statistics_url(): string
{
    return admin_url('index.php?page=' . fs_dashboard_stats_page_slug());
}

function fs_render_dashboard_statistics_page(): void
{
    if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
    }
    $matomo_rows = fs_dashboard_get_matomo_daily_visits(7);
    if (empty($matomo_rows)) {
?>
        <div class="wrap">
            <h1><?= esc_html__('Analytics', 'fromscratch') ?></h1>
            <div class="notice notice-warning">
                <p><strong><?= esc_html__('Matomo is not configured.', 'fromscratch') ?></strong></p>
                <p><?= esc_html__('Enable Matomo in Developer → Features and provide URL, Site ID and an Auth Token in Developer → System to show analytics.', 'fromscratch') ?></p>
            </div>
        </div>
    <?php
        return;
    }

    $rows = $matomo_rows;
    $labels = array_map(static function ($r) {
        $date = (string) ($r['date'] ?? '');
        $ts = $date !== '' ? strtotime($date . ' 00:00:00') : false;
        if (!$ts) {
            return $date;
        }
        return [
            wp_date('l', $ts),
            wp_date((string) get_option('date_format'), $ts),
        ];
    }, $rows);
    $unique = array_map(static fn($r) => (int) ($r['unique'] ?? 0), $rows);
    $visits = array_map(static fn($r) => (int) ($r['visits'] ?? 0), $rows);
    $pageviews = array_map(static fn($r) => (int) ($r['pageviews'] ?? 0), $rows);
    $datasets = [
        [
            'label' => __('Daily visits', 'fromscratch'),
            'data' => $visits,
            'borderColor' => '#2e8ae5',
            'backgroundColor' => '#2e8ae5',
            'borderWidth' => 3,
            'tension' => 0.3,
            'fill' => false,
            'pointRadius' => 3.5,
            'pointHoverRadius' => 4.5,
            'pointBackgroundColor' => '#2e8ae5',
        ],
        [
            'label' => __('Unique visitors', 'fromscratch'),
            'data' => $unique,
            'borderColor' => '#99ccff',
            'backgroundColor' => '#99ccff',
            'borderWidth' => 3,
            'tension' => 0.3,
            'fill' => false,
            'pointRadius' => 3.5,
            'pointHoverRadius' => 4.5,
            'pointBackgroundColor' => '#99ccff',
        ],
        [
            'label' => __('Page views', 'fromscratch'),
            'data' => $pageviews,
            'borderColor' => '#ff6673',
            'backgroundColor' => '#ff6673',
            'borderWidth' => 3,
            'tension' => 0.3,
            'fill' => false,
            'pointRadius' => 3.5,
            'pointHoverRadius' => 4.5,
            'pointBackgroundColor' => '#ff6673',
        ]
    ];
    $line_chart_config = fs_dashboard_line_chart_config($labels, $datasets);
    ?>
    <div class="wrap">
        <h1><?= esc_html__('Analytics', 'fromscratch') ?></h1>
        <p class="description"><?= esc_html__('Daily visits and page views for the last 7 days.', 'fromscratch') ?></p>
        <div class="fs-chart-container">
            <canvas
                id="fs-stats-chart"
                height="250"
                data-chart="line"
                data-chart-config="<?= esc_attr(wp_json_encode($line_chart_config)) ?>"></canvas>
        </div>
    </div>
<?php
}
