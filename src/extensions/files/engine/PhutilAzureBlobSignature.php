<?php

final class PhutilAzureBlobSignature extends Phobject {
  const CANON_HEADER_PREF = 'x-ms-';
  private $accountKey;
  private $accountName;

  private $date;

  public function setAccountName($account_name) {
    $this->accountName = $account_name;
  }

  public function getAccountName() {
    return $this->accountName;
  }

  public function setAccountKey(PhutilOpaqueEnvelope $account_key) {
    $this->accountKey = $account_key;
    return $this;
  }

  public function getAccountKey() {
    return $this->accountKey;
  }

  public function setDate($date) {
    $this->date = $date;
    return $this;
  }

  public function getDate() {
    if ($this->date === null) {
      $this->date = gmdate('D, d M Y H:i:s e', time());
    }
    return $this->date;
  }

  private function getAlgorithm() {
    return 'HMAC-SHA256';
  }

  private function getHost(HTTPSFuture $future) {
    $uri = new PhutilURI($future->getURI());
    return $uri->getDomain();
  }

  private function getPath(HTTPSFuture $future) {
    $uri = new PhutilURI($future->getURI());
    return $uri->getPath();
  }

  public function signRequest(HTTPSFuture $future) {
    $body_signature = $this->getBodySignature($future);

    $future->addHeader('X-Ms-Date', $this->getDate());

    $request_signature = $this->getCanonicalRequestSignature(
      $future,
      $body_signature
    );

    $string_to_sign = $this->getStringToSign($request_signature);

    $signing_key = $this->getSigningKey();

    $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

    $authorization =
                   'SharedKey '.
                   $this->getAccountName().':'.
                   $signature;

    $future->addHeader('Authorization', $authorization);

    return $future;
  }

  private function getCanonicalRequestSignature(
    HTTPSFuture $future,
    $body_signature
  ) {

    $http_method = $future->getMethod();

    $path = $this->getPath($future);
    $path = rawurlencode($path);
    $path = str_replace('%2F', '/', $path);

    $canonical_parameters = $this->getCanonicalParameterList($future);
    $canonical_headers = $this->getCanonicalHeaderList($future);
    $canonical_resource = $this->getCanonicalResource($future);
    $headers = $this->getNormalizedHeaderMap();

    $canonical_request = implode(
      "\n",
      array_map(
        array(
          $http_method,
          $headers["content-encoding"],
          $headers["content-language"],
          $headers["content-length"],
          $headers["content-md5"],
          $headers["content-type"],
          $headers["date"],
          $headers["if-modified-since"],
          $headers["if-match"],
          $headers["if-none-match"],
          $headers["if-unmodified-since"],
          $headers["range"],
          $canonical_headers,
          $canonical_resource,
        ),
        function ($x) {
          return $x ?: "";
        }
      )
    );

    return hash('sha256', $canonical_request);
  }

  private function getStringToSign($request_signature) {
    return $request_signature;
  }

  private function getCanonicalResource(HTTPSFuture $future) {
    return implode("/", array(
      "",
      $this->getAccountName(),
      $this->getPath(),
    ));
  }

  private function computeSigningKey() {
    $account_key = $this->accountKey;
    if (!$account_key) {
      throw new Exception(
        pht(
          'You must provide an account key with setAccountKey().'));
    }

    // NOTE: This part of the algorithm uses the raw binary hashes, and the
    // result is not human-readable.
    $raw_hash = true;

    $signing_key = $secret_key->openEnvelope();

    $scope_parts = $this->getScopeParts();
    foreach ($scope_parts as $scope_part) {
      $signing_key = hash_hmac('sha256', $scope_part, $signing_key, $raw_hash);
    }

    return $signing_key;
  }

  private function getCanonicalHeaderList(HTTPSFuture $future) {
    $headers = $this->getCanonicalHeaderMap($future);

    $canonical_headers = array();
    foreach ($headers as $header => $header_value) {
      $canonical_headers[] = $header.':'.trim($header_value);
    }

    return implode("\n", $canonical_headers);
  }

  private function getNormalizedHeaderMap(HTTPSFuture $future) {
    $headers = $future->getHeaders();
    $header_map = array();

    foreach ($headers as $header) {
      list($key, $value) = $header;
      $key = phutil_utf8_strtolower($key);
      $header_map[$key] = $value;
    }

    ksort($header_map);

    return $header_map;
  }

  private function getCanonicalHeaderMap(HTTPSFuture $future) {
    return array_filter(
      $this->getNormalizedHeaderMap($future),
      function ($k) {
        return substr($k, 0, strlen(self::CANON_HEADER_PREF)) === self::CANON_HEADER_PREF;

      },
      ARRAY_FILTER_USE_KEY
    );
  }

  private function getCanonicalParameterList(HTTPSFuture $future) {
    $uri = new PhutilURI($future->getURI());
    $params = $uri->getQueryParamsAsMap();

    ksort($params);
    $canonical_parameters = phutil_build_http_querystring($params);

    return $canonical_parameters;
  }

  private function getCredential() {
    $access_key = $this->accessKey;
    if (!strlen($access_key)) {
      throw new PhutilInvalidStateException('setAccessKey');
    }

    $parts = $this->getScopeParts();
    array_unshift($parts, $access_key);

    return implode('/', $parts);
  }
}
