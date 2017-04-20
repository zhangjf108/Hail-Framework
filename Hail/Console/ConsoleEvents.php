<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Console;

/**
 * Contains all events dispatched by an Application.
 *
 * @author Francesco Levorato <git@flevour.net>
 */
final class ConsoleEvents
{
    /**
     * The COMMAND event allows you to attach listeners before any command is
     * executed by the console. It also allows you to modify the command, input and output
     * before they are handled to the command.
     *
     * @Event("Hail\Console\Event\ConsoleCommandEvent")
     *
     * @var string
     */
    const COMMAND = 'console.command';

    /**
     * The TERMINATE event allows you to attach listeners after a command is
     * executed by the console.
     *
     * @Event("Hail\Console\Event\ConsoleTerminateEvent")
     *
     * @var string
     */
    const TERMINATE = 'console.terminate';

    /**
     * The ERROR event occurs when an uncaught exception appears or
     * a throwable error.
     *
     * This event allows you to deal with the exception/error or
     * to modify the thrown exception.
     *
     * @Event("Hail\Console\Event\ConsoleErrorEvent")
     *
     * @var string
     */
    const ERROR = 'console.error';
}
