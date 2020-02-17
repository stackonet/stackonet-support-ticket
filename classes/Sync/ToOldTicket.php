<?php

namespace StackonetSupportTicket\Sync;

use Stackonet\Modules\SupportTicket\SupportTicket;
use StackonetSupportTicket\Upgrade\UpgradeCategories;
use StackonetSupportTicket\Upgrade\UpgradePriorities;
use StackonetSupportTicket\Upgrade\UpgradeStatus;
use StackonetSupportTicket\Upgrade\UpgradeThreads;
use WP_Post;
use wpdb;

defined( 'ABSPATH' ) || exit;

class ToOldTicket extends SyncTicket {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			if ( ! class_exists( SupportTicket::class ) ) {
				return self::$instance;
			}

			// Ticket
			add_action( 'stackonet_support_ticket/v3/ticket_created', [ self::$instance, 'ticket_created' ], 10, 1 );
			add_action( 'stackonet_support_ticket/v3/ticket_updated', [ self::$instance, 'ticket_updated' ], 10, 2 );
			add_action( 'stackonet_support_ticket/v3/ticket_deleted', [ self::$instance, 'ticket_deleted' ], 10, 2 );

			// Thread
			add_action( 'stackonet_support_ticket/v3/thread_created', [ self::$instance, 'thread_created' ], 10, 2 );
			add_action( 'stackonet_support_ticket/v3/thread_updated', [ self::$instance, 'thread_updated' ], 10, 3 );
			add_action( 'stackonet_support_ticket/v3/delete_thread', [ self::$instance, 'delete_thread' ], 10, 2 );

			// Ticket agents
			add_action( 'stackonet_support_ticket/v3/update_ticket_agent', [ self::$instance, 'update_agent' ], 10, 2 );
		}

		return self::$instance;
	}

	/**
	 * Get old ticket id from new ticket id
	 *
	 * @param int $new_ticket_id
	 *
	 * @return int
	 */
	public static function get_old_ticket_id( $new_ticket_id ) {
		/** @var wpdb $wpdb */
		global $wpdb;
		$meta_table = $wpdb->prefix . static::$ticket_meta_table['old'];
		$sql        = $wpdb->prepare( "SELECT meta_value FROM {$meta_table} WHERE meta_key = %s and meta_value = %s",
			'_new_ticket_id', $new_ticket_id );
		$row        = $wpdb->get_row( $sql, ARRAY_A );

		return isset( $row['meta_value'] ) ? intval( $row['meta_value'] ) : 0;
	}

	/**
	 * Clone ticket
	 *
	 * @param int $new_ticket_id
	 */
	public function ticket_created( $new_ticket_id ) {
		$new_data = static::get_ticket_data( $new_ticket_id, 'new' );

		$ticket                    = $new_data['ticket'];
		$ticket['ticket_category'] = UpgradeCategories::get_old_term_id( $ticket['ticket_category'] );
		$ticket['ticket_priority'] = UpgradePriorities::get_old_term_id( $ticket['ticket_priority'] );
		$ticket['ticket_status']   = UpgradeStatus::get_old_term_id( $ticket['ticket_status'] );

		$ticket_metadata = $new_data['ticket_metadata'];

		/** @var WP_Post[] $threads */
		$threads = $new_data['threads'];

		/** @var wpdb $wpdb */
		global $wpdb;
		$table      = $wpdb->prefix . static::$ticket_table['old'];
		$meta_table = $wpdb->prefix . static::$ticket_meta_table['old'];
		$post_type  = static::$post_type['old'];

		// Add ticket
		$wpdb->insert( $table, $ticket );
		$id = $wpdb->insert_id;

		if ( $id ) {
			foreach ( $ticket_metadata as $metadata ) {
				$wpdb->insert( $meta_table, [
					'ticket_id'  => $id,
					'meta_key'   => $metadata['meta_key'],
					'meta_value' => $metadata['meta_value']
				] );
			}

			static::record_ticket_id_on_both_table( $new_ticket_id, $id );

			foreach ( $threads as $new_thread ) {
				$old_thread_id = UpgradeThreads::clone_thread( $new_thread, $post_type );
				if ( $old_thread_id ) {
					update_post_meta( $new_thread->ID, '_old_thread_id', $old_thread_id );
					update_post_meta( $old_thread_id, '_new_thread_id', $new_thread->ID );
				}
			}
		}
	}

	/**
	 * Update ticket
	 *
	 * @param int $new_ticket_id
	 * @param array $data
	 */
	public function ticket_updated( $new_ticket_id, $data ) {
		$old_ticket_id = static::get_old_ticket_id( $new_ticket_id );
		$ticket        = ( new SupportTicket() )->find_by_id( $old_ticket_id );

		if ( $ticket instanceof SupportTicket ) {
			$ticket->update( $data );
		}
	}

	/**
	 * Delete ticket
	 *
	 * @param int $new_ticket_id
	 * @param string $action
	 */
	public function ticket_deleted( $new_ticket_id, $action ) {
		$old_ticket_id = static::get_old_ticket_id( $new_ticket_id );

		$ticket = ( new SupportTicket() )->find_by_id( $old_ticket_id );

		if ( $ticket instanceof SupportTicket ) {
			if ( 'trash' == $action ) {
				$ticket->trash( $new_ticket_id );
			}
			if ( 'restore' == $action ) {
				$ticket->restore( $new_ticket_id );
			}
			if ( 'delete' == $action ) {
				$ticket->delete( $new_ticket_id );
			}
		}
	}

	/**
	 * Clone thread
	 *
	 * @param int $new_ticket_id
	 * @param int $thread_id
	 */
	public function thread_created( $new_ticket_id, $thread_id ) {
		$old_ticket_id = static::get_old_ticket_id( $new_ticket_id );
		$thread        = get_post( $thread_id );
		$new_thread_id = UpgradeThreads::clone_thread( $thread, static::$post_type['old'], $old_ticket_id );
		if ( $new_thread_id ) {
			update_post_meta( $new_thread_id, '_new_thread_id', $thread->ID );
			update_post_meta( $thread->ID, '_old_thread_id', $new_thread_id );
		}
	}

	/**
	 * Update a thread
	 *
	 * @param int $new_ticket_id
	 * @param int $new_thread_id
	 * @param string $content
	 */
	public function thread_updated( $new_ticket_id, $new_thread_id, $content ) {
		$old_ticket_id = static::get_old_ticket_id( $new_ticket_id );
		$old_thread_id = (int) get_post_meta( $new_thread_id, '_old_thread_id', true );
		if ( $old_ticket_id && $old_thread_id ) {
			$my_post = array( 'ID' => $old_thread_id, 'post_content' => $content );
			wp_update_post( $my_post );
		}
	}

	/**
	 * Delete thread
	 *
	 * @param int $new_ticket_id
	 * @param int $new_thread_id
	 */
	public function delete_thread( $new_ticket_id, $new_thread_id ) {
		$old_ticket_id = static::get_old_ticket_id( $new_ticket_id );
		if ( $old_ticket_id && $new_thread_id ) {
			$old_thread_id = (int) get_post_meta( $new_thread_id, '_old_thread_id', true );

			wp_delete_post( $old_thread_id );
		}
	}

	/**
	 * Update support agents
	 *
	 * @param int $new_ticket_id
	 * @param array $agents_ids Array of WordPress user ids
	 */
	public function update_agent( $new_ticket_id, $agents_ids ) {
		$old_ticket_id = static::get_old_ticket_id( $new_ticket_id );
		$ticket        = ( new SupportTicket() )->find_by_id( $old_ticket_id );
		if ( $ticket instanceof SupportTicket ) {
			$ticket->update_agent( $agents_ids );
		}
	}
}
