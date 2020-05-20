<?php

final class PhabricatorAzureBlobFileStorageEngine extends PhabricatorFileStorageEngine {
  public function getEngineIdentifier() {
    return 'azure-blob';
  }

  public function getEnginePriority() {
    return 99;
  }

  public function canWriteFiles() {
    $endpoint = PhabricatorEnv::getEnvConfig('azure-blob.endpoint');
    $account_name = PhabricatorEnv::getEnvConfig('azure-blob.account-name');
    $account_key = PhabricatorEnv::getEnvConfig('azure-blob.account-key');
    $container = PhabricatorEnv::getEnvConfig('azure-blob.container');

    return (
      strlen($endpoint) &&
      strlen($account_name) &&
      strlen($account_key) &&
      strlen($container)
    );
  }

  public function writeFile($data, array $params) {
    $az = $this->newAzureBlobAPI();

    $seed = Filesystem::readRandomCharacters(20);
    $parts = array();
    $parts[] = 'phabricator';

    $instance_name = PhabricatorEnv::getEnvConfig('cluster.instance');
    if (strlen($instance_name)) {
      $parts[] = $instance_name;
    }

    $parts[] = substr($seed, 0, 2);
    $parts[] = substr($seed, 2, 2);
    $parts[] = substr($seed, 4);

    $name = implode('/', $parts);

    AphrontWriteGuard::willWrite();
    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 'azure-blob',
        'method' => 'putObject',
      )
    );

    $az
      ->setParametersForPutObject($handle)
      ->resolve();

    $profiler->endServiceCall($call_id, array());
    return $name;
  }

  public function readFile($handle) {
    $az = $this->newAzureBlobAPI();

    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 'azure-blob',
        'method' => 'getObject',
      )
    );

    $az
      ->setParametersForGetObject($handle)
      ->resolve();

    $profiler->endServiceCall($call_id, array());

    return $result;
  }

  public function deleteFile($handle) {
    $az = $this->newAzureBlobAPI();

    AphrontWriteGuard::willWrite();
    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 'azure-blob',
        'method' => 'deleteObject',
      )
    );

    $az
      ->setParametersForDeleteObject($handle)
      ->resolve();

    $profiler->endServiceCall($call_id, array());
  }

  private function newAzureBlobAPI() {
    $endpoint = PhabricatorEnv::getEnvConfig('azure-blob.endpoint');
    $account_name = PhabricatorEnv::getEnvConfig('azure-blob.account-name');
    $account_key = PhabricatorEnv::getEnvConfig('azure-blob.account-key');
    $container = PhabricatorEnv::getEnvConfig('azure-blob.container');

    return id(new PhutilAzureBlobFuture())
      ->setAccountKey($account_key)
      ->setAccountName($account_name)
      ->setContainerName($container)
      ->setEndpoint($endpoint);
  }
}
