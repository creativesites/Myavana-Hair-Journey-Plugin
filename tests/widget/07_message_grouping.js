const { JSDOM, ok } = require('./_harness.js');
const fs = require('fs');
const { WIDGET: W } = require('./_harness.js');
const now = new Date();
const t = h => new Date(now.getTime()-h*3600000).toISOString();

const dom = new JSDOM('<!doctype html><html><body></body></html>',
  { url:'https://myhairjourney.ai/#today', runScripts:'outside-only', pretendToBeVisual:true });
const w = dom.window;
w.requestAnimationFrame = cb => setTimeout(cb,0);
global.window = w; global.document = w.document;

w.fetch = (url) => {
  const u = String(url);
  if (u.includes('/conversations?')) return Promise.resolve({ ok:true, json:()=>Promise.resolve({
    conversations:[{ id:'x', summary:'Grouping thread', messageCount:4, updatedAt: t(2) }] }) });
  if (u.includes('/conversations/x')) return Promise.resolve({ ok:true, json:()=>Promise.resolve({ chatHistory:[
    { role:'user',  content: t(2) + ' - User Said: First question' },
    { role:'user',  content: t(2) + ' - User Said: Also this' },
    { role:'model', content: 'Answer part one.' },
    { role:'model', content: 'And a follow-up thought.' }
  ]}) });
  return Promise.reject(new Error('n/a'));
};
w.eval(fs.readFileSync(W,'utf8'));
const MW = w.MyavanaWidget;
MW.init({ apiBase:'http://x' });
MW.open();
MW.switchView('history');

setTimeout(() => {
  w.document.querySelector('.mya-history-item').dispatchEvent(new w.MouseEvent('click',{bubbles:true}));
  setTimeout(() => {
    const rows = [...w.document.querySelectorAll('.mya-msg-row')];
    const shape = rows.map(r => r.getAttribute('data-role') + (r.classList.contains('grouped') ? '+grouped' : ''));
    console.log('    thread shape: ' + JSON.stringify(shape));
    ok('4 turns rendered', rows.length === 4);
    ok('1st user turn shows its header', !rows[0].classList.contains('grouped') && !!rows[0].querySelector('.mya-msg-header'));
    ok('2nd consecutive user turn is grouped (header suppressed)',
       rows[1].classList.contains('grouped') && !rows[1].querySelector('.mya-msg-header'));
    ok('role switch to assistant breaks the run', !rows[2].classList.contains('grouped'));
    ok('2nd consecutive assistant turn is grouped',
       rows[3].classList.contains('grouped') && !rows[3].querySelector('.mya-msg-header'));
    ok('exactly 2 header rows for 4 turns', w.document.querySelectorAll('.mya-msg-header').length === 2);
    ok('both assistant replies still offer copy',
       w.document.querySelectorAll('.mya-msg-tools .mya-msg-tool').length === 2);
  }, 40);
}, 40);
