<?php

declare(strict_types=1);

namespace Vatly\Laravel\Builders\Concerns;

trait ManagesTestmode
{
    protected bool $testmode = false;

    public function withTestmode(bool $testmode): self
    {
        $this->testmode = $testmode;

        return $this;
    }

    public function inTestmode(): self
    {
        return $this->withTestmode(true);
    }

    public function inLiveMode(): self
    {
        return $this->withTestmode(false);
    }
}
