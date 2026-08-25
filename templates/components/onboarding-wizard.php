<?php
/**
 * Post-Signup Onboarding Wizard
 *
 * Collects the hair-characteristics fields ProfileEntity/ProfileRepository
 * already model (hair type, porosity, density, length, concerns, goals)
 * but that nothing in the signup flow ever asked for — replacing the
 * decorative, non-functional texture picker on the logged-out Discovery
 * screen with a real intake that actually persists.
 *
 * Shown once per account (see Assets::enqueue()'s showOnboardingWizard
 * gate) via MyavanaNext.Onboarding.init(), driven entirely by JS —
 * everything here starts hidden.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

$hairTypes = [
    '1A' => '1A · Straight, fine', '1B' => '1B · Straight, medium', '1C' => '1C · Straight, coarse',
    '2A' => '2A · Wavy, fine', '2B' => '2B · Wavy, medium', '2C' => '2C · Wavy, coarse',
    '3A' => '3A · Loose curls', '3B' => '3B · Medium curls', '3C' => '3C · Tight curls',
    '4A' => '4A · Soft coils', '4B' => '4B · Z-pattern coils', '4C' => '4C · Tight, kinky coils',
];

$concernOptions = [
    'Dryness & Moisture', 'Breakage & Shedding', 'Length Retention',
    'Scalp Care & Dandruff', 'Frizz Control', 'Curl Definition',
    'Heat Damage Recovery', 'Color Treated Care', 'Volume & Thinning',
];

$goalOptions = [
    'Grow Longer Hair', 'Improve Moisture & Hydration', 'Strengthen & Reduce Breakage',
    'Define My Curls/Waves', 'Repair Heat/Color Damage', 'Healthier Scalp',
    'Simplify My Routine', 'Track My Progress Over Time',
];
?>
<div class="myavana-modal-backdrop myavana-onboarding-backdrop" id="myavana-onboarding-wizard" role="dialog" aria-modal="true" aria-labelledby="onboarding-wizard-title" style="display:none;">
    <div class="myavana-modal myavana-onboarding-modal">
        <button type="button" class="myavana-onboarding-skip" id="myavana-onboarding-skip"><?php esc_html_e('Skip for now', 'myavana-hair-journey-next'); ?></button>

        <div class="myavana-onboarding-header">
            <p class="myavana-onboarding-step-label" id="onboarding-step-label"><?php esc_html_e('Step 1 of 3', 'myavana-hair-journey-next'); ?></p>
            <h2 id="onboarding-wizard-title"><?php esc_html_e("Let's get your HairID set up", 'myavana-hair-journey-next'); ?></h2>
            <p class="myavana-onboarding-subtitle"><?php esc_html_e('A minute of setup so your routine, insights, and community matches are actually about your hair.', 'myavana-hair-journey-next'); ?></p>
            <div class="myavana-onboarding-progress">
                <div class="myavana-onboarding-progress-bar active" data-onboarding-bar="1"></div>
                <div class="myavana-onboarding-progress-bar" data-onboarding-bar="2"></div>
                <div class="myavana-onboarding-progress-bar" data-onboarding-bar="3"></div>
            </div>
        </div>

        <!-- STEP 1: Hair Type -->
        <div class="myavana-onboarding-step active" data-onboarding-step="1">
            <label class="myavana-label"><?php esc_html_e('What best describes your hair type?', 'myavana-hair-journey-next'); ?></label>
            <div class="myavana-onboarding-type-grid" id="myavana-onboarding-type-grid">
                <?php foreach ($hairTypes as $value => $label) : ?>
                    <button type="button" class="myavana-onboarding-type-card" data-hair-type="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></button>
                <?php endforeach; ?>
            </div>
            <p class="myavana-onboarding-hint"><?php esc_html_e('Not sure? Pick the closest match — you can refine it anytime from your profile.', 'myavana-hair-journey-next'); ?></p>
        </div>

        <!-- STEP 2: Porosity, Density, Length -->
        <div class="myavana-onboarding-step" data-onboarding-step="2">
            <div class="myavana-form-group">
                <label class="myavana-label" for="myavana-onboarding-porosity"><?php esc_html_e('Porosity', 'myavana-hair-journey-next'); ?></label>
                <select id="myavana-onboarding-porosity" class="myavana-input">
                    <option value=""><?php esc_html_e('Not sure yet', 'myavana-hair-journey-next'); ?></option>
                    <option value="Low"><?php esc_html_e('Low — resists moisture', 'myavana-hair-journey-next'); ?></option>
                    <option value="Medium"><?php esc_html_e('Medium — holds moisture well', 'myavana-hair-journey-next'); ?></option>
                    <option value="High"><?php esc_html_e('High — absorbs and loses moisture fast', 'myavana-hair-journey-next'); ?></option>
                </select>
            </div>
            <div class="myavana-form-group">
                <label class="myavana-label" for="myavana-onboarding-density"><?php esc_html_e('Density', 'myavana-hair-journey-next'); ?></label>
                <select id="myavana-onboarding-density" class="myavana-input">
                    <option value=""><?php esc_html_e('Not sure yet', 'myavana-hair-journey-next'); ?></option>
                    <option value="Low"><?php esc_html_e('Low (fine/thin)', 'myavana-hair-journey-next'); ?></option>
                    <option value="Medium"><?php esc_html_e('Medium', 'myavana-hair-journey-next'); ?></option>
                    <option value="High"><?php esc_html_e('High (thick/full)', 'myavana-hair-journey-next'); ?></option>
                </select>
            </div>
            <div class="myavana-form-group">
                <label class="myavana-label" for="myavana-onboarding-length"><?php esc_html_e('Current length', 'myavana-hair-journey-next'); ?></label>
                <select id="myavana-onboarding-length" class="myavana-input">
                    <option value=""><?php esc_html_e('Not sure yet', 'myavana-hair-journey-next'); ?></option>
                    <option value="Short"><?php esc_html_e('Short (ear/jaw)', 'myavana-hair-journey-next'); ?></option>
                    <option value="Medium"><?php esc_html_e('Medium (shoulder/collarbone)', 'myavana-hair-journey-next'); ?></option>
                    <option value="Long"><?php esc_html_e('Long (armpit/mid-back)', 'myavana-hair-journey-next'); ?></option>
                    <option value="Extra Long"><?php esc_html_e('Extra long (waist+)', 'myavana-hair-journey-next'); ?></option>
                </select>
            </div>
        </div>

        <!-- STEP 3: Concerns + Goals -->
        <div class="myavana-onboarding-step" data-onboarding-step="3">
            <label class="myavana-label"><?php esc_html_e('Any hair concerns right now?', 'myavana-hair-journey-next'); ?></label>
            <div class="myavana-onboarding-chip-grid" id="myavana-onboarding-concerns">
                <?php foreach ($concernOptions as $concern) : ?>
                    <button type="button" class="myavana-onboarding-chip" data-concern="<?php echo esc_attr($concern); ?>"><?php echo esc_html($concern); ?></button>
                <?php endforeach; ?>
            </div>

            <label class="myavana-label myavana-onboarding-second-label"><?php esc_html_e("What's your main goal?", 'myavana-hair-journey-next'); ?></label>
            <div class="myavana-onboarding-chip-grid" id="myavana-onboarding-goals">
                <?php foreach ($goalOptions as $goal) : ?>
                    <button type="button" class="myavana-onboarding-chip" data-goal="<?php echo esc_attr($goal); ?>"><?php echo esc_html($goal); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="myavana-onboarding-footer">
            <button type="button" class="myavana-btn myavana-btn-outline" id="myavana-onboarding-back" style="visibility:hidden;">
                <?php esc_html_e('← Back', 'myavana-hair-journey-next'); ?>
            </button>
            <button type="button" class="myavana-btn myavana-btn-primary" id="myavana-onboarding-next">
                <?php esc_html_e('Continue', 'myavana-hair-journey-next'); ?>
            </button>
        </div>
    </div>
</div>
