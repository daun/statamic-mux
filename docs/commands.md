# Artisan Commands

The following commands upload videos manually or on a schedule.

While the addon automatically uploads [mirrored videos](/upload) in the background, running the `mux:mirror`
command on a schedule guards against misconfigured queues. If mirroring is already working, the command is a
no-op and returns a success message.

| Command | Description |
|-------|-----------|
| [**`mux:upload`**](/commands/mux-upload) | Upload local video assets to Mux |
| [**`mux:prune`**](/commands/mux-prune) | Remove orphaned videos from Mux |
| [**`mux:mirror`**](/commands/mux-mirror) | Upload local videos to Mux & remove orphaned Mux videos |
| [**`mux:debug`**](/commands/mux-debug) | Debug Mux configuration and setup |
