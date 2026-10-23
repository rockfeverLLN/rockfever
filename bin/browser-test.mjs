// Opens the pages that use JavaScript libraries in headless Chrome and fails if a script throws, logs an
// error, doesn't load, or if a library didn't start its widgets. bin/smoke-test.sh only checks the HTML answers,
// this checks that jQuery, Materialize, TinyMCE, clipboard.js and Masonry still work after an update.
// Used by .github/workflows/update-dependencies.yml, with no npm packages: Node talks to Chrome over its
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

// path: what must be true once the page's scripts ran
const PAGES = [
    ['/', "M.Sidenav.getInstance(document.querySelector('.sidenav'))"],
    ['/contact', "typeof jQuery === 'function'"],
    ['/admin/events/1', "M.Datepicker.getInstance(document.querySelector('.datepicker'))"
        + " && M.Timepicker.getInstance(document.querySelector('.timepicker'))"],
    ['/admin/events/list/1', "typeof jQuery === 'function' && document.querySelector('#guests-list')"],
    ['/admin/about/1', "tinymce.get().length > 0 && tinymce.get()[0].initialized"
        + " && !!tinymce.get()[0].ui.registry.getAll().buttons.imageuploader"],
    ['/admin/values', "tinymce.get().length > 0 && tinymce.get().every(e => e.initialized)"],
    ['/admin/about/gallery', "new ClipboardJS('.to-clipboard') && !!jQuery('.grid').data('masonry')"],
];
// Analytics can fail in a test run without the site being broken
const IGNORED = /googletagmanager|google-analytics/;

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
    } else if (message.method === 'Log.entryAdded' && p.entry.level === 'error' && !IGNORED.test(p.entry.url || '')) {
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

    for (const [path, check] of PAGES) {
        problems = [];
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
