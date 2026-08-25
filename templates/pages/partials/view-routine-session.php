<?php
/**
 * Routine Session UI Partial
 * Used in Routines V2 and Hair Journey Timeline
 */

$icon = isset($icon) && is_callable($icon) ? $icon : static function ($name, $class = '') {
    if (function_exists('myavana_lucide_icon')) {
        return myavana_lucide_icon($name, $class);
    }
    if (function_exists('myavana_feather_icon')) {
        return myavana_feather_icon($name, $class);
    }
    return '';
};
?>
<div class="myavana-rv2-session" id="myavanaRv2Session" aria-hidden="true">
    <div class="myavana-rv2-session-backdrop" data-rv2-close-session></div>
    <div class="myavana-rv2-session-sheet">
        <div class="myavana-rv2-handle"></div>
        <header class="myavana-rv2-session-head">
            <div>
                <h3 id="myavanaRv2SessionTitle">Routine Session</h3>
                <p id="myavanaRv2SessionSub">Step 1 of 1</p>
            </div>
            <div class="myavana-rv2-session-timer" id="myavanaRv2SessionTimer">00:00</div>
        </header>
        <div class="myavana-rv2-session-progress">
            <div class="track"><div class="fill" id="myavanaRv2SessionBar"></div></div>
            <div class="label" id="myavanaRv2SessionLabel">0/0 done</div>
        </div>
        <div class="myavana-rv2-session-body" id="myavanaRv2SessionSteps"></div>
        <footer class="myavana-rv2-session-foot">
            <button type="button" class="myavana-rv2-pill-btn" id="myavanaRv2SessionDoneBtn"><?php echo $icon('check', 'is-xs'); ?>Mark Step Done</button>
            <button type="button" class="myavana-rv2-pill-btn ghost" data-rv2-close-session><?php echo $icon('pause', 'is-xs'); ?>Pause</button>
            <button type="button" class="myavana-rv2-pill-btn ghost" id="myavanaRv2LogEntryBtn"><?php echo $icon('square-pen', 'is-xs'); ?>Log Entry</button>
        </footer>
    </div>
</div>
