<?php

namespace StackonetSupportTicket\REST;

use Exception;
use StackonetSupportTicket\Models\SupportTicket;
use StackonetSupportTicket\Models\TicketThread;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) or exit;

class TicketController extends ApiController {

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
		register_rest_route( $this->namespace, '/tickets', [
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'create_item' ],
				'args'     => $this->get_create_item_params(),
			],
		] );
	}

	/**
	 * Creates one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 * @throws Exception
	 */
	public function create_item( $request ) {
		$name           = $request->get_param( 'name' );
		$email          = $request->get_param( 'email' );
		$subject        = $request->get_param( 'subject' );
		$ticket_content = $request->get_param( 'content' );
		$phone_number   = $request->get_param( 'phone_number' );

		$ticket_category = $request->get_param( 'category' );
		$ticket_status   = $request->get_param( 'status' );
		$ticket_priority = $request->get_param( 'priority' );

		$attachments = $request->get_param( 'attachments' );
		if ( ! empty( $attachments ) ) {
			$attachments = is_array( $attachments ) ? array_map( 'intval', $attachments ) : [];
		}

		$default_category = (int) get_option( 'support_ticket_default_category' );
		$default_status   = (int) get_option( 'support_ticket_default_status' );
		$default_priority = (int) get_option( 'support_ticket_default_priority' );

		$data = [
			'ticket_subject'   => $subject,
			'customer_name'    => $name,
			'customer_email'   => $email,
			'user_type'        => get_current_user_id() ? 'user' : 'guest',
			'ticket_category'  => ! empty( $ticket_category ) ? $ticket_category : $default_category,
			'ticket_status'    => ! empty( $ticket_status ) ? $ticket_status : $default_status,
			'ticket_priority'  => ! empty( $ticket_priority ) ? $ticket_priority : $default_priority,
			'ip_address'       => self::get_remote_ip(),
			'agent_created'    => get_current_user_id(),
			'ticket_auth_code' => bin2hex( random_bytes( 5 ) ),
			'active'           => 1
		];

		$ticket_id = ( new SupportTicket )->create( $data );

		if ( ! empty( $ticket_id ) ) {
			$html = $this->get_ticket_content( $name, $phone_number, $ticket_content );

			$thread_data = [
				'ticket_id'      => $ticket_id,
				'post_content'   => $html,
				'customer_name'  => $name,
				'customer_email' => $email,
				'thread_type'    => 'report',
				'attachments'    => $attachments,
			];

			$thread_id = ( new TicketThread )->create( $thread_data );

			return $this->respondCreated( [ 'ticket_id' => $ticket_id, 'thread_id' => $thread_id ] );
		}

		return $this->respondInternalServerError();
	}

	/**
	 * Retrieves the query params for the collections.
	 *
	 * @return array Query parameters for the collection.
	 */
	public function get_create_item_params() {
		return array(
			'name'         => array(
				'description'       => __( 'User full name.', 'stackonet-support-ticker' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'email'        => array(
				'description'       => __( 'User email address.', 'stackonet-support-ticker' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'phone_number' => array(
				'description'       => __( 'User phone number.', 'stackonet-support-ticker' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'subject'      => array(
				'description'       => __( 'Ticket subject.', 'stackonet-support-ticker' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'content'      => array(
				'description'       => __( 'Ticket content.', 'stackonet-support-ticker' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => 'rest_validate_request_arg',
			),
			'category'     => array(
				'description'       => __( 'Ticket category.', 'stackonet-support-ticker' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'intval',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'status'       => array(
				'description'       => __( 'Ticket status.', 'stackonet-support-ticker' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'intval',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'priority'     => array(
				'description'       => __( 'Ticket priority.', 'stackonet-support-ticker' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'intval',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'attachments'  => array(
				'description'       => __( 'Array of WordPress media ID.', 'stackonet-support-ticker' ),
				'type'              => 'array',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Get user IP address
	 *
	 * @return string
	 */
	public static function get_remote_ip() {
		$server_ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $server_ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
				return $_SERVER[ $key ];
			}
		}

		// Fallback local ip.
		return '';
	}

	/**
	 * @param string $name
	 * @param string $phone
	 * @param string $content
	 *
	 * @return false|string
	 */
	public function get_ticket_content( $name, $phone, $content ) {
		ob_start(); ?>
        <table class="table--support-ticket">
            <tr>
                <td>Name:</td>
                <td><strong><?php echo $name ?></strong></td>
            </tr>
            <tr>
                <td>Phone:</td>
                <td><strong><?php echo $phone ?></strong></td>
            </tr>
            <tr>
                <td>Content:</td>
                <td><strong><?php echo $content; ?></strong></td>
            </tr>
        </table>
		<?php
		$html = ob_get_clean();

		return $html;
	}
}