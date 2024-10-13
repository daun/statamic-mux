# GraphQL Types

Any Mux data available in the [Antlers tags](./tags) can also be queried via GraphQL.

## Example

Assuming you've added a [mirror field](/uploading) called `mux` to your default asset container
blueprint, you can query Mux data from the existing fieldname at `mux`. In your frontend, you can
plug the returned data into  one of the official [Mux video components](/display#video-components).

::: code-group

```graphql [GraphQL Query]
{
  asset: asset(id: "assets::video.mp4") {
    path
    ... on Asset_Assets {
      mux {
        playback_id
        thumbnail
      }
    }
  }
}
```

```json [JSON Result]
{
  "data": {
    "asset": {
      "id": "assets::video.mp4",
      "path": "video.mp4",
      "mux": {
        "playback_id": "02G1uw5SpNCrPMwiqfyNY4RkOWff4O7DCohToTqcZXH8",
        "thumbnail": "https://image.mux.com/02G1uw5SpNCrPMwiqfyNY4RkOWff4O7DCohToTqcZXH8/thumbnail.jpg",
      }
    }
  }
}
```

```html [Frontend Display]
<mux-video
  :playback-id="mux.playback_id"
  :poster="mux.thumbnail"
  width="1920"
  height="1080"
></mux-video>
```

:::

## Available subfields

| Field | Type | Description |
|-------|------|-------------|
| **`mux_id`** | `string` | Mux asset id |
| **`playback_id`** | `MuxPlaybackId` | Playback id used for streaming, defaults to public |
| **`playback_ids`** | `MuxPlaybackId[]` | All available playback ids for this asset in case multiple exist |
| **`playback_url`** | `string` | Playback url used for streaming |
| **`playback_token`** | `string` | Signed playback token used for secure streaming |
| **`thumbnail`** | `string` | Thumbnail image url |
| **`gif`** | `string` | Animated gif url |
| **`placeholder`** | `string` | Small blurry placeholder image data uri |
