<!--
  PROTOTYPE — Variant A: "Pixelate Reveal"
  An abstract video frame starts as chunky pixel blocks and resolves to sharp
  as a progress bar fills, then loops. Conveys: instant playback before full
  encoding completes — clarity arrives as the stream loads.
  Throwaway. Delete when a winner is chosen.
-->
<template>
  <div class="px-stage">
    <div class="px-window">
      <!-- hidden source video; canvas samples + pixelates each frame -->
      <video ref="video" class="px-src" src="/sample-pixelate.mp4"
             muted loop autoplay playsinline preload="auto"></video>
      <canvas ref="canvas" class="px-canvas" width="640" height="400"></canvas>
      <div class="px-scrim"></div>
      <button class="px-play" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="38" height="38"><polygon points="6 4 20 12 6 20" /></svg>
      </button>
      <div class="px-bar"><span ref="fill" class="px-fill"></span></div>
      <div ref="res" class="px-res">240p</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const canvas = ref(null)
const video = ref(null)
const fill = ref(null)
const res = ref(null)
let raf = null

onMounted(() => {
  const cv = canvas.value
  const ctx = cv.getContext('2d')
  const W = cv.width, H = cv.height
  const vid = video.value

  // best-effort autoplay (muted + playsinline allows it)
  const tryPlay = () => { const p = vid.play(); if (p && p.catch) p.catch(() => {}) }
  vid.addEventListener('canplay', tryPlay)
  tryPlay()

  function videoReady() {
    return vid.readyState >= 2 && vid.videoWidth > 0
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

  // Fallback abstract "video frame" while the clip buffers.
  function paintSource(t) {
    const g = ctx.createLinearGradient(0, 0, W, H)
    g.addColorStop(0, '#ff6100')
    g.addColorStop(1, '#fa50b5')
    ctx.fillStyle = g
    ctx.fillRect(0, 0, W, H)
    const blobs = [
      [0.30, 0.35, 150, 'rgba(255,255,255,.55)'],
      [0.72, 0.62, 190, 'rgba(120,0,90,.45)'],
      [0.55, 0.20, 110, 'rgba(255,210,120,.5)'],
    ]
    for (const [px, py, r, c] of blobs) {
      const cx = px * W + Math.sin(t / 1400 + px * 9) * 40
      const cy = py * H + Math.cos(t / 1600 + py * 9) * 30
      const rg = ctx.createRadialGradient(cx, cy, 0, cx, cy, r)
      rg.addColorStop(0, c)
      rg.addColorStop(1, 'rgba(255,255,255,0)')
      ctx.fillStyle = rg
      ctx.fillRect(0, 0, W, H)
    }
  }

  // offscreen buffer to sample low-res from
  const off = document.createElement('canvas')
  off.width = W; off.height = H
  const octx = off.getContext('2d')

  const CYCLE = 4200
  const labels = ['144p', '240p', '480p', '720p', '1080p']

  function frame(now) {
    const t = now % CYCLE
    const p = t / CYCLE // 0..1 progress
    // ease the resolution: few big steps
    const step = Math.min(labels.length - 1, Math.floor(p * labels.length))
    // pixel size shrinks as we progress (32 -> 1)
    const blocks = Math.max(1, Math.round(80 * Math.pow(1 - p, 2.2)) + 1)

    if (videoReady()) paintVideo()
    else paintSource(now)
    // copy to offscreen, then draw downscaled-up for pixelation
    octx.drawImage(cv, 0, 0)
    ctx.imageSmoothingEnabled = false
    if (blocks > 1) {
      const sw = Math.max(2, Math.round(W / blocks))
      const sh = Math.max(2, Math.round(H / blocks))
      ctx.drawImage(off, 0, 0, W, H, 0, 0, sw, sh)
      ctx.drawImage(cv, 0, 0, sw, sh, 0, 0, W, H)
    }
    ctx.imageSmoothingEnabled = true

    if (fill.value) fill.value.style.width = (p * 100).toFixed(1) + '%'
    if (res.value) res.value.textContent = labels[step]
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
.px-play {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
  width: 72px; height: 72px; border-radius: 999px; border: 0; cursor: default;
  background: rgba(255,255,255,.92); color: #18181b;
  display: grid; place-items: center;
  box-shadow: 0 8px 30px rgba(0,0,0,.3);
  animation: px-pulse 2.6s ease-in-out infinite;
}
.px-play svg { fill: currentColor; margin-left: 3px; }
@keyframes px-pulse { 0%,100% { transform: translate(-50%,-50%) scale(1); } 50% { transform: translate(-50%,-50%) scale(1.08); } }
.px-bar { position: absolute; left: 14px; right: 14px; bottom: 14px; height: 5px; border-radius: 999px; background: rgba(255,255,255,.25); overflow: hidden; }
.px-fill { display: block; height: 100%; width: 0; background: #fff; border-radius: 999px; }
.px-res {
  position: absolute; top: 12px; right: 12px;
  font: 600 11px ui-monospace, monospace; letter-spacing: .04em;
  color: #fff; background: rgba(0,0,0,.45); padding: 3px 8px; border-radius: 6px;
  backdrop-filter: blur(4px);
}
</style>
