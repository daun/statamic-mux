# `mux:upload` <Badge type="info">Artisan Command</Badge>

**Upload local video assets to Mux.**

Videos already linked to Mux are kept as they are, unless `--force` is set.

## Usage

```sh
php artisan mux:upload

# Upload videos to Mux, reupload existing videos
php artisan mux:upload --force

# Print the plan without uploading anything
php artisan mux:upload --dry-run

# Only upload videos from one asset container
php artisan mux:upload --container=videos
```

## Options

| Option | Description |
| --- | --- |
| `--container=` | Limit the command to one asset container |
| `--force` | Reupload videos that are already linked to Mux |
| `--dry-run` | Print the plan without uploading anything |
| `--json` | Print a single machine-readable JSON object instead of human output |

## Output

```sh
upload  3  local videos not yet on Mux
keep    8

● 3 uploads and 8 kept pending.
```
