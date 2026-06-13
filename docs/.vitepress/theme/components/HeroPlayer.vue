<!--
  Hero animation for the docs home page.

  A looping vignette of the Mux workflow: drag-and-drop intro sequence
  followed by video processing and quality progression. The full loop:

  1. idle (800ms) — dark drop zone fades in over the canvas, no cursor visible
  2. dragging (1800ms) — a macOS Finder file thumbnail + cursor arc in from bottom-right,
     moving toward the drop zone center; drop zone highlights as they approach
  3. dropping (500ms) — thumbnail shrinks + fades, drop zone flashes then fades out; canvas takes over
  4. processing (2600ms) — block+counter spinner (existing)
  5. ready (1000ms) — play button fades in (existing)
  6. pressed (500ms) — press animation (existing)
  7. playing (6×1100ms = 6600ms) — resolution steps 144p→2160p (existing)
  8. hold (2000ms) — sharp frame held (existing)
  9. Fade to black → loop restart

  Implemented as a canvas that samples a hidden <video> each frame and pixelates
  it via a downscale-then-upscale pass (so any clip works, regardless of aspect).
  The preview clip lives in docs/public/ and can be swapped freely.
-->
<template>
  <div class="px-stage">
    <div class="px-drag-wrap">
      <div class="px-window">
        <!-- hidden source video; canvas samples + pixelates each frame -->
        <!-- no `loop`: we rewind to frame 0 manually at idle, so a clip that
             finishes early holds its last frame instead of jumping back.
             src is resolved once on mount from the active color scheme. -->
        <video ref="video" class="px-src"
               muted playsinline preload="auto"></video>
        <canvas ref="canvas" class="px-canvas" width="640" height="400"
               :style="blurAmount > 0 ? { filter: `blur(${blurAmount}px)`, transform: 'scale(1.08)' } : {}"></canvas>
        <div class="px-scrim"></div>

        <!-- drop zone overlay — mirrors the Statamic assets fieldtype empty state -->
        <div class="px-dropzone"
             :class="{ 'px-dropzone--active': dropActive }"
             :style="{ opacity: dropzoneOpacity }">
          <div class="px-field" :style="{ opacity: fieldOpacity }">
            <!-- upload cloud icon -->
            <svg viewBox="0 0 24 24" width="26" height="26" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="16 16 12 12 8 16"/>
              <line x1="12" y1="12" x2="12" y2="21"/>
              <path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
            </svg>
          </div>
        </div>

        <!-- center control: spinner + button share the same grid cell and crossfade -->
        <div class="px-center">
          <div class="px-blockcount" :style="{ opacity: spinnerOpacity }">
            <div class="px-bc-grid">
              <div
                v-for="i in 16"
                :key="i"
                class="px-bc-cell"
                :style="{ background: cellColor(i - 1) }"
              ></div>
            </div>
            <div class="px-bc-stats">
              <div class="px-bc-row"><span class="px-bc-dim">frame</span><span>{{ String(blockFrame).padStart(5, '0') }}</span></div>
              <div class="px-bc-row"><span class="px-bc-dim">fps</span><span>24</span></div>
              <div class="px-bc-row"><span class="px-bc-dim">kbps</span><span>{{ blockKbps }}</span></div>
            </div>
          </div>
          <button class="px-btn"
                  :class="{ ready: phase === 'ready', pressing: phase === 'pressed', paused: showPause }"
                  :style="{ opacity: btnOpacity }"
                  aria-hidden="true">
            <svg v-if="!showPause" viewBox="0 0 24 24" width="34" height="34">
              <polygon points="6 4 20 12 6 20" />
            </svg>
            <svg v-else viewBox="0 0 24 24" width="32" height="32">
              <rect x="6" y="5" width="4" height="14" rx="1.2" />
              <rect x="14" y="5" width="4" height="14" rx="1.2" />
            </svg>
          </button>
        </div>

        <div class="px-bar" v-show="barVisible"><span ref="fill" class="px-fill"></span></div>
        <div ref="res" class="px-res" v-show="hudVisible"></div>

        <!-- black fade across the loop seam -->
        <div class="px-fade" :style="{ opacity: fadeOpacity }"></div>
      </div>

      <!-- file thumbnail (positioned outside window so it can escape bounds) -->
      <div class="px-file-thumb"
           :style="{
             left: dragX + '%',
             top: dragY + '%',
             opacity: thumbOpacity,
             transform: `translate(-50%, -22%) scale(${thumbScale})`
           }">
        <canvas ref="thumbCanvas" class="px-thumb-preview" width="216" height="128"></canvas>
        <div class="px-thumb-foot">
          <span class="px-thumb-name">original.mp4</span>
        </div>
      </div>

      <!-- macOS grabbing hand ("Mickey Mouse glove") cursor shown while dragging -->
      <svg class="px-mac-cursor"
           :style="{ left: dragX + '%', top: dragY + '%', opacity: thumbOpacity }"
           viewBox="0 0 26 26" width="24" height="24">
        <path fill="white" stroke="#1a1a1a" stroke-width="1" stroke-linejoin="round"
              d="M6.5 12.2c0-0.9 0.45-1.6 1.35-1.6 0.6 0 1.05 0.35 1.3 0.85 0-1.1 0.5-1.85 1.45-1.85 0.85 0 1.35 0.65 1.45 1.55 0.1-0.95 0.6-1.55 1.45-1.55 0.9 0 1.4 0.7 1.45 1.7 0.15-0.7 0.6-1.1 1.3-1.1 0.9 0 1.45 0.7 1.45 1.85l0 4.3c0 3-2.1 5.3-5.5 5.3l-1.4 0c-2 0-3.2-0.9-4.3-2.5l-2-3c-0.55-0.85-0.3-1.75 0.5-2.2 0.7-0.4 1.55-0.15 2.05 0.55l0.95 1.3z"/>
      </svg>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useData } from 'vitepress'

// reactive color scheme (VitePress) — lets us hot-swap the clip on theme toggle
const { isDark } = useData()

const props = defineProps({
  // 'blur'  — processing/ready shows a soft blurred frame (default)
  // 'pixel' — processing/ready shows the classic heavy pixelation
  idleStyle: { type: String, default: 'blur' },
  // source clip per color scheme; resolved once on mount (not reactive to
  // theme toggling). srcDark falls back to srcLight when omitted.
  srcLight: { type: String, default: '/blobs-light-540.mp4' },
  srcDark:  { type: String, default: '/blobs-dark-540.mp4' },
})

// pick the clip for the current color scheme
function resolveSrc() {
  return (isDark.value && props.srcDark) ? props.srcDark : props.srcLight
}

const canvas = ref(null)
const video = ref(null)
const thumbCanvas = ref(null)
const fill = ref(null)
const res = ref(null)

// existing refs
const phase = ref('idle')          // idle | dragging | dropping | processing | ready | pressed | playing | hold
const btnOpacity = ref(0)          // pause control auto-hides during playback
const fadeOpacity = ref(0)         // black overlay at the loop seam
const blurAmount = ref(0)          // CSS blur on canvas; non-zero = blurry idle state
const spinnerOpacity = ref(0)      // drives crossfade: spinner out / button in
const blockFrame = ref(0)          // frame counter shown in processing spinner
const blockKbps = ref(4800)        // kbps shown in processing spinner

// new drag/drop refs
const dragX = ref(115)             // cursor position as % of drag-wrap width
const dragY = ref(90)              // cursor position as % of drag-wrap height
const thumbOpacity = ref(0)        // file thumbnail opacity
const thumbScale = ref(1)          // file thumbnail scale
const dropzoneOpacity = ref(0)     // drop zone overlay opacity
const fieldOpacity = ref(0)        // inner rect: hidden until the file is over it
const dropActive = ref(false)      // true = drop zone highlight (solid border)
const hudVisible = ref(false)      // false during intro phases, hides px-res
const barVisible = ref(false)      // progress bar only visible while the video plays

// macro-block spinner: a comet that snakes through the grid. Brightness is
// computed per cell from its distance behind the head, so there's no CSS
// transition toggling (which was skipping fades and looking like flicker).
const SPIN_TRAIL = 4
function cellColor(dom) {
  const row = Math.floor(dom / 4), col = dom % 4
  // sequence position of this cell along the snake path (reverse on odd rows)
  const seq = row * 4 + (row % 2 === 0 ? col : 3 - col)
  const head = ((blockFrame.value % 16) + 16) % 16
  const d = (head - seq + 16) % 16          // steps behind the head
  const a = d < SPIN_TRAIL ? 0.95 - (d / SPIN_TRAIL) * 0.8 : 0.15
  return `rgba(255,255,255,${a.toFixed(3)})`
}

// pause icon shows from the press onward (through playback + hold)
const showPause = computed(() =>
  phase.value === 'pressed' || phase.value === 'playing' || phase.value === 'hold')

let raf = null

// --- timeline (ms) ---
const labels = ['144p', '240p', '480p', '720p', '1080p', '2160p']
const BLOCKS = [56, 32, 18, 9, 3, 0] // pixelation factor per resolution (higher = chunkier)

// durations (keep existing)
const PROCESS = 2600
const READY = 1000
const PRESS = 500
const BEAT = 1100
const PLAYING = labels.length * BEAT  // 6600
const HOLD = 2000
const HIDE_AFTER = 1300
const HIDE_DUR = 600
const FADE_OUT = 450
const FADE_IN = 450
const PROCESS_BLOCKS = 72
const SPINNER_FADE = 400

// NEW intro durations
const IDLE_DUR  = 800
const DRAG_DUR  = 1800
const DROP_DUR  = 500
const INTRO = IDLE_DUR + DRAG_DUR + DROP_DUR   // 3100

// NEW absolute boundaries (replace old READY_END / PRESS_END / PLAY_END / TOTAL)
const IDLE_END  = IDLE_DUR                   // 800
const DRAG_END  = IDLE_END + DRAG_DUR        // 2600
const DROP_END  = INTRO                      // 3100
const PROC_END  = INTRO + PROCESS            // 5700
const READY_END = PROC_END + READY           // 6700
const PRESS_END = READY_END + PRESS          // 7200
const PLAY_END  = PRESS_END + PLAYING        // 13800
const TOTAL     = PLAY_END + HOLD            // 15800

// progress bar: fills continuously across playing + hold, reaching 100%
// exactly when the closing fade-out begins (TOTAL - FADE_OUT)
const BAR_START = PRESS_END                  // 7200
const BAR_END   = TOTAL - FADE_OUT           // 15350
const BAR_SPAN  = BAR_END - BAR_START        // 8150

function easeInOutCubic(x) {
  return x < 0.5 ? 4*x*x*x : 1 - Math.pow(-2*x+2, 3)/2
}

onMounted(() => {
  const cv = canvas.value
  const ctx = cv.getContext('2d')
  const W = cv.width, H = cv.height
  const vid = video.value

  const tcv = thumbCanvas.value
  const tctx = tcv.getContext('2d')
  let thumbPainted = false
  let everPainted = false

  // the clip must cover the playback window (pressed → end of hold). If it's
  // shorter, slow it down (playbackRate < 1) so it stretches to fill the window
  // instead of looping; if it's long enough, leave it at normal speed.
  // +4% so a stretched clip ends just past the loop seam — still in motion when
  // the fade covers it, never freezing/looping a hair early
  const PLAYBACK_WINDOW = ((TOTAL - PRESS_END) / 1000) * 1.04   // seconds to fill
  const fitPlayback = () => {
    const d = vid.duration
    if (d && isFinite(d) && d < PLAYBACK_WINDOW) {
      // browsers clamp playbackRate to ~0.0625 minimum
      vid.playbackRate = Math.max(0.0625, d / PLAYBACK_WINDOW)
    } else {
      vid.playbackRate = 1
    }
  }

  let lastPhase = null

  const tryPlay = () => { fitPlayback(); const p = vid.play(); if (p && p.catch) p.catch(() => {}) }
  // load + decode the first frame, but stay paused & rewound until "play"
  vid.addEventListener('loadedmetadata', fitPlayback)
  // when a clip finishes loading: resume if we're in a playback phase (e.g. a
  // theme hot-swap mid-play), otherwise stay paused & rewound to the first frame
  vid.addEventListener('loadeddata', () => {
    const playing = phase.value === 'pressed' || phase.value === 'playing' || phase.value === 'hold'
    if (playing) tryPlay()
    else { vid.pause(); try { vid.currentTime = 0 } catch (e) {} }
  })
  // resolve the per-scheme clip, then kick off loading
  const loadSrc = () => {
    const next = resolveSrc()
    if (vid.src.endsWith(next)) return
    vid.src = next
    vid.load()
    // force the phase logic to re-apply play/pause for the current phase once
    // the new clip is ready (loadeddata pauses + rewinds it)
    lastPhase = null
    // repaint the dragged thumbnail from the new clip's first frame
    thumbPainted = false
  }
  loadSrc()
  // hot-swap on theme toggle; last painted frame stays up while it buffers
  watch(isDark, loadSrc)

  const videoReady = () => vid.readyState >= 2 && vid.videoWidth > 0

  // paint the video's first frame into the dragged file thumbnail (cover-fit)
  function paintThumb() {
    const vw = vid.videoWidth, vh = vid.videoHeight
    if (!vw || !vh) return
    const tw = tcv.width, th = tcv.height
    const targetAR = tw / th, videoAR = vw / vh
    let sx, sy, sw, sh
    if (videoAR > targetAR) { sh = vh; sw = vh * targetAR; sx = (vw - sw) / 2; sy = 0 }
    else { sw = vw; sh = vw / targetAR; sx = 0; sy = (vh - sh) / 2 }
    tctx.drawImage(vid, sx, sy, sw, sh, 0, 0, tw, th)
    thumbPainted = true
  }

  // draw the video into the canvas with cover-fit (crop to fill, no stretch)
  function paintVideo() {
    const vw = vid.videoWidth, vh = vid.videoHeight
    const targetAR = W / H, videoAR = vw / vh
    let sx, sy, sw, sh
    if (videoAR > targetAR) { sh = vh; sw = vh * targetAR; sx = (vw - sw) / 2; sy = 0 }
    else { sw = vw; sh = vw / targetAR; sx = 0; sy = (vh - sh) / 2 }
    ctx.drawImage(vid, sx, sy, sw, sh, 0, 0, W, H)
  }

  // brand-tinted fallback frame while the clip buffers
  function paintSource(t) {
    const g = ctx.createLinearGradient(0, 0, W, H)
    g.addColorStop(0, '#ff6100'); g.addColorStop(1, '#fa50b5')
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H)
    const blobs = [
      [0.30, 0.35, 150, 'rgba(255,255,255,.55)'],
      [0.72, 0.62, 190, 'rgba(120,0,90,.45)'],
      [0.55, 0.20, 110, 'rgba(255,210,120,.5)'],
    ]
    for (const [px, py, r, c] of blobs) {
      const cx = px * W + Math.sin(t / 1400 + px * 9) * 40
      const cy = py * H + Math.cos(t / 1600 + py * 9) * 30
      const rg = ctx.createRadialGradient(cx, cy, 0, cx, cy, r)
      rg.addColorStop(0, c); rg.addColorStop(1, 'rgba(255,255,255,0)')
      ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H)
    }
  }

  const off = document.createElement('canvas')
  off.width = W; off.height = H
  const octx = off.getContext('2d')

  function frame(now) {
    const t = now % TOTAL
    let blocks = 0, label = '', barP = 0, ph

    // global fade (black overlay). Hold full black for SEAM ms on either side of
    // the loop wrap so the seam is guaranteed opaque — no last/first video frame
    // ever peeks through at begin/end.
    const SEAM = 70
    fadeOpacity.value =
      t > TOTAL - FADE_OUT ? Math.min(1, (t - (TOTAL - FADE_OUT)) / (FADE_OUT - SEAM))
      : t < FADE_IN ? Math.max(0, 1 - Math.max(0, t - SEAM) / (FADE_IN - SEAM))
      : 0

    if (t < IDLE_END) {
      ph = 'idle'
      // opaque immediately so the video frame never bleeds through at the
      // start of the loop / on restart (the inner rect still fades in later)
      dropzoneOpacity.value = 1
      thumbOpacity.value = 0
      fieldOpacity.value = 0
      dropActive.value = false
      spinnerOpacity.value = 0; btnOpacity.value = 0
      blurAmount.value = 0; hudVisible.value = false

    } else if (t < DRAG_END) {
      ph = 'dragging'
      const dt = (t - IDLE_END) / DRAG_DUR
      const ease = easeInOutCubic(dt)
      dragX.value = 115 + ease * (50 - 115)
      dragY.value = 90  + ease * (48 - 90)
      thumbOpacity.value = Math.min(1, dt * 6)
      thumbScale.value = 1
      dropzoneOpacity.value = 1
      // inner rect fades in as the file arrives over it, then activates
      fieldOpacity.value = Math.min(1, Math.max(0, (dt - 0.5) / 0.2))
      dropActive.value = dt > 0.8
      spinnerOpacity.value = 0; btnOpacity.value = 0
      blurAmount.value = 0; hudVisible.value = false

    } else if (t < DROP_END) {
      ph = 'dropping'
      const dt = (t - DRAG_END) / DROP_DUR
      thumbOpacity.value = Math.max(0, 1 - dt * 2.5)
      thumbScale.value = 1 - dt * 0.25
      fieldOpacity.value = 1
      dropActive.value = dt < 0.35
      dropzoneOpacity.value = Math.max(0, 1 - dt * 2.2)
      spinnerOpacity.value = 0; btnOpacity.value = 0
      hudVisible.value = false
      // pre-apply the processing pixelation/blur so the canvas revealed behind
      // the fading field is already degraded — no sharp-frame flash before processing
      const useBlur = props.idleStyle === 'blur'
      blocks = useBlur ? 0 : PROCESS_BLOCKS
      blurAmount.value = useBlur ? 12 : 0

    } else {
      const tp = t - INTRO   // local time within post-intro section
      thumbOpacity.value = 0
      dropzoneOpacity.value = 0
      fieldOpacity.value = 0
      dropActive.value = false
      hudVisible.value = true

      const useBlur = props.idleStyle === 'blur'

      if (t < PROC_END) {
        ph = 'processing'
        blocks = useBlur ? 0 : PROCESS_BLOCKS
        label = 'Processing'; barP = 0
        blurAmount.value = useBlur ? 12 : 0
        blockFrame.value = Math.floor(tp * 11 / 1000)
        blockKbps.value = Math.round(4200 + 600 * (0.5 + 0.5 * Math.sin(tp / 200 + 1.3)))
        spinnerOpacity.value = tp < PROCESS - SPINNER_FADE
          ? 1 : 1 - (tp - (PROCESS - SPINNER_FADE)) / SPINNER_FADE
        btnOpacity.value = 0

      } else if (t < READY_END) {
        ph = 'ready'
        blocks = useBlur ? 0 : PROCESS_BLOCKS
        label = 'Ready'; barP = 0
        blurAmount.value = useBlur ? 12 : 0
        spinnerOpacity.value = 0
        btnOpacity.value = Math.min(1, (tp - PROCESS) / SPINNER_FADE)

      } else if (t < PRESS_END) {
        ph = 'pressed'
        blocks = PROCESS_BLOCKS; label = 'Ready'; barP = 0
        blurAmount.value = 0
        spinnerOpacity.value = 0; btnOpacity.value = 1

      } else if (t < PLAY_END) {
        ph = 'playing'
        const rt = t - PRESS_END
        const step = Math.min(labels.length - 1, Math.floor(rt / BEAT))
        blocks = BLOCKS[step]; label = labels[step]
        btnOpacity.value = rt < HIDE_AFTER ? 1 : Math.max(0, 1 - (rt - HIDE_AFTER) / HIDE_DUR)
        blurAmount.value = 0; spinnerOpacity.value = 0

      } else {
        ph = 'hold'
        blocks = 0; label = labels[labels.length - 1]
        btnOpacity.value = 0; blurAmount.value = 0; spinnerOpacity.value = 0
      }
    }

    // progress bar only shows while the clip is actually playing (playing + hold),
    // and reaches 100% exactly as the closing fade-out starts
    barVisible.value = ph === 'playing' || ph === 'hold'
    barP = barVisible.value
      ? Math.min(1, Math.max(0, (t - BAR_START) / BAR_SPAN))
      : 0

    // video playback control
    if (ph !== lastPhase) {
      // start playback the moment the pause icon appears (pressed) so there's no
      // gap where the pause control shows but the clip isn't rolling yet
      if (ph === 'pressed' || ph === 'playing' || ph === 'hold') tryPlay()
      else {
        vid.pause()
        // only rewind at the loop start (idle) — it's hidden by the black fade.
        // rewinding on 'processing' caused a seek that briefly dropped readyState
        // and flashed the gradient fallback for one frame.
        if (ph === 'idle') { try { vid.currentTime = 0 } catch(e){} }
      }
      lastPhase = ph
    }
    phase.value = ph

    // keep the dragged thumbnail showing the clip's first frame
    if (!thumbPainted && videoReady()) paintThumb()

    // canvas — once a real frame has been drawn, never fall back to the bright
    // gradient (a momentary readyState dip during a seek would flash it); keep
    // the last good frame instead.
    if (videoReady()) { paintVideo(); everPainted = true }
    else if (!everPainted) paintSource(now)
    octx.drawImage(cv, 0, 0)
    ctx.imageSmoothingEnabled = false
    if (blocks > 1) {
      const sw = Math.max(2, Math.round(W / blocks))
      const sh = Math.max(2, Math.round(H / blocks))
      ctx.drawImage(off, 0, 0, W, H, 0, 0, sw, sh)
      ctx.drawImage(cv, 0, 0, sw, sh, 0, 0, W, H)
    }
    ctx.imageSmoothingEnabled = true

    if (fill.value) fill.value.style.width = (barP * 100).toFixed(1) + '%'
    if (res.value) res.value.textContent = label
    raf = requestAnimationFrame(frame)
  }
  raf = requestAnimationFrame(frame)
})

onBeforeUnmount(() => cancelAnimationFrame(raf))
</script>

<style scoped>
/* Stage and window structure */
.px-stage { display: flex; align-items: center; justify-content: center; width: 100%; }
.px-drag-wrap {
  position: relative;
  width: min(420px, 80vw);
  aspect-ratio: 16 / 10;
}
.px-window {
  position: absolute;
  inset: 0;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 30px 60px -20px rgba(250, 80, 181, .45), 0 0 0 1px rgba(255,255,255,.08);
}

.px-src { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.px-canvas { display: block; width: 100%; height: 100%; }
.px-scrim { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.35), transparent 45%); }

/* Drop zone */
/* drop zone — styled after the Statamic assets fieldtype empty state */
.px-dropzone {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  padding: 2em;
  /* opaque page background so the video frame never shows through */
  background: var(--vp-c-bg);
  pointer-events: none;
  font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
}
.px-field {
  display: flex; align-items: center; justify-content: center;
  width: 100%; height: 100%;
  border: 1px solid var(--vp-c-divider);
  border-radius: 12px;
  background: var(--vp-c-bg-soft);
  color: var(--vp-c-text-3, var(--vp-c-text-2));
  transition: border-color 0.14s, background 0.14s, color 0.14s;
}
.px-dropzone--active .px-field {
  border-color: var(--vp-c-brand-1, #ff6100);
  border-style: dashed;
  background: var(--vp-c-bg);
  color: var(--vp-c-brand-1, #ff6100);
}

/* File thumbnail */
.px-file-thumb {
  position: absolute;
  width: 108px;
  background: #ececec;
  border-radius: 7px;
  overflow: hidden;
  box-shadow: 0 6px 20px rgba(0,0,0,0.38), 0 0 0 0.5px rgba(0,0,0,0.14);
  pointer-events: none;
  z-index: 20;
  transform-origin: 50% 20%;
}
.px-thumb-preview {
  display: block;
  width: 108px; height: 64px;
  background: #000;
}
.px-thumb-foot {
  padding: 5px 7px 6px;
  display: flex; align-items: center;
  background: #ececec;
}
.px-thumb-name {
  font: 12px ui-monospace, 'SF Mono', 'Menlo', monospace;
  color: #1a1a1a;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Cursor */
.px-mac-cursor {
  position: absolute;
  pointer-events: none;
  z-index: 21;
  transform: translate(-35%, -22%);
  filter: drop-shadow(0 1px 3px rgba(0,0,0,0.45));
}

/* centered control wrapper handles positioning; children handle scale */
.px-center {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
  display: grid; place-items: center;
}

/* block+counter and play button share one grid cell so they can crossfade */
.px-center { grid-template-areas: 's'; }
.px-blockcount, .px-btn { grid-area: s; }

.px-blockcount { display: flex; align-items: center; gap: 10px; pointer-events: none; }
.px-bc-grid {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 3px; width: 52px; height: 52px; flex-shrink: 0;
}
.px-bc-cell {
  border-radius: 0;
  background: rgba(255,255,255,.15);
  transition: background 90ms linear;
}
.px-bc-stats {
  font: 600 10px ui-monospace, monospace; letter-spacing: .03em;
  color: #fff; display: flex; flex-direction: column; gap: 4px;
}
.px-bc-row { display: flex; justify-content: space-between; gap: 10px; }
.px-bc-dim { color: rgba(255,255,255,.45); }

.px-btn {
  position: relative;
  width: 76px; height: 76px; border-radius: 999px; border: 0; cursor: default;
  /* liquid glass: dark frosted fill (darker than bg) + white icon */
  background: rgba(0, 0, 0, 0.3);
  -webkit-backdrop-filter: blur(10px) saturate(1.3);
  backdrop-filter: blur(10px) saturate(1.3);
  color: #fff;
  display: grid; place-items: center;
  box-shadow:
    inset 0 1px 1px rgba(255, 255, 255, 0.22),
    inset 0 -2px 5px rgba(0, 0, 0, 0.18),
    0 10px 30px rgba(0, 0, 0, 0.28);
}
/* offset gradient rim / shimmer on the edge */
.px-btn::before {
  content: ''; position: absolute; inset: 0; border-radius: inherit;
  padding: 1px; pointer-events: none;
  background: linear-gradient(135deg,
    rgba(255,255,255,0.45) 0%,
    rgba(255,255,255,0.08) 40%,
    rgba(255,255,255,0) 58%,
    rgba(255,255,255,0.25) 100%);
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
          mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
}
.px-btn svg { fill: currentColor; filter: drop-shadow(0 1px 2px rgba(0,0,0,.25)); }
.px-btn:not(.paused) svg { margin-left: 3px; }      /* optical centering for the play triangle */
.px-btn.ready { animation: px-pulse 2.4s ease-in-out infinite; }
.px-btn.pressing { animation: px-press .42s cubic-bezier(.3,1.4,.5,1) both; }
@keyframes px-pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
@keyframes px-press { 0% { transform: scale(1); } 35% { transform: scale(.82); } 100% { transform: scale(1); } }

.px-bar { position: absolute; left: 14px; right: 14px; bottom: 14px; height: 2.5px; border-radius: 999px; background: rgba(255,255,255,.25); overflow: hidden; }
.px-fill { display: block; height: 100%; width: 0; background: #fff; border-radius: 999px; }
.px-fade { position: absolute; inset: 0; background: #000; pointer-events: none; z-index: 5; }
/* light mode: fade to white (matches the white page background at the seam) */
html:not(.dark) .px-fade { background: #fff; }
/* light mode: lighter, frosted-white play button with a dark icon */
html:not(.dark) .px-btn {
  background: rgba(255, 255, 255, 0.15);
  box-shadow:
    inset 0 1px 1px rgba(255, 255, 255, 0.22),
    inset 0 -2px 5px rgba(0, 0, 0, 0.1),
    0 4px 12px rgba(0, 0, 0, 0.12);
}
html:not(.dark) .px-btn svg { filter: none; }
/* light mode: drop the dark bottom gradient behind the progress bar */
html:not(.dark) .px-scrim { background: none; }
/* light mode: lift video brightness a touch (on the canvas only, so it doesn't
   wash out the drop zone's soft-gray background) */
html:not(.dark) .px-canvas { filter: brightness(1.08); }
/* light mode: subtle outer border tucked behind the video edge */
html:not(.dark) .px-window {
  box-shadow: 0 30px 60px -20px rgba(250, 80, 181, .28), 0 0 0 1px var(--vp-c-divider);
}
.px-res {
  position: absolute; top: 12px; right: 12px;
  font: 600 11px ui-monospace, monospace; letter-spacing: .04em;
  color: #fff; background: rgba(0,0,0,.45); padding: 3px 8px; border-radius: 6px;
  backdrop-filter: blur(4px);
}
</style>
