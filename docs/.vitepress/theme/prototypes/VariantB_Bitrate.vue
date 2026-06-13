<!--
  PROTOTYPE — Variant B: "Bitrate Stream"
  An abstract adaptive-bitrate stream: vertical bars flow right-to-left like a
  live signal, their heights swelling and dropping as bandwidth adapts. A
  scanning playhead reads the stream. Conveys: optimized streaming matched to
  each viewer's bandwidth. Built with anime.js + SVG.
  Throwaway.
-->
<template>
  <div class="bs-stage">
    <svg ref="svg" class="bs-svg" viewBox="0 0 420 260" preserveAspectRatio="xMidYMid meet">
      <defs>
        <linearGradient id="bsGrad" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#ff6100" />
          <stop offset="1" stop-color="#fa50b5" />
        </linearGradient>
        <linearGradient id="bsFade" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0" stop-color="#fff" stop-opacity="0" />
          <stop offset="0.12" stop-color="#fff" stop-opacity="1" />
          <stop offset="0.88" stop-color="#fff" stop-opacity="1" />
          <stop offset="1" stop-color="#fff" stop-opacity="0" />
        </linearGradient>
        <mask id="bsMask"><rect x="0" y="0" width="420" height="260" fill="url(#bsFade)" /></mask>
      </defs>
      <g mask="url(#bsMask)">
        <rect v-for="(b, i) in bars" :key="i"
          class="bs-bar"
          :x="b.x" :width="barW" rx="3"
          y="130" height="8"
          fill="url(#bsGrad)" />
      </g>
      <!-- scanning playhead -->
      <line ref="head" class="bs-head" x1="210" y1="20" x2="210" y2="240" stroke="#fff" stroke-width="2" stroke-opacity="0.85" />
      <circle ref="dot" cx="210" cy="130" r="4" fill="#fff" />
    </svg>
    <div class="bs-tags">
      <span>2.1 Mbps</span><span class="bs-live"><i></i>LIVE</span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { animate, utils, createTimer } from 'animejs'

const svg = ref(null)
const head = ref(null)
const dot = ref(null)
const COUNT = 46
const gap = 9
const barW = 5
const bars = ref([])
let timer = null
const anims = []

onMounted(() => {
  // lay out bars across the width
  const total = COUNT * gap
  const startX = (420 - total) / 2
  bars.value = Array.from({ length: COUNT }, (_, i) => ({ x: startX + i * gap }))

  requestAnimationFrame(() => {
    const els = svg.value.querySelectorAll('.bs-bar')
    els.forEach((el, i) => {
      const seed = Math.random()
      const tick = () => {
        // bandwidth-adaptive height: smooth swells + occasional spikes
        const base = 14 + Math.random() * (60 + 60 * Math.sin(i / 5 + seed * 6))
        const h = Math.max(8, Math.min(150, base))
        const y = 130 - h / 2
        anims.push(animate(el, {
          height: h, y,
          duration: 520 + Math.random() * 420,
          ease: 'inOutSine',
          onComplete: tick,
        }))
      }
      // stagger the first kick
      setTimeout(tick, i * 26)
    })

    // playhead sweeps back and forth, reading the stream
    anims.push(animate([head.value, dot.value], {
      x1: [60, 360], x2: [60, 360], cx: [60, 360],
      duration: 3200, ease: 'inOutQuad', loop: true, alternate: true,
    }))
  })
})

onBeforeUnmount(() => { anims.forEach(a => a && a.pause && a.pause()) })
</script>

<style scoped>
.bs-stage {
  position: relative;
  width: 100%;
  max-width: 440px;
  aspect-ratio: 420 / 260;
  display: flex; align-items: center; justify-content: center;
}
.bs-svg { width: 100%; height: 100%; overflow: visible; }
.bs-head { filter: drop-shadow(0 0 6px rgba(255,255,255,.8)); }
.bs-tags {
  position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
  display: flex; gap: 10px; font: 600 11px ui-monospace, monospace; letter-spacing: .04em;
  color: var(--vp-c-text-2);
}
.bs-tags span { display: inline-flex; align-items: center; gap: 5px; }
.bs-live { color: #fa50b5; }
.bs-live i { width: 7px; height: 7px; border-radius: 999px; background: #fa50b5; animation: bs-blink 1.3s steps(1) infinite; }
@keyframes bs-blink { 50% { opacity: .25; } }
</style>
