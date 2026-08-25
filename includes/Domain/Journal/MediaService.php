<?php
/**
 * Media Service - Secure file upload & attachment processing
 *
 * @package Myavana\Next\Domain\Journal
 */

namespace Myavana\Next\Domain\Journal;

if (!defined('ABSPATH')) {
    exit;
}

class MediaService {
    /**
     * Allowed mime types
     */
    private const ALLOWED_MIMES = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'heic|heif' => 'image/heic',
    ];

    /**
     * Maximum file size (15MB)
     */
    private const MAX_FILE_SIZE = 15728640;

    /**
     * Handle file upload from multipart request
     *
     * @param array $file $_FILES['file'] entry
     * @param int $userId Current user ID
     * @return array|\WP_Error Array with attachmentId and url, or WP_Error on failure
     */
    public function uploadImage(array $file, int $userId) {
        if (empty($file) || !isset($file['name'])) {
            return new \WP_Error('no_file', __('No file provided for upload.', 'myavana-hair-journey-next'));
        }

        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', __('There was a problem uploading that photo. Please try again.', 'myavana-hair-journey-next'));
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return new \WP_Error('file_too_large', __('Image exceeds maximum size of 15MB.', 'myavana-hair-journey-next'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => self::ALLOWED_MIMES,
        ]);

        if (isset($upload['error']) && !empty($upload['error'])) {
            return new \WP_Error('upload_error', $upload['error']);
        }

        // Every browser except Safari fails to render HEIC/HEIF in an <img>
        // tag at all — a HEIC upload would "succeed" server-side and then
        // never actually display anywhere (form preview, timeline, compare
        // slider). iPhones default to HEIC for the camera, so this is the
        // single most likely reason a member reports "my photos aren't
        // showing up." Convert to JPEG here so every photo that's accepted
        // is guaranteed viewable everywhere, or fail with a clear, actionable
        // message instead of silently accepting a file that will never show.
        if (in_array($upload['type'], ['image/heic', 'image/heif'], true)) {
            $converted = $this->convertToJpeg($upload['file']);
            if (is_wp_error($converted)) {
                @unlink($upload['file']);
                return $converted;
            }
            $upload['file'] = $converted['file'];
            $upload['url'] = $converted['url'];
            $upload['type'] = 'image/jpeg';
        }

        // Create attachment post
        $attachmentId = wp_insert_attachment([
            'guid' => $upload['url'],
            'post_mime_type' => $upload['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => $userId,
        ], $upload['file']);

        if (is_wp_error($attachmentId)) {
            return new \WP_Error('attachment_failed', __('The photo uploaded, but MYAVANA could not process it. Please try a different photo.', 'myavana-hair-journey-next'));
        }

        $attachData = wp_generate_attachment_metadata($attachmentId, $upload['file']);
        if (is_array($attachData)) {
            wp_update_attachment_metadata($attachmentId, $attachData);
        }

        return [
            'attachmentId' => (int) $attachmentId,
            'url' => esc_url_raw($upload['url']),
            'file' => $upload['file'],
        ];
    }

    /**
     * Convert a HEIC/HEIF file to JPEG via WordPress's image editor
     * abstraction (Imagick, when the host's build has HEIC support compiled
     * in — inconsistent across hosts, which is exactly why this fails
     * clearly rather than silently accepting a file most browsers can never
     * display).
     *
     * @param string $filePath Absolute path to the uploaded HEIC file.
     * @return array|\WP_Error ['file' => string, 'url' => string]
     */
    private function convertToJpeg(string $filePath) {
        $unsupportedError = new \WP_Error(
            'heic_unsupported',
            __('This server can\'t process HEIC photos yet. Please use JPEG or PNG, or set your iPhone camera to "Most Compatible" under Settings > Camera > Formats.', 'myavana-hair-journey-next')
        );

        $editor = wp_get_image_editor($filePath);
        if (is_wp_error($editor)) {
            return $unsupportedError;
        }

        $jpegPath = preg_replace('/\.[^.]+$/', '', $filePath) . '.jpg';
        $saved = $editor->save($jpegPath, 'image/jpeg');

        if (is_wp_error($saved) || empty($saved['path'])) {
            return $unsupportedError;
        }

        @unlink($filePath);

        $uploadDir = wp_upload_dir();
        $url = str_replace($uploadDir['basedir'], $uploadDir['baseurl'], $saved['path']);

        return ['file' => $saved['path'], 'url' => $url];
    }
}
