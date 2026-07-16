/**
 * Screenshot-driven regression test for gstack-dashboard.dc.html.
 *
 * Keeps the Definition-of-Done provable on every change instead of it being a
 * one-time manual claim. It drives the committed single-file dashboard in a
 * real headless browser and, in one pass:
 *
 *   - re-runs the live-state assertions for
 *       [4] sidebar Active/Idle/Error counts == the actual cards on screen
 *       [5] header "N running" badge == number of running skills
 *       [6] "N of 34" + per-category counts derive from state (move together)
 *       [11] Escape closes the topmost layer (palette before drawer) + focus trap
 *       [15] zero console errors / page errors across the whole flow
 *   - regenerates the proof screenshots for
 *       [7] error card  [8] running card  [9] drawer  [10] palette
 *       [12] no-dup activity log  [13] log max-height+scroll  [14] empty state
 *
 * Screenshots land in test/__screenshots__/gstack-dashboard/ (gitignored) so a
 * reviewer can eyeball the current visual states after any edit. Override the
 * output dir with DASHBOARD_SHOTS_DIR.
 *
 * Skips (does not fail) when no Chromium is available, so it never breaks a
 * machine without a browser; CI's Playwright image runs it for real.
 */
import { describe, test, expect, beforeAll, afterAll } from "bun:test";
import { chromium, type Browser, type Page } from "playwright";
import fs from "node:fs";
import path from "node:path";

const ROOT = path.resolve(import.meta.dir, "..");
const HTML = path.join(ROOT, "gstack-dashboard.dc.html");
const SHOTS = process.env.DASHBOARD_SHOTS_DIR
  ? path.resolve(process.env.DASHBOARD_SHOTS_DIR)
  : path.join(ROOT, "test", "__screenshots__", "gstack-dashboard");

/** Resolve a usable Chromium binary: Playwright's bundle, else the CI image's. */
function findChromium(): string | undefined {
  try {
    const p = chromium.executablePath();
    if (p && fs.existsSync(p)) return p;
  } catch {
    /* Playwright browsers not installed via `playwright install` — try the CI image path */
  }
  const base = process.env.PLAYWRIGHT_BROWSERS_PATH || "/opt/pw-browsers";
  try {
    for (const entry of fs.readdirSync(base)) {
      if (!entry.startsWith("chromium")) continue;
      for (const rel of ["chrome-linux/chrome", "chrome-linux/headless_shell"]) {
        const candidate = path.join(base, entry, rel);
        if (fs.existsSync(candidate)) return candidate;
      }
    }
  } catch {
    /* no browsers dir */
  }
  return undefined;
}

const EXE = findChromium();
const HAS_HTML = fs.existsSync(HTML);
// If the dashboard file or a browser is missing, skip the whole suite rather than fail.
const suite = EXE && HAS_HTML ? describe : describe.skip;

type Snap = { sidebar: Record<string, number>; cards: Record<string, number>; badgeN: number | null; countPill: string | null };

suite("gstack-dashboard.dc.html regression", () => {
  let browser: Browser;
  let page: Page;
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];

  // Results captured by the single drive-through in beforeAll.
  const R: {
    cardCount: number;
    initCount: number;
    initCountLater: number;
    before?: Snap;
    during?: Snap;
    after?: Snap;
    consistent: boolean;
    focusStartsInDrawer: boolean;
    focusTrapped: boolean;
    afterEsc1?: { palette: boolean; drawer: boolean };
    afterEsc2?: { palette: boolean; drawer: boolean };
    palResults: string[];
    palSelBefore: string | null;
    palSelAfterDown: string | null;
    emptyShown: boolean;
    scroll?: { entries: number; scrollable: boolean; maxHeight: string; overflowY: string };
    shots: string[];
  } = {
    cardCount: 0, initCount: 0, initCountLater: 0, consistent: false,
    focusStartsInDrawer: false, focusTrapped: true,
    palResults: [], palSelBefore: null, palSelAfterDown: null, emptyShown: false, shots: [],
  };

  const shot = async (name: string, clip?: { x: number; y: number; width: number; height: number }) => {
    const file = path.join(SHOTS, name);
    await page.screenshot({ path: file, clip });
    R.shots.push(file);
    return file;
  };

  const readState = (): Promise<Snap> => page.evaluate(() => {
    const rows: Record<string, number> = {};
    document.querySelectorAll(".status-row").forEach((r) => {
      const label = (r.querySelector(".label")?.textContent || "").trim().toLowerCase();
      rows[label] = parseInt(r.querySelector(".n")?.textContent || "0", 10);
    });
    const cards: Record<string, number> = { active: 0, idle: 0, error: 0, running: 0 };
    document.querySelectorAll(".grid .card").forEach((c) => {
      (["active", "idle", "error", "running"] as const).forEach((k) => {
        if (c.classList.contains("status-" + k)) cards[k]++;
      });
    });
    const badge = document.querySelector(".running-badge");
    const badgeN = badge ? parseInt(badge.textContent || "0", 10) : null;
    const cp = document.querySelector(".count-pill");
    return { sidebar: rows, cards, badgeN, countPill: cp ? (cp.textContent || "").trim() : null };
  });

  const clickRun = (name: string) => page.evaluate((nm) => {
    const cards = Array.from(document.querySelectorAll(".grid .card"));
    for (const c of cards) {
      if ((c.querySelector(".card-title")?.textContent || "").trim() === nm) {
        (c.querySelector(".btn-run, .btn-retry") as HTMLButtonElement | null)?.click();
        return true;
      }
    }
    return false;
  }, name);

  beforeAll(async () => {
    fs.mkdirSync(SHOTS, { recursive: true });
    browser = await chromium.launch({ executablePath: EXE });
    page = await browser.newPage({ viewport: { width: 1460, height: 940 }, deviceScaleFactor: 2 });
    page.on("console", (m) => { if (m.type() === "error") consoleErrors.push(m.text()); });
    page.on("pageerror", (e) => pageErrors.push(e.message));

    await page.goto("file://" + HTML, { waitUntil: "domcontentloaded" });
    await page.waitForSelector(".grid .card", { timeout: 10000 });
    await page.waitForTimeout(150);

    R.cardCount = await page.$$eval(".grid .card", (els) => els.length);
    R.initCount = await page.evaluate(() =>
      Array.from(document.querySelectorAll(".log-msg")).filter((e) => /Console initialized/.test(e.textContent || "")).length);

    await shot("01-overview.png");
    R.before = await readState();

    // ---- run an idle skill -> RUNNING (deterministic window before the 2-4s resolve) ----
    await clickRun("Eng Review");
    await page.waitForTimeout(400);
    R.during = await readState();
    await shot("04-counters-during.png");
    const runBox = await page.evaluate(() => {
      const c = Array.from(document.querySelectorAll(".grid .card")).find((x) => x.classList.contains("status-running"));
      if (!c) return null; const r = c.getBoundingClientRect();
      return { x: r.x, y: r.y, width: r.width, height: r.height };
    });
    if (runBox) await shot("03-running-card.png", { x: Math.max(0, runBox.x - 8), y: Math.max(0, runBox.y - 8), width: runBox.width + 16, height: runBox.height + 16 });

    await page.waitForTimeout(4200); // let it resolve
    R.after = await readState();
    R.consistent = await page.evaluate(() => {
      const rows: Record<string, number> = {};
      document.querySelectorAll(".status-row").forEach((r) => {
        rows[(r.querySelector(".label")?.textContent || "").trim().toLowerCase()] = parseInt(r.querySelector(".n")?.textContent || "0", 10);
      });
      const cards: Record<string, number> = { active: 0, idle: 0, error: 0, running: 0 };
      document.querySelectorAll(".grid .card").forEach((c) => (["active", "idle", "error", "running"] as const).forEach((k) => { if (c.classList.contains("status-" + k)) cards[k]++; }));
      return rows.active === cards.active && rows.idle === cards.idle && rows.error === cards.error && rows.running === cards.running;
    });

    // ---- [7] error card (codex is seeded error) ----
    const errBox = await page.evaluate(() => {
      const c = Array.from(document.querySelectorAll(".grid .card")).find((x) => x.classList.contains("status-error") && /Codex/.test(x.textContent || ""));
      if (!c) return null; c.scrollIntoView({ block: "center" }); const r = c.getBoundingClientRect();
      return { x: r.x, y: r.y, width: r.width, height: r.height };
    });
    await page.waitForTimeout(150);
    if (errBox) {
      const b = await page.evaluate(() => {
        const c = Array.from(document.querySelectorAll(".grid .card")).find((x) => x.classList.contains("status-error") && /Codex/.test(x.textContent || ""));
        if (!c) return null; const r = c.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height };
      });
      if (b) await shot("05-error-card.png", { x: Math.max(0, b.x - 8), y: Math.max(0, b.y - 8), width: b.width + 16, height: b.height + 16 });
    }

    // ---- [9] drawer ----
    await page.evaluate(() => {
      const cards = Array.from(document.querySelectorAll(".grid .card"));
      const c = cards.find((x) => /PR Review/.test(x.textContent || "")) || cards[0];
      (c as HTMLElement).click();
    });
    await page.waitForSelector(".drawer", { timeout: 3000 });
    await page.waitForTimeout(250);
    await shot("06-drawer.png");

    // ---- [11] focus trap: focus must start inside drawer and stay across many Tabs ----
    R.focusStartsInDrawer = await page.evaluate(() => document.querySelector(".drawer")!.contains(document.activeElement));
    for (let i = 0; i < 12; i++) {
      await page.keyboard.press("Tab");
      const inside = await page.evaluate(() => document.querySelector(".drawer")!.contains(document.activeElement));
      if (!inside) R.focusTrapped = false;
    }

    // ---- [10] palette open (on top of the drawer) + query + arrow nav ----
    await page.keyboard.press("Control+k");
    await page.waitForSelector(".palette-wrap", { timeout: 3000 });
    await page.type(".palette-input input", "rev", { delay: 30 });
    await page.waitForTimeout(200);
    await shot("07-palette.png");
    R.palResults = await page.$$eval(".pal-row", (els) => els.map((e) => (e.querySelector(".pal-name")?.textContent || "").trim()));
    R.palSelBefore = await page.$eval(".pal-row.sel .pal-name", (e) => (e.textContent || "").trim()).catch(() => null);
    await page.keyboard.press("ArrowDown");
    R.palSelAfterDown = await page.$eval(".pal-row.sel .pal-name", (e) => (e.textContent || "").trim()).catch(() => null);

    // ---- [11] Escape layering: #1 closes palette (drawer stays), #2 closes drawer ----
    await page.keyboard.press("Escape");
    await page.waitForTimeout(120);
    R.afterEsc1 = await page.evaluate(() => ({ palette: !!document.querySelector(".palette-wrap"), drawer: !!document.querySelector(".drawer") }));
    await page.keyboard.press("Escape");
    await page.waitForTimeout(120);
    R.afterEsc2 = await page.evaluate(() => ({ palette: !!document.querySelector(".palette-wrap"), drawer: !!document.querySelector(".drawer") }));

    // ---- [14] empty state ----
    await page.fill(".field input", "zzznotaskill");
    await page.waitForTimeout(150);
    await shot("08-empty.png");
    R.emptyShown = await page.evaluate(() => !!document.querySelector(".empty"));
    await page.fill(".field input", "");
    await page.waitForTimeout(120);

    // ---- [13] activity log overflow: run several -> more entries ----
    for (const nm of ["QA", "Investigate", "Ship", "Health", "Canary", "Scrape"]) {
      await clickRun(nm);
      await page.waitForTimeout(100);
    }
    await page.waitForTimeout(4600);
    R.scroll = await page.evaluate(() => {
      const l = document.querySelector(".activity-log") as HTMLElement;
      const cs = getComputedStyle(l);
      return { entries: document.querySelectorAll(".log-row").length, scrollable: l.scrollHeight > l.clientHeight + 2, maxHeight: cs.maxHeight, overflowY: cs.overflowY };
    });
    const actBox = await page.evaluate(() => { const r = document.querySelector(".activity")!.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; });
    await page.evaluate(() => document.querySelector(".activity")!.scrollIntoView({ block: "center" }));
    await page.waitForTimeout(120);
    const actBox2 = await page.evaluate(() => { const r = document.querySelector(".activity")!.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; });
    await shot("09-activity-scroll.png", { x: Math.max(0, actBox2.x - 6), y: Math.max(0, actBox2.y - 6), width: actBox.width + 12, height: Math.min(actBox.height + 12, 900) });

    // ---- [12] still exactly one "Console initialized" after all the activity ----
    R.initCountLater = await page.evaluate(() =>
      Array.from(document.querySelectorAll(".log-msg")).filter((e) => /Console initialized/.test(e.textContent || "")).length);
  }, 60000);

  afterAll(async () => { if (browser) await browser.close(); });

  test("[1] all 34 skills render from the array", () => {
    expect(R.cardCount).toBe(34);
  });

  test("[4] sidebar Active/Idle/Error counts match the actual cards on screen", () => {
    // Snapshot equality proves the sidebar is derived, not hardcoded.
    expect(R.before!.sidebar.active).toBe(R.before!.cards.active);
    expect(R.before!.sidebar.idle).toBe(R.before!.cards.idle);
    expect(R.before!.sidebar.error).toBe(R.before!.cards.error);
    expect(R.consistent).toBe(true); // holds again after a run resolves
  });

  test("[5] header 'N running' badge matches the number of running skills", () => {
    expect(R.during!.badgeN).toBe(R.during!.cards.running);
    expect(R.during!.cards.running).toBe(1);
    expect(R.after!.badgeN).toBe(0); // back to zero once resolved
  });

  test("[6] counts are derived and move together on a single state change", () => {
    // idle drops by one and running rises by one the instant a skill starts.
    expect(R.during!.sidebar.running).toBe(R.before!.sidebar.running + 1);
    expect(R.during!.sidebar.idle).toBe(R.before!.sidebar.idle - 1);
    // total stays 34 the whole time.
    const total = (s: Snap) => s.sidebar.active + s.sidebar.idle + s.sidebar.error + s.sidebar.running;
    expect(total(R.before!)).toBe(34);
    expect(total(R.during!)).toBe(34);
    expect(total(R.after!)).toBe(34);
    expect(R.before!.countPill).toContain("of 34 skills");
  });

  test("[10] command palette fuzzy-searches and arrow keys move the selection", () => {
    expect(R.palResults.length).toBeGreaterThan(0);
    expect(R.palResults.every((n) => /review/i.test(n))).toBe(true);
    expect(R.palSelAfterDown).not.toBeNull();
    expect(R.palSelAfterDown).not.toBe(R.palSelBefore); // ArrowDown changed the selection
  });

  test("[11] Escape closes the topmost layer first (palette, then drawer) + focus trap", () => {
    expect(R.focusStartsInDrawer).toBe(true);
    expect(R.focusTrapped).toBe(true);
    // Esc #1: palette gone, drawer still open.
    expect(R.afterEsc1).toEqual({ palette: false, drawer: true });
    // Esc #2: drawer gone.
    expect(R.afterEsc2).toEqual({ palette: false, drawer: false });
  });

  test("[12] activity log has no duplicate 'Console initialized' entry", () => {
    expect(R.initCount).toBe(1);
    expect(R.initCountLater).toBe(1); // still 1 after many runs
  });

  test("[13] activity log has a max-height and scrolls", () => {
    expect(R.scroll!.maxHeight).toBe("340px");
    expect(R.scroll!.overflowY).toBe("auto");
    expect(R.scroll!.entries).toBeGreaterThan(8);
    expect(R.scroll!.scrollable).toBe(true);
  });

  test("[14] empty state shows when the filter matches nothing", () => {
    expect(R.emptyShown).toBe(true);
  });

  test("[15] zero console errors and zero page errors across the flow", () => {
    expect(consoleErrors).toEqual([]);
    expect(pageErrors).toEqual([]);
  });

  test("[7-14] proof screenshots were regenerated and are non-empty", () => {
    const expected = [
      "01-overview.png", "03-running-card.png", "04-counters-during.png", "05-error-card.png",
      "06-drawer.png", "07-palette.png", "08-empty.png", "09-activity-scroll.png",
    ];
    for (const name of expected) {
      const file = path.join(SHOTS, name);
      expect(fs.existsSync(file)).toBe(true);
      expect(fs.statSync(file).size).toBeGreaterThan(1000);
    }
  });
});
