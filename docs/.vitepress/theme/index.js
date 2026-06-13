// https://vitepress.dev/guide/custom-theme
import { h } from 'vue'
import DefaultTheme from 'vitepress/theme'
import './style.css'
import HeroPlayer from './components/HeroPlayer.vue'

/** @type {import('vitepress').Theme} */
export default {
  extends: DefaultTheme,
  Layout: () => {
    return h(DefaultTheme.Layout, null, {
      // animated hero in place of the static image
      'home-hero-image': () => h(HeroPlayer),
    })
  },
  enhanceApp({ app, router, siteData }) {
    // ...
  }
}
