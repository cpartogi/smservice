<?php

namespace App\Http\Helpers;

class WebCurl {
	
	var $headers;
	
	function __construct($headers=array()) {
		$this->headers = $headers;
	}

	function get($url, $headers=array()) {
		return $this->_request('GET', $url);
	}
	
	function post($url, $params) {
		return $this->_request('POST', $url, $params);
	}
	
	function put($url, $params) {
		return $this->_request('PUT', $url, $params);
	}
	
	function delete($url) {
		return $this->_request('DELETE', $url);
	}
	
	private function _request($method, $url, $params=null) {
		// create curl resource
		$ch = curl_init();
		
		// set url
		curl_setopt($ch, CURLOPT_URL, $url);
		
        //return the transfer as a string
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if($params != null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		if(is_array($this->headers) && !empty($this->headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		}
		
		// $output contains the output string
		$output = curl_exec($ch);
		
		// close curl resource to free up system resources
		curl_close($ch);
				
		return $output;
	}
}