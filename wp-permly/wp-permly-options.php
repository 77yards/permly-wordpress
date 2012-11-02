<?php
require( 'permly_api.php' ); 

add_action( 'admin_init', 'wppermly_options_init' );

function wppermly_options_init() {
	register_setting( 'wppermly_admin_options', 'wppermly_options', 'wppermly_options_validate' );
}

function wppermly_options_validate( $options ) {

	global $wppermly;
	function clean( $options ) {
		foreach ( $options as $k => $v ) {
			if ( is_array( $v ) ) {
				$options[$k] = clean( $v );
			} else {
				$options[$k] = trim( esc_attr( urlencode( $v ) ) );
			}
		}
		return $options;
	}

	$valid = false;
	$options = clean( $options );

	if ( ! empty( $options['permly_api_key'] ) ) {
		$wppermly->api_key = $options['permly_api_key'];
		$wppermly->permly_api(); // rebuild object with new key
		$result = $wppermly->_json_decode($wppermly->getUser(),true);
		if ( isset($result['data']) ) {
			$valid = true;
		}
	}
	if ( ! isset( $options['post_types'] ) ) {
		$options['post_types'] = array();
	}
	if ( $valid === true )
		delete_option( 'wppermly_invalid' );
	else
		update_option( 'wppermly_invalid', 1 );
	return $options;
}

class wppermly_options extends permly_api {

	public $version;
	public $options;
	
	public function __construct( array $defaults ) {		
	
		$this->_get_version();
		$this->_refresh_options( $defaults );

		add_action( 'init', array( $this, 'check_options' ) );
		parent::__construct();
	}
	
	private function _get_version() {
	
		$version = get_option( 'wppermly_version' );
		$this->version = $version;
	}

	private function _refresh_options( $defaults ) {

		$options = get_option( 'wppermly_options', false );
		if ( $options === false ) {
			update_option( 'wppermly_options', $defaults );
		} else if ( is_array( $options ) ) {
			$diff = array_diff_key( $defaults, $options );

			if ( ! empty( $diff ) ) {
				$options = array_merge( $options, $diff );
				update_option( 'wppermly_options', $options );
			}
		}
		$this->api_key = $options['permly_api_key'];
		$this->options = $options;
	}

	public function check_options() {

		// Display any necessary administrative notices
		if ( current_user_can( 'edit_posts' ) ) {
			if ( empty( $this->options['permly_api_key'] ) ) {
				if ( ! isset( $_GET['page'] ) || $_GET['page'] != 'wppermly' ) {
					add_action( 'admin_notices', array( $this, 'notice_setup' ) );
				}
			}
			if ( get_option( 'wppermly_invalid' ) !== false && isset( $_GET['page'] ) && $_GET['page'] == 'wppermly' ) {
				add_action( 'admin_notices', array( $this, 'notice_invalid' ) );
			}
		}
	}

	public function notice_setup() {

		$title = __( 'WP Permly is almost ready!', 'wppermly' );
		$settings_link = '<a href="options-general.php?page=wppermly">'.__( 'settings page', 'wppermly' ).'</a>';
		$message = sprintf( __( 'Please visit the %s to configure WP Permly', 'wppermly' ), $settings_link );

		return $this->display_notice( "<strong>{$title}</strong> {$message}", 'error' );
	}

	public function notice_invalid() {

		$title = __( 'Invalid API Key!', 'wppermly' );
		$message = __( "Your Permly API key is invalid. Please set a valid key or if you don't have, get a new key from <a href='http://www.permly.com' target='_blank'>http://www.permly.com</a>.", 'wppermly' );

		return $this->display_notice( "<strong>{$title}</strong> {$message}", 'error' );
	}

	public function display_notice( $string, $type = 'updated', $echo = true ) {

		if ( $type != 'updated' )
			$type == 'error';

		$string = '<div id="message" class="' . $type . ' fade"><p>' . $string . '</p></div>';

		if ( $echo != true )
			return $string;

		echo $string;
	}
	
	public function urlKeyByPostName($postname) {
		$title_tag = $postname;
		$url_key = '';
		$title_length = 0;
		$title_pos = 0;
		
		try {			
			if($title_tag != '') {
				$url_key = $this->make_url_string($title_tag);
				$title_arr = explode("-",$url_key);
				$title_length = count($title_arr);
				if( $title_length > 3 )  {
					$title_pos = 3;
					$url_key = implode("-",array_slice($title_arr,0,$title_pos));
					$title_pos++;
				}					
			}	
		} catch(Exception $e) {
			$url_key = '';
		}
		
		if( trim($url_key) == '') {
			$url_key = $this->generate_url_key(5);
		}

		// validate this url key
		if( trim($url_key) != '') {

			$this->action = 'validate_url_key';
			$postdata = array();
			$postdata['data']['url_key'] = $url_key;
			$result =  $this->_json_decode($this->send_request($postdata),true);
		}

		if( isset($result['Error']) || trim($url_key) == '') {
			if( $result['Error']['code'] == 1450 || $result['Error']['code'] == 1550 || trim($url_key) == '') {
				$found = false;
				do{
					if( $title_tag!='' && $title_pos > 0 && $title_pos < $title_length ) {						
						$url_key = implode("-",array_slice($title_arr,0,$title_pos));
						$title_pos++;
					} else {
						$url_key = $this->generate_url_key(5);
					}	

					$this->action = 'validate_url_key';
					$postdata = array();
					$postdata['data']['url_key'] = $url_key;
					$result =  $this->_json_decode($this->send_request($postdata),true);

					if(!isset($result['Error'])) $found = true;

				} while (!$found);
			}	
		}
		return $url_key;	
	}	
}

abstract class wppermly_post {

	private static $post_id;
	private static $permalink = array();
	private static $shortlink;

	public static function id() {

		if ( ! self::$post_id ) {
			self::_get_post_id();
		}

		return self::$post_id;
	}

	public static function permalink( $key = 'raw' ) {

		if ( empty( self::$permalink ) ) {
			self::_get_permalink();
		}

		switch ( $key ) {
			case 'raw':     return self::$permalink['raw'];
			case 'encoded': return self::$permalink['encoded'];
			default:        return self::$permalink;
		}
	}

	public static function shortlink() {

		if ( ! self::$shortlink ) {
			self::_get_shortlink();
		}

		return self::$shortlink;
	}

	private static function _get_post_id() {
	
		global $post;
		if ( is_null( $post ) ) {
			trigger_error( 'wppermly::id() cannot be called before $post is set in the global namespace.', E_USER_ERROR );
		}

		self::$post_id = $post->ID;

		if ( $parent = wp_is_post_revision( self::$post_id ) ) {
			self::$post_id = $parent;
		}
	}

	private static function _get_permalink() {

		if ( ! is_array( self::$permalink ) ) {
			self::$permalink = array();
		}

		self::$permalink['raw']     = get_permalink( self::$post_id );
		self::$permalink['encoded'] = urlencode( self::$permalink['raw'] );
	}

	private static function _get_shortlink() {
	
		self::$shortlink = get_post_meta( self::$post_id, '_wppermly', true );
	}
}