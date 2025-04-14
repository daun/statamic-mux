# Active Context

## Current Development Focus

### Test Coverage Improvements

Some features are currently not covered by tests.

- Add unit tests for the MuxMirrorFieldtype
- Add proper coverage for the Mux API client
- Add more tests for correct frontend tag rendering

### Architecture Overhaul

The architecture of the addon needs some restructuring to improve maintainability and allow for future enhancements.

- Move more logic into dedicated action classes
- Decouple services from the Mux API client

### Low-Res Placeholder File

- Provide a strategy for dealing with very large source files
- Replace uploaded video with a proxy/placeholder file (low-res and/or clipped)
- Polling or webhook to check status of proxy creation
- Download proxy video and locally replace files in Statamic assets

It should be possible to get the lowest resolution video from the playback manifest.

`ffmpeg -i "https://stream.mux.com/{{ PLAYBACK_ID }}.m3u8?rendition_order=desc" -c copy -map 0:3 -map 0:a low.mp4`

## Recent Changes

## Design Decisions & Preferences

### Code Organization

- Prefer trait-based composition over inheritance
- Action classes for discrete operations
- Event-driven communication between components

### Naming Conventions

- `Asset` for a file managed locally by Statamic
- `MuxAsset` for a file uploaded to and streamed by Mux
- `MuxPlaybackId` for a unique playback id allowing streaming from frontend components
- `Mux` prefix for all Mux-specific classes

### Testing Approach

- Feature tests for integration points
- Unit tests for isolated functionality
- Mock Mux API responses in tests

## Key Learnings & Insights

### Event-Based Architecture

The system benefits from Laravel's event system for loosely coupled communication between components, particularly for asynchronous operations.

### API Error Handling

Mux API errors must be carefully handled with appropriate retries and user feedback.

### Performance Considerations

- Avoid synchronous API calls in web requests
- Cache playback URLs when possible

## Immediate Next Steps

1. Complete low-res placeholder download implementation
2. Implement GraphQL types for headless implementations

## Current Questions & Considerations

1. How to create a low-res version of the original video? 
   - Use a short clip of the original video?
   - Use a low-res version of the original video?
