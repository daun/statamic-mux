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

## Troubleshooting

If videos are not uploading as expected, run the [debug command](/commands/mux-debug)
to ensure everything is configured correctly and check the addon's error logs in
`storage/logs/mux.log` for any issues. You can also temporarily increase the global
or addon log level to `debug` to get more insight into the queueing and processing
of video files. See the configuration docs on [Logging](/configuration#logging)
for details and more options.

```ini
LOG_LEVEL=debug        # global log level
MUX_LOG_LEVEL=debug    # addon log level
```

## Optimizing Storage

By default, the addon will keep the original video files on your configured asset disk. In general,
this is a good idea to ensure long-term independence from any one video provider and allow downloads
and streaming from your origin server as a fallback.

If you need to save storage space on the server, you can configure the addon to replace video files
with a smaller placeholder version. This will store a short 10s clip of the video for previewing in
the backend, but requires Mux for streaming and downloading the full video.

See the configuration docs on [Storage Optimization](/configuration#storage-optimization) for details.

## Video Metadata

Mux videos support a set of [metadata fields](https://www.mux.com/docs/guides/add-metadata-to-your-videos)
that are set when uploading video files. The addon will pull these from the asset blueprint if
possible. You can [customize metadata using a hook](/hooks#asset-metadata).

| Meta | Value | Example |
|------|------ | --------|
| `title` | Value of asset's `title` field<br> (or filename if empty) | `Good Times` or `video.mp4` |
| `creator_id` | Addon name | `statamic-mux` |
| `external_id` | Asset id | `assets::video.mp4` |
