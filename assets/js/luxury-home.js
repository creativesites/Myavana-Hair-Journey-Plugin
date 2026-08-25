/**
 * MYAVANA Luxury Homepage JavaScript
 *
 * Provides smooth interactions, animations, and user experience enhancements
 * for the luxury MYAVANA homepage design.
 */

(function($) {
    'use strict';

    // Wait for DOM and framework to be ready
    $(document).ready(function() {
        MyavanaLuxuryHomepage.init();
    });

    // Main Homepage Object
    window.MyavanaLuxuryHomepage = {
        // Configuration
        config: {
            scrollThreshold: 100,
            animationDuration: 600,
            counterAnimationDuration: 2000,
            debounceDelay: 100
        },

        // State
        state: {
            isScrolled: false,
            isMobileMenuOpen: false,
            animatedElements: new Set(),
            countersAnimated: new Set()
        },

        // Initialize the homepage
        init: function() {
            this.bindEvents();
            this.initAnimations();
            this.initNavigation();
            this.initCounters();
            this.initSmoothScrolling();
            this.checkInitialState();
            this.initOnboarding();

            console.log('✨ MYAVANA Luxury Homepage initialized');
        },

        // Bind all event listeners
        bindEvents: function() {
            const self = this;

            // Scroll events
            $(window).on('scroll', this.debounce(function() {
                self.handleScroll();
            }, this.config.debounceDelay));

            // Resize events
            $(window).on('resize', this.debounce(function() {
                self.handleResize();
            }, this.config.debounceDelay));

            // Navigation events
            $(document).on('click', '.myavana-luxury-nav-link', function(e) {
                const href = $(this).attr('href');

                // Close mobile menu when clicking any nav link
                if (self.state.isMobileMenuOpen) {
                    self.closeMobileMenu();
                }

                // Handle anchor links
                if (href.startsWith('#')) {
                    e.preventDefault();
                    self.smoothScrollTo(href);
                }
            });

            // Mobile menu toggle
            $(document).on('click', '.myavana-luxury-mobile-toggle', function(e) {
                e.preventDefault();
                self.toggleMobileMenu();
            });

            // Mobile app header profile dropdown
            $(document).on('click', '.myavana-app-profile-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggleAppProfileMenu($(this));
            });

            $(document).on('click', '.myavana-app-profile-menu a', function() {
                self.closeAppProfileMenu();
            });

            // CTA buttons
            $(document).on('click', '[data-modal]', function(e) {
                e.preventDefault();
                const modalType = $(this).data('modal');
                self.openModal(modalType);
            });

            // Close mobile menu on outside click
            $(document).on('click', function(e) {
                if (self.state.isMobileMenuOpen && !$(e.target).closest('.myavana-luxury-nav').length) {
                    self.closeMobileMenu();
                }
                if (!$(e.target).closest('.myavana-app-profile-dropdown').length) {
                    self.closeAppProfileMenu();
                }
            });

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.state.isMobileMenuOpen) {
                    self.closeMobileMenu();
                }
                if (e.key === 'Escape') {
                    self.closeAppProfileMenu();
                }
            });
        },

        // Handle scroll events
        handleScroll: function() {
            const scrollTop = $(window).scrollTop();
            const shouldBeScrolled = scrollTop > this.config.scrollThreshold;

            // Update navigation state
            if (shouldBeScrolled !== this.state.isScrolled) {
                this.state.isScrolled = shouldBeScrolled;
                this.updateNavigationState();
            }

            // Handle scroll animations
            this.handleScrollAnimations();

            // Update navigation visibility (hide on scroll down, show on scroll up)
            this.handleNavigationVisibility(scrollTop);
        },

        // Update navigation appearance based on scroll
        updateNavigationState: function() {
            const $nav = $('.myavana-luxury-nav');

            if (this.state.isScrolled) {
                $nav.addClass('scrolled');
            } else {
                $nav.removeClass('scrolled');
            }
        },

        // Handle navigation visibility on scroll
        handleNavigationVisibility: function(scrollTop) {
            const $nav = $('.myavana-luxury-nav');

            if (!this.lastScrollTop) {
                this.lastScrollTop = scrollTop;
                return;
            }

            const isScrollingDown = scrollTop > this.lastScrollTop;
            const scrollDifference = Math.abs(scrollTop - this.lastScrollTop);

            // Only hide/show if significant scroll movement
            if (scrollDifference > 10) {
                if (isScrollingDown && scrollTop > this.config.scrollThreshold) {
                    $nav.css('transform', 'translateY(-100%)');
                } else {
                    $nav.css('transform', 'translateY(0)');
                }
            }

            this.lastScrollTop = scrollTop;
        },

        // Handle resize events
        handleResize: function() {
            // Close mobile menu if screen becomes large
            if ($(window).width() > 768 && this.state.isMobileMenuOpen) {
                this.closeMobileMenu();
            }

            // Recalculate scroll animations
            this.handleScrollAnimations();
        },

        // Initialize intersection observer for animations
        initAnimations: function() {
            // Only initialize if IntersectionObserver is supported
            if (typeof IntersectionObserver === 'undefined') {
                // Fallback: show all elements immediately
                $('.myavana-luxury-feature-card, .myavana-luxury-step, .myavana-luxury-stat, .myavana-luxury-member-highlight, .myavana-luxury-member-card').addClass('animate-in');
                return;
            }

            const self = this;
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            this.observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting && !self.state.animatedElements.has(entry.target)) {
                        self.animateElement(entry.target);
                        self.state.animatedElements.add(entry.target);
                    }
                });
            }, observerOptions);

            // Observe elements for animation
            $('.myavana-luxury-feature-card, .myavana-luxury-step, .myavana-luxury-stat, .myavana-luxury-member-highlight, .myavana-luxury-member-card').each(function() {
                self.observer.observe(this);
            });
        },

        // Animate individual element
        animateElement: function(element) {
            const $element = $(element);

            // Add animation class with slight delay for staggered effect
            setTimeout(function() {
                $element.addClass('animate-in');

                // Trigger custom event for additional animations
                $element.trigger('myavana:animated');
            }, Math.random() * 200);
        },

        // Initialize navigation functionality
        initNavigation: function() {
            // Set active navigation item based on current section
            this.updateActiveNavItem();

            // Update on scroll
            $(window).on('scroll', this.debounce(() => {
                this.updateActiveNavItem();
            }, 100));
        },

        // Update active navigation item
        updateActiveNavItem: function() {
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();

            $('.myavana-luxury-nav-link').each(function() {
                const href = $(this).attr('href');
                if (href && href.startsWith('#')) {
                    const $target = $(href);
                    if ($target.length) {
                        const targetTop = $target.offset().top - 100;
                        const targetBottom = targetTop + $target.outerHeight();

                        if (scrollTop >= targetTop && scrollTop < targetBottom) {
                            $('.myavana-luxury-nav-link').removeClass('active');
                            $(this).addClass('active');
                        }
                    }
                }
            });
        },

        // Initialize counter animations
        initCounters: function() {
            const self = this;

            $('.myavana-luxury-stat-number').each(function() {
                const $counter = $(this);
                const originalText = $counter.text();
                const match = originalText.match(/^(\d+(?:,\d+)*)/);

                if (match) {
                    const targetValue = parseInt(match[1].replace(/,/g, ''));
                    $counter.data('target', targetValue);
                    $counter.data('original', originalText);

                    // Start counter when element becomes visible
                    if (self.observer) {
                        self.observer.observe(this);
                    }
                }
            });

            // Listen for animation trigger
            $('.myavana-luxury-stat-number').on('myavana:animated', function() {
                if (!self.state.countersAnimated.has(this)) {
                    self.animateCounter(this);
                    self.state.countersAnimated.add(this);
                }
            });
        },

        // Animate counter
        animateCounter: function(element) {
            const $counter = $(element);
            const target = $counter.data('target');
            const originalText = $counter.data('original');

            if (!target) return;

            const startValue = 0;
            const duration = this.config.counterAnimationDuration;
            const startTime = performance.now();

            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Use easing function for smooth animation
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const currentValue = Math.floor(startValue + (target - startValue) * easeOutQuart);

                // Format number with commas and preserve suffix
                let formattedValue = currentValue.toLocaleString();
                const suffix = originalText.replace(/^[\d,]+/, '');
                if (suffix) {
                    formattedValue += suffix;
                }

                $counter.text(formattedValue);

                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    $counter.text(originalText); // Ensure final value is correct
                }
            };

            requestAnimationFrame(animate);
        },

        // Initialize smooth scrolling
        initSmoothScrolling: function() {
            // Add smooth scrolling to all anchor links
            $('a[href^="#"]').on('click', (e) => {
                const href = $(e.currentTarget).attr('href');
                if (href !== '#') {
                    e.preventDefault();
                    this.smoothScrollTo(href);
                }
            });
        },

        // Smooth scroll to element
        smoothScrollTo: function(target) {
            const $target = $(target);
            if ($target.length) {
                const targetTop = $target.offset().top - 80; // Account for fixed nav

                $('html, body').animate({
                    scrollTop: targetTop
                }, {
                    duration: 800,
                    easing: 'easeInOutCubic'
                });

                // Close mobile menu if open
                if (this.state.isMobileMenuOpen) {
                    this.closeMobileMenu();
                }
            }
        },

        // Handle scroll animations (fallback for older browsers)
        handleScrollAnimations: function() {
            if (this.observer) return; // Use intersection observer if available

            const windowTop = $(window).scrollTop();
            const windowBottom = windowTop + $(window).height();

            $('.myavana-luxury-feature-card, .myavana-luxury-step, .myavana-luxury-stat, .myavana-luxury-member-highlight, .myavana-luxury-member-card').each((index, element) => {
                const $element = $(element);
                if ($element.hasClass('animate-in')) return;

                const elementTop = $element.offset().top;
                const elementBottom = elementTop + $element.outerHeight();

                // Check if element is in viewport
                if (elementBottom >= windowTop && elementTop <= windowBottom) {
                    this.animateElement(element);
                }
            });
        },

        // Check initial state
        checkInitialState: function() {
            // Check scroll position on load
            this.handleScroll();

            // Animate any elements already in viewport
            this.handleScrollAnimations();
        },

        // Mobile menu functions
        // Mobile menu functions (UPDATED - works with slide panel)
        toggleMobileMenu: function() {
            const isOpening = !this.state.isMobileMenuOpen;

            this.state.isMobileMenuOpen = isOpening;

            const $panel   = $('#mobileMenuPanel');
            const $overlay = $('#mobileMenuOverlay');
            const $toggle  = $('.myavana-luxury-mobile-toggle');

            if (isOpening) {
                $panel.addClass('open');
                $overlay.addClass('open');
                $toggle.addClass('active');
                $('body').addClass('mobile-menu-open');
            } else {
                $panel.removeClass('open');
                $overlay.removeClass('open');
                $toggle.removeClass('active');
                $('body').removeClass('mobile-menu-open');
            }
        },

        openMobileMenu: function() {
            const $nav = $('.myavana-luxury-nav');
            const $toggle = $('.myavana-luxury-mobile-toggle');

            $nav.addClass('mobile-open');
            $toggle.addClass('active');
            $('body').addClass('mobile-menu-open');

            this.state.isMobileMenuOpen = true;

            // Animate toggle button
            $toggle.find('span').each(function(index) {
                $(this).css('transform', index === 0 ? 'rotate(45deg) translateY(6px)' :
                            index === 1 ? 'opacity(0)' :
                            'rotate(-45deg) translateY(-6px)');
            });
        },

        closeMobileMenu: function() {
            const $nav = $('.myavana-luxury-nav');
            const $toggle = $('.myavana-luxury-mobile-toggle');

            $nav.removeClass('mobile-open');
            $toggle.removeClass('active');
            $('body').removeClass('mobile-menu-open');

            this.state.isMobileMenuOpen = false;

            // Reset toggle button
            $toggle.find('span').css('transform', '');
        },

        toggleAppProfileMenu: function($trigger) {
            const $dropdown = $trigger.closest('.myavana-app-profile-dropdown');
            const isOpen = $dropdown.hasClass('is-open');
            this.closeAppProfileMenu();

            if (!isOpen) {
                $dropdown.addClass('is-open');
                $trigger.attr('aria-expanded', 'true');
            }
        },

        closeAppProfileMenu: function() {
            $('.myavana-app-profile-dropdown').removeClass('is-open');
            $('.myavana-app-profile-toggle').attr('aria-expanded', 'false');
        },

        // Modal integration
        openModal: function(modalType) {
            // Handle auth modals
            if (modalType === 'login' || modalType === 'register') {
                if (typeof showMyavanaModal === 'function') {
                    showMyavanaModal(modalType);
                } else {
                    this.showModalFallback(modalType);
                }
                return;
            }

            // Handle entry modals
            if (modalType === 'new-entry') {
                this.openEntryModal();
                return;
            }

            // Handle other specific modals
            switch (modalType) {
                case 'timeline':
                    this.redirectToTimeline();
                    break;
                case 'analytics':
                    this.redirectToAnalytics();
                    break;
                default:
                    this.showModalFallback(modalType);
            }
        },

        // Open new entry modal
        openEntryModal: function() {
            // Check if user is logged in
            if (!this.isUserLoggedIn()) {
                this.showNotification('Please log in to add a hair entry.', 'warning');
                setTimeout(() => {
                    if (typeof showMyavanaModal === 'function') {
                        showMyavanaModal('login');
                    }
                }, 1500);
                return;
            }

            const ajaxData = window.myavanaLuxuryData || {};

            // Check if user is new and should go through onboarding first
            // BUT only if they haven't completed it yet
            if (ajaxData.isNewUser &&
                ajaxData.userStats &&
                ajaxData.userStats.entries === 0 &&
                !ajaxData.userStats.onboarding_completed &&
                ajaxData.showOnboarding) {
                // Show onboarding flow instead
                this.showNotification('Let\'s start with a quick setup to personalize your experience!', 'info');
                setTimeout(() => {
                    this.startOnboarding();
                }, 1500);
                return;
            }

            // Create and show entry modal
            this.createEntryModal();
        },

        // Create entry modal
        createEntryModal: function() {
            // Remove existing modal if present
            $('#myavanaEntryModal').remove();

            const modalHTML = `
                <div id="myavanaEntryModal" class="myavana-modal-overlay myavana-entry-modal-overlay">
                    <div class="myavana-modal-content myavana-entry-modal-content">
                        <button class="myavana-modal-close myavana-entry-modal-close" onclick="MyavanaLuxuryHomepage.closeEntryModal()">
                            <i class="fas fa-times"></i>
                        </button>
                        <div id="myavanaEntryFormContainer" class="myavana-entry-form-container">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin fa-2x"></i>
                                <span>Loading entry form...</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
            $('body').addClass('modal-open');

            // Animate in
            setTimeout(() => {
                $('#myavanaEntryModal').css('opacity', '1');
                $('#myavanaEntryModal .myavana-modal-content').css('transform', 'scale(1)');
            }, 50);

            // Load entry form content
            this.loadEntryForm();

            // Close on backdrop click
            $('#myavanaEntryModal').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeEntryModal();
                }
            });

            // Close on escape key
            $(document).on('keydown.entryModal', (e) => {
                if (e.key === 'Escape') {
                    this.closeEntryModal();
                }
            });
        },

        // Load entry form via AJAX
        loadEntryForm: function() {
            const ajaxData = window.myavanaLuxuryData || {};

            $.ajax({
                url: ajaxData.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'myavana_load_entry_form_dash',
                    nonce: ajaxData.nonce || window.myavanaNonce || ''
                },
                success: (response) => {
                    if (response.success) {
                        $('#myavanaEntryFormContainer').html(response.data);
                        this.initEntryFormEvents();
                    } else {
                        this.showEntryFormError('Failed to load entry form: ' + (response.data || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('MYAVANA: Entry form load error:', error);
                    this.showEntryFormFallback();
                }
            });
        },

        // Show entry form fallback
        showEntryFormFallback: function() {
            const fallbackHTML = `
                <div class="myavana-entry-form-fallback">
                    <div class="myavana-entry-form-fallback-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h3>Add Hair Entry</h3>
                    <p>
                        The entry form is currently loading. You can also access it through:
                    </p>
                    <div class="myavana-entry-form-fallback-actions">
                        <a href="/members/admin/hair_profile/" class="myavana-luxury-btn-primary">
                            <i class="fas fa-user"></i> Profile Page
                        </a>
                        <a href="/hair-journey/" class="myavana-luxury-btn-secondary">
                            <i class="fas fa-book"></i> My Hair Timeline
                        </a>
                    </div>
                </div>
            `;
            $('#myavanaEntryFormContainer').html(fallbackHTML);
        },

        // Show entry form error
        showEntryFormError: function(message) {
            const errorHTML = `
                <div class="myavana-entry-form-error">
                    <div class="myavana-entry-form-error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Unable to Load Entry Form</h3>
                    <p>
                        ${message}
                    </p>
                    <button type="button" onclick="MyavanaLuxuryHomepage.loadEntryForm()" class="myavana-luxury-btn-primary">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
            $('#myavanaEntryFormContainer').html(errorHTML);
        },

        // Initialize entry form events
        initEntryFormEvents: function() {
            // Add any specific entry form event handlers here
            console.log('MYAVANA: Entry form loaded and events initialized');
        },

        // Close entry modal
        closeEntryModal: function() {
            const $modal = $('#myavanaEntryModal');

            // Animate out
            $modal.css('opacity', '0');
            $modal.find('.myavana-modal-content').css('transform', 'scale(0.9)');

            setTimeout(() => {
                $modal.remove();
                $('body').removeClass('modal-open');
                $(document).off('keydown.entryModal');
            }, 300);
        },

        // Redirect functions
        redirectToTimeline: function() {
            window.location.href = '/hair-journey/';
        },

        redirectToAnalytics: function() {
            window.location.href = '/members/admin/hair_insights/';
        },

        // Check if user is logged in
        isUserLoggedIn: function() {
            // Check localized data first
            const ajaxData = window.myavanaLuxuryData || {};
            if (ajaxData.isLoggedIn !== undefined) {
                return ajaxData.isLoggedIn;
            }

            // Fallback to other indicators
            return document.body.classList.contains('logged-in') ||
                   window.myavanaUser ||
                   $('.myavana-luxury-profile-dropdown').length > 0;
        },

        // Fallback modal system
        showModalFallback: function(modalType) {
            let message = '';
            switch (modalType) {
                case 'register':
                    message = 'Registration modal would open here. Please ensure the MYAVANA authentication system is loaded.';
                    break;
                case 'login':
                    message = 'Login modal would open here. Please ensure the MYAVANA authentication system is loaded.';
                    break;
                case 'new-entry':
                    message = 'New entry modal would open here. Please ensure the MYAVANA entry system is loaded.';
                    break;
                default:
                    message = `${modalType} modal would open here.`;
            }

            // Create a simple notification
            this.showNotification(message, 'info');
        },

        // Simple notification system
        showNotification: function(message, type = 'info') {
            const notification = $(`
                <div class="myavana-notification myavana-notification-${type}" style="
                    position: fixed;
                    top: 100px;
                    right: 20px;
                    background: ${type === 'info' ? 'var(--myavana-coral)' : 'var(--myavana-onyx)'};
                    color: var(--myavana-white);
                    padding: 16px 24px;
                    border-radius: 8px;
                    box-shadow: var(--shadow-strong);
                    z-index: 10001;
                    max-width: 300px;
                    font-family: var(--font-secondary);
                    font-size: 14px;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                ">
                    ${message}
                    <button style="
                        position: absolute;
                        top: 8px;
                        right: 8px;
                        background: none;
                        border: none;
                        color: inherit;
                        cursor: pointer;
                        font-size: 16px;
                    ">&times;</button>
                </div>
            `);

            $('body').append(notification);

            // Animate in
            setTimeout(() => {
                notification.css('transform', 'translateX(0)');
            }, 100);

            // Close button
            notification.find('button').on('click', () => {
                notification.css('transform', 'translateX(100%)');
                setTimeout(() => notification.remove(), 300);
            });

            // Auto close after 5 seconds
            setTimeout(() => {
                notification.css('transform', 'translateX(100%)');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        },

        // Utility: Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Utility: Check if element is in viewport
        isInViewport: function(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },

        // ============================================
        // ONBOARDING SYSTEM
        // ============================================

        // Initialize onboarding system
        initOnboarding: function() {
            // The canonical onboarding modal (templates/auth/onboarding-modal.php)
            // already auto-shows itself server-side via render_onboarding_overlay()
            // when onboarding is pending. Do NOT auto-trigger the legacy overlay here
            // too — doing so injects a second, duplicate-ID copy of the overlay into
            // the DOM and breaks both instances' click handlers.
        },

        // Start onboarding process
        startOnboarding: function() {
            console.log('🚀 Starting MYAVANA onboarding...');

            // Prefer the canonical server-rendered onboarding modal so we never end
            // up with two overlays sharing the same element IDs on the page.
            if (typeof window.showMyavanaOnboarding === 'function') {
                window.showMyavanaOnboarding();
                return;
            }

            // Fallback for pages where the canonical modal wasn't rendered.
            this.loadOnboardingOverlay();
        },

        // Load onboarding overlay
        loadOnboardingOverlay: function() {
            $.ajax({
                url: window.myavanaLuxuryData?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'myavana_load_onboarding_overlay',
                    nonce: window.myavanaLuxuryData?.nonce || ''
                },
                success: (response) => {
                    if (response.success) {
                        // Add onboarding HTML to page
                        $('body').append(response.data);

                        // Initialize onboarding interactions
                        this.initOnboardingEvents();

                        // Prevent body scroll
                        $('body').addClass('onboarding-active');

                        console.log('✅ Onboarding overlay loaded');
                    } else {
                        console.error('❌ Failed to load onboarding:', response.data);
                        this.fallbackOnboarding();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ Onboarding AJAX error:', error);
                    this.fallbackOnboarding();
                }
            });
        },

        // Initialize onboarding events
        initOnboardingEvents: function() {
            // Close onboarding
            $(document).on('click', '.myavana-onboarding-close', (e) => {
                e.preventDefault();
                this.closeOnboarding();
            });

            // Skip onboarding
            $(document).on('click', '.myavana-onboarding-skip', (e) => {
                e.preventDefault();
                this.skipOnboarding();
            });

            // Complete onboarding
            $(document).on('click', '.myavana-onboarding-complete', (e) => {
                e.preventDefault();
                this.completeOnboarding();
            });

            // Escape key to close
            $(document).on('keydown.onboarding', (e) => {
                if (e.key === 'Escape') {
                    this.closeOnboarding();
                }
            });
        },

        // Close onboarding
        closeOnboarding: function() {
            $('.myavana-onboarding-overlay').fadeOut(300, function() {
                $(this).remove();
            });
            $('body').removeClass('onboarding-active');
            $(document).off('keydown.onboarding');
        },

        // Skip onboarding
        skipOnboarding: function() {
            // Mark as completed but skipped
            $.ajax({
                url: window.myavanaLuxuryData?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'myavana_skip_onboarding',
                    nonce: window.myavanaLuxuryData?.nonce || ''
                },
                success: (response) => {
                    console.log('Onboarding skipped');
                }
            });

            this.closeOnboarding();
        },

        // Complete onboarding
        completeOnboarding: function() {
            // Mark as completed
            $.ajax({
                url: window.myavanaLuxuryData?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'myavana_complete_onboarding',
                    nonce: window.myavanaLuxuryData?.nonce || ''
                },
                success: (response) => {
                    console.log('Onboarding completed');

                    // Show success message and redirect to entry form
                    this.showNotification('Welcome to MYAVANA! Let\'s create your first entry.', 'success');

                    // Auto-open entry modal after closing onboarding
                    setTimeout(() => {
                        this.openEntryModal();
                    }, 1000);
                }
            });

            this.closeOnboarding();
        },

        // Fallback onboarding (simple modal)
        fallbackOnboarding: function() {
            const onboardingHTML = `
                <div class="myavana-onboarding-overlay myavana-fallback-onboarding">
                    <div class="myavana-fallback-onboarding-card">
                        <div class="myavana-fallback-onboarding-header">
                            <i class="fas fa-sparkles"></i>
                            <h2>
                                Welcome to MYAVANA
                            </h2>
                            <p>
                                Ready to start your personalized hair journey?
                            </p>
                        </div>

                        <div class="myavana-fallback-onboarding-actions">
                            <button type="button" onclick="MyavanaLuxuryHomepage.completeOnboarding()" class="myavana-luxury-btn-primary">
                                <i class="fas fa-rocket"></i>
                                Yes, Let's Start!
                            </button>
                            <button type="button" onclick="MyavanaLuxuryHomepage.skipOnboarding()" class="myavana-luxury-btn-secondary">
                                <i class="fas fa-clock"></i>
                                Maybe Later
                            </button>
                        </div>

                        <div class="myavana-fallback-onboarding-note">
                            <p>
                                We'll help you set up your profile and create your first hair journey entry.
                            </p>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(onboardingHTML);
            $('body').addClass('onboarding-active');
            this.initOnboardingEvents();
        }
    };

    // Global functions for external access
    window.scrollToSection = function(sectionId) {
        MyavanaLuxuryHomepage.smoothScrollTo('#' + sectionId);
    };

    window.toggleMobileMenu = function() {
        MyavanaLuxuryHomepage.toggleMobileMenu();
    };

    window.toggleProfileDropdown = function() {
        console.log('Profile dropdown functionality not implemented yet');
        // Implement profile dropdown logic here
    };

    // Test function for entry modal
    window.testEntryModal = function() {
        if (MyavanaLuxuryHomepage) {
            MyavanaLuxuryHomepage.openEntryModal();
        } else {
            console.error('MYAVANA Luxury Homepage not initialized');
        }
    };

    // Test function for onboarding
    window.testOnboarding = function() {
        if (MyavanaLuxuryHomepage) {
            MyavanaLuxuryHomepage.startOnboarding();
        } else {
            console.error('MYAVANA Luxury Homepage not initialized');
        }
    };

    // Test function to simulate new user experience
    window.simulateNewUser = function() {
        // Override the data to simulate new user
        window.myavanaLuxuryData = {
            ...window.myavanaLuxuryData,
            isNewUser: true,
            showOnboarding: true,
            userStats: {
                entries: 0,
                days_active: 0,
                streak: 0,
                is_new_user: true,
                show_onboarding: true
            }
        };

        console.log('✅ Simulating new user experience. Try clicking "Add Entry" or wait 2 seconds for auto-onboarding.');

        // Auto-start onboarding after brief delay
        setTimeout(() => {
            if (MyavanaLuxuryHomepage) {
                MyavanaLuxuryHomepage.startOnboarding();
            }
        }, 2000);
    };

    // Add custom easing for jQuery animations
    if ($.easing) {
        $.easing.easeInOutCubic = function(x, t, b, c, d) {
            if ((t /= d / 2) < 1) return c / 2 * t * t * t + b;
            return c / 2 * ((t -= 2) * t * t + 2) + b;
        };
    }

    // Performance monitoring (optional)
    if (window.performance && window.performance.mark) {
        window.performance.mark('myavana-luxury-homepage-start');

        $(window).on('load', function() {
            window.performance.mark('myavana-luxury-homepage-end');
            window.performance.measure('myavana-luxury-homepage-init', 'myavana-luxury-homepage-start', 'myavana-luxury-homepage-end');

            const measure = window.performance.getEntriesByName('myavana-luxury-homepage-init')[0];
            if (measure) {
                console.log(`✨ MYAVANA Luxury Homepage loaded in ${measure.duration.toFixed(2)}ms`);
            }
        });
    }

})(jQuery);

// Expose to global scope for backward compatibility
window.MyavanaLuxuryHome = window.MyavanaLuxuryHomepage;

// Universal modal handler that routes to correct handler
(function() {
    'use strict';

    // Store original auth modal function
    const originalShowMyavanaModal = window.showMyavanaModal;

    // Override with universal handler
    window.showMyavanaModal = function(modalType) {
        console.log('showMyavanaModal called with:', modalType);

        // Auth modals - use original auth handler
        if (modalType === 'login' || modalType === 'signin' || modalType === 'register' || modalType === 'signup' || modalType === 'forgot' || modalType === 'forgot-password') {
            if (typeof originalShowMyavanaModal === 'function') {
                originalShowMyavanaModal(modalType);
            } else if (window.MyavanaAuth && window.MyavanaAuth.api && window.MyavanaAuth.api.showModal) {
                window.MyavanaAuth.api.showModal(modalType);
            }
            return;
        }

        // All other modals - use luxury homepage handler
        if (window.MyavanaLuxuryHomepage && window.MyavanaLuxuryHomepage.openModal) {
            window.MyavanaLuxuryHomepage.openModal(modalType);
        } else {
            console.warn('MyavanaLuxuryHomepage.openModal not available');
        }
    };

    console.log('Universal modal handler installed');
})();
