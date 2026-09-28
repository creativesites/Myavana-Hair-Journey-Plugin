<?php
/**
 * What the current launch exposes. Hair data (type, porosity, regimens) is to
 * come from MYAVANA AI, so until that integration lands the app focuses on
 * the Hair Journey timeline and goals: no self-reported hair-type wizard and
 * no Routines area, and hair analysis links out to the HairAI subscription.
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

if (!defined('ABSPATH')) {
    exit;
}

class LaunchScope {
    public const ROUTINES_ENABLED = false;
    public const ONBOARDING_WIZARD_ENABLED = false;

    private const HAIR_AI_URL = 'https://www.myavana.com/pages/consumer';

    /**
     * The restored post-signup welcome pop-up shows once, to members whose
     * signup left onboarding pending and who haven't finished or skipped it.
     */
    public static function shouldShowSignupWelcome(int $userId): bool {
        return $userId > 0
            && get_user_meta($userId, 'myavana_onboarding_status', true) === 'pending'
            && empty(get_user_meta($userId, 'myavana_onboarding_completed', true));
    }

    /**
     * The HairAI subscription page. The admin setting may still hold an
     * in-app hash (e.g. "#routine") from an earlier release; only a full URL
     * overrides the default.
     */
    public static function hairAiUrl(): string {
        $configured = trim((string) get_option('myavana_next_hair_analysis_url', ''));
        return preg_match('#^https?://#i', $configured) ? $configured : self::HAIR_AI_URL;
    }
}
