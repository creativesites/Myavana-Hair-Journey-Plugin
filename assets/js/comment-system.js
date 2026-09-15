/**
 * MYAVANA Enhanced Comment System
 * Modern social-media-quality commenting with nested threading,
 * reactions, editing, and rich interactions.
 *
 * @module CommentSystem
 * @requires jQuery
 */

(function($) {
    'use strict';

    /**
     * CommentSystem class for managing all comment interactions
     */
    window.CommentSystem = class {
        constructor(options = {}) {
            this.options = {
                postId: null,
                userId: null,
                ajaxUrl: ajaxurl || '/wp-admin/admin-ajax.php',
                nonce: null,
                maxChars: 2000,
                maxReplyDepth: 2,
                ...options
            };

            this.state = {
                isLoading: false,
                replyingTo: null,
                editingId: null,
                expandedThreads: new Set()
            };

            this.init();
        }

        init() {
            this.bindEvents();
            this.setupEmojiPicker();
        }

        /**
         * Bind all event listeners for comment interactions
         */
        bindEvents() {
            const self = this;

            // Comment submission
            $(document).on('click', '.cs-comment-submit', function(e) {
                e.preventDefault();
                const $form = $(this).closest('.cs-comment-form');
                const postId = $form.data('post-id');
                const parentId = $form.data('parent-id') || null;
                const content = $form.find('.cs-comment-input').val().trim();

                if (!content) return;
                self.submitComment(postId, parentId, content, $(this));
            });

            // Reply button
            $(document).on('click', '.cs-reply-btn', function(e) {
                e.preventDefault();
                const commentId = $(this).data('comment-id');
                self.showReplyForm(commentId);
            });

            // Cancel reply
            $(document).on('click', '.cs-cancel-reply', function(e) {
                e.preventDefault();
                self.hideReplyForm();
            });

            // Edit comment
            $(document).on('click', '.cs-edit-btn', function(e) {
                e.preventDefault();
                const commentId = $(this).data('comment-id');
                self.enableEditMode(commentId);
            });

            // Save edit
            $(document).on('click', '.cs-save-edit', function(e) {
                e.preventDefault();
                const $form = $(this).closest('.cs-edit-form');
                const commentId = $form.data('comment-id');
                const content = $form.find('.cs-edit-input').val().trim();

                if (!content) return;
                self.saveComment(commentId, content, $(this));
            });

            // Cancel edit
            $(document).on('click', '.cs-cancel-edit', function(e) {
                e.preventDefault();
                const commentId = $(this).data('comment-id');
                self.cancelEditMode(commentId);
            });

            // React to comment
            $(document).on('click', '.cs-reaction-option', function(e) {
                e.preventDefault();
                const reaction = $(this).data('reaction');
                const commentId = $(this).closest('[data-comment-id]').data('comment-id');
                self.toggleReaction(commentId, reaction);
            });

            // Delete comment
            $(document).on('click', '.cs-delete-btn', function(e) {
                e.preventDefault();
                if (!confirm('Delete this comment? This action cannot be undone.')) return;

                const commentId = $(this).data('comment-id');
                self.deleteComment(commentId, $(this));
            });

            // Expand thread
            $(document).on('click', '.cs-expand-thread', function(e) {
                e.preventDefault();
                const commentId = $(this).data('comment-id');
                self.expandThread(commentId);
            });

            // Auto-expand textarea
            $(document).on('input', '.cs-comment-input, .cs-edit-input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 200) + 'px';
            });
        }

        /**
         * Submit a new comment
         */
        submitComment(postId, parentId, content, $button) {
            const self = this;
            $button.prop('disabled', true).addClass('loading');

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cs_submit_comment',
                    nonce: this.options.nonce,
                    post_id: postId,
                    parent_id: parentId,
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        const $form = $button.closest('.cs-comment-form');
                        const $list = $form.prev('.cs-comments-list');

                        // Add new comment to list
                        $list.append(self.renderComment(response.data));

                        // Clear form
                        $form.find('.cs-comment-input').val('');

                        // Hide reply form if replying
                        if (parentId) {
                            self.hideReplyForm();
                        }

                        // Show success feedback
                        self.showNotification('Comment posted!', 'success');
                    } else {
                        self.showNotification(response.data || 'Failed to post comment', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Error posting comment', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        }

        /**
         * Show reply form for a comment
         */
        showReplyForm(commentId) {
            // Hide any existing reply form
            $('.cs-reply-form-container').slideUp(200, function() { $(this).remove(); });

            const $comment = $(`[data-comment-id="${commentId}"]`);
            const $formContainer = $('<div class="cs-reply-form-container"></div>');

            const replyForm = `
                <div class="cs-reply-form" style="margin-top: 12px; padding: 12px; background: var(--myavana-sand); border-radius: 12px;">
                    <div class="cs-comment-form" data-post-id="${$comment.data('post-id')}" data-parent-id="${commentId}">
                        <textarea class="cs-comment-input" placeholder="Write a reply..." rows="1" maxlength="${this.options.maxChars}"></textarea>
                        <div class="cs-form-actions">
                            <button type="button" class="cs-comment-submit cs-btn-primary">Reply</button>
                            <button type="button" class="cs-cancel-reply cs-btn-secondary">Cancel</button>
                        </div>
                    </div>
                </div>
            `;

            $formContainer.html(replyForm);
            $comment.find('.cs-replies-container').first().prepend($formContainer);
            $formContainer.slideDown(200);
            $formContainer.find('.cs-comment-input').focus();

            this.state.replyingTo = commentId;
        }

        /**
         * Hide reply form
         */
        hideReplyForm() {
            $('.cs-reply-form-container').slideUp(200, function() { $(this).remove(); });
            this.state.replyingTo = null;
        }

        /**
         * Enable edit mode for a comment
         */
        enableEditMode(commentId) {
            const $comment = $(`[data-comment-id="${commentId}"]`);
            const $content = $comment.find('.cs-comment-text');
            const currentText = $content.text().trim();

            const editForm = `
                <div class="cs-edit-form" data-comment-id="${commentId}">
                    <textarea class="cs-edit-input" maxlength="${this.options.maxChars}">${escapeHtml(currentText)}</textarea>
                    <div class="cs-form-actions">
                        <button type="button" class="cs-save-edit cs-btn-primary">Save</button>
                        <button type="button" class="cs-cancel-edit cs-btn-secondary" data-comment-id="${commentId}">Cancel</button>
                    </div>
                </div>
            `;

            $content.replaceWith(editForm);
            $comment.find('.cs-edit-input').focus().select();
            this.state.editingId = commentId;
        }

        /**
         * Save edited comment
         */
        saveComment(commentId, content, $button) {
            const self = this;
            $button.prop('disabled', true).addClass('loading');

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cs_save_comment',
                    nonce: this.options.nonce,
                    comment_id: commentId,
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        const $comment = $(`[data-comment-id="${commentId}"]`);
                        const $form = $comment.find('.cs-edit-form');
                        const $newContent = $(`<p class="cs-comment-text">${escapeHtml(content)}</p>`);

                        $form.replaceWith($newContent);
                        $comment.find('.cs-comment-edited').show();

                        self.showNotification('Comment updated', 'success');
                        self.state.editingId = null;
                    } else {
                        self.showNotification(response.data || 'Failed to update comment', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Error updating comment', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        }

        /**
         * Cancel edit mode
         */
        cancelEditMode(commentId) {
            const $comment = $(`[data-comment-id="${commentId}"]`);
            const $form = $comment.find('.cs-edit-form');
            const originalText = $form.find('.cs-edit-input').val();

            $form.replaceWith(`<p class="cs-comment-text">${escapeHtml(originalText)}</p>`);
            this.state.editingId = null;
        }

        /**
         * Delete a comment
         */
        deleteComment(commentId, $button) {
            const self = this;
            $button.prop('disabled', true).addClass('loading');

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cs_delete_comment',
                    nonce: this.options.nonce,
                    comment_id: commentId
                },
                success: function(response) {
                    if (response.success) {
                        const $comment = $(`[data-comment-id="${commentId}"]`);
                        $comment.slideUp(300, function() { $(this).remove(); });
                        self.showNotification('Comment deleted', 'success');
                    } else {
                        self.showNotification('Failed to delete comment', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Error deleting comment', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        }

        /**
         * Toggle reaction on a comment
         */
        toggleReaction(commentId, reaction) {
            const self = this;

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cs_toggle_reaction',
                    nonce: this.options.nonce,
                    comment_id: commentId,
                    reaction: reaction
                },
                success: function(response) {
                    if (response.success) {
                        self.updateReactionDisplay(commentId, response.data);
                    }
                }
            });
        }

        /**
         * Update reaction display
         */
        updateReactionDisplay(commentId, reactions) {
            const $comment = $(`[data-comment-id="${commentId}"]`);
            const $reactions = $comment.find('.cs-comment-reactions');

            if (Object.keys(reactions).length === 0) {
                $reactions.remove();
                return;
            }

            let html = '<div class="cs-comment-reactions">';
            for (const [emoji, count] of Object.entries(reactions)) {
                html += `<span class="cs-reaction" data-reaction="${emoji}">${emoji} <span class="cs-reaction-count">${count}</span></span>`;
            }
            html += '</div>';

            if ($reactions.length) {
                $reactions.replaceWith(html);
            } else {
                $comment.find('.cs-comment-actions').before(html);
            }
        }

        /**
         * Expand/collapse a thread
         */
        expandThread(commentId) {
            const $container = $(`.cs-replies-container[data-parent-id="${commentId}"]`);
            const $btn = $(`.cs-expand-thread[data-comment-id="${commentId}"]`);

            if (this.state.expandedThreads.has(commentId)) {
                $container.slideUp(200);
                $btn.removeClass('expanded');
                this.state.expandedThreads.delete(commentId);
            } else {
                $container.slideDown(200);
                $btn.addClass('expanded');
                this.state.expandedThreads.add(commentId);
            }
        }

        /**
         * Setup emoji picker (future implementation)
         */
        setupEmojiPicker() {
            // Emoji picker implementation will go here
            // For now, using predefined reaction buttons
        }

        /**
         * Render a single comment
         */
        renderComment(comment) {
            const isOwn = comment.user_id === this.options.userId;
            const edited = comment.edited_at && comment.edited_at !== comment.created_at;

            return `
                <div class="cs-comment" data-comment-id="${comment.id}" data-post-id="${comment.post_id}" data-depth="${comment.depth || 0}">
                    ${this.renderCommentContent(comment, isOwn, edited)}
                    <div class="cs-replies-container" data-parent-id="${comment.id}"></div>
                </div>
            `;
        }

        /**
         * Render comment content
         */
        renderCommentContent(comment, isOwn, edited) {
            const reactions = comment.reactions || {};
            let reactionsHtml = '';

            if (Object.keys(reactions).length > 0) {
                reactionsHtml = '<div class="cs-comment-reactions">';
                for (const [emoji, count] of Object.entries(reactions)) {
                    reactionsHtml += `<span class="cs-reaction" data-reaction="${emoji}">${emoji} <span class="cs-reaction-count">${count}</span></span>`;
                }
                reactionsHtml += '</div>';
            }

            return `
                <div class="cs-comment-wrapper">
                    <img src="${escapeHtml(comment.user_avatar)}" alt="${escapeHtml(comment.display_name)}" class="cs-comment-avatar" onerror="this.onerror=null; this.src='https://www.gravatar.com/avatar/?d=mp&s=48';">

                    <div class="cs-comment-body">
                        <div class="cs-comment-header">
                            <div class="cs-comment-meta">
                                <span class="cs-comment-author">${escapeHtml(comment.display_name)}</span>
                                ${comment.is_verified_journey ? '<span class="cs-verified-badge">✓ Verified</span>' : ''}
                                <span class="cs-comment-time" title="${escapeHtml(comment.created_at)}">${escapeHtml(comment.formatted_date)}</span>
                                ${edited ? '<span class="cs-comment-edited">(edited)</span>' : ''}
                            </div>

                            ${isOwn ? `
                                <div class="cs-comment-menu">
                                    <button type="button" class="cs-menu-btn" title="Comment options">⋯</button>
                                    <div class="cs-menu-dropdown">
                                        <button type="button" class="cs-edit-btn" data-comment-id="${comment.id}">Edit</button>
                                        <button type="button" class="cs-delete-btn" data-comment-id="${comment.id}">Delete</button>
                                    </div>
                                </div>
                            ` : ''}
                        </div>

                        <p class="cs-comment-text">${escapeHtml(comment.content)}</p>

                        ${reactionsHtml}

                        <div class="cs-comment-actions">
                            <div class="cs-reactions-picker">
                                <button type="button" class="cs-reaction-option" data-reaction="❤️" title="Like">❤️</button>
                                <button type="button" class="cs-reaction-option" data-reaction="😍" title="Love">😍</button>
                                <button type="button" class="cs-reaction-option" data-reaction="🔥" title="Fire">🔥</button>
                                <button type="button" class="cs-reaction-option" data-reaction="👏" title="Clap">👏</button>
                            </div>
                            <button type="button" class="cs-reply-btn" data-comment-id="${comment.id}">Reply</button>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            const $notification = $(`
                <div class="cs-notification cs-notification-${type}">
                    ${message}
                </div>
            `);

            $('body').append($notification);

            setTimeout(() => {
                $notification.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        }
    };

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize comment system on document ready
     */
    $(document).ready(function() {
        if (window.commentSystemConfig) {
            window.commentSystem = new CommentSystem(window.commentSystemConfig);
        }
    });

})(jQuery);
