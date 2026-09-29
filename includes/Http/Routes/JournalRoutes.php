<?php
/**
 * Journal REST Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Domain\Journal\JournalRepository;
use Myavana\Next\Domain\Journal\MediaService;
use Myavana\Next\Application\JourneyService;
use Myavana\Next\Application\SmartEntryService;
use Myavana\Next\Domain\Goals\GoalRepository;

if (!defined('ABSPATH')) {
    exit;
}

class JournalRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/journal/recap', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getRecap'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/journal/recap/share', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'shareRecap'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/journal/workspace', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getWorkspaceData'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/journal/entries', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getEntries'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createEntry'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/journal/entries/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getSingleEntry'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateEntry'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteEntry'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/journal/photos', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getUserPhotos'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/journal/upload', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'uploadMedia'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);
    }

    public function getWorkspaceData(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $params = $request->get_params();
        $service = new JourneyService();
        $data = $service->getJourneyData($userId, $params);

        return $this->respondSuccess($data);
    }

    public function getEntries(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $params = $request->get_params();
        $repo = new JournalRepository();
        $data = $repo->getEntries($userId, $params);

        return $this->respondSuccess($data);
    }

    public function createEntry(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $data = $request->get_json_params() ?: $request->get_params();

        $service = new SmartEntryService();
        $result = $service->submitEntry($userId, $data);

        if (is_wp_error($result)) {
            return $this->respondError($result->get_error_message(), $result->get_error_code(), 400);
        }

        // "Who can see this? Community" means it appears in the Community feed.
        $entryId = (int) ($result['entry']['id'] ?? 0);
        if ($entryId && ($result['entry']['visibility'] ?? '') === 'community'
            && class_exists('Myavana_Community_Integration')
            && \Myavana_Community_Integration::is_entry_shareable($entryId)) {
            $shared = \Myavana_Community_Integration::share_entry($entryId, 'public');
            $result['sharedToCommunity'] = !is_wp_error($shared);
        }

        return $this->respondSuccess($result, 201);
    }

    public function getSingleEntry(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $id = (int) $request['id'];
        $repo = new JournalRepository();
        $entry = $repo->getById($id, $userId);

        if (!$entry) {
            return $this->respondError(__('Entry not found.', 'myavana-hair-journey-next'), 'not_found', 404);
        }

        return $this->respondSuccess($entry->toArray());
    }

    public function updateEntry(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $id = (int) $request['id'];
        $data = $request->get_json_params() ?: $request->get_params();

        $repo = new JournalRepository();
        $result = $repo->update($id, $userId, $data);

        if (is_wp_error($result)) {
            return $this->respondError($result->get_error_message(), $result->get_error_code(), 400);
        }

        // Only re-run progress if this edit actually touched the goal link
        // or the measurement — an unrelated edit (e.g. fixing a typo in the
        // notes) must not silently nudge progress again.
        if ($result->goalId !== '' && (array_key_exists('goalId', $data) || array_key_exists('hairLength', $data))) {
            (new GoalRepository())->applyEntryProgress($userId, $result->goalId, $result->hairLength);
        }

        return $this->respondSuccess($result->toArray());
    }

    public function deleteEntry(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $id = (int) $request['id'];
        $repo = new JournalRepository();
        $result = $repo->delete($id, $userId);

        if (is_wp_error($result)) {
            return $this->respondError($result->get_error_message(), $result->get_error_code(), 400);
        }

        return $this->respondSuccess(['deleted' => true, 'id' => $id]);
    }

    public function uploadMedia(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $files = $request->get_file_params();

        if (empty($files) || !isset($files['file'])) {
            return $this->respondError(__('No image file provided.', 'myavana-hair-journey-next'), 'missing_file', 400);
        }

        $service = new MediaService();
        $upload = MediaService::isVideo($files['file'])
            ? $service->uploadVideo($files['file'], $userId)
            : $service->uploadImage($files['file'], $userId);

        if (is_wp_error($upload)) {
            return $this->respondError($upload->get_error_message(), $upload->get_error_code(), 400);
        }

        return $this->respondSuccess($upload, 201);
    }

    public function getUserPhotos(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $repo = new JournalRepository();
        $photos = $repo->getUserPhotos($userId);

        return $this->respondSuccess([
            'photos' => $photos,
            'total' => count($photos),
        ]);
    }

    public function getRecap(\WP_REST_Request $request): \WP_REST_Response {
        $month = sanitize_text_field((string) $request->get_param('month'));
        $recap = (new \Myavana\Next\Application\RecapService())->forMonth($this->getUserId(), $month);
        if (!$recap) {
            return $this->respondError(__('There is nothing logged for that month yet.', 'myavana-hair-journey-next'), 'empty_month', 404);
        }
        return $this->respondSuccess($recap);
    }

    public function shareRecap(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params() ?: $request->get_params();
        $result = (new \Myavana\Next\Application\RecapService())->share(
            $this->getUserId(),
            sanitize_text_field((string) ($data['month'] ?? '')),
            (string) ($data['caption'] ?? '')
        );
        if (is_wp_error($result)) {
            return $this->respondError($result->get_error_message(), $result->get_error_code(), 400);
        }
        return $this->respondSuccess(['postId' => $result], 201);
    }
}
