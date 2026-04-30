#!/usr/bin/env node
/* QA capture worker: drives Chrome via puppeteer, scrolls the sandbox, and
 * writes per-page anchored screenshots + a full-page screenshot for the
 * given iteration label. Reads anchor manifest from work/qa/anchors.json.
 *
 * Usage: node capture.js <label> [--url=URL]
 * Output: <repo>/work/screenshots/<label>/page-NN.png + _full.png + scroll-NN-yY.png
 */
const fs = require('fs');
const path = require('path');

const PUPPETEER_PATH = '/home/saad/phpstorm-projects/clm/node_modules/puppeteer';
let puppeteer;
try {
  puppeteer = require(PUPPETEER_PATH);
} catch (e) {
  console.error(`puppeteer not found at ${PUPPETEER_PATH}`);
  console.error('install: cd /home/saad/phpstorm-projects/clm && npm i puppeteer');
  process.exit(2);
}

const REPO = path.resolve(__dirname, '..', '..');
const QA_DIR = path.join(REPO, 'work', 'qa');
const ANCHORS_FILE = path.join(QA_DIR, 'anchors.json');

const args = process.argv.slice(2);
const label = (args.find(a => !a.startsWith('--')) || '').trim();
if (!label) {
  console.error('usage: node capture.js <label> [--url=URL]');
  process.exit(2);
}
const urlArg = args.find(a => a.startsWith('--url='));
const URL = urlArg ? urlArg.slice('--url='.length) : `http://127.0.0.1:8765/?cb=${label}`;

const manifest = JSON.parse(fs.readFileSync(ANCHORS_FILE, 'utf8'));
const VIEW = manifest.viewport || { width: 900, height: 1200 };
const OUT_DIR = path.join(REPO, 'work', 'screenshots', label);

// Idempotent: wipe and recreate the output dir
if (fs.existsSync(OUT_DIR)) {
  for (const f of fs.readdirSync(OUT_DIR)) {
    fs.unlinkSync(path.join(OUT_DIR, f));
  }
} else {
  fs.mkdirSync(OUT_DIR, { recursive: true });
}

function pad2(n) { return String(n).padStart(2, '0'); }

(async () => {
  console.log(`[capture] label=${label}`);
  console.log(`[capture] url=${URL}`);
  console.log(`[capture] out=${OUT_DIR}`);
  console.log(`[capture] viewport=${VIEW.width}x${VIEW.height}`);

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--hide-scrollbars'],
    defaultViewport: { width: VIEW.width, height: VIEW.height, deviceScaleFactor: 1 },
  });
  const page = await browser.newPage();

  let renderedOk = false;
  let renderError = null;
  page.on('pageerror', err => { renderError = err.message; });

  await page.goto(URL, { waitUntil: 'networkidle0', timeout: 60000 });

  // Wait for the sandbox's "rendered ok" banner. If absent within 60s, capture
  // whatever's on screen anyway and surface the issue in the report.
  try {
    await page.waitForFunction(
      () => /rendered ok/.test(document.body?.innerText || ''),
      { timeout: 60000 }
    );
    renderedOk = true;
  } catch (e) {
    console.error('[capture] WARNING: "rendered ok" banner not seen within 60s');
  }
  // small settle for fonts
  await new Promise(r => setTimeout(r, 1500));

  const fullHeight = await page.evaluate(() => document.documentElement.scrollHeight);
  console.log(`[capture] document scroll-height = ${fullHeight}px`);

  // 1) Full-page screenshot (puppeteer stitches internally for arbitrarily tall docs)
  const fullPath = path.join(OUT_DIR, '_full.png');
  await page.screenshot({ path: fullPath, fullPage: true });
  console.log(`[capture] full -> ${fullPath}`);

  // 2) Sequential scroll bands every 1100 px (overlap 100 px)
  const STEP = 1100;
  let bandN = 1;
  for (let y = 0; y < fullHeight; y += STEP) {
    await page.evaluate(yy => window.scrollTo(0, yy), y);
    await new Promise(r => setTimeout(r, 200));
    const out = path.join(OUT_DIR, `scroll-${pad2(bandN)}-y${y}.png`);
    await page.screenshot({ path: out });
    bandN++;
  }
  console.log(`[capture] scroll bands written: ${bandN - 1}`);

  // 3) Per-page anchored screenshots from manifest
  const log = [];
  for (const a of manifest.anchors || []) {
    let scrollY = null;
    let strategy = '(none)';
    let detail = '';

    if (typeof a.y === 'number') {
      scrollY = a.y;
      strategy = 'y';
      detail = `y=${a.y}`;
    }

    if (scrollY === null && a.text) {
      // Prefer H1/H2/H3 hits over generic text-node hits (avoids matching the
      // first body paragraph that happens to contain the heading text).
      // Honors optional a.text_in: 'h1' | 'h2' | 'h3' | 'any' (default: heading-first).
      const yFound = await page.evaluate((needle, restrict) => {
        const escape = (s) => s.replace(/\s+/g, ' ').trim();
        const norm = escape(needle);
        const headings = Array.from(document.querySelectorAll('h1,h2,h3,h4,h5,h6'));
        if (restrict !== 'any') {
          for (const h of headings) {
            if (escape(h.textContent || '').includes(norm)) {
              return Math.round(h.getBoundingClientRect().top + window.scrollY);
            }
          }
          if (restrict && /^h[1-6]$/.test(restrict)) {
            // user asked for a specific heading tag and we missed — fall through
            return null;
          }
        }
        // Fallback: any text node
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
        let n;
        while ((n = walker.nextNode())) {
          if ((n.nodeValue || '').includes(needle)) {
            const r = n.parentElement?.getBoundingClientRect();
            if (r) return Math.round(r.top + window.scrollY);
          }
        }
        return null;
      }, a.text, a.text_in || 'heading-first');
      if (yFound !== null) {
        scrollY = Math.max(0, yFound - 60);
        strategy = 'text';
        detail = `text="${a.text}" in=${a.text_in || 'heading-first'} y=${yFound}`;
      }
    }

    if (scrollY === null && a.tag && a.tag_index) {
      const yFound = await page.evaluate((tag, idx) => {
        const els = document.querySelectorAll(tag);
        const e = els[idx - 1];
        if (!e) return null;
        return Math.round(e.getBoundingClientRect().top + window.scrollY);
      }, a.tag, a.tag_index);
      if (yFound !== null) {
        scrollY = Math.max(0, yFound - 60);
        strategy = 'tag';
        detail = `tag=${a.tag}#${a.tag_index} y=${yFound}`;
      }
    }

    if (scrollY === null && typeof a.fallback_y === 'number') {
      scrollY = a.fallback_y;
      strategy = 'fallback_y';
      detail = `fallback_y=${a.fallback_y}`;
    }

    if (scrollY === null) {
      log.push({ page: a.page, label: a.label, ok: false, note: 'no anchor strategy succeeded' });
      console.log(`[capture] page ${a.page}: NO ANCHOR — skipped`);
      continue;
    }

    await page.evaluate(yy => window.scrollTo(0, yy), scrollY);
    await new Promise(r => setTimeout(r, 250));
    const out = path.join(OUT_DIR, `page-${pad2(a.page)}.png`);
    await page.screenshot({ path: out });
    const stat = fs.statSync(out);
    log.push({ page: a.page, label: a.label, ok: true, strategy, detail, scrollY, bytes: stat.size });
    console.log(`[capture] page ${a.page} (${a.label}): ${strategy} ${detail} scrollY=${scrollY} -> ${out} ${stat.size}B`);
  }

  // 4) Manifest of what was captured
  const manifestOut = {
    label,
    url: URL,
    viewport: VIEW,
    rendered_ok: renderedOk,
    render_error: renderError,
    full_height: fullHeight,
    captured_at: new Date().toISOString(),
    anchors: log,
  };
  fs.writeFileSync(path.join(OUT_DIR, '_manifest.json'), JSON.stringify(manifestOut, null, 2));

  await browser.close();
  console.log('[capture] done');
})().catch(e => {
  console.error(e);
  process.exit(1);
});
