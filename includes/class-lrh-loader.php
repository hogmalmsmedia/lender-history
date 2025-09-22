<?php
/**
 * Register all actions and filters for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Loader {
    
    protected $actions;
    protected $filters;
    
    public function __construct() {
        $this->actions = [];
        $this->filters = [];
    }
    
    /**
     * Add a new action to the collection
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Add a new filter to the collection
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Add hook to collection
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        ];
        
        return $hooks;
    }
    
    /**
     * Register the filters and actions with WordPress
     */
    public function run() {
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
    }
}