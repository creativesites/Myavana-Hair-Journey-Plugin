<?php
// Partial: offcanvas.php
?>
        <!-- Offcanvas Overlay -->
        <div class="offcanvas-overlay" id="offcanvasOverlay" onclick="closeOffcanvas()"></div>

        <!-- Entry Offcanvas -->
        <div class="offcanvas" id="entryOffcanvas">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title" id="entryOffcanvasTitle">New Hair Journey Entry</h2>
                <button class="offcanvas-close" onclick="closeOffcanvas()">&times;</button>
            </div>

            <div class="offcanvas-body">
                <form id="entryForm" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('myavana_add_entry','myavana_add_entry_nonce', true, false); ?>

                    <!-- Hidden field for entry ID (used for edit mode) -->
                    <input type="hidden" id="entry_id" name="entry_id" value="">

                    <div class="form-group">
                        <label class="form-label">Entry Title</label>
                        <input type="text" class="form-input" id="entry_title" name="entry_title" placeholder="e.g., Week 4 Progress Update" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-input" id="entry_date" name="entry_date" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="entry_category" name="entry_category" required>
                                <option value="">Select category</option>
                                <option value="progress">Progress Update</option>
                                <option value="routine">Routine</option>
                                <option value="product">Product Review</option>
                                <option value="milestone">Milestone</option>
                                <option value="note">General Note</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-textarea" id="entry_content" name="entry_description" rows="5" placeholder="Describe your progress, observations, or thoughts about your hair..." required></textarea>
                        <span class="character-count"><span id="entry_content_count">0</span>/1000</span>
                    </div>

                    <!-- Existing Images Display (for edit mode) -->
                    <div id="existingImagesGallery" style="display: none;">
                        <label class="form-label">Current Images</label>
                        <div id="existingImagesGrid" class="existing-images-grid"></div>
                    </div>

                    <!-- Photo Upload -->
                    <div class="form-group">
                        <label class="form-label">Photos (Optional)</label>
                        <input type="file"
                               id="entry_photos"
                               name="entry_photos[]"
                               multiple
                               accept="image/*"
                               data-max-files="5">
                        <small class="form-help">Upload up to 5 photos (5MB max each)</small>
                    </div>

                    <!-- Rating -->
                    <div class="form-group">
                        <label class="form-label">Hair Health Rating</label>
                        <div class="rating-stars" id="entry_rating">
                            <span class="rating-star" data-rating="1">★</span>
                            <span class="rating-star" data-rating="2">★</span>
                            <span class="rating-star" data-rating="3">★</span>
                            <span class="rating-star" data-rating="4">★</span>
                            <span class="rating-star" data-rating="5">★</span>
                        </div>
                        <input type="hidden" id="health_rating" name="health_rating" value="0">
                    </div>
                </form>
            </div>

            <div class="offcanvas-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas()">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="submitEntry()">Save Entry</button>
            </div>
        </div>

        <!-- Goal Offcanvas -->
        <div class="offcanvas" id="goalOffcanvas">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title">New Hair Goal</h2>
                <button class="offcanvas-close" onclick="closeOffcanvas()">&times;</button>
            </div>

            <div class="offcanvas-body">
                <form id="goalForm" method="post">
                    <?php wp_nonce_field('myavana_add_goal','myavana_add_goal_nonce', true, false); ?>
                    <div class="form-group">
                        <label class="form-label">Goal Title</label>
                        <input type="text" class="form-input" name="goal_title" placeholder="e.g., Grow hair to waist length" required>
                    </div>

                    <!-- Additional fields omitted for brevity -->
                </form>
            </div>

            <div class="offcanvas-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas()">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="submitGoal()">Save Goal</button>
            </div>
        </div>

        <!-- Routine Offcanvas -->
        <div class="offcanvas" id="routineOffcanvas">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title">New Hair Care Routine</h2>
                <button class="offcanvas-close" onclick="closeOffcanvas()">&times;</button>
            </div>

            <div class="offcanvas-body">
                <form id="routineForm" method="post">
                    <?php wp_nonce_field('myavana_add_routine','myavana_add_routine_nonce', true, false); ?>
                    <div class="form-group">
                        <label class="form-label">Routine Name</label>
                        <input type="text" class="form-input" name="routine_name" placeholder="e.g., My Wash Day Routine" required>
                    </div>

                    <!-- Additional fields omitted for brevity -->
                </form>
            </div>

            <div class="offcanvas-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas()">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="submitRoutine()">Save Routine</button>
            </div>
        </div>
