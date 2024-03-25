# `mux:gif` <Badge type="info">Antlers Tag</Badge>

Generate an [animated gif](https://docs.mux.com/guides/get-images-from-a-video#get-an-animated-gif-from-a-video)
version of the video. By default, this will extract a five-second timeline in 15fps from the beginning of the
video and render it in scaled-down thumbnail size.

```antlers
<img src="{{ mux:gif src="assets::video.mp4" }}">
```

::: code-group

```html [Output]
<img src="https://image.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc/animated.gif">
```

:::

## Customize size and timeline

You can customize the framerate, width and height, as well as the start and end of the used video portion, e.g.
to render a two-second, 500px-wide animation in 30fps:

```antlers
<img src="{{ mux:gif src="assets::video.mp4" width="500" start="3" end="5" fps="30" }}">
```

```html
<img src="https://image.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc/animated.gif?width=500&start=3&end=5&fps=30">
```
