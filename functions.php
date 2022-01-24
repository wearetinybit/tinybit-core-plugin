<?php
/**
 * Defines functions for us to use.
 *
 * @package TBC
 */

/**
 * Registers an array of hooks for a class.
 *
 * @param string $class Class name.
 * @param array  $hooks Hooks to register.
 */
function tbc_register_class_hooks( $class, $hooks ) {
	foreach ( tbc_parse_register_class_hooks( $class, $hooks ) as $parsed_hook ) {
		list( $type, $hook, $class, $method, $priority, $args ) = $parsed_hook;
		$function = 'action' === $type ? 'add_action' : 'add_filter';
		$function( $hook, [ $class, $method ], $priority, $args );
	}
}

/**
 * Parses an array of hooks into their appropriate definitions.
 *
 * @param string $class Class name.
 * @param array  $hooks Hooks to register.
 * @return array
 */
function tbc_parse_register_class_hooks( $class, $hooks ) {
	$parsed_hooks = [];
	foreach ( $hooks as $hook_data ) {
		$hook     = array_shift( $hook_data );
		$method   = null;
		$priority = 10;
		$args     = 1;
		if ( isset( $hook_data[0] ) && is_string( $hook_data[0] ) ) {
			$method = array_shift( $hook_data );
		}
		if ( isset( $hook_data[0] ) ) {
			$priority = array_shift( $hook_data );
			if ( isset( $hook_data[0] ) ) {
				$args = array_shift( $hook_data );
			}
		}
		if ( $method ) {
			$type = null;
			if ( 0 === stripos( $method, 'filter_' ) ) {
				$type = 'filter';
			} elseif ( 0 === stripos( $method, 'action_' ) ) {
				$type = 'action';
			}
			$parsed_hooks[] = [
				$type,
				$hook,
				$class,
				$method,
				$priority,
				$args,
			];
		} else {
			$type = null;
			if ( method_exists( $class, 'filter_' . $hook ) ) {
				$type   = 'filter';
				$method = 'filter_' . $hook;
			} elseif ( method_exists( $class, 'action_' . $hook ) ) {
				$type   = 'action';
				$method = 'action_' . $hook;
			}
			$parsed_hooks[] = [
				$type,
				$hook,
				$class,
				$method,
				$priority,
				$args,
			];
		}
	}
	return $parsed_hooks;
}
