# `mux:id` <Badge type="info">Antlers Tag</Badge>

Get the video's Mux id, required for fetching info about it from the Mux API.
This is rather useless on its own: what you probably want is the playback id.

```antlers
{{ mux:id src="assets::video.mp4" }}
```

::: code-group

```text [Output]
8jd7M77xQgf2NzuocJRPYdSdEfY5dLlcRwFARtgQqU4
```

:::
