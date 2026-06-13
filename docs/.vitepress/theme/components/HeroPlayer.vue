<!--
  Hero animation for the docs home page.

  A looping vignette of the Mux workflow: a freshly uploaded video is briefly
  "processed", becomes ready to play, then streams while its resolution steps
  up from 144p to 2160p — pixelated at first, sharpening as it loads. The frame
  fades to black and the loop restarts.

  Implemented as a canvas that samples a hidden <video> each frame and pixelates
  it via a downscale-then-upscale pass (so any clip works, regardless of aspect).
  The preview clip lives in docs/public/ and can be swapped freely.
-->
<template>
  <div class="px-stage">
    <div class="px-window">
      <!-- hidden source video; canvas samples + pixelates each frame -->
      <video ref="video" class="px-src" src="/sponges.mp4"
             muted loop playsinline preload="auto"></video>
      <canvas ref="canvas" class="px-canvas" width="640" height="400"
             :style="blurAmount > 0 ? { filter: `blur(${blurAmount}px)`, transform: 'scale(1.08)' } : {}"></canvas>
      <div class="px-scrim"></div>

      <!-- center control: spinner + button share the same grid cell and crossfade -->
      <div class="px-center">
        <div class="px-blockcount" :style="{ opacity: spinnerOpacity }">
          <div class="px-bc-grid">
            <div
              v-for="i in 16"
              :key="i"
              class="px-bc-cell"
              :class="{ active: (blockFrame % 16) === (i - 1) }"
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

      <div class="px-bar"><span ref="fill" class="px-fill"></span></div>
      <div ref="res" class="px-res">Processing</div>

      <!-- black fade across the loop seam -->
      <div class="px-fade" :style="{ opacity: fadeOpacity }"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  // 'blur'  — processing/ready shows a soft blurred frame (default)
  // 'pixel' — processing/ready shows the classic heavy pixelation
  idleStyle: { type: String, default: 'blur' },
})

const canvas = ref(null)
const video = ref(null)
const fill = ref(null)
const res = ref(null)

const phase = ref('processing')   // processing | ready | pressed | playing | hold
const btnOpacity = ref(1)         // pause control auto-hides during playback
const fadeOpacity = ref(0)        // black overlay at the loop seam
const blurAmount = ref(12)        // CSS blur on canvas; non-zero = blurry idle state
const spinnerOpacity = ref(1)     // drives crossfade: spinner out / button in
const blockFrame = ref(0)         // frame counter shown in processing spinner
const blockKbps = ref(4800)       // kbps shown in processing spinner
// pause icon shows from the press onward (through playback + hold)
const showPause = computed(() =>
  phase.value === 'pressed' || phase.value === 'playing' || phase.value === 'hold')

let raf = null

// --- timeline (ms) ---
const labels = ['144p', '240p', '480p', '720p', '1080p', '2160p']
const BLOCKS = [56, 32, 18, 9, 3, 0] // pixelation factor per resolution (higher = chunkier)
const HIDE_AFTER = 1300             // ms into playback before the pause control hides
const HIDE_DUR = 600                // fade-out duration of the control
const FADE_OUT = 450                // fade to black at the very end of the loop
const FADE_IN = 450                 // fade from black into the pixelated start
const SPINNER_FADE = 400            // spinner fade-out / button fade-in crossfade duration
const PROCESS_BLOCKS = 72           // heaviest pixelation while processing
const PROCESS = 2600                // spinner / "Processing"
const READY = 1000                  // play button shown, frame still pixelated
const PRESS = 500                   // press -> morph to pause
const BEAT = 1100                   // each resolution step
const PLAYING = labels.length * BEAT
const HOLD = 2000                    // dwell on the sharp frame before reset
const READY_END = PROCESS + READY
const PRESS_END = READY_END + PRESS
const PLAY_END = PRESS_END + PLAYING
const TOTAL = PLAY_END + HOLD

onMounted(() => {
  const cv = canvas.value
  const ctx = cv.getContext('2d')
  const W = cv.width, H = cv.height
  const vid = video.value

  const tryPlay = () => { const p = vid.play(); if (p && p.catch) p.catch(() => {}) }
  // load + decode the first frame, but stay paused & rewound until "play"
  vid.addEventListener('loadeddata', () => { vid.pause(); try { vid.currentTime = 0 } catch (e) {} })

  const videoReady = () => vid.readyState >= 2 && vid.videoWidth > 0
  let lastPhase = null

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
    let blocks, label, barP, ph

    const useBlur = props.idleStyle === 'blur'
    if (t < PROCESS) {
      ph = 'processing'; blocks = useBlur ? 0 : PROCESS_BLOCKS; label = 'Processing'; barP = 0; blurAmount.value = useBlur ? 12 : 0
      blockFrame.value = Math.floor(t * 24 / 1000)
      blockKbps.value = Math.round(4200 + 600 * (0.5 + 0.5 * Math.sin(t / 200 + 1.3)))
      spinnerOpacity.value = t < PROCESS - SPINNER_FADE ? 1 : 1 - (t - (PROCESS - SPINNER_FADE)) / SPINNER_FADE
      btnOpacity.value = 0
    } else if (t < READY_END) {
      ph = 'ready'; blocks = useBlur ? 0 : PROCESS_BLOCKS; label = 'Ready'; barP = 0; blurAmount.value = useBlur ? 12 : 0
      spinnerOpacity.value = 0
      btnOpacity.value = Math.min(1, (t - PROCESS) / SPINNER_FADE)
    } else if (t < PRESS_END) {
      ph = 'pressed'; blocks = PROCESS_BLOCKS; label = 'Ready'; barP = 0; blurAmount.value = 0
      spinnerOpacity.value = 0; btnOpacity.value = 1
    } else if (t < PLAY_END) {
      ph = 'playing'
      const rt = t - PRESS_END
      const step = Math.min(labels.length - 1, Math.floor(rt / BEAT))
      blocks = BLOCKS[step]; label = labels[step]; barP = rt / PLAYING
      btnOpacity.value = rt < HIDE_AFTER ? 1 : Math.max(0, 1 - (rt - HIDE_AFTER) / HIDE_DUR)
      blurAmount.value = 0; spinnerOpacity.value = 0
    } else {
      ph = 'hold'; blocks = 0; label = labels[labels.length - 1]; barP = 1
      btnOpacity.value = 0; blurAmount.value = 0; spinnerOpacity.value = 0
    }

    // paused on frame 0 until playback starts; keeps playing through the hold
    // right up to the reset, then rewinds when we loop back to processing
    if (ph !== lastPhase) {
      if (ph === 'playing' || ph === 'hold') tryPlay()
      else { vid.pause(); if (ph === 'processing') { try { vid.currentTime = 0 } catch (e) {} } }
      lastPhase = ph
    }

    phase.value = ph

    // black fade across the loop seam: out at the tail, in at the head
    fadeOpacity.value =
      t > TOTAL - FADE_OUT ? (t - (TOTAL - FADE_OUT)) / FADE_OUT
      : t < FADE_IN ? 1 - t / FADE_IN
      : 0

    if (videoReady()) paintVideo()
    else paintSource(now)

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
.px-stage { display: flex; align-items: center; justify-content: center; width: 100%; }
.px-window {
  position: relative;
  width: min(420px, 80vw);
  aspect-ratio: 16 / 10;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 30px 60px -20px rgba(250, 80, 181, .45), 0 0 0 1px rgba(255,255,255,.08);
}
.px-src { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.px-canvas { display: block; width: 100%; height: 100%; }
.px-scrim { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.35), transparent 45%); }

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
  transition: background .35s ease-out;
}
.px-bc-cell.active { background: rgba(255,255,255,.95); transition: none; }
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

.px-bar { position: absolute; left: 14px; right: 14px; bottom: 14px; height: 3.3px; border-radius: 999px; background: rgba(255,255,255,.25); overflow: hidden; }
.px-fill { display: block; height: 100%; width: 0; background: #fff; border-radius: 999px; }
.px-fade { position: absolute; inset: 0; background: #000; pointer-events: none; z-index: 5; }
.px-res {
  position: absolute; top: 12px; right: 12px;
  font: 600 11px ui-monospace, monospace; letter-spacing: .04em;
  color: #fff; background: rgba(0,0,0,.45); padding: 3px 8px; border-radius: 6px;
  backdrop-filter: blur(4px);
}
</style>
