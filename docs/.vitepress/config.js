import { defineConfig } from 'vitepress'

import { version } from '../../package.json'
import antlers from './grammars/antlers.json' assert { type: 'json' }

const title = 'Statamic Mux'
const description = 'Seamless video encoding and streaming for Statamic sites'
const hostname = 'https://statamic-mux.daun.ltd'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title,
  description,
  cleanUrls: true,

  // https://vitepress.dev/reference/default-theme-config
  themeConfig: {
    nav: [
      { text: 'Docs', link: '/introduction' },
      { text: 'Releases', link: 'https://github.com/daun/statamic-mux/releases' },
      // { text: `v${version}`, link: '/' },
    ],

    sidebar: [
      { text: 'Introduction', link: '/introduction' },
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/installation' },
          { text: 'Connecting Mux', link: '/connecting-mux' },
          { text: 'Uploading Videos', link: '/upload' },
          { text: 'Displaying Videos', link: '/display' },
        ]
      },
      {
        text: 'Details',
        items: [
          {
            text: 'Antlers Tags',
            link: '/tags',
            collapsed: true,
            items: [
              { text: 'mux', link: '/tags/mux' },
              { text: 'mux:video', link: '/tags/mux-video' },
              { text: 'mux:player', link: '/tags/mux-player' },
              { text: 'mux:thumbnail', link: '/tags/mux-thumbnail' },
              { text: 'mux:placeholder', link: '/tags/mux-placeholder' },
              { text: 'mux:gif', link: '/tags/mux-gif' },
              { text: 'mux:id', link: '/tags/mux-id' },
              { text: 'mux:playback_id', link: '/tags/mux-playback-id' },
              { text: 'mux:playback_url', link: '/tags/mux-playback-url' },
            ]
          },
          {
            text: 'Artisan Commands',
            link: '/commands',
            collapsed: true,
            items: [
              { text: 'mux:upload', link: '/commands/mux-upload' },
              { text: 'mux:prune', link: '/commands/mux-prune' },
              { text: 'mux:mirror', link: '/commands/mux-mirror' },
            ],
          },
          { text: 'Configuration', link: '/configuration' },
          { text: 'Secure Playback', link: '/secure-playback' },
          { text: 'Events', link: '/events' },
        ]
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/daun/statamic-mux' }
    ],

    footer: {
      message: 'Unlicensed commercial plugin',
      copyright: `
        Copyright © 2024-present <a href="https://github.com/daun">Philipp Daun</a><br><br>
        <small>Mux is a registered trademark of <a href="https://www.mux.com">Mux, Inc</a>.</small>
        <small>The use of its name is solely for descriptive purposes to denote compatibility with their services.</small>
      `,
    },

    docFooter: {
      next: 'Next →',
      prev: '← Previous',
    },

    head: [
      ['meta', { property: 'og:locale', content: 'en_US'}],
      ['meta', { property: 'og:type', content: 'website'}],
      ['meta', { property: 'og:site_name', content: title}],
      ['meta', { property: 'og:title', content: title}],
      ['meta', { property: 'og:description', content: description}],
      ['meta', { property: 'og:url', content: hostname}],
      ['meta', { property: 'og:image', content: `${hostname}/og.png`}],
      ['meta', { property: 'og:image:width', content: '1200'}],
      ['meta', { property: 'og:image:height', content: '630'}],
      ['meta', { property: 'og:image:type', content: 'image/png'}],
      ['meta', { property: 'twitter:card', content: 'summary_large_image'}],
      ['meta', { property: 'twitter:title', content: title}],
      ['meta', { property: 'twitter:description', content: description}],
      ['meta', { property: 'twitter:image', content: `${hostname}/og.png`}],
    ],

    // editLink: {
    //   pattern: 'https://github.com/daun/statamix-mux/edit/main/docs/:path',
    // },

    search: {
      provider: 'local',
      options: {
        detailedView: true
      }
    }
  },
  markdown: {
    languages: [
      { ...antlers, name: 'antlers' }
    ],
    languageAlias: {
      'env': 'ini'
    },
    image: {
      // image lazy loading is disabled by default
      lazyLoading: true
    }
  },
  sitemap: {
    hostname,
  },
})
