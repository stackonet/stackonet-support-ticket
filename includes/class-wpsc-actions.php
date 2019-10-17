<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WPSC_Actions' ) ) :

	final class WPSC_Actions {

		// constructor
		public function __construct() {
			add_action( 'init', array( $this, 'load_actions' ) );
			add_action( 'init', array( $this, 'check_download_file' ) );
		}

		// Load actions
		function load_actions() {

			// Log Entry
			add_action( 'wpsc_after_submit_reply', array( $this, 'submit_reply' ) );
			add_action( 'wpsc_set_change_status', array( $this, 'change_status' ), 10, 3 );
			add_action( 'wpsc_set_change_category', array( $this, 'change_category' ), 10, 3 );
			add_action( 'wpsc_set_change_priority', array( $this, 'change_priority' ), 10, 3 );
			add_action( 'wpsc_set_assign_agent', array( $this, 'assigned_agent' ), 10, 3 );
			add_action( 'wpsc_set_change_raised_by', array( $this, 'change_raised_by' ), 10, 4 );
			add_action( 'wpsc_set_change_fields', array( $this, 'change_field' ), 10, 4 );

			// Label Counts
			add_action( 'support_ticket_created', array( $this, 'ticket_create_label_count' ) );
			add_action( 'wpsc_set_change_status', array( $this, 'change_status_label_count' ), 10, 3 );
			add_action( 'wpsc_set_assign_agent', array( $this, 'assigned_agent_label_count' ), 10, 3 );
			add_action( 'wpsc_set_delete_ticket', array( $this, 'delete_label_count' ) );
			add_action( 'wpsc_restore_ticket', array( $this, 'restore_label_count' ) );

			// Email Notifications
			add_action( 'support_ticket_created', array( $this, 'en_ticket_created' ), 100 );
			add_action( 'wpsc_after_submit_reply', array( $this, 'en_submit_reply' ), 100, 2 );
			add_action( 'wpsc_after_submit_note', array( $this, 'en_submit_note' ), 100, 2 );
			add_action( 'wpsc_set_change_status', array( $this, 'en_change_status' ), 100, 3 );
			add_action( 'wpsc_set_change_category', array( $this, 'en_change_category' ), 100, 3 );
			add_action( 'wpsc_set_change_priority', array( $this, 'en_change_priority' ), 100, 3 );
			add_action( 'wpsc_set_assign_agent', array( $this, 'en_assign_agent' ), 100, 3 );
			add_action( 'wpsc_set_delete_ticket', array( $this, 'en_delete_ticket' ), 100 );
			add_action( 'wpsc_cron', array( $this, 'wpsc_check_cron_attachment' ) );

			// User profile update
			add_action( 'profile_update', array( $this, 'my_profile_update' ), 10, 2 );

			// GDPR
			add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'wpsc_register_privacy_exporters' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'wpsc_register_privacy_erasers' ) );
			add_action( 'wpsc_cron', array( $this, 'wpsc_gdpr_personal_data_eraser' ) );

			do_action( 'after_wpsc_actions_loaded' );

		}

		// Submit reply actions
		function submit_reply( $thread_id ) {

			global $wpscfunction, $current_user;
			// Thread fields
			$ticket_id      = get_post_meta( $thread_id, 'ticket_id', true );
			$customer_name  = get_post_meta( $thread_id, 'customer_name', true );
			$customer_email = get_post_meta( $thread_id, 'customer_email', true );
			$user           = get_user_by( 'email', $customer_email );
			// Ticket fields
			$ticket_data                             = $wpscfunction->get_ticket( $ticket_id );
			$ticket_customer_email                   = $ticket_data['customer_email'];
			$ticket_status                           = $ticket_data['ticket_status'];
			$support_ticket_status_after_customer_reply = get_option( 'support_ticket_status_after_customer_reply' );

			if ( $user && $user->ID && $user->has_cap( 'wpsc_agent' ) ) { // Reply by agent
				$support_ticket_status_after_agent_reply = get_option( 'support_ticket_status_after_agent_reply' );
				if ( $support_ticket_status_after_agent_reply && $ticket_status != $support_ticket_status_after_agent_reply ) {
					$wpscfunction->change_status( $ticket_id, $support_ticket_status_after_agent_reply );
				}
			} else if ( $ticket_status != $support_ticket_status_after_customer_reply ) {
				if ( $support_ticket_status_after_customer_reply && $ticket_status != $support_ticket_status_after_customer_reply ) {
					$wpscfunction->change_status( $ticket_id, $support_ticket_status_after_customer_reply );
				}
			}
		}

		// Change status
		function change_status( $ticket_id, $status_id, $prev_status ) {
			global $wpscfunction, $current_user;
			$status_obj      = get_term_by( 'id', $status_id, 'ticket_status' );
			$prev_status_obj = get_term_by( 'id', $prev_status, 'ticket_status' );
			if ( $current_user->ID ) {
				$log_str = sprintf( __( '%1$s changed status from %2$s to %3$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $prev_status_obj->name . '</strong>', '<strong>' . $status_obj->name . '</strong>' );
			} else {
				$log_str = sprintf( __( 'status changed to %1$s', 'supportcandy' ), '<strong>' . $status_obj->name . '</strong>' );
			}
			$args = array(
				'ticket_id'   => $ticket_id,
				'reply_body'  => $log_str,
				'thread_type' => 'log'
			);
			$args = apply_filters( 'wpsc_thread_args', $args );
			$wpscfunction->submit_ticket_thread( $args );
		}

		// Change category
		function change_category( $ticket_id, $category_id, $prev_cat ) {
			global $wpscfunction, $current_user;
			$category_obj      = get_term_by( 'id', $category_id, 'ticket_category' );
			$prev_category_obj = get_term_by( 'id', $prev_cat, 'ticket_category' );
			if ( $current_user->ID ) {
				$log_str = sprintf( __( '%1$s changed category from %2$s to %3$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $prev_category_obj->name . '</strong>', '<strong>' . $category_obj->name . '</strong>' );
			} else {
				$log_str = sprintf( __( 'category changed to %1$s', 'supportcandy' ), '<strong>' . $category_obj->name . '</strong>' );
			}
			$args = array(
				'ticket_id'   => $ticket_id,
				'reply_body'  => $log_str,
				'thread_type' => 'log'
			);
			$args = apply_filters( 'wpsc_thread_args', $args );
			$wpscfunction->submit_ticket_thread( $args );
		}

		// Change priority
		function change_priority( $ticket_id, $priority_id, $prev_priority ) {
			global $wpscfunction, $current_user;
			$priority_obj      = get_term_by( 'id', $priority_id, 'ticket_priority' );
			$prev_priority_obj = get_term_by( 'id', $prev_priority, 'ticket_priority' );
			if ( $current_user->ID ) {
				$log_str = sprintf( __( '%1$s changed priority from %2$s to %3$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $prev_priority_obj->name . '</strong>', '<strong>' . $priority_obj->name . '</strong>' );
			} else {
				$log_str = sprintf( __( 'priority changed to %1$s', 'supportcandy' ), '<strong>' . $priority_obj->name . '</strong>' );
			}
			$args = array(
				'ticket_id'   => $ticket_id,
				'reply_body'  => $log_str,
				'thread_type' => 'log'
			);
			$args = apply_filters( 'wpsc_thread_args', $args );
			$wpscfunction->submit_ticket_thread( $args );
		}

		// Assign agent
		function assigned_agent( $ticket_id, $agents, $prev_assigned ) {
			global $wpscfunction, $current_user;
			$assigned_agent_names = $wpscfunction->get_assigned_agent_names( $ticket_id );
			$prev_agent_names     = array();
			foreach ( $prev_assigned as $agent_id ) {
				$prev_agent_names[] = $wpscfunction->get_agent_name( $agent_id );
			}
			$prev_assigned_agent_names = implode( ', ', $prev_agent_names );
			if ( $current_user && $current_user->has_cap( 'wpsc_agent' ) && $prev_assigned_agent_names ) {
				$log_str = sprintf( __( '%1$s changed assign agent from %2$s to %3$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $prev_assigned_agent_names . '</strong>', '<strong>' . $assigned_agent_names . '</strong>' );
			} else if ( $current_user && $current_user->has_cap( 'wpsc_agent' ) ) {
				$log_str = sprintf( __( '%1$s changed assign agent to %2$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $assigned_agent_names . '</strong>' );
			} else {
				$log_str = sprintf( __( 'assign agent changed to %1$s', 'supportcandy' ), '<strong>' . $assigned_agent_names . '</strong>' );
			}
			$args = array(
				'ticket_id'   => $ticket_id,
				'reply_body'  => $log_str,
				'thread_type' => 'log'
			);
			$args = apply_filters( 'wpsc_thread_args', $args );
			$wpscfunction->submit_ticket_thread( $args );
		}

		// Create Ticket label count
		function ticket_create_label_count( $ticket_id ) {
			$this->execute_label_count( 'create_ticket', $ticket_id, array(), array(), 0 );
		}

		// Change status label count
		function change_status_label_count( $ticket_id, $status_id, $prev_status ) {
			global $wpscfunction;
			$assigned_agents = $wpscfunction->get_ticket_meta( $ticket_id, 'assigned_agent' );
			$assigned_agents = $assigned_agents[0] ? $assigned_agents : array();
			$this->execute_label_count( 'change_status', $ticket_id, array(), $assigned_agents, $prev_status );
		}

		// Assigned agent label count
		function assigned_agent_label_count( $ticket_id, $new_assigned, $prev_assigned ) {
			$this->execute_label_count( 'assign_agent', $ticket_id, $prev_assigned, $new_assigned, 0 );
		}

		function delete_label_count( $ticket_id ) {
			global $wpscfunction;
			$assigned_agents = $wpscfunction->get_ticket_meta( $ticket_id, 'assigned_agent' );
			$assigned_agents = $assigned_agents[0] ? $assigned_agents : array();
			$this->execute_label_count( 'delete_ticket', $ticket_id, $assigned_agents, array(), 0 );
		}

		function restore_label_count( $ticket_id ) {
			global $wpscfunction;
			$assigned_agents = $wpscfunction->get_ticket_meta( $ticket_id, 'assigned_agent' );
			$assigned_agents = $assigned_agents[0] ? $assigned_agents : array();
			$this->execute_label_count( 'restore_ticket', $ticket_id, array(), $assigned_agents, 0 );
		}

		// Execute label count
		function execute_label_count( $event, $ticket_id, $prev_assigned, $new_assigned, $prev_status ) {
			global $wpscfunction;
			$unresolved_statuses = get_option( 'wpsc_tl_agent_unresolve_statuses' );
			$ticket_status       = $wpscfunction->get_ticket_fields( $ticket_id, 'ticket_status' );
			$agent_role          = get_option( 'support_ticket_agent_roles' );

			$agents = get_terms( [
				'taxonomy'   => 'support_agent',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'     => 'agentgroup',
						'value'   => '0',
						'compare' => '='
					)
				)
			] );

			foreach ( $agents as $agent ) {
				$user_id      = get_term_meta( $agent->term_id, 'user_id', true );
				$role_id      = get_term_meta( $agent->term_id, 'role', true );
				$permissions  = $agent_role[ $role_id ];
				$label_counts = get_user_meta( $user_id, 'wpsc_' . get_current_blog_id() . '_label_counts', true );
				// Create Ticket
				if ( $event == 'create_ticket' && $label_counts && $permissions['view_unassigned'] == 1 && in_array( $ticket_status, $unresolved_statuses ) ) {
					$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] + 1;
				}
				// Change Status
				if ( $event == 'change_status' && $label_counts && ! ( in_array( $prev_status, $unresolved_statuses ) && in_array( $ticket_status, $unresolved_statuses ) ) ) {
					if ( in_array( $prev_status, $unresolved_statuses ) && ! $new_assigned && $permissions['view_unassigned'] == 1 ) {
						$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] - 1;
					}
					if ( in_array( $prev_status, $unresolved_statuses ) && $new_assigned && ( in_array( $agent->term_id, $new_assigned ) || $permissions['view_assigned_others'] ) ) {
						$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] - 1;
					}
					if ( in_array( $ticket_status, $unresolved_statuses ) && ! $new_assigned && $permissions['view_unassigned'] == 1 ) {
						$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] + 1;
					}
					if ( in_array( $ticket_status, $unresolved_statuses ) && $new_assigned && ( in_array( $agent->term_id, $new_assigned ) || $permissions['view_assigned_others'] ) ) {
						$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] + 1;
					}
				}

				// Assign Agent
				if ( $event == 'assign_agent' && $label_counts && ! ( in_array( $agent->term_id, $prev_assigned ) && in_array( $agent->term_id, $new_assigned ) ) ) {
					$checkpoint = array();
					if ( $permissions['view_unassigned'] == 1 && $prev_assigned && ! $new_assigned ) {
						if ( in_array( $ticket_status, $unresolved_statuses ) && ! $permissions['view_assigned_others'] ) {
							$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] + 1;
						}
					}
					if ( $permissions['view_unassigned'] == 1 && ! $prev_assigned && $new_assigned ) {
						if ( in_array( $ticket_status, $unresolved_statuses ) && ! $permissions['view_assigned_others'] ) {
							$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] - 1;
						}
					}
					$mine_decrease_flag = in_array( $agent->term_id, $prev_assigned ) ? true : false;
					$mine_decrease      = apply_filters( 'wpsc_unresolved_count_decrease', $mine_decrease_flag, $ticket_id, $agent, $user_id, $prev_assigned, $event );
					if ( $permissions['view_assigned_me'] == 1 && $mine_decrease && in_array( $ticket_status, $unresolved_statuses ) && ! $permissions['view_assigned_others'] ) {
						$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] - 1;
					}
					$mine_increase_flag = in_array( $agent->term_id, $new_assigned ) ? true : false;
					$mine_increase      = apply_filters( 'wpsc_unresolved_count_increase', $mine_increase_flag, $ticket_id, $agent, $user_id, $new_assigned, $event );
					if ( $permissions['view_assigned_me'] == 1 && $mine_increase && in_array( $ticket_status, $unresolved_statuses ) && ! $permissions['view_assigned_others'] ) {
						$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] + 1;
					}
				}
				// Delete Ticket
				if ( $event == 'delete_ticket' && $label_counts ) {
					if ( ! $prev_assigned && $permissions['view_unassigned'] == 1 ) {
						if ( in_array( $ticket_status, $unresolved_statuses ) ) {
							$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] - 1;
						}
					}
					if ( $prev_assigned ) {
						$mine_decrease_flag = in_array( $agent->term_id, $prev_assigned ) ? true : false;
						if ( $mine_decrease_flag ) {
							$mine_decrease = apply_filters( 'wpsc_unresolved_count_decrease', $mine_decrease_flag, $ticket_id, $agent, $user_id, $prev_assigned, $event );
							if ( in_array( $ticket_status, $unresolved_statuses ) && $mine_decrease ) {
								$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] - 1;
							}
						}
						if ( in_array( $ticket_status, $unresolved_statuses ) && ! in_array( $agent->term_id, $prev_assigned ) && $permissions['view_assigned_others'] == 1 ) {
							$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] - 1;
						}
					}
				}
				// Restore Ticket
				if ( $event == 'restore_ticket' && $label_counts ) {
					if ( ! $new_assigned && $permissions['view_unassigned'] == 1 ) {
						if ( in_array( $ticket_status, $unresolved_statuses ) ) {
							$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] + 1;
						}
					}
					if ( $new_assigned ) {
						$mine_increase_flag = in_array( $agent->term_id, $new_assigned ) ? true : false;
						if ( $mine_increase_flag ) {
							$mine_increase = apply_filters( 'wpsc_unresolved_count_increase', $mine_increase_flag, $ticket_id, $agent, $user_id, $new_assigned, $event );
							if ( in_array( $ticket_status, $unresolved_statuses ) && $mine_increase ) {
								$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] + 1;
							}
						}
						if ( in_array( $ticket_status, $unresolved_statuses ) && ! in_array( $agent->term_id, $new_assigned ) && $permissions['view_assigned_others'] == 1 ) {
							$label_counts['unresolved_agent'] = $label_counts['unresolved_agent'] + 1;
						}
					}
				}
				update_user_meta( $user_id, 'wpsc_' . get_current_blog_id() . '_label_counts', $label_counts );
			}
		}

		//Raised By
		function change_raised_by( $ticket_id, $name, $email, $prev_name ) {
			global $wpscfunction, $current_user;
			$user = $wpscfunction->get_ticket_fields( $ticket_id, 'customer_name' );
			if ( $current_user->ID ) {
				$log_str = sprintf( __( '%1$s changed raised by from %2$s to %3$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $prev_name . '</strong>', '<strong>' . $user . '</strong>' );
			}
			$args = array(
				'ticket_id'   => $ticket_id,
				'reply_body'  => $log_str,
				'thread_type' => 'log'
			);
			$args = apply_filters( 'wpsc_thread_args', $args );
			$wpscfunction->submit_ticket_thread( $args );
		}

		//Change Fields
		function change_field( $ticket_id, $fields_slug, $fields_value, $prev_fields_value ) {

			$term_id_data = get_term_by( 'slug', $fields_slug, 'support_ticket_custom_fields' );
			$wpsc_tf_type = get_term_meta( $term_id_data->term_id, 'wpsc_tf_type', true );
			$label_field  = get_term_meta( $term_id_data->term_id, 'wpsc_tf_label', true );

			if ( $wpsc_tf_type == 10 || $wpsc_tf_type == 5 ) {
				return;
			} else {
				global $wpscfunction, $current_user;
				$fields_value      = apply_filters( 'wpsc_change_field_value', $fields_value, $ticket_id, $wpsc_tf_type, $fields_slug );
				$prev_fields_value = apply_filters( 'wpsc_change_prev_field_value', $prev_fields_value, $ticket_id, $wpsc_tf_type, $fields_slug );
				$name              = apply_filters( 'wpsc_change_field_name', $label_field, $ticket_id, $fields_slug, $wpsc_tf_type, $term_id_data );

				if ( is_array( $fields_value ) || is_array( $prev_fields_value ) ) {
					$string      = implode( ",", $fields_value );
					$prev_string = implode( ",", $prev_fields_value );
					if ( $current_user->ID && $prev_string ) {
						$log_str = sprintf( __( '%1$s changed %2$s from %3$s to %4$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $name . '</strong>', '<strong>' . $prev_string . '</strong>', '<strong>' . $string . '</strong>' );
					} else if ( $current_user->ID ) {
						$log_str = sprintf( __( '%1$s changed %2$s to %3$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $name . '</strong>', '<strong>' . $string . '</strong>' );
					} else {
						$log_str = sprintf( __( '%1$s changed to %2$s', 'supportcandy' ), '<strong>' . $name . '</strong>', '<strong>' . $fields_value . '</strong>' );
					}
					$args = array(
						'ticket_id'   => $ticket_id,
						'reply_body'  => $log_str,
						'thread_type' => 'log'
					);
					$args = apply_filters( 'wpsc_thread_args', $args );
					$wpscfunction->submit_ticket_thread( $args );

				} else {
					if ( $current_user->ID && $prev_fields_value ) {
						$log_str = sprintf( __( '%1$s changed %2$s from %3$s to %4$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $name . '</strong>', '<strong>' . $prev_fields_value . '</strong>', '<strong>' . $fields_value . '</strong>' );

					} else if ( $current_user->ID ) {
						$log_str = sprintf( __( '%1$s changed %2$s to %3$s', 'supportcandy' ), '<strong>' . $current_user->display_name . '</strong>', '<strong>' . $name . '</strong>', '<strong>' . $fields_value . '</strong>' );
					} else {
						$log_str = sprintf( __( '%1$s changed to %2$s', 'supportcandy' ), '<strong>' . $name . '</strong>', '<strong>' . $fields_value . '</strong>' );
					}
					$args = array(
						'ticket_id'   => $ticket_id,
						'reply_body'  => $log_str,
						'thread_type' => 'log'
					);
					$args = apply_filters( 'wpsc_thread_args', $args );
					$wpscfunction->submit_ticket_thread( $args );
				}
			}
		}

		// Send email notifications for create ticket
		function en_ticket_created( $ticket_id ) {
			include WPSC_ABSPATH . 'includes/actions/en_ticket_created.php';
		}

		// Send email notification for submit reply
		function en_submit_reply( $thread_id, $ticket_id ) {
			include WPSC_ABSPATH . 'includes/actions/en_submit_reply.php';
		}

		// Send email notification for submit note
		function en_submit_note( $thread_id, $ticket_id ) {
			include WPSC_ABSPATH . 'includes/actions/en_submit_note.php';
		}

		// Send email notification for change status, category or priority
		function en_change_status( $ticket_id, $status, $prev_val ) {
			include WPSC_ABSPATH . 'includes/actions/en_change_status.php';
		}

		// Send email notification for assign agent
		function en_assign_agent( $ticket_id, $agents, $prev_assigned ) {
			include WPSC_ABSPATH . 'includes/actions/en_assign_agent.php';
		}

		// Send email notification for delete ticket
		function en_delete_ticket( $ticket_id ) {
			include WPSC_ABSPATH . 'includes/actions/en_delete_ticket.php';
		}

		//Check download file
		function check_download_file() {
			global $wpdb, $wpscfunction;
			if ( isset( $_REQUEST['support_ticket_attachment'] ) && isset( $_REQUEST['tid'] ) && isset( $_REQUEST['tac'] ) ) {
				$attach_id        = intval( sanitize_text_field( $_REQUEST['support_ticket_attachment'] ) );
				$auth_code        = intval( sanitize_text_field( $_REQUEST['tac'] ) );
				$ticket_id        = intval( sanitize_text_field( $_REQUEST['tid'] ) );
				$ticket_auth_code = $wpscfunction->get_ticket_fields( $ticket_id, 'ticket_auth_code' );

				if ( $ticket_auth_code == $auth_code ) {
					$this->file_download( $attach_id );
				}
			}
		}

		function file_download( $attach_id ) {
			$attach      = array();
			$attach_meta = get_term_meta( $attach_id );
			foreach ( $attach_meta as $key => $value ) {
				$attach[ $key ] = $value[0];
			}
			$upload_dir   = wp_upload_dir();
			$filepath     = $upload_dir['basedir'] . '/wpsc/' . $attach['save_file_name'];
			$content_type = $attach['is_image'];

			header( 'Content-Description: File Transfer' );
			header( 'Cache-Control: public' );
			header( 'Content-Type: ' . $content_type );
			header( "Content-Transfer-Encoding: binary" );
			header( "Content-Disposition: attachment;filename=" . $attach['filename'] );
			header( 'Content-Length: ' . filesize( $filepath ) );
			flush();
			readfile( $filepath );
			exit( 0 );
		}

		function wpsc_check_cron_attachment() {
			include WPSC_ABSPATH . 'includes/actions/wpsc_check_cron_attachment.php';
		}

		function wpsc_gdpr_personal_data_eraser() {
			include WPSC_ABSPATH . 'includes/actions/wpsc_gdpr_personal_data_eraser_cron.php';
		}

		function wpsc_register_privacy_exporters( $exporters ) {
			$exporters['support_tickets'] = array(
				'exporter_friendly_name' => __( 'Tickets', 'supportcandy' ),
				'callback'               => array( $this, 'wpsc_privacy_ticket_exporter' ),
			);

			return $exporters;
		}

		function wpsc_privacy_ticket_exporter( $email_address = '', $page = 1 ) {
			include WPSC_ABSPATH . 'includes/admin/gdpr_data/wpsc_privacy_ticket_exporter.php';

			return array(
				'data' => $export_ticket,
				'done' => true
			);
		}

		function wpsc_register_privacy_erasers( $erasers = array() ) {

			$erasers[] = array(
				'eraser_friendly_name' => __( 'Tickets Record', 'supportcandy' ),
				'callback'             => array( $this, 'wpsc_privacy_customer_erasers' ),
			);

			return $erasers;
		}

		function wpsc_privacy_customer_erasers( $email_address, $page = 1 ) {

			global $wpdb, $wpscfunction;

			$sql         = "SELECT t.* from {$wpdb->prefix}support_ticket  t WHERE t.customer_email = '$email_address' AND t.active = 1 ";
			$tickets     = $wpdb->get_results( $sql );
			$ticket_list = json_decode( json_encode( $tickets ), true );

			$fields = get_terms( [
				'taxonomy'   => 'support_ticket_custom_fields',
				'hide_empty' => false,
				'orderby'    => 'meta_value_num',
				'meta_key'   => 'wpsc_tf_load_order',
				'order'      => 'ASC',
				'meta_query' => array(
					array(
						'key'     => 'agentonly',
						'value'   => array( 0, 1 ),
						'compare' => 'IN'
					)
				),
			] );

			$tickets = array();
			foreach ( $ticket_list as $erase_post ) {
				$ticket_id = $erase_post['id'];
				$values    = array(
					'customer_name'  => 'Anonymized User',
					'customer_email' => 'anonymous@anonymous.anonymous'
				);
				$wpdb->update( $wpdb->prefix . 'support_ticket', $values, array( 'id' => $ticket_id ) );

				$args           = array(
					'post_type'      => 'ticket_thread',
					'post_status'    => 'publish',
					'posts_per_page' => - 1,
					'order'          => 'ASC',
					'meta_query'     => array(
						array(
							'key'     => 'ticket_id',
							'value'   => $ticket_id,
							'compare' => '='
						),
					),
				);
				$ticket_threads = get_posts( $args );
				foreach ( $ticket_threads as $ticket_thread ) {
					update_post_meta( $ticket_thread->ID, 'customer_name', 'Anonymized User' );
					update_post_meta( $ticket_thread->ID, 'customer_email', 'anonymous@anonymous.anonymous' );
				}
				foreach ( $fields as $key => $field ) {
					$personal_info = get_term_meta( $field->term_id, 'wpsc_tf_personal_info', true );
					if ( $personal_info ) {
						$wpscfunction->delete_ticket_meta( $erase_post['id'], $field->slug );
					}
				}
			}

			return array(
				'items_removed'  => true,
				'items_retained' => false,
				'messages'       => array( sprintf( __( 'Tickets of customer having email %s has been anonymized.', 'supportcandy' ), $email_address ) ),
				'done'           => true,
			);
		}

		function my_profile_update( $user_id, $old_user_data ) {

			global $wpdb;
			$new_user_data  = get_user_by( 'id', $user_id );
			$old_user_name  = $old_user_data->display_name;
			$new_user_name  = $new_user_data->display_name;
			$old_user_email = $old_user_data->user_email;
			$new_user_email = $new_user_data->user_email;
			if ( $new_user_name != $old_user_name || $new_user_email != $old_user_email ) {
				$sql         = "SELECT t.* from {$wpdb->prefix}support_ticket  t WHERE t.customer_email = '$old_user_email' AND t.active = 1 ";
				$tickets     = $wpdb->get_results( $sql );
				$ticket_list = json_decode( json_encode( $tickets ), true );

				if ( $ticket_list ) {
					foreach ( $ticket_list as $ticket ) {
						$wpdb->update( $wpdb->prefix . 'support_ticket', array(
							'customer_name'  => $new_user_name,
							'customer_email' => $new_user_email
						), array( 'id' => $ticket['id'] ) );
					}
				}
			}

			if ( $new_user_data->has_cap( 'wpsc_agent' ) ) {
				$agent = get_term_by( 'slug', 'agent_' . $user_id, 'support_agent' );

				update_term_meta( $agent->term_id, 'label', $new_user_name );
				update_term_meta( $agent->term_id, 'first_name', $new_user_data->first_name );
				update_term_meta( $agent->term_id, 'last_name', $new_user_data->last_name );
				update_term_meta( $agent->term_id, 'nicename', $new_user_data->user_nicename );
				update_term_meta( $agent->term_id, 'email', $new_user_data->user_email );

			}
		}

		//Send email notification for change category
		function en_change_category( $ticket_id, $category_id, $prev_cat ) {
			include WPSC_ABSPATH . 'includes/actions/en_change_category.php';
		}

		// Send email notification for change priority
		function en_change_priority( $ticket_id, $priority_id, $prev_priority ) {
			include WPSC_ABSPATH . 'includes/actions/en_change_priority.php';
		}
	}

endif;

new WPSC_Actions();