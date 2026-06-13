# Artisan Commands

The following commands are available for uploading videos manually or at regular intervals.

While the addon automatically uploads [mirrored videos](/upload) in the background, running the
`⁠mux:mirror` command at regular intervals is a recommended safety measure against misconfigured queues.
If mirroring is working as expected, the command will be a no-op and simply return a success message.

| Command | Description |
|-------|-----------|
| [**`mux:upload`**](/commands/mux-upload) | Upload local video assets to Mux |
| [**`mux:prune`**](/commands/mux-prune) | Remove orphaned videos from Mux |
| [**`mux:mirror`**](/commands/mux-mirror) | Upload local videos to Mux & remove orphaned Mux videos |
| [**`mux:debug`**](/commands/mux-debug) | Debug Mux configuration and setup |
