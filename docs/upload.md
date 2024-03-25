# Getting Your Videos Into Mux

## Automatic Sync

The addon ships with a custom **Mux Mirror** fieldtype. Add a field of this type to
any asset container blueprint you plan on uploading videos from. The title and handle
of the field don't matter and can be chosen freely.

Whenever a new video is uploaded to a container with this field in its blueprint, it will
be mirrored to Mux. Any videos deleted from this container will also be deleted from Mux.

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
```

:::

## Uploading Existing Videos

Adding a Mux Mirror field will enable automatic sync for videos uploaded **from that point onwards**.
To sync existing videos that were uploaded before the field was added, you'll need to manually run
one of the available [Artisan Commands](/commands), e.g.:

```sh
# Upload existing videos to Mux
php please mux:upload
```
