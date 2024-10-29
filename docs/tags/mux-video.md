# `mux:video` <Badge type="info">Antlers Tag</Badge>

Render a video player using the [`<mux-video>`](https://www.npmjs.com/package/@mux/mux-video) web component,
which can be used as a direct drop-in for the html video element. A thumbnail image of the video will
be generated automatically and used as video poster.

```antlers
{{ mux:video src="assets::video.mp4" }}
```

::: code-group

```html [Output]
<mux-video
  playback-id="85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"
  poster="https://image.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc/thumbnail.jpg"
  width="1920"
  height="1080"
></mux-video>
```

:::

## Including the required scripts

Normally you'd need to load the required web component scripts from a CDN or bundle them on your own.
Setting the tag's `script` attribute will render the required script tag for you, along with the video itself:

```antlers
{{ mux:video src="assets::video.mp4" script="true" }}
```

```html
<mux-video playback-id="85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"></mux-video>
<script async src="https://unpkg.com/@mux/mux-video@0"></script> // [!code focus]
```

## Usage as background video

Setting the `background` attribute acts as a shortcut for rendering a muted video in looping autoplay
to allow using the video as a background element:

```antlers
{{ mux:video src="assets::video.mp4" background="true" }}
```

```html
<mux-video
  playback-id="85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"
  autoplay loop muted
></mux-video>
```

## Custom attributes

Any other attributes will be passed along to the the web component itself:

```antlers
{{ mux:video src="assets::video.mp4" class="mt-3" }}
```

```html
<mux-video
  playback-id="85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"
  class="mt-3"
></mux-video>
```

## Customizing the view

<!--@include: ../partials/vendor-views.md-->

## Disabling tracking

<!--@include: ../partials/disable-tracking.md-->
