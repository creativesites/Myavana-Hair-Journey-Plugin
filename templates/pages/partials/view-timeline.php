<?php
/**
 * Hair Journey Timeline View (Redesigned)
 * Mobile-first weekly chapters with operating-center header.
 */

$user_id = 0;
if (isset($current_user) && !empty($current_user->ID)) {
    $user_id = intval($current_user->ID);
}
else {
    $user_id = get_current_user_id();
}

if (!$user_id): ?>
<div id="timelineView" class="view-content timeline-view-hjn active">
    <div class="timeline-empty-state-hjn">
        <h3>Sign in to view your journey timeline</h3>
    </div>
</div>
<?php
    return;
endif;

$hair_goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
if (!is_array($hair_goals)) {
    $hair_goals = [];
}

$current_routine = get_user_meta($user_id, 'myavana_current_routine', true);
if (!is_array($current_routine)) {
    $current_routine = [];
}

$routine_completion_map = get_user_meta($user_id, 'myavana_routine_completions', true);
if (!is_array($routine_completion_map)) {
    $routine_completion_map = [];
}

$today_date = current_time('Y-m-d');
$completed_today_routines = isset($routine_completion_map[$today_date]) && is_array($routine_completion_map[$today_date])
    ? array_values(array_map('intval', $routine_completion_map[$today_date]))
    : [];

$entries = get_posts([
    'post_type' => 'hair_journey_entry',
    'author' => $user_id,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'post_date',
    'order' => 'DESC',
]);

$resolve_timestamp = static function ($candidate_dates, $fallback_ts) {
    foreach ((array)$candidate_dates as $candidate) {
        if ($candidate === null || $candidate === '') {
            continue;
        }
        $ts = strtotime((string)$candidate);
        if ($ts) {
            return $ts;
        }
    }
    return intval($fallback_ts);
};

$type_priority = [
    'goal' => 0,
    'routine' => 1,
    'entry' => 2,
];

$timeline_items = [];
$now_ts = current_time('timestamp');

foreach ($hair_goals as $idx => $goal) {
    $goal_ts = $resolve_timestamp([
        $goal['updated_at'] ?? '',
        $goal['created_at'] ?? '',
        $goal['created_date'] ?? '',
        $goal['date_created'] ?? '',
        $goal['start_date'] ?? '',
        $goal['start'] ?? '',
    ], $now_ts - ($idx * 60));

    $timeline_items[] = [
        'type' => 'goal',
        'date' => $goal_ts,
        'day_key' => date('Y-m-d', $goal_ts),
        'data' => $goal,
        'index' => $idx,
    ];
}

foreach ($current_routine as $idx => $routine) {
    $routine_ts = $resolve_timestamp([
        $routine['updated_at'] ?? '',
        $routine['created_at'] ?? '',
        $routine['created_date'] ?? '',
        $routine['date_created'] ?? '',
        $routine['date'] ?? '',
    ], $now_ts - ($idx * 45));

    $timeline_items[] = [
        'type' => 'routine',
        'date' => $routine_ts,
        'day_key' => date('Y-m-d', $routine_ts),
        'data' => $routine,
        'index' => $idx,
    ];
}

foreach ($entries as $entry) {
    $entry_ts = strtotime($entry->post_date);
    $timeline_items[] = [
        'type' => 'entry',
        'date' => $entry_ts,
        'day_key' => date('Y-m-d', $entry_ts),
        'data' => $entry,
        'index' => $entry->ID,
    ];
}

usort($timeline_items, static function ($a, $b) use ($type_priority) {
    if ($a['day_key'] !== $b['day_key']) {
        return strcmp($b['day_key'], $a['day_key']);
    }

    if ($a['date'] !== $b['date']) {
        return $b['date'] - $a['date'];
    }

    $a_priority = $type_priority[$a['type']] ?? 99;
    $b_priority = $type_priority[$b['type']] ?? 99;
    return $a_priority - $b_priority;
});

$timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
$grouped_items = [];

foreach ($timeline_items as $item) {
    $dt = new DateTimeImmutable('@' . intval($item['date']));
    $dt = $dt->setTimezone($timezone);
    $week_start = $dt->modify('monday this week')->setTime(0, 0, 0);
    $week_end = $week_start->modify('+6 days')->setTime(23, 59, 59);
    $week_key = $week_start->format('o-\\WW');

    if (!isset($grouped_items[$week_key])) {
        $grouped_items[$week_key] = [
            'label' => $week_start->format('M j') . ' - ' . $week_end->format('M j, Y'),
            'counts' => ['entry' => 0, 'goal' => 0, 'routine' => 0],
            'items' => [],
        ];
    }

    $grouped_items[$week_key]['counts'][$item['type']]++;
    $grouped_items[$week_key]['items'][] = $item;
}

$resolve_shortcode_page_url = static function ($shortcode, $fallback_path) {
    global $wpdb;

    $candidates = [$shortcode];
    if (strpos($shortcode, '-') !== false) {
        $candidates[] = str_replace('-', '_', $shortcode);
    }

    foreach ($candidates as $candidate) {
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID
             FROM {$wpdb->posts}
             WHERE post_type = 'page'
               AND post_status = 'publish'
               AND post_content LIKE %s
             ORDER BY ID ASC
             LIMIT 1",
            '%[' . $wpdb->esc_like($candidate) . '%'
        ));

        if ($page_id) {
            $permalink = get_permalink(intval($page_id));
            if ($permalink) {
                return $permalink;
            }
        }
    }

    return home_url($fallback_path);
};

$goals_page_url = $resolve_shortcode_page_url('myavana_goals_page', '/goals/');
$routines_page_url = $resolve_shortcode_page_url('myavana_routines_page', '/routines/');
?>

<div id="timelineView" class="view-content timeline-view-hjn active">

    <?php
/* ── helper: render star rating ── */
$render_stars = static function (int $rating): string {
    if ($rating <= 0)
        return '';
    $filled = str_repeat('★', min(5, $rating));
    $empty = str_repeat('☆', max(0, 5 - $rating));
    return esc_html($filled . $empty);
};
?>

    <!-- ══ FILTER CONTROLS ══ -->
    <div class="tl2-ctrl">
        <div class="tl2-filter-chips" role="group" aria-label="Filter timeline">
            <button class="tl2-chip timeline-filter-btn-hjn active" data-filter="all"
                onclick="setTimelineFilter('all')">All</button>
            <button class="tl2-chip timeline-filter-btn-hjn" data-filter="entry"
                onclick="setTimelineFilter('entry')">Entries</button>
            <button class="tl2-chip timeline-filter-btn-hjn" data-filter="goal"
                onclick="setTimelineFilter('goal')">Goals</button>
            <button class="tl2-chip timeline-filter-btn-hjn" data-filter="routine"
                onclick="setTimelineFilter('routine')">Routines</button>
        </div>

        <!-- Rating filter & manage links -->
        <div class="tl2-manage-links">
            <a class="tl2-manage-link" href="<?php echo esc_url($goals_page_url); ?>">Manage Goals</a>
            <a class="tl2-manage-link" href="<?php echo esc_url($routines_page_url); ?>">Manage Routines</a>
            <button type="button" class="tl2-manage-link" style="cursor:pointer;border:none;"
                onclick="openOffcanvas('entry')">+ Log Entry</button>
        </div>
    </div>

    <!-- ══ EMPTY STATE ══ -->
    <?php if (empty($timeline_items)): ?>
    <div class="tl2-empty">
        <svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true">
            <path fill="currentColor"
                d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3" />
        </svg>
        <div class="tl2-empty-title">Start Your Hair Journey</div>
        <p class="tl2-empty-sub">Add your first entry, set a goal, or build a routine to begin your timeline.</p>
        <button class="tl2-empty-btn" onclick="openOffcanvas('entry')">Add Your First Entry</button>
    </div>

    <?php
else: ?>

    <!-- ══ WEEKLY TIMELINE ══ -->
    <div class="tl2-timeline" id="tl2TimelineWrapper">

        <?php
    $week_index = 0;
    foreach ($grouped_items as $week_group):
        $entry_count = intval($week_group['counts']['entry']);
        $goal_count = intval($week_group['counts']['goal']);
        $routine_count = intval($week_group['counts']['routine']);

        $summary_parts = [];
        if ($entry_count)
            $summary_parts[] = $entry_count . ($entry_count === 1 ? ' entry' : ' entries');
        if ($goal_count)
            $summary_parts[] = $goal_count . ($goal_count === 1 ? ' goal' : ' goals');
        if ($routine_count)
            $summary_parts[] = $routine_count . ($routine_count === 1 ? ' routine' : ' routines');
        $week_summary = implode(' · ', $summary_parts);

        /* Split entries vs non-entries for hero/compact layout */
        $week_entries = [];
        $week_nonentry = [];
        foreach ($week_group['items'] as $item) {
            if ($item['type'] === 'entry') {
                $week_entries[] = $item;
            }
            else {
                $week_nonentry[] = $item;
            }
        }
        $week_index++;
?>
        <div class="tl2-week" data-week-index="<?php echo esc_attr($week_index); ?>"
            data-entry-count="<?php echo esc_attr($entry_count); ?>"
            data-goal-count="<?php echo esc_attr($goal_count); ?>"
            data-routine-count="<?php echo esc_attr($routine_count); ?>">

            <!-- Week Header -->
            <div class="tl2-week-hdr">
                <span class="tl2-week-lbl">
                    <?php echo esc_html($week_group['label']); ?>
                </span>
                <div class="tl2-week-line"></div>
                <span class="tl2-week-count">
                    <?php echo esc_html($week_summary); ?>
                </span>
            </div>

            <!-- Entry Cards (hero grid) -->
            <?php if (!empty($week_entries)): ?>
            <div class="tl2-entries-grid">
                <?php foreach ($week_entries as $item):
                $entry = $item['data'];
                $post_id = $entry->ID;
                $e_title = get_the_title($post_id);
                $e_content = wp_strip_all_tags($entry->post_content);
                $e_date = get_the_date('M j, Y', $post_id);
                $e_thumb = get_the_post_thumbnail_url($post_id, 'medium_large');
                $e_rating = intval(get_post_meta($post_id, 'health_rating', true));
                $e_type_meta = get_post_meta($post_id, 'entry_type', true);
                $e_type_label = $e_type_meta ? ucfirst(str_replace('_', ' ', $e_type_meta)) : 'Entry';
                $e_products_raw = get_post_meta($post_id, 'products_used', true);
                $e_products = $e_products_raw ? array_filter(array_map('trim', explode(',', (string)$e_products_raw))) : [];
                $stars_str = $render_stars($e_rating);
?>
                <div class="tl2-entry-hero tl2-filter-item" data-type="entry" data-rating="<?php echo esc_attr($e_rating); ?>"
                    onclick="openViewOffcanvas('entry', <?php echo intval($post_id); ?>)">

                    <div class="tl2-entry-thumb">
                        <?php if ($e_thumb): ?>
                        <img src="<?php echo esc_url($e_thumb); ?>" alt="<?php echo esc_attr($e_title); ?>"
                            loading="lazy">
                        <?php
                else: ?>
                        🌿
                        <?php
                endif; ?>
                    </div>

                    <div class="tl2-entry-overlay">
                        <div class="tl2-entry-type-pill">✦
                            <?php echo esc_html($e_type_label); ?>
                        </div>
                        <div class="tl2-entry-title">
                            <?php echo esc_html($e_title); ?>
                        </div>
                        <div class="tl2-entry-meta-row">
                            <span class="tl2-entry-date">
                                <?php echo esc_html($e_date); ?>
                            </span>
                            <?php if ($stars_str): ?>
                            <div class="tl2-entry-dot"></div>
                            <span class="tl2-entry-stars">
                                <?php echo $stars_str; ?>
                            </span>
                            <?php
                endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($e_products)): ?>
                    <div class="tl2-entry-footer">
                        <?php
                    $shown = array_slice($e_products, 0, 2);
                    $extra = count($e_products) - count($shown);
                    foreach ($shown as $prod): ?>
                        <span class="tl2-product-tag">
                            <?php echo esc_html($prod); ?>
                        </span>
                        <?php
                    endforeach;
                    if ($extra > 0): ?>
                        <span class="tl2-product-tag">+
                            <?php echo esc_html($extra); ?> more
                        </span>
                        <?php
                    endif; ?>
                        <div class="tl2-entry-actions">
                            <button type="button" class="tl2-inline-btn"
                                onclick="event.stopPropagation(); openViewOffcanvas('entry', <?php echo intval($post_id); ?>)">View</button>
                            <button type="button" class="tl2-inline-btn"
                                onclick="event.stopPropagation(); editEntry(<?php echo intval($post_id); ?>)">Edit</button>
                        </div>
                    </div>
                    <?php
                else: ?>
                    <div class="tl2-entry-footer">
                        <div class="tl2-entry-actions">
                            <button type="button" class="tl2-inline-btn"
                                onclick="event.stopPropagation(); openViewOffcanvas('entry', <?php echo intval($post_id); ?>)">View</button>
                            <button type="button" class="tl2-inline-btn"
                                onclick="event.stopPropagation(); editEntry(<?php echo intval($post_id); ?>)">Edit</button>
                        </div>
                    </div>
                    <?php
                endif; ?>
                </div>
                <?php
            endforeach; ?>
            </div>
            <?php
        endif; ?>

            <!-- Goals & Routines grid -->
            <?php if (!empty($week_nonentry)): ?>
            <div class="tl2-nonentry-grid">
            <?php foreach ($week_nonentry as $item):

            if ($item['type'] === 'goal'):
                $goal = $item['data'];
                $g_index = intval($item['index']);
                $g_title = $goal['title'] ?? $goal['goal_title'] ?? 'Untitled Goal';
                $g_desc = $goal['description'] ?? $goal['notes'] ?? '';
                $g_progress = intval($goal['progress'] ?? ($goal['progress_percent'] ?? 0));
                $g_start = $goal['start_date'] ?? $goal['start'] ?? '';
                $g_end = $goal['target_date'] ?? $goal['end_date'] ?? ($goal['end'] ?? '');
                $g_range = '';
                if ($g_start || $g_end) {
                    $g_range = trim(
                        ($g_start ? date_i18n('M j', strtotime($g_start)) : '') .
                        ($g_start && $g_end ? ' – ' : '') .
                        ($g_end ? date_i18n('M j, Y', strtotime($g_end)) : '')
                    );
                }
?>
            <div class="tl2-goal-item-wrap tl2-filter-item" data-type="goal" data-rating="0">
                <!-- Milestone divider for goal items -->
                <div class="tl2-milestone">
                    <div class="tl2-milestone-line left"></div>
                    <div class="tl2-milestone-pill">
                        <span class="tl2-milestone-icon">🎯</span>
                        <span class="tl2-milestone-text">
                            <span class="tl2-milestone-label">Goal</span>
                            <span class="tl2-milestone-title"><?php echo esc_html(wp_trim_words($g_title, 6)); ?></span>
                        </span>
                    </div>
                    <div class="tl2-milestone-line right"></div>
                </div>

                <div class="tl2-goal-card"
                    onclick="openViewOffcanvas('goal', <?php echo $g_index; ?>)">
                    <div class="tl2-goal-card-hdr">
                        <div class="tl2-goal-card-title">
                            <?php echo esc_html($g_title); ?>
                        </div>
                        <div class="tl2-goal-progress-badge">
                            <?php echo esc_html($g_progress); ?>%
                        </div>
                    </div>
                    <?php if ($g_desc): ?>
                    <p class="tl2-goal-card-desc">
                        <?php echo esc_html(wp_trim_words($g_desc, 18)); ?>
                    </p>
                    <?php
                    endif; ?>
                    <?php if ($g_range): ?>
                    <div class="tl2-goal-card-meta">
                        <span class="tl2-badge tl2-badge-goal">
                            <?php echo esc_html($g_range); ?>
                        </span>
                    </div>
                    <?php
                    endif; ?>
                    <div class="tl2-goal-card-progress-bar">
                        <div class="tl2-goal-card-progress-fill"
                            style="width:<?php echo esc_attr(max(0, min(100, $g_progress))); ?>%"></div>
                    </div>
                    <div class="tl2-goal-card-actions">
                        <button type="button" class="tl2-inline-btn"
                            onclick="event.stopPropagation(); openViewOffcanvas('goal', <?php echo $g_index; ?>)">View</button>
                        <button type="button" class="tl2-inline-btn"
                            onclick="event.stopPropagation(); editGoal(<?php echo $g_index; ?>)">Edit</button>
                    </div>
                </div>
            </div>

            <?php
            elseif ($item['type'] === 'routine'):
                $routine = is_array($item['data']) ? $item['data'] : [];
                $r_index = intval($item['index']);
                $r_title = $routine['title'] ?? $routine['routine_title'] ?? 'Untitled Routine';
                $r_description = $routine['description'] ?? $routine['notes'] ?? '';
                $r_frequency = $routine['frequency'] ?? ($routine['routine_frequency'] ?? '');
                $r_time = $routine['routine_time'] ?? ($routine['time'] ?? '');
                $r_steps_raw = $routine['steps'] ?? [];
                if (is_string($r_steps_raw)) {
                    $r_decoded = json_decode($r_steps_raw, true);
                    $r_steps_raw = is_array($r_decoded) ? $r_decoded : preg_split('/\r\n|\r|\n|,/', $r_steps_raw);
                }
                $r_steps_count = is_array($r_steps_raw) ? count(array_filter(array_map(static function ($s) {
                    return trim((string)(is_array($s) ? ($s['text'] ?? '') : $s));
                }, $r_steps_raw))) : 0;
                $r_completed = in_array($r_index, $completed_today_routines, true);
                $r_meta = trim(
                    ($r_frequency ? $r_frequency : '') .
                    ($r_time ? ' · ' . $r_time : '') .
                    ($r_steps_count > 0 ? ' · ' . $r_steps_count . ' steps' : '')
                );
                $r_tl_date = date_i18n('M j, Y', intval($item['date']));
?>
            <div class="tl2-routine-tl-row tl2-filter-item" data-type="routine" data-rating="0"
                onclick="openViewOffcanvas('routine', <?php echo $r_index; ?>)">
                <div class="tl2-routine-tl-ico">🔄</div>
                <div class="tl2-routine-tl-body">
                    <div class="tl2-routine-tl-title">
                        <?php echo esc_html($r_title); ?>
                    </div>
                    <div class="tl2-routine-tl-meta">
                        <?php echo esc_html($r_tl_date . ($r_meta ? ' · ' . $r_meta : '')); ?>
                    </div>
                </div>
                <div class="tl2-routine-tl-actions">
                    <?php if ($r_completed): ?>
                    <span class="tl2-complete-indicator" title="Completed today">✓</span>
                    <?php
                endif; ?>
                    <button type="button" class="tl2-inline-btn"
                        onclick="event.stopPropagation(); openViewOffcanvas('routine', <?php echo $r_index; ?>)">View</button>
                    <button type="button" class="tl2-chk-btn <?php echo $r_completed ? 'is-complete' : ''; ?>"
                        data-routine-complete-toggle data-routine-id="<?php echo esc_attr($r_index); ?>"
                        data-date="<?php echo esc_attr($today_date); ?>"
                        aria-pressed="<?php echo $r_completed ? 'true' : 'false'; ?>"
                        onclick="event.stopPropagation(); toggleRoutineCompletion(<?php echo $r_index; ?>, '<?php echo esc_js($today_date); ?>', this)">
                        <?php echo $r_completed ? '✓ Done' : 'Mark done'; ?>
                    </button>
                </div>
            </div>

            <?php
            endif; ?>
            <?php
        endforeach; ?>
            </div><!-- /.tl2-nonentry-grid -->
            <?php endif; ?>

        </div><!-- .tl2-week -->

        <?php
    endforeach; ?>

    </div><!-- .tl2-timeline -->
    <?php
endif; ?>

</div>
