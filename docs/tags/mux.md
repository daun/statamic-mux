# `mux` <Badge type="info">Antlers Tag</Badge>

Injects the available Mux video data so you can render custom markup.

```antlers
{{ mux src="assets::video.mp4" }}
  <mux-video
    playback-id="{{ playback_id }}"
    poster="{{ thumbnail }}"
  ></mux-video>
  <noscript>
    <img src="{{ gif }}">
  </noscript>
{{ /mux }}
```

::: code-group
```html [Output]
<mux-video
  playback-id="85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc"
  poster="https://image.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc/thumbnail.jpg"
></mux-video>
<noscript>
  <img src="https://image.mux.com/85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc/animated.gif">
</noscript>
```

## Parameters

|Param|Type|Description|
|-----|----|-|
|**`src`** / **`path`** / **`asset`**|`string\|Asset`|The local video asset or its id/path|

## Available data inside the tag pair

|Variable|Type|Description|
|-----|----|-|
|**`thumbnail`**|`string`|The URL to a full-resolution thumbnail image of the video|
|**`placeholder`**|`string`|The data URI of a small blurry placeholder image of the video|
|**`gif`**|`string`|The URL to an animated GIF version of the video|
|**`mux_id`**|`string`|The unique Mux id of the video|
|**`playback_id`**|`string`|A playback id that allows streaming the video|
|**`playback_url`**|`string`|A playback url pointing to an encoded video playlist|
|**`playback_token`**|`string`|A signed playback token to enable secure playback|
|**`public`**|`bool`|Whether the video can be viewed without restrictions|
|**`signed`**|`bool`|Whether the video requires a signed playback url|
