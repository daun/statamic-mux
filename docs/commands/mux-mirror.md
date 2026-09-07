# `mux:mirror` <Badge type="info">Artisan Command</Badge>

**Bring Mux in sync with your local videos.**

Mirror reads local and remote state once, prints a plan, then does three things in order:

1. **Relink** local videos to videos that already exist on Mux
2. **Upload** local videos that are not on Mux yet
3. **Prune** videos on Mux that nothing uses anymore

```sh
# Relink, upload, then prune
php artisan mux:mirror

# Print the full plan without making changes
php artisan mux:mirror --dry-run

# Only sync one asset container
php artisan mux:mirror --container=videos

# Upload fresh copies instead of reusing existing videos
php artisan mux:mirror --force
```

Running this on a schedule is a good safety net in case a queue worker goes down. When everything is
already in sync, it does nothing and reports success.
