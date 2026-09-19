# `mux:debug` <Badge type="info">Artisan Command</Badge>

**Debug Mux configuration and setup.**

Checks credentials, the queue connection, the mirror feature, the asset containers with a Mux Mirror
field, and the playback policy.

## Usage

```sh
php artisan mux:debug

# Report the checks as a single JSON object
php artisan mux:debug --json
```

## Options

| Option | Description |
| --- | --- |
| `--json` | Print a single machine-readable JSON object instead of human output |

## Output

```sh
Credentials      OK
Queue            sync (not recommended)
Mirror feature   ON
Containers       videos
Signed playback  OFF

▎ The queue is synchronous. Uploads will block the request.
▎ Configure a background queue worker for best performance.

● Debug complete — the Mux setup looks good.
```

## Exit code

The command fails with exit code `1` when the setup is broken:

- Mux credentials are missing or invalid
- The mirror feature is globally disabled
- No asset container has a `mux_mirror` field

A synchronous queue and a signed playback policy without a signing key are warnings only. They do not
fail the command, so `mux:debug` is safe to run in CI as a setup check.
