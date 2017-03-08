<?php

namespace Hail\Console\TerminalObject\Basic;

class Clear extends AbstractBasic
{
    /**
     * Clear the terminal
     *
     * @return string
     */
    public function result()
    {
        return "\e[H\e[2J";
    }

    public function sameLine()
    {
        return true;
    }
}