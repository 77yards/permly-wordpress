<?php /*
Plugin Name: WP Permly
Version: 1.0
Description: WP Permly uses the Permly API to generate short links for all your posts and pages. It can be used to email, share, or bookmark the posts or pages quickly and easily.
Author: 77yards
*/

global $wp_version;
define( 'WPPERMLY_VERSION', '1.0' );

register_uninstall_hook( __FILE__, 'wppermly_uninstall' );

require( 'wp-permly-options.php' );
require( 'wp-permly-views.php' );

// Load our controller class... it's helpful!
$wppermly = new wppermly_options(
	array(
		'permly_api_key'  => ' ',
		'post_types'     => array( 'post', 'page' ),
	)
);

if ( function_exists( 'wpme_shortlink_header' ) ) {
	remove_action( 'wp',      'wpme_shortlink_header' );
	remove_action( 'wp_head', 'wpme_shortlink_wp_head' );
}

// Automatic generation is disabled if the API information is invalid
if ( ! get_option( 'wppermly_invalid' ) ) {
	add_action( 'save_post', 'wppermly_generate_shortlink', 10, 1 );
	add_action( 'before_delete_post', 'wppermly_delete_shortlink', 10, 1 );
}

// Settings menu on plugins page.
add_filter( 'plugin_action_links', 'wppermly_filter_plugin_actions', 10, 2 );

// WordPress 3.0!
add_filter( 'get_shortlink', 'wppermly_get_shortlink', 10, 3 );

function wppermly_uninstall() {

	// Delete associated options
	delete_option( 'wppermly_version' );
	delete_option( 'wppermly_options' );
	delete_option( 'wppermly_invalid' );

	// Grab all posts
	$posts = get_posts( 'numberposts=-1&post_type=any' );

	// And remove our meta information from them
	foreach ( $posts as $post ) {
		delete_post_meta( $post->ID, '_wppermly' );
	}
}

/**
 *
 * @param $links mixed  The array of links displayed by the plugins page
 * @param $file  string The current plugin being filtered.
 */

function wppermly_filter_plugin_actions( $links, $file ) {

	static $wppermly_plugin;

	if ( ! isset( $wppermly_plugin ) ) {
		$wppermly_plugin = plugin_basename( __FILE__ );
	}
	
	if ( $file == $wppermly_plugin ) {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=wppermly' ) . '">' . __( 'Settings', 'wppermly' ) . '</a>';
		array_unshift( $links, $settings_link );
	}

	return $links;
}

/**
 * Generates the shortlink for the post specified by $post_id.
 */

function wppermly_generate_shortlink( $post_id ) {

	global $wppermly;

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return false;

	// If no valid api key found, then don't process any request.
	if ( empty( $wppermly->options['permly_api_key'] ) || get_option( 'wppermly_invalid' ) )
		return false;

	// Check if it is save post and not versions or draft
	if ( $parent = wp_is_post_revision( $post_id ) ) {
		$post_id = $parent;
	}

	$post = get_post( $post_id );

	if ( $post->post_status != 'publish' ) // generate links, only if it is publish
		return false;

	// Link to be generated
	$permalink = get_permalink( $post_id );
	$wppermly_link = get_post_meta( $post_id, '_wppermly', true );

	if ( $wppermly_link == ''  ) {
		$data = array(
			'url_key' => $wppermly->urlKeyByPostName($post->post_name),
			'target' => $post->guid,
			'no_redirect' => 0,
			'favorite' => 0,
			'valid_from' => date("m/d/Y"),
			'valid_until' => date("m/d/Y",mktime(0,0,0,date("m"),date("d"),date("Y")+1))
		);		
		$permly_response = $wppermly->_json_decode($wppermly->saveLink($data),true);

		// If we have a shortlink for this post already, we've sent it to the Permly expand API to verify that it will actually forward to this posts permalink
		if ( isset($permly_response['Error']) )
			return false;

		// The expanded URLs don't match, so we can delete and regenerate
		delete_post_meta( $post_id, '_wppermly' );
		
		update_post_meta( $post_id, '_wppermly', $wppermly->shortlink_domain.$permly_response['data']['url_key'] );
	}
}

/**
 * Generates the shortlink for the post specified by $post_id.
 */

function wppermly_delete_shortlink( $post_id ) {

	global $wppermly;

	// Check if it is save post and not versions or draft
	if ( empty( $wppermly->options['permly_api_key'] ) || get_option( 'wppermly_invalid' ) )
		return false;

	$post = get_post( $post_id );
	$permly_response = $wppermly->_json_decode($wppermly->getLinkByTarget( $post->guid ), true);
	if( isset($permly_response['data']) )
		$permly_response = $wppermly->_json_decode($wppermly->deleteLink($permly_response['data'][0]['prim_uid']),true);
}


/**
 * Return the wppermly_get_shortlink method to the built in WordPress pre_get_shortlink
 * filter for internal use.
 */

function wppermly_get_shortlink( $shortlink, $id, $context ) {

	// Look for the post ID passed by wp_get_shortlink() first
	if ( empty( $id ) ) {
		global $post;
		$id = $post->ID;
	}

	// Fall back in case we still don't have a post ID
	if ( empty( $id ) ) {
		if ( ! empty( $shortlink ) )
			return $shortlink;

		return false;
	}

	$shortlink = get_post_meta( $id, '_wppermly', true );

	if ( $shortlink == false ) {
		wppermly_generate_shortlink( $id );
		$shortlink = get_post_meta( $id, '_wppermly', true );
	}

	return $shortlink;
}

