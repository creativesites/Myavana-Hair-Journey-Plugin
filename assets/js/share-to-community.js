/**
 * Share to Community JavaScript
 * Handles sharing hair journey entries to the community feed
 */

(function($) {
    'use strict';

    /**
     * Handle share button clicks
     */
    $(document).on('click', '.myavana-share-entry-btn:not(.shared)', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const entryId = $btn.data('entry-id');

        // Disable button and show loading
        $btn.prop('disabled', true);
        const originalText = $btn.html();
        $btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10"></circle></svg> Sharing...');

        // Make AJAX request
        $.ajax({
            url: myavana.ajax_url || ajaxurl,
            method: 'POST',
            data: {
                action: 'share_entry_to_community',
                nonce: myavana.nonce,
                entry_id: entryId
            },
            success: function(response) {
                if (response.success) {
                    // Update button to "shared" state
                    $btn.addClass('shared');
                    $btn.html(`
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Shared to Community
                    `);

                    // Show success notification
                    showNotification(response.data.message, 'success');

                    // Add celebration animation
                    celebrateShare($btn);
                } else {
                    // Re-enable button and restore text
                    $btn.prop('disabled', false);
                    $btn.html(originalText);

                    // Show error notification
                    showNotification(response.data || 'Failed to share entry', 'error');
                }
            },
            error: function() {
                // Re-enable button and restore text
                $btn.prop('disabled', false);
                $btn.html(originalText);

                // Show error notification
                showNotification('Network error. Please try again.', 'error');
            }
        });
    });

    /**
     * Celebration animation when entry is shared
     */
    function celebrateShare($btn) {
        // Create confetti effect
        for (let i = 0; i < 20; i++) {
            const $confetti = $('<div class="confetti"></div>');
            $confetti.css({
                position: 'fixed',
                left: $btn.offset().left + ($btn.width() / 2) + 'px',
                top: $btn.offset().top + 'px',
                width: '10px',
                height: '10px',
                backgroundColor: ['#e7a690', '#4a4d68', '#f5f5f7'][Math.floor(Math.random() * 3)],
                borderRadius: '50%',
                pointerEvents: 'none',
                zIndex: 10000
            });

            $('body').append($confetti);

            // Animate confetti
            $confetti.animate({
                left: '+=' + (Math.random() * 200 - 100) + 'px',
                top: '+=' + (Math.random() * 200 + 100) + 'px',
                opacity: 0
            }, 1000 + Math.random() * 500, function() {
                $(this).remove();
            });
        }
    }

    /**
     * Show notification toast
     */
    function showNotification(message, type = 'info') {
        const colors = {
            success: '#e7a690',
            error: '#dc3545',
            info: '#4a4d68'
        };

        const $notification = $(`
            <div class="myavana-notification" style="
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: ${colors[type]};
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 10000;
                font-family: 'Archivo', sans-serif;
                font-weight: 600;
                animation: slideInUp 0.3s ease;
                max-width: 350px;
            ">
                ${escapeHtml(message)}
            </div>
        `);

        $('body').append($notification);

        setTimeout(() => {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Add spin animation for loading
    if (!document.getElementById('myavana-spin-keyframes')) {
        const style = document.createElement('style');
        style.id = 'myavana-spin-keyframes';
        style.textContent = `
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            @keyframes slideInUp {
                from {
                    transform: translateY(20px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }

})(jQuery);
