<?php

final class PhutilAzureBlobFuture extends FutureProxy {
  const DATE_HEADER = 'x-ms-date';
  private $accountName;
  private $accountKey;
  private $containerName;

  private $httpMethod = 'GET';
  private $path;
  private $endpoint;
  private $headers = array();
  private $data = '';

  public function __construct() {
    parent::__construct(null);
  }

  public function setAccountName($account_name) {
    $this->accountName = $account_name;
  }

  public function getAccountName() {
    return $this->accountName;
  }

  public function setContainerName($container_name) {
    $this->containerName = $container_name;
    return $this;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

  public function getContainerName() {
    return $this->containerName;
  }

  public function getPath() {
    return $this->path;
  }

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getEndpoint() {
    return $this->endpoint;
  }

  public function setEndpoint($endpoint) {
    $this->endpoint = $endpoint;
    return $this;
  }

  public function setAccessKey(PhutilOpaqueEnvelope $access_key) {
    $this->accessKey = $access_key;
    return $this;
  }

  public function getAccessKey() {
    return $this->accessKey;
  }

  public function setHTTPMethod($method) {
    $this->httpMethod = $method;
    return $this;
  }

  public function getHTTPMethod() {
    return $this->httpMethod;
  }

  public function setParametersForGetObject($key) {
    $container_name = $this->getContainerName();

    $this->setHTTPMethod('GET');
    $this->setPath($container_name.'/'.$key);

    return $this;
  }

  public function setParametersForPutObject($key, $value) {
    $container_name = $this->getContainerName();

    $this->setHTTPMethod('PUT');
    $this->setPath($container_name.'/'.$key);

    $this->addHeader('Content-Type', 'application/octet-stream');

    $this->setData($value);

    return $this;
  }

  public function setParametersForDeleteObject($key) {
    $container_name = $this->getContainerName();

    $this->setHTTPMethod('DELETE');
    $this->setPath($container_name.'/'.$key);

    return $this;
  }

  public function addHeader($key, $value) {
    $this->headers[] = array($key, $value);
    return $this;
  }

  protected function getParameters() {
    return array();
  }

  protected function getProxiedFuture() {
    if (!$this->future) {
      $params = $this->getParameters();
      $method = $this->getHTTPMethod();
      $host = $this->getEndpoint();
      $path = $this->getPath();
      $data = $this->getData();

      $uri = id(new PhutilURI("https://{$host}/", $params))
           ->setPath($path);

      $future = id(new HTTPSFuture($uri, $data))
              ->setMethod($method);

      foreach ($this->headers as $header) {
        list($key, $value) = $header;
        $future->addHeader($key, $value);
      }

      $this->signRequest($future);

      $this->future = $future;
    }

    return $this->future;
  }

  protected function signRequest(HTTPSFuture $future) {
    $account_name = $this->getAccountName();
    $account_key = $this->getAccountKey();

    id(new PhutilAzureBlobSignature())
      ->setAccountKey($account_key)
      ->setAccountName($account_name)
      ->signRequest($future);
  }

  protected function didReceiveResult($result) {
    list($status, $body, $headers) = $result;

    try {
      $xml = @(new SimpleXMLElement($body));
    } catch (Exception $ex) {
      $xml = null;
    }

    if ($status->isError() || !$xml) {
      if (!($status instanceof HTTPFutureHTTPResponseStatus)) {
        throw $status;
      }

      $params = array(
        'body' => $body,
      );
      if ($xml) {
        $params['RequestID'] = $xml->RequestID[0];
        $errors = array($xml->Error);
        foreach ($errors as $error) {
          $params['Errors'][] = array($error->Code, $error->Message);
        }
      }

      throw new PhutilAWSException($status->getStatusCode(), $params);
    }

    return $xml;
  }
}
