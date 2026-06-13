# Introduction

**Statamic Mux** is an addon for integrating the [Mux](https://www.mux.com/) video encoding and streaming service.

## Philosophy

The main goal of this addon: **make working with videos as easy as working with images**.

## How it works

Videos uploaded to an asset container are mirrored to your Mux account. Within a few seconds, they can be streamed
using the official [`<mux-video>`](/tags/mux-video) web component, which is a drop-in replacement for the native video element.

## Features

**Automatic sync**  
Mirror locally uploaded videos to your Mux account

**Standard upload workflow**  
Upload original video files into the control panel like any other asset

**No custom fieldtype required**  
Handle and display videos like any other asset in Statamic

**Instant playback**  
Videos can be streamed within seconds of upload, before full encoding completes

**Optimized streaming**  
Mux delivers a resolution matched to each viewer's bandwidth

**Customizable player**  
Configure the Mux video player component to match your site's design

**Secure streaming**  
Restrict access to videos using signed URLs

## Why a video service

Video encoding and delivery require more processing and storage than images. Serving video files directly
from your origin server, without adaptive bitrate streaming, results in large downloads and inconsistent
playback across devices and connections. A dedicated service handles encoding, storage, and adaptive
delivery.

## Why Mux

Mux provides encoding, storage, and delivery through a single API. This addon uses Mux specifically because
it exposes these features through an HTTP API and ships official web components for video playback, both of
which the addon depends on.

## License

Statamic Mux is paid software with an open-source codebase. To use it in production, you'll need
to [buy a license](https://statamic.com/addons/daun/mux) from the Statamic Marketplace.

## Credits

Developed by [Philipp Daun](https://philippdaun.net/)
