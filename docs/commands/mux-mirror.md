# `mux:mirror` <Badge type="info">Artisan Command</Badge>

**Bring Mux in sync with your local videos.**

1. **Relink** local videos to videos that already exist on Mux
2. **Upload** local videos that are not on Mux yet
3. **Prune** videos on Mux that nothing uses anymore

Running this on a schedule is a good safety net in case a queue worker goes down. When everything is
already in sync, it does nothing and reports success.

## Usage

```sh
php artisan mux:mirror

# Print the plan without making changes
php artisan mux:mirror --dry-run

# Only sync one asset container
php artisan mux:mirror --container=videos
```

## Options

| Option | Description |
| --- | --- |
| `--container=` | Limit the command to one asset container |
| `--force` | Upload fresh copies instead of reusing existing videos on Mux |
| `--dry-run` | Print the plan without uploading, relinking or pruning |
| `--json` | Print a single machine-readable JSON object instead of human output |

## Output

```sh
relink    2  placeholder clip source is unlinked
upload    3  local videos not yet on Mux
prune   442  superseded by a newer upload
keep      8

● Mirror complete — 2 queued for re-linking, 3 queued for upload, 442 queued for removal, 8 kept.
```

Individual files are not listed at normal verbosity. Run with `-v` to see every affected record, or
use `--json` for reporting.

## Advisories

Mirror warns before it does something you may not want:

- **Relink first.** How many encodings the relink step saves from being recreated.
- **Unready links** and **shared references.** Local videos pointing at an encoding that never became
  ready, and Mux videos referenced by more than one local asset.
- **Unscopable videos.** Mux videos that cannot be attributed to a container are
  skipped rather than guessed at.
