<?php
/**
 * WordPress Hook API declarations for IDE indexing and static analysis.
 *
 * This file is never loaded by WordPress at runtime.
 *
 * @see https://developer.wordpress.org/plugins/hooks/
 */

/**
 * Registers a callback for a filter hook.
 *
 * @param non-empty-string $hook_name
 * @param callable         $callback
 * @param positive-int     $priority
 * @param positive-int     $accepted_args
 * @return true
 */
function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {}

/**
 * Registers a callback for an action hook.
 *
 * @param non-empty-string $hook_name
 * @param callable         $callback
 * @param positive-int     $priority
 * @param positive-int     $accepted_args
 * @return true
 */
function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {}

/**
 * Runs the callbacks registered for a filter hook.
 *
 * @template TValue
 * @param non-empty-string $hook_name
 * @param TValue           $value
 * @param mixed            ...$args
 * @return TValue
 */
function apply_filters( string $hook_name, mixed $value, mixed ...$args ): mixed {}

/**
 * Runs the callbacks registered for an action hook.
 *
 * @param non-empty-string $hook_name
 * @param mixed            ...$args
 */
function do_action( string $hook_name, mixed ...$args ): void {}

/**
 * Removes a callback from a filter hook.
 *
 * @param non-empty-string $hook_name
 * @param callable         $callback
 * @param positive-int     $priority
 */
function remove_filter( string $hook_name, callable $callback, int $priority = 10 ): bool {}

/**
 * Removes a callback from an action hook.
 *
 * @param non-empty-string $hook_name
 * @param callable         $callback
 * @param positive-int     $priority
 */
function remove_action( string $hook_name, callable $callback, int $priority = 10 ): bool {}

/**
 * Reports whether a callback is registered for a filter hook.
 *
 * @param non-empty-string      $hook_name
 * @param callable|string|array $callback
 * @return false|positive-int
 */
function has_filter( string $hook_name, callable|string|array $callback ): int|false {}

/**
 * Reports whether a callback is registered for an action hook.
 *
 * @param non-empty-string      $hook_name
 * @param callable|string|array $callback
 * @return false|positive-int
 */
function has_action( string $hook_name, callable|string|array $callback ): int|false {}
