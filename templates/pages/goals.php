<?php
/**
 * Goals Management Page Shortcode (Redesigned)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('myavana_goals_page_shortcode')) {
function myavana_goals_page_shortcode($atts = [], $content = null) {
    if (!is_user_logged_in()) {
        return '<div class="hair-journey-container"><div class="calendar-empty-hjn"><h2>Please sign in to manage your goals</h2></div></div>';
    }

    $goals_css_path = MYAVANA_NEXT_PATH . 'assets/css/goals-page-redesign.css';
    $goals_js_path = MYAVANA_NEXT_PATH . 'assets/js/goals-page-redesign.js';
    $asset_version = defined('WP_DEBUG') && WP_DEBUG
        ? (string) time()
        : (string) max(
            file_exists($goals_css_path) ? (int) filemtime($goals_css_path) : 0,
            file_exists($goals_js_path) ? (int) filemtime($goals_js_path) : 0,
            1
        );
    wp_enqueue_style(
        'myavana-goal-routine-pages',
        MYAVANA_NEXT_URL . 'assets/css/goal-routine-pages.css',
        [],
        $asset_version
    );
    wp_enqueue_style(
        'myavana-goals-page-redesign',
        MYAVANA_NEXT_URL . 'assets/css/goals-page-redesign.css',
        ['myavana-goal-routine-pages'],
        $asset_version
    );
    wp_enqueue_script(
        'myavana-lucide',
        'https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js',
        [],
        '0.469.0',
        true
    );
    wp_enqueue_script(
        'myavana-goals-page-redesign',
        MYAVANA_NEXT_URL . 'assets/js/goals-page-redesign.js',
        ['myavana-lucide', 'jquery'],
        $asset_version,
        true
    );

    $user_id = get_current_user_id();
    $today = current_time('Y-m-d');
    $today_ts = strtotime($today . ' 00:00:00');

    $goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
    if (!is_array($goals)) {
        $goals = [];
    }

    $routines = get_user_meta($user_id, 'myavana_current_routine', true);
    if (!is_array($routines)) {
        $routines = [];
    }

    $routine_completion_map = get_user_meta($user_id, 'myavana_routine_completions', true);
    if (!is_array($routine_completion_map)) {
        $routine_completion_map = [];
    }

    $icon = static function ($name, $class = '') {
        $class_attr = trim('myavana-gv2-lucide ' . $class);
        return '<i data-lucide="' . esc_attr($name) . '" class="' . esc_attr($class_attr) . '"></i>';
    };

    $parse_list = static function ($value) {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/\r\n|\r|\n|,/', (string) $value);
        }
        $items = array_map(static function ($item) {
            return trim((string) $item);
        }, $items);
        return array_values(array_filter($items, static function ($item) {
            return $item !== '';
        }));
    };

    $safe_date = static function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : '';
    };

    $category_slug = static function ($category) {
        $slug = strtolower(trim((string) $category));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        return trim((string) $slug, '_');
    };

    $category_palette = static function ($category) {
        $cat = strtolower(trim((string) $category));
        if (strpos($cat, 'length') !== false || strpos($cat, 'retention') !== false) {
            return 'coral';
        }
        if (strpos($cat, 'moisture') !== false || strpos($cat, 'texture') !== false) {
            return 'bb';
        }
        if (strpos($cat, 'scalp') !== false || strpos($cat, 'health') !== false || strpos($cat, 'strength') !== false) {
            return 'green';
        }
        return 'amber';
    };

    $priority_key = static function ($priority) {
        $p = strtolower(trim((string) $priority));
        if ($p === 'high') {
            return 'high';
        }
        if ($p === 'low') {
            return 'low';
        }
        return 'med';
    };

    $format_value = static function ($value, $unit) {
        if ($value === '' || $value === null) {
            return '-';
        }
        $num = (float) $value;
        $formatted = (abs($num - round($num)) < 0.01) ? number_format_i18n((float) round($num), 0) : number_format_i18n($num, 1);
        $unit = trim((string) $unit);
        if ($unit !== '') {
            if ($unit === '%') {
                return $formatted . '%';
            }
            return $formatted . ' ' . $unit;
        }
        return $formatted;
    };

    $routine_dates = [];
    foreach ($routine_completion_map as $date_key => $ids) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_key) || !is_array($ids)) {
            continue;
        }
        foreach ($ids as $rid) {
            $rid = (int) $rid;
            if (!isset($routine_dates[$rid])) {
                $routine_dates[$rid] = [];
            }
            $routine_dates[$rid][] = (string) $date_key;
        }
    }

    foreach ($routine_dates as $rid => $dates) {
        $dates = array_values(array_unique($dates));
        rsort($dates);
        $routine_dates[$rid] = $dates;
    }

    $routine_streak = static function ($dates, $today_str) {
        if (!is_array($dates) || empty($dates)) {
            return 0;
        }
        $date_set = array_fill_keys($dates, true);
        $streak = 0;
        $cursor = strtotime($today_str . ' 00:00:00');
        while ($cursor) {
            $key = gmdate('Y-m-d', $cursor);
            if (!isset($date_set[$key])) {
                break;
            }
            $streak++;
            $cursor -= DAY_IN_SECONDS;
        }
        return $streak;
    };

    $normalized_goals = [];
    $active_count = 0;
    $completed_count = 0;
    $milestones_hit_total = 0;
    $progress_total_active = 0;
    $categories_seen = [];

    foreach ($goals as $idx => $goal) {
        $title = trim((string) ($goal['title'] ?? ($goal['goal_title'] ?? 'Untitled Goal')));
        if ($title === '') {
            $title = 'Untitled Goal';
        }
        $description = trim((string) ($goal['description'] ?? ($goal['notes'] ?? '')));
        $category = trim((string) ($goal['goal_category'] ?? 'Other'));
        if ($category === '') {
            $category = 'Other';
        }
        $category_id = $category_slug($category);
        $palette = $category_palette($category);

        $priority = trim((string) ($goal['goal_priority'] ?? ($goal['priority'] ?? 'Medium')));
        if ($priority === '') {
            $priority = 'Medium';
        }
        $priority_id = $priority_key($priority);

        $start_date = $safe_date($goal['start_date'] ?? ($goal['start'] ?? ''));
        $target_date = $safe_date($goal['target_date'] ?? ($goal['end_date'] ?? ($goal['end'] ?? '')));
        $checkin_frequency = trim((string) ($goal['goal_checkin_frequency'] ?? 'Weekly'));
        if ($checkin_frequency === '') {
            $checkin_frequency = 'Weekly';
        }

        $progress = max(0, min(100, (int) ($goal['progress'] ?? ($goal['progress_percent'] ?? 0))));
        $status_raw = strtolower(trim((string) ($goal['status'] ?? 'active')));
        if ($status_raw === '') {
            $status_raw = 'active';
        }

        if ($progress >= 100 && $status_raw !== 'paused') {
            $status_raw = 'completed';
        }

        $baseline_value = $goal['goal_baseline_value'] ?? '';
        $target_value = $goal['goal_target_value'] ?? '';
        $measure_unit = trim((string) ($goal['goal_measure_unit'] ?? ''));

        $baseline_numeric = ($baseline_value !== '' && is_numeric($baseline_value)) ? (float) $baseline_value : null;
        $target_numeric = ($target_value !== '' && is_numeric($target_value)) ? (float) $target_value : null;

        $current_numeric = null;
        if ($baseline_numeric !== null && $target_numeric !== null) {
            $current_numeric = $baseline_numeric + (($target_numeric - $baseline_numeric) * ($progress / 100));
        } elseif ($target_numeric !== null) {
            $current_numeric = $target_numeric * ($progress / 100);
        }

        $weeks_in = 0;
        if ($start_date !== '') {
            $start_ts = strtotime($start_date . ' 00:00:00');
            if ($start_ts && $today_ts >= $start_ts) {
                $weeks_in = max(0, (int) floor(($today_ts - $start_ts) / WEEK_IN_SECONDS));
            }
        }

        $weeks_remaining = null;
        if ($target_date !== '') {
            $target_ts = strtotime($target_date . ' 00:00:00');
            if ($target_ts) {
                $weeks_remaining = (int) ceil(($target_ts - $today_ts) / WEEK_IN_SECONDS);
            }
        }

        $expected_progress = null;
        if ($start_date !== '' && $target_date !== '') {
            $start_ts = strtotime($start_date . ' 00:00:00');
            $target_ts = strtotime($target_date . ' 00:00:00');
            if ($start_ts && $target_ts && $target_ts > $start_ts) {
                $elapsed = max(0, min($today_ts - $start_ts, $target_ts - $start_ts));
                $expected_progress = (int) round(($elapsed / ($target_ts - $start_ts)) * 100);
            }
        }

        $status_group = 'on_track';
        if ($status_raw === 'paused') {
            $status_group = 'paused';
        } elseif ($status_raw === 'completed') {
            $status_group = 'completed';
        } else {
            if ($expected_progress !== null) {
                $status_group = ($progress + 10 >= $expected_progress) ? 'on_track' : 'at_risk';
            } else {
                $status_group = ($progress >= 35) ? 'on_track' : 'at_risk';
            }
        }

        $milestones_source = $goal['milestones'] ?? [];
        $milestones = [];
        if (is_array($milestones_source)) {
            foreach ($milestones_source as $m_idx => $milestone_item) {
                $text = '';
                $achieved = false;
                if (is_array($milestone_item)) {
                    $text = trim((string) ($milestone_item['text'] ?? ''));
                    $achieved = !empty($milestone_item['achieved']);
                } else {
                    $text = trim((string) $milestone_item);
                }
                if ($text === '') {
                    continue;
                }
                $milestones[] = [
                    'text' => $text,
                    'achieved' => $achieved,
                ];
            }
        }

        if (!empty($milestones)) {
            $hit_count = 0;
            foreach ($milestones as &$milestone_ref) {
                if ($milestone_ref['achieved']) {
                    $hit_count++;
                }
            }
            unset($milestone_ref);

            if ($hit_count === 0 && $progress > 0) {
                $inferred = (int) floor(($progress / 100) * count($milestones));
                $inferred = max(0, min(count($milestones), $inferred));
                foreach ($milestones as $m_i => &$milestone_ref2) {
                    $milestone_ref2['achieved'] = $m_i < $inferred;
                }
                unset($milestone_ref2);
            }
        }

        $milestones_total = count($milestones);
        $milestones_hit = count(array_filter($milestones, static function ($m) {
            return !empty($m['achieved']);
        }));
        $milestones_hit_total += $milestones_hit;

        $next_milestone = '';
        foreach ($milestones as $milestone) {
            if (empty($milestone['achieved'])) {
                $next_milestone = $milestone['text'];
                break;
            }
        }

        $progress_history = isset($goal['progress_history']) && is_array($goal['progress_history'])
            ? array_values($goal['progress_history'])
            : [];

        usort($progress_history, static function ($a, $b) {
            $a_ts = strtotime((string) ($a['date'] ?? '')) ?: 0;
            $b_ts = strtotime((string) ($b['date'] ?? '')) ?: 0;
            return $b_ts <=> $a_ts;
        });

        $progress_text = isset($goal['progress_text']) && is_array($goal['progress_text'])
            ? array_values($goal['progress_text'])
            : [];

        usort($progress_text, static function ($a, $b) {
            $a_ts = strtotime((string) ($a['date'] ?? '')) ?: 0;
            $b_ts = strtotime((string) ($b['date'] ?? '')) ?: 0;
            return $b_ts <=> $a_ts;
        });

        $checkins = [];
        foreach ($progress_text as $note_item) {
            $note_text = trim((string) ($note_item['text'] ?? ''));
            if ($note_text === '') {
                continue;
            }
            $note_date = $safe_date($note_item['date'] ?? '');
            $checkins[] = [
                'date' => $note_date,
                'note' => $note_text,
            ];
        }

        $last_checkin = '';
        if (!empty($checkins)) {
            $last_checkin = (string) ($checkins[0]['date'] ?? '');
        } elseif (!empty($progress_history)) {
            $last_checkin = $safe_date($progress_history[0]['date'] ?? '');
        }

        $linked_routines = [];
        foreach ($routines as $r_idx => $routine) {
            $routine_goal_link = strtolower(trim((string) ($routine['routine_goal_link'] ?? '')));
            if ($routine_goal_link === '') {
                continue;
            }
            $title_lc = strtolower($title);
            $category_lc = strtolower($category);

            if (strpos($routine_goal_link, $title_lc) === false && strpos($routine_goal_link, $category_lc) === false) {
                continue;
            }

            $routine_title = trim((string) ($routine['title'] ?? ($routine['routine_title'] ?? 'Routine')));
            if ($routine_title === '') {
                $routine_title = 'Routine';
            }
            $routine_frequency = trim((string) ($routine['routine_frequency'] ?? ($routine['frequency'] ?? 'Weekly')));
            if ($routine_frequency === '') {
                $routine_frequency = 'Weekly';
            }
            $routine_duration = trim((string) ($routine['routine_duration'] ?? ($routine['duration'] ?? '')));
            if ($routine_duration !== '' && preg_match('/^\d+$/', $routine_duration)) {
                $routine_duration .= ' min';
            }
            $routine_steps = $parse_list($routine['steps'] ?? ($routine['routine_steps'] ?? []));
            $dates_for_routine = $routine_dates[(int) $r_idx] ?? [];
            $linked_routines[] = [
                'id' => (int) $r_idx,
                'title' => $routine_title,
                'frequency' => $routine_frequency,
                'duration' => $routine_duration,
                'steps_count' => max(1, count($routine_steps)),
                'streak' => $routine_streak($dates_for_routine, $today),
                'last_completed' => !empty($dates_for_routine) ? $dates_for_routine[0] : '',
            ];
        }

        $search_blob = strtolower(implode(' ', [
            $title,
            $description,
            $category,
            $priority,
            $checkin_frequency,
            $status_group,
        ]));

        $normalized_goal = [
            'id' => (int) $idx,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'category_id' => $category_id,
            'palette' => $palette,
            'priority' => $priority,
            'priority_id' => $priority_id,
            'status_raw' => $status_raw,
            'status_group' => $status_group,
            'progress' => $progress,
            'start_date' => $start_date,
            'target_date' => $target_date,
            'weeks_in' => $weeks_in,
            'weeks_remaining' => $weeks_remaining,
            'checkin_frequency' => $checkin_frequency,
            'baseline_value' => $baseline_numeric,
            'target_value' => $target_numeric,
            'current_value' => $current_numeric,
            'measure_unit' => $measure_unit,
            'baseline_display' => $format_value($baseline_numeric, $measure_unit),
            'current_display' => $format_value($current_numeric, $measure_unit),
            'target_display' => $format_value($target_numeric, $measure_unit),
            'milestones' => $milestones,
            'milestones_total' => $milestones_total,
            'milestones_hit' => $milestones_hit,
            'next_milestone' => $next_milestone,
            'checkins' => array_slice($checkins, 0, 12),
            'last_checkin' => $last_checkin,
            'linked_routines' => $linked_routines,
            'goal_reward' => trim((string) ($goal['goal_reward'] ?? '')),
            'goal_success_criteria' => trim((string) ($goal['goal_success_criteria'] ?? '')),
            'expected_progress' => $expected_progress,
            'search' => $search_blob,
        ];

        $normalized_goals[] = $normalized_goal;

        if ($status_group === 'completed') {
            $completed_count++;
        } else {
            $active_count++;
            $progress_total_active += $progress;
        }

        if (!isset($categories_seen[$category_id])) {
            $categories_seen[$category_id] = $category;
        }
    }

    usort($normalized_goals, static function ($a, $b) {
        $score = ['on_track' => 0, 'at_risk' => 1, 'paused' => 2, 'completed' => 3];
        $a_score = $score[$a['status_group']] ?? 9;
        $b_score = $score[$b['status_group']] ?? 9;
        if ($a_score !== $b_score) {
            return $a_score <=> $b_score;
        }
        if ($a['status_group'] === 'completed' && $b['status_group'] === 'completed') {
            return ($b['progress'] <=> $a['progress']);
        }
        return ($b['progress'] <=> $a['progress']);
    });

    $active_goals = array_values(array_filter($normalized_goals, static function ($goal) {
        return $goal['status_group'] !== 'completed';
    }));

    $completed_goals = array_values(array_filter($normalized_goals, static function ($goal) {
        return $goal['status_group'] === 'completed';
    }));

    $avg_progress = $active_count > 0 ? (int) round($progress_total_active / $active_count) : 0;

    $milestone_goal = null;
    foreach ($active_goals as $goal) {
        if ($goal['milestones_hit'] > 0) {
            $milestone_goal = $goal;
            break;
        }
    }

    $category_filters = [];
    foreach ($categories_seen as $cat_id => $cat_label) {
        $category_filters[] = [
            'id' => $cat_id,
            'label' => $cat_label,
        ];
    }

    usort($category_filters, static function ($a, $b) {
        return strcasecmp($a['label'], $b['label']);
    });

    $goals_json = wp_json_encode($normalized_goals);

    ob_start();
    ?>
    <div class="hair-journey-container myavana-goals-v2-page" data-today="<?php echo esc_attr($today); ?>">
        <div class="myavana-gv2" id="myavanaGoalsV2Root">
            <section class="myavana-gv2-overview">
                <div class="myavana-gv2-overview-left">
                    <div class="eyebrow">Your goal journey</div>
                    <h2><?php echo esc_html($active_count); ?> goals <span>in motion</span></h2>
                    <p>
                        <?php if ($active_count > 0) : ?>
                            Keep momentum by checking in consistently. Your current active-goal average progress is <?php echo esc_html($avg_progress); ?>%.
                        <?php else : ?>
                            Set your first goal and define what progress should look like for your hair journey.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="myavana-gv2-overview-stats">
                    <article>
                        <div class="val"><?php echo esc_html($active_count); ?></div>
                        <div class="lbl">Active</div>
                    </article>
                    <article>
                        <div class="val"><?php echo esc_html($completed_count); ?></div>
                        <div class="lbl">Completed</div>
                    </article>
                    <article>
                        <div class="val"><?php echo esc_html($milestones_hit_total); ?></div>
                        <div class="lbl">Milestones Hit</div>
                    </article>
                    <article>
                        <div class="val"><?php echo esc_html($avg_progress); ?>%</div>
                        <div class="lbl">Avg Progress</div>
                    </article>
                </div>
            </section>

            <?php if ($milestone_goal) : ?>
                <section class="myavana-gv2-alert" id="myavanaGv2Alert">
                    <div class="icon"><?php echo $icon('trophy', 'is-md'); ?></div>
                    <div class="info">
                        <h3>Milestone unlocked in <?php echo esc_html($milestone_goal['title']); ?></h3>
                        <p>
                            <?php echo esc_html($milestone_goal['milestones_hit']); ?> of <?php echo esc_html(max(1, $milestone_goal['milestones_total'])); ?> milestones complete.
                            <?php if (!empty($milestone_goal['next_milestone'])) : ?>Next: <?php echo esc_html($milestone_goal['next_milestone']); ?>.<?php endif; ?>
                        </p>
                    </div>
                    <button type="button" class="cta" data-gv2-open-detail="<?php echo esc_attr($milestone_goal['id']); ?>">View goal</button>
                    <button type="button" class="close" data-gv2-dismiss-alert aria-label="Dismiss"><?php echo $icon('x', 'is-close'); ?></button>
                </section>
            <?php endif; ?>

            <section class="myavana-gv2-goals-wrap">
                <div class="myavana-gv2-controls">
                    <div class="left">
                        <h3><?php echo $icon('target', 'is-xs'); ?>Active Goals</h3>
                        <div class="status-tabs" id="myavanaGv2StatusTabs">
                            <button class="tab is-active" type="button" data-status="all"><?php echo $icon('layout-grid', 'is-xxs'); ?>All</button>
                            <button class="tab" type="button" data-status="on_track"><?php echo $icon('check', 'is-xxs'); ?>On Track</button>
                            <button class="tab" type="button" data-status="at_risk"><?php echo $icon('triangle-alert', 'is-xxs'); ?>At Risk</button>
                            <button class="tab" type="button" data-status="paused"><?php echo $icon('pause', 'is-xxs'); ?>Paused</button>
                        </div>
                    </div>
                    <button type="button" class="myavana-gv2-btn ghost" data-gv2-open-picker><?php echo $icon('plus', 'is-btn'); ?>New Goal</button>
                </div>

                <div class="myavana-gv2-category-row" id="myavanaGv2CategoryRow">
                    <button class="chip is-active" type="button" data-category="all">All Categories</button>
                    <?php foreach ($category_filters as $filter) : ?>
                        <button class="chip" type="button" data-category="<?php echo esc_attr($filter['id']); ?>"><?php echo esc_html($filter['label']); ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="myavana-gv2-grid" id="myavanaGv2Grid">
                    <?php if (empty($active_goals)) : ?>
                        <article class="myavana-gv2-create-card is-empty">
                            <div class="cc-icon"><?php echo $icon('target', 'is-create'); ?></div>
                            <h4>Set your first goal</h4>
                            <p>Define a clear target, attach milestones, and track progress over time.</p>
                            <button type="button" class="myavana-gv2-btn" data-gv2-open-picker><?php echo $icon('plus', 'is-btn'); ?>Create Goal</button>
                        </article>
                    <?php else : ?>
                        <?php foreach ($active_goals as $goal) :
                            $strip_class = 'strip-' . $goal['palette'];
                            $badge_class = 'cb-' . $goal['palette'];
                            $prog_class = 'prog-' . $goal['palette'];
                            $fill_class = 'pf-' . $goal['palette'];
                            $priority_dot = 'pd-' . $goal['priority_id'];
                        ?>
                            <article
                                class="myavana-gv2-card"
                                data-gv2-card
                                data-goal-id="<?php echo esc_attr($goal['id']); ?>"
                                data-status-group="<?php echo esc_attr($goal['status_group']); ?>"
                                data-category-id="<?php echo esc_attr($goal['category_id']); ?>"
                                data-search="<?php echo esc_attr($goal['search']); ?>">
                                <div class="myavana-gv2-card-strip <?php echo esc_attr($strip_class); ?>"></div>
                                <div class="myavana-gv2-card-body" data-gv2-open-detail="<?php echo esc_attr($goal['id']); ?>">
                                    <div class="top">
                                        <div class="badges">
                                            <span class="badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($goal['category']); ?></span>
                                            <span class="badge cb-sand"><?php echo esc_html($goal['priority']); ?> Priority</span>
                                        </div>
                                        <span class="priority-dot <?php echo esc_attr($priority_dot); ?>"></span>
                                    </div>

                                    <h4 class="title"><?php echo esc_html($goal['title']); ?></h4>
                                    <p class="desc"><?php echo esc_html($goal['description'] !== '' ? wp_trim_words($goal['description'], 24) : 'No description added yet.'); ?></p>

                                    <div class="progress-wrap">
                                        <div class="head">
                                            <div class="label">
                                                <?php if ($goal['current_display'] !== '-' && $goal['target_display'] !== '-') : ?>
                                                    <?php echo esc_html($goal['current_display']); ?> of <?php echo esc_html($goal['target_display']); ?> target
                                                <?php else : ?>
                                                    Progress toward target
                                                <?php endif; ?>
                                            </div>
                                            <div class="pct <?php echo esc_attr($prog_class); ?>"><?php echo esc_html($goal['progress']); ?>%</div>
                                        </div>
                                        <div class="track"><div class="fill <?php echo esc_attr($fill_class); ?>" style="width: <?php echo esc_attr($goal['progress']); ?>%"></div></div>
                                    </div>

                                    <div class="values">
                                        <div class="item"><div class="num"><?php echo esc_html($goal['baseline_display']); ?></div><div class="lbl">Baseline</div></div>
                                        <div class="item"><div class="num"><?php echo esc_html($goal['current_display']); ?></div><div class="lbl">Current</div></div>
                                        <div class="item"><div class="num"><?php echo esc_html($goal['target_display']); ?></div><div class="lbl">Target</div></div>
                                    </div>

                                    <div class="milestones">
                                        <div class="m-label">Milestones</div>
                                        <div class="m-track">
                                            <?php
                                            $nodes_count = max(1, min(5, $goal['milestones_total'] > 0 ? $goal['milestones_total'] : 1));
                                            $hit_nodes = (int) min($nodes_count, round(($goal['milestones_hit'] / max(1, $goal['milestones_total'])) * $nodes_count));
                                            for ($node_i = 0; $node_i < $nodes_count; $node_i++) :
                                                $node_state = $node_i < $hit_nodes ? 'hit' : (($node_i === $hit_nodes && $goal['status_group'] !== 'paused') ? 'next' : '');
                                            ?>
                                                <span class="m-node <?php echo esc_attr($node_state); ?>"><?php echo $node_state === 'hit' ? $icon('check', 'is-xxs') : ''; ?></span>
                                                <?php if ($node_i < $nodes_count - 1) : ?>
                                                    <span class="m-line <?php echo $node_i < ($hit_nodes - 1) ? 'hit' : ''; ?>"></span>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="m-caption">
                                            <?php if ($goal['next_milestone'] !== '') : ?>
                                                Next: <?php echo esc_html($goal['next_milestone']); ?>
                                            <?php else : ?>
                                                <?php echo esc_html($goal['milestones_hit']); ?> of <?php echo esc_html(max(1, $goal['milestones_total'])); ?> milestones hit
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="meta">
                                        <span class="pill"><?php echo $icon('calendar-days', 'is-xxs'); ?><?php echo esc_html($goal['weeks_in']); ?> weeks in</span>
                                        <?php if ($goal['target_date'] !== '') : ?><span class="pill"><?php echo $icon('flag', 'is-xxs'); ?><?php echo esc_html(date_i18n('M Y', strtotime($goal['target_date']))); ?></span><?php endif; ?>
                                        <span class="pill"><?php echo $icon('repeat-2', 'is-xxs'); ?><?php echo esc_html($goal['checkin_frequency']); ?></span>
                                    </div>
                                </div>

                                <div class="myavana-gv2-card-foot">
                                    <div>
                                        <div class="left-meta">Last check-in: <?php echo esc_html($goal['last_checkin'] !== '' ? date_i18n('M j, Y', strtotime($goal['last_checkin'])) : 'Not yet'); ?></div>
                                        <div class="left-streak"><?php echo $icon('flame', 'is-xxs'); ?><?php echo esc_html($goal['checkin_frequency']); ?> cadence</div>
                                    </div>
                                    <button type="button" class="checkin-btn" data-gv2-open-checkin="<?php echo esc_attr($goal['id']); ?>"><?php echo $icon('plus', 'is-xxs'); ?>Check in</button>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <article class="myavana-gv2-create-card" data-gv2-open-picker>
                            <div class="cc-icon"><?php echo $icon('target', 'is-create'); ?></div>
                            <h4>Set a New Goal</h4>
                            <p>Define milestones, timeline, and success criteria.</p>
                        </article>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (!empty($completed_goals)) : ?>
                <section class="myavana-gv2-completed">
                    <div class="head">
                        <h3><?php echo $icon('trophy', 'is-xs'); ?>Completed Goals</h3>
                    </div>
                    <div class="grid">
                        <?php foreach (array_slice($completed_goals, 0, 6) as $goal) : ?>
                            <article class="comp-card">
                                <div class="top">
                                    <div class="check"><?php echo $icon('check', 'is-xs'); ?></div>
                                    <div>
                                        <div class="title"><?php echo esc_html($goal['title']); ?></div>
                                        <div class="date">
                                            Completed
                                            <?php if ($goal['target_date'] !== '') : ?> · <?php echo esc_html(date_i18n('F Y', strtotime($goal['target_date']))); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($goal['goal_reward'] !== '') : ?>
                                    <div class="reward"><?php echo $icon('medal', 'is-xxs'); ?>Reward: <?php echo esc_html($goal['goal_reward']); ?></div>
                                <?php else : ?>
                                    <div class="reward"><?php echo $icon('medal', 'is-xxs'); ?>Goal completed successfully</div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <button type="button" class="myavana-gv2-fab" data-gv2-open-picker aria-label="Set new goal"><?php echo $icon('plus', 'is-fab'); ?></button>

            <div class="myavana-gv2-overlay" id="myavanaGv2Overlay"></div>
            <aside class="myavana-gv2-drawer" id="myavanaGv2Drawer" aria-hidden="true">
                <header class="drawer-head">
                    <h3>Goal Detail</h3>
                    <div class="actions">
                        <div class="tabs" id="myavanaGv2DrawerTabs">
                            <button class="tab is-active" type="button" data-tab="overview">Overview</button>
                            <button class="tab" type="button" data-tab="milestones">Milestones</button>
                            <button class="tab" type="button" data-tab="checkins">Check-ins</button>
                            <button class="tab" type="button" data-tab="routines">Routines</button>
                        </div>
                        <button type="button" class="close" data-gv2-close-detail><?php echo $icon('x', 'is-close'); ?></button>
                    </div>
                </header>

                <div class="drawer-scroll">
                    <section class="pane is-active" data-pane="overview">
                        <div class="hero">
                            <div class="strip" id="myavanaGv2DetailStrip"></div>
                            <div class="badges" id="myavanaGv2DetailBadges"></div>
                            <h4 id="myavanaGv2DetailTitle">Goal title</h4>
                            <p id="myavanaGv2DetailDescription">Goal description</p>
                            <div class="big-progress">
                                <div class="head">
                                    <div>
                                        <div class="pct" id="myavanaGv2DetailPct">0%</div>
                                        <div class="lbl" id="myavanaGv2DetailProgLbl">Progress label</div>
                                    </div>
                                    <div class="status" id="myavanaGv2DetailStatus">On Track</div>
                                </div>
                                <div class="track"><div class="fill" id="myavanaGv2DetailFill" style="width:0%"></div></div>
                            </div>
                            <div class="timeline" id="myavanaGv2DetailTimeline"></div>
                        </div>
                        <div class="body">
                            <div class="sec">
                                <div class="label">Baseline vs Current vs Target</div>
                                <div class="value-grid" id="myavanaGv2DetailValues"></div>
                            </div>
                            <div class="sec" id="myavanaGv2DetailCriteriaSec">
                                <div class="label">Success Criteria</div>
                                <p id="myavanaGv2DetailCriteria"></p>
                            </div>
                            <div class="sec" id="myavanaGv2DetailRewardSec">
                                <div class="label">Reward</div>
                                <div class="reward-pill" id="myavanaGv2DetailReward"></div>
                            </div>
                        </div>
                    </section>

                    <section class="pane" data-pane="milestones">
                        <div class="body">
                            <div class="label">Milestones</div>
                            <div id="myavanaGv2DetailMilestones"></div>
                        </div>
                    </section>

                    <section class="pane" data-pane="checkins">
                        <div class="body">
                            <div class="checkins-head">
                                <div class="label">Check-ins</div>
                                <button type="button" class="checkin-btn" data-gv2-open-checkin-current><?php echo $icon('plus', 'is-xxs'); ?>New Check-in</button>
                            </div>
                            <div id="myavanaGv2DetailCheckins"></div>
                        </div>
                    </section>

                    <section class="pane" data-pane="routines">
                        <div class="body">
                            <div class="label">Linked Routines</div>
                            <div id="myavanaGv2DetailRoutines"></div>
                            <div class="impact" id="myavanaGv2DetailImpact"></div>
                        </div>
                    </section>
                </div>

                <footer class="drawer-foot">
                    <button type="button" class="myavana-gv2-btn" id="myavanaGv2DetailCheckinBtn"><?php echo $icon('plus', 'is-xs'); ?>Check In</button>
                    <button type="button" class="myavana-gv2-btn ghost" id="myavanaGv2DetailEditBtn"><?php echo $icon('pencil', 'is-xs'); ?>Edit</button>
                    <button type="button" class="myavana-gv2-btn danger" id="myavanaGv2DetailPauseBtn">Pause</button>
                    <button type="button" class="myavana-gv2-btn danger" id="myavanaGv2DetailDeleteBtn"><?php echo $icon('trash-2', 'is-xs'); ?>Delete</button>
                </footer>
            </aside>

            <div class="myavana-gv2-checkin" id="myavanaGv2CheckinModal" aria-hidden="true">
                <div class="overlay" data-gv2-close-checkin></div>
                <div class="box">
                    <div class="top">
                        <h3>Weekly Check-in</h3>
                        <button type="button" class="close" data-gv2-close-checkin><?php echo $icon('x', 'is-close'); ?></button>
                    </div>
                    <p id="myavanaGv2CheckinGoalSub">Record your progress this period</p>

                    <label class="field-label">Current measurement</label>
                    <div class="measure-row">
                        <input type="number" id="myavanaGv2MeasureInput" step="0.1" min="0" placeholder="0.0" />
                        <span id="myavanaGv2MeasureUnit">unit</span>
                    </div>

                    <label class="field-label">How did your hair feel?</label>
                    <div class="moods" id="myavanaGv2MoodRow">
                        <button type="button" class="mood" data-mood="great">Great</button>
                        <button type="button" class="mood is-selected" data-mood="ok">OK</button>
                        <button type="button" class="mood" data-mood="rough">Rough</button>
                    </div>

                    <label class="field-label">Rate this period</label>
                    <div class="stars" id="myavanaGv2StarRow">
                        <button type="button" class="star" data-rating="1"><?php echo $icon('star', 'is-star'); ?></button>
                        <button type="button" class="star" data-rating="2"><?php echo $icon('star', 'is-star'); ?></button>
                        <button type="button" class="star" data-rating="3"><?php echo $icon('star', 'is-star'); ?></button>
                        <button type="button" class="star" data-rating="4"><?php echo $icon('star', 'is-star'); ?></button>
                        <button type="button" class="star" data-rating="5"><?php echo $icon('star', 'is-star'); ?></button>
                    </div>

                    <label class="field-label">Notes (optional)</label>
                    <textarea id="myavanaGv2CheckinNote" placeholder="How did your routines go this week? Any observations..."></textarea>

                    <div class="btn-row">
                        <button type="button" class="myavana-gv2-btn" id="myavanaGv2SaveCheckinBtn"><?php echo $icon('save', 'is-xs'); ?>Save Check-in</button>
                        <button type="button" class="myavana-gv2-btn ghost" data-gv2-close-checkin>Cancel</button>
                    </div>
                </div>
            </div>

            <div class="myavana-gv2-picker-overlay" id="myavanaGv2PickerOverlay" data-gv2-close-picker></div>
            <div class="myavana-gv2-picker" id="myavanaGv2Picker" aria-hidden="true">
                <div class="handle"></div>
                <h3>Set a New Goal</h3>
                <p>Choose a category template to get started quickly.</p>
                <div class="picker-grid">
                    <button type="button" data-category="Length"><?php echo $icon('ruler', 'is-xs'); ?><span>Length</span></button>
                    <button type="button" data-category="Moisture"><?php echo $icon('droplets', 'is-xs'); ?><span>Moisture</span></button>
                    <button type="button" data-category="Scalp"><?php echo $icon('sparkles', 'is-xs'); ?><span>Scalp</span></button>
                    <button type="button" data-category="Strength"><?php echo $icon('shield-plus', 'is-xs'); ?><span>Strength</span></button>
                    <button type="button" data-category="Texture"><?php echo $icon('waves', 'is-xs'); ?><span>Texture</span></button>
                    <button type="button" data-category="Other"><?php echo $icon('bot', 'is-xs'); ?><span>AI Suggest</span></button>
                </div>
            </div>
        </div>

        <script type="application/json" id="myavanaGoalsV2Data"><?php echo wp_json_encode([
            'today' => $today,
            'goals' => $normalized_goals,
        ]); ?></script>

        <?php
        $partials_dir = __DIR__ . '/partials';
        // Goals has its own drawer, check-in and picker. Keep unrelated timeline
        // viewers off this route so their legacy markup cannot leak into the page.
        if (file_exists($partials_dir . '/create-offcanvas.php')) {
            $myavana_hjn_context = 'goals';
            include $partials_dir . '/create-offcanvas.php';
            unset($myavana_hjn_context);
        }
        ?>
    </div>
    <?php

    wp_add_inline_script(
        'myavana-goals-page-redesign',
        'window.myavanaGoalsV2Boot = ' . ($goals_json ?: '[]') . ';',
        'before'
    );

    return ob_get_clean();
}
}
