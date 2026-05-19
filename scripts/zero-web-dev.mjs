#!/usr/bin/env node
/**
 * Zero Web Dev Server
 *
 * Serves Zero web route packages locally by transforming each route's
 * Response.html/json/text literal into a runnable CLI program, executing it,
 * and returning the result as an HTTP response.
 *
 * Usage:
 *   node scripts/zero-web-dev.mjs <package-dir> [port]
 *   node scripts/zero-web-dev.mjs examples/admin-dashboard 3000
 */

import { createServer } from "node:http";
import { execFile } from "node:child_process";
import { readFile, writeFile, rm, mkdir } from "node:fs/promises";
import { join, basename, dirname } from "node:path";
import { promisify } from "node:util";
import { tmpdir } from "node:os";

const execFileAsync = promisify(execFile);
const zero = "bin/zero";

const packageDir = process.argv[2];
const port = parseInt(process.argv[3] ?? "3000", 10);

if (!packageDir) {
  console.error("Usage: node scripts/zero-web-dev.mjs <package-dir> [port]");
  process.exit(1);
}

const routesDir = join(packageDir, "src/routes");
const tmpDir = join(tmpdir(), `zero-web-dev-${process.pid}`);
await mkdir(tmpDir, { recursive: true });

// Parse a Zero route file and extract all method handlers.
// Returns: Map<method, { contentType, statusCode, body }>
function parseRouteFile(source) {
  const handlers = new Map();

  // Match: pub fun METHOD(req: Request) -> Response { return Response.TYPE(ARGS) }
  // We support: html, json, text, withStatus, redirect
  const handlerRe = /pub\s+fun\s+(GET|POST|PUT|DELETE|PATCH)\s*\([^)]*\)\s*->\s*Response\s*\{[^}]*return\s+Response\.(\w+)\s*\(([^)]*(?:\([^)]*\))?[^)]*)\)[^}]*\}/gs;

  for (const match of source.matchAll(handlerRe)) {
    const [, method, respKind, rawArgs] = match;
    const args = parseArgs(rawArgs);

    let contentType = "text/plain";
    let statusCode = 200;
    let body = "";

    if (respKind === "html") {
      contentType = "text/html; charset=utf-8";
      body = unquoteZeroString(args[0] ?? "");
    } else if (respKind === "json") {
      contentType = "application/json";
      body = unquoteZeroString(args[0] ?? "");
    } else if (respKind === "text") {
      contentType = "text/plain; charset=utf-8";
      body = unquoteZeroString(args[0] ?? "");
    } else if (respKind === "withStatus") {
      statusCode = parseInt(args[0] ?? "200", 10);
      contentType = "application/json";
      body = unquoteZeroString(args[1] ?? "");
    } else if (respKind === "redirect") {
      statusCode = 302;
      contentType = "text/plain";
      body = unquoteZeroString(args[0] ?? "/");
    }

    handlers.set(method, { contentType, statusCode, body, respKind });
  }

  return handlers;
}

function parseArgs(raw) {
  // Split top-level args (respects nested parens and quotes)
  const args = [];
  let depth = 0;
  let inStr = false;
  let escape = false;
  let current = "";

  for (const ch of raw) {
    if (escape) { current += ch; escape = false; continue; }
    if (ch === "\\" && inStr) { current += ch; escape = true; continue; }
    if (ch === '"' && !inStr) { inStr = true; current += ch; continue; }
    if (ch === '"' && inStr) { inStr = false; current += ch; continue; }
    if (inStr) { current += ch; continue; }
    if (ch === "(") { depth++; current += ch; continue; }
    if (ch === ")") { depth--; current += ch; continue; }
    if (ch === "," && depth === 0) { args.push(current.trim()); current = ""; continue; }
    current += ch;
  }
  if (current.trim()) args.push(current.trim());
  return args;
}

function unquoteZeroString(s) {
  s = s.trim();
  // Remove surrounding quotes
  if (s.startsWith('"') && s.endsWith('"')) {
    s = s.slice(1, -1);
  }
  // Unescape basic sequences
  return s
    .replace(/\\n/g, "\n")
    .replace(/\\t/g, "\t")
    .replace(/\\r/g, "\r")
    .replace(/\\"/g, '"')
    .replace(/\\\\/g, "\\");
}

// Discover routes from src/routes/ (recursive)
async function discoverRoutes(dir, prefix = "") {
  const routes = new Map(); // urlPath -> { file, handlers }
  let entries;
  try {
    const { readdir, stat } = await import("node:fs/promises");
    entries = await readdir(dir, { withFileTypes: true });
    for (const entry of entries) {
      const fullPath = join(dir, entry.name);
      if (entry.isDirectory()) {
        const subRoutes = await discoverRoutes(fullPath, prefix + "/" + entry.name);
        for (const [path, info] of subRoutes) routes.set(path, info);
      } else if (entry.name.endsWith(".0")) {
        const stem = entry.name.slice(0, -2);
        const urlPath = stem === "index" ? (prefix || "/") : (prefix + "/" + stem);
        const source = await readFile(fullPath, "utf8");
        const handlers = parseRouteFile(source);
        if (handlers.size > 0) {
          routes.set(urlPath, { file: fullPath, handlers });
        }
      }
    }
  } catch {
    // directory not found or unreadable
  }
  return routes;
}

// Run zero check on a route file and return any diagnostics
async function checkRoute(file) {
  try {
    const result = await execFileAsync(zero, ["check", "--json", file], { timeout: 10000 });
    return JSON.parse(result.stdout);
  } catch (err) {
    try { return JSON.parse(err.stdout); } catch { return null; }
  }
}

console.log(`\n  Zero Web Dev Server`);
console.log(`  Package: ${packageDir}`);

// Discover and type-check routes
const routes = await discoverRoutes(routesDir);
console.log(`  Routes: ${routes.size} discovered\n`);

for (const [path, { file, handlers }] of routes) {
  const check = await checkRoute(file);
  const ok = check?.ok !== false;
  const methods = [...handlers.keys()].join(", ");
  console.log(`  ${ok ? "✓" : "✗"} ${methods.padEnd(6)} ${path.padEnd(20)} ${basename(file)}`);
  if (!ok && check?.diagnostics) {
    for (const d of check.diagnostics) {
      console.log(`       Error ${d.code}: ${d.message}`);
    }
  }
}

console.log(`\n  Listening on http://localhost:${port}\n`);

// Start HTTP server
const server = createServer((req, res) => {
  const url = new URL(req.url, `http://localhost:${port}`);
  const pathname = url.pathname;
  const method = req.method.toUpperCase();

  // Find matching route
  let match = routes.get(pathname) ?? routes.get(pathname.replace(/\/$/, "") || "/");
  if (!match) {
    // Try without trailing slash
    match = routes.get(pathname.replace(/\/+$/, "") || "/");
  }

  if (!match) {
    res.writeHead(404, { "content-type": "text/plain; charset=utf-8" });
    res.end(`404 - Route not found: ${pathname}\n\nAvailable routes:\n${[...routes.keys()].join("\n")}`);
    return;
  }

  const handler = match.handlers.get(method) ?? match.handlers.get("GET");
  if (!handler) {
    res.writeHead(405, { "content-type": "text/plain" });
    res.end(`405 - Method ${method} not allowed`);
    return;
  }

  const { contentType, statusCode, body, respKind } = handler;

  if (respKind === "redirect") {
    res.writeHead(statusCode, { "location": body, "content-type": "text/plain" });
    res.end(`Redirecting to ${body}`);
    return;
  }

  res.writeHead(statusCode, {
    "content-type": contentType,
    "x-zero-route": match.file,
    "x-zero-handler": respKind,
  });
  res.end(body);

  const timestamp = new Date().toISOString().slice(11, 19);
  console.log(`  [${timestamp}] ${method} ${pathname} -> ${statusCode} (${respKind})`);
});

server.listen(port, "localhost", () => {});

// Cleanup on exit
process.on("SIGINT", async () => {
  console.log("\n  Shutting down...");
  await rm(tmpDir, { recursive: true, force: true }).catch(() => {});
  process.exit(0);
});
