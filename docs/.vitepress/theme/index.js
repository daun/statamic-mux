// https://vitepress.dev/guide/custom-theme
import DefaultTheme from 'vitepress/theme'

// import Layout from './Layout.vue'
import './style.css'

/** @type {import('vitepress').Theme} */
export default {
  extends: DefaultTheme,
  Layout: DefaultTheme.Layout
}
