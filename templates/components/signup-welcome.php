<?php
/**
 * Post-signup welcome pop-up, restored from the original Hair Journey plugin
 * (templates/auth/onboarding-modal.php): the member's name and hair goals.
 * Hair type is intentionally not asked — that will come from MYAVANA AI.
 * Shown once, to new signups whose onboarding is still pending.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

$myaSignupUser = wp_get_current_user();
$myaSignupName = $myaSignupUser->first_name ?: ($myaSignupUser->display_name ?: '');
$myaSignupGoals = [
    'growth' => __('Hair Growth', 'myavana-hair-journey-next'),
    'moisture' => __('More Moisture', 'myavana-hair-journey-next'),
    'strength' => __('Stronger Hair', 'myavana-hair-journey-next'),
    'damage' => __('Repair Damage', 'myavana-hair-journey-next'),
    'definition' => __('Better Definition', 'myavana-hair-journey-next'),
    'frizz' => __('Reduce Frizz', 'myavana-hair-journey-next'),
    'shine' => __('More Shine', 'myavana-hair-journey-next'),
    'volume' => __('Add Volume', 'myavana-hair-journey-next'),
];
?>
<style>
/* MYAVANA Onboarding Modal */
.mya-signup-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(34, 35, 35, 0.9);
    backdrop-filter: blur(10px);
    z-index: 1000000;
    align-items: center;
    justify-content: center;
    animation: myaSignupFadeIn 0.3s ease;
    font-family: 'Archivo', -apple-system, sans-serif;
}

.mya-signup-overlay.show {
    display: flex;
}

@keyframes myaSignupFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.mya-signup-card {
    border-radius: 28px;
    width: 95%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.3);
    background: linear-gradient(160deg, #ffffff 0%, #fffaf7 100%);
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.24);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    animation: myaSignupSlideUp 0.4s ease;
}

@keyframes myaSignupSlideUp {
    from { transform: translateY(40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.mya-signup-header {
    background: linear-gradient(135deg, var(--myavana-coral, #e7a690) 0%, var(--myavana-light-coral, #fce5d7) 100%);
    padding: 30px;
    text-align: center;
    position: relative;
}

.mya-signup-logo {
    font-family: 'Archivo Black', sans-serif;
    font-size: 24px;
    color: white;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 8px;
}

.mya-signup-title {
    color: white;
    font-size: 20px;
    font-weight: 600;
    margin: 0;
}

.mya-signup-subtitle {
    margin: 8px 0 0;
    color: rgba(255,255,255,0.86);
    font-size: 14px;
}

.mya-signup-skip {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
}

.mya-signup-skip:hover {
    background: rgba(255,255,255,0.3);
}

.mya-signup-progress {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 20px;
    background: #f8f9fa;
}

.mya-signup-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ddd;
    transition: all 0.3s ease;
}

.mya-signup-dot.active {
    background: var(--myavana-coral, #e7a690);
    transform: scale(1.2);
}

.mya-signup-dot.completed {
    background: var(--myavana-coral, #e7a690);
    opacity: 0.55;
}

.mya-signup-body {
    padding: 30px;
    overflow-y: auto;
    max-height: 60vh;
}

.mya-signup-step {
    display: none;
}

.mya-signup-step.active {
    display: block;
    animation: myaSignupStepFade 0.3s ease;
}

@keyframes myaSignupStepFade {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}

.mya-signup-step h3 {
    font-size: 22px;
    font-weight: 700;
    color: var(--myavana-onyx, #222323);
    margin: 0 0 8px 0;
}

.mya-signup-step p {
    color: #666;
    margin: 0 0 24px 0;
    font-size: 14px;
}

.mya-signup-field {
    margin-bottom: 24px;
}

.mya-signup-helper {
    margin: 0 0 20px;
    padding: 14px 16px;
    border-radius: 16px;
    background: rgba(255,255,255,0.62);
    border: 1px solid rgba(74,77,104,0.08);
    color: var(--myavana-blueberry, #4a4d68);
    font-size: 13px;
    line-height: 1.6;
}

.mya-signup-field label {
    display: block;
    font-weight: 600;
    color: var(--myavana-onyx, #222323);
    margin-bottom: 8px;
    font-size: 14px;
}

.mya-signup-field input[type="text"] {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #eeece1;
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.mya-signup-field input:focus {
    outline: none;
    border-color: var(--myavana-coral, #e7a690);
}

/* Selection Grid */
.mya-signup-select-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.mya-signup-select-option {
    border: 2px solid #eeece1;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
}

.mya-signup-select-option:hover {
    border-color: var(--myavana-coral, #e7a690);
    background: #fef7f4;
}

.mya-signup-select-option.selected {
    border-color: var(--myavana-coral, #e7a690);
    background: linear-gradient(135deg, rgba(231,166,144,0.1) 0%, rgba(252,229,215,0.3) 100%);
}

.mya-signup-select-option .icon {
    font-size: 28px;
    margin-bottom: 8px;
}

.mya-signup-select-option .label {
    font-weight: 600;
    color: var(--myavana-onyx, #222323);
    font-size: 14px;
}

/* Multi-select chips */
.mya-signup-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.mya-signup-chip {
    font-family: inherit;
    font-size: inherit;
    cursor: pointer;
    padding: 10px 18px;
    border: 2px solid #eeece1;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 14px;
    font-weight: 500;
    background: white;
}

.mya-signup-chip:hover {
    border-color: var(--myavana-coral, #e7a690);
}

.mya-signup-chip.selected {
    border-color: var(--myavana-coral, #e7a690);
    background: var(--myavana-coral, #e7a690);
    color: white;
}

/* Footer */
.mya-signup-footer {
    padding: 20px 30px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mya-signup-back {
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    font-size: 14px;
    padding: 10px;
}

.mya-signup-back:hover {
    color: var(--myavana-onyx, #222323);
}

.mya-signup-next {
    background: linear-gradient(135deg, var(--myavana-coral, #e7a690) 0%, #d4956f 100%);
    border: none;
    color: white;
    padding: 14px 32px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mya-signup-next:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(231, 166, 144, 0.4);
}

.mya-signup-next:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.mya-signup-next.loading {
    color: transparent;
    position: relative;
}

.mya-signup-next.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin: -10px 0 0 -10px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Mobile */
@media (max-width: 768px) {
    .mya-signup-overlay {
        padding: 12px;
        align-items: flex-start;
        overflow-y: auto;
    }

    .mya-signup-card {
        width: 100%;
        max-height: calc(100dvh - 24px);
        border-radius: 14px;
    }

    .mya-signup-header {
        padding: 22px 16px;
    }

    .mya-signup-title {
        font-size: 18px;
    }

    .mya-signup-body {
        padding: 20px 16px;
        max-height: calc(100dvh - 220px);
    }

    .mya-signup-footer {
        padding: 14px 16px;
    }

    .mya-signup-next {
        padding: 12px 22px;
    }
}

@media (max-width: 480px) {
    .mya-signup-overlay {
        padding: 0;
    }

    .mya-signup-card {
        width: 100%;
        height: 100%;
        max-height: 100vh;
        border-radius: 0;
    }

    .mya-signup-select-grid {
        grid-template-columns: 1fr;
    }

    .mya-signup-body {
        max-height: calc(100vh - 250px);
    }
}

/* Match the app: soft blush header, real wordmark, onyx primary, ink text. */
.mya-signup-header { background: linear-gradient(160deg, #fce5d7 0%, #fdf8f5 100%); padding: 56px 28px 28px; }
.mya-signup-logo { margin: 0 auto 14px; }
.mya-signup-logo img { display: block; width: 132px; height: auto; margin: 0 auto; }
.mya-signup-title { color: #222323; font-size: 22px; line-height: 1.15; }
.mya-signup-subtitle { color: #6b6b6b; }
.mya-signup-skip { top: 16px; right: 16px; background: transparent; color: #9b5a49; padding: 8px 10px; font: 600 12px/1 Archivo, sans-serif; letter-spacing: 0.08em; text-transform: uppercase; }
.mya-signup-skip:hover { background: rgba(155, 90, 73, 0.08); }
.mya-signup-field input[type="text"] { border: 1px solid #eadfd8; border-radius: 14px; color: #222323; -webkit-text-fill-color: #222323; background: #fff; margin: 0; }
.mya-signup-field input:focus { box-shadow: 0 0 0 4px rgba(231, 166, 144, 0.2); }
.mya-signup-next { background: #222323; border-radius: 9999px; padding: 0 28px; min-height: 48px; font: 700 12px/1 Archivo, sans-serif; letter-spacing: 0.12em; text-transform: uppercase; }
.mya-signup-next:hover { background: #363737; box-shadow: 0 10px 24px rgba(34, 35, 35, 0.16); }
.mya-signup-chip { color: #222323; }
.mya-signup-chip.selected, .mya-signup-chip.selected:hover { border-color: #222323; background: #222323; color: #fff; }
.mya-signup-chip:focus { outline: none; }
.mya-signup-chip:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(231, 166, 144, 0.35); }
</style>

<div class="mya-signup-overlay" id="myaSignupOverlay" role="dialog" aria-modal="true" aria-labelledby="myaSignupTitle">
    <div class="mya-signup-card">
        <div class="mya-signup-header">
            <button type="button" class="mya-signup-skip" id="myaSignupSkip"><?php esc_html_e('Skip for now', 'myavana-hair-journey-next'); ?></button>
            <div class="mya-signup-logo"><img src="<?php echo esc_url(MYAVANA_NEXT_URL . 'assets/images/myavana-primary-logo.png'); ?>" alt="MYAVANA" /></div>
            <h2 class="mya-signup-title" id="myaSignupTitle"><?php esc_html_e('Your Hair Journey Starts Here', 'myavana-hair-journey-next'); ?></h2>
            <p class="mya-signup-subtitle"><?php esc_html_e('A quick setup to personalize your experience and prepare your first entry.', 'myavana-hair-journey-next'); ?></p>
        </div>

        <div class="mya-signup-progress">
            <div class="mya-signup-dot active" data-step="1"></div>
            <div class="mya-signup-dot" data-step="2"></div>
        </div>

        <div class="mya-signup-body">
            <div class="mya-signup-step active" data-step="1">
                <h3><?php esc_html_e('Welcome! Tell us about yourself', 'myavana-hair-journey-next'); ?></h3>
                <p><?php esc_html_e('This helps us make your hair journey feel like yours.', 'myavana-hair-journey-next'); ?></p>
                <div class="mya-signup-field">
                    <label for="mya-signup-name"><?php esc_html_e('What should we call you?', 'myavana-hair-journey-next'); ?></label>
                    <input type="text" id="mya-signup-name" placeholder="<?php esc_attr_e('Your name', 'myavana-hair-journey-next'); ?>" value="<?php echo esc_attr($myaSignupName); ?>" autocomplete="given-name">
                </div>
            </div>

            <div class="mya-signup-step" data-step="2">
                <h3><?php esc_html_e('Almost done! Your hair goals', 'myavana-hair-journey-next'); ?></h3>
                <p><?php esc_html_e("Select all that apply - we'll tailor your experience.", 'myavana-hair-journey-next'); ?></p>
                <div class="mya-signup-field">
                    <label><?php esc_html_e('What are your main hair goals?', 'myavana-hair-journey-next'); ?></label>
                    <div class="mya-signup-chips" id="mya-signup-goals">
                        <?php foreach ($myaSignupGoals as $value => $label) : ?>
                            <button type="button" class="mya-signup-chip" data-value="<?php echo esc_attr($value); ?>" aria-pressed="false"><?php echo esc_html($label); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="mya-signup-footer">
            <button type="button" class="mya-signup-back" id="myaSignupBack" style="visibility: hidden;"><?php esc_html_e('← Back', 'myavana-hair-journey-next'); ?></button>
            <button type="button" class="mya-signup-next" id="myaSignupNext"><?php esc_html_e('Continue', 'myavana-hair-journey-next'); ?></button>
        </div>
    </div>
</div>
