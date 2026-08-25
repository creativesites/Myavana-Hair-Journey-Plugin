<?php
/**
 * Enterprise-Grade Calendar View - Hair Journey
 * Month/Week/Day views with precise date/time positioning
 * Connectors point to calendar dates, not between entries
 * Mobile-first responsive design with MYAVANA luxury branding
 */

/**
 * Calendar View Partial
 *
 * This partial expects $shared_data to be passed from the main shortcode.
 * All data is fetched ONCE by Myavana_Data_Manager to avoid redundant queries.
 *
 * Available variables:
 * - $user_id, $hair_goals, $current_routine (from $shared_data)
 * - $entries (from $shared_data['entries'])
 */

// Use data from parent scope (already fetched in hair-journey.php)
if (!isset($user_id) || !$user_id) : ?>
    <div id="calendarView" class="view-content calendar-view-hjn">
        <div class="calendar-empty-hjn">
            <h2>Sign in to view your journey calendar</h2>
        </div>
    </div>
<?php
    return;
endif;

// Use entries from shared_data instead of re-querying
$entries = $shared_data['entries'];

// Calculate current calendar period
$current_year = date('Y');
$current_month = date('n');
$current_day = date('j');
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

// Month view calculations
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day_of_month);
$day_of_week = date('w', $first_day_of_month);
$month_name = date('F Y', $first_day_of_month);

// Start from Monday (adjust if month starts on Sunday = 0, make it 7)
$start_day = ($day_of_week == 0) ? 7 : $day_of_week;
$start_offset = $start_day - 1;

// Build calendar data structure
$calendar_data = [
    'entries' => [],
    'goals' => [],
    'routines' => [],
    'routine_records' => [],
    'routine_summary' => [],
    'routine_notifications' => [],
    'today' => current_time('Y-m-d'),
    'routes' => [
        'routines' => home_url('/routines/'),
        'profile' => home_url('/profile/'),
    ],
];

$routine_tracking = function_exists('myavana_get_routine_tracking_context')
    ? myavana_get_routine_tracking_context($user_id, (array) $current_routine)
    : [];
$routine_tracking_by_id = !empty($routine_tracking['routines_by_id']) && is_array($routine_tracking['routines_by_id'])
    ? $routine_tracking['routines_by_id']
    : [];
$calendar_data['routine_records'] = !empty($routine_tracking['records']) && is_array($routine_tracking['records'])
    ? $routine_tracking['records']
    : [];
$calendar_data['routine_summary'] = !empty($routine_tracking['summary']) && is_array($routine_tracking['summary'])
    ? $routine_tracking['summary']
    : [];
$calendar_data['routine_notifications'] = !empty($routine_tracking['notifications']) && is_array($routine_tracking['notifications'])
    ? $routine_tracking['notifications']
    : [];

$resolve_entry_thumbnail = static function(int $post_id): string {
    $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');

    if (!$thumbnail) {
        $featured_image_index = get_post_meta($post_id, 'featured_image_index', true);
        $gallery_images = get_post_meta($post_id, '_entry_gallery', true);

        if (is_array($gallery_images) && !empty($gallery_images)) {
            $index = ($featured_image_index !== '') ? intval($featured_image_index) : 0;
            if (isset($gallery_images[$index])) {
                $thumbnail = wp_get_attachment_image_url($gallery_images[$index], 'medium');
            } else {
                $thumbnail = wp_get_attachment_image_url($gallery_images[0], 'medium');
            }
        }
    }

    return $thumbnail ?: '';
};

// Process entries for calendar
foreach ($entries as $entry) {
    $post_id = $entry->ID;
    $entry_date = get_the_date('Y-m-d', $post_id);
    $entry_time = get_the_date('H:i', $post_id);
    $entry_hour = intval(get_the_date('H', $post_id));

    $calendar_data['entries'][] = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'content' => wp_strip_all_tags($entry->post_content),
        'date' => $entry_date,
        'time' => $entry_time,
        'hour' => $entry_hour,
        'day' => intval(date('j', strtotime($entry_date))),
        'month' => intval(date('n', strtotime($entry_date))),
        'year' => intval(date('Y', strtotime($entry_date))),
        'thumbnail' => $resolve_entry_thumbnail($post_id),
        'rating' => get_post_meta($post_id, 'health_rating', true),
        'mood' => get_post_meta($post_id, 'mood_demeanor', true),
        'products' => get_post_meta($post_id, 'products_used', true),
    ];
}

// Process goals for calendar
foreach ($hair_goals as $idx => $goal) {
    $start_date = $goal['start_date'] ?? $goal['start'] ?? '';
    if ($start_date === '') {
        continue;
    }

    $end_date = $goal['target_date'] ?? $goal['end_date'] ?? ($goal['end'] ?? '');
    $start_timestamp = strtotime($start_date);
    $end_timestamp = $end_date ? strtotime($end_date) : null;

    $calendar_data['goals'][] = [
        'id' => $idx,
        'title' => $goal['title'] ?? $goal['goal_title'] ?? 'Untitled Goal',
        'description' => $goal['description'] ?? $goal['notes'] ?? '',
        'start_date' => $start_date,
        'end_date' => $end_date,
        'start_day' => $start_timestamp ? intval(date('j', $start_timestamp)) : null,
        'start_month' => $start_timestamp ? intval(date('n', $start_timestamp)) : null,
        'start_year' => $start_timestamp ? intval(date('Y', $start_timestamp)) : null,
        'end_day' => $end_timestamp ? intval(date('j', $end_timestamp)) : null,
        'end_month' => $end_timestamp ? intval(date('n', $end_timestamp)) : null,
        'end_year' => $end_timestamp ? intval(date('Y', $end_timestamp)) : null,
        'progress' => isset($goal['progress']) ? intval($goal['progress']) : (isset($goal['progress_percent']) ? intval($goal['progress_percent']) : 0),
    ];
}

// Process routines for calendar with shared tracking metadata
foreach ((array) $current_routine as $idx => $routine) {
    $routine_title = $routine['name'] ?? $routine['title'] ?? $routine['routine_title'] ?? 'Routine';
    $routine_time = function_exists('myavana_normalize_routine_time')
        ? myavana_normalize_routine_time($routine)
        : ($routine['time_of_day'] ?? $routine['time'] ?? $routine['routine_time'] ?? '08:00');
    $routine_frequency = function_exists('myavana_normalize_routine_frequency')
        ? myavana_normalize_routine_frequency($routine['frequency'] ?? $routine['routine_frequency'] ?? 'weekly')
        : ($routine['frequency'] ?? $routine['routine_frequency'] ?? 'weekly');
    $created_date = function_exists('myavana_get_routine_created_date')
        ? myavana_get_routine_created_date($routine, current_time('Y-m-d'))
        : ($routine['created_at'] ?? $routine['created_on'] ?? $routine['date_created'] ?? ($routine['start_date'] ?? ''));
    $tracking = $routine_tracking_by_id[$idx] ?? [];

    $hour = 8;
    if (!empty($routine_time) && preg_match('/(\d{1,2})/', (string) $routine_time, $time_match)) {
        $parsed_hour = intval($time_match[1]);
        if ($parsed_hour >= 0 && $parsed_hour <= 23) {
            $hour = $parsed_hour;
        }
    }

    $steps = $routine['products'] ?? $routine['steps'] ?? $routine['routine_steps'] ?? [];
    $calendar_data['routines'][] = [
        'id' => $idx,
        'title' => $routine_title,
        'time' => $routine_time,
        'hour' => $hour,
        'frequency' => $routine_frequency,
        'steps' => $steps,
        'description' => $routine['description'] ?? ($routine['notes'] ?? ''),
        'products' => $steps,
        'created_date' => $created_date,
        'duration' => $routine['duration'] ?? ($routine['routine_duration'] ?? ''),
        'goal_link' => $routine['routine_goal_link'] ?? '',
        'phase' => $routine['routine_phase'] ?? '',
        'status' => $routine['status'] ?? 'active',
        'routine_reminder_days' => $routine['routine_reminder_days'] ?? '',
        'routine_auto_track' => $routine['routine_auto_track'] ?? 0,
        'schedule_days' => $tracking['schedule_days'] ?? [],
        'today_status' => $tracking['today_status'] ?? '',
        'due_today' => !empty($tracking['due_today']),
        'overdue' => !empty($tracking['overdue']),
        'last_due_date' => $tracking['last_due_date'] ?? '',
        'next_due_date' => $tracking['next_due_date'] ?? '',
        'last_completed' => $tracking['last_completed'] ?? '',
        'completion_rate_30d' => intval($tracking['completion_rate_30d'] ?? 0),
        'current_streak' => intval($tracking['current_streak'] ?? 0),
        'completed_dates' => $tracking['completed_dates'] ?? [],
    ];
}

$routine_matches_day = static function(array $routine, string $date_str): bool {
    return function_exists('myavana_routine_matches_date')
        ? myavana_routine_matches_date($routine, $date_str)
        : false;
};

// Check if we have any data
$has_data = !empty($calendar_data['entries']) || !empty($calendar_data['goals']) || !empty($calendar_data['routines']);
?>

<!-- Calendar View -->
<div id="calendarView" class="view-content calendar-view-hjn">

    <!-- Calendar Controls -->
    <div class="calendar-controls-hjn">
        <div class="calendar-view-toggles-hjn">
            <button class="calendar-view-toggle-hjn active" data-view="month" onclick="switchCalendarView('month')">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M19,4H18V2H16V4H8V2H6V4H5A2,2 0 0,0 3,6V20A2,2 0 0,0 5,22H19A2,2 0 0,0 21,20V6A2,2 0 0,0 19,4M19,20H5V10H19V20Z"/>
                </svg>
                Month
            </button>
            <button class="calendar-view-toggle-hjn" data-view="week" onclick="switchCalendarView('week')">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M6,1H8V3H16V1H18V3H19A2,2 0 0,1 21,5V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5C3,3.89 3.89,3 5,3H6V1M5,8V19H19V8H5M7,10H17V12H7V10Z"/>
                </svg>
                Week
            </button>
            <button class="calendar-view-toggle-hjn" data-view="day" onclick="switchCalendarView('day')">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M7,1H9V3H15V1H17V3H18A2,2 0 0,1 20,5V19A2,2 0 0,1 18,21H6C4.89,21 4,20.1 4,19V5A2,2 0 0,1 6,3H7V1M6,8V19H18V8H6M13,10H16V13H13V10Z"/>
                </svg>
                Day
            </button>
        </div>

        <div class="calendar-date-navigation-hjn">
            <button class="calendar-nav-btn-hjn" onclick="navigateCalendar('prev')">
                <svg viewBox="0 0 24 24" width="18" height="18">
                    <path fill="currentColor" d="M15.41,16.58L10.83,12L15.41,7.41L14,6L8,12L14,18L15.41,16.58Z"/>
                </svg>
            </button>
            <div class="calendar-date-range-hjn" id="calendarDateRange"><?php echo esc_html($month_name); ?></div>
            <button class="calendar-nav-btn-hjn" onclick="navigateCalendar('next')">
                <svg viewBox="0 0 24 24" width="18" height="18">
                    <path fill="currentColor" d="M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z"/>
                </svg>
            </button>
            <button class="calendar-today-btn-hjn" onclick="navigateCalendar('today')">Today</button>
        </div>

        <div class="calendar-action-btns-hjn">
            <button class="calendar-filter-btn-hjn" onclick="toggleCalendarFilters()">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M14,12V19.88C14.04,20.18 13.94,20.5 13.71,20.71C13.32,21.1 12.69,21.1 12.3,20.71L10.29,18.7C10.06,18.47 9.96,18.16 10,17.87V12H9.97L4.21,4.62C3.87,4.19 3.95,3.56 4.38,3.22C4.57,3.08 4.78,3 5,3V3H19V3C19.22,3 19.43,3.08 19.62,3.22C20.05,3.56 20.13,4.19 19.79,4.62L14.03,12H14Z"/>
                </svg>
                Filter
            </button>
            <!-- <button class="calendar-add-btn-hjn" onclick="openOffcanvas('entry')">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                </svg>
                Add Entry
            </button> -->
        </div>
    </div>

    <section class="calendar-routine-planner-hjn" id="calendarRoutinePlanner">
        <div class="calendar-routine-planner-head-hjn">
            <div>
                <span class="calendar-routine-planner-kicker-hjn">Routine Planner</span>
                <h3>Track your routine cadence from the calendar</h3>
                <p>Mark routines done, snooze reminders, and keep your streak visible without leaving the planner.</p>
            </div>
            <div class="calendar-routine-planner-links-hjn">
                <a href="<?php echo esc_url(home_url('/routines/')); ?>" class="calendar-routine-link-hjn">Manage routines</a>
                <a href="<?php echo esc_url(home_url('/profile/')); ?>" class="calendar-routine-link-hjn ghost">View profile</a>
            </div>
        </div>
        <div class="calendar-routine-summary-grid-hjn" id="calendarRoutineSummary"></div>
        <div class="calendar-routine-notifications-hjn" id="calendarRoutineNotifications"></div>
    </section>

    <!-- Filter & Search Panel -->
    <div class="calendar-filters-panel-hjn" id="calendarFiltersPanel" style="display: none;">
        <div class="filters-panel-content-hjn">
            <!-- Search Input -->
            <div class="filter-section-hjn">
                <label class="filter-label-hjn">
                    <svg viewBox="0 0 24 24" width="16" height="16">
                        <path fill="currentColor" d="M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z"/>
                    </svg>
                    Search
                </label>
                <input
                    type="search"
                    id="calendarSearchInput"
                    class="filter-search-input-hjn"
                    placeholder="Search entries, goals, routines..."
                    oninput="applyCalendarFilters()"
                >
            </div>

            <!-- Type Filter -->
            <div class="filter-section-hjn">
                <label class="filter-label-hjn">
                    <svg viewBox="0 0 24 24" width="16" height="16">
                        <path fill="currentColor" d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4M11,17V16H9V14H13V13H10A1,1 0 0,1 9,12V9A1,1 0 0,1 10,8H14V9H12V11H14V12H15V14A1,1 0 0,1 14,15H11V17H13V19H11V17Z"/>
                    </svg>
                    Type
                </label>
                <div class="filter-checkbox-group-hjn">
                    <label class="filter-checkbox-hjn">
                        <input type="checkbox" id="filterEntries" checked onchange="applyCalendarFilters()">
                        <span>Entries</span>
                    </label>
                    <label class="filter-checkbox-hjn">
                        <input type="checkbox" id="filterGoals" checked onchange="applyCalendarFilters()">
                        <span>Goals</span>
                    </label>
                    <label class="filter-checkbox-hjn">
                        <input type="checkbox" id="filterRoutines" checked onchange="applyCalendarFilters()">
                        <span>Routines</span>
                    </label>
                </div>
            </div>

            <!-- Rating Filter (Entries Only) -->
            <div class="filter-section-hjn">
                <label class="filter-label-hjn">
                    <svg viewBox="0 0 24 24" width="16" height="16">
                        <path fill="currentColor" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"/>
                    </svg>
                    Minimum Rating
                </label>
                <select id="filterRating" class="filter-select-hjn" onchange="applyCalendarFilters()">
                    <option value="0">All Ratings</option>
                    <option value="5">5 Stars</option>
                    <option value="4">4+ Stars</option>
                    <option value="3">3+ Stars</option>
                    <option value="2">2+ Stars</option>
                    <option value="1">1+ Stars</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="filter-actions-hjn">
                <button class="filter-clear-btn-hjn" onclick="clearCalendarFilters()">
                    <svg viewBox="0 0 24 24" width="16" height="16">
                        <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                    </svg>
                    Clear Filters
                </button>
                <button class="filter-apply-btn-hjn" onclick="toggleCalendarFilters()">
                    Close
                </button>
            </div>
        </div>
    </div>

    <?php if (!$has_data): ?>
        <!-- Empty State -->
        <div class="calendar-empty-state-hjn">
            <svg viewBox="0 0 24 24" width="64" height="64">
                <path fill="currentColor" d="M19,4H18V2H16V4H8V2H6V4H5A2,2 0 0,0 3,6V20A2,2 0 0,0 5,22H19A2,2 0 0,0 21,20V6A2,2 0 0,0 19,4M19,20H5V10H19V20Z"/>
            </svg>
            <h3>Your Calendar is Empty</h3>
            <p>Start tracking your hair journey by adding your first entry</p>
            <button class="calendar-cta-btn-hjn" onclick="openOffcanvas('entry')">
                Add Your First Entry
            </button>
        </div>
    <?php else: ?>

        <!-- Month View -->
        <div id="monthViewHjn" class="calendar-month-view-hjn active">
            <!-- Month Grid (Desktop) -->
            <div class="calendar-month-grid-hjn" style="position: relative;">
                <!-- Day Headers -->
                <div class="calendar-day-headers-hjn">
                    <div class="calendar-day-header-hjn">Mon</div>
                    <div class="calendar-day-header-hjn">Tue</div>
                    <div class="calendar-day-header-hjn">Wed</div>
                    <div class="calendar-day-header-hjn">Thu</div>
                    <div class="calendar-day-header-hjn">Fri</div>
                    <div class="calendar-day-header-hjn">Sat</div>
                    <div class="calendar-day-header-hjn">Sun</div>
                </div>

                <!-- Calendar Days Grid -->
                <div class="calendar-days-grid-hjn">
                    <?php
                    // Empty cells for days before month starts
                    for ($i = 0; $i < $start_offset; $i++):
                    ?>
                        <div class="calendar-day-cell-hjn calendar-day-empty-hjn"></div>
                    <?php endfor; ?>

                    <?php
                    // Days of the month
                    for ($day = 1; $day <= $days_in_month; $day++):
                        $is_today = ($day == $current_day && $current_month == date('n') && $current_year == date('Y'));
                        $date_str = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);

                        // Get entries for this day
                        $day_entries = array_filter($calendar_data['entries'], function($entry) use ($day, $current_month, $current_year) {
                            return $entry['day'] == $day && $entry['month'] == $current_month && $entry['year'] == $current_year;
                        });

                        // Get goals for this day
                        $day_goals = array_filter($calendar_data['goals'], function($goal) use ($day, $current_month, $current_year) {
                            $is_in_range = false;
                            $start_timestamp = strtotime($goal['start_date']);
                            $end_timestamp = $goal['end_date'] ? strtotime($goal['end_date']) : $start_timestamp;
                            $day_timestamp = strtotime(sprintf('%04d-%02d-%02d', $current_year, $current_month, $day));

                            return $day_timestamp >= $start_timestamp && $day_timestamp <= $end_timestamp;
                        });

                        // Get routines scheduled for this date.
                        $day_routines = array_values(array_filter($calendar_data['routines'], static function($routine) use ($routine_matches_day, $date_str) {
                            return $routine_matches_day((array) $routine, $date_str);
                        }));

                        $has_content = !empty($day_entries) || !empty($day_goals) || !empty($day_routines);
                    ?>
                        <div class="calendar-day-cell-hjn <?php echo $is_today ? 'calendar-day-today-hjn' : ''; ?> <?php echo $has_content ? 'calendar-day-has-content-hjn' : ''; ?>"
                             data-date="<?php echo esc_attr($date_str); ?>"
                             onclick="openCalendarDayDetail('<?php echo esc_attr($date_str); ?>')">
                            <div class="calendar-day-number-hjn"><?php echo $day; ?></div>

                            <?php if ($has_content): ?>
                                <div class="calendar-day-indicators-hjn">
                                    <?php if (!empty($day_entries)): ?>
                                        <div class="calendar-day-indicator-hjn calendar-indicator-entry-hjn" title="<?php echo count($day_entries); ?> entries">
                                            <?php echo count($day_entries); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($day_goals)): ?>
                                        <div class="calendar-day-indicator-hjn calendar-indicator-goal-hjn" title="<?php echo count($day_goals); ?> goals">
                                            <?php echo count($day_goals); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($day_routines)): ?>
                                        <div class="calendar-day-indicator-hjn calendar-indicator-routine-hjn" title="<?php echo count($day_routines); ?> routines">
                                            <?php echo count($day_routines); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="calendar-day-focus-hjn">
                                    <?php if (!empty($day_goals)): $day_goal = reset($day_goals); ?>
                                        <div class="calendar-day-focus-chip-hjn is-goal" title="<?php echo esc_attr($day_goal['title']); ?>">
                                            <span class="chip-icon-hjn">🎯</span>
                                            <span class="chip-text-hjn"><?php echo esc_html(wp_trim_words($day_goal['title'], 2)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($day_routines)): $day_routine = reset($day_routines); ?>
                                        <div class="calendar-day-focus-chip-hjn is-routine" title="<?php echo esc_attr($day_routine['title']); ?>">
                                            <span class="chip-icon-hjn"><?php echo intval($day_routine['hour']) < 12 ? '☀️' : '🌙'; ?></span>
                                            <span class="chip-text-hjn"><?php echo esc_html(wp_trim_words($day_routine['title'], 2)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (count($day_goals) > 1 || count($day_routines) > 1): ?>
                                        <div class="calendar-day-focus-chip-hjn is-more">
                                            +<?php echo intval(max(0, count($day_goals) - 1) + max(0, count($day_routines) - 1)); ?> more
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Mini previews for entries -->
                                <?php
                                $preview_entries = array_slice($day_entries, 0, 2);
                                foreach ($preview_entries as $entry):
                                ?>
                                    <div class="calendar-day-entry-preview-hjn"
                                         onclick="event.stopPropagation(); openViewOffcanvas('entry', <?php echo $entry['id']; ?>)">
                                        <?php if (!empty($entry['thumbnail'])): ?>
                                            <span class="calendar-entry-thumb-hjn" style="background-image: url('<?php echo esc_url($entry['thumbnail']); ?>');"></span>
                                        <?php else: ?>
                                            <span class="calendar-entry-thumb-hjn is-fallback">📸</span>
                                        <?php endif; ?>
                                        <span class="calendar-entry-preview-body-hjn">
                                            <span class="calendar-entry-time-hjn"><?php echo esc_html($entry['time']); ?></span>
                                            <span class="calendar-entry-title-hjn"><?php echo esc_html(wp_trim_words($entry['title'], 3)); ?></span>
                                        </span>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($day_entries) > 2): ?>
                                    <div class="calendar-day-more-hjn">
                                        +<?php echo count($day_entries) - 2; ?> more
                                    </div>
                                <?php endif; ?>
                                
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>

                
            </div>

            <!-- Mobile Month List View -->
            <div class="calendar-month-list-hjn">
                <?php
                // Reset day counter for list view
                $day = 1;
                for ($day = 1; $day <= $days_in_month; $day++):
                    $is_today = ($day == $current_day && $current_month == date('n') && $current_year == date('Y'));
                    $date_str = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);

                    // Get entries for this day
                    $day_entries = array_filter($calendar_data['entries'], function($entry) use ($day, $current_month, $current_year) {
                        return $entry['day'] == $day && $entry['month'] == $current_month && $entry['year'] == $current_year;
                    });

                    // Get goals for this day
                    $day_goals = array_filter($calendar_data['goals'], function($goal) use ($day, $current_month, $current_year) {
                        $is_in_range = false;
                        $start_timestamp = strtotime($goal['start_date']);
                        $end_timestamp = $goal['end_date'] ? strtotime($goal['end_date']) : $start_timestamp;
                        $day_timestamp = strtotime(sprintf('%04d-%02d-%02d', $current_year, $current_month, $day));

                        return $day_timestamp >= $start_timestamp && $day_timestamp <= $end_timestamp;
                    });

                    // Get routines scheduled for this date.
                    $day_routines = array_values(array_filter($calendar_data['routines'], static function($routine) use ($routine_matches_day, $date_str) {
                        return $routine_matches_day((array) $routine, $date_str);
                    }));

                    $has_content = !empty($day_entries) || !empty($day_goals) || !empty($day_routines);
                ?>
                    <div class="calendar-day-list-item-hjn <?php echo $is_today ? 'calendar-day-today-hjn' : ''; ?> <?php echo $has_content ? 'calendar-day-has-content-hjn' : ''; ?>"
                         data-date="<?php echo esc_attr($date_str); ?>"
                         onclick="openCalendarDayDetail('<?php echo esc_attr($date_str); ?>')">
                        <div class="calendar-day-list-header-hjn">
                            <div class="calendar-day-list-date-hjn">
                                <div class="calendar-day-list-day-hjn"><?php echo $day; ?></div>
                                <div class="calendar-day-list-weekday-hjn"><?php echo date('D', strtotime($date_str)); ?></div>
                            </div>
                            <?php if ($has_content): ?>
                                <div class="calendar-day-list-indicators-hjn">
                                    <?php if (!empty($day_entries)): ?>
                                        <div class="calendar-day-indicator-hjn calendar-indicator-entry-hjn">
                                            <?php echo count($day_entries); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($day_goals)): ?>
                                        <div class="calendar-day-indicator-hjn calendar-indicator-goal-hjn">
                                            <?php echo count($day_goals); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($day_routines)): ?>
                                        <div class="calendar-day-indicator-hjn calendar-indicator-routine-hjn">
                                            <?php echo count($day_routines); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_content): ?>
                            <div class="calendar-day-list-content-hjn">
                                <?php if (!empty($day_goals)): ?>
                                    <?php foreach (array_slice($day_goals, 0, 2) as $goal): ?>
                                        <div class="calendar-list-goal-hjn">
                                            <div class="goal-list-title-hjn"><?php echo esc_html($goal['title']); ?></div>
                                            <div class="goal-list-progress-hjn">
                                                <div class="goal-bar-fill-hjn" style="width: <?php echo $goal['progress']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($day_goals) > 2): ?>
                                        <div class="calendar-list-more-hjn">+<?php echo count($day_goals) - 2; ?> more goals</div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($day_routines)): ?>
                                    <div class="calendar-list-routines-hjn">
                                        <?php foreach (array_slice($day_routines, 0, 2) as $routine): ?>
                                            <div class="calendar-list-routine-hjn">
                                                <div class="routine-list-icon-hjn">
                                                    <?php echo ($routine['hour'] < 12) ? '☀️' : '🌙'; ?>
                                                </div>
                                                <div class="routine-list-content-hjn">
                                                    <div class="routine-list-title-hjn"><?php echo esc_html($routine['title']); ?></div>
                                                    <div class="routine-list-time-hjn"><?php echo esc_html($routine['time']); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($day_routines) > 2): ?>
                                        <div class="calendar-list-more-hjn">+<?php echo count($day_routines) - 2; ?> more routines</div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php
                                $preview_entries = array_slice($day_entries, 0, 3);
                                foreach ($preview_entries as $entry):
                                ?>
                                    <div class="calendar-list-entry-hjn">
                                        <?php if (!empty($entry['thumbnail'])): ?>
                                            <span class="entry-list-thumb-hjn" style="background-image: url('<?php echo esc_url($entry['thumbnail']); ?>');"></span>
                                        <?php else: ?>
                                            <span class="entry-list-thumb-hjn is-fallback">📸</span>
                                        <?php endif; ?>
                                        <div class="entry-list-body-hjn">
                                            <div class="entry-list-time-hjn"><?php echo esc_html($entry['time']); ?></div>
                                            <div class="entry-list-title-hjn"><?php echo esc_html($entry['title']); ?></div>
                                            <?php if ($entry['mood']): ?>
                                                <div class="entry-list-mood-hjn"><?php echo esc_html($entry['mood']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($day_entries) > 3): ?>
                                    <div class="calendar-list-more-hjn">
                                        +<?php echo count($day_entries) - 3; ?> more entries
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Week View -->
        <div id="weekViewHjn" class="calendar-week-view-hjn">
            <!-- Week Grid (Desktop) -->
            <div class="calendar-week-container-hjn">
                <!-- Time Column -->
                <div class="calendar-time-column-hjn">
                    <div class="calendar-time-header-hjn"></div>
                    <?php
                    // Calculate which hours have content for smart compression
                    $week_start_ts = strtotime($current_week_start);
                    $week_end_ts = strtotime('+6 days', $week_start_ts);
                    $hours_with_content = [];

                    // Check entries
                    foreach ($calendar_data['entries'] as $entry) {
                        $entry_ts = strtotime($entry['date']);
                        if ($entry_ts >= $week_start_ts && $entry_ts <= $week_end_ts) {
                            $hours_with_content[$entry['hour']] = true;
                        }
                    }

                    // Check routines
                    foreach ($calendar_data['routines'] as $routine) {
                        $hours_with_content[$routine['hour']] = true;
                    }

                    // Expand range to include hours around content (for context)
                    $expanded_hours = [];
                    foreach ($hours_with_content as $hour => $val) {
                        $expanded_hours[$hour] = true;
                        if ($hour > 0) $expanded_hours[$hour - 1] = true;
                        if ($hour < 23) $expanded_hours[$hour + 1] = true;
                    }

                    // If no content, show working hours (8am-8pm)
                    if (empty($expanded_hours)) {
                        for ($h = 8; $h <= 20; $h++) {
                            $expanded_hours[$h] = true;
                        }
                    }

                    // Render time slots
                    $prev_hour = -1;
                    for ($hour = 0; $hour < 24; $hour++):
                        if (isset($expanded_hours[$hour])):
                            // Show gap indicator if there's a jump
                            if ($prev_hour >= 0 && $hour > $prev_hour + 1):
                                $gap_hours = $hour - $prev_hour - 1;
                    ?>
                                <div class="calendar-time-gap-hjn">
                                    <span class="gap-text"><?php echo $gap_hours; ?> hours hidden</span>
                                </div>
                    <?php   endif; ?>
                            <div class="calendar-time-slot-hjn" data-hour="<?php echo $hour; ?>">
                                <?php echo sprintf('%02d:00', $hour); ?>
                            </div>
                    <?php
                            $prev_hour = $hour;
                        endif;
                    endfor;
                    ?>
                </div>

                <!-- Week Days Columns -->
                <div class="calendar-week-days-hjn">
                    <!-- Day Headers -->
                    <div class="calendar-week-headers-hjn">
                        <?php
                        $week_start = strtotime($current_week_start);
                        for ($i = 0; $i < 7; $i++):
                            $day_timestamp = strtotime("+$i days", $week_start);
                            $day_name = date('D', $day_timestamp);
                            $day_number = date('j', $day_timestamp);
                            $is_today = date('Y-m-d', $day_timestamp) == date('Y-m-d');
                        ?>
                            <div class="calendar-week-day-header-hjn <?php echo $is_today ? 'calendar-week-today-hjn' : ''; ?>">
                                <div class="calendar-week-day-name-hjn"><?php echo $day_name; ?></div>
                                <div class="calendar-week-day-number-hjn"><?php echo $day_number; ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Week Grid -->
                    <div class="calendar-week-grid-hjn" style="position: relative;">
                        <?php
                        // Render Goal Bars Spanning Week (positioned above entries)
                        $week_end = strtotime('+6 days', $week_start);
                        foreach ($calendar_data['goals'] as $goal):
                            $goal_start = strtotime($goal['start_date']);
                            $goal_end = $goal['end_date'] ? strtotime($goal['end_date']) : $goal_start;

                            // Check if goal overlaps with this week
                            if ($goal_end >= $week_start && $goal_start <= $week_end):
                                // Calculate which days this goal spans in the current week
                                $span_start_day = max(0, ceil(($goal_start - $week_start) / 86400));
                                $span_end_day = min(6, floor(($goal_end - $week_start) / 86400));
                                $span_days = $span_end_day - $span_start_day + 1;

                                // Position: percentage based on 7 columns
                                $left_percent = ($span_start_day / 7) * 100;
                                $width_percent = ($span_days / 7) * 100;
                        ?>
                            <div class="calendar-week-goal-bar-hjn"
                                 style="left: <?php echo $left_percent; ?>%; width: <?php echo $width_percent; ?>%; top: 10px;"
                                 onclick="openViewOffcanvas('goal', <?php echo $goal['id']; ?>)">
                                <div class="goal-bar-content-hjn">
                                    <div class="goal-bar-title-hjn"><?php echo esc_html($goal['title']); ?></div>
                                    <div class="goal-bar-progress-hjn">
                                        <div class="goal-bar-fill-hjn" style="width: <?php echo $goal['progress']; ?>%"></div>
                                    </div>
                                    <div class="goal-bar-meta-hjn"><?php echo $goal['progress']; ?>%</div>
                                </div>
                            </div>
                        <?php
                            endif;
                        endforeach;
                        ?>

                        <?php for ($i = 0; $i < 7; $i++):
                            $day_timestamp = strtotime("+$i days", $week_start);
                            $day_date = date('Y-m-d', $day_timestamp);
                        ?>
                            <div class="calendar-week-day-column-hjn" data-date="<?php echo esc_attr($day_date); ?>">
                                <?php
                                // Get entries for this day
                                $day_entries = array_filter($calendar_data['entries'], function($entry) use ($day_date) {
                                    return $entry['date'] == $day_date;
                                });

                                // Render entries at their specific times
                                foreach ($day_entries as $entry):
                                    $hour_position = $entry['hour'] * 60; // 60px per hour
                                    $minute = intval(date('i', strtotime($entry['time'])));
                                    $minute_offset = $minute; // 1px per minute
                                    $top_position = $hour_position + $minute_offset;
                                ?>
                                    <div class="calendar-week-entry-hjn"
                                         style="top: <?php echo $top_position; ?>px;"
                                         onclick="openViewOffcanvas('entry', <?php echo $entry['id']; ?>)">
                                        <div class="calendar-week-entry-time-hjn"><?php echo esc_html($entry['time']); ?></div>
                                        <div class="calendar-week-entry-title-hjn"><?php echo esc_html($entry['title']); ?></div>
                                        <?php if ($entry['mood']): ?>
                                            <div class="calendar-week-entry-mood-hjn"><?php echo esc_html($entry['mood']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Mobile Week List View -->
            <div class="calendar-week-list-hjn">
                <?php
                // Calculate week range for mobile list
                $date = new DateTime();
                $dayOfWeek = $date->format('w');
                $diff = $date->format('j') - $dayOfWeek + ($dayOfWeek == 0 ? -6 : 1);
                $monday = clone $date;
                $monday->setDate($date->format('Y'), $date->format('m'), $diff);

                for ($i = 0; $i < 7; $i++):
                    $dayDate = clone $monday;
                    $dayDate->modify("+$i days");
                    $dateStr = $dayDate->format('Y-m-d');
                    $isToday = $dayDate->format('Y-m-d') == date('Y-m-d');
                    $weekday = $dayDate->format('D');
                    $dayNum = $dayDate->format('j');

                    // Get data for this day
                    $dayEntries = array_filter($calendar_data['entries'], function($entry) use ($dateStr) {
                        return $entry['date'] == $dateStr;
                    });
                    $dayGoals = array_filter($calendar_data['goals'], function($goal) use ($dateStr) {
                        $dayTs = strtotime($dateStr);
                        $startTs = strtotime($goal['start_date']);
                        $endTs = $goal['end_date'] ? strtotime($goal['end_date']) : $startTs;
                        return $dayTs >= $startTs && $dayTs <= $endTs;
                    });
                    $routines = $calendar_data['routines'] ?? [];
                    $dayRoutines = array_values(array_filter($routines, static function($routine) use ($routine_matches_day, $dateStr) {
                        return $routine_matches_day((array) $routine, $dateStr);
                    }));

                    $hasContent = !empty($dayEntries) || !empty($dayGoals) || !empty($dayRoutines);
                    if (!$hasContent) continue; // Skip empty days in mobile list
                ?>
                    <div class="calendar-week-list-item-hjn <?php echo $isToday ? 'calendar-week-today-hjn' : ''; ?>"
                         data-date="<?php echo esc_attr($dateStr); ?>"
                         onclick="openCalendarDayDetail('<?php echo esc_attr($dateStr); ?>')">
                        <div class="calendar-week-list-header-hjn">
                            <div class="calendar-week-list-date-hjn">
                                <div class="calendar-week-list-day-hjn"><?php echo $dayNum; ?></div>
                                <div class="calendar-week-list-weekday-hjn"><?php echo $weekday; ?></div>
                            </div>
                            <div class="calendar-week-list-indicators-hjn">
                                <?php if (!empty($dayEntries)): ?>
                                    <div class="calendar-day-indicator-hjn calendar-indicator-entry-hjn"><?php echo count($dayEntries); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($dayGoals)): ?>
                                    <div class="calendar-day-indicator-hjn calendar-indicator-goal-hjn"><?php echo count($dayGoals); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($dayRoutines)): ?>
                                    <div class="calendar-day-indicator-hjn calendar-indicator-routine-hjn"><?php echo count($dayRoutines); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="calendar-week-list-content-hjn">
                            <?php if (!empty($dayGoals)): ?>
                                <?php foreach ($dayGoals as $goal): ?>
                                    <div class="calendar-list-goal-hjn">
                                        <div class="goal-list-title-hjn"><?php echo esc_html($goal['title']); ?></div>
                                        <div class="goal-list-progress-hjn">
                                            <div class="goal-bar-fill-hjn" style="width: <?php echo $goal['progress']; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (!empty($dayRoutines)): ?>
                                <div class="calendar-list-routines-hjn">
                                    <?php foreach ($dayRoutines as $routine): ?>
                                        <div class="calendar-list-routine-hjn">
                                            <div class="routine-list-icon-hjn">
                                                <?php echo ($routine['hour'] < 12) ? '☀️' : '🌙'; ?>
                                            </div>
                                            <div class="routine-list-content-hjn">
                                                <div class="routine-list-title-hjn"><?php echo esc_html($routine['title']); ?></div>
                                                <div class="routine-list-time-hjn"><?php echo esc_html($routine['time']); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            $previewEntries = array_slice($dayEntries, 0, 3);
                            foreach ($previewEntries as $entry):
                            ?>
                                <div class="calendar-list-entry-hjn">
                                    <?php if (!empty($entry['thumbnail'])): ?>
                                        <span class="entry-list-thumb-hjn" style="background-image: url('<?php echo esc_url($entry['thumbnail']); ?>');"></span>
                                    <?php else: ?>
                                        <span class="entry-list-thumb-hjn is-fallback">📸</span>
                                    <?php endif; ?>
                                    <div class="entry-list-body-hjn">
                                        <div class="entry-list-time-hjn"><?php echo esc_html($entry['time']); ?></div>
                                        <div class="entry-list-title-hjn"><?php echo esc_html($entry['title']); ?></div>
                                        <?php if ($entry['mood']): ?>
                                            <div class="entry-list-mood-hjn"><?php echo esc_html($entry['mood']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($dayEntries) > 3): ?>
                                <div class="calendar-list-more-hjn">+<?php echo count($dayEntries) - 3; ?> more entries</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Day View -->
        <div id="dayViewHjn" class="calendar-day-view-hjn">
            <div class="calendar-day-container-hjn">
                <!-- Time Column -->
                <div class="calendar-time-column-hjn">
                    <?php
                    // Calculate which hours have content for smart compression (day view)
                    $day_hours_with_content = [];
                    $today_date = date('Y-m-d');

                    // Check entries for today
                    foreach ($calendar_data['entries'] as $entry) {
                        if ($entry['date'] == $today_date) {
                            $day_hours_with_content[$entry['hour']] = true;
                        }
                    }

                    // Check routines
                    foreach ($calendar_data['routines'] as $routine) {
                        $day_hours_with_content[$routine['hour']] = true;
                    }

                    // Expand range to include hours around content
                    $day_expanded_hours = [];
                    foreach ($day_hours_with_content as $hour => $val) {
                        $day_expanded_hours[$hour] = true;
                        if ($hour > 0) $day_expanded_hours[$hour - 1] = true;
                        if ($hour < 23) $day_expanded_hours[$hour + 1] = true;
                    }

                    // If no content, show working hours (8am-8pm)
                    if (empty($day_expanded_hours)) {
                        for ($h = 8; $h <= 20; $h++) {
                            $day_expanded_hours[$h] = true;
                        }
                    }

                    // Render time slots with compression
                    $day_prev_hour = -1;
                    for ($hour = 0; $hour < 24; $hour++):
                        if (isset($day_expanded_hours[$hour])):
                            // Show gap indicator if there's a jump
                            if ($day_prev_hour >= 0 && $hour > $day_prev_hour + 1):
                                $gap_hours = $hour - $day_prev_hour - 1;
                    ?>
                                <div class="calendar-time-gap-hjn">
                                    <span class="gap-text"><?php echo $gap_hours; ?> hours hidden</span>
                                </div>
                    <?php   endif; ?>
                            <div class="calendar-time-slot-hjn" data-hour="<?php echo $hour; ?>">
                                <?php echo sprintf('%02d:00', $hour); ?>
                            </div>
                    <?php
                            $day_prev_hour = $hour;
                        endif;
                    endfor;
                    ?>
                </div>

                <!-- Single Day Column -->
                <div class="calendar-single-day-hjn">
                    <div class="calendar-day-header-hjn">
                        <h3 id="singleDayTitle"><?php echo date('l, F j, Y'); ?></h3>
                        <div class="calendar-day-header-meta-hjn">
                            <div class="calendar-day-summary-hjn" id="dayViewSummary"></div>
                            <div class="calendar-day-filter-group-hjn" id="dayViewFilterGroup">
                                <button type="button" class="calendar-day-filter-btn-hjn active" data-day-filter="all">All</button>
                                <button type="button" class="calendar-day-filter-btn-hjn" data-day-filter="entries">Entries</button>
                                <button type="button" class="calendar-day-filter-btn-hjn" data-day-filter="routines">Routines</button>
                                <button type="button" class="calendar-day-filter-btn-hjn" data-day-filter="goals">Goals</button>
                            </div>
                        </div>
                    </div>
                    <div class="calendar-day-goals-strip-hjn" id="dayGoalsStrip"></div>

                    <div class="calendar-single-day-grid-hjn" id="singleDayGrid">
                        <?php
                        // Current day entries
                        $today_date = date('Y-m-d');
                        $today_entries = array_filter($calendar_data['entries'], function($entry) use ($today_date) {
                            return $entry['date'] == $today_date;
                        });

                        // Render entries at their specific times
                        foreach ($today_entries as $entry):
                            $hour_position = $entry['hour'] * 80; // 80px per hour
                            $minute = intval(date('i', strtotime($entry['time'])));
                            $minute_offset = ($minute / 60) * 80;
                            $top_position = $hour_position + $minute_offset;
                        ?>
                            <div class="calendar-day-entry-block-hjn"
                                 style="top: <?php echo $top_position; ?>px;"
                                 onclick="openViewOffcanvas('entry', <?php echo $entry['id']; ?>)">
                                <?php if ($entry['thumbnail']): ?>
                                    <div class="calendar-day-entry-image-hjn" style="background-image: url('<?php echo esc_url($entry['thumbnail']); ?>');"></div>
                                <?php else: ?>
                                    <div class="calendar-day-entry-icon-hjn">📸</div>
                                <?php endif; ?>
                                <div class="calendar-day-entry-content-hjn">
                                    <div class="calendar-day-entry-meta-row-hjn">
                                        <div class="calendar-day-entry-time-block-hjn"><?php echo esc_html($entry['time']); ?></div>
                                        <?php if ($entry['rating']): ?>
                                            <div class="calendar-day-entry-rating-block-hjn">
                                                <svg viewBox="0 0 24 24" width="12" height="12">
                                                    <path fill="currentColor" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"/>
                                                </svg>
                                                <?php echo esc_html($entry['rating']); ?>/10
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="calendar-day-entry-title-block-hjn"><?php echo esc_html($entry['title']); ?></div>
                                    <?php if ($entry['mood']): ?>
                                        <div class="calendar-day-entry-mood-block-hjn"><?php echo esc_html($entry['mood']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Current time indicator -->
                        <?php
                        $current_hour = intval(date('H'));
                        $current_minute = intval(date('i'));
                        $current_position = ($current_hour * 80) + (($current_minute / 60) * 80);
                        ?>
                        <div class="calendar-current-time-indicator-hjn" style="top: <?php echo $current_position; ?>px;">
                            <div class="calendar-time-indicator-dot-hjn"></div>
                            <div class="calendar-time-indicator-line-hjn"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
         <!-- Entry Carousel -->
         <div class="entry-carousel">
            <button class="carousel-nav" onclick="scrollCarousel(-1)">‹</button>
            <div class="carousel-track" id="carouselTrack">
                <?php if (!empty($calendar_data['entries'])): ?>
                    <?php foreach ($calendar_data['entries'] as $index => $entry): ?>
                        <?php
                        $background_style = !empty($entry['thumbnail'])
                            ? 'background-image: url(\'' . esc_url($entry['thumbnail']) . '\');'
                            : 'background: linear-gradient(135deg, #e7a690, #fce5d7);';
                        ?>
                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>"
                             onclick="goToDateInCalendar('<?php echo esc_attr($entry['date']); ?>')"
                             data-date="<?php echo esc_attr($entry['date']); ?>">
                            <div class="carousel-date"><?php echo esc_html(date('M j', strtotime($entry['date']))); ?></div>
                            <div class="carousel-preview">
                                <div class="carousel-image" style="<?php echo $background_style; ?>"></div>
                                <div class="carousel-title"><?php echo esc_html(wp_trim_words($entry['title'], 4)); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button class="carousel-nav" onclick="scrollCarousel(1)">›</button>
        </div>

    <?php endif; ?>
</div>

<!-- Hidden data for JavaScript -->
<script type="application/json" id="calendarDataHjn">
<?php echo wp_json_encode($calendar_data); ?>
</script>
