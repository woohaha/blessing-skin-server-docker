<?php

namespace Blessing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Filter
{
    protected $listeners = [];

    /** @var Container */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Add one filter to a specified hook.
     *
     * @param string $hook     the hook to be listened
     * @param mixed  $filter   filter handler
     * @param int    $priority Priority for this filter. Higher value is with higher priority.
     */
    public function add(string $hook, $filter, $priority = 20): void
    {
        if (!isset($this->listeners[$hook])) {
            $this->listeners[$hook] = new Collection();
        }

        $this->listeners[$hook]->push([
            'filter' => $filter,
            'priority' => $priority,
        ]);
    }

    /**
     * Apply a hook with initial value and additional arguments.
     *
     * @param string $hook hook name
     * @param mixed  $init initial value
     * @param array  $args additional arguments
     */
    public function apply(string $hook, $init, $args = [])
    {
        $listeners = $this->getListeners($hook);
        if ($listeners->isNotEmpty()) {
            return $this->listeners[$hook]
                ->sortBy('priority')
                ->reduce(function ($carry, $item) use ($args) {
                    $arguments = array_merge([], [$carry], $args);

                    $filter = $item['filter'];
                    if (is_callable($filter)) {
                        return call_user_func_array($item['filter'], $arguments);
                    }

                    $instance = $this->container->make($item['filter']);

                    return call_user_func_array([$instance, 'filter'], $arguments);
                }, $init);
        } else {
            return $init;
        }
    }

    /**
     * Remove all filter listeners for a specified hook.
     *
     * @param string $hook hook name
     */
    public function remove(string $hook): void
    {
        unset($this->listeners[$hook]);
    }

    /**
     * Get all listeners for a specified hook.
     *
     * @param string $hook hook name
     */
    public function getListeners(string $hook): Collection
    {
        return Arr::get($this->listeners, $hook, new Collection());
    }
}
