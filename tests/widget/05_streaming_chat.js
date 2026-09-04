const { JSDOM, ok } = require('./_harness.js');
const fs = require('fs');
const { WIDGET: W } = require('./_harness.js');

const dom = new JSDOM('<!doctype html><html><body></body></html>',
  { url:'https://myhairjourney.ai/#today', runScripts:'outside-only', pretendToBeVisual:true });
const w = dom.window;
w.requestAnimationFrame = cb => setTimeout(cb,0);
global.window = w; global.document = w.document;

// A controllable NDJSON stream
let push, finish;
const frames = [];
w.fetch = () => Promise.resolve({
  ok: true,
  body: { getReader: () => ({
    read: () => new Promise(res => {
      push = chunk => res({ done:false, value: new TextEncoder().encode(chunk) });
      finish = () => res({ done:true });
      if (frames.length) { const f = frames.shift(); setTimeout(()=>res({done:false, value:new TextEncoder().encode(f)}),0); }
    })
  })}
});
w.TextDecoder = global.TextDecoder;

w.eval(fs.readFileSync(W,'utf8'));
const MW = w.MyavanaWidget;
MW.init({ apiBase:'http://x' });
MW.open();
const q = s => w.document.querySelector(s);
const qa = s => [...w.document.querySelectorAll(s)];

MW.sendMessage('Why is my crown breaking?');

setTimeout(() => {
  ok('user turn rendered', qa('.mya-msg.user').length === 1);
  ok('send button disabled while streaming', q('.mya-send-btn').disabled === true);
  ok('composer placeholder signals Mya is replying', /replying/i.test(q('.mya-textarea').getAttribute('placeholder')));
  ok('thinking indicator uses animated dots, no telemetry',
     !!q('.mya-status-dots') && !/getHairGoals|function|stack/i.test(q('.mya-status-indicator').textContent));

  push('{"t":"delta","text":"Try **reducing tension**"}\n');
  setTimeout(() => {
    ok('status pill cleared once text arrives', !q('.mya-status-indicator'));
    ok('streaming caret visible mid-stream', !!q('.mya-caret'));
    ok('assistant bubble rendering markdown live', /<strong>reducing tension<\/strong>/.test(q('.mya-msg.assistant').innerHTML));

    finish();
    setTimeout(() => {
      ok('caret removed when the stream ends', !q('.mya-caret'));
      ok('send button re-enabled', q('.mya-send-btn').disabled === false);
      ok('placeholder restored', /hair journey/i.test(q('.mya-textarea').getAttribute('placeholder')));

      // Grouping: a second user turn right after the first shows its header;
      // consecutive same-role turns should group.
      const stream = q('.mya-stream');
      const before = qa('.mya-msg-row').length;
      MW.sendMessage('And how long will it take?');
      setTimeout(() => {
        const rows = qa('.mya-msg-row');
        ok('new turn appended', rows.length === before + 1);
        const userRows = rows.filter(r => r.getAttribute('data-role') === 'user');
        ok('a user turn following an assistant turn is NOT grouped',
           !userRows[userRows.length-1].classList.contains('grouped'));
        ok('every non-grouped row carries a header + timestamp',
           rows.filter(r=>!r.classList.contains('grouped'))
               .every(r => r.querySelector('.mya-msg-time')));

        // Copy affordance
        const tool = q('.mya-msg-tools .mya-msg-tool');
        let copied = null;
        w.navigator.clipboard = { writeText: t => { copied = t; return Promise.resolve(); } };
        tool.dispatchEvent(new w.MouseEvent('click',{bubbles:true}));
        setTimeout(() => {
          ok('copy writes the reply text to the clipboard', /reducing tension/.test(copied || ''));
          ok('copy button confirms', /Copied/.test(tool.textContent));
        }, 20);
      }, 30);
    }, 30);
  }, 30);
}, 40);
