# `mux:debug` <Badge type="info">Artisan Command</Badge>

**Debug Mux configuration and setup.**

Warn about missing credentials, misconfigured queue, disabled mirror feature,
and missing asset containers with Mux Mirror fields.

```sh
# Check for Mux configuration issues
php please mux:debug
```

Example output:

```sh
✓ Mux is configured with credentials.
✓ The queue is configured to use a background worker.
✓ The mirror feature is globally enabled.
✗ No asset containers found with Mux Mirror fields.
```

<!--@include: ../partials/command-names.md-->
