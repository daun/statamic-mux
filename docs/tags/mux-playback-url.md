# `mux:playback_url` <Badge type="info">Antlers Tag</Badge>

Get the video's playback url, a link to the encoded video playlist file. This can be used with any
custom player that supports streaming from a m3u8 playlist file.

```antlers
{{ mux:playback_url src="assets::video.mp4" }}
```

::: code-group

```text [Output]
https://stream.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc.m3u8
```

:::
