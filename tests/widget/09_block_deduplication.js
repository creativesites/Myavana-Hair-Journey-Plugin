/**
 * Suite 09: Block and Card Deduplication in Mya Stream
 *
 * Verifies that duplicate tool and model blocks (such as double "Weather Defense Check-in"
 * journal cards or repeated quick-reply pills) are deduplicated within a turn, and that
 * database timestamps like "2026-09-20 00:00:00" are cleanly formatted as "Sep 20, 2026".
 */
const { boot, ok } = require('./_harness.js');

const w = boot('https://myhairjourney.ai/#today', {
    fetch: () => Promise.reject(new Error('offline'))
});
const MW = w.MyavanaWidget;
MW.init({ apiBase: 'https://test-api' });
MW.open();

const stream = w.document.querySelector('.mya-stream');

// Simulate a user sending a message
w.document.querySelector('.mya-textarea').value = 'Log weather defense';
MW.sendMessage('Log weather defense');

setTimeout(() => {
    // 1. First block arrives from tool (e.g. createJournalEntryWithBlock)
    MW.renderBlock({
        id: 'journal_entry_001',
        type: 'journal_entry',
        data: {
            id: 42,
            title: 'Weather Defense Check-in',
            notes: 'Applied protective hydration barrier and sealing serum to shield high-porosity 1C strands from environmental moisture loss and ambient humidity fluctuations.',
            date: '2026-09-20 00:00:00',
        },
    });

    const cardsAfterFirst = stream.querySelectorAll('.mya-card');
    ok('first journal card rendered', cardsAfterFirst.length === 1);

    const firstCard = cardsAfterFirst[0];
    const dateText = firstCard.querySelector('.mya-pill').textContent;
    ok('date formatted cleanly (not raw MySQL timestamp)', !dateText.includes('00:00:00') && (dateText.includes('Sep') || dateText.includes('2026')));

    // 2. Second block arrives from model output (duplicate echo)
    MW.renderBlock({
        type: 'journal_entry',
        data: {
            title: 'Weather Defense Check-in',
            notes: 'Applied protective hydration barrier and sealing serum to shield high-porosity 1C strands from environmental moisture loss and ambient humidity fluctuations.',
        },
    });

    const cardsAfterSecond = stream.querySelectorAll('.mya-card');
    ok('duplicate journal card suppressed in stream', cardsAfterSecond.length === 1);

    // 3. Different journal entry arrives
    MW.renderBlock({
        id: 'journal_entry_002',
        type: 'journal_entry',
        data: {
            id: 43,
            title: 'Sunday Deep Condition',
            notes: 'Hydration mask applied for 30 minutes with gentle steam.',
            date: '2026-09-20 12:00:00',
        },
    });

    const cardsAfterThird = stream.querySelectorAll('.mya-card');
    ok('distinct journal card is not suppressed', cardsAfterThird.length === 2);

    // 4. Quick replies deduplication
    MW.renderBlock({
        type: 'quick_replies',
        data: { replies: ['Check hair weather', 'View journal'] },
    });
    MW.renderBlock({
        type: 'quick_replies',
        data: { replies: ['Check hair weather', 'View journal'] },
    });

    const qrRows = stream.querySelectorAll('.mya-qr-row');
    ok('duplicate quick-replies row suppressed', qrRows.length === 1);
}, 50);
