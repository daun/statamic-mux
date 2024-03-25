# `mux:prune` <Badge type="info">Artisan Command</Badge>

**Remove orphaned videos from Mux.**

Deletes any videos on Mux that no longer exist as local assets. Only affects videos
that were uploaded by this addon.

Use `--dry-run` to print a list of affected files without actually performing the removal.

```sh
# Remove orphaned videos
php please mux:prune

# Perform a trial run and print a list of affected files
php please mux:prune --dry-run
```

<!--@include: ../partials/command-names.md-->
