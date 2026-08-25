<?php
// Partial: timeline-area.php
$timeline_total_entries = intval($shared_data['analytics']['total_entries'] ?? 0);
$timeline_total_goals = is_array($hair_goals) ? count($hair_goals) : 0;
$timeline_total_routines = is_array($current_routine) ? count($current_routine) : 0;
$timeline_total_analyses = intval($shared_data['gamification']['total_ai_analyses'] ?? count($analysis_history));
$timeline_remaining_analyses = max(0, intval($analysis_limit_info['remaining'] ?? 0));
$timeline_focus_copy = $timeline_total_entries > 0
    ? 'Every entry, goal, and routine now lives in one calmer, clearer workspace.'
    : 'Start with one beautiful entry and build your timeline from there.';
?>
            <div class="timeline-area journey-stage-area-hjn">
                <div class="journey-stage-shell-hjn">
                    <?php if (!empty($show_welcome_banner)): ?>
                    <div class="journey-launch-banner-hjn">
                        <div class="journey-launch-copy-hjn">
                            <span class="journey-launch-kicker-hjn">Welcome to MYAVANA</span>
                            <strong>Your profile is ready. Let’s create your first meaningful entry.</strong>
                            <p>We saved your onboarding details and prepared your timeline for day one.</p>
                        </div>
                        <button type="button" class="journey-launch-btn-hjn" onclick="createEntry(window.myavanaJourneyLaunchState?.entryPrefill || {})">
                            Create First Entry
                        </button>
                    </div>
                    <?php endif; ?>



                    <div class="timeline-controls journey-stage-controls-hjn">
                    <div class="view-tabs myavana-journey-view-switcher-hjn" role="tablist" aria-label="Hair journey views">
                        <button class="tab active" onclick="switchView('timeline')" role="tab" aria-selected="true">Timeline</button>
                        <button class="tab" onclick="switchView('calendar')" role="tab" aria-selected="false">Calendar</button>
                        <button class="tab" onclick="switchView('list')" role="tab" aria-selected="false">List</button>
                    </div>


                </div>

                    <div class="journey-stage-body-hjn">
                        <!-- Include modular view partials -->
                        <?php
                        $views_dir = __DIR__;
                        if ( file_exists( $views_dir . '/view-calendar.php' ) ) {
                            include $views_dir . '/view-calendar.php';
                        }
                        if ( file_exists( $views_dir . '/view-timeline.php' ) ) {
                            include $views_dir . '/view-timeline.php';
                        }
                        
                        if ( file_exists( $views_dir . '/view-list.php' ) ) {
                            include $views_dir . '/view-list.php';
                        }
                        ?>
                    </div>
                </div>
            </div>
