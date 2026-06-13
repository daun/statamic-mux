// PROTOTYPE — capture each hero variant as a GIF for self-review.
// Usage: node capture.mjs [variantKeys] e.g. node capture.mjs A B C D E
// Loads the home page with ?variant=X&bar=0, screenshots N frames, ffmpeg -> gif.
import { chromium } from 'playwright'
import { execFileSync } from 'node:child_process'
import { mkdirSync, rmSync, existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const __dir = dirname(fileURLToPath(import.meta.url))
const BASE = 'http://localhost:5174/'
const ALL = ['A', 'B']
const keys = process.argv.slice(2).filter(k => ALL.includes(k))
const variants = keys.length ? keys : ALL

const FPS = 12
const SECONDS = 7
const FRAMES = FPS * SECONDS

// crop to the hero image area for a focused clip
const browser = await chromium.launch()
const page = await browser.newPage({
  viewport: { width: 1280, height: 800 },
  deviceScaleFactor: 2,
})

for (const v of variants) {
  const tmp = join(__dir, `frames_${v}`)
  if (existsSync(tmp)) rmSync(tmp, { recursive: true })
  mkdirSync(tmp, { recursive: true })

  await page.goto(`${BASE}?variant=${v}&bar=0`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(600)

  // locate the hero image stage for a tight crop
  const el = await page.$('.VPHero .image-container')
  const box = el ? await el.boundingBox() : null
  const clip = box
    ? { x: Math.max(0, box.x - 10), y: Math.max(0, box.y - 10), width: box.width + 20, height: box.height + 20 }
    : undefined

  process.stdout.write(`\n[${v}] capturing ${FRAMES} frames`)
  for (let i = 0; i < FRAMES; i++) {
    await page.screenshot({ path: join(tmp, `f${String(i).padStart(3, '0')}.png`), clip })
    await page.waitForTimeout(1000 / FPS)
    if (i % 12 === 0) process.stdout.write('.')
  }

  const out = join(__dir, `variant_${v}.gif`)
  // ffmpeg: palette for clean gif
  const palette = join(tmp, 'pal.png')
  execFileSync('ffmpeg', ['-y', '-i', join(tmp, 'f%03d.png'),
    '-vf', `fps=${FPS},scale=520:-1:flags=lanczos,palettegen=stats_mode=diff`, palette],
    { stdio: 'ignore' })
  execFileSync('ffmpeg', ['-y', '-framerate', String(FPS), '-i', join(tmp, 'f%03d.png'),
    '-i', palette, '-lavfi', `fps=${FPS},scale=520:-1:flags=lanczos[x];[x][1:v]paletteuse=dither=bayer:bayer_scale=3`,
    out], { stdio: 'ignore' })

  // also a full-page still for layout review
  await page.screenshot({ path: join(__dir, `page_${v}.png`), fullPage: false })

  rmSync(tmp, { recursive: true })
  process.stdout.write(` -> ${out}`)
}

await browser.close()
console.log('\ndone')
