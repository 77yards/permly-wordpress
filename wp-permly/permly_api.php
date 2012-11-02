<?php
/**
 * API library - it allows to process all api calls
 *
 *
 * @author	Pradeep Singh
 * @copyright	Simplessus
 *
 * @version	1.0
 */


class permly_api {
	var $url;
	var $action;
	var $api_key = "";
	var $api_version = "1.0";
	var $api_server_protocol = "http";
	var $api_server = "api.permly.com";
	var $request_logging = false;
	var $shortlink_domain = 'http://www.perm.ly/';

	/**
	 * Constructor of class. set the base url of api server.
	 *
	 */	
	function permly_api(){
		$this->url = $this->api_server_protocol."://".$this->api_server."/?remote_service=rs_external_api&key=".$this->api_key."&interface=eai_permly&version=".$this->api_version;
	}

	/**
	 * Build complete url with action.
	 *
	 * @return	string
	 */	
	function _build_url() {
		$url = $this->url.'&action='.$this->action;
		return $url;
	}
	
	/**
	 * Send request to api server
	 *
	 * @param	mixed	$postdata
	 * @return	string
	 */	
	function send_request($postdata = "") {
		if(!is_array($postdata) ) $postdata = array();
		
		$url = $this->_build_url();

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_POST      ,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'json='.$this->_json_encode($postdata));
		curl_setopt($ch, CURLOPT_HEADER      ,0); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER  ,1);
		curl_setopt($ch, CURLOPT_TIMEOUT  ,10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);		

		$return_data = curl_exec($ch);

		return $return_data;
	}	
		
	/**
	 * Encoded array in json data
	 * 
	 * @return	string
	 */
	function _json_encode($data, $options = 0) {
		return json_encode($data);
		return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	}

	/**
	 * Decode json data
	 * 
	 * @return	mixed
	 */
	function _json_decode($data, $assoc = false) {
		return json_decode($data, $assoc);
		return json_decode($data, $assoc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	}	
	
	/**
	 * Return user data
	 * 
	 * @return	string
	 */
	function getUser() {
		$this->action = 'get_user';
		return $this->send_request();
	}	
	
	/**
	 * Return my links data
	 * 
	 * @return	string
	 */
	function getMyLinks($data = '') {
		$postdata = array();
		if( is_array($data) ) {
			$postdata['data'] = $data;
		}
		
		$this->action = 'get_my_links';
		return $this->send_request($postdata);
	}	
		
	/**
	 * Save link
	 * 
	 * @return	string
	 */
	function saveLink($data) {
		if( trim(@$data['url_key']) == '' ) $data['url_key'] = $this->generateUrlKey($data['target']);
		$postdata = array();
		$postdata['data'] = $data;
		$this->action = 'save_link';
		return $this->send_request($postdata);
	}	
	
	/**
	 * Delete link
	 * 
	 * @return	string
	 */
	function deleteLink($linkid) {
		$postdata = array();
		$postdata['data']['link_uid'] = $linkid;
		$this->action = 'delete_link';
		return $this->send_request($postdata);
	}	
	
	/**
	 * Delete link by url_key
	 * 
	 * @return	string
	 */
	function deleteLinkByKey($url_key) {
		$search = array(
			'link_search_term' => $url_key
		);
		$link_data = $this->_json_decode($this->getMyLinks($search),true);
		
		if( !empty($link_data['data']) ) {
			$linkid = $link_data['data'][0]['prim_uid'];
			return $this->deleteLink($linkid);
		} else {
			return $this->_json_encode(array('Error' => array('response' => 'Invalid link')));
		}	
	}	
	
	/**
	 * Get link by target
	 * 
	 * @return	string
	 */
	function getLinkByTarget($target) {
		$postdata = array();
		$postdata['data']['target'] = $target;
		$this->action = 'get_link_by_target';
		return $this->send_request($postdata);
	}	
	
	/**
	 * Generate URL key
	 * 
	 * @return	string
	 */
	function generateUrlKey($target) {
		$url_target = $target;
		$title_tag = "";
		$url_key = '';
		$title_length = 0;
		$title_pos = 0;

		if( $url_target!='' and !preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url_target))
			$url_target = 'http://'.$url_target;
		
		try {
			preg_match('/<title>([^>]*)<\/title>/si',@file_get_contents($url_target), $match); 
			$title_tag = @$match[1];
			
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
					$result =  $this->send_request($postdata);

					if(!isset($result['Error'])) $found = true;

				} while (!$found);
			}	
		}
		return $url_key;
	}	
	
	// Return random url key

	function generate_url_key($length){
		$pattern = "1234567890abcdefghijklmnopqrstuvwxyz";

		$result = '';
		for($i = 0; $i < $length; $i++) {
			$result .= $pattern{rand(0,35)};
		}

		return $result;	
	}	
	
	/**
	 * Returns a string formatted so it can 
	 * be used as a valid part of URL.
	 *
	 * @see		http://www.php.net/strtr
	 *
	 * @param	string	$value
	 * @return	string
	 */
	function make_url_string($value) {

		// Lower
		$result = strtolower($value); 

		// Replace special chars
		$char_search = array('Ö', 'Ä', 'Ü', 'ö', 'ä', 'ü', 'ß');
		$char_replace = array('oe', 'ae', 'ue', 'oe', 'ae', 'ue','ss');
		$result = trim(str_replace($char_search, $char_replace, $result));
		$result = str_replace(array('!', '"', '#', '$', '%', '&', '\'', '(', ')', '*', '+', ',', '.', '/', ':', ';', '_', '@', '\\', '<', '=', '>', '?', '[', ']', '^', '`', '{', '|', '}', '~'), ' ', $result);


		// Verify characters
		$result = preg_replace('/([^a-z0-9]+)/', '-', $result);


		// Reduce hyphen to one
		$result = preg_replace("#([\-])+#", "\\1", $result);


		// No invalid characters at the begin and the end
		while(strlen($result) > 0 && substr($result, 0, 1) == '-') {
			$result = substr($result, 1);
		}

		while(strlen($result) > 0 && (substr($result, -1) == '-' || in_array(substr($result, -1), $char_search))) {
			$result = substr($result, 0, strlen($result) - 1);
		}

		return $result;	
	}	
}
?>