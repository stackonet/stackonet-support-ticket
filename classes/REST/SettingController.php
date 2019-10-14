<?php

namespace StackonetSupportTicket\REST;

use StackonetSupportTicket\Supports\SettingHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SettingController extends ApiController {

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
		register_rest_route( $this->namespace, '/settings', [
			[ 'methods' => WP_REST_Server::READABLE, 'callback' => [ $this, 'get_settings' ] ],
			[ 'methods' => WP_REST_Server::EDITABLE, 'callback' => [ $this, 'update_settings' ] ],
		] );
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->respondUnauthorized();
		}

		$settings = SettingHandler::init();

		return $this->respondOK( $settings->to_array() );
	}

	/**
	 * Updates settings
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function update_settings( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->respondUnauthorized();
		}

		$settings = SettingHandler::init();
		$options  = $request->get_param( 'options' );
		$settings->update( $options );

		return $this->respondOK();
	}
}