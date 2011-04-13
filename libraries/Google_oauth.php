<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * This library is meant to get you an oauth access token
 * for a google service. You can define which service by
 * changing the SCOPE constant. You will need to pass in
 * your consumer key and secret upon loading this library.
 *
 * I would like to note that the HMAC-SHA1 hashes google
 * generates seem to be different from those that are correctly
 * generated it could be I was signing my requests wrong (I did
 * make some corrections to how that works on the helper) or its
 * possible that google is doing it wrong (I read a number of
 * posts about people seeking help who were told to use RSA).
 * Either way I can say that I have used the RSA-SHA1 signing
 * method with out any issues and I would recommend you use that.
 */
class Google_oauth
{
    const SCHEME 		= 'https';
    const HOST 			= 'www.google.com';
    const AUTHORIZE_URI = '/accounts/OAuthAuthorizeToken';
    const REQUEST_URI   = '/accounts/OAuthGetRequestToken';
    const ACCESS_URI    = '/accounts/OAuthGetAccessToken';

    //This should be changed to correspond with the google service you are authenticating against.
    const SCOPE         = 'https://gdata.youtube.com'; //YouTube

    //Array that should contain the consumer secret and key which should be passed into the constructor.
    private $_consumer	= false;    

    /**
     * Pass in a parameters array which should look as follows:
     * array('key'=>'example.com', 'secret'=>'mysecret');
     * Note that the secret should either be a hash string for
     * HMAC signatures or a file path string for RSA signatures.
     * @param array $params 
    */   
	function __construct($params)
	{
		$this->ci =& get_instance();
		$this->ci->load->helper('oauth');
		
        //Set defaults for method and algorithm if they are not specified
        if (!array_key_exists('method', $params)) $params['method'] = 'GET';
        if (!array_key_exists('algorithm', $params)) $params['algorithm'] = OAUTH_ALGORITHMS::RSA_SHA1;

        $this->_consumer = $params;		  
	}

    /**
     * This is called to begin the oauth token exchange. This should only
     * need to be called once for a user, provided they allow oauth access.
     * It will return a URL that your site should redirect to, allowing the
     * user to login and accept your application.
     *
     * @param string $callback the page on your site you wish to return to after the user grants your application access.
     * @return mixed either the URL to redirect to, or if they specified HMAC signing an array with the token_secret and the redirect url
     */
    public function get_request_token($callback)
    {
        $baseurl	= self::SCHEME.'://'.self::HOST.self::REQUEST_URI;

        //Generate an array with the initial oauth values we need
        $auth = build_auth_array(
        	$baseurl, 
        	$this->_consumer['key'], 
        	$this->_consumer['secret'],
        	array(
        		'oauth_callback' 	=> urlencode($callback), 
        		'scope'				=> urlencode(self::SCOPE)
        	),
            $this->_consumer['method'], 
            $this->_consumer['algorithm']
        );
        
        //Create the "Authorization" portion of the header
        $str = '';
        foreach($auth AS $key => $value)
        {
	        //Do not include scope in the Authorization string.
            if($key != 'scope') $str .= ",".$key."=".$value;
        }
        
        $str = substr($str, 1);
        $str = 'Authorization: OAuth '.$str;
        
        //Send it
        $response = $this->_connect($baseurl."?scope=".$auth['scope'], $str);
        
        //We should get back a request token and secret which we will add to the redirect url.
        parse_str($response, $resarray);
        
        //Return the full redirect url and let the user decide what to do from there.
        $redirect = self::SCHEME.'://'.self::HOST.self::AUTHORIZE_URI."?oauth_token=".$resarray['oauth_token'];
        
        //If they are using HMAC then we need to return the token secret for them to store.
        if($this->_consumer['algorithm'] == OAUTH_ALGORITHMS::RSA_SHA1)
        {
        	return $redirect;
        }
        else
        { 
        	return array('token_secret' => $resarray['oauth_token_secret'], 'redirect' => $redirect);
    	}
    }

    /**
     * This is called to finish the oauth token exchange. This too should
     * only need to be called once for a user. The token returned should
     * be stored in your database for that particular user.
     *
     * @param string $token this is the oauth_token returned with your callback url
     * @param string $secret this is the token secret supplied from the request (Only required if using HMAC)
     * @param string $verifier this is the oauth_verifier returned with your callback url
     * @return array access token and token secret
     */
    public function get_access_token($token = false, $secret = false, $verifier = false)
    {
        //If no request token was specified then attempt to get one from the url
        if($token === false && isset($_GET['oauth_token']))$token = $_GET['oauth_token'];
        if($verifier === false && isset($_GET['oauth_verifier']))$verifier = $_GET['oauth_verifier'];
        //If all else fails attempt to get it from the request uri.
        if($token === false && $verifier === false)
        {
            $uri = $_SERVER['REQUEST_URI'];
            $uriparts = explode('?', $uri);

            $authfields = array();
            parse_str($uriparts[1], $authfields);
            $token = $authfields['oauth_token'];
            $verifier = $authfields['oauth_verifier'];
        }

        $tokenddata = array('oauth_token'=>urlencode($token), 'oauth_verifier'=>urlencode($verifier));
        if($secret !== false)$tokenddata['oauth_token_secret'] = urlencode($secret);

        $baseurl = self::SCHEME.'://'.self::HOST.self::ACCESS_URI;
        //Include the token and verifier into the header request.
        $auth = get_auth_header($baseurl, $this->_consumer['key'], $this->_consumer['secret'],
                                $tokenddata, $this->_consumer['method'], $this->_consumer['algorithm']);
        $response = $this->_connect($baseurl, $auth);
        //Parse the response into an array it should contain
        //both the access token and the secret key. (You only
        //need the secret key if you use HMAC-SHA1 signatures.)
        parse_str($response, $oauth);
        //Return the token and secret for storage
        return $oauth;
    }

    /**
     * Connects to the server and sends the request,
     * then returns the response from the server.
     * @param <type> $url
     * @param <type> $auth
     * @return <type>
     */
    private function _connect($url, $auth)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
        curl_setopt($ch, CURLOPT_SSLVERSION,3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($auth));

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
// ./system/application/libraries