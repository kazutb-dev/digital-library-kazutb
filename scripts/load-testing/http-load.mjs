#!/usr/bin/env node

import { performance } from 'node:perf_hooks';

const baseUrl = (process.env.LOAD_BASE_URL ?? 'http://perf-app').replace(/\/$/, '');
const concurrency = positiveInteger(process.env.LOAD_CONCURRENCY, 1);
const durationSeconds = positiveInteger(process.env.LOAD_DURATION_SECONDS, 30);
const timeoutMs = positiveInteger(process.env.LOAD_TIMEOUT_MS, 10_000);
const scenario = process.env.LOAD_SCENARIO ?? 'public-read';

const scenarios = {
  'public-read': [
    ['/', 20],
    ['/catalog', 25],
    ['/catalog?q=%D0%B0', 20],
    ['/resources', 10],
    ['/repository', 10],
    ['/news', 8],
    ['/events', 7],
  ],
  'catalog-search': [
    ['/catalog?q=%D0%B0', 35],
    ['/catalog?q=%D1%82%D0%B5%D1%85%D0%BD%D0%BE%D0%BB%D0%BE%D0%B3%D0%B8%D1%8F', 25],
    ['/catalog?language=ru', 15],
    ['/catalog?sort=latest', 15],
    ['/catalog', 10],
  ],
  'public-shell': [
    ['/', 25],
    ['/login', 20],
    ['/resources', 15],
    ['/repository', 15],
    ['/news', 15],
    ['/events', 10],
  ],
};

if (!(scenario in scenarios)) {
  throw new Error(`Unknown LOAD_SCENARIO=${scenario}`);
}

const weightedPaths = expandWeights(scenarios[scenario]);
const deadline = performance.now() + durationSeconds * 1_000;
const startedAt = new Date().toISOString();
const latencies = [];
const statuses = new Map();
let completed = 0;
let failed = 0;
let bytes = 0;
let cursor = 0;

async function worker() {
  while (performance.now() < deadline) {
    const path = weightedPaths[cursor++ % weightedPaths.length];
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const start = performance.now();

    try {
      const response = await fetch(`${baseUrl}${path}`, {
        redirect: 'manual',
        signal: controller.signal,
        headers: {
          Accept: 'text/html,application/xhtml+xml',
          'Accept-Encoding': 'gzip, deflate',
          'User-Agent': 'KazUTB-Isolated-Audit-Load/1.0',
        },
      });
      const body = await response.arrayBuffer();
      const elapsed = performance.now() - start;
      completed++;
      bytes += body.byteLength;
      statuses.set(response.status, (statuses.get(response.status) ?? 0) + 1);
      if (response.status >= 500) failed++;
      if (latencies.length < 1_000_000) latencies.push(elapsed);
    } catch {
      failed++;
      if (latencies.length < 1_000_000) latencies.push(performance.now() - start);
    } finally {
      clearTimeout(timer);
    }
  }
}

await Promise.all(Array.from({ length: concurrency }, () => worker()));

latencies.sort((a, b) => a - b);
const elapsedSeconds = durationSeconds;
const result = {
  schema_version: 1,
  generated_at: new Date().toISOString(),
  started_at: startedAt,
  target: baseUrl,
  scenario,
  concurrency,
  duration_seconds: durationSeconds,
  requests: completed,
  failures: failed,
  error_rate: completed > 0 ? failed / completed : 1,
  requests_per_second: completed / elapsedSeconds,
  throughput_bytes_per_second: bytes / elapsedSeconds,
  latency_ms: {
    min: quantile(latencies, 0),
    p50: quantile(latencies, 0.50),
    p90: quantile(latencies, 0.90),
    p95: quantile(latencies, 0.95),
    p99: quantile(latencies, 0.99),
    max: quantile(latencies, 1),
  },
  statuses: Object.fromEntries([...statuses.entries()].sort(([a], [b]) => a - b)),
  sampled_latencies: latencies.length,
};

process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);

function positiveInteger(value, fallback) {
  const parsed = Number.parseInt(value ?? '', 10);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function expandWeights(entries) {
  return entries.flatMap(([path, weight]) => Array.from({ length: weight }, () => path));
}

function quantile(sorted, fraction) {
  if (sorted.length === 0) return null;
  const index = Math.min(sorted.length - 1, Math.max(0, Math.ceil(sorted.length * fraction) - 1));
  return Number(sorted[index].toFixed(3));
}
