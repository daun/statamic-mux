# PROTOTYPE — Hero animation art directions

**Question:** Which hero animation best conveys that Mux optimizes & streams
video for you — lightly, playfully, "a little machine that just works" — without
you running any infrastructure?

**Shape:** UI prototype, sub-shape A. Rendered into the VitePress home
`home-hero-image` slot on the existing `docs/index.md` home page, switchable via
`?variant=` + a floating bottom switcher bar.

**Status:** narrowed to **A + B** (C Machine, D Player, E Pipeline dropped).
Refinement of A and B is next.

## How to view

```bash
cd docs && npm run dev          # http://localhost:5174/
```

- Open the home page. A floating pill at the bottom switches variants.
- `←` / `→` arrow keys cycle. URL stays shareable (`?variant=C`).
- `?bar=0` hides the switcher (used for clean screenshots).
- The switcher bar is dev-only intent; remove the whole prototype before ship.

## The five directions

| Key | Name | Concept / message | Tech |
|-----|------|-------------------|------|
| A | **Pixelate Reveal** | A video frame loads from chunky pixels to sharp as a progress bar fills, resolution steps 144p→1080p. "Plays within seconds, sharpens as it streams." | Canvas (downscale-up pixelation) + CSS |
| B | **Bitrate Stream** | Abstract adaptive-bitrate waveform flowing, heights adapting, a playhead reading it. "Optimized streaming matched to bandwidth." | SVG + anime.js |


## Self-review / screenshots

`prototype-shots/capture.mjs` (Playwright + ffmpeg) loads each variant with
`?bar=0`, captures ~84 frames at 12fps, and batches them into a looping GIF
per variant (+ a still). Run:

```bash
cd docs
node prototype-shots/capture.mjs          # all five
node prototype-shots/capture.mjs C E      # specific ones
```

Outputs: `prototype-shots/variant_{A..E}.gif`, plus `contact_sheet.png`.

## Verdict

_TBD — pick a winner, then:_
- delete the losing variant `.vue` files + `HeroPrototype.vue` + the switcher;
- fold the winner into `docs/index.md` / the theme (rewrite cleanly — these were
  built under prototype constraints: no tests, minimal guards);
- revert the prototype CSS block in `.vitepress/theme/style.css`
  (marked `PROTOTYPE — Hero animation stage`) and the slot in `theme/index.js`;
- remove `animejs` + `playwright` from `docs/package.json` if the winner
  doesn't need anime.js;
- delete `prototype-shots/`.
```
