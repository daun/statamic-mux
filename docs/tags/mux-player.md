# `mux:player` <Badge type="info">Antlers Tag</Badge>

Render a custom video player using the [`<mux-player>`](https://www.npmjs.com/package/@mux/mux-player) web component,
which is a highly customizable, themable player for streaming Mux videos. Refer to the official
[Mux Player docs](https://docs.mux.com/guides/mux-player-web) for details on customizing the player.

```antlers
{{ mux:player src="assets::video.mp4" }}
```

::: code-group

```html [Output]
<mux-player
  playback-id="85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"
  width="1920"
  height="1080"
></mux-player>
```

:::

## Including the required scripts

Normally you'd need to load the required web component scripts from a CDN or bundle them on your own.
Setting the tag's `script` attribute will render the required script tag for you, along with the video itself:

```antlers
{{ mux:player src="assets::video.mp4" script="true" }}
```

```html
<mux-player playback-id="85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"></mux-player>
<script async src="https://unpkg.com/@mux/mux-player@3"></script> // [!code focus]
```

## Custom attributes

Any other attributes will be passed along to the the web component itself to allow for customization.
Learn more about [customizing the look and feel of Mux Player](https://docs.mux.com/guides/player-customize-look-and-feel).

```antlers
{{ mux:player src="assets::video.mp4" primary-color="#075389" start-time="10" }}
```

```html
<mux-player
  playback-id="85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"
  primary-color="#075389"
  start-time="10"
></mux-player>
```

## Customizing the view

<!--@include: ../partials/vendor-views.md-->

## Disabling tracking

<!--@include: ../partials/disable-tracking.md-->
