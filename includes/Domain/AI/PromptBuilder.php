<?php
/**
 * AI Prompt Builder - Constructs hair science and contextual prompts
 *
 * @package Myavana\Next\Domain\AI
 */

namespace Myavana\Next\Domain\AI;

use Myavana\Next\Domain\Profile\ProfileEntity;

if (!defined('ABSPATH')) {
    exit;
}

class PromptBuilder {
    /**
     * Build system context for AI hair consultations
     *
     * @param ProfileEntity $profile
     * @param array $recentLogs
     * @return string
     */
    public static function buildHairContext(ProfileEntity $profile, array $recentLogs = []): string {
        $concerns = !empty($profile->concerns) ? implode(', ', $profile->concerns) : 'General maintenance';

        $prompt = "You are MYAVANA's AI Hair Care Concierge, a leading authority on personalized hair science, curl textures, scalp wellness, and healthy regimen building.\n\n";
        $prompt .= "User Hair Profile:\n";
        $prompt .= "- Curl Pattern / Type: {$profile->hairType}\n";
        $prompt .= "- Porosity: {$profile->porosity}\n";
        $prompt .= "- Density: {$profile->density}\n";
        $prompt .= "- Length: {$profile->length}\n";
        $prompt .= "- Hair Journey Stage: {$profile->hairJourneyStage}\n";
        $prompt .= "- Top Concerns: {$concerns}\n\n";

        if (!empty($recentLogs)) {
            $prompt .= "Recent Hair Journal History:\n";
            foreach (array_slice($recentLogs, 0, 3) as $log) {
                $date = $log['date'] ?? 'Recent';
                $mood = $log['mood'] ?? 'neutral';
                $notes = $log['notes'] ?? '';
                $prompt .= "- Date: {$date} | Feel: {$mood} | Notes: {$notes}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Guidelines:\n";
        $prompt .= "1. Deliver warm, scientifically accurate, encouraging hair care recommendations.\n";
        $prompt .= "2. Clarify that you provide cosmetic hair care insights, not medical dermatological prescriptions.\n";
        $prompt .= "3. Include a concrete 'Recommended Next Step' that can be added to the user's routine.\n";

        return $prompt;
    }
}
