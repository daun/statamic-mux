<!--
  PROTOTYPE — Hero animation switcher.
  Renders one of 5 throwaway hero animation variants into the VitePress
  home-hero-image slot, switchable via ?variant=A..E and a floating bottom bar.
  Dev-only: the switcher bar is hidden in production builds.
  Question being answered: "Which hero animation best conveys that Mux
  optimizes & streams video for you, lightly and playfully?"
  Delete the losers + this switcher once a direction wins.
-->
<template>
  <div class="hp-root">
    <div class="hp-anim">
      <component :is="current.comp" :key="variant" />
    </div>

    <div v-if="showBar" class="hp-bar">
      <button class="hp-arrow" @click="cycle(-1)" aria-label="Previous variant">‹</button>
      <span class="hp-label">{{ variant }} — {{ current.name }}</span>
      <button class="hp-arrow" @click="cycle(1)" aria-label="Next variant">›</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, shallowRef } from 'vue'
import VariantA from './VariantA_Pixelate.vue'
import VariantB from './VariantB_Bitrate.vue'

const VARIANTS = [
  { key: 'A', name: 'Pixelate Reveal', comp: VariantA },
  { key: 'B', name: 'Bitrate Stream', comp: VariantB },
]

const variant = ref('A')
const showBar = ref(true)

const current = computed(() => VARIANTS.find(v => v.key === variant.value) || VARIANTS[0])

function readUrl() {
  if (typeof window === 'undefined') return
  const v = new URLSearchParams(window.location.search).get('variant')
  if (v && VARIANTS.some(x => x.key === v.toUpperCase())) variant.value = v.toUpperCase()
  // hide bar for clean screenshots: ?bar=0
  if (new URLSearchParams(window.location.search).get('bar') === '0') showBar.value = false
}

function writeUrl() {
  if (typeof window === 'undefined') return
  const url = new URL(window.location.href)
  url.searchParams.set('variant', variant.value)
  window.history.replaceState({}, '', url)
}

function cycle(dir) {
  const i = VARIANTS.findIndex(v => v.key === variant.value)
  variant.value = VARIANTS[(i + dir + VARIANTS.length) % VARIANTS.length].key
  writeUrl()
}

function onKey(e) {
  const tag = (e.target.tagName || '').toLowerCase()
  if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) return
  if (e.key === 'ArrowLeft') { e.preventDefault(); cycle(-1) }
  if (e.key === 'ArrowRight') { e.preventDefault(); cycle(1) }
}

onMounted(() => {
  readUrl()
  window.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<style scoped>
.hp-root { width: 100%; display: flex; align-items: center; justify-content: center; }
.hp-anim { width: 100%; display: flex; align-items: center; justify-content: center; }

.hp-bar {
  position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%);
  z-index: 9999; display: flex; align-items: center; gap: 4px;
  background: #18181b; color: #fff; border-radius: 999px; padding: 5px 6px;
  box-shadow: 0 10px 30px rgba(0,0,0,.35), 0 0 0 1px rgba(255,255,255,.08);
  font: 600 13px ui-sans-serif, system-ui, sans-serif;
}
.hp-arrow {
  width: 30px; height: 30px; border-radius: 999px; border: 0; cursor: pointer;
  background: rgba(255,255,255,.1); color: #fff; font-size: 18px; line-height: 1;
  display: grid; place-items: center; transition: background .15s;
}
.hp-arrow:hover { background: rgba(255,255,255,.22); }
.hp-label { padding: 0 12px; letter-spacing: .01em; white-space: nowrap; }
</style>
