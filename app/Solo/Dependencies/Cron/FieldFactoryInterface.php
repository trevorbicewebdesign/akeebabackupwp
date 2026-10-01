<?php

namespace Solo\Dependencies\Cron;

interface FieldFactoryInterface
{
    public function getField(int $position): FieldInterface;
}
