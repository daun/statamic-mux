# `mux:embed` <Badge type="info">Antlers Tag</Badge>

Render an iframe to embed a Mux Player. While the official web components provide a better viewer
experience, they require component scripts. This tag offers a simple script-free alternative for
embedding videos. See the [Mux Player docs](https://www.mux.com/docs/guides/mux-player-web#html-embed)
for customization options.

```antlers
{{ mux:embed src="assets::video.mp4" }}
```

::: code-group

```html [Output]
<iframe
  src="https://player.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"
  width="1920"
  height="1080"
></iframe>
```

:::

## Custom attributes

Any other attributes will be passed along as query params to allow for customization. Learn more
about [customizing the look and feel of Mux Player](https://docs.mux.com/guides/player-customize-look-and-feel).

```antlers
{{ mux:embed src="assets::video.mp4" primary-color="#075389" start-time="10" }}
```

```html
<iframe
  src="https://player.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc?primary_color=%23075389&start_time=10"
  width="1920"
  height="1080"
></iframe>
```

## Customizing the view

<!--@include: ../partials/vendor-views.md-->
