<?php

namespace Daun\StatamicMux\Commands\Concerns;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait HasOutputStyles
{
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->registerStyles();
    }

    protected function registerStyles()
    {
        $this->output->getFormatter()->setStyle('bold', new OutputFormatterStyle(options: ['bold']));
        $this->output->getFormatter()->setStyle('success', new OutputFormatterStyle('green', options: ['bold']));
        $this->output->getFormatter()->setStyle('name', new OutputFormatterStyle('blue'));
    }
}
