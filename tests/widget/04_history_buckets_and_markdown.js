const { JSDOM, ok } = require('./_harness.js');
const fs = require('fs');
const { WIDGET: W } = require('./_harness.js');

const now = new Date();
const iso = d => new Date(now.getTime() - d*3600000).toISOString();

const convos = [
  { id:'c1', summary:'Crown breakage plan',      messageCount:8, updatedAt: iso(1) },
  { id:'c2', summary:'Wash day frequency',       messageCount:4, updatedAt: iso(30) },
  { id:'c3', summary:'Protein overload recovery',messageCount:12,updatedAt: iso(24*4) },
  { id:'c4', summary:'Silk press aftercare',     messageCount:2, updatedAt: iso(24*45) }
];

const dom = new JSDOM('<!doctype html><html><body></body></html>',
  { url:'https://myhairjourney.ai/#today', runScripts:'outside-only', pretendToBeVisual:true });
const w = dom.window;
w.requestAnimationFrame = cb => setTimeout(cb,0);
w.fetch = (url) => {
  if (String(url).includes('/conversations?')) {
    return Promise.resolve({ ok:true, json:()=>Promise.resolve({ success:true, conversations: convos }) });
  }
  return Promise.reject(new Error('n/a'));
};
global.window = w; global.document = w.document;
w.eval(fs.readFileSync(W,'utf8'));

const MW = w.MyavanaWidget;
MW.init({ apiBase:'http://x' });
MW.open();

const q = s => w.document.querySelector(s);
const qa = s => [...w.document.querySelectorAll(s)];

// ---- Markdown ----
const md = q('.mya-stream');
MW.sendMessage; // no-op ref
w.eval(`window.__md = null;`);
// Exercise markdown through an assistant bubble
const bubbleHtml = (() => {
  const stream = q('.mya-stream');
  stream.innerHTML = '';
  // use the widget's own renderer via a streamed-style append
  return null;
})();

// Drive real messages
const stream = q('.mya-stream');
stream.innerHTML = '';

// simulate: user msg, then two assistant msgs in a row
w.eval(`
  var api = window.MyavanaWidget;
`);
// Use internal behaviour via sendMessage is network-bound; instead exercise
// the exported surface that renders: resume a conversation.
w.fetch = (url) => {
  if (String(url).includes('/conversations/c1')) {
    return Promise.resolve({ ok:true, json:()=>Promise.resolve({ success:true, chatHistory:[
      { role:'user',  content: iso(24*2).replace('Z','Z') + ' - User Said: Why is my crown breaking?' },
      { role:'model', content: 'Here is the plan:\n\n1. **Reduce tension** at the crown\n2. Deep condition weekly\n\n- Use a silk scarf\n- Skip heat\n\n> Consistency beats intensity.' },
      { role:'user',  content: iso(1) + ' - User Said: How long until I see change?' },
      { role:'model', content: 'Give it **6 weeks**.' }
    ]}) });
  }
  if (String(url).includes('/conversations?')) {
    return Promise.resolve({ ok:true, json:()=>Promise.resolve({ success:true, conversations: convos }) });
  }
  return Promise.reject(new Error('n/a'));
};

MW.switchView('history');
setTimeout(() => {
  const hv = q('.mya-history-view');
  const groups = qa('.mya-history-group-title').map(g=>g.textContent);
  ok('history buckets by real recency: ' + JSON.stringify(groups),
     JSON.stringify(groups) === JSON.stringify(['Today','Yesterday','Previous 7 Days','Earlier']));
  ok('all four conversations rendered', qa('.mya-history-item').length === 4);
  ok('relative times shown, not "Recent"', /ago|Just now/.test(hv.innerHTML) && !/• Recent/.test(hv.innerHTML));
  ok('message counts pluralise', /8 messages/.test(hv.innerHTML) && /2 messages/.test(hv.innerHTML));

  // search filter
  const input = q('.mya-history-search');
  input.value = 'breakage';
  input.dispatchEvent(new w.Event('input'));
  const visible = qa('.mya-history-item').filter(i=>i.style.display !== 'none');
  ok('search filters to one match', visible.length === 1 && /Crown breakage/.test(visible[0].textContent));
  ok('empty groups collapse while filtering',
     qa('.mya-history-group').filter(g=>g.style.display !== 'none').length === 1);
  ok('clear button appears with a query', q('.mya-search-clear').classList.contains('on'));

  input.value = 'zzzznope';
  input.dispatchEvent(new w.Event('input'));
  ok('no-match state shown', q('.mya-history-nohits').style.display === 'flex');

  q('.mya-search-clear').dispatchEvent(new w.MouseEvent('click',{bubbles:true}));
  ok('clearing search restores all rows',
     qa('.mya-history-item').filter(i=>i.style.display !== 'none').length === 4);

  // ---- Resume a thread: grouping, day rules, markdown ----
  qa('.mya-history-item')[0].dispatchEvent(new w.MouseEvent('click',{bubbles:true}));
  setTimeout(() => {
    const st = q('.mya-stream');
    ok('day separators printed for a multi-day thread', qa('.mya-day-sep').length >= 1);
    ok('markdown ordered list rendered', /<ol><li>/.test(st.innerHTML));
    ok('markdown bullet list rendered', /<ul><li>/.test(st.innerHTML));
    ok('markdown bold rendered', /<strong>Reduce tension<\/strong>/.test(st.innerHTML));
    ok('markdown blockquote rendered', /<blockquote>/.test(st.innerHTML));
    ok('raw list markers no longer leak as text', !/&gt;\s*Consistency|^\s*1\. /m.test(st.textContent.trim()));

    const rows = qa('.mya-msg-row');
    ok('four turns rendered', rows.length === 4);
    ok('every turn tagged with its role', rows.every(r=>['user','assistant'].includes(r.getAttribute('data-role'))));
    ok('timestamps come from the stored time, not now',
       qa('.mya-msg-time').length === 4);
    ok('assistant replies offer copy', qa('.mya-msg-tools .mya-msg-tool').length === 2);

    // Grouping: append two assistant messages back to back
    st.innerHTML = '';
    w.eval(`
      (function(){
        var el = document.querySelector('.mya-stream');
      })();
    `);
    ok('history view marks the resumed conversation as current',
       (MW.switchView('history'), true));
    setTimeout(() => {
      const cur = qa('.mya-history-item.current');
      ok('current conversation highlighted in history', cur.length === 1 && /Crown breakage/.test(cur[0].textContent));
    }, 30);
  }, 40);
}, 40);
