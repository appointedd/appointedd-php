<?php namespace Appointedd\Appointedd;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Exception;


class Appointedd_Error extends Exception {

    public function __construct($message, $code = null, $response = null) {
        $this->message = $message;
        $this->code = $code;
        $this->response = $response;
    }
}

class Appointedd_MissingArgument extends Appointedd_Error {}
class Appointedd_HTTPError extends Appointedd_Error {}
class Appointedd_NotFound extends Appointedd_Error {}
class Appointedd_Unauthorised extends Appointedd_Error {}
class Appointedd_Conflict extends Appointedd_Error {}
class Appointedd_CardError extends Appointedd_Error {}

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
    private $oauthAuthoriseURL;
    private $oauthAccessTokenURL;
    private $accessToken;

    // base url for api calls
    private $apiUrl;

    /**
     * Construct sets up httpClient and accessToken
     * @param string|null $accessToken 
     * @param \GuzzleHttp\Client|null $httpClient 
     */
    public function __construct($accessToken = null, \GuzzleHttp\Client $httpClient = null) {

        // default domain
        $domain = 'https://internal-api.appointedd.com';

        // override the domain
        if(getenv('APPOINTEDD_ENV_DOMAIN')) {
            $domain = getenv('APPOINTEDD_ENV_DOMAIN');
        }

        $this->oauthAuthoriseURL = ($domain . '/oauth/authorise');
        $this->oauthAccessTokenURL = ($domain . '/oauth/access_token');
        $this->apiUrl = $domain;

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
     * Chained method. Takes clientId create new class instance and passes back to next in chain
     * @param string $clientId
     * @return class instance
     */
    public static function setClient($clientId){

        if(!$clientId)
            throw new Appointedd_MissingArgument('Client ID is missing');

        $appointeddInstance = new Appointedd;

        $appointeddInstance->clientId = $clientId;

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
    	if($this->clientId) {
            $data['client_id'] = $this->clientId;
        }
        $response = $this->call($endpoint, 'get', $data);
        return $response;
    }

    public function put($endpoint, $data=array()) {
    	if($this->clientId) {
            $data['client_id'] = $this->clientId;
        }
        $response = $this->call($endpoint, 'put', $data);
        return $response;
    }

    public function post($endpoint, $data=array()) {
        if($this->clientId) {
            $data['client_id'] = $this->clientId;
        }
    	$response = $this->call($endpoint, 'post', $data);
        return $response;
    }

    public function delete($endpoint, $data=array()) {
    	if($this->clientId) {
            $data['client_id'] = $this->clientId;
        }
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

        $headers = array();
        
        if($this->accessToken)
            $headers['Access-Token'] = $this->accessToken;

        try {
            
            switch ($type) {
                case 'get':
                    $response = $this->client->get($this->apiUrl. '/' . $endpoint.'?'. http_build_query($data), array('headers' => $headers));
                    break;

                case 'put':
                    $response = $this->client->put($this->apiUrl. '/' . $endpoint, array('body'=>$data, 'headers' => $headers));
                    break;

                case 'post':
                    $response = $this->client->post($this->apiUrl. '/' . $endpoint, array('body'=>$data, 'headers' => $headers));               
                    break;

                case 'delete':
                    $response = $this->client->delete($this->apiUrl. '/' . $endpoint.'?'. http_build_query($data),  array('headers' => $headers));
                    break;

                case 'oauth':
                    $wrappedData = array('body'=>$data, 'headers' => $headers);
                    $response = $this->client->post($this->oauthAccessTokenURL, $wrappedData);
                    break;
                
                default:
                    # code...
                    break;
            }

            return $response;

        } catch (RequestException $e) {
                
            $responseBody = $e->getResponse()->json();

            if($e->getCode() === 404) {
                throw new Appointedd_NotFound($e->getMessage(), $e->getCode(), $responseBody);
            } else if($e->getCode() === 401) {
                throw new Appointedd_Unauthorised($e->getMessage(), $e->getCode(), $responseBody);
            } else if($e->getCode() === 409){
                throw new Appointedd_Conflict($e->getMessage(), $e->getCode(), $responseBody);
            } else if($e->getCode() === 402){
                throw new Appointedd_CardError($e->getMessage(), $e->getCode(), $responseBody);
            } else {
                throw new Appointedd_HTTPError($e->getMessage(), $e->getCode(), $responseBody);
            }

        } catch (Exception $e) {
            throw new Appointedd_HTTPError($e->getMessage());
        }

        return;
    }
}