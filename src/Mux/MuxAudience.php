<?php

namespace Daun\StatamicMux\Mux;

enum MuxAudience: string {
    case Gif = 'g';
    case Storyboard = 's';
    case Thumbnail = 't';
    case Video = 'v';
}
