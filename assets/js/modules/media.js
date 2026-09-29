/**
 * Media helpers shared by entries, Community and Stories: a poster frame
 * from a picked video, an upload with progress, the video tile markup,
 * and one full-screen player that any [data-play-video] opens.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Media = (function() {
    'use strict';

    const VIDEO_TYPES = /^video\/(mp4|quicktime|webm|x-m4v)$/;

    function esc(value) {
        const d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function isVideoFile(file) {
        if (!file) return false;
        if (file.type) return file.type.startsWith('video/');
        return /\.(mp4|m4v|mov|webm)$/i.test(file.name || '');
    }

    function isSupportedVideo(file) {
        return VIDEO_TYPES.test(file.type || '') || /\.(mp4|m4v|mov|webm)$/i.test(file.name || '');
    }

    function maxVideoBytes() {
        const n = parseInt((window.myavanaNextData && window.myavanaNextData.maxVideoBytes) || 0, 10);
        return n > 0 ? n : 100 * 1024 * 1024;
    }

    function formatBytes(bytes) {
        return bytes >= 1048576 ? `${Math.round(bytes / 1048576)}MB` : `${Math.round(bytes / 1024)}KB`;
    }

    function formatDuration(seconds) {
        const s = Math.max(0, Math.round(Number(seconds) || 0));
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    }

    /**
     * Grab a still from a local video file, a quarter of a second or so in
     * (the first frame is often black). Resolves to { blob, duration } or
     * { blob: null, duration } when the browser can't decode the codec.
     */
    function posterFromFile(file) {
        return new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const video = document.createElement('video');
            let duration = 0;
            let settled = false;
            const finish = (blob) => {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                video.removeAttribute('src');
                video.load();
                URL.revokeObjectURL(url);
                resolve({ blob, duration });
            };
            const timer = setTimeout(() => finish(null), 10000);
            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';
            video.addEventListener('loadedmetadata', () => {
                duration = Number.isFinite(video.duration) ? video.duration : 0;
                video.currentTime = Math.min(0.6, duration > 0 ? duration / 4 : 0);
            });
            video.addEventListener('seeked', () => {
                const w = video.videoWidth;
                const h = video.videoHeight;
                if (!w || !h) return finish(null);
                const scale = Math.min(1, 1080 / Math.max(w, h));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(w * scale);
                canvas.height = Math.round(h * scale);
                try {
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob((blob) => finish(blob), 'image/jpeg', 0.82);
                } catch (e) {
                    finish(null);
                }
            });
            video.addEventListener('error', () => finish(null));
            video.src = url;
        });
    }

    /**
     * POST a file to a REST endpoint with upload progress, which fetch()
     * cannot report. Resolves to the response's data payload.
     */
    function uploadWithProgress(file, onProgress, endpoint) {
        return new Promise((resolve, reject) => {
            const base = (window.myavanaNextData && window.myavanaNextData.restUrl) || '/wp-json/myavana/v1/';
            const xhr = new XMLHttpRequest();
            xhr.open('POST', new URL(endpoint || 'journal/upload', base).toString());
            xhr.setRequestHeader('X-WP-Nonce', (window.myavanaNextData && window.myavanaNextData.nonce) || '');
            xhr.timeout = 15 * 60 * 1000;
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable && onProgress) onProgress(e.loaded / e.total);
            });
            xhr.addEventListener('load', () => {
                let json = null;
                try { json = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
                if (xhr.status >= 200 && xhr.status < 300 && json && json.success !== false) {
                    resolve(json.data || json);
                } else if (xhr.status === 413) {
                    reject(new Error(`That video is larger than this site accepts (${formatBytes(maxVideoBytes())}).`));
                } else {
                    reject(new Error((json && json.message) || 'Upload failed. Please try again.'));
                }
            });
            xhr.addEventListener('error', () => reject(new Error('Connection lost during upload. Please try again.')));
            xhr.addEventListener('timeout', () => reject(new Error('The upload took too long. Please try a shorter clip.')));
            const form = new FormData();
            form.append('file', file);
            xhr.send(form);
        });
    }

    /** Markup for a tappable video tile. */
    function videoTile(video, extraClass) {
        if (!video || !video.url) return '';
        const poster = video.poster || '';
        return `
            <button type="button" class="myavana-media-video ${extraClass || ''}" data-play-video="${esc(video.url)}" data-poster="${esc(poster)}" aria-label="Play video">
                ${poster ? `<img src="${esc(poster)}" alt="" loading="lazy" />` : '<span class="myavana-media-video-blank"></span>'}
                <span class="myavana-media-play" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" fill="currentColor"/></svg></span>
                ${video.duration ? `<span class="myavana-media-duration">${formatDuration(video.duration)}</span>` : ''}
            </button>`;
    }

    // ------------------------------------------------------------ Player
    let player = null;
    let lastFocus = null;

    function ensurePlayer() {
        if (player) return player;
        player = document.createElement('div');
        player.className = 'myavana-media-player';
        player.hidden = true;
        player.setAttribute('role', 'dialog');
        player.setAttribute('aria-modal', 'true');
        player.setAttribute('aria-label', 'Video');
        player.innerHTML = `
            <div class="myavana-media-player-backdrop" data-player-close></div>
            <div class="myavana-media-player-stage">
                <video controls playsinline preload="metadata"></video>
            </div>
            <button type="button" class="myavana-media-player-close" data-player-close aria-label="Close video">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>`;
        document.body.appendChild(player);
        player.addEventListener('click', (e) => {
            if (e.target.closest('[data-player-close]')) closePlayer();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && player && !player.hidden) closePlayer();
        });
        return player;
    }

    function openPlayer(url, poster) {
        if (!url) return;
        ensurePlayer();
        lastFocus = document.activeElement;
        const video = player.querySelector('video');
        video.poster = poster || '';
        video.src = url;
        player.hidden = false;
        requestAnimationFrame(() => player.classList.add('is-open'));
        document.documentElement.classList.add('myavana-media-player-open');
        const p = video.play();
        if (p && p.catch) p.catch(() => {});
        player.querySelector('.myavana-media-player-close').focus({ preventScroll: true });
    }

    function closePlayer() {
        if (!player) return;
        const video = player.querySelector('video');
        video.pause();
        video.removeAttribute('src');
        video.load();
        player.classList.remove('is-open');
        player.hidden = true;
        document.documentElement.classList.remove('myavana-media-player-open');
        if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-play-video]');
        if (!trigger) return;
        e.preventDefault();
        e.stopPropagation();
        openPlayer(trigger.getAttribute('data-play-video'), trigger.getAttribute('data-poster'));
    }, true);

    return {
        isVideoFile,
        isSupportedVideo,
        maxVideoBytes,
        formatBytes,
        formatDuration,
        posterFromFile,
        uploadWithProgress,
        videoTile,
        openPlayer,
        closePlayer,
    };
})();
