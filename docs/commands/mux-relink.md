# `mux:relink` <Badge type="info">Artisan Command</Badge>

**Reconnect local videos to videos that already exist on Mux.**

When a local asset loses its Mux ID — after a failed migration or a stray edit to asset metadata —
its video is still on Mux. This command finds it and reconnects the two, instead of
uploading the same video again.

## Usage 

```sh
php artisan mux:relink

# Accept all safe matches without asking
php artisan mux:relink --no-interaction

# Print the plan without changing anything
php artisan mux:relink --dry-run

# Only relink videos in one asset container
php artisan mux:relink --container=videos
```

## Options

| Option | Description |
| --- | --- |
| `--container=` | Limit the command to one asset container |
| `--force` | Relink candidates that failed media validation |
| `--dry-run` | Print the plan without changing anything |
| `--json` | Print a single machine-readable JSON object instead of human output |

## Output

```sh
▎ 1 local video has no Mux encoding to re-link to.
▎ Upload them: php artisan mux:upload

relink  2  local asset exists but is unlinked
hold    1  media validation failed

● Relink complete — 2 re-linked, 1 held.
```

## Confirmation

In an interactive terminal, relink asks how to proceed: accept all safe matches, review each asset
individually, or cancel. Cancelling holds every reviewable match and exits successfully.

The prompt is skipped — and safe matches applied directly — when running with `--no-interaction`,
outside a terminal, with `--force`, with `--dry-run` or with `--json`.
