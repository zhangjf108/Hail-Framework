<?php

namespace Hail\Promise;

use Hail\Promise\Exception\RejectionException;

class Factory
{
    /**
     * Get the global task queue used for promise resolution.
     *
     * This task queue MUST be run in an event loop in order for promises to be
     * settled asynchronously. It will be automatically run when synchronously
     * waiting on a promise.
     *
     * <code>
     * while ($eventLoop->isRunning()) {
     *     Hail\Promise\Factory::queue()->run();
     * }
     * </code>
     *
     * @param TaskQueue $assign Optionally specify a new queue instance.
     *
     * @return TaskQueue
     */
    public static function queue(TaskQueue $assign = null): TaskQueue
    {
        static $queue;

        if ($assign) {
            $queue = $assign;
        } elseif (!$queue) {
            $queue = new TaskQueue();
        }

        return $queue;
    }

    /**
     * Adds a public static function to run in the task queue when it is next `run()` and returns
     * a promise that is fulfilled or rejected with the result.
     *
     * @param callable $task Task public static function to run.
     *
     * @return PromiseInterface
     */
    public static function task(callable $task): PromiseInterface
    {
        $queue = self::queue();
        $promise = new Promise([$queue, 'run']);
        $queue->add(static function () use ($task, $promise) {
            try {
                $promise->resolve($task());
            } catch (\Throwable $e) {
                $promise->reject($e);
            }
        });

        return $promise;
    }

    /**
     * Creates a promise for a value if the value is not a promise.
     *
     * @param mixed $value Promise or value.
     *
     * @return PromiseInterface
     */
    public static function promise($value): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }

        // Return a Promise that shadows the given promise.
        if (method_exists($value, 'then')) {
            $wfn = method_exists($value, 'wait') ? [$value, 'wait'] : null;
            $cfn = method_exists($value, 'cancel') ? [$value, 'cancel'] : null;
            $promise = new Promise($wfn, $cfn);
            $value->then([$promise, 'resolve'], [$promise, 'reject']);

            return $promise;
        }

        return (new Promise())->withState(PromiseInterface::FULFILLED, $value);
    }

    /**
     * Creates a rejected promise for a reason if the reason is not a promise. If
     * the provided reason is a promise, then it is returned as-is.
     *
     * @param mixed $reason Promise or reason.
     *
     * @return PromiseInterface
     */
    public static function rejection($reason): PromiseInterface
    {
        if ($reason instanceof PromiseInterface) {
            return $reason;
        }

        return (new Promise())->withState(PromiseInterface::REJECTED, $reason);
    }

    /**
     * Create an exception for a rejected promise value.
     *
     * @param mixed $reason
     *
     * @return \Exception|\Throwable
     */
    public static function exception($reason)
    {
        return $reason instanceof \Throwable ? $reason : new RejectionException($reason);
    }

    /**
     * Returns an iterator for the given value.
     *
     * @param mixed $value
     *
     * @return \Iterator
     */
    public static function iterator($value): \Iterator
    {
        if ($value instanceof \Iterator) {
            return $value;
        }

        if (is_array($value)) {
            return new \ArrayIterator($value);
        }

        return new \ArrayIterator([$value]);
    }

    /**
     * @see Coroutine
     *
     * @param callable $generatorFn
     *
     * @return PromiseInterface
     */
    public static function coroutine(callable $generatorFn): PromiseInterface
    {
        return new Coroutine($generatorFn);
    }
}