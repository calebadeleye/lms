#!/usr/bin/env node
/**
 * One-off dev script (not run by the app at runtime): searches Pexels for
 * candidate stock photos per homepage placeholder slot and downloads them
 * into .pexels-candidates/<slot>/ for visual review, since Pexels' keyword
 * search doesn't reliably guarantee the subjects match a specific
 * demographic — each slot needs a human pick, not just "take the first
 * result". Once a candidate is chosen, copy it into public/marketing/ by
 * hand and record it in CREDITS.md.
 *
 * Usage: node scripts/fetch-marketing-images.mjs
 * Requires PEXELS_API_KEY (and optionally PEXELS_CACHE_TTL, seconds) in
 * naitalk-lms-frontend/.env.local.
 */
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const ENV_PATH = path.join(ROOT, '.env.local');
const CACHE_DIR = path.join(ROOT, '.pexels-cache');
const CANDIDATES_DIR = path.join(ROOT, '.pexels-candidates');

async function loadEnv() {
  const content = await fs.readFile(ENV_PATH, 'utf8');
  const env = {};
  for (const line of content.split('\n')) {
    const match = line.match(/^([A-Z0-9_]+)=(.*)$/);
    if (match) env[match[1]] = match[2].trim();
  }
  return env;
}

// One search query + output slot per placeholder in src/lib/home-content.ts.
// Every query is scoped to Black students/young professionals per the
// client's brief (Black-skinned subjects, mostly undergraduate-age).
const SLOTS = [
  { slot: 'hero', query: 'black college students studying together laptop', orientation: 'landscape' },
  { slot: 'who-we-are', query: 'black students hands together teamwork', orientation: 'landscape' },
  { slot: 'community-1', query: 'black students group discussion campus', orientation: 'landscape' },
  { slot: 'community-2', query: 'black student studying laptop library', orientation: 'landscape' },
  { slot: 'community-3', query: 'black mentor student conversation', orientation: 'landscape' },
  { slot: 'community-4', query: 'black students networking event smiling', orientation: 'landscape' },
  { slot: 'community-5', query: 'black students workshop classroom', orientation: 'landscape' },
  { slot: 'community-6', query: 'black graduate student career', orientation: 'landscape' },
  { slot: 'more-stories', query: 'black student portrait thinking', orientation: 'square' },
];

async function cachedSearch(env, query, orientation) {
  await fs.mkdir(CACHE_DIR, { recursive: true });
  const cacheKey = query.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
  const cachePath = path.join(CACHE_DIR, `${cacheKey}.json`);
  const ttlSeconds = Number(env.PEXELS_CACHE_TTL || '86400');

  try {
    const cached = JSON.parse(await fs.readFile(cachePath, 'utf8'));
    if (Date.now() - cached.fetchedAt < ttlSeconds * 1000) {
      console.log(`  cache hit (${query})`);
      return cached.body;
    }
  } catch {
    // no cache yet, fall through to a real fetch
  }

  const url = `https://api.pexels.com/v1/search?query=${encodeURIComponent(query)}&per_page=8&orientation=${orientation}`;
  const response = await fetch(url, { headers: { Authorization: env.PEXELS_API_KEY } });
  if (!response.ok) {
    throw new Error(`Pexels search failed for "${query}": ${response.status} ${await response.text()}`);
  }
  const body = await response.json();
  await fs.writeFile(cachePath, JSON.stringify({ fetchedAt: Date.now(), body }, null, 2));
  return body;
}

async function downloadPhoto(photo, destPath) {
  const response = await fetch(photo.src.large ?? photo.src.large2x);
  if (!response.ok) throw new Error(`Download failed for ${destPath}: ${response.status}`);
  const buffer = Buffer.from(await response.arrayBuffer());
  await fs.writeFile(destPath, buffer);
}

async function main() {
  const env = await loadEnv();
  if (!env.PEXELS_API_KEY) {
    console.error('PEXELS_API_KEY not found in .env.local');
    process.exit(1);
  }

  await fs.mkdir(CANDIDATES_DIR, { recursive: true });
  const manifest = [];

  for (const { slot, query, orientation } of SLOTS) {
    console.log(`Searching: ${query}`);
    const body = await cachedSearch(env, query, orientation);
    const photos = body.photos ?? [];
    if (!photos.length) {
      console.warn(`  no results for "${query}"`);
      continue;
    }

    const slotDir = path.join(CANDIDATES_DIR, slot);
    await fs.mkdir(slotDir, { recursive: true });

    for (const [i, photo] of photos.entries()) {
      const destPath = path.join(slotDir, `${i}.jpg`);
      await downloadPhoto(photo, destPath);
      manifest.push({ slot, index: i, id: photo.id, photographer: photo.photographer, photographerUrl: photo.photographer_url, pexelsUrl: photo.url });
    }
    console.log(`  saved ${photos.length} candidates to .pexels-candidates/${slot}/`);
  }

  await fs.writeFile(path.join(CANDIDATES_DIR, 'manifest.json'), JSON.stringify(manifest, null, 2));
  console.log(`\nDone. Review .pexels-candidates/<slot>/*.jpg and pick one per slot.`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
