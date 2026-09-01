# Handoff: Reupload retries create orphaned Mux assets

## Status

Investigation complete. The reported orphan-creation failure is plausible and is confirmed by the current control flow. No production code or tests have been changed yet.

Branch: `fix/deletion-while-processing`

## Client report

A client reports that reuploading a video while its existing Mux asset is still preparing can produce several orphaned Mux assets:

1. Statamic requests a forced reupload.
2. The addon creates a replacement Mux asset.
3. It immediately tries to delete the previous Mux asset.
4. Mux returns HTTP 400 because a preparing asset cannot be deleted.
5. Laravel retries the failed upload job within seconds.
6. Every retry creates another replacement before deletion fails again.

The client also reports that some videos have no width, height, or duration and cannot be previewed in Statamic's asset browser. Their prune dry run finds 126 orphaned Mux assets.

## Confirmed cause

`CreateMuxAsset::handle()` currently performs creation and cleanup in one failure boundary:

- `src/Mux/Actions/CreateMuxAsset.php` reads the current Mux ID into `$previousMuxId`.
- It creates a new Mux asset.
- It saves the new ID and playback ID to the Statamic asset.
- It synchronously calls `MuxService::deleteMuxAsset($previousMuxId)`.
- It dispatches `AssetUploadedToMux` only after deletion succeeds.

`src/Mux/Actions/DeleteMuxAsset.php` catches Mux API failures and rethrows them. Therefore, a failed deletion fails the enclosing `CreateMuxAssetJob` even though creation and local persistence already succeeded.

`src/Jobs/CreateMuxAssetJob.php` has no retry policy or stage tracking. When Laravel retries a forced upload, the complete action runs again. Because the previous attempt already saved its new Mux ID locally, the retry treats that newly created asset as the predecessor, creates another Mux asset, and then tries to delete the predecessor while it is also preparing.

This produces one orphan for each failed attempt. The reported three retries in three seconds depend on the application's queue worker configuration; the addon currently supplies no upload-job backoff.

## Existing coverage gap

`tests/Feature/Actions/CreateMuxAssetTest.php` contains a happy-path test named:

> deletes the previous Mux asset after re-upload when no duplicates exist

It mocks deletion to return `true`. There is no test for a successful replacement followed by a deletion exception, and no test proving that cleanup failure cannot retry creation.

## Related metadata and preview symptoms

The retry defect can contribute to missing cached duration but does not fully explain every preview symptom.

Relevant behavior:

- Reupload clears the existing Mux field data before saving the new ID and playback IDs. This also clears cached `duration` and `is_proxy` values.
- A newly created, preparing Mux asset may not yet expose duration.
- `AssetUploadedToMux` is dispatched after predecessor deletion. If deletion throws, that event is never dispatched.
- When `mux.storage.store_placeholders` is enabled, `ProxyVersionSubscriber` relies on `AssetUploadedToMux` to begin placeholder generation.
- Native playback in Statamic's asset browser uses the local file and Statamic's local video metadata, not Mux playback.

Consequently, this bug may leave duration uncached and prevent a new placeholder workflow from starting. Missing width and height are still unconfirmed and may instead reflect Statamic metadata extraction, the local source encoding, or an incomplete/failed placeholder replacement. Do not claim that all 126 prune candidates or all preview failures have this cause without client logs and asset metadata.

## Implementation plan

### 1. Add a failing regression test

Add a focused test at the upload-job/action integration seam.

Scenario:

1. Give a local video an existing Mux ID.
2. Run a forced reupload through `CreateMuxAssetJob`/`CreateMuxAsset`.
3. Mock the Mux create request as successful.
4. Make deletion of the previous, preparing asset fail as Mux does in production.
5. Emulate queue retry behavior only when the upload job throws.

Desired assertions:

- The upload job does not fail because cleanup failed.
- Exactly one Mux create request is made.
- The local asset retains the replacement Mux ID and playback ID.
- Cleanup of the predecessor is queued exactly once.
- `AssetUploadedToMux` is dispatched for the successful replacement.

Before the fix, the test must fail by showing that the deletion exception escapes and permits another create attempt.

Also update the existing successful-reupload test so it expects delegated cleanup instead of a synchronous service call.

### 2. Separate replacement creation from predecessor cleanup

After the replacement Mux metadata has been saved, dispatch a `DeleteMuxAssetJob` for the predecessor instead of deleting it synchronously inside `CreateMuxAsset`.

Requirements:

- A cleanup failure must not fail `CreateMuxAssetJob`.
- `AssetUploadedToMux` must still be dispatched once creation and local persistence succeed.
- Use the addon's configured queue.
- Ensure the sync queue driver does not put cleanup back inside the upload's failure boundary; use the existing asynchronous-dispatch convention or equivalent isolation.
- Keep the existing rule that a predecessor is not deleted when another local asset references it.

Because cleanup may now run later, recheck local references immediately before deleting the predecessor. This closes the race where another local asset begins referencing the old Mux ID after cleanup was scheduled.

### 3. Add delayed retries to deletion jobs

Configure `DeleteMuxAssetJob` with a bounded retry window and minute-scale backoff so a preparing Mux asset has time to become deletable.

Suggested behavior:

- Retry for up to 24 hours, matching the existing proxy-processing jobs.
- Use increasing delays measured in minutes rather than immediate retries.
- Let the Mux API remain authoritative: retry deletion exceptions instead of rerunning creation.
- Do not silently report deletion success when the API rejected it.

A status pre-check is optional. It may avoid a predictable HTTP 400, but it must not introduce a check/delete race or brittle matching against Mux error text. Separating the jobs and applying backoff are the required correctness changes.

### 4. Add cleanup-job coverage

Cover at least:

- Backoff/retry configuration exists and is long enough for normal Mux encoding.
- A delayed string-ID cleanup skips deletion if a local asset currently references that Mux ID.
- A ready, unreferenced predecessor is deleted normally.
- Existing proxy and prune cleanup behavior remains valid.

### 5. Verify

Run:

```bash
./vendor/bin/pest tests/Feature/Actions/CreateMuxAssetTest.php
./vendor/bin/pest tests/Feature/Actions/DeleteMuxAssetTest.php
./vendor/bin/pest tests/Feature/Jobs
composer test
composer lint
composer analyse
```

If no `tests/Feature/Jobs` directory is introduced, run the specific new test file instead.

## Acceptance criteria

- A failed attempt to delete a preparing predecessor cannot cause a second Mux asset creation.
- The successful replacement remains connected to the Statamic asset.
- Upload completion events run even when predecessor cleanup must wait.
- Predecessor deletion retries over minutes, not seconds.
- Delayed cleanup does not delete a Mux asset referenced by another local asset.
- Focused tests, full test suite, formatting, and static analysis pass.

## Follow-up investigation for the client

After fixing the confirmed retry defect, request the following for one playable and one non-playable asset:

- Statamic asset metadata, especially `width`, `height`, and `duration`.
- Stored `mux` field data.
- Mux asset IDs, statuses, creation times, and passthrough values.
- Queue failure logs around upload and placeholder jobs.
- `mux.storage.store_placeholders` and queue configuration.
- The screenshots referenced in the report.

This will determine whether the CP preview issue is a consequence of interrupted placeholder processing or an independent source-metadata problem.
