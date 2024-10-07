# Displaying Videos in Your Frontend

Once uploaded to Mux, your videos are ready to be streamed within a few seconds, thanks to just-in-time encoding.

## Video Components

Mux offers a set of [web components](https://github.com/muxinc/elements) that can be used as drop-in
replacements of the native `video` element. Instead of a source file, they receive a Mux playback id to offer
adaptive bitrate streaming.

### `mux-video`

Extends the native `video` element with support for streaming Mux videos. Attributes like `autoplay`
and `loop` or events like `playing` will continue to work as expected.

```diff
- <video src="/assets/video.mp4" autoplay>
+ <mux-video playback-id="DS00Spx1CV902M" autoplay></mux-video>
```

### `mux-player`

Wraps the native `video` element in a full-fledged customizable video player. Accepts the same attributes
and emits the same events as a video element, but adds lots of useful interactive controls for video
playback.

```diff
- <video src="/assets/video.mp4">
+ <mux-player playback-id="DS00Spx1CV902M"></mux-player>
```

## Antlers Tags

You can use the provided Antlers tags to render the video components and placeholder images.
Learn more about the [available Antlers tags](/tags).

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

## Secure Playback

By default, videos uploaded by this addon are public and can be watched without restrictions. If
your use case requires limiting access to certain users, you can enable [Secure Playback](/secure-playback).
