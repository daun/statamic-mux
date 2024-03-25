# `mux:upload` <Badge type="info">Artisan Command</Badge>

**Upload local video assets to Mux.**

Existing videos will not be uploaded again, unless the `--force` flag is set.

Use `--dry-run` to print a list of affected files without actually performing the upload.

```sh
# Upload videos to Mux, skip existing videos
php please mux:upload

# Upload videos to Mux, reupload existing videos
php please mux:upload --force

# Perform a trial run and print a list of affected files
php please mux:upload --dry-run
```

<!--@include: ../partials/command-names.md-->
