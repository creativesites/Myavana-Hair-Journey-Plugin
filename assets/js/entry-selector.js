/* Community entry selector — shares existing Hair Journey entries without recreating them. */
(function ($) {
    'use strict';
    $(function () {
        const $modal = $('#myavana-entry-selector-modal');
        if (!$modal.length) return;
        const $checks = $modal.find('.entry-selector-checkbox');
        const $share = $modal.find('#share-selected-entries');
        const settings = window.myavanaCommunitySettings || {};
        const selected = () => $checks.filter(':checked').map(function () { return $(this).val(); }).get();
        const close = () => { $modal.fadeOut(160); $('body').css('overflow', ''); $checks.prop('checked', false).prop('disabled', false); update(); };
        const open = () => { $modal.css('display', 'flex').hide().fadeIn(160); $('body').css('overflow', 'hidden'); $modal.find('#entry-search').trigger('focus'); };
        const update = () => {
            const count = selected().length;
            $modal.find('#selected-count').text(count);
            $share.prop('disabled', count === 0);
            $checks.not(':checked').prop('disabled', count >= 10);
            $modal.find('.entry-selector-card').toggleClass('is-selected', false);
            $checks.filter(':checked').closest('.entry-selector-card').addClass('is-selected');
        };
        const filter = () => {
            const term = String($modal.find('#entry-search').val() || '').toLowerCase();
            const photo = $modal.find('#entry-filter-photos').val();
            $modal.find('.entry-selector-card').each(function () {
                const $card = $(this);
                const matchesTerm = !term || String($card.data('title') || '').includes(term);
                const hasPhoto = String($card.data('has-photos') || '') === 'yes';
                const matchesPhoto = !photo || (photo === 'with-photos' ? hasPhoto : !hasPhoto);
                $card.toggle(matchesTerm && matchesPhoto);
            });
        };
        $(document).on('click', '.share-existing-entry-btn', function (event) { event.preventDefault(); open(); });
        $modal.on('click', '.myavana-modal-close, #cancel-entry-selection, .myavana-modal-overlay', function (event) { if ($(event.target).hasClass('myavana-modal-overlay') || !$(event.target).closest('.myavana-modal-container').length || $(event.target).is('.myavana-modal-close, #cancel-entry-selection')) close(); });
        $modal.on('change', '.entry-selector-checkbox', update);
        $modal.on('click', '.entry-selector-card:not(.already-shared)', function (event) { if ($(event.target).is('input,button,label')) return; const $check = $(this).find('.entry-selector-checkbox'); if (!$check.prop('disabled')) $check.prop('checked', !$check.prop('checked')).trigger('change'); });
        $modal.on('input change', '#entry-search, #entry-filter-photos', filter);
        $modal.on('click', '#select-all-entries', function () { $modal.find('.entry-selector-card:visible:not(.already-shared) .entry-selector-checkbox:not(:disabled)').prop('checked', true); update(); });
        $modal.on('click', '#deselect-all-entries', function () { $checks.prop('checked', false); update(); });
        $share.on('click', function () {
            const ids = selected();
            if (!ids.length) return;
            const original = $share.text();
            $share.prop('disabled', true).text('Sharing…');
            $.post(settings.ajaxUrl, { action: 'myavana_bulk_share_entries', nonce: settings.nonce, entry_ids: ids, privacy: $modal.find('input[name="bulk_privacy"]:checked').val() || 'public' })
                .done(function (response) {
                    if (!response || !response.success) { window.HJN?.toast?.(response?.data?.message || 'Unable to share selected entries.', 'error'); return; }
                    window.HJN?.toast?.(response.data?.message || 'Entries shared with Community.', 'success');
                    close();
                    window.MyavanaSocialFeed?.reload?.();
                })
                .fail(function () { window.HJN?.toast?.('Connection error. Please try again.', 'error'); })
                .always(function () { $share.prop('disabled', false).text(original); });
        });
    });
})(jQuery);
