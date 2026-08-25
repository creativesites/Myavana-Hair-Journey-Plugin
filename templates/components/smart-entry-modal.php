<?php
/**
 * Global "New Entry" Composer — 3-step flow (type+photos / details / review)
 *
 * Reachable from anywhere in the app shell (header "+ New entry", mobile
 * FAB) via MyavanaNext.SmartEntry.open(). Ported from the "My Hair
 * Timeline" Claude Design mockup's entry composer.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="myavana-modal-backdrop" id="myavana-smart-entry-modal" role="dialog" aria-modal="true" aria-labelledby="smart-entry-modal-title" style="display:none;">
    <div class="myavana-modal myavana-entry-modal">
        <button type="button" class="myavana-modal-close" aria-label="<?php esc_attr_e('Close modal', 'myavana-hair-journey-next'); ?>">✕</button>

        <div class="myavana-entry-header">
            <p class="myavana-entry-step-label" id="entry-step-label"><?php esc_html_e('Step 1 of 3', 'myavana-hair-journey-next'); ?></p>
            <h2 id="smart-entry-modal-title"><?php esc_html_e('New journey entry', 'myavana-hair-journey-next'); ?></h2>
            <div class="myavana-entry-progress">
                <div class="myavana-entry-progress-bar active" data-step-bar="1"></div>
                <div class="myavana-entry-progress-bar" data-step-bar="2"></div>
                <div class="myavana-entry-progress-bar" data-step-bar="3"></div>
            </div>
        </div>

        <form id="smart-entry-form">
            <!-- STEP 1: Type + Photos -->
            <div class="myavana-entry-step" data-step="1">
                <div class="myavana-form-group">
                    <label class="myavana-label"><?php esc_html_e('What are you logging?', 'myavana-hair-journey-next'); ?></label>
                    <div class="myavana-entry-type-grid">
                        <button type="button" class="myavana-entry-type-card active" data-entry-type="wash_day">
                            <strong><?php esc_html_e('Wash day', 'myavana-hair-journey-next'); ?></strong>
                            <span><?php esc_html_e('Routine, products, how it went', 'myavana-hair-journey-next'); ?></span>
                        </button>
                        <button type="button" class="myavana-entry-type-card" data-entry-type="length_check">
                            <strong><?php esc_html_e('Length check', 'myavana-hair-journey-next'); ?></strong>
                            <span><?php esc_html_e('Track a measurement over time', 'myavana-hair-journey-next'); ?></span>
                        </button>
                        <button type="button" class="myavana-entry-type-card" data-entry-type="milestone">
                            <strong><?php esc_html_e('Milestone', 'myavana-hair-journey-next'); ?></strong>
                            <span><?php esc_html_e('A moment worth remembering', 'myavana-hair-journey-next'); ?></span>
                        </button>
                        <button type="button" class="myavana-entry-type-card" data-entry-type="setback">
                            <strong><?php esc_html_e('Setback', 'myavana-hair-journey-next'); ?></strong>
                            <span><?php esc_html_e('Something changed, log the fix', 'myavana-hair-journey-next'); ?></span>
                        </button>
                    </div>
                </div>

                <div class="myavana-form-group">
                    <div class="myavana-entry-field-header">
                        <label class="myavana-label" style="margin:0;"><?php esc_html_e('Photos', 'myavana-hair-journey-next'); ?></label>
                        <span class="myavana-entry-photo-count" id="entry-photo-count"></span>
                    </div>
                    <div class="myavana-entry-photo-grid" id="entry-photo-grid">
                        <button type="button" class="myavana-entry-photo-add" id="entry-photo-add">
                            <span class="myavana-entry-photo-add-icon" aria-hidden="true">+</span>
                            <span class="myavana-entry-photo-add-label"><?php esc_html_e('Add photo', 'myavana-hair-journey-next'); ?></span>
                        </button>
                    </div>
                    <input type="file" id="smart-entry-file-input" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple style="display:none;" />
                    <p class="myavana-entry-hint"><?php esc_html_e('JPEG, PNG, WEBP, or HEIC — up to 15MB each, up to 6 photos.', 'myavana-hair-journey-next'); ?></p>
                </div>
            </div>

            <!-- STEP 2: Details (conditional on type) -->
            <div class="myavana-entry-step" data-step="2" style="display:none;">
                <div class="myavana-form-group">
                    <label class="myavana-label" for="entry-title"><?php esc_html_e('Title', 'myavana-hair-journey-next'); ?> <span class="myavana-entry-required-mark" id="entry-title-required-mark" hidden>*</span></label>
                    <input type="text" class="myavana-input" id="entry-title" maxlength="120" placeholder="<?php esc_attr_e('e.g. Wash day with new deep conditioner', 'myavana-hair-journey-next'); ?>" />
                </div>

                <div class="myavana-entry-row">
                    <div class="myavana-form-group" style="flex:0 0 180px;">
                        <label class="myavana-label" for="entry-date"><?php esc_html_e('Date', 'myavana-hair-journey-next'); ?></label>
                        <input type="date" class="myavana-input" id="entry-date" />
                    </div>
                    <div class="myavana-form-group" style="flex:1;">
                        <label class="myavana-label"><?php esc_html_e('How does your hair feel?', 'myavana-hair-journey-next'); ?></label>
                        <div class="myavana-entry-pill-group" id="entry-mood-group">
                            <button type="button" class="myavana-entry-pill" data-mood="happy">✨ <?php esc_html_e('Great', 'myavana-hair-journey-next'); ?></button>
                            <button type="button" class="myavana-entry-pill" data-mood="neutral">🌿 <?php esc_html_e('Normal', 'myavana-hair-journey-next'); ?></button>
                            <button type="button" class="myavana-entry-pill" data-mood="dry">🍂 <?php esc_html_e('Dry', 'myavana-hair-journey-next'); ?></button>
                            <button type="button" class="myavana-entry-pill" data-mood="itchy">💆 <?php esc_html_e('Sensitive', 'myavana-hair-journey-next'); ?></button>
                        </div>
                    </div>
                </div>

                <div class="myavana-form-group">
                    <label class="myavana-label"><?php esc_html_e('Which goal does this feed?', 'myavana-hair-journey-next'); ?></label>
                    <div class="myavana-entry-pill-group" id="entry-goal-group">
                        <span class="myavana-entry-hint"><?php esc_html_e('Loading your goals…', 'myavana-hair-journey-next'); ?></span>
                    </div>
                </div>

                <!-- Length check fields -->
                <div class="myavana-entry-conditional" data-type-field="length_check" style="display:none;">
                    <div class="myavana-entry-measure-box">
                        <label class="myavana-label"><?php esc_html_e('Measurement', 'myavana-hair-journey-next'); ?></label>
                        <div class="myavana-entry-row" style="align-items:center;">
                            <input type="number" step="0.1" min="0" class="myavana-input" id="entry-length" placeholder="6.5" style="width:110px;" />
                            <span class="myavana-entry-unit"><?php esc_html_e('inches', 'myavana-hair-journey-next'); ?></span>
                        </div>
                        <div class="myavana-entry-pill-group" id="entry-length-point-group">
                            <button type="button" class="myavana-entry-pill" data-length-point="crown"><?php esc_html_e('Crown', 'myavana-hair-journey-next'); ?></button>
                            <button type="button" class="myavana-entry-pill" data-length-point="nape"><?php esc_html_e('Nape', 'myavana-hair-journey-next'); ?></button>
                            <button type="button" class="myavana-entry-pill" data-length-point="ends"><?php esc_html_e('Ends', 'myavana-hair-journey-next'); ?></button>
                            <button type="button" class="myavana-entry-pill" data-length-point="overall"><?php esc_html_e('Overall', 'myavana-hair-journey-next'); ?></button>
                        </div>
                        <p class="myavana-entry-hint"><?php esc_html_e('Length checks feed your growth chart and, if linked to a length goal, its progress.', 'myavana-hair-journey-next'); ?></p>
                    </div>
                </div>

                <!-- Setback fields -->
                <div class="myavana-entry-conditional" data-type-field="setback" style="display:none;">
                    <div class="myavana-form-group">
                        <label class="myavana-label" for="entry-change"><?php esc_html_e('What are you changing?', 'myavana-hair-journey-next'); ?></label>
                        <textarea class="myavana-textarea" id="entry-change" rows="2" placeholder="<?php esc_attr_e('Protein treatment every other wash', 'myavana-hair-journey-next'); ?>"></textarea>
                        <p class="myavana-entry-hint"><?php esc_html_e('Setbacks and fixes train your recommendations.', 'myavana-hair-journey-next'); ?></p>
                    </div>
                </div>

                <div class="myavana-form-group">
                    <label class="myavana-label" for="entry-notes"><?php esc_html_e('Notes', 'myavana-hair-journey-next'); ?></label>
                    <textarea class="myavana-textarea" id="entry-notes" rows="3" placeholder="<?php esc_attr_e('What did you do, and how did it turn out?', 'myavana-hair-journey-next'); ?>"></textarea>
                </div>

                <div class="myavana-form-group">
                    <label class="myavana-label" for="entry-products"><?php esc_html_e('Products used', 'myavana-hair-journey-next'); ?></label>
                    <input type="text" class="myavana-input" id="entry-products" placeholder="<?php esc_attr_e('e.g. Cleanser, Deep Conditioner, Leave-in', 'myavana-hair-journey-next'); ?>" />
                </div>
            </div>

            <!-- STEP 3: Review + privacy -->
            <div class="myavana-entry-step" data-step="3" style="display:none;">
                <div class="myavana-entry-review" id="entry-review"></div>

                <div class="myavana-form-group">
                    <label class="myavana-label"><?php esc_html_e('Who can see this?', 'myavana-hair-journey-next'); ?></label>
                    <div class="myavana-entry-pill-group" id="entry-visibility-group">
                        <button type="button" class="myavana-entry-pill active" data-visibility="private"><?php esc_html_e('Only me', 'myavana-hair-journey-next'); ?></button>
                        <button type="button" class="myavana-entry-pill" data-visibility="twins"><?php esc_html_e('Hair twins', 'myavana-hair-journey-next'); ?></button>
                        <button type="button" class="myavana-entry-pill" data-visibility="community"><?php esc_html_e('Community', 'myavana-hair-journey-next'); ?></button>
                    </div>
                </div>
            </div>

            <div class="myavana-entry-footer">
                <button type="button" class="myavana-entry-back" id="entry-back-btn" style="visibility:hidden;"><?php esc_html_e('Back', 'myavana-hair-journey-next'); ?></button>
                <button type="button" class="myavana-btn myavana-btn-primary" id="entry-continue-btn"><?php esc_html_e('Continue', 'myavana-hair-journey-next'); ?></button>
                <button type="submit" class="myavana-btn myavana-btn-primary" id="entry-submit-btn" style="display:none;"><?php esc_html_e('Save entry', 'myavana-hair-journey-next'); ?></button>
            </div>
        </form>
    </div>
</div>
