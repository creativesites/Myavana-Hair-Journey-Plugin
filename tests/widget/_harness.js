/**
 * Shared harness for the Mya widget suites.
 *
 * These run the real, unmodified widget bundle inside jsdom — no build step and
 * no test-only branches in the widget itself. jsdom is not a dependency of this
 * plugin (WordPress plugins ship no node_modules), so we resolve it from the
 * chatbot repo, which already has it. Set MYAVANA_JSDOM to override.
 */
const path = require('path');
const fs = require('fs');

const PLUGIN_ROOT = path.resolve(__dirname, '..', '..');

const JSDOM_CANDIDATES = [
    process.env.MYAVANA_JSDOM,
    path.join(process.env.HOME || '', 'WebstormProjects/Myavana-Chatbot/node_modules/jsdom'),
    'jsdom',
].filter(Boolean);

function loadJSDOM() {
    for (const candidate of JSDOM_CANDIDATES) {
        try {
            return require(candidate).JSDOM;
        } catch (err) { /* try the next one */ }
    }
    console.error(
        'Could not resolve jsdom. Install it, or point MYAVANA_JSDOM at an existing copy:\n' +
        '  MYAVANA_JSDOM=/path/to/node_modules/jsdom node tests/widget/<suite>.js'
    );
    process.exit(2);
}

const JSDOM = loadJSDOM();

const WIDGET = path.join(PLUGIN_ROOT, 'assets/js/myavana-widget.js');
const EMBED = path.join(PLUGIN_ROOT, 'assets/js/modules/mya-widget-embed.js');

/** Boot a fresh jsdom window with the widget bundle evaluated in it. */
function boot(url, opts = {}) {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', {
        url,
        runScripts: 'outside-only',
        pretendToBeVisual: true,
    });
    const w = dom.window;
    w.requestAnimationFrame = (cb) => setTimeout(cb, 0);
    w.matchMedia = () => ({ matches: false, addListener() {}, removeListener() {} });
    if (opts.fetch) w.fetch = opts.fetch;
    global.window = w;
    global.document = w.document;
    w.eval(fs.readFileSync(WIDGET, 'utf8'));
    if (opts.withEmbed) w.eval(fs.readFileSync(EMBED, 'utf8'));
    return w;
}

let passed = 0;
let failed = 0;

function ok(name, condition) {
    if (condition) { passed++; console.log('  PASS  ' + name); }
    else { failed++; console.log('  FAIL  ' + name); }
}

// Suites are async (they wait on jsdom timers), so report on exit.
process.on('exit', () => {
    console.log(`\n  ${passed} passed, ${failed} failed`);
    if (failed > 0) process.exitCode = 1;
});

module.exports = { JSDOM, boot, ok, WIDGET, EMBED, PLUGIN_ROOT };
