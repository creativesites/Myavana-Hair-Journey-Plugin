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
        'heic' => 'image/heic',
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
     * @return array|\WP_Error Array with url and attachment_id, or WP_Error on failure
     */
    public function uploadImage(array $file, int $userId) {
        if (empty($file) || !isset($file['name'])) {
            return new \WP_Error('no_file', __('No file provided for upload.', 'myavana-hair-journey-next'));
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

        // Create attachment post
        $attachmentId = wp_insert_attachment([
            'guid' => $upload['url'],
            'post_mime_type' => $upload['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => $userId,
        ], $upload['file']);

        if (!is_wp_error($attachmentId)) {
            $attachData = wp_generate_attachment_metadata($attachmentId, $upload['file']);
            wp_update_attachment_metadata($attachmentId, $attachData);
        }

        return [
            'attachmentId' => (int) $attachmentId,
            'url' => esc_url_raw($upload['url']),
            'file' => $upload['file'],
        ];
    }
}
