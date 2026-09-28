/* Community entry selector — shares existing Hair Journey entries without recreating them. */
(function ($) {
    'use strict';
    $(function () {
        const $modal = $('#myavana-entry-selector-modal');
        if (!$modal.length) return;
        const $checks = $modal.find('.entry-selector-checkbox');
        const $share = $modal.find('#share-selected-entries');
        const settings = window.myavanaCommunitySettings || {};
        const toast = (message, type) => {
            if (window.MyavanaNext && MyavanaNext.API && MyavanaNext.API.showToast) MyavanaNext.API.showToast(message, type);
            else if (window.HJN && window.HJN.toast) window.HJN.toast(message, type);
        };
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
        $modal.on('click', '.myavana-modal-close, #cancel-entry-selection, .myavana-modal-overlay', function (event) { event.preventDefault(); close(); });
        $(document).on('keydown', function (event) { if (event.key === 'Escape' && $modal.is(':visible')) close(); });
        $modal.on('change', '.entry-selector-checkbox', update);
        // Cards are <label>s wrapping their checkbox, so the browser toggles it.
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
                    if (!response || !response.success) { toast(response?.data?.message || 'We couldn\'t share that just now. Please try again.', 'error'); return; }
                    toast(response.data?.message || 'Shared with the community.', 'success');
                    close();
                    window.MyavanaSocialFeed?.reload?.();
                })
                .fail(function () { toast('Connection error. Please try again.', 'error'); })
                .always(function () { $share.text(original); update(); });
        });
    });
})(jQuery);
