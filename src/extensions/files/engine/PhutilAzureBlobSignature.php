<?php

final class PhutilAzureBlobSignature extends Phobject {
  const CANON_HEADER_PREF = 'x-ms-';
  private $accountKey;
  private $accountName;

  private $date;

  public function setAccountName($account_name) {
    $this->accountName = $account_name;
    return $this;
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
      $this->date = gmdate('D, d M Y H:i:s', time()) . ' GMT';
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
    $future->addHeader('X-Ms-Date', $this->getDate());
    $future->addHeader('X-Ms-Version', '2019-07-07');

    $string_to_sign = $this->getCanonicalRequest($future);

    $signing_key = $this->getAccountKey()->openEnvelope();

    $signature = hash_hmac(
      'sha256',
      $string_to_sign,
      base64_decode($signing_key),
      true
    );

    $authorization =
      'SharedKey ' . $this->getAccountName() . ':' . base64_encode($signature);

    $future->addHeader('Authorization', $authorization);

    return $future;
  }

  private function getCanonicalRequest(HTTPSFuture $future) {
    $http_method = $future->getMethod();

    $canonical_parameters = $this->getCanonicalParameterList($future);
    $canonical_headers = $this->getCanonicalHeaderList($future);
    $canonical_resource = $this->getCanonicalResource($future);
    $headers = $this->getNormalizedHeaderMap($future);

    $canonical_request = implode("\n", [
      $http_method,
      idx($headers, "content-encoding", ""),
      idx($headers, "content-language", ""),
      idx($headers, "content-length", ""),
      idx($headers, "content-md5", ""),
      idx($headers, "content-type", ""),
      idx($headers, "date", ""),
      idx($headers, "if-modified-since", ""),
      idx($headers, "if-match", ""),
      idx($headers, "if-none-match", ""),
      idx($headers, "if-unmodified-since", ""),
      idx($headers, "range", ""),
      $canonical_headers,
      $canonical_resource,
    ]);

    return phutil_utf8ize($canonical_request);
  }

  private function getStringToSign($request_signature) {
    return $request_signature;
  }

  private function getCanonicalResource(HTTPSFuture $future) {
    return "/" .
      implode(
        "/",
        array_merge(
          [$this->getAccountName()],
          array_filter(explode("/", $this->getPath($future)))
        )
      );
  }

  private function getCanonicalHeaderList(HTTPSFuture $future) {
    $headers = $this->getCanonicalHeaderMap($future);

    $canonical_headers = [];
    foreach ($headers as $header => $header_value) {
      $canonical_headers[] = $header . ':' . trim($header_value);
    }

    return implode("\n", $canonical_headers);
  }

  private function getNormalizedHeaderMap(HTTPSFuture $future) {
    $headers = $future->getHeaders();
    $header_map = [];

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
        return substr($k, 0, strlen(self::CANON_HEADER_PREF)) ===
          self::CANON_HEADER_PREF;
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
}
