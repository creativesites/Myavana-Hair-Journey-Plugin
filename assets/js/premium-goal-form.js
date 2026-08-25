/**
 * MYAVANA Premium Goal Form
 * Luxury UI for creating hair goals
 * @version 1.0.0
 */

(function($) {
    'use strict';

    window.MyavanaPremiumGoalForm = {
        formData: {},

        /**
         * Open the premium goal form
         */
        open: function(prefillData) {
            console.log('[Premium Goal Form] Opening with prefill data:', prefillData);
            this.formData = prefillData || {};
            this.createModal();
        },

        /**
         * Create the premium modal structure
         */
        createModal: function() {
            // Remove existing modal if present
            $('#myavanaPremiumGoalModal').remove();

            const modalHTML = `
                <div id="myavanaPremiumGoalModal" class="myavana-premium-modal-overlay">
                    <div class="myavana-premium-modal">
                        <!-- Header -->
                        <div class="premium-modal-header">
                            <div class="header-content">
                                <h2 class="modal-title">🎯 New Hair Goal</h2>
                                <p class="modal-subtitle">Set your hair journey target</p>
                            </div>
                            <button class="modal-close" onclick="MyavanaPremiumGoalForm.close()">
                                <svg viewBox="0 0 24 24" width="24" height="24">
                                    <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Form Body -->
                        <div class="premium-form-body">
                            <form id="premiumGoalForm">
                                <div class="form-step active">
                                    <div class="step-content">
                                        <!-- Goal Title -->
                                        <div class="form-group">
                                            <label class="form-label">
                                                Goal Title
                                                <span class="required">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                id="goalTitle"
                                                class="form-input"
                                                placeholder="e.g., Grow 3 inches, Reduce breakage, Achieve curl definition"
                                                maxlength="100"
                                            >
                                        </div>

                                        <!-- Goal Description -->
                                        <div class="form-group">
                                            <label class="form-label">
                                                Description
                                                <span class="required">*</span>
                                            </label>
                                            <textarea
                                                id="goalDescription"
                                                class="form-textarea"
                                                rows="4"
                                                placeholder="Describe what you want to achieve and why..."
                                                maxlength="1000"
                                            ></textarea>
                                            <div class="char-count">
                                                <span id="goalCharCount">0</span>/1000
                                            </div>
                                        </div>

                                        <!-- Target Date -->
                                        <div class="form-group">
                                            <label class="form-label">
                                                Target Date
                                                <span class="required">*</span>
                                            </label>
                                            <input
                                                type="date"
                                                id="goalTargetDate"
                                                class="form-input"
                                                min="${new Date().toISOString().split('T')[0]}"
                                            >
                                        </div>

                                        <!-- Priority -->
                                        <div class="form-group">
                                            <label class="form-label">Priority Level</label>
                                            <div class="priority-selector" id="prioritySelector">
                                                <button type="button" class="priority-option" data-priority="High">
                                                    <span class="priority-icon">🔥</span>
                                                    <span class="priority-label">High</span>
                                                </button>
                                                <button type="button" class="priority-option selected" data-priority="Medium">
                                                    <span class="priority-icon">⭐</span>
                                                    <span class="priority-label">Medium</span>
                                                </button>
                                                <button type="button" class="priority-option" data-priority="Low">
                                                    <span class="priority-icon">📌</span>
                                                    <span class="priority-label">Low</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Footer -->
                        <div class="premium-modal-footer">
                            <button type="button" class="btn-secondary" onclick="MyavanaPremiumGoalForm.close()">
                                Cancel
                            </button>
                            <button type="button" class="btn-primary" onclick="MyavanaPremiumGoalForm.saveGoal()">
                                <svg viewBox="0 0 24 24" width="20" height="20">
                                    <path fill="currentColor" d="M9,20.42L2.79,14.21L5.62,11.38L9,14.77L18.88,4.88L21.71,7.71L9,20.42Z"/>
                                </svg>
                                Save Goal
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
            $('body').addClass('modal-open');

            // Set form values if prefill data exists
            if (this.formData.title) {
                $('#myavanaPremiumGoalModal #goalTitle').val(this.formData.title);
            }
            if (this.formData.description) {
                $('#myavanaPremiumGoalModal #goalDescription').val(this.formData.description);
                $('#myavanaPremiumGoalModal #goalCharCount').text(this.formData.description.length);
            }
            if (this.formData.target_date) {
                $('#myavanaPremiumGoalModal #goalTargetDate').val(this.formData.target_date);
            }
            if (this.formData.priority) {
                $('#myavanaPremiumGoalModal .priority-option').removeClass('selected');
                $('#myavanaPremiumGoalModal .priority-option[data-priority="' + this.formData.priority + '"]').addClass('selected');
            }

            // Animate in
            setTimeout(() => {
                $('#myavanaPremiumGoalModal').addClass('show');
            }, 50);

            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            const self = this;

            // Character count
            $(document).on('input', '#myavanaPremiumGoalModal #goalDescription', function() {
                const count = $(this).val().length;
                $('#myavanaPremiumGoalModal #goalCharCount').text(count);
            });

            // Priority selector
            $(document).on('click', '#myavanaPremiumGoalModal .priority-option', function() {
                $('#myavanaPremiumGoalModal .priority-option').removeClass('selected');
                $(this).addClass('selected');
                self.formData.priority = $(this).data('priority');
            });
        },

        /**
         * Save goal
         */
        saveGoal: function() {
            const title = $('#myavanaPremiumGoalModal #goalTitle').val();
            const description = $('#myavanaPremiumGoalModal #goalDescription').val();
            const targetDate = $('#myavanaPremiumGoalModal #goalTargetDate').val();
            const priority = $('#myavanaPremiumGoalModal .priority-option.selected').data('priority') || 'Medium';

            // Validation
            if (!title || title.trim().length === 0) {
                this.showNotification('Please enter a goal title', 'error');
                return;
            }
            if (!description || description.trim().length === 0) {
                this.showNotification('Please add a description', 'error');
                return;
            }
            if (!targetDate) {
                this.showNotification('Please set a target date', 'error');
                return;
            }

            this.showNotification('Saving your goal...', 'info');

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'myavana_add_goal');
            formData.append('goal_title', title);
            formData.append('goal_description', description);
            formData.append('target_date', targetDate);
            formData.append('priority', priority);

            const settings = window.myavanaTimelineSettings || {};
            formData.append('security', settings.addGoalNonce || settings.nonce);

            // Submit via AJAX
            $.ajax({
                url: settings.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Goal saved successfully! 🎯', 'success');
                        setTimeout(() => {
                            this.close();
                            location.reload();
                        }, 1500);
                    } else {
                        this.showNotification(response.data || 'Failed to save goal', 'error');
                    }
                },
                error: () => {
                    this.showNotification('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Close modal
         */
        close: function() {
            $('#myavanaPremiumGoalModal').removeClass('show');
            setTimeout(() => {
                $('#myavanaPremiumGoalModal').remove();
                $('body').removeClass('modal-open');
            }, 300);
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            $('.premium-notification').remove();

            const icons = {
                success: '✓',
                error: '✗',
                warning: '⚠',
                info: 'ℹ'
            };

            const notification = $(`
                <div class="premium-notification ${type}">
                    <span class="notification-icon">${icons[type]}</span>
                    <span class="notification-message">${message}</span>
                </div>
            `);

            $('body').append(notification);
            setTimeout(() => notification.addClass('show'), 100);

            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    notification.removeClass('show');
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
        }
    };

    // Global shortcut
    window.createGoal = (prefillData) => {
        if (typeof window.myavanaOpenCollectionComposer === 'function') {
            return window.myavanaOpenCollectionComposer('goal', prefillData || {});
        }
        return MyavanaPremiumGoalForm.open(prefillData);
    };

})(jQuery);
