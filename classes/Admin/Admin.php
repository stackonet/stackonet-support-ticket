<?php

namespace StackonetSupportTicket\Admin;

use StackonetSupportTicket\Models\SupportAgent;
use StackonetSupportTicket\Models\SupportTicket;
use StackonetSupportTicket\Models\TicketCategory;
use StackonetSupportTicket\Models\TicketPriority;
use StackonetSupportTicket\Models\TicketStatus;
use WP_Post;

defined( 'ABSPATH' ) || exit;

class Admin {

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

			add_action( 'admin_enqueue_scripts', [ self::$instance, 'admin_localize_scripts' ] );
			add_action( 'admin_menu', [ self::$instance, 'add_menu' ], 9 );
		}

		return self::$instance;
	}

	/**
	 * Admin scripts
	 */
	public function admin_localize_scripts() {
		/** @var WP_Post[] $pages */
		$pages  = get_pages();
		$_pages = [];
		foreach ( $pages as $page ) {
			$_pages[] = [ 'id' => $page->ID, 'title' => $page->post_title ];
		}
		wp_localize_script( 'jquery', 'stackonetSettings', array(
			'root'  => esc_url_raw( rest_url( 'stackonet-support-ticker/v1' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'pages' => $_pages,
		) );
	}

	/**
	 * Add top level menu
	 */
	public function add_menu() {
		$capability = 'manage_options';
		$slug       = 'wpsc-tickets';

		$hook = add_menu_page( __( 'Support', 'stackonet-support-ticket' ), __( 'Support', 'stackonet-support-ticket' ),
			$capability, $slug, [ self::$instance, 'support_tickets_callback' ], 'dashicons-admin-post', 8 );

		add_action( 'load-' . $hook, [ self::$instance, 'init_support_tickets_hooks' ] );
	}

	/**
	 * Menu page callback
	 */
	public function support_tickets_callback() {
		add_action( 'admin_footer', [ $this, 'tinymce_script' ], 9 );
		echo '<div class="wrap"><div id="admin-stackonet-support-tickets"></div></div>';
	}

	/**
	 * Load tinymce scripts
	 */
	public function tinymce_script() {
		echo '<script type="text/javascript" src="' . includes_url( 'js/tinymce/tinymce.min.js' ) . '"></script>';
	}

	/**
	 * Load required styles and scripts
	 */
	public static function init_support_tickets_hooks() {
		wp_enqueue_style( 'stackonet-support-ticket-admin' );
		wp_enqueue_script( 'stackonet-support-ticket-admin' );

		$data          = [];
		$supportTicket = new SupportTicket();

		$_statuses        = $supportTicket->get_ticket_statuses_terms();
		$data['statuses'] = [ [ 'key' => 'all', 'label' => 'All Statuses', 'count' => 0, 'active' => true ] ];
		foreach ( $_statuses as $status ) {
			$data['statuses'][] = [ 'key' => $status->term_id, 'label' => $status->name, 'count' => 0, ];
		}
		$data['statuses'][] = [ 'key' => 'trash', 'label' => 'Trash', 'count' => 0, 'active' => false ];


		$_categories        = $supportTicket->get_categories_terms();
		$data['categories'] = [ [ 'key' => 'all', 'label' => 'All Categories', 'count' => 0, 'active' => true ] ];
		foreach ( $_categories as $status ) {
			$data['categories'][] = [ 'key' => $status->term_id, 'label' => $status->name, 'count' => 0, ];
		}

		$_priorities        = $supportTicket->get_priorities_terms();
		$data['priorities'] = [ [ 'key' => 'all', 'label' => 'All Priorities', 'count' => 0, 'active' => true ] ];
		foreach ( $_priorities as $status ) {
			$data['priorities'][] = [ 'key' => $status->term_id, 'label' => $status->name, 'count' => 0, ];
		}

		$data['count_trash'] = $supportTicket->count_trash_records();

		$data['ticket_categories'] = TicketCategory::get_all();
		$data['ticket_statuses']   = TicketStatus::get_all();
		$data['ticket_priorities'] = TicketPriority::get_all();
		$data['support_agents']    = SupportAgent::get_all();

		$user           = wp_get_current_user();
		$data['user']   = [
			'display_name' => $user->display_name,
			'user_email'   => $user->user_email,
		];
		$data['cities'] = ( new SupportTicket() )->find_all_cities();

		$data['search_categories'] = (array) get_option( 'stackonet_ticket_search_categories' );

		wp_localize_script( 'stackonet-support-ticket-admin', 'SupportTickets', $data );
	}
}
