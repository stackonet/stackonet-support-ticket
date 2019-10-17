<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check nonce
 */
// if( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce']) ){
//     // die(__('Cheating huh?', 'supportcandy'));
// }

global $wpdb;
$isError       = false;
$errorMessege  = '';
$attachment_id = 0;

if ( ! $_FILES ) {
	$isError = true;
}

if ( ! $isError ) {
	$tempExtension = explode( '.', $_FILES['file']['name'] );
	$extension     = $tempExtension[ count( $tempExtension ) - 1 ];
	switch ( $extension ) {
		case 'exe':
		case 'php':
		case 'js':
			$isError      = true;
			$errorMessege = __( 'Error: file format not supported!', 'supportcandy' );
			break;
	}
	if ( preg_match( '/php/i', $extension ) || preg_match( '/phtml/i', $extension ) ) {
		$isError      = true;
		$errorMessege = __( 'Error: file format not supported!', 'supportcandy' );
	}
}

if ( ! $isError && $_FILES['file']['tmp_name'] == '' ) {
	$isError      = true;
	$errorMessege = __( 'Error: file size exceeded allowed limit!', 'supportcandy' );
}

if ( ! $isError ) {

	$attachment_count = get_option( 'support_ticket_attachment_count' );
	if ( ! $attachment_count ) {
		$attachment_count = 1;
	}
	$term = wp_insert_term( 'attachment_' . $attachment_count, 'support_ticket_attachment' );
	if ( ! $term ) {
		die();
	}
	update_option( 'support_ticket_attachment_count', ++ $attachment_count );

	$upload_dir = wp_upload_dir();
	if ( ! file_exists( $upload_dir['basedir'] . '/wpsc/' ) ) {
		mkdir( $upload_dir['basedir'] . '/wpsc/', 0755, true );
	}

	add_term_meta( $term['term_id'], 'filename', $_FILES['file']['name'] );

	$save_file_name = str_replace( ' ', '_', $_FILES['file']['name'] );
	$save_file_name = str_replace( ',', '_', $_FILES['file']['name'] );
	$save_file_name = explode( '.', $save_file_name );

	$img_extensions = array(
		'png',
		'jpeg',
		'jpg',
		'bmp',
		'pdf',
		'mp4',
		'mp3',
		'PNG',
		'JPEG',
		'JPG',
		'BMP',
		'PDF',
		'MP4',
		'MP3'
	);
	$extension      = $save_file_name[ count( $save_file_name ) - 1 ];
	if ( ! in_array( $extension, $img_extensions ) ) {
		$extension = $extension . '.txt';
		add_term_meta( $term['term_id'], 'is_image', '0' );
	} else {
		add_term_meta( $term['term_id'], 'is_image', '1' );
	}

	unset( $save_file_name[ count( $save_file_name ) - 1 ] );

	$save_file_name = implode( '-', $save_file_name );

	$save_file_name = time() . '_' . preg_replace( '/[^A-Za-z0-9\-]/', '', $save_file_name ) . '.' . $extension;

	$save_directory = $upload_dir['basedir'] . '/wpsc/' . $save_file_name;

	move_uploaded_file( $_FILES['file']['tmp_name'], $save_directory );

	add_term_meta( $term['term_id'], 'save_file_name', $save_file_name );
	add_term_meta( $term['term_id'], 'active', '0' );
	add_term_meta( $term['term_id'], 'time_uploaded', date( "Y-m-d H:i:s" ) );

	$attachment_id = $term['term_id'];
	$errorMessege  = __( 'done', 'supportcandy' );

}

$isError = ( $isError ) ? 'yes' : 'no';

$response = array(
	'error'        => $isError,
	'errorMessege' => $errorMessege,
	'id'           => $attachment_id
);

echo json_encode( $response );