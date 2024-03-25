# `mux:thumbnail` <Badge type="info">Antlers Tag</Badge>

Generate a [thumbnail image](https://docs.mux.com/guides/get-images-from-a-video#get-an-image-from-a-video)
url of the video. By default, this will pick a frame from the middle of the video and render it in its original size.

```antlers
<img src="{{ mux:thumbnail src="assets::video.mp4" }}">
```

::: code-group

```html [Output]
<img src="https://image.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc/thumbnail.jpg">
```

:::

## Customize size and frame

You can customize the width and height as well as the time point of the selected video frame used to
generate the thumbnail, e.g. to render a 500px-wide image of the first frame:

```antlers
<img src="{{ mux:thumbnail src="assets::video.mp4" width="500" time="0" }}">
```

```html
<img src="https://image.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc/thumbnail.jpg?width=500&time=0">
```
