# `mux:prune` <Badge type="info">Artisan Command</Badge>

**Remove orphaned videos from Mux.**

Delete videos on Mux that are no longer used by any local asset.

Only touches videos this addon uploaded.

## Usage

```sh
php artisan mux:prune

# Print the plan without deleting anything
php artisan mux:prune --dry-run

# Only consider videos from one asset container
php artisan mux:prune --container=videos
```

## Options

| Option | Description |
| --- | --- |
| `--container=` | Limit the command to one asset container |
| `--dry-run` | Print the plan without deleting anything |
| `--json` | Print a single machine-readable JSON object instead of human output |

## Output

```sh
  prune   442
           96  local asset no longer exists
           42  placeholder parent is gone
          304  expired placeholder clip
  keep      8  linked to a ready Mux encoding
  ignore    3  not created by this addon

  ● 442 prunes, 8 kept and 3 ignored pending.
```

Individual videos are not listed at normal verbosity. Run with `-v` to see each Mux ID and its
reason, `-vv` for full IDs, resolutions and statuses, or use `--json`.
