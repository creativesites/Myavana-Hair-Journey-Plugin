// Timeline filter variables
let timelineCurrentFilter = 'all';

/**
 * Set timeline filter by type
 */
function setTimelineFilter(filterType) {
    timelineCurrentFilter = filterType;
    console.log('Timeline filter set to:', filterType);

    // Update button states
    const filterButtons = document.querySelectorAll('.timeline-filter-btn-hjn[data-filter]');
    filterButtons.forEach(btn => {
        if (btn.dataset.filter === filterType) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    applyTimelineFilters();
}

/**
 * Toggle timeline advanced filter panel
 */
function toggleTimelineFilterPanel() {
    const panel = document.getElementById('timelineFiltersPanel');
    if (!panel) return;

    if (panel.style.display === 'none' || !panel.style.display) {
        panel.style.display = 'block';
    } else {
        panel.style.display = 'none';
    }
}

/**
 * Apply timeline filters
 */
function applyTimelineFilters() {
    const searchInput = document.getElementById('timelineSearchInput');
    const filterRating = document.getElementById('timelineFilterRating');

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const minRating = filterRating ? parseInt(filterRating.value) : 0;

    console.log('Applying timeline filters:', { filter: timelineCurrentFilter, search: searchTerm, minRating });

    // Get all timeline month groups
    const monthGroups = document.querySelectorAll('.timeline-month-group-hjn');

    monthGroups.forEach(monthGroup => {
        const items = monthGroup.querySelectorAll('.timeline-item-hjn');
        let visibleCount = 0;

        items.forEach(item => {
            const type = item.dataset.type || 'entry';
            const title = item.querySelector('.timeline-item-title-hjn')?.textContent.toLowerCase() || '';
            const description = item.querySelector('.timeline-item-description-hjn')?.textContent.toLowerCase() || '';
            const ratingStars = item.querySelectorAll('.timeline-rating-star-hjn.filled');
            const itemRating = ratingStars.length;

            // Check type filter
            let matchesType = timelineCurrentFilter === 'all' || type === timelineCurrentFilter;

            // Check search
            let matchesSearch = !searchTerm || title.includes(searchTerm) || description.includes(searchTerm);

            // Check rating (only for entries)
            let matchesRating = type !== 'entry' || minRating === 0 || itemRating >= minRating;

            const isVisible = matchesType && matchesSearch && matchesRating;

            item.style.display = isVisible ? 'flex' : 'none';
            if (isVisible) visibleCount++;
        });

        // Hide month group if no visible items
        monthGroup.style.display = visibleCount > 0 ? 'block' : 'none';
    });

    console.log('Timeline filters applied');
}

/**
 * Clear timeline filters
 */
function clearTimelineFilters() {
    const searchInput = document.getElementById('timelineSearchInput');
    const filterRating = document.getElementById('timelineFilterRating');

    if (searchInput) searchInput.value = '';
    if (filterRating) filterRating.value = '0';

    setTimelineFilter('all');

    console.log('Timeline filters cleared');
}