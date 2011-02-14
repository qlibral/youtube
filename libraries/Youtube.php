<?php
/**
 * This library was written exclusively for CodeIgniter, it is meant as an alternative
 * to the Zend youtube libraries to remove any Zend dependencies from CodeIgniter and
 * reduce the bloat those libraries add. Not every method was tested in this library so
 * use it at your own risk. I do not guarantee it will work and am not obligated to
 * fix it if something does break or is broken. If you post a question on my blog I
 * will do my best to answer it. My blog is http://jimdoescode.blogspot.com. If you
 * have any suggestions for improvements please let me know and I will do my best to
 * incorporate them.
 *
 * This software is open and free you may use it in whatever manner you deem fit. You
 * don't have to give me any credit for it, but it would be nice if you dropped me a
 * line on my blog and let me know how awesome I am.
 *
 * Enjoy!
 *
 * -JimDoesCode
 **/

class Youtube
{
    const HTTP_1 = '1.1';
    const HOST = 'gdata.youtube.com';
    const PORT = '80';
    const SCHEME = 'http';
    const METHOD = 'GET';
    const LINE_END = "\r\n";

    const URI_BASE = 'http://gdata.youtube.com/';

    const DEBUG = false;

    private $_uris = array(
        'STANDARD_TOP_RATED_URI'            => 'feeds/api/standardfeeds/top_rated',
        'STANDARD_MOST_VIEWED_URI'          => 'feeds/api/standardfeeds/most_viewed',
        'STANDARD_RECENTLY_FEATURED_URI'    => 'feeds/api/standardfeeds/recently_featured',
        'STANDARD_WATCH_ON_MOBILE_URI'      => 'feeds/api/standardfeeds/watch_on_mobile',
        'USER_URI'                          => 'feeds/api/users',
        'INBOX_FEED_URI'                    => 'feeds/api/users/default/inbox',
        'FRIEND_ACTIVITY_FEED_URI'          => 'feeds/api/users/default/friendsactivity',
        'ACTIVITY_FEED_URI'                 => 'feeds/api/events',
        'VIDEO_URI'                         => 'feeds/api/videos',
        'USER_UPLOADS_REL'                  => 'schemas/2007#user.uploads',
        'USER_PLAYLISTS_REL'                => 'schemas/2007#user.playlists',
        'USER_SUBSCRIPTIONS_REL'            => 'schemas/2007#user.subscriptions',
        'USER_CONTACTS_REL'                 => 'schemas/2007#user.contacts',
        'USER_FAVORITES_REL'                => 'schemas/2007#user.favorites',
        'VIDEO_RESPONSES_REL'               => 'schemas/2007#video.responses',
        'VIDEO_RATINGS_REL'                 => 'schemas/2007#video.ratings',
        'VIDEO_COMPLAINTS_REL'              => 'schemas/2007#video.complaints',
        'PLAYLIST_REL'                      => 'schemas/2007#playlist',
        'IN_REPLY_TO_SCHEME'                => 'schemas/2007#in-reply-to',
        'UPLOAD_TOKEN_REQUEST'              => 'action/GetUploadToken'
    );

    private $_header = array(
        'Host'=>self::HOST,
        'Connection'=>'close',
        'User-Agent'=>'CodeIgniter',
        'Accept-encoding'=>'identity'
    );

    private $_oauth = array();
    private $_access = false;

    /**
     * Create YouTube object
     *
     * @param string $clientId The clientId issued by the YouTube dashboard
     * @param string $developerKey The developerKey issued by the YouTube dashboard
     */
    public function youtube($params)
    {
        if(isset($params['apikey']))$this->_header['X-GData-Key'] = 'key='.$params['apikey'];
        $this->CI = get_instance();
        if(isset($params['oauth']))
        {
            $this->_oauth['key'] = $params['oauth']['key'];
            $this->_oauth['secret'] = $params['oauth']['secret'];
            $this->_oauth['algorithm'] = $params['oauth']['algorithm'];
            $this->_access = $params['oauth']['access_token'];
        }
    }

    /**
     * Builds out an http header based on the specified parameters.
     *
     * @param $url string the url this header will go to.
     * @param $prepend any header data that needs to be added to the header before it is built.
     * @param $append any header data that needs to be added after the header is built.
     * @param $method the http method to be used 'POST', 'GET', 'PUT' etc.
     * return string the http header.
     **/
    private function _build_header($url = false, $prepend = false, $append = false, $method = self::METHOD)
    {
        $str = $prepend === false ? '' : $prepend;
        foreach($this->_header AS $key=>$value)
            $str .= $key.": ".$value.self::LINE_END;
        if($this->_access !== false && $url !== false)
        {
            $this->CI->load->helper('oauth_helper');
            $str .= get_auth_header($url, $this->_oauth['key'], $this->_oauth['secret'], $this->_access, $method, $this->_oauth['algorithm']);
        }
        $str .= $append === false ? '' : $append;

        return $str;
    }

    /**
     * Connects to the configured server and returns a handle for I/O
     **/
    private function _connect($host = self::HOST, $port = self::PORT, $ssl = false)
    {
        $connect = $ssl === false ? 'tcp' : 'ssl';
        $opts = array(self::SCHEME=>array('method'=>self::METHOD, 'header'=>$this->_build_header()));
        $context = stream_context_create($opts);
        $handle = stream_socket_client($connect.'://'.$host.':'.$port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        
        return $handle;
    }
    
    /**
     * Checks that the response from the server after we make our request is good.
     * If it isn't then we log the response we got and return false.
     **/
    private function _check_status($handle)
    {
        $gotStatus = false;
        $response = '';
        $resparray = array();
        while(($line = fgets($handle)) !== false && !$this->_timedout($handle))
        {
            $gotStatus = $gotStatus || (strpos($line, 'HTTP') !== false);
            if($gotStatus)
            {
                $response .= $line;
                array_push($resparray, $line);
                if(rtrim($line) === '')break;
            }
        }
        
        $matches = explode(' ', $resparray[0]);
        $status = $gotStatus ? intval($matches[1]) : 0;
        if($status < 200 || $status > 299)
        {
            error_log('YouTube library received bad response: '.$response);
            if(!self::DEBUG)return false;
            else return $response;
        }
        return true;
    }
    
    private function _read($handle)
    {
        if($this->_check_status($handle) !== true)return false;
        $response = '';
        //Get the chunk size
        $chunksize = rtrim(fgets($handle));
        //Convert hex chunk size to int
        if(ctype_xdigit($chunksize))$chunksize = hexdec($chunksize);
        else $chunksize = 0;
        
        if(self::DEBUG)error_log("\nCHUNKSIZE: {$chunksize}");
        
        while($chunksize > 0 && !$this->_timedout($handle))
        {
            $line = fgets($handle, $chunksize);
            //If fgets stops on a newline before reaching
            //chunksize. Loop till we get to the chunksize.
            while(strlen($line) < $chunksize)
                $line .= fgets($handle);

            $response .= rtrim($line);
            if(self::DEBUG)error_log("\nCHUNK: {$line}");
            
            $chunksize = rtrim(fgets($handle));
            //If we have a valid number for chunksize and we
            //didn't get an error while reading the last line
            if(ctype_xdigit($chunksize) && $line !== false)$chunksize = hexdec($chunksize);
            else break;
            
            if(self::DEBUG)error_log("\nCHUNKSIZE: {$chunksize}");
        }
        if(self::DEBUG)error_log("\nRESPONSE: {$response}");
        return $response;
    }
    
    /**
     * Writes the specified request to the file handle.
     **/
    private function _write($handle, $request)
    {
        if(self::DEBUG)error_log($request);
        fwrite($handle, $request);
        return $request;
    }
    
    /**
     * Checks that the specified file handle hasn't timed out.
     **/
    private function _timedout($handle)
    {
        if($handle)
        {
            $info = stream_get_meta_data($handle);
            return $info['timed_out'];
        }
        return false;
    }

    /**
     * Executes a request that does not pass data, and returns the response.
     *
     * @param $uri The URI that corresponds to the data we want.
     * @return the xml response from youtube.
     **/
    private function _response_request($uri)
    {
        $request = self::METHOD." {$uri} HTTP/".self::HTTP_1.self::LINE_END;

        $url = self::URI_BASE.substr($uri, 1);

        $fullrequest = $this->_build_header($url, $request, self::LINE_END);

        $handle = $this->_connect();
        $this->_write($handle, $fullrequest);
        $output = $this->_read($handle);

        fclose($handle);
        $handle = null;

        return $output;
    }

    /**
     * Retrieves a specific video entry.
     *
     * @param $videoId The ID of the video to retrieve.
     * @param $fullEntry (optional) Retrieve the full metadata for the entry.
     *         Only possible if entry belongs to currently authenticated user.
     * @return the xml response from youtube
     */
    public function getVideoEntry($videoId, $fullEntry = false)
    {
        if($fullEntry)return $this->_response_request ("/{$this->_uris['USER_URI']}/default/uploads/{$videoId}");
        else return $this->_response_request ("/{$this->_uris['VIDEO_URI']}/{$videoId}");
    }

    /**
     * Retrieves a feed of videos related to the specified video ID.
     *
     * @param string $videoId The videoId of interest
     * @return the xml response from youtube.
     */
    public function getRelatedVideoFeed($videoId)
    {
        return $this->_response_request("/{$this->_uris['VIDEO_URI']}/{$videoId}/related");
    }

    /**
     * Retrieves a feed of video responses related to the specified video ID.
     *
     * @param string $videoId The videoId of interest
     * @return the xml response from youtube.
     */
    public function getVideoResponseFeed($videoId)
    {
        return $this->_response_request("/{$this->_uris['VIDEO_URI']}/{$videoId}/responses");
    }

    /**
     * Retrieves a feed of video comments related to the specified video ID.
     *
     * @param string $videoId The videoId of interest
     * @return the xml response from youtube.
     */
    public function getVideoCommentFeed($videoId)
    {
        return $this->_response_request("/{$this->_uris['VIDEO_URI']}/{$videoId}/comments");
    }

    public function getTopRatedVideoFeed()
    {
        return $this->_response_request("/{$this->_uris['STANDARD_TOP_RATED_URI']}");
    }
    
    public function getMostViewedVideoFeed()
    {
        return $this->_response_request("/{$this->_uris['STANDARD_MOST_VIEWED_URI']}");
    }

    public function getRecentlyFeaturedVideoFeed()
    {
        return $this->_response_request("/{$this->_uris['STANDARD_RECENTLY_FEATURED_URI']}");
    }

    public function getWatchOnMobileVideoFeed()
    {
        return $this->_response_request("/{$this->_uris['STANDARD_WATCH_ON_MOBILE_URI']}");
    }

    public function getPlaylistListFeed($user = 'default')
    {
        return $this->_response_request("/{$this->_uris['USER_URI']}/{$user}/playlists");
    }

    public function getSubscriptionFeed($user = 'default')
    {
        return $this->_response_request("/{$this->_uris['USER_URI']}/{$user}/subscription");
    }

    public function getContactFeed($user = 'default')
    {
        return $this->_response_request("/{$this->_uris['USER_URI']}/{$user}/contacts");
    }

    /**
     * Get all of the uploads the specified user has made to youtube.
     * If no user is specified then the currently authenticated user
     * is used.
     *
     * @param string $user the youtube user name of the user whose uploads you want.
     * @return the xml response from youtube.
     **/
    public function getUserUploads($user = 'default')
    {
        return $this->_response_request("/{$this->_uris['USER_URI']}/{$user}/uploads");
    }

    public function getUserFavorites($user = 'default')
    {
        return $this->_response_request("/{$this->_uris['USER_URI']}/{$user}/favorites");
    }

    public function getUserProfile($user = 'default')
    {
        return $this->_response_request("/{$this->_uris['USER_URI']}/{$user}");
    }

    public function getActivityForUser($user = 'default')
    {
        return $this->_response_request("/{$this->_uris['ACTIVITY_FEED_URI']}?author={$user}");
    }

    public function getFriendActivityForCurrentUser()
    {
        if($this->_access !== false)return $this->_response_request("/{$this->_uris['FRIEND_ACTIVITY_FEED_URI']}");
        else return false;
    }
    
    /**
     * Get a feed of the currently authenticated users inbox.
     *
     * @return the youtube response xml.
     **/
    public function getInboxFeedForCurrentUser()
    {
        if($this->_access !== false)return $this->_response_request ("/{$this->_uris['INBOX_FEED_URI']}");
        else return false;
    }

    /**
     * Executes a request and passes metadata, the returns the response.
     *
     * @param $uri the URI for this request.
     * @param $metadata the data to send for this request (usually XML)
     * @return mixed false if not authroized otherwise the response is returned.
     **/
    private function _data_request($uri, $metadata)
    {
        if($this->_access !== false)
        {
            $header = "POST {$uri} HTTP/".self::HTTP_1.self::LINE_END;
            $url = self::URI_BASE.substr($uri, 1);
            $encoding = "UTF-8";
            $extra = "Content-Type: application/atom+xml; charset={$encoding}".self::LINE_END;
            $extra .= "GData-Version: 2.0".self::LINE_END;
            mb_internal_encoding($encoding);
            
            $extra .= "Content-Length: ".mb_strlen($metadata.self::LINE_END).self::LINE_END.self::LINE_END;
            
            $fullrequest = $this->_build_header($url, $header, $extra, 'POST');
            $fullrequest .= $metadata.self::LINE_END;
            
            $handle = $this->_connect();
            $this->_write($handle, $fullrequest);
            $output = $this->_read($handle);
            
            fclose($handle);
            $handle = null;
            
            return $output;
        }
        return false;
    }

    /**
     * Directly uploads videos stored on your server to the youtube servers.
     *
     * @param string $path The path on your server to the video to upload.
     * @param string $contenttype The mime-type of the video to upload.
     * @param string $metadata XML information about the video to upload.
     * @param string (optional) $user the user name whose account this video will go to. Defaults to the authenticated user.
     **/
    public function directUpload($path, $contenttype, $metadata, $user = 'default')
    {
        if($this->_access !== false)
        {
            $uri = "/{$this->_uris['USER_URI']}/{$user}/uploads";
            $header = "POST {$uri} HTTP/".self::HTTP_1.self::LINE_END;
            //We use a special host for direct uploads.
            $host = "uploads.gdata.youtube.com";
            $url = "http://".$host.$uri;
            $extra = "GData-Version: 2.0".self::LINE_END;
            //Add the file name to the slug parameter.
            $extra .= "Slug: ".basename($path).self::LINE_END;
            //Create a random boundry string.
            $this->CI = get_instance();
            $this->CI->load->helper('string');
            $boundry = random_string();
            $extra .= "Content-Type: multipart/related; boundary=\"{$boundry}\"".self::LINE_END;
            
            //Build out the data portion of the request
            $data = "--".$boundry.self::LINE_END;
            $data .= "Content-Type: application/atom+xml; charset=UTF-8".self::LINE_END.self::LINE_END;
            $data .= $metadata.self::LINE_END;
            $data .= "--".$boundry.self::LINE_END;
            $data .= "Content-Type: ".$contenttype.self::LINE_END;
            $data .= "Content-Transfer-Encoding: binary".self::LINE_END.self::LINE_END;
            $data .= file_get_contents($path).self::LINE_END;
            $data .= "--{$boundry}--".self::LINE_END;
            
            //Calculate the size of the data portion.
            $extra .= "Content-Length: ".strlen($data).self::LINE_END.self::LINE_END;
            $this->_header['Host'] = $host;//Swap the default host
            $fullrequest = $this->_build_header($url, $header, $extra, 'POST');
            $this->_header['Host'] = self::HOST;//Revert the default host.
            $fullrequest .= $data;
            
            $handle = null;
            //Connect to the special upload host
            $handle = $this->_connect($host);
            
            $this->_write($handle, $fullrequest);
            $output = $this->_read($handle);
            
            fclose($handle);
            $handle = null;
            
            return $output;
        }
        return false;
    }


    /**
     * Makes a data request for a youtube upload token.
     * You must provide details for the video prior to
     * the request. These are specified in xml and are
     * passed as the metadata field.
     *
     * @param string $metadata XML information about the video about to be uploaded.
     * @return mixed false if not authorized otherwise the response is returned.
     **/
    public function getFormUploadToken($metadata)
    {
        return $this->_data_request("/{$this->_uris['UPLOAD_TOKEN_REQUEST']}", $metadata);
    }
    
    /**
     * Add a comment to a video or a reply to another comment.
     * To reply to a comment you must specify the commentId
     * otherwise it is just a regular comment.
     *
     * @param string $videoId the video the comment goes with.
     * @param string $comment the comment
     * @param string (optional) $commentId the id of the comment to reply to.
     * @return mixed false if not authenticated otherwise the http response is returned.
     **/
    public function addComment($videoId, $comment, $commentId = false)
    {
        
            $uri = "/{$this->_uris['VIDEO_URI']}/{$videoId}/comments";
            $url = self::URI_BASE.substr($uri, 1);
            
            $xml = "<?xml version='1.0' encoding='UTF-8'?><entry xmlns='http://www.w3.org/2005/Atom' xmlns:yt='http://gdata.youtube.com/schemas/2007'>";
            if($commentId !== false)$xml .= "<link rel='http://gdata.youtube.com/schemas/2007#in-reply-to' type='application/atom+xml' href='{$url}/{$commentId}'/>";
            $xml .= "<content>{$comment}</content></entry>";
            
            return $this->_data_request($uri, $xml);
    }
    
    /**
     * Add a video response to another video.
     *
     * @param string $videoId the youtube id of the video the response is to.
     * @param string $responseId the youtube id of the video response.
     * @return mixed false if not authenticated otherwise the http response is returned.
     **/
    public function addVideoResponse($videoId, $responseId)
    {
        $uri = "/{$this->_uris['VIDEO_URI']}/{$videoId}/responses";
        $xml = "<?xml version='1.0' encoding='UTF-8'?><entry xmlns='http://www.w3.org/2005/Atom'><id>{$responseId}</id></entry>";
        
        return $this->_data_request($uri, $xml);
    }
    
    /**
     * Adds a numeric rating between 1 and 5 to the specified video
     *
     * @param string $videoId the youtube video id.
     * @param int $rating the numeric rating between 1 and 5.
     * @return mixed false if not authenticated otherwise the http response is sent.
     **/
    public function addNumericRating($videoId, $rating)
    {
        if(is_numeric($rating) && $rating > 0 && $rating < 6)
        {
            $xml = "<?xml version='1.0' encoding='UTF-8'?><entry xmlns='http://www.w3.org/2005/Atom' xmlns:gd='http://schemas.google.com/g/2005'><gd:rating value='{$rating}' min='1' max='5'/></entry>";
            return $this->_data_request("/{$this->_uris['VIDEO_URI']}/{$videoId}/ratings", $xml);
        }
        return false;
    }
    
    /**
     * Adds a like or dislike rating to the specified video.
     *
     * @param string $videoId the youtube video id.
     * @param bool $like boolean where true = like and false = dislike.
     * @return mixed false if not authenticated otherwise the http response is sent.
     **/
    public function addLikeDislike($videoId, $like)
    {
        $xml = "<?xml version='1.0' encoding='UTF-8'?><entry xmlns='http://www.w3.org/2005/Atom' xmlns:yt='http://gdata.youtube.com/schemas/2007'><yt:rating value='".($like === true ? 'like':'dislike')."'/></entry>";
        return $this->_data_request("/{$this->_uris['VIDEO_URI']}/{$videoId}/ratings", $xml);
    }
}
// ./system/application/libraries