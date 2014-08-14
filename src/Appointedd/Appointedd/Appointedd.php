<?php namespace Appointedd\Appointedd;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Exception;

class Appointedd_MissingArgument extends Exception {}
class Appointedd_HTTPError extends Exception {}
class Appointedd_NotFound extends Exception {}

class Appointedd
{

    private $client;

    // client credentials
    private $clientId;
    private $clientSecret;

    // user credentials
    private $username;
    private $password;
    
    // oauth urls
    private $oauthAuthoriseURL = 'http://api.appointedd.com/oauth/authorise';
    private $oauthAccessTokenURL = 'http://api.appointedd.com/oauth/access_token';
    private $accessToken;

    // base url for api calls
    private $apiUrl = 'http://api.appointedd.com';

    /**
     * Construct sets up httpClient and accessToken
     * @param string|null $accessToken 
     * @param \GuzzleHttp\Client|null $httpClient 
     */
    public function __construct($accessToken = null, \GuzzleHttp\Client $httpClient = null) {

        if($httpClient) {
            $this->client = $httpClient;
        } else {
            $this->client = new Client;
        }

    	if($accessToken) $this->accessToken = $accessToken;
    }

    /**
     * Generates the URL to authorise with
     * @param  string $callback return URL
     * @return string           Full URL to authorise endpoint
     */
    public function getAuthoriseURL($callback){
            
        $authoriseURL  = $this->oauthAuthoriseURL;
        $authoriseURL .= '?response_type=code';
        $authoriseURL .= '&client_id='.$this->clientId;
        $authoriseURL .= '&client_secret='.$this->clientSecret;
        $authoriseURL .= '&state=requested';
        $authoriseURL .= '&redirect_uri='.urlencode($callback);
        
        return $authoriseURL;
     
    }

    /**
     * Exchange the access code for an access_token
     * @param  string $code
     * @param  string $callback return url
     * @return array           token response from API
     */
    public function getAccessTokenFromCode($code, $callback){
        
        // params to send to oauth receiver
        $params = array(
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id'=>$this->clientId,
            'client_secret'=>$this->clientSecret,
            'response_type'=>'token',
            'redirect_uri' => urlencode($callback)
        );
        
        // call oauth
        $result = $this->call('', 'oauth', $params);
            
        // Return the response as an array
        return $result;
    }

    /**
     * Get the access_token from user credentials
     * @param  string $code
     * @param  string $callback Return url
     * @return array Token response from API
     */
    public function getAccessTokenFromLogin($username = null, $password = null){
        
        if(!$username)
            throw new Appointedd_MissingArgument('Username is missing');

        if(!$password)
            throw new Appointedd_MissingArgument('Password is missing');

        // params to send to oauth receiver
        $params = array(
            'grant_type' => 'password',
            'client_id'=>$this->clientId,
            'client_secret'=>$this->clientSecret,
            'username'=>$username,
            'password'=>$password
        );
        
        // call oauth
        $result = $this->call('', 'oauth', $params);
    
        // Return the response as an array
        return $result;
    }

    /**
     * Chained method. Takes client creds create new class instance and passes back to next in chain
     * @param string $accessToken
     * @return class instance
     */
    public static function setClientCredentials($clientId = '', $clientSecret = ''){

        if(!$clientId)
            throw new Appointedd_MissingArgument('Client ID is missing');

        if(!$clientSecret)
            throw new Appointedd_MissingArgument('Client secret is missing');

        $appointeddInstance = new Appointedd;

        $appointeddInstance->clientId = $clientId;
        $appointeddInstance->clientSecret = $clientSecret;

        return $appointeddInstance;
    }

    /**
     * Chained method. Takes access token create new class instance and passes back to next in chain
     * @param string $accessToken
     * @return class instance
     */
    public static function setAccessToken($accessToken = ''){

        if(!$accessToken)
            throw new Appointedd_MissingArgument('Access token is missing');

        $appointeddInstance = new Appointedd($accessToken);
        return $appointeddInstance;
    }


    public function get($endpoint, $data=array()) {
    	$response = $this->call($endpoint, 'get', $data);
        return $response;
    }

    public function put($endpoint, $data=array()) {
    	$response = $this->call($endpoint, 'put', $data);
        return $response;
    }

    public function post($endpoint, $data=array()) {
        $data['client_id'] = $this->clientId;
    	$response = $this->call($endpoint, 'post', $data);
        return $response;
    }

    public function delete($endpoint, $data=array()) {
    	$response = $this->call($endpoint, 'delete', $data);
        return $response;
    }

    /**
     * Create guzzle client and get response
     * @param  string $endpoint API endpoint
     * @param  string $type     get|post|put|delete
     * @param  array  $data     data to sent to the API
     * @return object           response from API
     */
	private function call($endpoint, $type, $data = array()) {

        if($this->accessToken)
        	$data['access_token'] = $this->accessToken;

        $headers = array();

        try {
            
            switch ($type) {
                case 'get':
                    $response = $this->client->get($this->apiUrl. '/' . $endpoint.'?'. http_build_query($data), $headers);
                    break;

                case 'put':
                    $response = $this->client->put($this->apiUrl. '/' . $endpoint, array('body'=>$data));
                    break;

                case 'post':
                    $response = $this->client->post($this->apiUrl. '/' . $endpoint, array('body'=>$data));               
                    break;

                case 'delete':
                    $response = $this->client->delete($this->apiUrl. '/' . $endpoint.'?'. http_build_query($data), $headers);
                    break;

                case 'oauth':
                    $wrappedData = array('body'=>$data);
                    $response = $this->client->post($this->oauthAccessTokenURL, $wrappedData);
                    break;
                
                default:
                    # code...
                    break;
            }

            return $response;

        } catch (RequestException $e) {
            if($e->getCode() === 404) {
                throw new Appointedd_NotFound($e->getMessage());
            } else {
                throw new Appointedd_HTTPError($e->getMessage());
            }
        }

        return;
    }
}