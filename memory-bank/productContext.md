# Product Context

## Problem Statement

Website editors need to deliver high-quality video content through their Statamic-powered websites but face several challenges:

1. Video hosting directly on web servers is inefficient and costly
2. Manual encoding for different devices and bandwidths is complex
3. Delivery optimization requires specialized CDN infrastructure
4. Managing video assets separately from other content creates workflow friction

## Solution Overview

The Statamic Mux addon solves these problems by:

1. Automatically mirroring video assets from Statamic to Mux's specialized video platform
2. Leveraging Mux's adaptive encoding to optimize for all devices and connections
3. Providing a unified content management experience within Statamic

## User Experience Goals

- **For Content Editors**: Manage videos directly within the familiar Statamic control panel
- **For Developers**: Implement video playback with simple template tags
- **For End Users**: Experience fast-loading, adaptive video playback across all devices

## Primary Workflows

### Video Asset Management

1. Content editor uploads video through Statamic's asset manager
2. System automatically mirrors the asset to Mux (async job)
3. Mux processes video for optimal delivery
4. Playback IDs and metadata are stored with Statamic asset

### Frontend Implementation

1. Developer implements templating tags in Antlers templates
2. System generates appropriate embed code based on context
3. Frontend visitors receive optimized video streams

### Security Considerations

1. Private videos can use signed URLs to prevent unauthorized access

## Value Proposition

This addon provides significant value through:

1. Reduced development time for video implementation
2. Lower bandwidth costs through optimized delivery
3. Improved user experience with adaptive streaming
4. Simplified content management workflow
