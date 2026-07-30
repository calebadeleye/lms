#!/usr/bin/env node
/**
 * One-off dev script (not run by the app at runtime): searches Pexels for a
 * fitting stock photo per homepage placeholder slot, downloads it into
 * public/marketing/, and writes a small credits manifest. Re-run any time to
 * refresh a slot — API responses are cached on disk for PEXELS_CACHE_TTL
 * seconds so repeated runs during iteration don't burn API quota.
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
const OUTPUT_DIR = path.join(ROOT, 'public', 'marketing');

async function loadEnv() {
  const content = await fs.readFile(ENV_PATH, 'utf8');
  const env = {};
  for (const line of content.split('\n')) {
    const match = line.match(/^([A-Z0-9_]+)=(.*)$/);
    if (match) env[match[1]] = match[2].trim();
  }
  return env;
}

// One search query + one output filename per placeholder slot in
// src/lib/home-content.ts. Query wording aims for genuine, non-staged-looking
// professional/coaching imagery matching each slot's actual caption/alt text.
const SLOTS = [
  { file: 'hero.jpg', query: 'coaching session office conversation', orientation: 'landscape' },
  { file: 'who-we-are.jpg', query: 'hands together teamwork unity', orientation: 'landscape' },
  { file: 'community-1.jpg', query: 'group coaching discussion meeting', orientation: 'landscape' },
  { file: 'community-2.jpg', query: 'colleagues peer learning laptop', orientation: 'landscape' },
  { file: 'community-3.jpg', query: 'mentor mentee conversation office', orientation: 'landscape' },
  { file: 'community-4.jpg', query: 'professional networking event handshake', orientation: 'landscape' },
  { file: 'community-5.jpg', query: 'workshop training seminar whiteboard', orientation: 'landscape' },
  { file: 'community-6.jpg', query: 'career growth professional development', orientation: 'landscape' },
  { file: 'more-stories.jpg', query: 'person achievement mountain success', orientation: 'square' },
];

async function cachedSearch(env, slot) {
  await fs.mkdir(CACHE_DIR, { recursive: true });
  const cacheKey = slot.query.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
  const cachePath = path.join(CACHE_DIR, `${cacheKey}.json`);
  const ttlSeconds = Number(env.PEXELS_CACHE_TTL || '86400');

  try {
    const cached = JSON.parse(await fs.readFile(cachePath, 'utf8'));
    if (Date.now() - cached.fetchedAt < ttlSeconds * 1000) {
      console.log(`  cache hit (${slot.query})`);
      return cached.body;
    }
  } catch {
    // no cache yet, fall through to a real fetch
  }

  const url = `https://api.pexels.com/v1/search?query=${encodeURIComponent(slot.query)}&per_page=3&orientation=${slot.orientation}`;
  const response = await fetch(url, { headers: { Authorization: env.PEXELS_API_KEY } });
  if (!response.ok) {
    throw new Error(`Pexels search failed for "${slot.query}": ${response.status} ${await response.text()}`);
  }
  const body = await response.json();
  await fs.writeFile(cachePath, JSON.stringify({ fetchedAt: Date.now(), body }, null, 2));
  return body;
}

async function downloadPhoto(photo, destPath) {
  const response = await fetch(photo.src.large2x ?? photo.src.large);
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

  await fs.mkdir(OUTPUT_DIR, { recursive: true });
  const credits = [];

  for (const slot of SLOTS) {
    console.log(`Searching: ${slot.query}`);
    const body = await cachedSearch(env, slot);
    const photo = body.photos?.[0];
    if (!photo) {
      console.warn(`  no result for "${slot.query}" — skipping ${slot.file}`);
      continue;
    }

    const destPath = path.join(OUTPUT_DIR, slot.file);
    await downloadPhoto(photo, destPath);
    console.log(`  saved ${slot.file} (photo by ${photo.photographer})`);
    credits.push({ file: slot.file, photographer: photo.photographer, photographerUrl: photo.photographer_url, pexelsUrl: photo.url });
  }

  const creditsPath = path.join(OUTPUT_DIR, 'CREDITS.md');
  const creditsBody =
    '# Photo credits\n\n' +
    'Sourced from [Pexels](https://www.pexels.com) — free to use, attribution not required but recorded here for traceability.\n\n' +
    credits.map((c) => `- \`${c.file}\` — [${c.photographer}](${c.photographerUrl}) ([source](${c.pexelsUrl}))`).join('\n') +
    '\n';
  await fs.writeFile(creditsPath, creditsBody);

  console.log(`\nDone. ${credits.length}/${SLOTS.length} images saved to public/marketing/.`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
