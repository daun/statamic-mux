# Changelog

## [3.0.0] - 2026-02-08

- Add support for Statamic 6
- Show animated video thumbnails in asset listings
- Show upload status in asset listings
- Drop support for Laravel 10 & 11 (breaking change)
- Drop support for PHP 8.2 (breaking change)

## [2.1.1] - 2026-02-08

- Explicitly test against PHP 8.5

## [2.1.0] - 2025-12-11

- Speed up initial rendering by reusing existing playback id

## [2.0.0] - 2025-11-22

- Add configurable logger for simpler troubleshooting
- Add `mux:debug` command for ensuring correct configuration
- Make Guzzle requests hookable for advanced use cases
- Drop support for Laravel 9 and PHP 8.1 (breaking change)

## [1.8.0] - 2025-11-15

- Add option to lazyload web component scripts

## [1.7.0] - 2025-11-15

- Add option to optimize storage by replacing original video with placeholder
- Lower memory usage by streaming uploads
- Support ingestion from public url in more scenarios
- Improve dispatch speed on async queues

## [1.6.0] - 2025-10-10

- Improve Antlers rendering performance by augmenting on request

## [1.5.4] - 2025-09-02

- Fix test case with newest Mux SDK
- Fix some orphaned videos not being pruned in command

## [1.5.3] - 2025-08-01

- Fix job constructor types

## [1.5.2] - 2025-05-30

- Update Mux metadata when underlying Statamic asset is saved

## [1.5.1] - 2025-05-30

- Rename asset settings hook

## [1.5.0] - 2025-05-29

- Add support for video metadata
- Make upload request data hookable
- Display toast notifications on upload
- Improve test coverage
- Update Mux SDK to v5

## [1.4.0] - 2025-03-12

- Add `mux:embed` tag to render player in iframe
- Use correct poster images in player views
- Use all available tokens for secure playback
- Improve test coverage
- Update Mux SDK to v4

## [1.3.0] - 2025-03-06

- Add support for Laravel 12

## [1.2.5] - 2025-02-03

- Improve display of available data in fieldtype

## [1.2.4] - 2025-02-03

- Autocreate playback id on GraphQL requests

## [1.2.3] - 2025-01-30

- Fix reupload when using sync queue

## [1.2.2] - 2025-01-30

- Fix access error on upload

## [1.2.1] - 2024-11-11

- Update docs for new `premium` quality level
- Add CI workflow

## [1.2.0] - 2024-10-29

- Customizable Antlers views for video components
- Add examples for disabling tracking
- Fix error when pruning orphaned videos

## [1.1.0] - 2024-10-13

- Add GraphQL support

## [1.0.1] - 2024-10-11

- Extend test coverage
- Refactor for improved testability

## [1.0.0] - 2024-10-08

- Public release
- Support multiple playback ids
- Clean up public facade
- Upgrade to `mux-player` v3
- Support new `video_quality` param

## [0.1.3] - 2024-07-23

- Fix service argument types

## [0.1.2] - 2024-07-23

- Reduce addon style specificity

## [0.1.1] - 2024-04-30

- Remove tailwind display classes

## [0.1.0] - 2024-04-30

- Beta release

[3.0.0]: https://github.com/daun/statamic-mux/releases/tag/3.0.0
[2.1.1]: https://github.com/daun/statamic-mux/releases/tag/2.1.1
[2.1.0]: https://github.com/daun/statamic-mux/releases/tag/2.1.0
[2.0.0]: https://github.com/daun/statamic-mux/releases/tag/2.0.0
[1.8.0]: https://github.com/daun/statamic-mux/releases/tag/1.8.0
[1.7.0]: https://github.com/daun/statamic-mux/releases/tag/1.7.0
[1.6.0]: https://github.com/daun/statamic-mux/releases/tag/1.6.0
[1.5.4]: https://github.com/daun/statamic-mux/releases/tag/1.5.4
[1.5.3]: https://github.com/daun/statamic-mux/releases/tag/1.5.3
[1.5.2]: https://github.com/daun/statamic-mux/releases/tag/1.5.2
[1.5.1]: https://github.com/daun/statamic-mux/releases/tag/1.5.1
[1.5.0]: https://github.com/daun/statamic-mux/releases/tag/1.5.0
[1.4.0]: https://github.com/daun/statamic-mux/releases/tag/1.4.0
[1.3.0]: https://github.com/daun/statamic-mux/releases/tag/1.3.0
[1.2.5]: https://github.com/daun/statamic-mux/releases/tag/1.2.5
[1.2.4]: https://github.com/daun/statamic-mux/releases/tag/1.2.4
[1.2.3]: https://github.com/daun/statamic-mux/releases/tag/1.2.3
[1.2.2]: https://github.com/daun/statamic-mux/releases/tag/1.2.2
[1.2.1]: https://github.com/daun/statamic-mux/releases/tag/1.2.1
[1.2.0]: https://github.com/daun/statamic-mux/releases/tag/1.2.0
[1.1.0]: https://github.com/daun/statamic-mux/releases/tag/1.1.0
[1.0.1]: https://github.com/daun/statamic-mux/releases/tag/1.0.1
[1.0.0]: https://github.com/daun/statamic-mux/releases/tag/1.0.0
[0.1.3]: https://github.com/daun/statamic-mux/releases/tag/0.1.3
[0.1.2]: https://github.com/daun/statamic-mux/releases/tag/0.1.2
[0.1.1]: https://github.com/daun/statamic-mux/releases/tag/0.1.1
[0.1.0]: https://github.com/daun/statamic-mux/releases/tag/0.1.0
