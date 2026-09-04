# Mya Widget Tests

94 assertions across 7 suites, covering the Mya chat widget and the WordPress
platform adapter. They run the **real, unmodified bundle** from `assets/js/` inside
jsdom — there is no build step and no test-only branch in the widget itself.

## Running

```
./tests/run.sh
```

jsdom is not vendored here (WordPress plugins ship no `node_modules`), so the harness
resolves it from the chatbot repo, which already depends on it. Override if needed:

```
MYAVANA_JSDOM=/path/to/node_modules/jsdom ./tests/run.sh
```

Run one suite directly with `node tests/widget/04_history_buckets_and_markdown.js`.

## The suites

| Suite | Covers |
|:---|:---|
| `01_stories_and_profile_real_data` | Stories reel, timeline, entry detail, Profile card — all rendering real records; no fabricated data |
| `02_empty_error_and_offplatform_states` | Zero entries, provider failure, and surfaces with no adapter registered |
| `03_embed_adapter_and_rest_envelope` | `mya-widget-embed.js` end to end: hash routing, nonce on the REST call, `{success,data}` unwrapping |
| `04_history_buckets_and_markdown` | Recency buckets, relative times, search + no-match, markdown lists/quotes/bold |
| `05_streaming_chat` | NDJSON streaming, caret, busy composer, thinking dots, copy-to-clipboard |
| `06_style_contracts` | CSS contracts that behaviour depends on (grouping seams, reduced-motion, focus-visible) |
| `07_message_grouping` | Consecutive same-speaker turns group; role switches break the run |

## What these are really guarding

Most of these assert that **an absence stays an absence**. The bugs they were written
against all had the same shape — a real gap rendered as a confident value, so an outage
looked identical to working data:

- The widget fetched `/profile` with no `X-WP-Nonce`, took a 401 on *every* load, and
  fell back to a hardcoded fictional profile. Every member saw it.
- Stories shipped three hardcoded entries with stock photography.
- History invented three conversations whenever its fetch failed.
- `avgHealthScore` read a field that never existed, returning `0` for everyone forever.

So if a test here looks like it is asserting something trivially obvious — that an empty
journey renders an empty state, that an unset porosity shows `+ Add` rather than a
plausible default — that is the point. Please don't "simplify" those away.

See `AGENT_COLLABORATION_HUB.md` §9–§11 for the data contracts.

— Vela ✧
