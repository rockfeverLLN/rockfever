// Takes the README's screenshots again from the live site, so they show it as it is today:
// .github/assets/desktop.jpg (the home page on a computer) and .github/assets/phones.webp (the home page, the
// calendar and the photo gallery on phones). The banner isn't a screenshot and stays as it is.
// Used by .github/workflows/readme-screenshots.yml, with no npm packages: like bin/browser-test.mjs, Node talks to
// Chrome over its DevTools protocol.
//
// Usage: node bin/readme-screenshots.mjs [base-url]   (default https://rockfever.be; Node 20 needs --experimental-websocket)
//        CHROME=/path/to/chrome to use another browser than Google Chrome
import {spawn} from 'node:child_process';
import {existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';

const BASE = (process.argv[2] || 'https://rockfever.be').replace(/\/$/, '');
const ASSETS = new URL('../.github/assets/', import.meta.url).pathname;
const PHONE_PAGES = ['/', '/calendrier', '/photos'];
// What a phone shows: an iPhone's width, cut to twice as high as wide, and its width in the picture below. The
// screenshots are taken at that size rather than scaled into it, so the same page always gives the same pixels
const PHONE = {width: 390, height: 780, shown: 285};

const chromePath = process.env.CHROME || (process.platform === 'darwin'
    ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : 'google-chrome');
const profile = mkdtempSync(join(tmpdir(), 'chrome-'));
const chrome = spawn(chromePath, [
    '--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--no-first-run',
    '--no-default-browser-check', '--no-sandbox', '--hide-scrollbars', 'about:blank',
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

const targets = await (await fetch(browserUrl.replace(/^ws:\/\/([^/]+).*/, 'http://$1/json/list'))).json();
const socket = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
await new Promise(resolve => socket.addEventListener('open', resolve));
let nextId = 0;
const pending = new Map();
socket.addEventListener('message', ({data}) => {
    const message = JSON.parse(data);
    if (message.id === undefined) return;
    const {resolve, reject} = pending.get(message.id);
    pending.delete(message.id);
    message.error ? reject(new Error(message.error.message)) : resolve(message.result);
});
const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = nextId++;
    pending.set(id, {resolve, reject});
    socket.send(JSON.stringify({id, method, params}));
});
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

// Opens a page at a size and returns its screenshot, once its fonts and images are in and its scripts ran
async function capture(url, width, height, format, quality, deviceScaleFactor = 2) {
    await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor, mobile: width < 600});
    await send('Page.navigate', {url});
    for (let waited = 0; waited < 20000; waited += 250) {
        await sleep(250);
        const {result} = await send('Runtime.evaluate', {
            expression: "document.readyState === 'complete' && [...document.images].every(i => i.complete)",
            returnByValue: true,
        });
        if (result.value) break;
    }
    await send('Runtime.evaluate', {expression: 'document.fonts.ready.then(() => true)', awaitPromise: true});
    // The site's scripts load after the first paint (views/layout.twig): let them set up the page
    await sleep(1500);
    const {data} = await send('Page.captureScreenshot', {format, quality});
    return data;
}

// Saves a screenshot unless the file already shows the same thing: the encoders don't always give the same bytes
// for the same pixels, and the workflow commits whatever changed
async function save(name, base64, mime) {
    const file = join(ASSETS, name);
    if (existsSync(file)) {
        await send('Page.navigate', {url: 'about:blank'});
        await sleep(200);
        const old = readFileSync(file).toString('base64');
        const {result} = await send('Runtime.evaluate', {awaitPromise: true, returnByValue: true, expression: `(async () => {
            const pixels = async src => {
                const image = new Image();
                image.src = src;
                await image.decode();
                const canvas = new OffscreenCanvas(image.width, image.height);
                const context = canvas.getContext('2d');
                context.drawImage(image, 0, 0);
                return context.getImageData(0, 0, image.width, image.height).data;
            };
            const [a, b] = await Promise.all([pixels('data:${mime};base64,${old}'), pixels('data:${mime};base64,${base64}')]);
            if (a.length !== b.length) return false;
            // Text antialiasing and lossy encoding change a few hundred scattered pixels from one run to the next: a
            // real change (a date, a button) changes thousands
            let changed = 0;
            // Colors weighted by opacity: the colors of the nearly transparent glow around the phones vary wildly
            for (let i = 0; i < a.length; i += 4) {
                const color = c => Math.abs(a[i + c] * a[i + 3] - b[i + c] * b[i + 3]) / 255;
                if (Math.max(color(0), color(1), color(2), Math.abs(a[i + 3] - b[i + 3])) > 8) changed++;
            }
            return changed < a.length / 4 / 1000;
        })()`});
        if (result.value) {
            console.log(`  ${name} (unchanged)`);
            return;
        }
    }
    writeFileSync(file, Buffer.from(base64, 'base64'));
    console.log(`  ${name}`);
}

try {
    await send('Page.enable');
    // OVH turns away clients that don't look like a browser, and "HeadlessChrome" might count as one
    const {userAgent} = await send('Browser.getVersion');
    await send('Network.setUserAgentOverride', {userAgent: userAgent.replace('HeadlessChrome', 'Chrome')});

    const desktop = await capture(`${BASE}/`, 1280, 800, 'jpeg', 85);
    await save('desktop.jpg', desktop, 'image/jpeg');

    const phones = [];
    for (const path of PHONE_PAGES) {
        phones.push(await capture(BASE + path, PHONE.width, PHONE.height, 'png', undefined, 2 * PHONE.shown / PHONE.width));
    }

    // The three phones side by side, in dark frames with a glow of the site's blue, on a transparent background
    const html = `<!doctype html><html><head><style>
        html, body { margin: 0; background: transparent; }
        body { width: 1000px; height: 618px; display: flex; justify-content: center; align-items: center; gap: 22px; }
        .phone { padding: 6px; border-radius: 44px; background: #101929;
                 box-shadow: 0 0 0 2px #1c2a42, 0 6px 22px 2px rgba(7, 45, 85, .8); }
        .phone img { display: block; width: ${PHONE.shown}px; height: ${PHONE.shown * PHONE.height / PHONE.width}px; border-radius: 38px; }
    </style></head><body>${phones.map(png => `<div class="phone"><img src="data:image/png;base64,${png}"></div>`).join('')}
    </body></html>`;
    const page = join(profile, 'phones.html');
    writeFileSync(page, html);
    await send('Emulation.setDefaultBackgroundColorOverride', {color: {r: 0, g: 0, b: 0, a: 0}});
    const composed = await capture(`file://${page}`, 1000, 618, 'webp', 85);
    await save('phones.webp', composed, 'image/webp');
} finally {
    socket.close();
    if (chrome.exitCode === null) {
        const exited = new Promise(resolve => chrome.on('exit', resolve));
        chrome.kill();
        await exited;
    }
    try {
        rmSync(profile, {recursive: true, force: true, maxRetries: 10, retryDelay: 200});
    } catch (e) {
        console.log(`  (couldn't remove ${profile}: ${e.code})`);
    }
}
