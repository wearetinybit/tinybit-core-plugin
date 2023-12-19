<?php
/**
 * Loads what we needs.
 *
 * @package TBC
 */

/**
 * Registers an array of hooks for a class.
 *
 * @param string $class_name Class name.
 * @param array  $hooks      Hooks to register.
 */
function tbc_register_class_hooks( $class_name, $hooks ) {
	foreach ( tbc_parse_register_class_hooks( $class_name, $hooks ) as $parsed_hook ) {
		list( $type, $hook, $class_name, $method, $priority, $args ) = $parsed_hook;
		$function = 'action' === $type ? 'add_action' : 'add_filter';
		$function( $hook, [ $class_name, $method ], $priority, $args );
	}
}

/**
 * Parses an array of hooks into their appropriate definitions.
 *
 * @param string $class_name Class name.
 * @param array  $hooks      Hooks to register.
 * @return array
 */
function tbc_parse_register_class_hooks( $class_name, $hooks ) {
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
				$class_name,
				$method,
				$priority,
				$args,
			];
		} else {
			$type = null;
			if ( method_exists( $class_name, 'filter_' . $hook ) ) {
				$type   = 'filter';
				$method = 'filter_' . $hook;
			} elseif ( method_exists( $class_name, 'action_' . $hook ) ) {
				$type   = 'action';
				$method = 'action_' . $hook;
			}
			$parsed_hooks[] = [
				$type,
				$hook,
				$class_name,
				$method,
				$priority,
				$args,
			];
		}
	}
	return $parsed_hooks;
}

/**
 * Register the class autoloader
 */
spl_autoload_register(
	function ( $class_name ) {
		$class_name = ltrim( $class_name, '\\' );
		if ( 0 !== stripos( $class_name, 'TBC\\' ) ) {
			return;
		}

		$parts = explode( '\\', $class_name );
		array_shift( $parts ); // Don't need "TBC".
		$last    = array_pop( $parts ); // File should be 'class-[...].php'.
		$last    = 'class-' . $last . '.php';
		$parts[] = $last;
		$file    = __DIR__ . '/inc/' . str_replace( '_', '-', strtolower( implode( '/', $parts ) ) );
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
