# Getting Your Videos Into Mux

## Automatic Sync

The addon ships with a **Mux Mirror** fieldtype. When a field of this type is added to an asset
container blueprint, videos uploaded to the container will be uploaded to Mux automatically. Videos
deleted from the container will also be deleted from Mux. The handle of the field can be chosen freely.

::: code-group

```yaml [resources/blueprints/assets/assets.yaml]
title: Asset
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: alt
            field:
              display: 'Alt text'
              type: text
          - // [!code focus]
            handle: mux // [!code focus]
            field: // [!code focus]
              type: mux_mirror // [!code focus]
              display: Mux // [!code focus]
              if: // [!code focus]
                extension: mp4 // [!code focus]
```

:::

When editing the asset in the control panel, the Mux Mirror field will display the upload
status of the asset, e.g. "Video uploaded to Mux". If you'd rather hide the field from
editors, you can set its `visibility` to `hidden`.

## Uploading Existing Videos

Adding a Mux Mirror field will enable automatic sync for videos uploaded **from that point onwards**.
To sync existing videos that were uploaded before the field was added, you'll need to manually run
one of the available [Artisan Commands](/commands), e.g.:

```sh
# Upload existing videos to Mux
php please mux:upload
```

## Video Metadata

Mux videos support a set of [metadata fields](https://www.mux.com/docs/guides/add-metadata-to-your-videos)
that are set when uploading video files. The addon will pull these from the asset blueprint if
possible. You can [customize metadata using a hook](/hooks#asset-metadata).

| Meta | Value | Example |
|------|------ | --------|
| `title` | Value of asset's `title` field<br> (or filename if empty) | `Good Times` or `video.mp4` |
| `creator_id` | Addon name | `statamic-mux` |
| `external_id` | Asset id | `assets::video.mp4` |
