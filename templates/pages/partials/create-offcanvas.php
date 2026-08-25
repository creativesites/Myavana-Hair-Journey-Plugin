<?php
/**
 * Create/Edit Offcanvas Forms — Hair Journey
 * Premium redesign with luxury editorial aesthetic
 * Mobile-first, drag-and-drop media, animated interactions
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$today    = date('Y-m-d');
$now_time = date('H:i');
$render_goal_offcanvas = ($myavana_hjn_context ?? '') !== 'routines';
$render_entry_offcanvas = !in_array(($myavana_hjn_context ?? ''), ['routines', 'goals'], true);
$render_routine_offcanvas = ($myavana_hjn_context ?? '') !== 'goals';
?>

<!-- ══════════════════════════════════════════════════
     TOAST CONTAINER
══════════════════════════════════════════════════ -->
<div class="hjn-toast-container" id="hjnToastContainer" aria-live="polite"></div>

<!-- ══════════════════════════════════════════════════
     OVERLAY
══════════════════════════════════════════════════ -->
<div class="offcanvas-overlay-hjn" id="createOffcanvasOverlay" onclick="HJN.closeOffcanvas()"></div>


<!-- ══════════════════════════════════════════════════
     ENTRY  OFFCANVAS
══════════════════════════════════════════════════ -->
<?php if ($render_entry_offcanvas) : ?>
<div class="offcanvas-hjn create-offcanvas-hjn" id="entryOffcanvas" data-type="entry" role="dialog"
     aria-modal="true" aria-labelledby="entryOffcanvasTitle">

    <!-- Header -->
    <div class="offcanvas-header-hjn">
        <p class="offcanvas-eyebrow-hjn">Hair Journey</p>
        <h2 class="offcanvas-title-hjn" id="entryOffcanvasTitle">New Entry</h2>
        <p class="offcanvas-subtitle-hjn" id="entryOffcanvasSubtitle">Log your hair care session</p>
        <button class="offcanvas-close-hjn" onclick="HJN.closeOffcanvas()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg>
        </button>
    </div>

    <!-- Progress bar -->
    <div class="offcanvas-progress-hjn"><div class="offcanvas-progress-fill-hjn" id="entryProgressFill" style="width:33%"></div></div>

    <!-- Section Tabs -->
    <div class="form-tabs-hjn" id="entryTabs" role="tablist">
        <button class="form-tab-hjn is-active" data-tab="entry-basics"    role="tab" aria-selected="true">Basics</button>
        <button class="form-tab-hjn"            data-tab="entry-media"    role="tab" aria-selected="false">Photos & Video</button>
        <button class="form-tab-hjn"            data-tab="entry-details"  role="tab" aria-selected="false">Details</button>
    </div>

    <!-- Scrollable body -->
    <div class="offcanvas-content-hjn">

        <form id="entryForm" class="hair-journey-form-hjn" novalidate enctype="multipart/form-data" data-submit-owner="hjn" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" onsubmit="if(window.HJN&&typeof window.HJN._submitForm==='function'){event.preventDefault();event.stopImmediatePropagation();window.HJN._submitForm('entryForm','entryFormLoading');return false;}return true;">
            <?php wp_nonce_field('myavana_add_entry','myavana_nonce'); ?>
            <input type="hidden" name="action"              value="myavana_add_entry">
            <input type="hidden" name="myavana_entry"       value="1">
            <input type="hidden" name="is_automated"        value="0">
            <input type="hidden" name="entry_id"            id="entry_id"           value="">
            <input type="hidden" name="removed_gallery_ids" id="removed_gallery_ids" value="[]">
            <input type="hidden" name="removed_video_ids"   id="removed_video_ids"   value="[]">
            <input type="hidden" name="featured_image_index" id="featured_image_index" value="0">
            <input type="hidden" name="rating"              id="health_rating"       value="0">
            <input type="hidden" name="mood_demeanor"       id="mood_hidden"         value="">

            <!-- ┄┄ TAB 1 : BASICS ┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄ -->
            <div class="form-section-hjn is-active" id="entry-basics">

                <!-- Title -->
                <div class="float-field-hjn">
                    <input type="text" id="entry_title" name="title" class="form-input-hjn"
                           placeholder=" " maxlength="100" required autocomplete="off">
                    <label for="entry_title" class="float-label-hjn">
                        Entry Title <span class="form-required-hjn">*</span>
                    </label>
                </div>

                <!-- Date & Time -->
                <div class="form-row-hjn">
                    <div class="float-field-hjn">
                        <input type="date" id="entry_date" name="entry_date" class="form-input-hjn"
                               placeholder=" " max="<?php echo esc_attr($today); ?>" value="<?php echo esc_attr($today); ?>">
                        <label for="entry_date" class="float-label-hjn">Date</label>
                    </div>
                    <div class="float-field-hjn">
                        <input type="time" id="entry_time" name="entry_time" class="form-input-hjn"
                               placeholder=" " value="<?php echo esc_attr($now_time); ?>">
                        <label for="entry_time" class="float-label-hjn">Time</label>
                    </div>
                </div>

                <!-- Description -->
                <div class="float-field-hjn is-textarea">
                    <textarea id="entry_content" name="description" class="form-textarea-hjn"
                              placeholder=" " rows="4" maxlength="2000"></textarea>
                    <label for="entry_content" class="float-label-hjn">Description</label>
                    <div class="char-counter-hjn" id="entry_content_count">0 / 2000</div>
                </div>

                <!-- Hair Health Rating -->
                <div class="form-group-hjn">
                    <label class="form-label-hjn">Hair Health Rating</label>
                    <div class="rating-group-hjn">
                        <div class="rating-stars-hjn" id="health_rating_stars" role="radiogroup" aria-label="Hair health rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" class="rating-star-hjn" data-value="<?php echo $i; ?>"
                                    role="radio" aria-label="<?php echo $i; ?> star" aria-checked="false">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.63L12,2L9.19,8.63L2,9.24L7.45,13.97L5.82,21L12,17.27Z"/>
                                </svg>
                            </button>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-label-hjn" id="health_rating_label">Not yet rated</span>
                    </div>
                </div>

                <!-- Mood -->
                <div class="form-group-hjn">
                    <label class="form-label-hjn">How's your hair feeling?</label>
                    <div class="mood-pills-hjn" id="moodPills" role="radiogroup">
                        <?php
                        $moods = ['Amazing' => '✨', 'Great' => '🌟', 'Good' => '😊', 'Okay' => '😐', 'Struggling' => '😔', 'Frustrated' => '😤'];
                        foreach ($moods as $val => $emoji): ?>
                        <label class="mood-pill-hjn">
                            <input type="radio" name="_mood_ui" value="<?php echo esc_attr($val); ?>">
                            <?php echo $emoji . ' ' . esc_html($val); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Entry Type -->
                <div class="form-group-hjn">
                    <label class="form-label-hjn">Entry Type</label>
                    <div class="tag-pills-hjn" id="entryTypePills">
                        <?php
                        $types = ['Wash Day','Refresh Day','Protective Style','Treatment','Trim','Style Session','Scalp Care','Length Check'];
                        foreach ($types as $t): ?>
                        <button type="button" class="tag-pill-hjn" data-value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="entry_type" id="entry_type_hidden" value="">
                </div>

            </div><!-- /entry-basics -->


            <!-- ┄┄ TAB 2 : MEDIA ┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄ -->
            <div class="form-section-hjn" id="entry-media">

                <!-- Existing Images (edit mode) -->
                <div id="existingImagesGallery" style="display:none">
                    <div class="section-divider-hjn"><span>Current Photos</span></div>
                    <div class="photo-preview-grid-hjn" id="existingImagesGrid"></div>
                </div>

                <!-- Existing Videos (edit mode) -->
                <div id="existingVideosGallery" style="display:none">
                    <div class="section-divider-hjn"><span>Current Videos</span></div>
                    <div class="photo-preview-grid-hjn" id="existingVideosGrid"></div>
                </div>

                <!-- Drag & Drop Upload Zone -->
                <div class="section-divider-hjn"><span>Add New Photos</span></div>

                <div class="photo-upload-zone-hjn" id="photoDropZone" role="button"
                     tabindex="0" aria-label="Upload photos – click or drag and drop">
                    <div class="upload-icon-hjn">
                        <svg viewBox="0 0 24 24" width="26" height="26">
                            <path fill="currentColor" d="M4 5h13v7h2V5c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8v-2H4V5zm3 6 2.5 3.21 3.5-4.51 4.5 6H4l3-4.7z"/>
                            <path fill="currentColor" d="M20 18h-2v-2h-2v2h-2v2h2v2h2v-2h2v-2z"/>
                        </svg>
                    </div>
                    <p class="upload-title-hjn">Drop photos here</p>
                    <p class="upload-sub-hjn">JPG, PNG, WebP · Up to 5 photos · 5 MB each</p>
                    <div class="upload-actions-hjn">
                        <button type="button" class="upload-btn-hjn is-primary" id="choosePhotoBtn">
                            <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/></svg>
                            Choose Photos
                        </button>
                        <button type="button" class="upload-btn-hjn" id="cameraPhotoBtn">
                            <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 15.2A3.2 3.2 0 0 1 8.8 12 3.2 3.2 0 0 1 12 8.8 3.2 3.2 0 0 1 15.2 12 3.2 3.2 0 0 1 12 15.2M12 7a5 5 0 0 0-5 5 5 5 0 0 0 5 5 5 5 0 0 0 5-5 5 5 0 0 0-5-5m-7-1h2.2l1.27-1.5h7.06L16.8 6H19a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/></svg>
                            Take Photo
                        </button>
                    </div>
                </div>

                <!-- Hidden file inputs -->
                <input class="upload-input-hjn" type="file" id="photoFilePicker"  name="entry_photos[]"  accept="image/*"        multiple data-max-files="5">
                <input class="upload-input-hjn" type="file" id="cameraPhotoPicker" name="_camera_photo"   accept="image/*"        capture="environment">
                <input class="upload-input-hjn" type="file" id="videoFilePicker"   name="entry_videos[]"  accept="video/*"        multiple>
                <input class="upload-input-hjn" type="file" id="cameraVideoPicker" name="_camera_video"   accept="video/*"        capture="environment">

                <!-- Photo preview -->
                <div class="photo-preview-grid-hjn" id="newMediaPreviewGrid"></div>

                <div class="section-divider-hjn"><span>Add Video</span></div>
                <div class="photo-upload-zone-hjn" id="videoDropZone" style="padding:24px">
                    <div class="upload-icon-hjn" style="width:42px;height:42px">
                        <svg viewBox="0 0 24 24" width="22" height="22">
                            <path fill="currentColor" d="M15 8v8H5V8h10m1-2H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4V7c0-.55-.45-1-1-1z"/>
                        </svg>
                    </div>
                    <p class="upload-title-hjn" style="font-size:.9375rem">Add Progress Video</p>
                    <p class="upload-sub-hjn">MP4, MOV, WebM · Up to 2 videos · 30 MB each</p>
                    <div class="upload-actions-hjn">
                        <button type="button" class="upload-btn-hjn is-primary" id="chooseVideoBtn">Choose Video</button>
                        <button type="button" class="upload-btn-hjn" id="cameraVideoBtn">Record Video</button>
                    </div>
                </div>
                <div class="photo-preview-grid-hjn" id="newVideoPreviewGrid"></div>

                <!-- Video notes -->
                <div class="float-field-hjn is-textarea" style="margin-top:16px">
                    <textarea id="entry_video_notes" name="video_notes" class="form-textarea-hjn"
                              placeholder=" " rows="2"></textarea>
                    <label for="entry_video_notes" class="float-label-hjn">Video Notes (optional)</label>
                </div>

                <input type="hidden" name="request_ai_analysis" value="0">

            </div><!-- /entry-media -->


            <!-- ┄┄ TAB 3 : DETAILS ┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄ -->
            <div class="form-section-hjn" id="entry-details">

                <!-- Environment & Scalp -->
                <div class="form-row-hjn">
                    <div class="form-group-hjn">
                        <label for="environment" class="form-label-hjn">Environment</label>
                        <div class="select-wrapper-hjn">
                            <select id="environment" name="environment" class="form-select-hjn">
                                <option value="">Select…</option>
                                <?php foreach (['Home','Salon','Travel','Gym','Outdoor'] as $v): ?>
                                <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group-hjn">
                        <label for="scalp_condition" class="form-label-hjn">Scalp Condition</label>
                        <div class="select-wrapper-hjn">
                            <select id="scalp_condition" name="scalp_condition" class="form-select-hjn">
                                <option value="">Select…</option>
                                <?php foreach (['Balanced','Dry','Oily','Flaky','Sensitive','Irritated'] as $v): ?>
                                <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-row-hjn">
                    <div class="form-group-hjn">
                        <label for="hair_feel" class="form-label-hjn">How Hair Felt</label>
                        <div class="select-wrapper-hjn">
                            <select id="hair_feel" name="hair_feel" class="form-select-hjn">
                                <option value="">Select…</option>
                                <?php foreach (['Soft','Moisturized','Dry','Brittle','Strong','Frizzy'] as $v): ?>
                                <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="float-field-hjn">
                        <input type="number" id="length_check_cm" name="length_check_cm"
                               class="form-input-hjn" placeholder=" " min="0" max="200" step="0.1">
                        <label for="length_check_cm" class="float-label-hjn">Length Check (cm)</label>
                    </div>
                </div>

                <!-- Products used -->
                <div class="form-group-hjn">
                    <label for="products_used" class="form-label-hjn">Products Used</label>
                    <select id="products_used" name="products[]" class="form-select-hjn products-select-hjn" multiple></select>
                    <div class="form-hint-hjn">Select from the list, or type to add your own products.</div>
                </div>

                <!-- Techniques -->
                <div class="float-field-hjn">
                    <input type="text" id="techniques" name="techniques" class="form-input-hjn"
                           placeholder=" " maxlength="200">
                    <label for="techniques" class="float-label-hjn">Techniques / Methods Used</label>
                </div>

                <!-- Tags -->
                <div class="float-field-hjn">
                    <input type="text" id="entry_tags" name="entry_tags" class="form-input-hjn"
                           placeholder=" " maxlength="180">
                    <label for="entry_tags" class="float-label-hjn">Tags (comma-separated)</label>
                </div>

                <!-- Stylist Notes -->
                <div class="float-field-hjn is-textarea">
                    <textarea id="notes" name="notes" class="form-textarea-hjn"
                              placeholder=" " rows="3"></textarea>
                    <label for="notes" class="float-label-hjn">Stylist Notes</label>
                </div>

                <!-- Next Step -->
                <div class="float-field-hjn is-textarea">
                    <textarea id="next_step" name="next_step" class="form-textarea-hjn"
                              placeholder=" " rows="2" maxlength="500"></textarea>
                    <label for="next_step" class="float-label-hjn">Next Step / Follow-up</label>
                </div>

            </div><!-- /entry-details -->

            <!-- Sticky Footer -->
            <div class="form-actions-hjn">
                <button type="button" class="btn-cancel-hjn" onclick="HJN.closeOffcanvas()">Cancel</button>
                <div style="display:flex;gap:8px;flex:1">
                    <button type="button" class="btn-save-hjn" id="entryPrevBtn" onclick="HJN.entryTab(-1)"
                            style="display:none;background:var(--blueberry);box-shadow:none">
                        ← Back
                    </button>
                    <button type="button" class="btn-save-hjn" id="entryNextBtn" onclick="HJN.entryTab(1)">
                        Continue →
                    </button>
                    <button type="submit" class="btn-save-hjn" id="entrySaveBtn" style="display:none">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M17,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V7L17,3M19,19H5V5H16.17L19,7.83V19M12,12A3,3 0 0,0 9,15A3,3 0 0,0 12,18A3,3 0 0,0 15,15A3,3 0 0,0 12,12M6,6H15V10H6V6Z"/>
                        </svg>
                        Save Entry
                    </button>
                </div>
            </div>

            <div class="form-loading-hjn" id="entryFormLoading" style="display:none">
                <div class="loading-ring-hjn"></div>
                <p>Saving your entry…</p>
            </div>

        </form>
    </div><!-- /offcanvas-content -->
</div><!-- /entryOffcanvas -->
<?php endif; ?>


<!-- ══════════════════════════════════════════════════
     GOAL  OFFCANVAS
══════════════════════════════════════════════════ -->
<?php if ($render_goal_offcanvas) : ?>
<div class="offcanvas-hjn" id="goalOffcanvas" data-type="goal" role="dialog"
     aria-modal="true" aria-labelledby="goalOffcanvasTitle">

    <div class="offcanvas-header-hjn">
        <h2 class="offcanvas-title-hjn" id="goalOffcanvasTitle">Create a goal</h2>
        <p class="offcanvas-subtitle-hjn">Make progress feel clear and achievable.</p>
        <button class="offcanvas-close-hjn" onclick="HJN.closeOffcanvas()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg>
        </button>
    </div>

    <div class="offcanvas-content-hjn">
        <form id="goalForm" class="hair-journey-form-hjn" novalidate data-submit-owner="hjn" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" onsubmit="if(window.HJN&&typeof window.HJN._submitForm==='function'){event.preventDefault();event.stopImmediatePropagation();window.HJN._submitForm('goalForm','goalFormLoading');return false;}return true;">
            <input type="hidden" name="goal_id" id="goal_id" value="">
            <input type="hidden" name="action" value="myavana_add_goal">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('myavana_add_goal'); ?>">

            <div class="float-field-hjn">
                <input type="text" id="goal_title" name="goal_title" class="form-input-hjn"
                       placeholder=" " maxlength="100" required>
                <label for="goal_title" class="float-label-hjn">Goal Title <span class="form-required-hjn">*</span></label>
            </div>

            <div class="form-group-hjn">
                <label class="form-label-hjn">Category</label>
                <div class="tag-pills-hjn" id="goalCategoryPills">
                    <?php foreach (['Length','Health','Moisture','Strength','Texture','Thickness','Scalp','Retention','Style','Other'] as $c): ?>
                    <button type="button" class="tag-pill-hjn" data-value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="goal_category" id="goal_category_hidden" value="">
            </div>

            <div class="float-field-hjn is-textarea">
                <textarea id="goal_description" name="goal_description" class="form-textarea-hjn"
                          placeholder=" " rows="3" maxlength="1000"></textarea>
                <label for="goal_description" class="float-label-hjn">Description</label>
            </div>

            <div class="form-row-hjn">
                <div class="float-field-hjn">
                    <input type="date" id="goal_start_date" name="goal_start_date" class="form-input-hjn"
                           placeholder=" " required value="<?php echo esc_attr($today); ?>">
                    <label for="goal_start_date" class="float-label-hjn">Start Date <span class="form-required-hjn">*</span></label>
                </div>
                <div class="float-field-hjn">
                    <input type="date" id="goal_end_date" name="goal_end_date" class="form-input-hjn"
                           placeholder=" " min="<?php echo esc_attr($today); ?>">
                    <label for="goal_end_date" class="float-label-hjn">Target Date</label>
                </div>
            </div>

            <div class="form-row-hjn">
                <div class="form-group-hjn">
                    <label for="goal_priority" class="form-label-hjn">Priority</label>
                    <div class="select-wrapper-hjn">
                        <select id="goal_priority" name="goal_priority" class="form-select-hjn">
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>
                <div class="form-group-hjn">
                    <label for="goal_checkin_frequency" class="form-label-hjn">Check-in</label>
                    <div class="select-wrapper-hjn">
                        <select id="goal_checkin_frequency" name="goal_checkin_frequency" class="form-select-hjn">
                            <?php foreach (['Weekly','Bi-weekly','Monthly','As Needed'] as $v): ?>
                            <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="section-divider-hjn"><span>Measurement</span></div>

            <div class="form-row-hjn">
                <div class="float-field-hjn">
                    <input type="number" id="goal_baseline_value" name="goal_baseline_value"
                           class="form-input-hjn" placeholder=" " min="0" step="0.1">
                    <label for="goal_baseline_value" class="float-label-hjn">Baseline Value</label>
                </div>
                <div class="float-field-hjn">
                    <input type="number" id="goal_target_value" name="goal_target_value"
                           class="form-input-hjn" placeholder=" " min="0" step="0.1">
                    <label for="goal_target_value" class="float-label-hjn">Target Value</label>
                </div>
            </div>

            <div class="form-group-hjn">
                <label for="goal_measure_unit" class="form-label-hjn">Measure Unit</label>
                <div class="select-wrapper-hjn">
                    <select id="goal_measure_unit" name="goal_measure_unit" class="form-select-hjn">
                        <option value="">Select unit…</option>
                        <?php foreach (['cm'=>'Centimeters (cm)','in'=>'Inches (in)','score'=>'Score','%'=>'Percent (%)','sessions'=>'Sessions','days'=>'Days'] as $val=>$label): ?>
                        <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Progress -->
            <div class="form-group-hjn">
                <label class="form-label-hjn">Current Progress</label>
                <div class="progress-field-hjn">
                    <input type="range" id="goal_progress" name="goal_progress" class="form-range-hjn"
                           min="0" max="100" value="0">
                    <span class="progress-val-hjn" id="goal_progress_value">0%</span>
                </div>
            </div>

            <div class="section-divider-hjn"><span>Motivation</span></div>

            <div class="float-field-hjn is-textarea">
                <textarea id="goal_motivation" name="goal_motivation" class="form-textarea-hjn"
                          placeholder=" " rows="2" maxlength="500"></textarea>
                <label for="goal_motivation" class="float-label-hjn">Why is this goal meaningful?</label>
            </div>

            <div class="float-field-hjn is-textarea">
                <textarea id="goal_success_criteria" name="goal_success_criteria" class="form-textarea-hjn"
                          placeholder=" " rows="2" maxlength="500"></textarea>
                <label for="goal_success_criteria" class="float-label-hjn">How will you know it's achieved?</label>
            </div>

            <div class="float-field-hjn is-textarea">
                <textarea id="goal_blockers" name="goal_blockers" class="form-textarea-hjn"
                          placeholder=" " rows="2" maxlength="500"></textarea>
                <label for="goal_blockers" class="float-label-hjn">Potential Challenges</label>
            </div>

            <div class="float-field-hjn">
                <input type="text" id="goal_reward" name="goal_reward" class="form-input-hjn"
                       placeholder=" " maxlength="140">
                <label for="goal_reward" class="float-label-hjn">Reward / Celebration milestone</label>
            </div>

            <!-- Milestones -->
            <div class="section-divider-hjn"><span>Milestones</span></div>
            <div id="milestones_list" class="routine-steps-hjn"></div>
            <button type="button" class="btn-add-hjn" onclick="HJN.addMilestone()">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/></svg>
                Add Milestone
            </button>

            <!-- Edit mode: progress notes -->
            <div id="goalProgressNotesGroup" style="display:none">
                <div class="section-divider-hjn"><span>Progress History</span></div>
                <div id="goalProgressNotesList"></div>
                <div class="float-field-hjn is-textarea" style="margin-top:12px">
                    <textarea id="newProgressNote" class="form-textarea-hjn" placeholder=" "
                              rows="3" maxlength="500"></textarea>
                    <label for="newProgressNote" class="float-label-hjn">Add Progress Update</label>
                    <div class="char-counter-hjn" id="progress_note_count">0 / 500</div>
                </div>
            </div>

            <div class="form-actions-hjn">
                <button type="button" class="btn-cancel-hjn" onclick="HJN.closeOffcanvas()">Cancel</button>
                <button type="submit" class="btn-save-hjn" id="saveGoalBtn">
                    <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M17,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V7L17,3M19,19H5V5H16.17L19,7.83V19M12,12A3,3 0 0,0 9,15A3,3 0 0,0 12,18A3,3 0 0,0 15,15A3,3 0 0,0 12,12M6,6H15V10H6V6Z"/></svg>
                    Save Goal
                </button>
            </div>
            <div class="form-loading-hjn" id="goalFormLoading" style="display:none">
                <div class="loading-ring-hjn"></div>
                <p>Saving your goal…</p>
            </div>
        </form>
    </div>
</div><!-- /goalOffcanvas -->
<?php endif; ?>


<!-- ══════════════════════════════════════════════════
     ROUTINE  OFFCANVAS
══════════════════════════════════════════════════ -->
<?php if ($render_routine_offcanvas) : ?>
<div class="offcanvas-hjn" id="routineOffcanvas" data-type="routine" role="dialog"
     aria-modal="true" aria-labelledby="routineOffcanvasTitle">

    <div class="offcanvas-header-hjn">
        <h2 class="offcanvas-title-hjn" id="routineOffcanvasTitle">Create a routine</h2>
        <p class="offcanvas-subtitle-hjn">Build care that fits your real life.</p>
        <button class="offcanvas-close-hjn" onclick="HJN.closeOffcanvas()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg>
        </button>
    </div>

    <div class="offcanvas-content-hjn">
        <form id="routineForm" class="hair-journey-form-hjn" novalidate data-submit-owner="hjn" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" onsubmit="if(window.HJN&&typeof window.HJN._submitForm==='function'){event.preventDefault();event.stopImmediatePropagation();window.HJN._submitForm('routineForm','routineFormLoading');return false;}return true;">
            <input type="hidden" name="routine_id" id="routine_id" value="">
            <input type="hidden" name="action" value="myavana_add_routine">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('myavana_add_routine'); ?>">

            <div class="float-field-hjn">
                <input type="text" id="routine_title" name="routine_title" class="form-input-hjn"
                       placeholder=" " maxlength="100" required>
                <label for="routine_title" class="float-label-hjn">Routine Name <span class="form-required-hjn">*</span></label>
            </div>

            <div class="form-group-hjn">
                <label class="form-label-hjn">Routine Type</label>
                <div class="tag-pills-hjn" id="routineTypePills">
                    <?php foreach (['Wash Day','Daily Care','Deep Conditioning','Protein Treatment','Styling','Protective Style','Night Routine','Scalp Care','Other'] as $t): ?>
                    <button type="button" class="tag-pill-hjn" data-value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="routine_type" id="routine_type_hidden" value="">
            </div>

            <div class="form-row-hjn">
                <div class="form-group-hjn">
                    <label for="routine_frequency" class="form-label-hjn">Frequency</label>
                    <div class="select-wrapper-hjn">
                        <select id="routine_frequency" name="routine_frequency" class="form-select-hjn">
                            <?php foreach (['Daily','Weekly','Bi-weekly','Monthly','As Needed'] as $v): ?>
                            <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group-hjn">
                    <label for="routine_duration" class="form-label-hjn">Duration</label>
                    <div class="select-wrapper-hjn">
                        <select id="routine_duration" name="routine_duration" class="form-select-hjn">
                            <option value="">Select…</option>
                            <?php foreach ([5=>'5 min',10=>'10 min',15=>'15 min',20=>'20 min',30=>'30 min',45=>'45 min',60=>'1 hour',90=>'1.5 hours',120=>'2 hours',180=>'3+ hours'] as $v=>$l): ?>
                            <option value="<?php echo $v; ?>"><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-row-hjn">
                <div class="form-group-hjn">
                    <label for="routine_phase" class="form-label-hjn">Phase</label>
                    <div class="select-wrapper-hjn">
                        <select id="routine_phase" name="routine_phase" class="form-select-hjn">
                            <?php foreach (['Anytime','Morning','Afternoon','Evening','Night'] as $v): ?>
                            <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group-hjn">
                    <label for="routine_difficulty" class="form-label-hjn">Difficulty</label>
                    <div class="select-wrapper-hjn">
                        <select id="routine_difficulty" name="routine_difficulty" class="form-select-hjn">
                            <?php foreach (['Beginner','Intermediate','Advanced'] as $v): ?>
                            <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Steps -->
            <div class="section-divider-hjn"><span>Routine Steps <span class="form-required-hjn">*</span></span></div>
            <div id="routine_steps_list" class="routine-steps-hjn">
                <div class="step-item-hjn" data-step="1">
                    <div class="step-num-hjn">1</div>
                    <input type="text" name="routine_steps[]" class="step-input-hjn"
                           placeholder="Describe this step…" required>
                    <span class="step-drag-hjn" title="Drag to reorder">
                        <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M9 3h2v2H9zm4 0h2v2h-2zM9 7h2v2H9zm4 0h2v2h-2zM9 11h2v2H9zm4 0h2v2h-2zM9 15h2v2H9zm4 0h2v2h-2zM9 19h2v2H9zm4 0h2v2h-2z"/></svg>
                    </span>
                    <button type="button" class="step-remove-hjn" onclick="HJN.removeStep(this)" disabled aria-label="Remove step">
                        <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg>
                    </button>
                </div>
            </div>
            <button type="button" class="btn-add-hjn" style="margin-bottom:20px" onclick="HJN.addStep()">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/></svg>
                Add Step
            </button>

            <div class="float-field-hjn is-textarea">
                <textarea id="routine_products" name="routine_products" class="form-textarea-hjn"
                          placeholder=" " rows="3"></textarea>
                <label for="routine_products" class="float-label-hjn">Products Needed (one per line)</label>
            </div>

            <div class="float-field-hjn is-textarea">
                <textarea id="routine_tools" name="routine_tools" class="form-textarea-hjn"
                          placeholder=" " rows="2"></textarea>
                <label for="routine_tools" class="float-label-hjn">Tools Needed</label>
            </div>

            <div class="float-field-hjn is-textarea">
                <textarea id="routine_expected_result" name="routine_expected_result" class="form-textarea-hjn"
                          placeholder=" " rows="2"></textarea>
                <label for="routine_expected_result" class="float-label-hjn">Expected Outcome</label>
            </div>

            <div class="float-field-hjn is-textarea">
                <textarea id="routine_notes" name="routine_notes" class="form-textarea-hjn"
                          placeholder=" " rows="3"></textarea>
                <label for="routine_notes" class="float-label-hjn">Notes / Tips</label>
            </div>

            <div class="form-row-hjn">
                <div class="float-field-hjn">
                    <input type="time" id="routine_time" name="routine_time" class="form-input-hjn"
                           placeholder=" " value="08:00">
                    <label for="routine_time" class="float-label-hjn">Preferred Time</label>
                </div>
                <div class="float-field-hjn">
                    <input type="text" id="routine_reminder_days" name="routine_reminder_days"
                           class="form-input-hjn" placeholder=" " maxlength="35" aria-describedby="routine_reminder_days_help">
                    <label for="routine_reminder_days" class="float-label-hjn">Reminder Days (e.g. Mon, Fri)</label>
                    <small id="routine_reminder_days_help" class="form-help-hjn">Optional. Choose the days you want this care to appear in “Your care for today.”</small>
                </div>
            </div>

            <label class="check-field-hjn">
                <input type="checkbox" id="routine_auto_track" name="routine_auto_track" value="1">
                <span class="check-text-hjn">
                    <strong>Auto-track in calendar</strong><br>
                    <span style="font-size:.8125rem;opacity:.7">Show this routine in calendar suggestions</span>
                </span>
            </label>

            <div class="form-actions-hjn">
                <button type="button" class="btn-cancel-hjn" onclick="HJN.closeOffcanvas()">Cancel</button>
                <button type="submit" class="btn-save-hjn" id="saveRoutineBtn">
                    <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M17,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V7L17,3M19,19H5V5H16.17L19,7.83V19M12,12A3,3 0 0,0 9,15A3,3 0 0,0 12,18A3,3 0 0,0 15,15A3,3 0 0,0 12,12M6,6H15V10H6V6Z"/></svg>
                    Save Routine
                </button>
            </div>
            <div class="form-loading-hjn" id="routineFormLoading" style="display:none">
                <div class="loading-ring-hjn"></div>
                <p>Saving your routine…</p>
            </div>
        </form>
    </div>
</div><!-- /routineOffcanvas -->
<?php endif; ?>


<!-- ══════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════ -->
<script>
(function() {
'use strict';

/* ──────────────────────────────────────────────────
   HJN namespace
────────────────────────────────────────────────── */
const HJN = window.HJN = {

    /* State */
    _currentOffcanvas : null,
    _entryTabIndex    : 0,
    _entryTabs        : ['entry-basics','entry-media','entry-details'],
    _newPhotoFiles    : [],
    _newVideoFiles    : [],
    _stepCounter      : 1,
    _milestoneCounter : 0,
    _returnFocus      : null,

    /* ── Open / Close ──────────────────────────────*/
    openOffcanvas(type, data = {}) {
        const id  = type + 'Offcanvas';
        const el  = document.getElementById(id);
        const ov  = document.getElementById('createOffcanvasOverlay');
        if (!el) return;
        this._returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        this._currentOffcanvas = el;
        el.classList.add('is-open');
        el.classList.add('active');
        if (ov) {
            ov.classList.add('is-open');
            ov.classList.add('active');
        }
        document.body.style.overflow = 'hidden';

        if (type === 'entry') {
            this._entryTabIndex = 0;
            this._newPhotoFiles = [];
            this._newVideoFiles = [];
            this._refreshEntryNav();
            if (data.entry_id) this._populateEntry(data);
            else this._resetEntry();
        } else if (type === 'goal') {
            this._resetGoal();
            this._prefillGoal(data);
        } else if (type === 'routine') {
            this._resetRoutine();
            this._prefillRoutine(data);
        }
        el.querySelector('.offcanvas-content-hjn')?.scrollTo(0, 0);
        window.setTimeout(() => {
            const firstField = el.querySelector('input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), button:not([disabled])');
            firstField?.focus();
        }, 0);
    },

    closeOffcanvas() {
        const target = this._currentOffcanvas || document.querySelector('.offcanvas-hjn.is-open, .offcanvas-hjn.active');
        if (target) {
            target.classList.remove('is-open');
            target.classList.remove('active');
        }
        const overlay = document.getElementById('createOffcanvasOverlay');
        if (overlay) {
            overlay.classList.remove('is-open');
            overlay.classList.remove('active');
        }
        document.body.style.overflow = '';
        this._currentOffcanvas = null;
        const returnFocus = this._returnFocus;
        this._returnFocus = null;
        if (returnFocus && document.contains(returnFocus)) {
            window.setTimeout(() => returnFocus.focus(), 0);
        }
    },

    /* ── Entry multi-tab navigation ───────────────*/
    entryTab(dir) {
        const tabs = this._entryTabs;
        this._entryTabIndex = Math.max(0, Math.min(tabs.length - 1, this._entryTabIndex + dir));
        // Validate tab 0 before advancing
        if (dir > 0 && this._entryTabIndex > 0) {
            if (!this._validateBasics()) { this._entryTabIndex--; return; }
        }
        this._activateEntryTab(this._entryTabIndex);
        this._refreshEntryNav();
    },

    _activateEntryTab(idx) {
        const tabs    = this._entryTabs;
        const tabBtns = document.querySelectorAll('#entryTabs .form-tab-hjn');
        document.querySelectorAll('#entryForm .form-section-hjn').forEach((s, i) => {
            s.classList.toggle('is-active', i === idx);
        });
        tabBtns.forEach((b, i) => {
            b.classList.toggle('is-active', i === idx);
            b.setAttribute('aria-selected', i === idx ? 'true' : 'false');
        });
        // Progress
        const fill = document.getElementById('entryProgressFill');
        if (fill) fill.style.width = ((idx + 1) / tabs.length * 100) + '%';
        document.getElementById('entryOffcanvas')?.querySelector('.offcanvas-content-hjn')?.scrollTo(0,0);
    },

    _refreshEntryNav() {
        const idx   = this._entryTabIndex;
        const last  = this._entryTabs.length - 1;
        const prev  = document.getElementById('entryPrevBtn');
        const next  = document.getElementById('entryNextBtn');
        const save  = document.getElementById('entrySaveBtn');
        if (prev) prev.style.display  = idx > 0    ? 'flex' : 'none';
        if (next) next.style.display  = idx < last ? 'flex' : 'none';
        if (save) save.style.display  = idx === last ? 'flex' : 'none';
    },

    /* ── Validation ───────────────────────────────*/
    _validateBasics() {
        const title = document.getElementById('entry_title');
        let ok = true;
        [title].forEach(el => {
            if (!el) return;
            const valid = el.value.trim() !== '';
            el.classList.toggle('is-invalid', !valid);
            if (!valid) ok = false;
        });
        if (!ok) HJN.toast('Please add a title before continuing.', 'error');
        return ok;
    },

    _entryHasMedia() {
        const hasNewPhotos = Array.isArray(this._newPhotoFiles) && this._newPhotoFiles.length > 0;
        const hasNewVideos = Array.isArray(this._newVideoFiles) && this._newVideoFiles.length > 0;
        const existingImages = document.getElementById('existingImagesGrid')?.children.length || 0;
        const existingVideos = document.getElementById('existingVideosGrid')?.children.length || 0;
        return hasNewPhotos || hasNewVideos || existingImages > 0 || existingVideos > 0;
    },

    _validateEntrySubmit() {
        const title = document.getElementById('entry_title');
        let ok = true;
        let hasTitle = false;

        if (title) {
            hasTitle = title.value.trim() !== '';
            title.classList.toggle('is-invalid', !hasTitle);
            if (!hasTitle) ok = false;
        }

        const hasMedia = this._entryHasMedia();
        if (!hasMedia) {
            ok = false;
        }

        if (!ok) {
            if (!hasTitle && !hasMedia) {
                this.toast('Add a title and at least one photo or video before saving.', 'error');
            } else if (!hasMedia) {
                this.toast('Add at least one photo or video before saving.', 'error');
            } else if (!hasTitle) {
                this.toast('Please add a title before saving.', 'error');
            }
        }

        return ok;
    },

    _validateForm(form) {
        if (form?.id === 'entryForm') {
            return this._validateEntrySubmit();
        }

        let firstInvalid = null;
        let ok = true;
        form.querySelectorAll('[required]').forEach(el => {
            const valid = el.value.trim() !== '';
            el.classList.toggle('is-invalid', !valid);
            el.setAttribute('aria-invalid', valid ? 'false' : 'true');
            if (!valid) {
                ok = false;
                firstInvalid = firstInvalid || el;
            }
        });
        if (form.id === 'routineForm') {
            const title = form.querySelector('#routine_title');
            const steps = Array.from(form.querySelectorAll('input[name="routine_steps[]"]'));
            const hasStep = steps.some((step) => step.value.trim() !== '');
            if (!hasStep) {
                ok = false;
                const firstStep = steps[0];
                firstStep?.classList.add('is-invalid');
                firstStep?.setAttribute('aria-invalid', 'true');
                firstInvalid = firstInvalid || firstStep;
            }
            if (title && title.value.trim().length > 100) {
                ok = false;
                title.classList.add('is-invalid');
                title.setAttribute('aria-invalid', 'true');
                firstInvalid = firstInvalid || title;
            }
        }
        if (!ok) {
            HJN.toast(form.id === 'routineForm' ? 'Add a name and at least one clear step before saving.' : 'Please fill in all required fields.', 'error');
            firstInvalid?.focus();
        }
        return ok;
    },

    /* ── Reset / Populate Entry ───────────────────*/
    _resetEntry() {
        const form = document.getElementById('entryForm');
        if (form) form.reset();
        document.getElementById('entry_id').value = '';
        document.getElementById('featured_image_index').value = '0';
        document.getElementById('health_rating').value = '0';
        document.getElementById('entry_title').value   = '';
        document.getElementById('entry_date').value    = '<?php echo esc_js($today); ?>';
        document.getElementById('entry_time').value    = '<?php echo esc_js($now_time); ?>';
        document.querySelectorAll('#health_rating_stars .rating-star-hjn').forEach(s => s.classList.remove('is-active'));
        document.getElementById('health_rating_label').textContent = 'Not yet rated';
        document.getElementById('health_rating_label').classList.remove('is-rated');
        document.querySelectorAll('#moodPills .mood-pill-hjn').forEach(p => p.classList.remove('is-selected'));
        document.getElementById('mood_hidden').value = '';
        document.querySelectorAll('#entryTypePills .tag-pill-hjn').forEach(p => p.classList.remove('is-active'));
        document.getElementById('entry_type_hidden').value = '';
        document.getElementById('newMediaPreviewGrid').innerHTML  = '';
        document.getElementById('newVideoPreviewGrid').innerHTML  = '';
        this._newPhotoFiles = [];
        this._newVideoFiles = [];
        this._setFeaturedPhoto(0);
        this._clearProducts();
        this._entryTabIndex = 0;
        this._activateEntryTab(0);
        document.getElementById('entryOffcanvasTitle').textContent    = 'New Entry';
        document.getElementById('entryOffcanvasSubtitle').textContent = 'Log your hair care session';
        document.getElementById('existingImagesGallery').style.display = 'none';
        document.getElementById('existingVideosGallery').style.display = 'none';
    },

    _populateEntry(data) {
        document.getElementById('entryOffcanvasTitle').textContent    = 'Edit Entry';
        document.getElementById('entryOffcanvasSubtitle').textContent = data.title || '';
        const fields = ['entry_title','entry_date','entry_time','entry_content','notes',
                        'techniques','entry_tags','length_check_cm','next_step','entry_video_notes'];
        const names  = ['title','entry_date','entry_time','description','notes',
                        'techniques','entry_tags','length_check_cm','next_step','video_notes'];
        fields.forEach((id, i) => {
            const el = document.getElementById(id);
            if (el && data[names[i]] !== undefined) el.value = data[names[i]];
        });
        document.getElementById('entry_id').value = data.entry_id || '';
        if (data.rating) this._setRating(parseInt(data.rating));
        if (data.mood_demeanor) this._setMood(data.mood_demeanor);
        if (data.entry_type)    this._setTagPill('entryTypePills', 'entry_type_hidden', data.entry_type);
        ['environment','scalp_condition','hair_feel'].forEach(f => {
            const el = document.getElementById(f);
            if (el && data[f]) el.value = data[f];
        });
        this._setProducts(data.products_list || data.products_used || data.products || []);
        const featuredIndex = parseInt(data.featured_image_index || '0', 10);
        this._setFeaturedPhoto(Number.isNaN(featuredIndex) ? 0 : Math.max(0, featuredIndex));
    },

    /* ── Star Rating ──────────────────────────────*/
    _ratingLabels : ['','Needs love','Getting there','Good shape','Really healthy','Absolutely thriving'],
    _setRating(val) {
        document.getElementById('health_rating').value = val;
        const stars = document.querySelectorAll('#health_rating_stars .rating-star-hjn');
        stars.forEach((s, i) => {
            s.classList.toggle('is-active', i < val);
            s.setAttribute('aria-checked', i < val ? 'true' : 'false');
        });
        const lbl = document.getElementById('health_rating_label');
        lbl.textContent = val > 0 ? this._ratingLabels[val] : 'Not yet rated';
        lbl.classList.toggle('is-rated', val > 0);
    },

    /* ── Mood ─────────────────────────────────────*/
    _setMood(val) {
        document.getElementById('mood_hidden').value = val;
        document.querySelectorAll('#moodPills .mood-pill-hjn').forEach(p => {
            p.classList.toggle('is-selected', p.querySelector('input')?.value === val);
        });
    },

    /* ── Tag Pills ────────────────────────────────*/
    _setTagPill(containerId, hiddenId, val) {
        const pills  = document.querySelectorAll(`#${containerId} .tag-pill-hjn`);
        const hidden = document.getElementById(hiddenId);
        pills.forEach(p => {
            const active = p.dataset.value === val;
            p.classList.toggle('is-active', active);
        });
        if (hidden) hidden.value = val;
    },

    /* ── Goal Form ───────────────────────────────*/
    _resetGoal() {
        const form = document.getElementById('goalForm');
        if (form) form.reset();
        document.getElementById('goal_id').value = '';
        document.getElementById('goal_title').value = '';
        document.getElementById('goal_description').value = '';
        document.getElementById('goal_start_date').value = '<?php echo esc_js($today); ?>';
        document.getElementById('goal_end_date').value = '';
        document.getElementById('goal_priority').value = 'Medium';
        document.getElementById('goal_checkin_frequency').value = 'Weekly';
        document.getElementById('goal_baseline_value').value = '';
        document.getElementById('goal_target_value').value = '';
        document.getElementById('goal_measure_unit').value = '';
        document.getElementById('goal_progress').value = '0';
        document.getElementById('goal_progress_value').textContent = '0%';
        document.getElementById('goal_motivation').value = '';
        document.getElementById('goal_success_criteria').value = '';
        document.getElementById('goal_blockers').value = '';
        document.getElementById('goal_reward').value = '';
        document.getElementById('goal_category_hidden').value = '';
        document.getElementById('goalOffcanvasTitle').textContent = 'Create a goal';
        const saveGoal = document.getElementById('saveGoalBtn');
        if (saveGoal) saveGoal.lastChild.textContent = ' Save Goal';
        document.querySelectorAll('#goalCategoryPills .tag-pill-hjn').forEach(p => p.classList.remove('is-active'));
        document.getElementById('milestones_list').innerHTML = '';
        this._milestoneCounter = 0;
        const progressNotes = document.getElementById('goalProgressNotesGroup');
        if (progressNotes) progressNotes.style.display = 'none';
        const progressNotesList = document.getElementById('goalProgressNotesList');
        if (progressNotesList) progressNotesList.innerHTML = '';
        const noteInput = document.getElementById('newProgressNote');
        if (noteInput) noteInput.value = '';
    },

    _prefillGoal(data = {}) {
        if (!data || typeof data !== 'object') return;

        const category = String(data.category || data.goal_category || '').trim();
        const title = String(data.title || '').trim();
        const description = String(data.description || '').trim();
        const endDate = String(data.target_date || data.goal_end_date || '').trim();

        const fields = {
            goal_id: data.id ?? data.goal_id ?? '',
            goal_start_date: data.start_date ?? data.goal_start_date ?? '',
            goal_priority: data.priority ?? data.goal_priority ?? '',
            goal_checkin_frequency: data.checkin_frequency ?? data.goal_checkin_frequency ?? '',
            goal_baseline_value: data.baseline_value ?? data.goal_baseline_value ?? '',
            goal_target_value: data.target_value ?? data.goal_target_value ?? '',
            goal_measure_unit: data.measure_unit ?? data.goal_measure_unit ?? '',
            goal_progress: data.progress ?? data.goal_progress ?? '',
            goal_motivation: data.goal_motivation ?? '',
            goal_success_criteria: data.goal_success_criteria ?? '',
            goal_blockers: data.goal_blockers ?? '',
            goal_reward: data.goal_reward ?? '',
        };
        Object.entries(fields).forEach(([id, value]) => {
            const field = document.getElementById(id);
            if (field && value !== '' && value !== null && typeof value !== 'undefined') field.value = value;
        });
        const progressValue = document.getElementById('goal_progress')?.value || '0';
        const progressLabel = document.getElementById('goal_progress_value');
        if (progressLabel) progressLabel.textContent = progressValue + '%';
        if (fields.goal_id !== '') {
            document.getElementById('goalOffcanvasTitle').textContent = 'Edit goal';
            const saveGoal = document.getElementById('saveGoalBtn');
            if (saveGoal) saveGoal.lastChild.textContent = ' Save changes';
        }

        if (category) {
            this._setTagPill('goalCategoryPills', 'goal_category_hidden', category);
        }
        if (title) {
            document.getElementById('goal_title').value = title;
        }
        if (description) {
            document.getElementById('goal_description').value = description;
        }
        if (endDate) {
            document.getElementById('goal_end_date').value = endDate;
        }
    },

    /* ── Routine Form ────────────────────────────*/
    _resetRoutine() {
        const form = document.getElementById('routineForm');
        if (form) form.reset();
        document.getElementById('routine_id').value = '';
        document.getElementById('routine_title').value = '';
        document.getElementById('routine_frequency').value = 'Daily';
        document.getElementById('routine_duration').value = '';
        document.getElementById('routine_phase').value = 'Anytime';
        document.getElementById('routine_difficulty').value = 'Beginner';
        document.getElementById('routine_products').value = '';
        document.getElementById('routine_tools').value = '';
        document.getElementById('routine_expected_result').value = '';
        document.getElementById('routine_notes').value = '';
        document.getElementById('routine_time').value = '08:00';
        document.getElementById('routine_reminder_days').value = '';
        document.getElementById('routine_auto_track').checked = false;
        document.getElementById('routine_type_hidden').value = '';
        document.getElementById('routineOffcanvasTitle').textContent = 'Create a routine';
        const saveRoutine = document.getElementById('saveRoutineBtn');
        if (saveRoutine) saveRoutine.lastChild.textContent = ' Save Routine';
        document.querySelectorAll('#routineTypePills .tag-pill-hjn').forEach(p => p.classList.remove('is-active'));

        const list = document.getElementById('routine_steps_list');
        if (list) {
            list.innerHTML = `
                <div class="step-item-hjn" data-step="1">
                    <div class="step-num-hjn">1</div>
                    <input type="text" name="routine_steps[]" class="step-input-hjn" placeholder="Describe this step…" required>
                    <span class="step-drag-hjn" title="Drag to reorder">
                        <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M9 3h2v2H9zm4 0h2v2h-2zM9 7h2v2H9zm4 0h2v2h-2zM9 11h2v2H9zm4 0h2v2h-2zM9 15h2v2H9zm4 0h2v2h-2zM9 19h2v2H9zm4 0h2v2h-2z"/></svg>
                    </span>
                    <button type="button" class="step-remove-hjn" onclick="HJN.removeStep(this)" disabled aria-label="Remove step">
                        <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg>
                    </button>
                </div>
            `;
        }
        this._stepCounter = 1;
        this._renumberSteps();
    },

    _prefillRoutine(data = {}) {
        if (!data || typeof data !== 'object') return;

        const title = String(data.title || data.routine_title || '').trim();
        const type = String(data.type || data.routine_type || '').trim();
        const frequency = String(data.frequency || data.routine_frequency || '').trim();
        const duration = String(data.duration || data.routine_duration || '').trim();
        const notes = String(data.notes || data.routine_notes || data.description || '').trim();
        const phase = String(data.phase || data.routine_phase || '').trim();
        const difficulty = String(data.difficulty || data.routine_difficulty || '').trim();
        const time = String(data.time || data.routine_time || '').trim();
        const reminderDays = String(data.reminder_days || data.routine_reminder_days || '').trim();
        const expectedResult = String(data.expected_result || data.routine_expected_result || '').trim();
        const goalLink = String(data.goal_link || data.routine_goal_link || '').trim();
        const tools = String(data.tools || data.routine_tools || '').trim();
        const productsSource = data.products || data.routine_products || [];
        const stepsSource = data.steps || data.routine_steps || [];
        const products = Array.isArray(productsSource)
            ? productsSource
            : String(productsSource).split(/[\n,]+/).map((item) => item.trim()).filter(Boolean);
        const steps = Array.isArray(stepsSource)
            ? stepsSource
            : String(stepsSource).split(/[\n,]+/).map((item) => item.trim()).filter(Boolean);

        const routineId = data.id ?? data.routine_id ?? '';
        if (routineId !== '') {
            document.getElementById('routine_id').value = String(routineId);
            document.getElementById('routineOffcanvasTitle').textContent = 'Edit routine';
            const saveRoutine = document.getElementById('saveRoutineBtn');
            if (saveRoutine) saveRoutine.lastChild.textContent = ' Save changes';
        }

        if (title) {
            document.getElementById('routine_title').value = title;
        }
        if (type) {
            this._setTagPill('routineTypePills', 'routine_type_hidden', type);
        }
        if (frequency) {
            document.getElementById('routine_frequency').value = frequency;
        }
        if (duration) {
            document.getElementById('routine_duration').value = duration;
        }
        if (notes) {
            document.getElementById('routine_notes').value = notes;
        }
        if (phase) {
            document.getElementById('routine_phase').value = phase;
        }
        if (difficulty) {
            document.getElementById('routine_difficulty').value = difficulty;
        }
        if (time) {
            document.getElementById('routine_time').value = time;
        }
        if (reminderDays) {
            document.getElementById('routine_reminder_days').value = reminderDays;
        }
        if (expectedResult) {
            document.getElementById('routine_expected_result').value = expectedResult;
        }
        if (goalLink) {
            const goalLinkField = document.getElementById('routine_goal_link');
            if (goalLinkField) goalLinkField.value = goalLink;
        }
        if (tools) {
            document.getElementById('routine_tools').value = tools;
        }
        if (products.length) {
            document.getElementById('routine_products').value = products.join('\n');
        }
        if (typeof data.auto_track !== 'undefined' || typeof data.routine_auto_track !== 'undefined') {
            document.getElementById('routine_auto_track').checked = !!(data.auto_track || data.routine_auto_track);
        }
        if (steps.length) {
            const list = document.getElementById('routine_steps_list');
            if (list) {
                list.innerHTML = '';
                steps.forEach((stepText) => {
                    this.addStep();
                    const lastInput = list.querySelector('.step-item-hjn:last-child .step-input-hjn');
                    if (lastInput) {
                        lastInput.value = String(stepText || '').trim();
                    }
                });

                const firstEmpty = list.querySelector('.step-item-hjn:first-child .step-input-hjn');
                if (firstEmpty && !firstEmpty.value && steps[0]) {
                    firstEmpty.value = String(steps[0] || '').trim();
                }

                const items = list.querySelectorAll('.step-item-hjn');
                if (items.length > steps.length) {
                    items[0].remove();
                }
                this._renumberSteps();
            }
        }
    },

    /* ── Products ─────────────────────────────────*/
    _setProducts(value) {
        const select = document.getElementById('products_used');
        if (!select) return;

        const products = Array.isArray(value)
            ? value
            : String(value || '').split(/[\n,]+/).map(v => v.trim()).filter(Boolean);

        products.forEach(product => {
            if (!Array.from(select.options).some(option => option.value === product)) {
                select.add(new Option(product, product, true, true));
            }
        });

        Array.from(select.options).forEach(option => {
            option.selected = products.includes(option.value);
        });

        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(select).val(products).trigger('change');
        }
    },

    _clearProducts() {
        const select = document.getElementById('products_used');
        if (!select) return;
        Array.from(select.options).forEach(option => { option.selected = false; });
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(select).val(null).trigger('change');
        }
    },

    /* ── Featured Photo ───────────────────────────*/
    _getFeaturedPhotoIndex() {
        const input = document.getElementById('featured_image_index');
        if (!input) return 0;
        const parsed = parseInt(input.value || '0', 10);
        return Number.isNaN(parsed) || parsed < 0 ? 0 : parsed;
    },

    _setFeaturedPhoto(index) {
        const safeIndex = Number.isFinite(index) && index >= 0 ? index : 0;
        const input = document.getElementById('featured_image_index');
        if (input) input.value = String(safeIndex);
        this._syncFeaturedPhotoUI();
    },

    _syncFeaturedPhotoUI() {
        const grid = document.getElementById('newMediaPreviewGrid');
        if (!grid) return;
        const featuredIndex = this._getFeaturedPhotoIndex();
        grid.querySelectorAll('.photo-preview-item-hjn[data-photo-index]').forEach(item => {
            const idx = parseInt(item.getAttribute('data-photo-index') || '-1', 10);
            const active = idx === featuredIndex;
            item.classList.toggle('is-featured', active);
            const btn = item.querySelector('.preview-featured-hjn');
            if (btn) {
                btn.textContent = active ? 'Featured' : 'Set cover';
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            }
        });
    },

    _reindexPhotoPreview() {
        const grid = document.getElementById('newMediaPreviewGrid');
        if (!grid) return;
        const cards = Array.from(grid.querySelectorAll('.photo-preview-item-hjn[data-photo-index]'));
        cards.forEach((card, index) => card.setAttribute('data-photo-index', String(index)));

        if (!cards.length) {
            this._setFeaturedPhoto(0);
            return;
        }

        const current = this._getFeaturedPhotoIndex();
        if (current >= cards.length) {
            this._setFeaturedPhoto(0);
        } else {
            this._syncFeaturedPhotoUI();
        }
    },

    /* ── Media upload helpers ─────────────────────*/
    _addFilesToPreview(files, type) {
        const grid = document.getElementById(type === 'photo' ? 'newMediaPreviewGrid' : 'newVideoPreviewGrid');
        const max  = type === 'photo' ? 5 : 2;
        const list = type === 'photo' ? this._newPhotoFiles : this._newVideoFiles;
        Array.from(files).forEach(file => {
            if (list.length >= max) { HJN.toast(`Max ${max} ${type}s allowed.`, 'error'); return; }
            const size  = type === 'photo' ? 5 : 30;
            if (file.size > size * 1024 * 1024) { HJN.toast(`${file.name} exceeds ${size}MB.`, 'error'); return; }
            list.push(file);
            const item = document.createElement('div');
            item.className = 'photo-preview-item-hjn';
            const url = URL.createObjectURL(file);
            const isVid = file.type.startsWith('video/');
            item.innerHTML = `
                ${isVid
                    ? `<video src="${url}" muted playsinline preload="metadata"></video>`
                    : `<img src="${url}" alt="${file.name}" loading="lazy">`}
                <div class="preview-overlay-hjn"></div>
                <button type="button" class="preview-remove-hjn" aria-label="Remove">✕</button>
                ${!isVid ? `<button type="button" class="preview-featured-hjn" aria-label="Set as featured image" aria-pressed="false">Set cover</button>` : ''}
                <span class="preview-type-badge-hjn">${isVid ? 'Video' : 'Photo'}</span>
            `;
            item.querySelector('.preview-remove-hjn').addEventListener('click', () => {
                const idx = list.indexOf(file);
                if (idx > -1) list.splice(idx, 1);
                URL.revokeObjectURL(url);
                item.remove();
                if (!isVid) this._reindexPhotoPreview();
            });

            if (!isVid) {
                item.setAttribute('data-photo-index', String(list.length - 1));
                item.addEventListener('click', (event) => {
                    if (event.target.closest('.preview-remove-hjn')) return;
                    const idx = parseInt(item.getAttribute('data-photo-index') || '0', 10);
                    this._setFeaturedPhoto(idx);
                });
                item.querySelector('.preview-featured-hjn')?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const idx = parseInt(item.getAttribute('data-photo-index') || '0', 10);
                    this._setFeaturedPhoto(idx);
                });
            }
            grid.appendChild(item);
            if (!isVid) {
                this._reindexPhotoPreview();
                if (this._newPhotoFiles.length === 1) this._setFeaturedPhoto(0);
            }
        });
    },

    /* ── Routine Steps ────────────────────────────*/
    addStep() {
        const list = document.getElementById('routine_steps_list');
        if (!list) return;
        this._stepCounter++;
        const item = document.createElement('div');
        item.className = 'step-item-hjn';
        item.dataset.step = this._stepCounter;
        item.innerHTML = `
            <div class="step-num-hjn">${this._stepCounter}</div>
            <input type="text" name="routine_steps[]" class="step-input-hjn" placeholder="Describe this step…">
            <span class="step-drag-hjn" title="Drag to reorder">
                <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M9 3h2v2H9zm4 0h2v2h-2zM9 7h2v2H9zm4 0h2v2h-2zM9 11h2v2H9zm4 0h2v2h-2zM9 15h2v2H9zm4 0h2v2h-2zM9 19h2v2H9zm4 0h2v2h-2z"/></svg>
            </span>
            <button type="button" class="step-remove-hjn" onclick="HJN.removeStep(this)" aria-label="Remove step">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg>
            </button>
        `;
        list.appendChild(item);
        this._renumberSteps();
        item.querySelector('.step-input-hjn')?.focus();
    },

    removeStep(btn) {
        const list = document.getElementById('routine_steps_list');
        if (!list) return;
        btn.closest('.step-item-hjn')?.remove();
        this._renumberSteps();
    },

    _renumberSteps() {
        const items = document.querySelectorAll('#routine_steps_list .step-item-hjn');
        items.forEach((item, i) => {
            const num = item.querySelector('.step-num-hjn');
            if (num) num.textContent = i + 1;
            const rmBtn = item.querySelector('.step-remove-hjn');
            if (rmBtn) rmBtn.disabled = items.length <= 1;
        });
    },

    /* ── Milestones ───────────────────────────────*/
    addMilestone() {
        const list = document.getElementById('milestones_list');
        if (!list) return;
        this._milestoneCounter++;
        const item = document.createElement('div');
        item.className = 'milestone-item-hjn';
        item.innerHTML = `
            <input type="text" name="goal_milestones[]" class="form-input-hjn"
                   placeholder="Milestone ${this._milestoneCounter}…" style="flex:1">
            <button type="button" class="milestone-remove-hjn" onclick="this.closest('.milestone-item-hjn').remove()" aria-label="Remove">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg>
            </button>
        `;
        list.appendChild(item);
        item.querySelector('input')?.focus();
    },

    /* ── Toast ────────────────────────────────────*/
    toast(msg, type = 'default') {
        const container = document.getElementById('hjnToastContainer');
        if (!container) return;
        const icons = { success:'✓', error:'✕', default:'ℹ' };
        const t = document.createElement('div');
        t.className = `hjn-toast ${type === 'success' ? 'is-success' : type === 'error' ? 'is-error' : ''}`;
        t.textContent = (icons[type] || '') + ' ' + msg;
        container.appendChild(t);
        setTimeout(() => {
            t.classList.add('is-out');
            t.addEventListener('animationend', () => t.remove());
        }, 3200);
    },

    /* ── Form Submission ──────────────────────────*/
    _submitForm(formId, loaderId) {
        const form   = document.getElementById(formId);
        const loader = document.getElementById(loaderId);
        if (!form) return;
        if (!this._validateForm(form)) return;

        const submitButton = form.querySelector('[type="submit"]');
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        submitButton?.setAttribute('aria-busy', 'true');
        if (submitButton) submitButton.disabled = true;
        loader && (loader.style.display = 'flex');

        const entryId = String(document.getElementById('entry_id')?.value || '').trim();
        const isEntryForm = formId === 'entryForm';
        const isEntryCreate = isEntryForm && !entryId;
        const goalId = String(document.getElementById('goal_id')?.value || '').trim();
        const routineId = String(document.getElementById('routine_id')?.value || '').trim();
        const isGoalCreate = formId === 'goalForm' && !goalId;
        const fd = new FormData(form);

        // Append actual file objects for photos / videos
        if (isEntryForm) {
            const settings = window.myavanaTimelineSettings || {};
            const nonce = entryId
                ? (settings.updateEntryNonce || settings.addEntryNonce || settings.nonce || '')
                : (settings.addEntryNonce || settings.nonce || '');
            const titleInput = document.getElementById('entry_title');
            const descriptionInput = document.getElementById('entry_content');
            const dateInput = document.getElementById('entry_date');
            const timeInput = document.getElementById('entry_time');
            const moodInput = document.getElementById('mood_hidden');
            const entryTypeInput = document.getElementById('entry_type_hidden');
            fd.set('action', 'myavana_entry_action');
            fd.set('form_type', 'entry');
            fd.set('title', titleInput?.value?.trim() || '');
            fd.set('entry_title', titleInput?.value?.trim() || '');
            fd.set('description', descriptionInput?.value || '');
            fd.set('entry_content', descriptionInput?.value || '');
            fd.set('entry_date', dateInput?.value || '');
            fd.set('entry_time', timeInput?.value || '');
            fd.set('mood_demeanor', moodInput?.value || '');
            fd.set('entry_type', entryTypeInput?.value || '');
            if (nonce) {
                fd.set('security', nonce);
                fd.set('myavana_nonce', nonce);
            }
            fd.set('featured_image_index', String(this._getFeaturedPhotoIndex()));
            this._newPhotoFiles.forEach(f => fd.append('entry_photos[]', f));
            this._newVideoFiles.forEach(f => fd.append('entry_videos[]', f));
        } else if (formId === 'goalForm') {
            const settings = window.myavanaTimelineSettings || {};
            const goalId = String(document.getElementById('goal_id')?.value || '').trim();
            const isUpdate = goalId !== '';
            const nonce = isUpdate
                ? (settings.updateGoalNonce || settings.addGoalNonce || settings.nonce || '')
                : (settings.addGoalNonce || settings.nonce || '');

            fd.set('action', isUpdate ? 'myavana_update_goal' : 'myavana_add_goal');
            if (nonce) {
                fd.set('security', nonce);
                fd.set('myavana_nonce', nonce);
            }
        } else if (formId === 'routineForm') {
            const settings = window.myavanaTimelineSettings || {};
            const routineId = String(document.getElementById('routine_id')?.value || '').trim();
            const isUpdate = routineId !== '';
            const nonce = isUpdate
                ? (settings.updateRoutineNonce || settings.addRoutineNonce || settings.nonce || '')
                : (settings.addRoutineNonce || settings.nonce || '');

            fd.set('action', isUpdate ? 'myavana_update_routine' : 'myavana_add_routine');
            if (nonce) {
                fd.set('security', nonce);
                fd.set('myavana_nonce', nonce);
            }
        }

        const settings = window.myavanaTimelineSettings || {};
        const endpoint = settings.ajaxUrl || settings.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        fetch(endpoint, {
            method : 'POST',
            body   : fd,
            credentials : 'same-origin',
        })
        .then(async (response) => {
            const raw = await response.text();
            try {
                return JSON.parse(raw);
            } catch (error) {
                throw new Error(raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220) || 'Unexpected server response.');
            }
        })
        .then(res => {
            loader && (loader.style.display = 'none');
            delete form.dataset.submitting;
            submitButton?.removeAttribute('aria-busy');
            if (submitButton) submitButton.disabled = false;
            if (res.success) {
                const successMessage = typeof res.data === 'string'
                    ? res.data
                    : (res.data?.message || 'Saved successfully!');
                this.toast(successMessage, 'success');
                this.closeOffcanvas();
                const shouldReloadForCollections =
                    (formId === 'goalForm' && document.getElementById('myavanaGoalsV2Root')) ||
                    (formId === 'routineForm' && document.getElementById('myavanaRoutinesV2Root'));

                if (isEntryCreate || shouldReloadForCollections) {
                    if (formId === 'routineForm' || formId === 'goalForm') {
                        try { window.sessionStorage.setItem(formId === 'routineForm' ? 'myavanaRoutineFeedback' : 'myavanaGoalFeedback', successMessage); } catch (error) {}
                    }
                    setTimeout(() => {
                        const url = new URL(window.location.href);
                        url.searchParams.delete('create');
                        url.searchParams.delete('category');
                        url.searchParams.delete('template');
                        url.searchParams.delete('title');
                        window.location.href = url.toString();
                    }, 280);
                    return;
                }
                // Optionally refresh the journey view
                if (typeof window.hjnRefresh === 'function') window.hjnRefresh();
            } else {
                const errorMessage = typeof res.data === 'string'
                    ? res.data
                    : (res.data?.message || 'Something went wrong.');
                this.toast(errorMessage, 'error');
            }
        })
        .catch((error) => {
            loader && (loader.style.display = 'none');
            delete form.dataset.submitting;
            submitButton?.removeAttribute('aria-busy');
            if (submitButton) submitButton.disabled = false;
            this.toast(error?.message || 'Connection error. Please try again.', 'error');
        });
    },
};

/* Backward compatibility + load-order hardening.
   Always prefer the new HJN controller, then fallback to any legacy function. */
const _legacyOpenOffcanvas = window.openOffcanvas;
const _legacyCloseOffcanvas = window.closeOffcanvas;

window.openOffcanvas = function(type, data) {
    if (window.HJN && typeof window.HJN.openOffcanvas === 'function') {
        if (data && typeof data === 'object') {
            window.HJN.openOffcanvas(type, data);
            return;
        }
        if (data === null || typeof data === 'undefined' || data === '') {
            window.HJN.openOffcanvas(type, {});
            return;
        }
    }
    if (typeof _legacyOpenOffcanvas === 'function' && _legacyOpenOffcanvas !== window.openOffcanvas) {
        _legacyOpenOffcanvas(type, data);
    }
};

window.closeOffcanvas = function() {
    if (window.HJN && typeof window.HJN.closeOffcanvas === 'function') {
        window.HJN.closeOffcanvas();
        return;
    }
    if (typeof _legacyCloseOffcanvas === 'function' && _legacyCloseOffcanvas !== window.closeOffcanvas) {
        _legacyCloseOffcanvas();
    }
};

/* ── Init ─────────────────────────────────────────*/
function initHjnOffcanvas() {
    if (window.__myavanaHjnOffcanvasInitialized) return;
    window.__myavanaHjnOffcanvasInitialized = true;

    /* Star rating */
    document.querySelectorAll('#health_rating_stars .rating-star-hjn').forEach(star => {
        star.addEventListener('mouseenter', () => {
            const val = parseInt(star.dataset.value);
            document.querySelectorAll('#health_rating_stars .rating-star-hjn').forEach((s, i) => {
                s.classList.toggle('is-hover', i < val);
            });
        });
        star.addEventListener('mouseleave', () => {
            document.querySelectorAll('#health_rating_stars .rating-star-hjn').forEach(s => s.classList.remove('is-hover'));
        });
        star.addEventListener('click', () => HJN._setRating(parseInt(star.dataset.value)));
    });

    /* Mood pills */
    document.querySelectorAll('#moodPills .mood-pill-hjn').forEach(pill => {
        pill.addEventListener('click', () => {
            const val = pill.querySelector('input')?.value || '';
            HJN._setMood(val);
        });
    });

    /* Tag pills — entry type */
    document.querySelectorAll('#entryTypePills .tag-pill-hjn').forEach(pill => {
        pill.addEventListener('click', () => {
            const isActive = pill.classList.contains('is-active');
            document.querySelectorAll('#entryTypePills .tag-pill-hjn').forEach(p => p.classList.remove('is-active'));
            if (!isActive) {
                pill.classList.add('is-active');
                document.getElementById('entry_type_hidden').value = pill.dataset.value;
            } else {
                document.getElementById('entry_type_hidden').value = '';
            }
        });
    });

    /* Tag pills — goal category */
    document.querySelectorAll('#goalCategoryPills .tag-pill-hjn').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('#goalCategoryPills .tag-pill-hjn').forEach(p => p.classList.remove('is-active'));
            pill.classList.add('is-active');
            document.getElementById('goal_category_hidden').value = pill.dataset.value;
        });
    });

    /* Tag pills — routine type */
    document.querySelectorAll('#routineTypePills .tag-pill-hjn').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('#routineTypePills .tag-pill-hjn').forEach(p => p.classList.remove('is-active'));
            pill.classList.add('is-active');
            document.getElementById('routine_type_hidden').value = pill.dataset.value;
        });
    });

    /* Entry form tabs via header buttons */
    document.querySelectorAll('#entryTabs .form-tab-hjn').forEach((btn, idx) => {
        btn.addEventListener('click', () => {
            HJN._entryTabIndex = idx;
            HJN._activateEntryTab(idx);
            HJN._refreshEntryNav();
        });
    });

    /* Character counters */
    function bindCounter(textareaId, counterId, max) {
        const ta  = document.getElementById(textareaId);
        const ctr = document.getElementById(counterId);
        if (!ta || !ctr) return;
        const update = () => {
            const len = ta.value.length;
            ctr.textContent = len + ' / ' + max;
            ctr.classList.toggle('is-warning', len > max * 0.85);
        };
        ta.addEventListener('input', update);
        update();
    }
    bindCounter('entry_content',   'entry_content_count',  2000);
    bindCounter('newProgressNote', 'progress_note_count',   500);

    /* Products select */
    function initProductsSelect() {
        const select = document.getElementById('products_used');
        if (!select) return;

        const catalog = [
            'Shampoo', 'Conditioner', 'Leave-In Conditioner', 'Deep Conditioner', 'Hair Mask',
            'Scalp Serum', 'Scalp Oil', 'Curl Cream', 'Styling Gel', 'Mousse',
            'Heat Protectant', 'Hair Oil', 'Edge Control', 'Dry Shampoo', 'Protein Treatment'
        ];

        if (select.options.length === 0) {
            catalog.forEach(product => select.add(new Option(product, product)));
        }

        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            const $select = window.jQuery(select);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                width: '100%',
                placeholder: 'Select or type products...',
                tags: true,
                tokenSeparators: [',', ';'],
                dropdownParent: window.jQuery('#entryOffcanvas')
            });
        }
    }
    initProductsSelect();

    /* Inline validation: clear on input */
    document.querySelectorAll('.form-input-hjn, .form-select-hjn, .form-textarea-hjn').forEach(el => {
        el.addEventListener('input', () => el.classList.remove('is-invalid'));
        el.addEventListener('change', () => el.classList.remove('is-invalid'));
    });

    /* ── Drag & Drop — Photos ─── */
    const photoZone = document.getElementById('photoDropZone');
    if (photoZone) {
        photoZone.addEventListener('click', (e) => {
            if (!e.target.closest('button')) document.getElementById('photoFilePicker')?.click();
        });
        photoZone.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') document.getElementById('photoFilePicker')?.click();
        });
        photoZone.addEventListener('dragover', (e) => { e.preventDefault(); photoZone.classList.add('is-dragover'); });
        photoZone.addEventListener('dragleave', () => photoZone.classList.remove('is-dragover'));
        photoZone.addEventListener('drop', (e) => {
            e.preventDefault();
            photoZone.classList.remove('is-dragover');
            HJN._addFilesToPreview(e.dataTransfer.files, 'photo');
        });
    }

    /* ── Drag & Drop — Videos ─── */
    const videoZone = document.getElementById('videoDropZone');
    if (videoZone) {
        videoZone.addEventListener('click', (e) => {
            if (!e.target.closest('button')) document.getElementById('videoFilePicker')?.click();
        });
        videoZone.addEventListener('dragover', (e) => { e.preventDefault(); videoZone.classList.add('is-dragover'); });
        videoZone.addEventListener('dragleave', () => videoZone.classList.remove('is-dragover'));
        videoZone.addEventListener('drop', (e) => {
            e.preventDefault();
            videoZone.classList.remove('is-dragover');
            HJN._addFilesToPreview(e.dataTransfer.files, 'video');
        });
    }

    /* Upload button wiring */
    const wire = (btnId, inputId) => {
        const btn = document.getElementById(btnId);
        const inp = document.getElementById(inputId);
        if (btn && inp) btn.addEventListener('click', (e) => { e.stopPropagation(); inp.click(); });
    };
    wire('choosePhotoBtn',  'photoFilePicker');
    wire('cameraPhotoBtn',  'cameraPhotoPicker');
    wire('chooseVideoBtn',  'videoFilePicker');
    wire('cameraVideoBtn',  'cameraVideoPicker');

    /* File input change handlers */
    document.getElementById('photoFilePicker')?.addEventListener('change', function() {
        HJN._addFilesToPreview(this.files, 'photo'); this.value = '';
    });
    document.getElementById('cameraPhotoPicker')?.addEventListener('change', function() {
        HJN._addFilesToPreview(this.files, 'photo'); this.value = '';
    });
    document.getElementById('videoFilePicker')?.addEventListener('change', function() {
        HJN._addFilesToPreview(this.files, 'video'); this.value = '';
    });
    document.getElementById('cameraVideoPicker')?.addEventListener('change', function() {
        HJN._addFilesToPreview(this.files, 'video'); this.value = '';
    });

    /* Progress range slider fill */
    function bindRange(rangeId, valId) {
        const range = document.getElementById(rangeId);
        const val   = document.getElementById(valId);
        if (!range) return;
        const update = () => {
            const pct = ((range.value - range.min) / (range.max - range.min)) * 100;
            range.style.backgroundSize = pct + '% 100%';
            if (val) val.textContent = range.value + '%';
        };
        range.addEventListener('input', update);
        update();
    }
    bindRange('goal_progress', 'goal_progress_value');

    /* Form submissions */
    document.getElementById('entryForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        e.stopImmediatePropagation();
        HJN._submitForm('entryForm', 'entryFormLoading');
    }, true);
    document.getElementById('goalForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        e.stopImmediatePropagation();
        HJN._submitForm('goalForm', 'goalFormLoading');
    }, true);
    document.getElementById('routineForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        e.stopImmediatePropagation();
        HJN._submitForm('routineForm', 'routineFormLoading');
    }, true);

    /* Keyboard: Escape closes */
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && HJN._currentOffcanvas) HJN.closeOffcanvas();
        if (e.key !== 'Tab' || !HJN._currentOffcanvas) return;
        const focusable = Array.from(HJN._currentOffcanvas.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
            .filter((element) => element.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    /* Initial step renumber */
    HJN._renumberSteps();

} /* end initHjnOffcanvas */

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHjnOffcanvas, { once: true });
} else {
    initHjnOffcanvas();
}

})();
</script>
