<?php

namespace Hail\Console\TerminalObject\Dynamic;

class Radio extends Checkboxes
{
    /**
     * Build out the checkboxes
     *
     * @param array $options
     *
     * @return Checkbox\RadioGroup
     */
    protected function buildCheckboxes(array $options)
    {
        return new Checkbox\RadioGroup($options);
    }
}