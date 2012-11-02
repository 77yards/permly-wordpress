<?php

add_action( 'admin_menu', 'wppermly_add_pages' );
add_action( 'admin_head', 'wppermly_add_metaboxes' );


function wppermly_add_pages() {

	$hook = add_options_page( 'WP Permly Options', 'WP Permly', 'edit_posts', 'wppermly', 'wppermly_display' );
		add_action( 'admin_print_styles-'.$hook, 'wppermly_print_styles' ); 
		add_action( 'admin_print_scripts-'.$hook, 'wppermly_print_scripts' ); 
}

function wppermly_print_styles() {

	wp_enqueue_style( 'dashboard' );
	wp_enqueue_style( 'wppermly', plugins_url( '', __FILE__ ).'/assets/permly.css', false, WPPERMLY_VERSION, 'screen' );
}

function wppermly_print_scripts() {

	wp_enqueue_script( 'dashboard' );
}

function wppermly_add_metaboxes() {

	global $post;
	if ( is_object( $post ) ) {

		$shortlink = get_post_meta( $post->ID, '_wppermly', true );

		if ( empty( $shortlink ) )
			return;

		add_meta_box( 'wppermly-meta', 'WP Permly', 'wppermly_build_metabox', $post->post_type, 'side', 'default', array( $shortlink ) );
	}
}

function wppermly_build_metabox( $post, $args ) {

	global $wppermly;

	$shortlink = $args['args'][0];

	echo '<label class="screen-reader-text" for="new-tag-post_tag">WP Permly</label>';
	echo '<p style="margin-top: 8px;"><input type="text" id="wppermly-shortlink" name="_wppermly" size="32" autocomplete="off" value="'.$shortlink.'" style="margin-right: 4px; color: #aaa;" /></p>';

	$permly_response = $wppermly->_json_decode($wppermly->getLinkByTarget( $post->guid ), true);
	echo '<h4 style="margin-left: 4px; margin-right: 4px; padding-bottom: 3px; border-bottom: 4px solid #eee;">Shortlink Stats</h4>';

	if ( isset($permly_response['data']) && !empty($permly_response['data']) ) {
		echo "<p>Clicks: <strong>{$permly_response['data'][0]['count']}</strong>";
	} else {
		echo '<p class="error" style="padding: 4px;">System is not able to retrieve the statstics. Please try later!</p>';
	}
}


function wppermly_display() {

	global $wppermly;

	echo '<div class="wrap">';
	screen_icon();
	echo '<h2 style="margin-bottom: 1em;">' . __( 'WP Permly Options', 'wppermly' ) . '</h2>';

?>

	<div class="postbox-container" style="width: 70%;">
	<div class="metabox-holder">	
	<div class="meta-box-sortables">
		<form action="options.php" id="wppermly" method="post">
		<?php
        	settings_fields( 'wppermly_admin_options' );
			wppermly_postbox_options();
		?>
		</form>
	</div></div>
	</div> <!-- .postbox-container -->

	<div class="postbox-container" style="width: 24%;">
	<div class="metabox-holder">	
	<div class="meta-box-sortables">
	<?php
		wppermly_postbox_support();
		if ( ! empty( $wppermly->options['permly_api_key'] ) && ! get_option( 'wppermly_invalid' ) )
		{
			wppermly_postbox_generate();
		}
	?>
	</div></div>
	</div> <!-- .postbox-container -->

	</div> <!-- .wrap -->
<?php

}


function wppermly_postbox_options() {

	global $wppermly;

	$exclude_types = array(
		'revision',
		'nav_menu_item',
	);

	$post_types = array( 'post', 'page' );
	$checkboxes = array();

	foreach ( $post_types as $pt ) {
		if ( ! in_array( $pt, $exclude_types ) ) {
			$checked = false;

			if ( in_array( $pt, $wppermly->options['post_types'] ) ) {
				$checked = $pt;
			}

			$checkboxes[] = '<input name="wppermly_options[post_types][]" type="checkbox" value="'.$pt.'" '.checked( $pt, $checked, false ).' /><span>'.ucwords( str_replace( '_', ' ', $pt ) ).'</span><br />';

		}
	}


	$options = array();

	$options[] = array(
		'id'    => 'permly_api_key',
		'name'  => __( 'Permly API Key:', 'wppermly' ),
		'desc'  => 'Get your permly api key from <a href="http://www.permly.com" target="_blank">http://www.permly.com</a>',
		'input' => '<input name="wppermly_options[permly_api_key]" type="text" value="'.$wppermly->options['permly_api_key'] . '" />'
	);

	$options[] = array(
		'id'    => 'post_types',
		'name'  => __( 'Post Types:', 'wppermly' ),
		'desc'  => __( 'Type of posts for which, WP permly should generate short links?', 'wppermly' ),
		'input' => implode( "\n", $checkboxes ),
  	);

	$output  = '<div class="intro">';
	$output .= '<p>' . __( 'Set your permly api key, to access the functions of WP Permly plugin.', 'wppermly' ).'</p>';
	$output .= '</div>';

	$output .= wppermly_build_form( $options );

	wppermly_build_postbox( 'wp_permly_options', __( 'Permly API Configuration', 'wppermly' ), $output );
}


function wppermly_postbox_support() {

	$output  = '<p>' . __( 'If you need any support, please feel free to write us <a href="http://www.permly.com/contact" target="_blank">here</a>;', 'wppermly' ) . '</p>';
	$output .= '<p><a href="http://www.twitter.com/77yards" target="_blank">FOLLOW US</a></p>';

	wppermly_build_postbox( 'support', 'WP Permly', $output );

}

function wppermly_postbox_generate() {

	global $wppermly;

	$output = '';

	if ( isset( $_POST['wppermly_generate'] ) ) {

		$generate = $wppermly->options['post_types'];

		if ( empty( $wppermly->options['permly_api_key'] ) || get_option( 'wppermly_invalid' ) ) {
			$output .= '<div class="error"><p>' . $status . __( 'Kindly configure your Permly API key before generating short links!', 'wppermly' ) . '</p></div>';
		} else {

			$posts = get_posts( array(
				'numberposts' => '-1',
				'post_type'   => $generate,
			) );
			foreach ( $posts as $the ) {
				if ( ! get_post_meta( $the->ID, '_wppermly', true ) ) {
					wppermly_generate_shortlink( $the->ID );
				}
			}

			$output .= '<div class="updated fade"><p style="font-weight: 700;">'.__( 'Short links have been generated for the selected post type(s)!', 'wppermly' ).'</p></div>';
		} // if ( empty )
	} // if ( isset )

	$output .= '<form action="" method="post">';
	$output .= '<p class="wppermly utility">To generate shortlinks for all previous posts or page, please click Generate Links</p>';
	$output .= '<p class="wppermly utility"><input type="submit" name="wppermly_generate" class="button-primary" value="' . __( 'Generate Links', 'wppermly' ) . '" /></p>';
	$output .= '</form>';

	wppermly_build_postbox( 'wppermly_generate', __( 'Generate Short Links', 'wppermly' ), $output );
}

function wppermly_build_postbox( $id, $title, $content, $echo = true ) {

	$output  = '<div id="wppermly_' . $id . '" class="postbox">';
	$output .= '<div class="handlediv" title="Click to toggle"><br /></div>';
	$output .= '<h3 class="hndle"><span>' . $title . '</span></h3>';
	$output .= '<div class="inside">';
	$output .= $content;
	$output .= '</div></div>';

	if ( $echo === true ) {
		echo $output;
	}

	return $output;
}

function wppermly_build_form( $options, $button = 'secondary' ) {


	$output = '<fieldset>';
	foreach ( $options as $option ) {

		$output .= '<dl' . ( isset( $option['class'] ) ? ' class="' . $option['class'] . '"' : '' ) . '>';
		$output .= '<dt><label for="wppermly_options[' . $option['id'] . '">' . $option['name'] . '</label>';

		if ( isset( $option['desc'] ) ) {
			$output .= '<p>' . $option['desc'] . '</p>';
		}

		$output .= '</dt>';
		$output .= '<dd>' . $option['input'] . '</dd>';
		$output .= '</dl>';
	}

	$output .= '<div style="clear: both;"></div>';
	$output .= '<p class="wppermly_submit"><input type="submit" class="button-' . $button . '" value="' . __( 'Save Changes', 'wppermly' ) . '" /></p>';
	$output .= '</fieldset>';

	return $output;
}
