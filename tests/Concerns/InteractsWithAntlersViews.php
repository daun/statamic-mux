<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Testing\TestView;

trait InteractsWithAntlersViews
{
    /**
     * Render the contents of the given Antlers template string.
     *
     * @param  Arrayable|array  $data
     */
    protected function antlers(string $template, $data = []): TestView
    {
        $tempDirectory = sys_get_temp_dir();

        if (! in_array($tempDirectory, ViewFacade::getFinder()->getPaths())) {
            ViewFacade::addLocation($tempDirectory);
        }

        $tempFileInfo = pathinfo(tempnam($tempDirectory, 'laravel-antlers'));

        $tempFile = $tempFileInfo['dirname'].'/'.$tempFileInfo['filename'].'.antlers.html';

        file_put_contents($tempFile, $template);

        return new TestView(view($tempFileInfo['filename'], $data));
    }
}
