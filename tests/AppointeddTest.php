<?php

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;

class AppointeddTest extends \PHPUnit_Framework_TestCase {

	private $stub;

	public function __construct(){
		$this->stub = new StubAppointedd();
	}

	public function testGetAuthoriseURL() {
		$returnUrl = 'http://google.com';
		$url = $this->stub->getAuthoriseURL($returnUrl);
		
		$this->assertInternalType('string', $url);
		$this->assertStringEndsWith(urlencode($returnUrl), $url);
	}

	public function testSetAccessToken() {
		$token = '123';
		
		$instance = $this->stub->setAccessToken($token);
		$this->assertInstanceOf('Appointedd\Appointedd\Appointedd', $instance);
	}

	public function testMissingAccessToken() {
		$this->setExpectedException('InvalidArgumentException', 'MissingAccessToken');
		$this->stub->setAccessToken();
	}

	public function testSetClientCredentials() {
		$clientId = '538745a8c202222b7cc3f1b8';
		$clientSecret = '538745a8c202222b7cc3f1b8';
		
		$instance = $this->stub->setClientCredentials($clientId, $clientSecret);
		$this->assertInstanceOf('Appointedd\Appointedd\Appointedd', $instance);
	}

	public function testMissingUsername() {
		$this->setExpectedException('InvalidArgumentException', 'MissingUsername');
		$this->stub->getAccessTokenFromLogin(null,123);	
	}

	public function testMissingPassword() {
		$this->setExpectedException('InvalidArgumentException', 'MissingPassword');
		$this->stub->getAccessTokenFromLogin(123,null);
	}

	public function testVerbs() {
		$verbs = array('get', 'put', 'post', 'delete');
		foreach ($verbs as $key => $value) {
			$this->_testErrorCodes($value);
			$this->_testSuccessCode($value);
		}
	}

	private function _testSuccessCode($verb) {
		$mock = new Mock(array(
		    new Response(200, array()),         // Use response object
		    "HTTP/1.1 202 OK\r\nContent-Length: 0\r\n\r\n"  // Use a response string
		));

		$client = new Client();
		$client->getEmitter()->attach($mock);

		$stub = new StubAppointedd(null, $client);

		$response = $stub->$verb('/');

		$this->assertInstanceOf('GuzzleHttp\Message\Response', $response);

		$this->assertEquals(200, $response->getStatusCode());
	}

	private function _testErrorCodes($verb) {
		$codes = array(400,401,402,403,404,405,406,407,408,500,501,502,503,504);
		foreach ($codes as $key => $value) {
			$this->_testErrorCode($verb, $value);
		}
	}

	private function _testErrorCode($verb, $code) {
		$mock = new Mock(array(
		    new Response($code, array()),         // Use response object
		    "HTTP/1.1 $code\r\nContent-Length: 0\r\n\r\n"  // Use a response string
		));

		$client = new Client();
		$client->getEmitter()->attach($mock);

		$stub = new StubAppointedd(null, $client);

		$response = $stub->$verb('/');

		$this->assertInternalType('array', $response);
		$this->assertEquals(1, count($response));
		$this->assertRegExp('/'.$code.'/i',$response['error']);
	}
}

class StubAppointedd extends \Appointedd\Appointedd\Appointedd {}