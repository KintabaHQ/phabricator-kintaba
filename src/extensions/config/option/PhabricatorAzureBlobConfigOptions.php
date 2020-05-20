<?php

final class PhabricatorAzureBlobConfigOptions extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Azure Blob Storage');
  }

  public function getDescription() {
    return pht('Configure integration with Azure Blob Storage.');
  }

  public function getIcon() {
    return 'fa-hdd-o';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('azure-blob.endpoint', 'string', null)
           ->setLocked(false)
           ->setHidden(false)
           ->setDescription(pht('The URL for the Azure Blob.')),
      $this->newOption('azure-blob.account-name', 'string', null)
           ->setLocked(false)
           ->setHidden(false)
           ->setDescription(pht('The account name for the azure blob.')),
      $this->newOption('azure-blob.account-key', 'string', null)
           ->setLocked(false)
           ->setHidden(false)
           ->setDescription(pht('The shared account key for the azure blob.')),
      $this->newOption('azure-blob.container', 'string', null)
           ->setLocked(false)
           ->setHidden(false)
           ->setDescription(pht('The container for the azure blob.'))
    );
  }
}
