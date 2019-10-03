<?php

namespace StackonetSupportTicket\REST;

use StackonetSupportTicket\Models\TicketPriority;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) or exit;

class PriorityController extends ApiController {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Only one instance of the class can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;

			add_action( 'rest_api_init', array( self::$instance, 'register_routes' ) );
		}

		return self::$instance;
	}

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/priorities', [
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_items' ],
			],
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'create_item' ],
				'args'     => $this->get_create_item_params()
			],
		] );
		register_rest_route( $this->namespace, '/priorities/(?P<id>\d+)', [
			[
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => [ $this, 'update_item' ],
			],
			[
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => [ $this, 'delete_item' ],
			],
		] );
		register_rest_route( $this->namespace, '/priorities/batch', [
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'update_batch_items' ],
				'args'     => $this->get_batch_update_params()
			],
		] );
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$items = TicketPriority::get_all();

		return $this->respondOK( [ 'items' => $items ] );
	}

	/**
	 * Creates one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$name        = $request->get_param( 'name' );
		$slug        = $request->get_param( 'slug' );
		$description = $request->get_param( 'description' );
		$parent      = $request->get_param( 'parent' );

		$args = [];
		if ( ! empty( $slug ) ) {
			$args['slug'] = $slug;
		}
		if ( ! empty( $description ) ) {
			$args['description'] = $description;
		}
		if ( ! empty( $parent ) ) {
			$args['parent'] = $parent;
		}

		$status_id = TicketPriority::create( $name, $args );
		if ( is_wp_error( $status_id ) ) {
			if ( 'term_exists' == $status_id->get_error_code() ) {
				return $this->respondUnprocessableEntity( 'status_exists', 'Status already exists.' );
			}

			return $status_id;
		}

		return $this->respondCreated( [ 'status_id' => $status_id ] );
	}

	/**
	 * Updates one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$name = $request->get_param( 'name' );
		$slug = $request->get_param( 'slug' );

		$category = TicketPriority::find_by_id( $id );
		if ( ! $category instanceof TicketPriority ) {
			return $this->respondNotFound( null, 'No ticket priority found with this id.' );
		}

		$response = TicketPriority::update( $id, $name, $slug );
		if ( is_wp_error( $response ) ) {
			return $this->respondUnprocessableEntity( $response->get_error_code(), $response->get_error_message() );
		}

		return $this->respondOK( $response );
	}

	/**
	 * Update batch items
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function update_batch_items( $request ) {
		$menu_orders = $request->get_param( 'menu_orders' );
		if ( count( $menu_orders ) > 0 ) {
			TicketPriority::update_menu_orders( $menu_orders );
		}

		return $this->respondOK();
	}


	/**
	 * Deletes one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( TicketPriority::delete( $id ) ) {
			return $this->respondOK( [ 'id' => $id ] );
		}

		return $this->respondInternalServerError();
	}

	/**
	 * Get create items args
	 *
	 * @return array
	 */
	public function get_create_item_params() {
		return [
			'name'        => [
				'description'       => __( 'Priority name.', 'stackonet-support-ticker' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'slug'        => [
				'description'       => __( 'Priority slug. Must be unique for priorities.', 'stackonet-support-ticker' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'description' => [
				'description'       => __( 'Priority description.', 'stackonet-support-ticker' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'parent'      => [
				'description'       => __( 'Parent priority ID.', 'stackonet-support-ticker' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'intval',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	/**
	 * Get batch update items args
	 *
	 * @return array
	 */
	public function get_batch_update_params() {
		return [
			'menu_orders' => [
				'description'       => __( 'Array of all priorities ID. New order will be set by numeric order.', 'stackonet-support-ticker' ),
				'type'              => 'array',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}
}
