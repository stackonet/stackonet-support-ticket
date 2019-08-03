<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wpscfunction, $current_user;

$from_name     = get_option( 'wpsc_en_from_name', '' );
$from_email    = get_option( 'wpsc_en_from_email', '' );
$reply_to      = get_option( 'wpsc_en_reply_to', '' );
$ignore_emails = get_option( 'wpsc_en_ignore_emails', '' );

if ( ! $from_name || ! $from_email ) {
	return;
}

$email_templates = get_terms( [
	'taxonomy'   => 'wpsc_en',
	'hide_empty' => false,
	'orderby'    => 'ID',
	'order'      => 'ASC',
	'meta_query' => array(
		'relation' => 'AND',
		array(
			'key'     => 'type',
			'value'   => 'ticket_reply',
			'compare' => '='
		),
	),
] );

foreach ( $email_templates as $email ) :

	$conditions = get_term_meta( $email->term_id, 'conditions', true );
	if ( $wpscfunction->check_ticket_conditions( $conditions, $ticket_id ) ) :

		$subject          = $wpscfunction->replace_macro( get_term_meta( $email->term_id, 'subject', true ), $ticket_id );
		$subject          = '[' . get_option( 'wpsc_ticket_alice', '' ) . $ticket_id . '] ' . $subject;
		$body             = $wpscfunction->replace_macro( get_term_meta( $email->term_id, 'body', true ), $ticket_id );
		$recipients       = get_term_meta( $email->term_id, 'recipients', true );
		$extra_recipients = get_term_meta( $email->term_id, 'extra_recipients', true );

		$email_addresses = array();
		foreach ( $recipients as $recipient ) {
			if ( is_numeric( $recipient ) ) {
				$agents = get_terms( [
					'taxonomy'   => 'wpsc_agents',
					'hide_empty' => false,
					'meta_query' => array(
						'relation' => 'AND',
						array(
							'key'     => 'role',
							'value'   => $recipient,
							'compare' => '='
						),
					),
				] );
				foreach ( $agents as $agent ) {
					$user_id = get_term_meta( $agent->term_id, 'user_id', true );
					if ( $user_id ) {
						$user              = get_user_by( 'id', $user_id );
						$email_addresses[] = $user->user_email;
					}
				}
			} else {
				switch ( $recipient ) {
					case 'customer':
						$customer_email    = $wpscfunction->get_ticket_fields( $ticket_id, 'customer_email' );
						$email_addresses[] = $customer_email;
						break;
					case 'assigned_agent':
						$email_addresses = array_merge( $email_addresses, $wpscfunction->get_assigned_agent_emails( $ticket_id ) );
						break;
				}
			}
		}
		if ( $extra_recipients ) {
			$email_addresses = array_merge( $email_addresses, $extra_recipients );
		}

		if ( isset( $_POST['reply_bcc'] ) ) {
			$reply_bcc       = explode( ',', sanitize_text_field( $_POST['reply_bcc'] ) );
			$reply_bcc       = array_map( 'trim', $reply_bcc );
			$email_addresses = array_merge( $email_addresses, $reply_bcc );
		}

		$email_addresses = array_unique( $email_addresses );
		$email_addresses = array_diff( $email_addresses, $ignore_emails );
		$last_reply      = $wpscfunction->get_last_reply( $ticket_id );
		$email_addresses = array_diff( $email_addresses, array( $last_reply['user_email'] ) );
		$email_addresses = apply_filters( 'wpsc_en_submit_reply_email_addresses', $email_addresses, $email, $thread_id, $ticket_id );
		$email_addresses = array_values( $email_addresses );

		$to = isset( $email_addresses[0] ) ? $email_addresses[0] : '';
		if ( $to ) {
			unset( $email_addresses[0] );
		} else {
			continue; // no email address found to send. So go to next foreach iteration.
		}

		$reply_to = $reply_to ? $reply_to : $from_email;

		// Customization filter
		$from_email = apply_filters( 'wpsc_reply_from_email_headers', $from_email, $ticket_id );
		$reply_to   = apply_filters( 'wpsc_reply_replyto_headers', $reply_to, $ticket_id );

		$headers = "From: {$from_name} <{$from_email}>\r\n";
		$headers .= "Reply-To: {$reply_to}\r\n";
		foreach ( $email_addresses as $email_address ) {
			$headers .= "BCC: {$email_address}\r\n";
		}
		$headers .= "Content-Type: text/html; charset=utf-8\r\n";

		wp_mail( $to, $subject, $body, $headers );

	endif;

endforeach;
