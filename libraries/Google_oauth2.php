<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Google_oauth2
{
	protected $ci;
	private $_client_id;
	private $_client_secret;
	private $_base_redirect;

	public function __construct($google_config)
	{
		$this->ci =& get_instance();	
		$this->_client_id	  = $google_config['client_id'];
		$this->_client_secret = $google_config['client_secret'];
		$this->_base_redirect = $google_config['base_redirect'];
		
		// Define Variables
		$this->post_response = NULL;		
	}

	public function authorize_url($scope='')
	{
		$params = array(
			'client_id' 	=> $this->_client_id,
			'redirect_uri' 	=> $this->_base_redirect,
			'scope'			=> $scope,
			'response_type' => 'code',
		);
			
		return 'https://accounts.google.com/o/oauth2/auth?'.http_build_query($params);
	}

	function get_tokens($grant_type, $code_or_refresh)
	{
		// Basic Params
		$params = array(
			'client_id' 	=> $this->_client_id,
			'client_secret' => $this->_client_secret,
			'grant_type' 	=> $grant_type
		);
		
		// Is Getting Original or Refresh Token
		if ($grant_type == 'authorization_code')
		{
			$params['redirect_uri'] = $this->_base_redirect;
			$params['code'] 		= $code_or_refresh;
		}
		elseif ($grant_type == 'refresh_token')
		{
			$params['refresh_token'] = $code_or_refresh;
		}
	
		// Sets Curl Options
		$options = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_SSL_VERIFYHOST => 1,
			CURLOPT_USERAGENT => 'Social-Igniter 1.0 http://social-igniter.com',
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => http_build_query($params)
		);	
	
		$ch = curl_init('https://accounts.google.com/o/oauth2/token');
		curl_setopt_array($ch, $options);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		return array('info' => $info, 'output' => $output);
	}	

	function request_ssl_get($url)
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_SSL_VERIFYHOST => 1,
			CURLOPT_USERAGENT => 'Social-Igniter 1.0 http://social-igniter.com'
		);
	
		$ch = curl_init($url);
		curl_setopt_array($ch, $options);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		return array('info' => $info, 'output' => $output);
	}
	
	function request_oauth_post($connection, $developer_key, $xml_data, $number)
	{
		if (isset($connection->auth_one))
		{
	 		$headers = array( 
	            'Authorization: OAuth '.$connection->auth_one,
	            'Accept: text/xml', 
	            'X-GData-Key: key='.$developer_key, 
	            'Content-length: '.strlen($xml_data),
	            'Content-Type: application/atom+xml; charset=UTF-8' 
	        ); 	
		
			$options = array(
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_SSL_VERIFYPEER => 0,
				CURLOPT_SSL_VERIFYHOST => 1,
				CURLOPT_USERAGENT  => 'Social-Igniter 1.0 http://social-igniter.com',
				CURLOPT_HTTPHEADER => $headers, 
				CURLOPT_POST => TRUE,
				CURLOPT_POSTFIELDS => $xml_data
			);
	
			$ch = curl_init('http://gdata.youtube.com/action/GetUploadToken');
			curl_setopt_array($ch, $options);
			$output = curl_exec($ch);
			$info	= curl_getinfo($ch);
			$close	= curl_close($ch);
	
			if ($info['http_code'] == 401)
			{	
				$tokens_raw = $this->get_tokens('refresh_token', $connection->auth_two);
				$tokens		= json_decode($tokens_raw['output']);
				
				if (isset($tokens->access_token))
				{					
			   		$connection_data = array(
			   			'auth_one' => $tokens->access_token
			   		);
		
					$update_connection	= $this->ci->social_auth->update_connection($connection->connection_id, $connection_data);		
					$new_connection		= $this->ci->social_auth->get_connection($connection->connection_id);
					$number++;
					
					$this->request_oauth_post($new_connection, $developer_key, $xml_data, $number);
				}
			}
			elseif ($info['http_code'] == 200)
			{
				$this->post_response = array('info' => $info, 'output' => $output);
			}
			else
			{	
				$this->post_response = $output;
			}
		}
		else
		{		
			$this->post_response = FALSE;
		}
		
		return $this->post_response;
	}

	function request($url, $params=array(), $post=FALSE)
	{
		if($this->accessToken)
		{
			$params['oauth_token'] = $this->accessToken;
		}

		$params['alt'] = 'jsonc';
			
		if($post)
		{
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		}
		else
		{
			$url .= '?' . http_build_query($params);
			$ch = curl_init($url);
		}
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		$json = curl_exec($ch);

		if($json)
			return json_decode($json);
		else
			return FALSE;
	}

}