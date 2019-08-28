<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $current_user;
if ( ! ( $current_user->ID && $current_user->has_cap( 'manage_options' ) ) ) {
	exit;
}

$cat_id = isset( $_POST ) && isset( $_POST['cat_id'] ) ? intval( $_POST['cat_id'] ) : 0;
if ( ! $cat_id ) {
	exit;
}

$support_ticket_default_category = get_option( 'support_ticket_default_category' );

if ( $support_ticket_default_category != $cat_id ) {
	wp_delete_term( $cat_id, 'wpsc_categories' );
	do_action( 'wpsc_delete_category', $cat_id );
	echo '{ "sucess_status":"1","messege":"' . __( 'Category deleted successfully.', 'supportcandy' ) . '" }';
} else {
	echo '{ "sucess_status":"0","messege":"' . __( 'Default category can not be deleted.', 'supportcandy' ) . '" }';
}
