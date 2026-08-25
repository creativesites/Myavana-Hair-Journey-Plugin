<?php
/**
 * Journal Repository - CRUD for hair journey entries
 *
 * @package Myavana\Next\Domain\Journal
 */

namespace Myavana\Next\Domain\Journal;

if (!defined('ABSPATH')) {
    exit;
}

class JournalRepository {
    public const POST_TYPE = 'hair_journey_entry';

    /**
     * Get entries for user
     *
     * @param int $userId
     * @param array $args Filter & pagination options
     * @return array ['items' => JournalEntryEntity[], 'total' => int, 'pages' => int]
     */
    public function getEntries(int $userId, array $args = []): array {
        $page = max(1, (int) ($args['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($args['perPage'] ?? 20)));
        $filter = sanitize_key($args['filter'] ?? 'all');
        $search = sanitize_text_field($args['search'] ?? '');

        $queryArgs = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => $perPage,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $queryArgs['s'] = $search;
        }

        $validTypes = ['quick_checkin', 'wash_day', 'standard', 'length_check', 'milestone', 'setback'];
        if (in_array($filter, $validTypes, true)) {
            $queryArgs['meta_query'] = [
                [
                    'key' => 'entry_type',
                    'value' => $filter,
                    'compare' => '=',
                ],
            ];
        }

        $query = new \WP_Query($queryArgs);
        $items = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $items[] = $this->hydrateEntity($post);
            }
        }

        return [
            'items' => array_map(fn($item) => $item->toArray(), $items),
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'page' => $page,
        ];
    }

    /**
     * Get single entry by ID
     *
     * @param int $entryId
     * @param int $userId
     * @return JournalEntryEntity|null
     */
    public function getById(int $entryId, int $userId): ?JournalEntryEntity {
        $post = get_post($entryId);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        if ((int) $post->post_author !== $userId && !current_user_can('manage_options')) {
            return null;
        }

        return $this->hydrateEntity($post);
    }

    /**
     * Create new journal entry
     *
     * @param int $userId
     * @param array $data
     * @return JournalEntryEntity|\WP_Error
     */
    public function create(int $userId, array $data) {
        $title = !empty($data['title']) ? sanitize_text_field($data['title']) : sprintf(__('Hair Log - %s', 'myavana-hair-journey-next'), date_i18n('M j, Y'));
        $content = sanitize_textarea_field($data['notes'] ?? '');
        $date = !empty($data['date']) ? sanitize_text_field($data['date']) : current_time('mysql');

        $postId = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => $userId,
            'post_date' => $date,
        ], true);

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Save meta fields
        $this->saveMetaFields($postId, $data);

        return $this->hydrateEntity(get_post($postId));
    }

    /**
     * Update existing entry
     *
     * @param int $entryId
     * @param int $userId
     * @param array $data
     * @return JournalEntryEntity|\WP_Error
     */
    public function update(int $entryId, int $userId, array $data) {
        $post = get_post($entryId);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new \WP_Error('not_found', __('Entry not found.', 'myavana-hair-journey-next'));
        }

        if ((int) $post->post_author !== $userId && !current_user_can('manage_options')) {
            return new \WP_Error('unauthorized', __('You do not have permission to edit this entry.', 'myavana-hair-journey-next'));
        }

        $updateArgs = ['ID' => $entryId];
        if (isset($data['title'])) {
            $updateArgs['post_title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['notes'])) {
            $updateArgs['post_content'] = sanitize_textarea_field($data['notes']);
        }
        if (isset($data['date'])) {
            $updateArgs['post_date'] = sanitize_text_field($data['date']);
        }

        $result = wp_update_post($updateArgs, true);
        if (is_wp_error($result)) {
            return $result;
        }

        $this->saveMetaFields($entryId, $data);

        return $this->hydrateEntity(get_post($entryId));
    }

    /**
     * Delete entry
     *
     * @param int $entryId
     * @param int $userId
     * @return bool|\WP_Error
     */
    public function delete(int $entryId, int $userId) {
        $post = get_post($entryId);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new \WP_Error('not_found', __('Entry not found.', 'myavana-hair-journey-next'));
        }

        if ((int) $post->post_author !== $userId && !current_user_can('manage_options')) {
            return new \WP_Error('unauthorized', __('You do not have permission to delete this entry.', 'myavana-hair-journey-next'));
        }

        $deleted = wp_delete_post($entryId, true);
        return (bool) $deleted;
    }

    /**
     * Save metadata fields to post
     *
     * @param int $postId
     * @param array $data
     */
    private function saveMetaFields(int $postId, array $data): void {
        if (isset($data['entryType'])) {
            update_post_meta($postId, 'entry_type', sanitize_key($data['entryType']));
        }
        if (isset($data['mood'])) {
            update_post_meta($postId, 'mood', sanitize_text_field($data['mood']));
        }
        if (isset($data['moistureLevel'])) {
            update_post_meta($postId, 'moisture_level', (int) $data['moistureLevel']);
        }
        if (isset($data['scalpState'])) {
            update_post_meta($postId, 'scalp_state', sanitize_text_field($data['scalpState']));
        }
        if (isset($data['productsUsed'])) {
            $products = is_array($data['productsUsed'])
                ? array_map('sanitize_text_field', $data['productsUsed'])
                : array_filter(array_map('trim', explode(',', sanitize_text_field($data['productsUsed']))));
            update_post_meta($postId, 'products_used', $products);
        }
        if (isset($data['tags'])) {
            $tags = is_array($data['tags'])
                ? array_map('sanitize_text_field', $data['tags'])
                : array_filter(array_map('trim', explode(',', sanitize_text_field($data['tags']))));
            update_post_meta($postId, 'entry_tags', $tags);
        }
        if (isset($data['visibility'])) {
            update_post_meta($postId, 'visibility', sanitize_key($data['visibility']));
        }
        if (isset($data['aiAnalysis'])) {
            update_post_meta($postId, 'ai_analysis', sanitize_textarea_field($data['aiAnalysis']));
        }
        if (isset($data['hairLength']) && $data['hairLength'] !== '' && $data['hairLength'] !== null) {
            update_post_meta($postId, 'hair_length', (float) $data['hairLength']);
        }
        if (isset($data['hairLengthPoint'])) {
            $point = sanitize_key($data['hairLengthPoint']);
            if (in_array($point, ['crown', 'nape', 'ends', 'overall'], true)) {
                update_post_meta($postId, 'hair_length_point', $point);
            }
        }
        if (isset($data['goalId'])) {
            update_post_meta($postId, 'goal_id', sanitize_text_field($data['goalId']));
        }
        if (isset($data['changeDescription'])) {
            update_post_meta($postId, 'change_description', sanitize_textarea_field($data['changeDescription']));
        }

        // Handle attachment photos
        if (!empty($data['photos']) && is_array($data['photos'])) {
            $photoUrls = [];
            foreach ($data['photos'] as $p) {
                if (is_string($p)) {
                    $photoUrls[] = esc_url_raw($p);
                } elseif (is_array($p) && !empty($p['url'])) {
                    $photoUrls[] = esc_url_raw($p['url']);
                    if (!empty($p['attachmentId']) && !has_post_thumbnail($postId)) {
                        set_post_thumbnail($postId, (int) $p['attachmentId']);
                    }
                }
            }
            update_post_meta($postId, 'entry_photos', $photoUrls);
        } elseif (!empty($data['featuredImage'])) {
            update_post_meta($postId, 'entry_photos', [esc_url_raw($data['featuredImage'])]);
        }
    }

    /**
     * Convert WP_Post to JournalEntryEntity
     *
    /**
     * Get all eligible photo entries for user (for Before/After comparison)
     *
     * @param int $userId
     * @return array
     */
    public function getUserPhotos(int $userId): array {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC', // Earliest first so default Before is earliest
        ]);

        $photos = [];
        foreach ($posts as $post) {
            $entity = $this->hydrateEntity($post);
            if (!empty($entity->photos)) {
                foreach ($entity->photos as $idx => $photoUrl) {
                    $photos[] = [
                        'entryId' => $entity->id,
                        'title' => $entity->title,
                        'date' => date_i18n('M j, Y', strtotime($entity->date)),
                        'rawDate' => $entity->date,
                        'imageUrl' => $photoUrl,
                        'mood' => $entity->mood,
                    ];
                }
            }
        }

        return $photos;
    }

    /**
     * Chronological hair_length readings for a user, oldest first — the
     * source data for both the growth sparkline and measured goal progress.
     *
     * @param int $userId
     * @return array [{date, rawDate, length, point, entryId}]
     */
    public function getLengthHistory(int $userId): array {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'hair_length',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $history = [];
        foreach ($posts as $post) {
            $length = get_post_meta($post->ID, 'hair_length', true);
            if ($length === '' || $length === false) {
                continue;
            }
            $history[] = [
                'entryId' => (int) $post->ID,
                'date' => date_i18n('M j, Y', strtotime($post->post_date)),
                'rawDate' => (string) $post->post_date,
                'length' => (float) $length,
                'point' => (string) get_post_meta($post->ID, 'hair_length_point', true),
            ];
        }

        return $history;
    }

    /**
     * Convert WP_Post to JournalEntryEntity
     *
     * @param \WP_Post $post
     * @return JournalEntryEntity
     */
    private function hydrateEntity(\WP_Post $post): JournalEntryEntity {
        $entity = new JournalEntryEntity();
        $entity->id = (int) $post->ID;
        $entity->userId = (int) $post->post_author;
        $entity->title = (string) $post->post_title;
        $entity->date = (string) $post->post_date;
        $entity->notes = (string) $post->post_content;

        $entity->entryType = (string) (get_post_meta($post->ID, 'entry_type', true) ?: 'quick_checkin');
        $entity->mood = (string) get_post_meta($post->ID, 'mood', true);
        $rawMoisture = get_post_meta($post->ID, 'moisture_level', true);
        $entity->moistureLevel = ($rawMoisture !== '' && $rawMoisture !== false) ? (int) $rawMoisture : 0;
        $entity->scalpState = (string) get_post_meta($post->ID, 'scalp_state', true);

        $products = get_post_meta($post->ID, 'products_used', true);
        $entity->productsUsed = is_array($products) ? $products : [];

        $tags = get_post_meta($post->ID, 'entry_tags', true);
        $entity->tags = is_array($tags) ? $tags : [];

        $entity->visibility = (string) (get_post_meta($post->ID, 'visibility', true) ?: 'private');
        $entity->aiAnalysis = (string) get_post_meta($post->ID, 'ai_analysis', true);

        $rawLength = get_post_meta($post->ID, 'hair_length', true);
        $entity->hairLength = ($rawLength !== '' && $rawLength !== false) ? (float) $rawLength : null;
        $entity->hairLengthPoint = (string) get_post_meta($post->ID, 'hair_length_point', true);
        $entity->goalId = (string) get_post_meta($post->ID, 'goal_id', true);
        $entity->changeDescription = (string) get_post_meta($post->ID, 'change_description', true);

        // Featured image & photo gallery
        $thumbId = get_post_thumbnail_id($post->ID);
        if ($thumbId) {
            $entity->featuredImage = (string) wp_get_attachment_url($thumbId);
        }

        $storedPhotos = get_post_meta($post->ID, 'entry_photos', true);
        if (is_array($storedPhotos) && !empty($storedPhotos)) {
            $entity->photos = array_map('esc_url_raw', $storedPhotos);
            if (empty($entity->featuredImage) && !empty($entity->photos[0])) {
                $entity->featuredImage = $entity->photos[0];
            }
        } elseif (!empty($entity->featuredImage)) {
            $entity->photos = [$entity->featuredImage];
        }

        return $entity;
    }
}
