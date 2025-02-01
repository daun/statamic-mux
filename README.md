# Statamic Mux

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daun/statamic-mux.svg)](https://packagist.org/packages/daun/statamic-mux)
[![Test Status](https://img.shields.io/github/actions/workflow/status/daun/statamic-mux/ci.yml?label=tests)](https://github.com/daun/statamic-mux/actions/workflows/ci.yml)
<!-- [![Code Coverage](https://img.shields.io/codecov/c/github/daun/statamic-mux)](https://app.codecov.io/gh/daun/statamic-mux) -->
<!-- [![License](https://img.shields.io/github/license/daun/statamic-mux.svg)](https://github.com/daun/statamic-mux/blob/master/LICENSE) -->

**Enable seamless video encoding and streaming with this [Mux](https://www.mux.com/) integration for Statamic sites.**

[Documentation](https://statamic-mux.daun.ltd) · [Getting Started](https://statamic-mux.daun.ltd/installation) · [Releases](https://github.com/daun/statamic-mux/releases)

## Philosophy

The main goal of this addon: **make working with videos as easy as working with images**. All the magic
happens behind the scenes: videos are mirrored to your Mux account and can start streaming within seconds
using the official `<mux-video>` web component as a drop-in replacement for the native video element.

## Features

- **Automatic sync**  
  Mirror locally uploaded videos to your Mux account  
- **Seamless video upload workflow**  
  Just drop the original video files directly into the backend
- **No custom fieldtype required**  
  Handle and display videos like any other asset in Statamic
- **Instant playback**  
  Just-in-time encoding ensures videos can be streamed immediately
- **Optimized streaming**  
  Smart adaptive bitrates deliver the right resolution for your viewers' bandwith
- **Customizable player**  
  Tailor the video player component to match your site's design
- **Real-time analytics**  
  Get insights into your audience and video playback performance
- **Secure streaming**  
  Protect your content using signed urls

## Getting Started

Read the docs on [Installation](https://statamic-mux.daun.ltd/installation) and
[Connecting Mux](https://statamic-mux.daun.ltd/connecting-mux).

## Responsive Video

Just like image pipelines efficiently deliver optimized images for faster load times on a variety of devices
and connections, video encoding services like Mux play a crucial role for optimizing video content.
Video encoding demands more resources and expertise than image processing, so a dedicated service
like Mux becomes essential for performant websites.

## Why Mux?

While there is a host of services for video encoding to choose from, Mux offers all the key
components: encoding, storage, and delivery. It stands out with its API-first approach prioritizing
developer productivity, as well as its official web components for creating rich, customized video players.

## License

Statamic Mux is paid software with an open-source codebase. To use it in production, you'll need
to [buy a license](https://statamic.com/addons/daun/mux) from the Statamic Marketplace.

## Credits

Developed by [Philipp Daun](https://philippdaun.net/)
