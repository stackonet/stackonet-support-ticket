<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $current_user;
if ( ! ( $current_user->ID && $current_user->has_cap( 'manage_options' ) ) ) {
	exit;
}

$role_id = isset( $_POST ) && isset( $_POST['role_id'] ) ? sanitize_text_field( $_POST['role_id'] ) : 0;
if ( ! $role_id ) {
	exit;
}

$agent_role = get_option( 'support_ticket_agent_roles' );

if ( $role_id > 2 ) {
	unset( $agent_role[ $role_id ] );
	update_option( 'support_ticket_agent_roles', $agent_role );
	do_action( 'wpsc_delete_agent_role' );
	echo '{ "sucess_status":"1","messege":"' . __( 'Deleted successfully.', 'supportcandy' ) . '" }';
} else {
	echo '{ "sucess_status":"0","messege":"' . __( 'Default role can not be deleted.', 'supportcandy' ) . '" }';
}
