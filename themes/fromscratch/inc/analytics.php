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
 * Fetch Analytics Matomo data in one call.
 *
 * @return array{
 *   daily: array<int, array{date:string,unique:int,visits:int,pageviews:int}>,
 *   weekly: array<int, array{date:string,unique:int,visits:int,pageviews:int}>,
 *   devices: array{desktop:int,mobile:int,tablet:int},
 *   pages: array<int, array{label:string,url:string,hits:int}>
 * }
 */
function fs_dashboard_get_matomo_daily_and_weekly(int $days = 7, int $weeks = 8): array
{
    $days = max(1, min(365, $days));
    $weeks = max(1, min(104, $weeks));
    $settings = fs_dashboard_matomo_settings();
    if ($settings === null || !function_exists('wp_remote_get')) {
        return ['daily' => [], 'weekly' => [], 'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0], 'pages' => []];
    }

    $cache_key = 'fromscratch_matomo_day_week_' . md5(wp_json_encode([
        'url' => $settings['url'],
        'site_id' => (int) $settings['site_id'],
        'token' => $settings['token'],
        'days' => $days,
        'weeks' => $weeks,
    ]));

    $bypass_cache = is_admin()
        && current_user_can('manage_options')
        && isset($_GET['fs_no_cache'])
        && (string) $_GET['fs_no_cache'] !== '';
    if (!$bypass_cache) {
        $cached = get_transient($cache_key);
        if (
            is_array($cached)
            && isset($cached['daily'], $cached['weekly'], $cached['devices'], $cached['pages'])
            && is_array($cached['daily'])
            && is_array($cached['weekly'])
            && is_array($cached['devices'])
            && is_array($cached['pages'])
        ) {
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
        // In your other implementation you use previous9 for 8 weeks (skip current week).
        'urls[1]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=week&date=previous%d&idSite=%d',
            $weeks + 1,
            (int) $settings['site_id']
        )),
        // Devices: previous 90 days (range).
        // Desktop.
        'urls[2]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=range&date=previous90&idSite=%d&segment=%s',
            (int) $settings['site_id'],
            'deviceType==desktop'
        )),
        // Handy (mobile + smartphone).
        'urls[3]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=range&date=previous90&idSite=%d&segment=%s',
            (int) $settings['site_id'],
            'deviceType==smartphone,deviceType==mobile'
        )),
        // Tablet (tablet + phablet).
        'urls[4]' => rawurlencode(sprintf(
            'method=VisitsSummary.get&period=range&date=previous90&idSite=%d&segment=%s',
            (int) $settings['site_id'],
            'deviceType==tablet,deviceType==phablet'
        )),
        // Top pages: previous 90 days.
        'urls[5]' => rawurlencode(sprintf(
            'method=Actions.getPageUrls&period=range&date=previous90&idSite=%d&flat=1&filter_limit=10',
            (int) $settings['site_id']
        )),
    ];
    $bulk_url = $bulk_base . '?' . http_build_query($bulk_query, '', '&', PHP_QUERY_RFC3986);

    $response = wp_remote_get($bulk_url, [
        'timeout' => 10,
        'headers' => ['Accept' => 'application/json'],
    ]);
    if (is_wp_error($response)) {
        $empty = ['daily' => [], 'weekly' => [], 'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0], 'pages' => []];
        set_transient($cache_key, $empty, 5 * MINUTE_IN_SECONDS);
        return $empty;
    }

    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
        $empty = ['daily' => [], 'weekly' => [], 'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0], 'pages' => []];
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

        // For daily we prefill missing dates with 0.
        // For weekly we do NOT prefill, because Matomo `previousX` excludes the current week
        // and prefilling would introduce a zero-week and then slice away real data.
        $series = [];
        if ($mode !== 'week') {
            for ($i = $daysOrWeeks - 1; $i >= 0; $i--) {
                $key = gmdate('Y-m-d', time() - ($i * DAY_IN_SECONDS));
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
        // Trim to last N points (weekly request contains 1 extra)
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
            $pages[] = [
                'label' => $label,
                'url' => $url,
                'hits' => max(0, $hits),
            ];
        }
        $out['pages'] = array_slice($pages, 0, 10);
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
    $series = fs_dashboard_get_matomo_daily_and_weekly(8, 8);
    $matomo_rows = $series['daily'] ?? [];
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

    $week_rows = $series['weekly'] ?? [];
    $week_chart_config = [];
    if (!empty($week_rows)) {
        $week_labels = array_map(static function ($r) {
            $date = (string) ($r['date'] ?? '');
            $ts = $date !== '' ? strtotime($date . ' 00:00:00') : false;
            if (!$ts) {
                return $date;
            }
            $monday = (new DateTimeImmutable('@' . $ts))->setTimezone(wp_timezone())->modify('monday this week');
            $week_no = (int) $monday->format('W');
            return [
                sprintf(__('Week %d', 'fromscratch'), $week_no),
                wp_date((string) get_option('date_format'), $monday->getTimestamp()),
            ];
        }, $week_rows);

        $week_unique = array_map(static fn($r) => (int) ($r['unique'] ?? 0), $week_rows);
        $week_visits = array_map(static fn($r) => (int) ($r['visits'] ?? 0), $week_rows);
        $week_pageviews = array_map(static fn($r) => (int) ($r['pageviews'] ?? 0), $week_rows);
        $week_datasets = [
            [
                'label' => __('Daily visits', 'fromscratch'),
                'data' => $week_visits,
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
                'data' => $week_unique,
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
                'data' => $week_pageviews,
                'borderColor' => '#ff6673',
                'backgroundColor' => '#ff6673',
                'borderWidth' => 3,
                'tension' => 0.3,
                'fill' => false,
                'pointRadius' => 3.5,
                'pointHoverRadius' => 4.5,
                'pointBackgroundColor' => '#ff6673',
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
                [__('Handy', 'fromscratch'), sprintf(__('%d%%', 'fromscratch'), $device_pct($device_mobile))],
                [__('Tablet', 'fromscratch'), sprintf(__('%d%%', 'fromscratch'), $device_pct($device_tablet))],
            ],
            'datasets' => [
                [
                    'data' => [
                        $device_desktop,
                        $device_mobile,
                        $device_tablet,
                    ],
                    'backgroundColor' => ['#ff6673', '#2e8ae5', '#99ccff'],
                    'borderRadius' => 6,
                ],
            ],
        ],
        'options' => [
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
    ?>
    <div class="wrap">
        <h1><?= esc_html__('Analytics', 'fromscratch') ?></h1>
        <?php
        $matomo_settings = fs_dashboard_matomo_settings();
        $matomo_login_url = $matomo_settings ? $matomo_settings['url'] : '';
        $total_visits = array_sum(array_map(static fn($r) => (int) ($r['visits'] ?? 0), $rows));
        $total_pageviews = array_sum(array_map(static fn($r) => (int) ($r['pageviews'] ?? 0), $rows));
        ?>
        <div class="notice inline" style="margin: 12px 0 14px; padding: 10px 12px;">
            <p style="margin: 0;">
                <strong><?= esc_html__('Total', 'fromscratch') ?>:</strong>
                <?= esc_html(sprintf(__('%1$s visits', 'fromscratch'), number_format_i18n((int) $total_visits))) ?>
                · <?= esc_html(sprintf(__('%1$s page views', 'fromscratch'), number_format_i18n((int) $total_pageviews))) ?>
                <?php if ($matomo_login_url !== '') : ?>
                    · <a href="<?= esc_url($matomo_login_url) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html__('Open Matomo', 'fromscratch') ?></a>
                <?php endif; ?>
            </p>
        </div>

        <h2 style="margin-top: 32px; margin-bottom: 12px;"><?= esc_html__('Daily visits and page views (last 8 days)', 'fromscratch') ?></h2>

        <div class="fs-chart-container">
            <canvas
                id="fs-stats-chart"
                height="250"
                data-chart="line"
                data-chart-config="<?= esc_attr(wp_json_encode($line_chart_config)) ?>"></canvas>
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
        <?php endif; ?>

        <div class="fs-chart-wrapper-flex">
            <div class="fs-chart-container-flex">
                <h2 style="margin-top: 0; margin-bottom: 12px;"><?= esc_html__('Devices (last 90 days)', 'fromscratch') ?></h2>
                <div class="fs-chart-container -small" style="min-height: 334px;">
                    <canvas
                        id="fs-stats-chart-devices"
                        height="300"
                        data-chart="bar"
                        data-chart-config="<?= esc_attr(wp_json_encode($devices_chart_config)) ?>"></canvas>
                </div>
            </div>

            <div class="fs-chart-container-flex">
                <h2 style="margin-top: 0; margin-bottom: 12px;"><?= esc_html__('Top pages (last 90 days)', 'fromscratch') ?></h2>
                <div class="fs-chart-container -small" style="min-height: 334px;">
                    <?php if (!empty($top_pages)) : ?>
                        <ol style="margin: 0; padding-left: 18px;">
                            <?php foreach ($top_pages as $row) :
                                $label = (string) ($row['label'] ?? '');
                                $url = (string) ($row['url'] ?? '');
                                $hits = (int) ($row['hits'] ?? 0);
                                $text = $label !== '' ? $label : $url;
                            ?>
                                <li style="margin: 0 0 8px 0;">
                                    <?php if ($url !== '') : ?>
                                        <a href="<?= esc_url($url) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html($text) ?></a>
                                    <?php else : ?>
                                        <?= esc_html($text) ?>
                                    <?php endif; ?>
                                    <span style="color:#646970;"> — <?= esc_html(number_format_i18n($hits)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else : ?>
                        <p style="margin: 0; color:#646970;"><?= esc_html__('No data available.', 'fromscratch') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
}
