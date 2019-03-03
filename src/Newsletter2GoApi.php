<?php
/*
 * Implements Class Newsletter2GoApi for connection to the Newsletter2go service  
 */

class Newsletter2GoApi{

  const GRANT_TYPE = "https://nl2go.com/jwt";
  const BASE_URL = "https://api.newsletter2go.com";

  const METHOD_GET = "GET";
  const METHOD_POST = "POST";
  const METHOD_PATCH = "PATCH";
  const METHOD_DELETE = "DELETE";

  private $user_email = "email";
  private $user_pw = "password";
  private $user_auth_key = "authkey";

  private $access_token = "";
  private $refresh_token = "";

  private $sslVerification = true;

  /**
   * constructor
   * @param $authKey
   * @param $userEmail
   * @param $userPassword
   */
  function __construct($authKey, $userEmail, $userPassword) {
    $this->user_auth_key = $authKey;
    $this->user_email = $userEmail;
    $this->user_pw = $userPassword;
  }

  /*
   * Public function auth()
   */
  public function auth() {
    $this->getToken();
  }

  /*
   * Private function Get Access Token
   */
  private function getToken() {
    $endpoint = "/oauth/v2/token";
    $data = array(
      "username"   => $this->user_email,
      "password"   => $this->user_pw,
      "grant_type" => static::GRANT_TYPE
    );
    try {
      $response = $this->_curl('Basic ' . base64_encode($this->user_auth_key), $endpoint, $data, "POST");
      $this->access_token = $response->access_token;
      $this->refresh_token = $response->refresh_token;
    } catch (Exception $e) {
    }
  }

  /*
   * Create new recipient or updates existing ones
   */
  public function addRecipientToList($params) {
    $data = array(
      'list_id' => $params['list_id'],
      'email' => $params['email'],
      "phone" => $params['phone'],
      "gender" => $params['gender'],
      "first_name" => $params['first_name'],
      "last_name" => $params['last_name']
    );

    $endpoint = "/recipients";
    try {
      return $this->curl($endpoint, $data,static::METHOD_POST);
    } catch (Exception $e) {
    }

    return false;
  }

  /**
   * @param $endpoint string the endpoint to call (see docs.newsletter2go.com)
   * @param $data array tha data to submit. In case of POST and PATCH its submitted as the body of the request. In case of GET and PATCH it is used as GET-Params. See docs.newsletter2go.com for supported parameters.
   * @param string $type GET,PATCH,POST,DELETE
   * @return \stdClass
   * @throws \Exception
   */
  public function curl($endpoint, $data, $type = "GET") {
    if (!isset($this->access_token) || strlen($this->access_token) == 0) {
      $this->getToken();
    }
    if (!isset($this->access_token) || strlen($this->access_token) == 0) {
      throw new \Exception("Authentication failed");
    }
    return $this->_curl('Bearer ' . $this->access_token, $endpoint, $data, $type);
  }

  /**
   * @param $authorization
   * @param $endpoint
   * @param $data
   * @param string $type
   * @return mixed
   * @throws Exception
   */
  private function _curl($authorization, $endpoint, $data, $type = "GET") {

    $ch = curl_init();
    $data_string = json_encode($data);
    $get_params = "";

    if ($type == static::METHOD_POST || $type == static::METHOD_PATCH) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
      if ($type == static::METHOD_POST) {
        curl_setopt($ch, CURLOPT_POST, true);
      }
    } else {
      if ($type == static::METHOD_GET || $type == static::METHOD_DELETE) {
        $get_params = "?" . http_build_query($data);
      } else {
        throw new \Exception("Invalid HTTP method: " . $type);
      }
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
    curl_setopt($ch, CURLOPT_URL, static::BASE_URL . $endpoint . $get_params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: ' . $authorization,
        'Content-Length: ' . ($type == static::METHOD_GET || $type == static::METHOD_DELETE) ? 0 : strlen($data_string)
    ));

    if (!$this->sslVerification) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
  }
}