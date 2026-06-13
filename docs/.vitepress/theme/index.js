// https://vitepress.dev/guide/custom-theme
import { h } from 'vue'
import DefaultTheme from 'vitepress/theme'
import './style.css'
// PROTOTYPE — hero animation variants. Remove with the prototype.
import HeroPrototype from './prototypes/HeroPrototype.vue'

/** @type {import('vitepress').Theme} */
export default {
  extends: DefaultTheme,
  Layout: () => {
    return h(DefaultTheme.Layout, null, {
      // PROTOTYPE: render the switchable hero animation in place of the static image.
      'home-hero-image': () => h(HeroPrototype),
    })
  },
  enhanceApp({ app, router, siteData }) {
    // ...
  }
}
