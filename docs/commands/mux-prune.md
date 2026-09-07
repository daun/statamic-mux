# `mux:prune` <Badge type="info">Artisan Command</Badge>

**Remove orphaned videos from Mux.**

Prune only ever deletes videos this addon uploaded. Anything else in your Mux account is left alone.

Before deleting, it prints what it found, grouped by reason:

| Reason | What happened |
| --- | --- |
| **Superseded** | The local video was uploaded again and now points at a newer video on Mux |
| **Source deleted** | The local video it belonged to no longer exists |
| **Not linked** | The local video still exists, but no longer stores a Mux ID |
| **Expired placeholder** | A temporary placeholder clip that was never cleaned up |

```sh
# Print a list of affected videos without deleting anything
php artisan mux:prune --dry-run

# Delete orphaned videos
php artisan mux:prune

# Only consider videos from one asset container
php artisan mux:prune --container=videos
```
