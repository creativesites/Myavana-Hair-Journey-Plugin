<?php
/**
 * Journal Entry Entity Model
 *
 * @package Myavana\Next\Domain\Journal
 */

namespace Myavana\Next\Domain\Journal;

if (!defined('ABSPATH')) {
    exit;
}

class JournalEntryEntity {
    public int $id = 0;
    public int $userId = 0;
    public string $title = '';
    public string $date = '';
    public string $entryType = 'quick_checkin'; // 'quick_checkin' | 'wash_day' | 'standard' | 'length_check' | 'milestone' | 'setback'
    public string $mood = 'happy'; // 'happy', 'neutral', 'concerned', 'excited'
    public int $moistureLevel = 7; // 1-10
    public string $scalpState = 'Balanced';
    public array $productsUsed = [];
    public array $photos = [];
    public string $featuredImage = '';
    public string $notes = '';
    public string $aiAnalysis = '';
    public array $tags = [];
    public string $visibility = 'private'; // 'private', 'community', 'twins'
    public ?float $hairLength = null; // numeric measurement, e.g. 6.5 — only 'length_check' entries set this
    public string $hairLengthPoint = ''; // 'crown' | 'nape' | 'ends' | 'overall'
    public string $goalId = ''; // links to a GoalRepository goal's string id
    public string $changeDescription = ''; // 'setback' entries' "what I changed"

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'title' => $this->title,
            'date' => $this->date,
            'entryType' => $this->entryType,
            'mood' => $this->mood,
            'moistureLevel' => $this->moistureLevel,
            'scalpState' => $this->scalpState,
            'productsUsed' => $this->productsUsed,
            'photos' => $this->photos,
            'featuredImage' => $this->featuredImage,
            'notes' => $this->notes,
            'aiAnalysis' => $this->aiAnalysis,
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'hairLength' => $this->hairLength,
            'hairLengthPoint' => $this->hairLengthPoint,
            'goalId' => $this->goalId,
            'changeDescription' => $this->changeDescription,
        ];
    }
}
