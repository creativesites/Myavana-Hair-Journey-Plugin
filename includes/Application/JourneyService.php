<?php
/**
 * Journey Service - Aggregator for the Journey Workspace (Timeline, Story mode,
 * growth chart, goals/routines rails).
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

use Myavana\Next\Domain\Journal\JournalRepository;
use Myavana\Next\Domain\Goals\GoalRepository;
use Myavana\Next\Domain\Routine\RoutineRepository;
use Myavana\Next\Domain\Profile\ProfileRepository;

if (!defined('ABSPATH')) {
    exit;
}

class JourneyService {
    private JournalRepository $journalRepo;
    private GoalRepository $goalRepo;
    private RoutineRepository $routineRepo;
    private ProfileRepository $profileRepo;

    public function __construct(
        ?JournalRepository $journalRepo = null,
        ?GoalRepository $goalRepo = null,
        ?RoutineRepository $routineRepo = null,
        ?ProfileRepository $profileRepo = null
    ) {
        $this->journalRepo = $journalRepo ?: new JournalRepository();
        $this->goalRepo = $goalRepo ?: new GoalRepository();
        $this->routineRepo = $routineRepo ?: new RoutineRepository();
        $this->profileRepo = $profileRepo ?: new ProfileRepository();
    }

    /**
     * Get aggregated Journey view payload
     *
     * @param int $userId
     * @param array $args
     * @return array
     */
    public function getJourneyData(int $userId, array $args = []): array {
        $entriesData = $this->journalRepo->getEntries($userId, array_merge($args, ['perPage' => 100]));
        $entries = $entriesData['items'];
        $lengthHistory = $this->journalRepo->getLengthHistory($userId);

        // Extract compare/story photos (entries with valid image attachments)
        $photoEntries = [];
        foreach ($entries as $entry) {
            if (!empty($entry['featuredImage']) || !empty($entry['photos'])) {
                $photoEntries[] = [
                    'id' => $entry['id'],
                    'date' => $entry['date'],
                    'title' => $entry['title'],
                    'imageUrl' => $entry['featuredImage'] ?: ($entry['photos'][0] ?? ''),
                    'mood' => $entry['mood'],
                    'entryType' => $entry['entryType'],
                    'caption' => $entry['notes'],
                ];
            }
        }

        $profile = $this->profileRepo->getByUserId($userId);

        return [
            'timeline' => $entriesData,
            'groups' => $this->buildMonthGroups($entries),
            'photoEntries' => $photoEntries,
            'heatmap' => $this->buildConsistencyHeatmap($entries),
            'sparkline' => $this->buildGrowthSparkline($lengthHistory),
            'goals' => array_slice($this->goalRepo->getGoals($userId), 0, 4),
            'routines' => array_slice($this->routineRepo->getRoutines($userId), 0, 4),
            'stats' => [
                'currentLength' => !empty($lengthHistory) ? end($lengthHistory)['length'] : null,
                'healthScore' => max(0, min(100, (int) $profile->hairHealthRating * 10)),
                'totalEntries' => $entriesData['total'],
                'photoCount' => count($photoEntries),
            ],
            'summary' => [
                'totalEntries' => $entriesData['total'],
                'photoCount' => count($photoEntries),
            ],
        ];
    }

    /**
     * Group entries by month (newest first), each with a short summary line
     * and a "time since previous entry" gap label per card — matches the
     * Timeline view's month-grouped layout.
     *
     * @param array $entries Flat entries list, already newest-first.
     * @return array [{month, summary, entries: [...]}]
     */
    private function buildMonthGroups(array $entries): array {
        $groups = [];
        $previousDate = null;

        foreach ($entries as $entry) {
            $timestamp = strtotime($entry['date']);
            $monthKey = date('F Y', $timestamp);

            if (!isset($groups[$monthKey])) {
                $groups[$monthKey] = ['month' => $monthKey, 'count' => 0, 'entries' => []];
            }
            $groups[$monthKey]['count']++;

            $gap = '';
            if ($previousDate !== null) {
                $days = (int) round(($previousDate - $timestamp) / DAY_IN_SECONDS);
                if ($days >= 1) {
                    $gap = sprintf(_n('%d day before', '%d days before', $days, 'myavana-hair-journey-next'), $days);
                }
            }
            $previousDate = $timestamp;

            $entry['gap'] = $gap;
            $entry['showGap'] = $gap !== '';
            $entry['displayDate'] = date_i18n('M j, Y', $timestamp);
            $groups[$monthKey]['entries'][] = $entry;
        }

        return array_values(array_map(function ($group) {
            $group['summary'] = sprintf(
                /* translators: %d: number of entries logged that month */
                _n('%d entry', '%d entries', $group['count'], 'myavana-hair-journey-next'),
                $group['count']
            );
            return $group;
        }, $groups));
    }

    /**
     * Build the growth sparkline's chart data from chronological length
     * readings — the SVG polyline points, filled-area path, and summary
     * labels the Journey sidebar's chart component expects.
     *
     * @param array $lengthHistory Oldest-first, from JournalRepository::getLengthHistory().
     * @return array|null Null when there isn't enough data to chart yet.
     */
    private function buildGrowthSparkline(array $lengthHistory): ?array {
        if (count($lengthHistory) < 2) {
            return null;
        }

        $values = array_column($lengthHistory, 'length');
        $min = min($values);
        $max = max($values);
        $range = ($max - $min) ?: 1;

        $width = 254;
        $height = 76;
        $count = count($values);
        $points = [];

        foreach ($values as $i => $value) {
            $x = $count > 1 ? ($i / ($count - 1)) * $width : 0;
            $y = $height - (($value - $min) / $range) * $height;
            $points[] = round($x, 1) . ',' . round($y, 1);
        }

        $areaPath = 'M0,' . $height . ' L' . implode(' L', $points) . ' L' . $width . ',' . $height . ' Z';
        $last = end($lengthHistory);
        $first = $lengthHistory[0];
        $gain = $last['length'] - $first['length'];

        return [
            'points' => implode(' ', $points),
            'area' => $areaPath,
            'current' => $last['length'] . '"',
            'gain' => ($gain >= 0 ? '+' : '') . round($gain, 1) . '" since ' . $first['date'],
            'first' => $first['date'],
            'last' => $last['date'],
            'lastX' => end($points) ? explode(',', end($points))[0] : 0,
            'lastY' => end($points) ? explode(',', end($points))[1] : 0,
            'checks' => sprintf(_n('%d check', '%d checks', $count, 'myavana-hair-journey-next'), $count),
        ];
    }

    /**
     * Build 60-day activity map
     *
     * @param array $entries
     * @return array
     */
    private function buildConsistencyHeatmap(array $entries): array {
        $activityByDate = [];
        foreach ($entries as $e) {
            $date = substr($e['date'], 0, 10);
            $activityByDate[$date] = ($activityByDate[$date] ?? 0) + 1;
        }

        $days = [];
        $startDate = strtotime('-59 days');
        $endDate = time();

        for ($curr = $startDate; $curr <= $endDate; $curr += 86400) {
            $dStr = date('Y-m-d', $curr);
            $count = $activityByDate[$dStr] ?? 0;
            $level = 0;
            if ($count >= 3) {
                $level = 3;
            } elseif ($count === 2) {
                $level = 2;
            } elseif ($count === 1) {
                $level = 1;
            }

            $days[] = [
                'date' => $dStr,
                'count' => $count,
                'level' => $level,
                'label' => date('M j', $curr),
            ];
        }

        return $days;
    }
}
