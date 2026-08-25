<?php
/**
 * Luxury List View - Hair Journey Timeline
 * Displays goals, routines, and entries in a beautiful, filterable list
 * Mobile-first responsive design with MYAVANA branding
 */

// Determine current user ID safely
$user_id = 0;
if ( isset($current_user) && ! empty($current_user->ID) ) {
    $user_id = intval($current_user->ID);
} else {
    $user_id = get_current_user_id();
}

// Fallback: if no user, render auth required message
if ( ! $user_id ) : ?>
    <div id="listView" class="view-content list-view-hjn">
        <div class="list-header-hjn">
            <h2 class="list-title-hjn">Journey List</h2>
            <p class="list-description-hjn">Please sign in to see your goals, routines and entries.</p>
        </div>
    </div>
<?php
    return;
endif;

// Fetch user data
$hair_goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true) ?: [];
$current_routine = get_user_meta($user_id, 'myavana_current_routine', true) ?: [];

$entries_args = [
    'post_type' => 'hair_journey_entry',
    'author' => $user_id,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'post_date',
    'order' => 'DESC',
];
$entries = get_posts($entries_args);

// Calculate totals for filter counts
$total_goals = count($hair_goals);
$total_routines = count($current_routine);
$total_entries = count($entries);
$total_all = $total_goals + $total_routines + $total_entries;
?>

<!-- Luxury List View -->
<div id="listView" class="view-content list-view-hjn">
    <!-- Header Section -->
    <div class="list-header-hjn">
        <div class="list-header-content-hjn">
            <div class="list-title-section-hjn">
                <h2 class="list-title-hjn">Journey List</h2>
                <p class="list-description-hjn">All your goals, entries, and routines in one comprehensive view</p>
            </div>

            <!-- Filter & Sort Controls -->
            <div class="list-controls-hjn">
                <div class="list-filter-chips-hjn">
                    <button class="filter-chip-hjn active" data-filter="all">
                        <span class="filter-label-hjn">All</span>
                        <span class="filter-count-hjn"><?php echo $total_all; ?></span>
                    </button>
                    <button class="filter-chip-hjn" data-filter="goals">
                        <span class="filter-label-hjn">Goals</span>
                        <span class="filter-count-hjn"><?php echo $total_goals; ?></span>
                    </button>
                    <button class="filter-chip-hjn" data-filter="entries">
                        <span class="filter-label-hjn">Entries</span>
                        <span class="filter-count-hjn"><?php echo $total_entries; ?></span>
                    </button>
                    <button class="filter-chip-hjn" data-filter="routines">
                        <span class="filter-label-hjn">Routines</span>
                        <span class="filter-count-hjn"><?php echo $total_routines; ?></span>
                    </button>
                </div>

                <div class="list-sort-search-hjn">
                    <div class="list-search-wrapper-hjn">
                        <svg class="search-icon-hjn" viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z"/>
                        </svg>
                        <input
                            type="text"
                            class="list-search-input-hjn"
                            placeholder="Search by title, date, or keywords..."
                            id="listSearchInput"
                        />
                        <button class="search-clear-btn-hjn" id="searchClearBtn" style="display: none;">
                            <svg viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                            </svg>
                        </button>
                    </div>

                    <select class="list-sort-select-hjn" id="listSortSelect">
                        <option value="date-desc">Date (Newest First)</option>
                        <option value="date-asc">Date (Oldest First)</option>
                        <option value="title-asc">Title (A-Z)</option>
                        <option value="title-desc">Title (Z-A)</option>
                        <option value="type">Type</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- List Container -->
    <div class="list-container-hjn" id="listContainer">
        <div class="list-grid-hjn" id="listGrid">

            <!-- GOALS -->
            <?php if ( ! empty( $hair_goals ) ) : ?>
                <?php foreach ( $hair_goals as $idx => $goal ) :
                    $title = $goal['title'] ?? $goal['goal_title'] ?? 'Untitled Goal';
                    $start = $goal['start_date'] ?? $goal['start'] ?? '';
                    $end = $goal['end_date'] ?? $goal['end'] ?? '';
                    $progress = isset($goal['progress']) ? intval($goal['progress']) : (isset($goal['progress_percent']) ? intval($goal['progress_percent']) : 0);
                    $description = $goal['description'] ?? $goal['notes'] ?? '';
                    $category = $goal['category'] ?? 'growth';
                    $milestones = $goal['milestones'] ?? [];

                    // Format dates
                    $date_range = '';
                    if ($start || $end) {
                        $start_formatted = $start ? date('M j, Y', strtotime($start)) : '';
                        $end_formatted = $end ? date('M j, Y', strtotime($end)) : '';
                        $date_range = trim($start_formatted . ($start && $end ? ' - ' : '') . $end_formatted);
                    }

                    // Calculate milestone progress
                    $completed_milestones = 0;
                    $total_milestones = count($milestones);
                    foreach ($milestones as $milestone) {
                        if (isset($milestone['achieved']) && $milestone['achieved']) {
                            $completed_milestones++;
                        }
                    }

                    // Sort date for sorting functionality
                    $sort_date = $start ? strtotime($start) : 0;
                ?>
                <div class="list-item-hjn list-item-goal-hjn"
                     data-type="goals"
                     data-title="<?php echo esc_attr(strtolower($title)); ?>"
                     data-date="<?php echo esc_attr($sort_date); ?>"
                     data-goal-index="<?php echo esc_attr($idx); ?>"
                     data-goal-id="<?php echo esc_attr($goal['id'] ?? $goal['goal_id'] ?? $idx); ?>">

                    <div class="list-item-icon-hjn list-icon-goal-hjn">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z"/>
                        </svg>
                    </div>

                    <div class="list-item-content-hjn">
                        <div class="list-item-header-hjn">
                            <h3 class="list-item-title-hjn"><?php echo esc_html($title); ?></h3>
                            <span class="list-item-badge-hjn badge-goal-hjn"><?php echo $progress; ?>%</span>
                        </div>

                        <?php if ($date_range): ?>
                        <div class="list-item-date-hjn">
                            <svg viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M19,19H5V8H19M16,1V3H8V1H6V3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3H18V1"/>
                            </svg>
                            <?php echo esc_html($date_range); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($description): ?>
                        <p class="list-item-description-hjn"><?php echo esc_html(wp_trim_words($description, 15)); ?></p>
                        <?php endif; ?>

                        <div class="list-item-meta-hjn">
                            <span class="meta-tag-hjn tag-type-hjn">Goal</span>
                            <?php if ($total_milestones > 0): ?>
                            <span class="meta-tag-hjn">
                                <?php echo $completed_milestones; ?>/<?php echo $total_milestones; ?> Milestones
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="list-item-progress-hjn">
                            <div class="progress-bar-hjn">
                                <div class="progress-fill-hjn" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="list-item-actions-hjn">
                        <button class="list-item-action-hjn"
                                data-action="view-goal"
                                data-index="<?php echo esc_attr($idx); ?>"
                                onclick="openViewOffcanvas('goal', <?php echo esc_js($idx); ?>)">
                            View
                        </button>
                        <button class="list-item-action-hjn is-secondary"
                                data-action="edit-goal"
                                data-index="<?php echo esc_attr($idx); ?>"
                                onclick="editGoal(<?php echo esc_js($idx); ?>)">
                            Edit
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ENTRIES -->
            <?php if ( ! empty( $entries ) ) : ?>
                <?php foreach ( $entries as $entry ) :
                    $post_id = $entry->ID;
                    $entry_title = get_the_title($post_id);
                    $entry_date = get_the_date('', $post_id);
                    $entry_date_formatted = get_the_date('F j, Y', $post_id);
                    
                    // Try to get post thumbnail first
                    $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
                    
                    // If no thumbnail, check for featured_image_index meta and gallery
                    if (empty($thumbnail)) {
                        $featured_image_index = get_post_meta($post_id, 'featured_image_index', true);
                        $gallery_images = get_post_meta($post_id, '_entry_gallery', true);
                        
                        // If we have a featured_image_index and gallery images exist
                        if ($featured_image_index !== '' && !empty($gallery_images) && is_array($gallery_images)) {
                            // Ensure the index is valid
                            $index = intval($featured_image_index);
                            if (isset($gallery_images[$index])) {
                                $thumbnail = wp_get_attachment_image_url($gallery_images[$index], 'medium');
                            }
                        }
                        
                        // If still no thumbnail, try the first gallery image
                        if (empty($thumbnail) && !empty($gallery_images) && is_array($gallery_images)) {
                            $thumbnail = wp_get_attachment_image_url($gallery_images[0], 'medium');
                        }
                    }
                    
                    $content = wp_strip_all_tags($entry->post_content);
                    $excerpt = wp_trim_words($content, 20);

                    // Get entry metadata
                    $rating = get_post_meta($post_id, 'health_rating', true);
                    $mood = get_post_meta($post_id, 'mood_demeanor', true);
                    $products = get_post_meta($post_id, 'products_used', true);

                    // Sort date
                    $sort_date = strtotime($entry->post_date);
                ?>
                <div class="list-item-hjn list-item-entry-hjn"
                    data-type="entries"
                    data-title="<?php echo esc_attr(strtolower($entry_title)); ?>"
                    data-date="<?php echo esc_attr($sort_date); ?>"
                    data-entry-id="<?php echo esc_attr($post_id); ?>">

                    <?php if ($thumbnail): ?>
                    <div class="list-item-thumbnail-hjn">
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($entry_title); ?>" loading="lazy" />
                        <?php if ($rating): ?>
                        <div class="thumbnail-rating-hjn">
                            <svg viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"/>
                            </svg>
                            <?php echo esc_html($rating); ?>/10
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="list-item-icon-hjn list-icon-entry-hjn">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M4,4H7L9,2H15L17,4H20A2,2 0 0,1 22,6V18A2,2 0 0,1 20,20H4A2,2 0 0,1 2,18V6A2,2 0 0,1 4,4M12,7A5,5 0 0,0 7,12A5,5 0 0,0 12,17A5,5 0 0,0 17,12A5,5 0 0,0 12,7M12,9A3,3 0 0,1 15,12A3,3 0 0,1 12,15A3,3 0 0,1 9,12A3,3 0 0,1 12,9Z"/>
                        </svg>
                    </div>
                    <?php endif; ?>

                    <div class="list-item-content-hjn">
                        <div class="list-item-header-hjn">
                            <h3 class="list-item-title-hjn"><?php echo esc_html($entry_title); ?></h3>
                        </div>

                        <div class="list-item-date-hjn">
                            <svg viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22C6.47,22 2,17.5 2,12A10,10 0 0,1 12,2M12.5,7V12.25L17,14.92L16.25,16.15L11,13V7H12.5Z"/>
                            </svg>
                            <?php echo esc_html($entry_date_formatted); ?>
                        </div>

                        <p class="list-item-description-hjn"><?php echo esc_html($excerpt); ?></p>

                        <div class="list-item-meta-hjn">
                            <span class="meta-tag-hjn tag-type-hjn">Entry</span>
                            <?php if ($mood): ?>
                            <span class="meta-tag-hjn"><?php echo esc_html($mood); ?></span>
                            <?php endif; ?>
                            <?php if ($products): ?>
                            <span class="meta-tag-hjn">
                                <?php
                                $product_count = count(array_filter(explode(',', $products)));
                                echo $product_count . ' Product' . ($product_count !== 1 ? 's' : '');
                                ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="list-item-actions-hjn">
                        <button class="list-item-action-hjn"
                                data-action="view-entry"
                                data-entry-id="<?php echo esc_attr($post_id); ?>"
                                onclick="openViewOffcanvas('entry', <?php echo esc_js($post_id); ?>)">
                            View
                        </button>
                        <button class="list-item-action-hjn is-secondary"
                                data-action="edit-entry"
                                data-entry-id="<?php echo esc_attr($post_id); ?>"
                                onclick="editEntry(<?php echo esc_js($post_id); ?>)">
                            Edit
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ROUTINES -->
            <?php if ( ! empty( $current_routine ) ) : ?>
                <?php foreach ( $current_routine as $r_idx => $step ) :
                    $r_title = $step['title'] ?? $step['name'] ?? 'Routine Step';
                    $schedule = $step['schedule'] ?? $step['when'] ?? $step['frequency'] ?? '';
                    $description = $step['description'] ?? $step['notes'] ?? '';

                    // Ensure products and steps are arrays
                    $products = $step['products'] ?? [];
                    if (!is_array($products)) {
                        $products = !empty($products) ? explode(',', $products) : [];
                    }

                    $steps_list = $step['steps'] ?? [];
                    if (!is_array($steps_list)) {
                        $steps_list = !empty($steps_list) ? explode("\n", $steps_list) : [];
                    }

                    // Sort date - routines don't have dates, use 0
                    $sort_date = 0;
                ?>
                <div class="list-item-hjn list-item-routine-hjn"
                     data-type="routines"
                     data-title="<?php echo esc_attr(strtolower($r_title)); ?>"
                     data-date="<?php echo esc_attr($sort_date); ?>"
                     data-routine-index="<?php echo esc_attr($r_idx); ?>">

                    <div class="list-item-icon-hjn list-icon-routine-hjn">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,7A5,5 0 0,0 7,12A5,5 0 0,0 12,17A5,5 0 0,0 17,12A5,5 0 0,0 12,7Z"/>
                        </svg>
                    </div>

                    <div class="list-item-content-hjn">
                        <div class="list-item-header-hjn">
                            <h3 class="list-item-title-hjn"><?php echo esc_html($r_title); ?></h3>
                            <?php if ($schedule): ?>
                            <span class="list-item-badge-hjn badge-routine-hjn"><?php echo esc_html($schedule); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($description): ?>
                        <p class="list-item-description-hjn"><?php echo esc_html(wp_trim_words($description, 15)); ?></p>
                        <?php endif; ?>

                        <div class="list-item-meta-hjn">
                            <span class="meta-tag-hjn tag-type-hjn">Routine</span>
                            <?php if (!empty($steps_list)): ?>
                            <span class="meta-tag-hjn"><?php echo count($steps_list); ?> Steps</span>
                            <?php endif; ?>
                            <?php if (!empty($products)): ?>
                            <span class="meta-tag-hjn"><?php echo count($products); ?> Products</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="list-item-actions-hjn">
                        <button class="list-item-action-hjn"
                                data-action="view-routine"
                                data-index="<?php echo esc_attr($r_idx); ?>"
                                onclick="openViewOffcanvas('routine', <?php echo esc_js($r_idx); ?>)">
                            View
                        </button>
                        <button class="list-item-action-hjn is-secondary"
                                data-action="edit-routine"
                                data-index="<?php echo esc_attr($r_idx); ?>"
                                onclick="editRoutine(<?php echo esc_js($r_idx); ?>)">
                            Edit
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>

        <!-- Empty State -->
        <div class="list-empty-state-hjn" id="listEmptyState" style="display: none;">
            <svg viewBox="0 0 24 24" width="64" height="64">
                <path fill="currentColor" d="M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z"/>
            </svg>
            <h3 class="empty-title-hjn">No Results Found</h3>
            <p class="empty-description-hjn">Try adjusting your filters or search terms</p>
        </div>

        <!-- No Data State -->
        <?php if (empty($hair_goals) && empty($entries) && empty($current_routine)): ?>
        <div class="list-empty-state-hjn">
            <svg viewBox="0 0 24 24" width="64" height="64">
                <path fill="currentColor" d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
            </svg>
            <h3 class="empty-title-hjn">Start Your Journey</h3>
            <p class="empty-description-hjn">Add your first goal, entry, or routine to begin tracking your hair care journey</p>
            <button class="empty-action-btn-hjn" onclick="openOffcanvas('entry')">
                Add Your First Entry
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
