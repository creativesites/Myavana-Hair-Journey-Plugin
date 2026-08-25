<?php
/**
 * Reusable View Offcanvas - Hair Journey Timeline
 * Displays detailed view with edit functionality for goals, entries, and routines
 * Mobile-first responsive design with MYAVANA branding
 */
?>

<!-- View Offcanvas Overlay -->
<div class="offcanvas-overlay-hjn" id="viewOffcanvasOverlay" onclick="closeTimelineViewOffcanvas()"></div>

<!-- Hair Analysis Offcanvas -->
<div class="offcanvas-hjn view-offcanvas-hjn" id="analysisViewOffcanvas" data-type="analysis">
    <div class="offcanvas-header-hjn">
        <div class="offcanvas-header-content">
            <h2 class="offcanvas-title-hjn">Hair Analysis Details</h2>
            <div class="analysis-date" id="analysis-date"></div>
        </div>
        <button class="offcanvas-close-hjn" onclick="closeTimelineViewOffcanvas()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="24" height="24">
                <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
            </svg>
        </button>
    </div>

    <div class="offcanvas-content-hjn">
        <div class="analysis-view-content">
            <!-- Analysis Image -->
            <div class="analysis-image-section">
                <div class="analysis-image-wrapper">
                    <img src="" alt="Hair Analysis" id="analysis-image" class="analysis-image">
                </div>
            </div>

            <!-- Analysis Metrics -->
            <div class="analysis-metrics-section">
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">💪</div>
                        <h4>Health Score</h4>
                        <div class="metric-value" id="health-score"></div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="health-progress"></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon">💧</div>
                        <h4>Hydration</h4>
                        <div class="metric-value" id="hydration-score"></div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="hydration-progress"></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon">🔄</div>
                        <h4>Elasticity</h4>
                        <div class="metric-value" id="elasticity-score"></div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="elasticity-progress"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analysis Details -->
            <div class="analysis-details-section">
                <div class="details-card">
                    <h4>Hair Type</h4>
                    <div class="detail-content" id="hair-type">--</div>
                </div>
                <div class="details-card">
                    <h4>Curl Pattern</h4>
                    <div class="detail-content" id="curl-pattern">--</div>
                </div>
                <div class="details-card">
                    <h4>Porosity</h4>
                    <div class="detail-content" id="porosity">--</div>
                </div>
                <div class="details-card">
                    <h4>Length</h4>
                    <div class="detail-content" id="detail-length">--</div>
                </div>
                <div class="details-card">
                    <h4>Texture</h4>
                    <div class="detail-content" id="detail-texture">--</div>
                </div>
                <div class="details-card">
                    <h4>Density</h4>
                    <div class="detail-content" id="detail-density">--</div>
                </div>
                <div class="details-card">
                    <h4>Hairstyle</h4>
                    <div class="detail-content" id="detail-hairstyle">--</div>
                </div>
                <div class="details-card">
                    <h4>Hair Color</h4>
                    <div class="detail-content" id="detail-hair-color">--</div>
                </div>
                <div class="details-card">
                    <h4>Scalp Health</h4>
                    <div class="detail-content" id="detail-scalp-health">--</div>
                </div>
                <div class="details-card">
                    <h4>Damage</h4>
                    <div class="detail-content" id="detail-damage">--</div>
                </div>
                <div class="details-card">
                    <h4>Strand Thickness</h4>
                    <div class="detail-content" id="detail-strand-thickness">--</div>
                </div>
                <div class="details-card">
                    <h4>Growth Pattern</h4>
                    <div class="detail-content" id="detail-growth-pattern">--</div>
                </div>
                <div class="details-card">
                    <h4>Mood</h4>
                    <div class="detail-content" id="detail-mood-demeanor">--</div>
                </div>
                <div class="details-card">
                    <h4>Environment</h4>
                    <div class="detail-content" id="detail-environment">--</div>
                </div>
                <div class="details-card">
                    <h4>Confidence</h4>
                    <div class="detail-content" id="detail-confidence-level">--</div>
                </div>
            </div>

            <!-- Analysis Summary -->
            <div class="analysis-summary-section">
                <h3>Analysis Summary</h3>
                <div class="summary-content" id="analysis-summary"></div>
            </div>

            <div class="analysis-summary-section">
                <h3>AI Notes</h3>
                <div class="summary-content" id="analysis-full-context"></div>
            </div>

            <!-- Recommendations -->
            <div class="analysis-recommendations-section">
                <h3>Recommendations</h3>
                <div class="recommendations-list" id="analysis-recommendations"></div>
            </div>

            <div class="analysis-recommendations-section">
                <h3>Suggested Products</h3>
                <div class="recommendations-list" id="analysis-products"></div>
            </div>
        </div>
    </div>
</div>

<!-- Entry View Offcanvas -->
<div class="offcanvas-hjn view-offcanvas-hjn" id="entryViewOffcanvas" data-type="entry">
    <div class="offcanvas-header-hjn">
        <h2 class="offcanvas-title-hjn">Entry Details</h2>
        <button class="offcanvas-close-hjn" onclick="closeTimelineViewOffcanvas()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="24" height="24">
                <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
            </svg>
        </button>
    </div>

    <div class="offcanvas-body-hjn" id="entryViewBody">
        <!-- Loading State -->
        <div class="view-loading-hjn">
            <div class="loading-spinner-hjn"></div>
            <p>Loading entry details...</p>
        </div>

        <!-- Content will be dynamically loaded here -->
        <div class="view-content-hjn" style="display: none;">
            <!-- Entry Primary Image (prominent) -->
            <div class="view-primary-image-hjn" id="entryPrimaryImage" style="display: none;">
                <!-- Image injected by JS -->
            </div>

            <!-- Entry Image Gallery -->
            <div class="view-gallery-hjn" id="entryGallery"></div>

            <!-- Entry Video Gallery -->
            <div class="view-section-hjn" id="entryVideosSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M17,10.5V6C17,4.89 16.1,4 15,4H5A2,2 0 0,0 3,6V18A2,2 0 0,0 5,20H15A2,2 0 0,0 17,18V13.5L22,18.5V5.5L17,10.5Z"/>
                    </svg>
                    <h4>Videos</h4>
                </div>
                <div class="view-media-grid-hjn" id="entryVideos"></div>
            </div>

            <!-- Entry Header -->
            <div class="view-header-hjn">
                <h3 class="view-item-title-hjn" id="entryTitle"></h3>
                <div class="view-meta-row-hjn">
                    <span class="view-date-hjn" id="entryDate"></span>
                    <span class="view-type-badge-hjn">Entry</span>
                </div>
            </div>

            <!-- Entry Rating -->
            <div class="view-section-hjn" id="entryRatingSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"/>
                    </svg>
                    <h4>Hair Health Rating</h4>
                </div>
                <div class="rating-display-hjn">
                    <div class="rating-stars-hjn" id="entryRatingStars"></div>
                    <span class="rating-value-hjn" id="entryRatingValue"></span>
                </div>
            </div>

            <!-- Entry Content -->
            <div class="view-section-hjn" id="entryContentSection">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    <h4>Description</h4>
                </div>
                <div class="view-text-content-hjn" id="entryContent"></div>
            </div>

            <!-- Entry Mood -->
            <div class="view-section-hjn" id="entryMoodSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M12,2C6.47,2 2,6.47 2,12C2,17.53 6.47,22 12,22A10,10 0 0,0 22,12C22,6.47 17.5,2 12,2M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20M13,9.94L14.06,11L15.12,9.94L16.18,11L17.24,9.94L15.12,7.82L13,9.94M8.88,9.94L9.94,11L11,9.94L8.88,7.82L6.76,9.94L7.82,11L8.88,9.94M12,17.5C14.33,17.5 16.31,16.04 17.11,14H6.89C7.69,16.04 9.67,17.5 12,17.5Z"/>
                    </svg>
                    <h4>Mood</h4>
                </div>
                <div class="view-tag-list-hjn">
                    <span class="view-tag-hjn" id="entryMood"></span>
                </div>
            </div>

            <!-- Entry Products -->
            <div class="view-section-hjn" id="entryProductsSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19,6H17C17,3.24 14.76,1 12,1C9.24,1 7,3.24 7,6H5C3.9,6 3,6.9 3,8V20C3,21.1 3.9,22 5,22H19C20.1,22 21,21.1 21,20V8C21,6.9 20.1,6 19,6M12,3C13.66,3 15,4.34 15,6H9C9,4.34 10.34,3 12,3M19,20H5V8H19V20M12,12C10.34,12 9,10.66 9,9H7C7,11.76 9.24,14 12,14C14.76,14 17,11.76 17,9H15C15,10.66 13.66,12 12,12Z"/>
                    </svg>
                    <h4>Products Used</h4>
                </div>
                <div class="view-tag-list-hjn" id="entryProducts"></div>
            </div>

            <div class="view-section-hjn" id="entryDetailsSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M11,7H13V9H11V7M11,11H13V17H11V11M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                    </svg>
                    <h4>Entry Details</h4>
                </div>
                <div class="view-detail-grid-hjn" id="entryDetailsGrid"></div>
            </div>

            <div class="view-section-hjn" id="entryFollowUpSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M13,9H11V7H13M13,17H11V11H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                    </svg>
                    <h4>Notes & Next Step</h4>
                </div>
                <div class="view-followup-stack-hjn" id="entryFollowUpStack"></div>
            </div>

            <!-- Entry AI Analysis -->
            <div class="view-section-hjn view-highlight-section-hjn" id="entryAISection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4M11,16.5L6.5,12L7.91,10.59L11,13.67L16.59,8.09L18,9.5L11,16.5Z"/>
                    </svg>
                    <h4>AI Analysis</h4>
                </div>
                <div class="view-text-content-hjn" id="entryAI"></div>
            </div>
        </div>
    </div>

    <div class="offcanvas-footer-hjn">
        <button class="btn-hjn btn-secondary-hjn" onclick="closeTimelineViewOffcanvas()">Close</button>
        <button class="btn-hjn btn-danger-hjn" onclick="deleteEntry()" id="deleteEntryBtn">
            <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"/>
            </svg>
            Delete
        </button>
        <button class="btn-hjn btn-primary-hjn" onclick="editEntry()" id="editEntryBtn">
            <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z"/>
            </svg>
            Edit Entry
        </button>
    </div>
</div>

<!-- Goal View Offcanvas -->
<div class="offcanvas-hjn view-offcanvas-hjn" id="goalViewOffcanvas" data-type="goal">
    <div class="offcanvas-header-hjn">
        <h2 class="offcanvas-title-hjn">Goal Details</h2>
        <button class="offcanvas-close-hjn" onclick="closeTimelineViewOffcanvas()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="24" height="24">
                <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
            </svg>
        </button>
    </div>

    <div class="offcanvas-body-hjn" id="goalViewBody">
        <!-- Loading State -->
        <div class="view-loading-hjn">
            <div class="loading-spinner-hjn"></div>
            <p>Loading goal details...</p>
        </div>

        <!-- Content -->
        <div class="view-content-hjn" style="display: none;">
            <!-- Goal Header -->
            <div class="view-header-hjn">
                <h3 class="view-item-title-hjn" id="goalTitle"></h3>
                <div class="view-meta-row-hjn">
                    <span class="view-date-hjn" id="goalDateRange"></span>
                    <span class="view-type-badge-hjn badge-goal-hjn">Goal</span>
                </div>
            </div>

            <!-- Goal Progress Circle -->
            <div class="view-progress-section-hjn">
                <div class="progress-circle-hjn">
                    <svg class="progress-ring-hjn" width="140" height="140">
                        <circle class="progress-ring-bg-hjn" cx="70" cy="70" r="60" />
                        <circle class="progress-ring-fill-hjn" cx="70" cy="70" r="60" id="goalProgressRing" />
                    </svg>
                    <div class="progress-circle-text-hjn">
                        <span class="progress-percentage-hjn" id="goalProgressPercent">0%</span>
                        <span class="progress-label-hjn">Complete</span>
                    </div>
                </div>
            </div>

            <!-- Goal Description -->
            <div class="view-section-hjn" id="goalDescriptionSection">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    <h4>Description</h4>
                </div>
                <div class="view-text-content-hjn" id="goalDescription"></div>
            </div>

            <div class="view-section-hjn" id="goalDetailsSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M11,7H13V9H11V7M11,11H13V17H11V11M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                    </svg>
                    <h4>Goal Details</h4>
                </div>
                <div class="view-detail-grid-hjn" id="goalDetailsGrid"></div>
            </div>

            

            <!-- Goal Milestones -->
            <div class="view-section-hjn" id="goalMilestonesSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M12,11.5A2.5,2.5 0 0,1 9.5,9A2.5,2.5 0 0,1 12,6.5A2.5,2.5 0 0,1 14.5,9A2.5,2.5 0 0,1 12,11.5M12,2A7,7 0 0,0 5,9C5,14.25 12,22 12,22C12,22 19,14.25 19,9A7,7 0 0,0 12,2Z"/>
                    </svg>
                    <h4>Milestones</h4>
                </div>
                <div class="milestone-list-hjn" id="goalMilestones"></div>
            </div>

            <!-- Goal Progress History -->
            <div class="view-section-hjn" id="goalProgressHistorySection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3"/>
                    </svg>
                    <h4>Progress History</h4>
                </div>
                <div class="progress-timeline-hjn" id="goalProgressHistory"></div>
            </div>

            <!-- Goal Progress Notes (Achievements & Milestones) -->
            <div class="view-section-hjn" id="goalNotesSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M9,11H7V9H9M13,11H11V9H13M17,11H15V9H17M19,4H18V2H16V4H8V2H6V4H5C3.89,4 3,4.9 3,6V20A2,2 0 0,0 5,22H19A2,2 0 0,0 21,20V6C21,4.9 20.1,4 19,4M19,20H5V9H19V20Z"/>
                    </svg>
                    <h4>Progress Notes</h4>
                </div>
                <div class="goal-notes-timeline-hjn" id="goalProgressNotes">
                    <!-- Progress notes will be populated here -->
                    <div class="goal-notes-empty-hjn">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor" opacity="0.3">
                            <path d="M19,4H18V2H16V4H8V2H6V4H5C3.89,4 3,4.9 3,6V20A2,2 0 0,0 5,22H19A2,2 0 0,0 21,20V6C21,4.9 20.1,4 19,4M19,20H5V9H19V20Z"/>
                        </svg>
                        <p>No progress notes yet. Add some when updating your goal progress!</p>
                    </div>
                </div>
            </div>

            <!-- Linked Entries -->
            <div class="view-section-hjn" id="goalLinkedEntriesSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M3.9,12C3.9,10.29 5.29,8.9 7,8.9H11V7H7A5,5 0 0,0 2,12A5,5 0 0,0 7,17H11V15.1H7C5.29,15.1 3.9,13.71 3.9,12M8,13H16V11H8V13M17,7H13V8.9H17C18.71,8.9 20.1,10.29 20.1,12C20.1,13.71 18.71,15.1 17,15.1H13V17H17A5,5 0 0,0 22,12A5,5 0 0,0 17,7Z"/>
                    </svg>
                    <h4>Related Entries</h4>
                </div>
                <div class="linked-items-hjn" id="goalLinkedEntries"></div>
            </div>
        </div>
    </div>

    <div class="offcanvas-footer-hjn">
        <button class="btn-hjn btn-secondary-hjn" onclick="closeTimelineViewOffcanvas()">Close</button>
        <button class="btn-hjn btn-danger-hjn" onclick="deleteGoal()" id="deleteGoalBtn">
            <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"/>
            </svg>
            Delete
        </button>
        <button class="btn-hjn btn-primary-hjn" onclick="editGoal()" id="editGoalBtn">
            <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z"/>
            </svg>
            Edit Goal
        </button>
    </div>
</div>

<!-- Routine View Offcanvas -->
<div class="offcanvas-hjn view-offcanvas-hjn" id="routineViewOffcanvas" data-type="routine">
    <div class="offcanvas-header-hjn">
        <h2 class="offcanvas-title-hjn">Routine Details</h2>
        <button class="offcanvas-close-hjn" onclick="closeTimelineViewOffcanvas()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="24" height="24">
                <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
            </svg>
        </button>
    </div>

    <div class="offcanvas-body-hjn" id="routineViewBody">
        <!-- Loading State -->
        <div class="view-loading-hjn">
            <div class="loading-spinner-hjn"></div>
            <p>Loading routine details...</p>
        </div>

        <!-- Content -->
        <div class="view-content-hjn" style="display: none;">
            <!-- Routine Header -->
            <div class="view-header-hjn">
                <h3 class="view-item-title-hjn" id="routineTitle"></h3>
                <div class="view-meta-row-hjn">
                    <span class="view-schedule-hjn" id="routineSchedule"></span>
                    <span class="view-type-badge-hjn badge-routine-hjn">Routine</span>
                </div>
            </div>

            <!-- Routine Description -->
            <div class="view-section-hjn" id="routineDescriptionSection">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    <h4>Description</h4>
                </div>
                <div class="view-text-content-hjn" id="routineDescription"></div>
            </div>

            <div class="view-section-hjn" id="routineDetailsSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M11,7H13V9H11V7M11,11H13V17H11V11M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                    </svg>
                    <h4>Routine Details</h4>
                </div>
                <div class="view-detail-grid-hjn" id="routineDetailsGrid"></div>
            </div>

            <!-- Routine Steps -->
            <div class="view-section-hjn" id="routineStepsSection">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M10,17L6,13L7.41,11.59L10,14.17L16.59,7.58L18,9M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2Z"/>
                    </svg>
                    <h4>Steps</h4>
                </div>
                <div class="routine-steps-list-hjn" id="routineSteps"></div>
            </div>

            <!-- Routine Products -->
            <div class="view-section-hjn" id="routineProductsSection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19,6H17C17,3.24 14.76,1 12,1C9.24,1 7,3.24 7,6H5C3.9,6 3,6.9 3,8V20C3,21.1 3.9,22 5,22H19C20.1,22 21,21.1 21,20V8C21,6.9 20.1,6 19,6M12,3C13.66,3 15,4.34 15,6H9C9,4.34 10.34,3 12,3M19,20H5V8H19V20M12,12C10.34,12 9,10.66 9,9H7C7,11.76 9.24,14 12,14C14.76,14 17,11.76 17,9H15C15,10.66 13.66,12 12,12Z"/>
                    </svg>
                    <h4>Products</h4>
                </div>
                <div class="view-tag-list-hjn" id="routineProducts"></div>
            </div>

            <!-- Routine Completion History -->
            <div class="view-section-hjn view-highlight-section-hjn" id="routineHistorySection" style="display: none;">
                <div class="view-section-header-hjn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3"/>
                    </svg>
                    <h4>Completion History</h4>
                </div>
                <div class="completion-stats-hjn">
                    <div class="completion-stat-hjn">
                        <span class="stat-value-hjn" id="routineCompletionRate">0%</span>
                        <span class="stat-label-hjn">Completion Rate</span>
                    </div>
                    <div class="completion-stat-hjn">
                        <span class="stat-value-hjn" id="routineStreak">0</span>
                        <span class="stat-label-hjn">Day Streak</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas-footer-hjn">
        <button class="btn-hjn btn-secondary-hjn" onclick="closeTimelineViewOffcanvas()">Close</button>
        <button
            class="btn-hjn btn-secondary-hjn"
            onclick="toggleCurrentRoutineCompletion(this)"
            id="routineCompleteBtn"
            data-date="<?php echo esc_attr(current_time('Y-m-d')); ?>">
            <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M9,16.17L4.83,12L3.41,13.41L9,19L21,7L19.59,5.59L9,16.17Z"/>
            </svg>
            Mark Complete Today
        </button>
        <button class="btn-hjn btn-danger-hjn" onclick="deleteRoutine()" id="deleteRoutineBtn">
            <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"/>
            </svg>
            Delete
        </button>
        <button class="btn-hjn btn-primary-hjn" onclick="editRoutine()" id="editRoutineBtn">
            <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z"/>
            </svg>
            Edit Routine
        </button>
    </div>
</div>
