# Installation

Install the addon via composer.

```sh
composer require daun/statamic-mux
```

## Backend Requirements

- PHP 8.3+
- Laravel 12+
- Statamic 6+

For Statamic 5 and Laravel 10/11 support, please use version 2.x of this addon.

## Frontend Scripts

To stream videos on your frontend, you need a video player that handles adaptive bitrate streaming.
Mux provides [web components](https://github.com/muxinc/elements) you can install through NPM or include
from a CDN. The built-in Antlers tags like [`mux:video`](/tags/mux-video) can be configured to include
the required scripts automatically.
