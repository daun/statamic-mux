# `mux:placeholder` <Badge type="info">Antlers Tag</Badge>

Generate a small blurry placeholder image than can be displayed while the video is loading.
Uses [Thumbhash](https://evanw.github.io/thumbhash/) under the hood and is represented as a data
URI to be inlined as image source. By default, this will pick a frame from the middle of the video.

```antlers
<img src="{{ mux:placeholder src="assets::video.mp4" }}">
```

::: code-group

```html [Output]
<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAASCAYA...">
```

:::

## Customize frame

You can customize the time point of the selected video frame used to generate the placeholder:

```antlers
<img src="{{ mux:placeholder time="0" }}">
```
