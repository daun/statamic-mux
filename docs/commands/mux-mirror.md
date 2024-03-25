# `mux:mirror` <Badge type="info">Artisan Command</Badge>

**Upload local videos to Mux, and remove orphaned Mux videos.**

This basically runs `mux:upload` and `mux:prune` in sequence.

```sh
# Sync local assets to Mux
php please mux:mirror

# Sync local assets to Mux, reupload existing videos
php please mux:mirror --force

# Perform a trial run and print a list of affected files
php please mux:mirror --dry-run
```

<!--@include: ../partials/command-names.md-->
