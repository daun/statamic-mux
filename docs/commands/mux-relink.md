# `mux:relink` <Badge type="info">Artisan Command</Badge>

**Reconnect local videos to videos that already exist on Mux.**

When a local asset loses its Mux ID — after a bad restore, a failed migration, or a stray edit to
asset metadata — its video is still on Mux. This command finds it and reconnects the two, instead of
uploading the same video again.

```sh
# Review matches and confirm them
php artisan mux:relink

# Accept all safe matches without asking
php artisan mux:relink --no-interaction

# Print a list of matches without changing anything
php artisan mux:relink --dry-run

# Only relink videos in one asset container
php artisan mux:relink --container=videos
```

::: info Relink before you prune
[`mux:prune`](/commands/mux-prune) treats these videos as unused and deletes them. Relink first, or
use [`mux:mirror`](/commands/mux-mirror), which handles the ordering for you.
:::
