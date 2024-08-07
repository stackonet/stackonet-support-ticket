<?php

namespace StackonetSupportTicket\REST;

use Stackonet\WP\Framework\Media\UploadedFile;
use Stackonet\WP\Framework\Media\Uploader;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use Stackonet\WP\Framework\Traits\ApiResponse;
use Stackonet\WP\Framework\Traits\ApiUtils;
use StackonetSupportTicket\Admin\Settings;
use StackonetSupportTicket\Models\SupportTicket;
use StackonetSupportTicket\Models\TicketThread;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class ApiController extends WP_REST_Controller {
	use ApiResponse, ApiUtils, ApiPermissionChecker;

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = STACKONET_SUPPORT_TICKET_REST_NAMESPACE;

	/**
	 * @param  WP_REST_Request  $request
	 *
	 * @return int[]|WP_Error
	 */
	protected function get_attachments_ids( WP_REST_Request $request ) {
		$files = self::handle_file_upload();
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$attachments     = is_array( $files ) && count( $files ) ? $files : [];
		$attachments_ids = $request->get_param( 'attachments' );
		if ( is_array( $attachments_ids ) ) {
			$attachments = array_merge( $attachments, $attachments_ids );
		}

		return $attachments;
	}

	/**
	 * Handle attachment upload
	 *
	 * @return array|WP_Error
	 */
	protected static function handle_file_upload() {
		$files = UploadedFile::getUploadedFiles();
		if ( ! isset( $files['files'] ) ) {
			return [];
		}

		$attachments = $files['files'] instanceof UploadedFile ? [ $files['files'] ] : $files['files'];

		$error = new WP_Error();
		$ids   = [];
		foreach ( $attachments as $attachment ) {
			if ( ! $attachment instanceof UploadedFile ) {
				continue;
			}
			if ( $attachment->getSize() > ( 5 * MB_IN_BYTES ) ) {
				$error->add( 'large_file_size', 'Max allowed file size is 5MB.' );
				continue;
			}
			$response = Uploader::uploadSingleFile( $attachment );
			if ( is_wp_error( $response ) ) {
				$error->add( $response->get_error_code(), $response->get_error_message(), $response->get_error_data() );
				continue;
			}

			$ids[] = $response;
		}

		if ( $error->has_errors() ) {
			foreach ( $ids as $id ) {
				wp_delete_attachment( $id, true );
			}

			return $error;
		}

		return $ids;
	}

	/**
	 * @param  TicketThread  $thread
	 *
	 * @return array
	 */
	protected static function format_thread_for_response( TicketThread $thread ): array {
		return [
			'id'          => $thread->get_id(),
			'content'     => $thread->get_thread_content(),
			'creator'     => [
				'name'   => $thread->get_prop( 'user_name' ),
				'email'  => $thread->get_prop( 'user_email' ),
				'avatar' => $thread->get_avatar_url(),
				'type'   => $thread->get_user_type(),
			],
			'created'     => mysql_to_rfc3339( $thread->get_created_at() ),
			'thread_type' => $thread->get_thread_type(),
			'attachments' => $thread->get_attachments(),
		];
	}

	/**
	 * @param  array  $threads
	 *
	 * @return array
	 */
	protected static function format_thread_collections( array $threads ): array {
		$data = [];
		foreach ( $threads as $thread ) {
			if ( $thread instanceof TicketThread ) {
				$data[] = static::format_thread_for_response( $thread );
			}
		}

		return $data;
	}

	/**
	 * @param  SupportTicket  $ticket
	 *
	 * @return array
	 */
	protected static function format_item_for_response( SupportTicket $ticket ): array {
		$meta_labels = Settings::get_custom_fields_labels();
		$user_fields = Settings::get_user_custom_fields();
		$metadata    = [];
		foreach ( $ticket->get_all_metadata() as $meta_key => $meta_value ) {
			if ( isset( $user_fields[ $meta_key ] ) && true === $user_fields[ $meta_key ] ) {
				$metadata[ $meta_key ] = [
					'label' => ! empty( $meta_labels[ $meta_key ] ) ? $meta_labels[ $meta_key ] : $meta_key,
					'value' => $meta_value,
				];
			}
		}

		return [
			'id'            => intval( $ticket->get_prop( 'id' ) ),
			'subject'       => $ticket->get_prop( 'ticket_subject' ),
			'created'       => mysql_to_rfc3339( $ticket->get_prop( 'date_created' ) ),
			'updated'       => mysql_to_rfc3339( $ticket->get_prop( 'date_updated' ) ),
			'status'        => $ticket->get_ticket_status(),
			'category'      => $ticket->get_ticket_category(),
			'priority'      => $ticket->get_ticket_priority(),
			'creator'       => [
				'id'     => intval( $ticket->get_prop( 'agent_created' ) ),
				'name'   => $ticket->get_prop( 'customer_name' ),
				'email'  => $ticket->get_prop( 'customer_email' ),
				'avatar' => get_avatar_url( $ticket->get_prop( 'customer_email' ) ),
				'phone'  => $ticket->get_prop( 'customer_phone' ),
				'city'   => $ticket->get_prop( 'city' ),
				'type'   => $ticket->get_prop( 'user_type' ),
			],
			'metadata'      => $metadata,
			'unread_thread' => $ticket->get_user_unread_threads_count(),
		];
	}
}

