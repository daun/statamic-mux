# Artisan Commands

The following commands upload videos manually or on a schedule.

While the addon automatically uploads [mirrored videos](/upload) in the background, running the `mux:mirror`
command on a schedule guards against misconfigured queues. If mirroring is already working, the command is a
no-op and returns a success message.

| Command | Description |
|-------|-----------|
| [**`mux:mirror`**](/commands/mux-mirror) | Upload, prune and relink in a single operation |
| [**`mux:upload`**](/commands/mux-upload) | Upload local videos to Mux |
| [**`mux:prune`**](/commands/mux-prune) | Remove orphaned videos from Mux |
| [**`mux:relink`**](/commands/mux-relink) | Reconnect local videos to existing videos on Mux |
| [**`mux:debug`**](/commands/mux-debug) | Debug Mux configuration and setup |

## Output

### Verbosity

Every command accepts a `-v` flag to control verbosity.

| Flag | Output |
| --- | --- |
| `-q` | Nothing. The exit code is the result. |
| (default) | Advisories, plan and summary. No per-record lines. |
| `-v` | Lists every affected record with reason and status. |
| `-vv` | Adds full Mux IDs, candidate comparisons, resolutions and statuses. |

### JSON {#json-output}

Every command accepts `--json` to write a single object to stdout.

```json
{
  "command": "mux:mirror",
  "dry_run": true,
  "tense": "planned",
  "scope": { "containers": ["videos"], "locals": 11, "remotes": 453 },
  "context": { "Queue": "redis (background)" },
  "plan": { "relink": 0, "upload": 3, "re-upload": 0, "prune": 442, "keep": 8, "hold": 0, "ignore": 3, "skip": 7 },
  "records": [
    { "action": "upload", "id": "videos::trailer.mp4", "state": "upload", "reason": null }
  ],
  "advisories": [
    { "level": "warn", "code": "proxy-source-conflict", "records": ["videos::trailer.mp4"] }
  ],
  "failures": [],
  "exit_code": 0
}
```
