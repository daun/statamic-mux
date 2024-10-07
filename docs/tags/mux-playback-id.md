# `mux:playback_id` <Badge type="info">Antlers Tag</Badge>

Get the video's playback id, required for streaming videos from Mux. While that's all you
need to create your own player, you can use the provided [`mux:video`](mux-video) and
[`mux:player`](mux-player) tags to render full-fledged video player components.

```antlers
{{ mux:playback_id src="assets::video.mp4" }}
```

::: code-group

```text [Output]
85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc
```

:::
