import statamicPreset from './vendor/statamic/cms/tailwind.config.js'

export default {
    // prefix: 'seo-',
    presets: [
        statamicPreset
    ],
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {},
    },
    corePlugins: {
        preflight: false,
    },
}
