<?php
declare(strict_types=1);

namespace WatermarkManager\Includes;

/**
 * Register all actions and filters for the plugin.
 */
class Loader {
    /** @var array<array<string, mixed>> */
    private array $actions = [];
    
    /** @var array<array<string, mixed>> */
    private array $filters = [];

    /**
     * Add a new action to the collection
     */
    public function add_action(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): void {
        $this->actions = $this->add(
            $this->actions,
            $hook,
            $component,
            $callback,
            $priority,
            $accepted_args
        );
    }

    /**
     * Add a new filter to the collection
     */
    public function add_filter(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): void {
        $this->filters = $this->add(
            $this->filters,
            $hook,
            $component,
            $callback,
            $priority,
            $accepted_args
        );
    }

    /**
     * Register the filters and actions with WordPress
     */
    public function run(): void {
        try {
            foreach ($this->filters as $hook) {
                add_filter(
                    $hook['hook'],
                    [$hook['component'], $hook['callback']],
                    $hook['priority'],
                    $hook['accepted_args']
                );
            }

            foreach ($this->actions as $hook) {
                add_action(
                    $hook['hook'],
                    [$hook['component'], $hook['callback']],
                    $hook['priority'],
                    $hook['accepted_args']
                );
            }
        } catch (\Exception $e) {
             $this->logger->error('Failed to register hooks: ' . $e->getMessage());
        }
    }

    /**
     * Utility function to register the actions and hooks into a single collection
     * 
     * @param array<array<string, mixed>> $hooks
     * @return array<array<string, mixed>>
     */
    private function add(
        array $hooks,
        string $hook,
        object $component,
        string $callback,
        int $priority,
        int $accepted_args
    ): array {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        ];

        return $hooks;
    }
}