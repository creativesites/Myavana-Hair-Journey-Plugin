/**
 * Touch & Mouse Accessible Before/After Photo Comparison Slider
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.CompareSlider = (function() {
    'use strict';

    function init(containerElement) {
        if (!containerElement) return;

        const handle = containerElement.querySelector('.myavana-compare-handle');
        const afterClip = containerElement.querySelector('.myavana-compare-after');
        const afterImg = afterClip?.querySelector('img');

        if (!handle || !afterClip || !afterImg) return;

        let isDragging = false;

        function updateSlider(percentage) {
            const clamped = Math.max(0, Math.min(100, percentage));
            afterClip.style.width = `${clamped}%`;
            handle.style.left = `${clamped}%`;

            // Adjust image width to match wrapper
            const rect = containerElement.getBoundingClientRect();
            afterImg.style.width = `${rect.width}px`;
        }

        function onPointerMove(e) {
            if (!isDragging) return;
            const rect = containerElement.getBoundingClientRect();
            const clientX = e.clientX !== undefined ? e.clientX : (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
            const offset = clientX - rect.left;
            const percent = (offset / rect.width) * 100;
            updateSlider(percent);
        }

        function startDrag(e) {
            isDragging = true;
            onPointerMove(e);
        }

        function stopDrag() {
            isDragging = false;
        }

        handle.addEventListener('mousedown', startDrag);
        containerElement.addEventListener('mousedown', startDrag);
        window.addEventListener('mousemove', onPointerMove);
        window.addEventListener('mouseup', stopDrag);

        handle.addEventListener('touchstart', startDrag, { passive: true });
        containerElement.addEventListener('touchstart', startDrag, { passive: true });
        window.addEventListener('touchmove', onPointerMove, { passive: true });
        window.addEventListener('touchend', stopDrag);

        // Keyboard accessibility
        handle.setAttribute('tabindex', '0');
        handle.addEventListener('keydown', (e) => {
            let current = parseFloat(afterClip.style.width) || 50;
            if (e.key === 'ArrowLeft') {
                updateSlider(current - 5);
            } else if (e.key === 'ArrowRight') {
                updateSlider(current + 5);
            }
        });

        // Initialize at 50%
        updateSlider(50);
    }

    return {
        init,
    };
})();
