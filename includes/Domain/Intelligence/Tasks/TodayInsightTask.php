<?php
/**
 * "today_insight" task definition — purpose, expected output shape, and the
 * instructions handed to whichever provider is generating it.
 *
 * @package Myavana\Next\Domain\Intelligence\Tasks
 */

namespace Myavana\Next\Domain\Intelligence\Tasks;

if (!defined('ABSPATH')) {
    exit;
}

class TodayInsightTask {
    public const NAME = 'today_insight';

    /**
     * @return array ['required' => string[], 'instructions' => string]
     */
    public static function schema(): array {
        return [
            'required' => ['title', 'summary', 'recommendation', 'confidence', 'supporting_signals'],
            'instructions' => <<<'TXT'
Required JSON shape:
{
  "title": short headline, 6 words or fewer,
  "summary": 1-2 sentences naming the specific pattern you noticed in the facts above,
  "recommendation": one concrete, specific next step the member could take today or on their next wash day,
  "confidence": one of "low", "medium", "high" — how strongly the facts support this,
  "supporting_signals": array of 1-4 short strings, each citing one specific fact from above that led to this insight
}
TXT,
        ];
    }
}
