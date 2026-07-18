/**
 * Cross-platform "open a URL in the default browser" helper, shared by the
 * daemon-publish path (cli.ts) and the legacy single-process server
 * (serve.ts). Handles macOS (open), Linux (xdg-open), and headless or
 * unsupported environments (prints the URL instead).
 *
 * serve.ts's structured stderr telemetry (SERVE_BROWSER_OPENED /
 * SERVE_BROWSER_MANUAL, documented in docs/designs/DESIGN_SHOTGUN.md) is
 * preserved behind the `telemetry` option; cli.ts keeps its plain fallback
 * message by omitting it.
 */

import { spawn } from "child_process";

export function openBrowser(url: string, opts: { telemetry?: boolean } = {}): void {
  const platform = process.platform;
  let cmd: string;
  if (platform === "darwin") {
    cmd = "open";
  } else if (platform === "linux") {
    cmd = "xdg-open";
  } else {
    // Windows or unknown — just print the URL
    printManualFallback(url, opts.telemetry);
    return;
  }
  try {
    const child = spawn(cmd, [url], { stdio: "ignore", detached: true });
    child.unref();
    if (opts.telemetry) console.error(`SERVE_BROWSER_OPENED: url=${url}`);
  } catch {
    // open/xdg-open not available (headless CI environment)
    printManualFallback(url, opts.telemetry);
  }
}

function printManualFallback(url: string, telemetry?: boolean): void {
  if (telemetry) console.error(`SERVE_BROWSER_MANUAL: url=${url}`);
  console.error(`Open this URL in your browser: ${url}`);
}
