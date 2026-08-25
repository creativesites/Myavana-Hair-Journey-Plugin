<?php
/**
 * MYAVANA Slider View - Hair Journey
 * Horizontal carousel showcasing hair journey entries
 * MYAVANA luxury branding with modern card design
 */

// Determine current user ID
$user_id = get_current_user_id();

if (!$user_id) : ?>
    <div id="sliderView" class="view-content slider-view-hjn">
        <div class="slider-empty-hjn">
            <h2>Sign in to view your journey</h2>
        </div>
    </div>
<?php
    return;
endif;

// Fetch entries
$entries_args = [
    'post_type' => 'hair_journey_entry',
    'author' => $user_id,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'post_date',
    'order' => 'DESC',
];
$entries = get_posts($entries_args);
?>

<!-- MYAVANA Slider View -->
<div id="sliderView" class="view-content slider-view-hjn">

    <?php if (empty($entries)): ?>
        <!-- Empty State -->
        <div class="slider-empty-state-hjn">
            <svg viewBox="0 0 24 24" width="64" height="64">
                <path fill="currentColor" d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3"/>
            </svg>
            <h3 class="myavana-subheader">Your Journey Awaits</h3>
            <p class="myavana-body">Start documenting your beautiful hair transformation</p>
            <button class="slider-cta-btn-hjn" onclick="openOffcanvas('entry')">
                <svg viewBox="0 0 24 24" width="18" height="18">
                    <path fill="currentColor" d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                </svg>
                Add Your First Entry
            </button>
        </div>
    <?php else: ?>

        <!-- Slider Header -->
        <div class="slider-header-hjn">
            <div class="slider-header-content-hjn">
                <div class="myavana-preheader">HAIR JOURNEY SHOWCASE</div>
                <h2 class="myavana-h1-slider">YOUR TRANSFORMATION</h2>
                <p class="myavana-body">Swipe through your beautiful hair journey moments</p>
            </div>
            <div class="slider-header-actions-hjn">
                <div class="slider-entry-count-hjn">
                    <span class="count-number"><?php echo count($entries); ?></span>
                    <span class="count-label">Entries</span>
                </div>
                <button class="slider-add-btn-hjn" onclick="openOffcanvas('entry')">
                    <svg viewBox="0 0 24 24" width="18" height="18">
                        <path fill="currentColor" d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    New Entry
                </button>
            </div>
        </div>

        <!-- Splide Carousel -->
        <div class="slider-carousel-container-hjn">
            <div class="splide" id="hairJourneySlider">
                <div class="splide__track">
                    <ul class="splide__list">
                        <?php foreach ($entries as $entry):
                            $post_id = $entry->ID;
                            $entry_date = get_the_date('F j, Y', $post_id);
                            $entry_time = get_the_date('g:i A', $post_id);
                            $thumbnail = get_the_post_thumbnail_url($post_id, 'large');
                            $rating = get_post_meta($post_id, 'health_rating', true);
                            $mood = get_post_meta($post_id, 'mood_demeanor', true);
                            $products = get_post_meta($post_id, 'products_used', true);
                            $content = wp_trim_words($entry->post_content, 30);
                        ?>
                        <li class="splide__slide">
                            <div class="slider-entry-card-hjn" data-entry-id="<?php echo $post_id; ?>">
                                <!-- Card Image -->
                                <div class="slider-card-image-hjn">
                                    <?php if ($thumbnail): ?>
                                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>">
                                    <?php else: ?>
                                        <div class="slider-card-placeholder-hjn">
                                            <svg viewBox="0 0 24 24" width="48" height="48">
                                                <path fill="currentColor" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Date Badge -->
                                    <div class="slider-date-badge-hjn">
                                        <div class="date-day"><?php echo date('j', strtotime($entry->post_date)); ?></div>
                                        <div class="date-month"><?php echo date('M', strtotime($entry->post_date)); ?></div>
                                    </div>

                                    <?php if ($rating): ?>
                                    <!-- Rating Badge -->
                                    <div class="slider-rating-badge-hjn">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <svg class="rating-star-hjn <?php echo $i <= $rating ? 'filled' : ''; ?>" viewBox="0 0 24 24" width="14" height="14">
                                                <path fill="currentColor" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"/>
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Card Content -->
                                <div class="slider-card-content-hjn">
                                    <h3 class="slider-card-title-hjn"><?php echo esc_html(get_the_title($post_id)); ?></h3>

                                    <div class="slider-card-meta-hjn">
                                        <span class="meta-date">
                                            <svg viewBox="0 0 24 24" width="14" height="14">
                                                <path fill="currentColor" d="M19,19H5V8H19M16,1V3H8V1H6V3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3H18V1M17,12H12V17H17V12Z"/>
                                            </svg>
                                            <?php echo $entry_date; ?>
                                        </span>
                                        <?php if ($mood): ?>
                                        <span class="meta-mood">
                                            <svg viewBox="0 0 24 24" width="14" height="14">
                                                <path fill="currentColor" d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M7,9.5C7,8.7 7.7,8 8.5,8C9.3,8 10,8.7 10,9.5C10,10.3 9.3,11 8.5,11C7.7,11 7,10.3 7,9.5M12,17.23C9.97,17.23 8.16,16.5 6.74,15.27L8.28,13.73C9.3,14.72 10.6,15.23 12,15.23C13.4,15.23 14.7,14.72 15.72,13.73L17.26,15.27C15.84,16.5 14.03,17.23 12,17.23M15.5,11C14.7,11 14,10.3 14,9.5C14,8.7 14.7,8 15.5,8C16.3,8 17,8.7 17,9.5C17,10.3 16.3,11 15.5,11Z"/>
                                            </svg>
                                            <?php echo esc_html($mood); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($content): ?>
                                    <p class="slider-card-description-hjn"><?php echo esc_html($content); ?></p>
                                    <?php endif; ?>

                                    <?php if ($products): ?>
                                    <div class="slider-card-products-hjn">
                                        <svg viewBox="0 0 24 24" width="14" height="14">
                                            <path fill="currentColor" d="M20,18H4V6H20M20,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6C22,4.89 21.1,4 20,4Z"/>
                                        </svg>
                                        <span><?php echo esc_html(wp_trim_words($products, 5)); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Card Actions -->
                                    <div class="slider-card-actions-hjn">
                                        <button class="slider-view-btn-hjn" onclick="openViewOffcanvas('entry', <?php echo $post_id; ?>)">
                                            <svg viewBox="0 0 24 24" width="16" height="16">
                                                <path fill="currentColor" d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"/>
                                            </svg>
                                            View
                                        </button>
                                        <button class="slider-edit-btn-hjn" onclick="editEntry(<?php echo $post_id; ?>)">
                                            <svg viewBox="0 0 24 24" width="16" height="16">
                                                <path fill="currentColor" d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z"/>
                                            </svg>
                                            Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Splide Arrows -->
                <div class="splide__arrows">
                    <button class="splide__arrow splide__arrow--prev">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M15.41,16.58L10.83,12L15.41,7.41L14,6L8,12L14,18L15.41,16.58Z"/>
                        </svg>
                    </button>
                    <button class="splide__arrow splide__arrow--next">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z"/>
                        </svg>
                    </button>
                </div>

                <!-- Splide Pagination -->
                <ul class="splide__pagination"></ul>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
// Initialize Splide slider when view is shown
(function initSlider() {
    // Wait for Splide to be loaded
    if (typeof Splide === 'undefined') {
        console.log('[Slider] Waiting for Splide to load...');
        setTimeout(initSlider, 100);
        return;
    }

    if (document.getElementById('hairJourneySlider')) {
        console.log('[Slider] Initializing Splide...');
        const splide = new Splide('#hairJourneySlider', {
            type: 'loop',
            perPage: 3,
            perMove: 1,
            gap: '2rem',
            padding: '5rem',
            breakpoints: {
                1200: {
                    perPage: 2,
                    gap: '1.5rem',
                    padding: '3rem',
                },
                768: {
                    perPage: 1,
                    gap: '1rem',
                    padding: '2rem',
                },
            },
            arrows: true,
            pagination: true,
            autoplay: false,
            speed: 600,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
        });

        splide.mount();
        console.log('[Slider] Splide initialized successfully');
    }
})();

// Edit entry function
function editEntry(entryId) {
    console.log('Editing entry:', entryId);
    openOffcanvas('entry');
    loadEntryForEdit(entryId);
}
</script>
