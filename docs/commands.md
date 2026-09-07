# Artisan Commands

The following commands upload videos manually or on a schedule.

While the addon automatically uploads [mirrored videos](/upload) in the background, running the `mux:mirror`
command on a schedule guards against misconfigured queues. If mirroring is already working, the command is a
no-op and returns a success message.

| Command | Description |
|-------|-----------|
| [**`mux:mirror`**](/commands/mux-mirror) | Upload, prune and relink in a single operation |
| [**`mux:upload`**](/commands/mux-upload) | Upload local videos to Mux |
| [**`mux:prune`**](/commands/mux-prune) | Remove orphaned videos from Mux |
| [**`mux:relink`**](/commands/mux-relink) | Reconnect local videos to existing videos on Mux |
| [**`mux:debug`**](/commands/mux-debug) | Debug Mux configuration and setup |
