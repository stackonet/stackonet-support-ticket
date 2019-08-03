<?php

namespace StackonetSupportTicket\Interfaces;

use ArrayAccess;
use Countable;
use IteratorAggregate;

defined( 'ABSPATH' ) || exit;

/**
 * Collection Interface
 *
 * @package DialogContactForm
 * @since   3.0.0
 */
interface CollectionInterface extends ArrayAccess, Countable, IteratorAggregate {

	/**
	 * Does this collection have a given key?
	 *
	 * @param string $key The data key
	 *
	 * @return bool
	 */
	public function has( $key );

	/**
	 * Set collection item
	 *
	 * @param string $key The data key
	 * @param mixed $value The data value
	 */
	public function set( $key, $value );

	/**
	 * Get collection item for key
	 *
	 * @param string $key The data key
	 * @param mixed $default The default value to return if data key does not exist
	 *
	 * @return mixed The key's value, or the default value
	 */
	public function get( $key, $default = null );

	/**
	 * Add item to collection, replacing existing items with the same data key
	 *
	 * @param array $items Key-value array of data to append to this collection
	 */
	public function replace( array $items );

	/**
	 * Get all items in collection
	 *
	 * @return array The collection's source data
	 */
	public function all();

	/**
	 * Remove item from collection
	 *
	 * @param string $key The data key
	 */
	public function remove( $key );

	/**
	 * Remove all items from collection
	 */
	public function clear();
}
