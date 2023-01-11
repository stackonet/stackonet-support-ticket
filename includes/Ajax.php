<?php

namespace StackonetSupportTicket;

use StackonetSupportTicket\Models\SupportAgent;
use StackonetSupportTicket\Models\SupportTicket;
use StackonetSupportTicket\Models\TicketCategory;
use StackonetSupportTicket\Models\TicketPriority;
use StackonetSupportTicket\Models\TicketStatus;
use StackonetSupportTicket\Supports\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ajax
 *
 * @package StackonetSupportTicket
 */
class Ajax {

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

			add_action( 'wp_ajax_support_ticket_activation', [ self::$instance, 'support_ticket_activation' ] );
			add_action( 'wp_ajax_support_ticket_deactivation', [ self::$instance, 'support_ticket_deactivation' ] );
			add_action( 'wp_ajax_download_support_ticket', [ self::$instance, 'download_support_ticket' ] );
			add_action( 'wp_ajax_nopriv_download_support_ticket', [ self::$instance, 'download_support_ticket' ] );
		}

		return self::$instance;
	}

	public function support_ticket_activation() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You have no permission to download phones CSV file.' );
		}
		Install::init();
		wp_die( 'Support ticket activated successfully.' );
	}

	public function support_ticket_deactivation() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You have no permission to download phones CSV file.' );
		}
		Uninstall::run();
		wp_die( 'Support ticket deactivated successfully.' );
	}

	public function download_support_ticket() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You have no permission to download phones CSV file.' );
		}

		$ticket_status   = $_GET['ticket_status'] ?? '';
		$ticket_category = $_GET['ticket_category'] ?? '';
		$ticket_priority = $_GET['ticket_priority'] ?? '';

		$_statuses   = TicketStatus::get_all();
		$_categories = TicketCategory::get_all();
		$_priorities = TicketPriority::get_all();
		$_agents     = SupportAgent::get_all();

		$statuses = $categories = $priorities = $agents = [];

		foreach ( $_statuses as $status ) {
			$statuses[ $status->get_prop( 'term_id' ) ] = $status->to_array();
		}

		foreach ( $_priorities as $status ) {
			$priorities[ $status->get_prop( 'term_id' ) ] = $status->to_array();
		}

		foreach ( $_categories as $status ) {
			$categories[ $status->get_prop( 'term_id' ) ] = $status->to_array();
		}

		foreach ( $_agents as $agent ) {
			$agents[ $agent->get_user_id() ] = $agent->to_array();
		}

		$items = ( new SupportTicket() )->find(
			[
				'paged'           => 1,
				'per_page'        => 1000,
				'ticket_status'   => $ticket_status,
				'ticket_category' => $ticket_category,
				'ticket_priority' => $ticket_priority,
			]
		);

		$filename = sprintf( 'support-tickets-%s-%s-%s.csv', $ticket_status, $ticket_category, $ticket_priority );

		$header = [
			'Ticket ID',
			'Subject',
			'Status',
			'Name',
			'Email Address',
			'Phone',
			'Assigned Agents',
			'Category',
			'Priority',
			'Created',
		];

		$rows = [ $header ];

		/** @var SupportTicket[] $items */
		foreach ( $items as $ticket ) {
			$status   = $ticket->get_prop( 'ticket_status' );
			$status   = isset( $statuses[ $status ] ) ? $statuses[ $status ]['name'] : $status;
			$category = $ticket->get_prop( 'ticket_category' );
			$category = isset( $categories[ $category ] ) ? $categories[ $category ]['name'] : $category;
			$priority = $ticket->get_prop( 'ticket_priority' );
			$priority = isset( $priorities[ $priority ] ) ? $priorities[ $priority ]['name'] : $priority;

			$__agents   = [];
			$agents_ids = $ticket->get_assigned_agents_ids();
			foreach ( $agents_ids as $agent_id ) {
				if ( ! $agent_id ) {
					continue;
				}
				$__agents[] = isset( $agents[ $agent_id ] ) ? $agents[ $agent_id ]['display_name'] : '';

			}

			$rows[] = [
				$ticket->get_prop( 'id' ),
				$ticket->get_prop( 'ticket_subject' ),
				$status,
				$ticket->get_prop( 'customer_name' ),
				$ticket->get_prop( 'customer_email' ),
				$ticket->get_prop( 'customer_phone' ),
				implode( ', ', $__agents ),
				$category,
				$priority,
				$ticket->update_at(),
			];
		}

		@header( 'Content-Description: File Transfer' );
		@header( 'Content-Type: text/csv; charset=UTF-8' );
		@header( 'Content-Disposition: filename="' . $filename . '"' );
		@header( 'Expires: 0' );
		@header( 'Cache-Control: must-revalidate' );
		@header( 'Pragma: public' );
		echo Utils::generateCsv( $rows );
		die();
	}
}