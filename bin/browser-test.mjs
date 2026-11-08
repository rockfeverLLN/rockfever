// Opens the pages that use JavaScript in headless Chrome and fails if a script throws, logs an error, doesn't
// load, or if a widget doesn't work. bin/smoke-test.sh only checks the HTML answers, this checks that the site's
// own widgets (www/js/script.js, guests.js) and TinyMCE, clipboard.js and Masonry still work after an update.
// Used by bin/test.sh on every pull request, with no npm packages: Node talks to Chrome over its
// DevTools protocol.
//
// Expects a database filled by `composer migrate && composer seed` (the admin login comes from RolesSeeder).
// Usage: node bin/browser-test.mjs [base-url]   (default http://127.0.0.1:8000; Node 20 needs --experimental-websocket)
//        CHROME=/path/to/chrome to use another browser than Google Chrome
import {spawn} from 'node:child_process';
import {mkdtempSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';

const BASE = process.argv[2] || 'http://127.0.0.1:8000';

// path: what must be true once the page's scripts ran. The checks run again until they pass, so the ones that click
// only do it while the widget isn't open yet
const PAGES = [
    // Side menu: the menu button opens it
    ['/', "(document.querySelector('.sidenav.open') || document.querySelector('.sidenav-trigger').click(),"
        + " !!document.querySelector('.sidenav.open') && !!document.querySelector('.sidenav-overlay.open'))"],
    // A text field's label moves up once it's filled
    ['/contact', "(() => { const input = document.querySelector('.input-field input');"
        + " input.value = 'Test'; input.dispatchEvent(new Event('change', {bubbles: true}));"
        + " return input.parentElement.querySelector('label').classList.contains('active'); })()"],
    // The role is a native select, shown
    ['/login', "!!document.querySelector('.sidenav-overlay') && document.querySelector('select[name=role]').offsetHeight > 0"],
    // Collapsible (another soirée opens and the first closes), tooltip, the "Paramètres" dropdown. On the past events:
    // the test database's are all past, and /admin/events only lists those to come (their buttons hold the tooltips)
    ['/admin/events/past', "(() => {"
        + " const items = document.querySelectorAll('.collapsible > li');"
        + " if (!items[1].classList.contains('active')) {"
        + "  items[0].querySelector('.collapsible-header').click(); items[1].querySelector('.collapsible-header').click(); }"
        + " document.querySelector('.tooltipped').dispatchEvent(new MouseEvent('mouseenter'));"
        + " const menu = document.getElementById('params-dropdown');"
        + " if (!menu.classList.contains('open')) document.querySelector('.dropdown-trigger').click();"
        + " return items[1].classList.contains('active') && !items[0].classList.contains('active')"
        + "  && document.querySelector('.material-tooltip.open').textContent.length > 0"
        + "  && menu.classList.contains('open') && menu.parentElement.tagName === 'LI'; })()"],
    // The browser's date and time pickers, with the event's values
    ['/admin/events/1', "/^\\d{4}-\\d{2}-\\d{2}$/.test(document.querySelector('input[type=date][name=date]').value)"
        + " && /^\\d{2}:\\d{2}$/.test(document.querySelector('input[type=time][name=waltz_time]').value)"],
    // A new event says when its sign-ups open, again when its date changes (7 days before by default)
    ['/admin/events/new', "(() => { const date = document.getElementById('date');"
        + " date.value = '2030-01-16'; date.dispatchEvent(new Event('change'));"
        + " return document.getElementById('date-registration').textContent.includes('dès le mercredi 9 janvier'); })()"],
    // The list and the numbers load, and the search filters the list
    ['/admin/events/list/1', "(() => {"
        + " const items = document.querySelectorAll('#guests-list .collection-item');"
        + " const search = document.getElementById('guest-search');"
        + " search.value = 'zzzz'; search.dispatchEvent(new Event('input'));"
        + " const hidden = [...items].every(item => item.style.display === 'none');"
        + " search.value = ''; search.dispatchEvent(new Event('input'));"
        + " return items.length > 0 && hidden && !!items[0].querySelector('[data-remove]')"
        + " && document.getElementById('attendees').textContent !== '-'; })()"],
    // Card reveal
    ['/admin/events/statistics/1', "(document.querySelector('.card-reveal.open') || document.querySelector('.activator').click(),"
        + " !!document.querySelector('.card-reveal.open'))"],
    // The tables hidden for screen readers add nothing to the page's height: a table on its own ignores .sr-only's
    ['/admin/statistics', "!!document.getElementById('eventsChart')"
        + " && [...document.querySelectorAll('.sr-only')].every(element => element.offsetHeight <= 1)"
        + " && document.documentElement.scrollHeight <= Math.ceil(document.querySelector('main').getBoundingClientRect().bottom + scrollY)"],
    ['/admin/about/1', "tinymce.get().length > 0 && tinymce.get()[0].initialized"
        + " && !!tinymce.get()[0].ui.registry.getAll().buttons.imageuploader"],
    // The settings page: TinyMCE on the R.O.I. and the conditions
    ['/admin/values', "tinymce.get().length === 2 && tinymce.get().every(e => e.initialized)"],
    ['/admin/about/gallery', "new ClipboardJS('.to-clipboard') && !!Masonry.data(document.querySelector('.grid'))"],
    // In Instagram's browser, a calendar link that can't leave for the real browser ends on the help popup. The third
    // value is the browser the page is opened as
    ['/calendrier', "(window.tapped || (window.tapped = true, document.querySelector('.calendar-subscribe a').click()),"
        + " document.getElementById('inAppBrowser').open)",
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 350.0.0.0'],
];
// Chrome has no handler for Safari's scheme
const IGNORED = /Failed to launch 'x-safari-/;

const chromePath = process.env.CHROME || (process.platform === 'darwin'
    ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : 'google-chrome');
const profile = mkdtempSync(join(tmpdir(), 'chrome-'));
const chrome = spawn(chromePath, [
    '--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--no-first-run',
    '--no-default-browser-check', '--no-sandbox', 'about:blank',
], {stdio: ['ignore', 'ignore', 'pipe']});
const browserUrl = await new Promise((resolve, reject) => {
    let output = '';
    chrome.stderr.on('data', chunk => {
        output += chunk;
        const match = output.match(/DevTools listening on (ws:\/\/\S+)/);
        if (match) resolve(match[1]);
    });
    chrome.on('exit', () => reject(new Error(`Chrome didn't start:\n${output}`)));
});

// Connects to the page Chrome opened at start
const targets = await (await fetch(browserUrl.replace(/^ws:\/\/([^/]+).*/, 'http://$1/json/list'))).json();
const socket = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
await new Promise(resolve => socket.addEventListener('open', resolve));
let nextId = 0;
const pending = new Map();
let problems = [];
socket.addEventListener('message', ({data}) => {
    const message = JSON.parse(data);
    if (message.id !== undefined) {
        const {resolve, reject} = pending.get(message.id);
        pending.delete(message.id);
        message.error ? reject(new Error(message.error.message)) : resolve(message.result);
        return;
    }
    const p = message.params;
    if (message.method === 'Runtime.exceptionThrown') {
        problems.push(p.exceptionDetails.exception?.description || p.exceptionDetails.text);
    } else if (message.method === 'Runtime.consoleAPICalled' && p.type === 'error') {
        problems.push('console.error: ' + p.args.map(a => a.value ?? a.description).join(' '));
    } else if (message.method === 'Log.entryAdded' && p.entry.level === 'error' && !IGNORED.test(`${p.entry.text} ${p.entry.url || ''}`)) {
        problems.push(`${p.entry.text} ${p.entry.url || ''}`);
    }
});
const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = nextId++;
    pending.set(id, {resolve, reject});
    socket.send(JSON.stringify({id, method, params}));
});
const evaluate = async expression => {
    const {result, exceptionDetails} = await send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true});
    if (exceptionDetails) throw new Error(exceptionDetails.exception?.description || exceptionDetails.text);
    return result.value;
};
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

await send('Runtime.enable');
await send('Log.enable');
await send('Page.enable');

let failed = false;
try {
    // Same login as bin/smoke-test.sh, from the test database's RolesSeeder
    await send('Page.navigate', {url: `${BASE}/login`});
    await sleep(500);
    await evaluate(`fetch('/login', {method: 'POST', body: new URLSearchParams({role: 'administrator', password: 'admin'})})
        .then(r => r.ok)`);

    const defaultAgent = await evaluate('navigator.userAgent');
    for (const [path, check, userAgent] of PAGES) {
        problems = [];
        await send('Emulation.setUserAgentOverride', {userAgent: userAgent || defaultAgent});
        await send('Page.navigate', {url: BASE + path});
        // The site's scripts load after the first paint (views/layout.twig), and TinyMCE loads its parts after
        // that: wait until the check passes, for up to 15 seconds
        let ok = false, error = '';
        for (let waited = 0; waited < 15000 && !ok; waited += 250) {
            await sleep(250);
            try {
                ok = await evaluate(`document.readyState === 'complete' && !!(${check})`);
            } catch (e) {
                error = e.message.split('\n')[0];
            }
        }
        await sleep(500);
        if (ok && problems.length === 0) {
            console.log(`  ok   ${path}`);
        } else {
            failed = true;
            console.log(`  FAIL ${path}${ok ? '' : `: ${check} never became true${error ? ` (${error})` : ''}`}`);
            if (path === '/admin/statistics') {
                const bounds = await evaluate(`(() => {
                    const main = document.querySelector('main');
                    return JSON.stringify({
                        chart: !!document.getElementById('eventsChart'),
                        hiddenTableHeights: [...document.querySelectorAll('.sr-only')].map(element => element.offsetHeight),
                        documentHeight: document.documentElement.scrollHeight,
                        mainBottom: main.getBoundingClientRect().bottom + scrollY,
                        overflow: [...document.querySelectorAll('body *')].filter(element => {
                            const rect = element.getBoundingClientRect();
                            return rect.bottom + scrollY > main.getBoundingClientRect().bottom + scrollY + 1;
                        }).slice(-8).map(element => ({tag: element.tagName, className: element.className,
                            bottom: Math.round(element.getBoundingClientRect().bottom + scrollY)}))
                    });
                })()`);
                console.log(`       page bounds: ${bounds}`);
            }
            problems.forEach(problem => console.log(`       ${problem}`));
        }
    }
} finally {
    socket.close();
    if (chrome.exitCode === null) {
        const exited = new Promise(resolve => chrome.on('exit', resolve));
        chrome.kill();
        await exited;
    }
    // Chrome's helper processes can still be writing to the profile for a moment: the test result doesn't depend on it
    try {
        rmSync(profile, {recursive: true, force: true, maxRetries: 10, retryDelay: 200});
    } catch (e) {
        console.log(`  (couldn't remove ${profile}: ${e.code})`);
    }
}
process.exit(failed ? 1 : 0);
