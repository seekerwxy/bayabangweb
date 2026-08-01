const { chromium } = require('C:/Users/DN/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { pathToFileURL } = require('url');
const fs = require('fs');

const base = 'E:/辛巴巴';
const styles = [
  { dir: 'style-1-editorial', name: 'editorial' },
  { dir: 'style-2-tech', name: 'tech' },
  { dir: 'style-3-classical', name: 'classical' },
  { dir: 'style-4-western', name: 'western' },
  { dir: 'style-5-gothic', name: 'gothic' }
];

const results = [];

async function checkPage(page, style, theme, viewport) {
  const url = pathToFileURL(`${base}/${style.dir}/index.html`).href + `?theme=${theme}`;
  await page.setViewportSize(viewport);
  await page.goto(url, { waitUntil: 'load', timeout: 30000 });
  await page.waitForTimeout(900);

  const layout = await page.evaluate(() => {
    const vw = window.innerWidth;
    const offenders = [];
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect();
      if (r.width === 0 && r.height === 0) continue;
      if (r.right > vw + 1 || r.left < -1) {
        const cls = typeof el.className === 'string' ? el.className : (el.getAttribute('class') || '');
        offenders.push({
          tag: el.tagName,
          cls: cls.slice(0, 60),
          left: Math.round(r.left),
          right: Math.round(r.right),
          w: Math.round(r.width)
        });
      }
    }
    const failedImgs = Array.from(document.images)
      .filter((img) => !img.complete || img.naturalWidth === 0)
      .map((img) => img.getAttribute('src'));
    return {
      scrollWidth: document.documentElement.scrollWidth,
      vw,
      dataTheme: document.documentElement.getAttribute('data-theme'),
      failedImgs,
      offenders: offenders.slice(0, 10),
      bodyText: document.body.innerText.length
    };
  });

  const themeToggle = page.locator('#themeToggle');
  let toggleWorks = false;
  if (await themeToggle.count()) {
    const before = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    await themeToggle.click();
    const after = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    toggleWorks = before !== after;
  }

  const noOverflow = layout.scrollWidth <= layout.vw + 1;
  const noBrokenImages = layout.failedImgs.length === 0;
  results.push({
    style: style.name,
    theme,
    viewport: viewport.width,
    dataTheme: layout.dataTheme,
    toggleWorks,
    noOverflow,
    noBrokenImages,
    scrollWidth: layout.scrollWidth,
    vw: layout.vw,
    brokenImages: layout.failedImgs,
    offenders: layout.offenders,
    bodyText: layout.bodyText
  });
}

(async () => {
  const browser = await chromium.launch({
    channel: 'msedge',
    headless: true,
    args: ['--disable-gpu', '--no-sandbox', '--disable-software-rasterizer', '--disable-features=Vulkan,CanvasOopRasterization']
  });
  const page = await browser.newPage();
  for (const style of styles) {
    for (const theme of ['light', 'dark']) {
      await checkPage(page, style, theme, { width: 1280, height: 900 });
      await checkPage(page, style, theme, { width: 390, height: 844 });
    }
  }
  await browser.close();

  fs.writeFileSync('E:/辛巴巴/_preview/verify-report.json', JSON.stringify(results, null, 2));
  for (const r of results) {
    const flag = (r.noOverflow && r.noBrokenImages && r.toggleWorks && r.bodyText > 200) ? 'PASS' : 'FAIL';
    console.log(
      `${flag} ${r.style.padEnd(10)} ${r.theme.padEnd(5)} ${String(r.viewport).padStart(4)}px` +
      ` noOverflow=${r.noOverflow} imgs=${r.noBrokenImages ? 0 : r.brokenImages.length} toggle=${r.toggleWorks}` +
      (r.noOverflow ? '' : ` offenders=${JSON.stringify(r.offenders.slice(0, 3))}`)
    );
  }
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
