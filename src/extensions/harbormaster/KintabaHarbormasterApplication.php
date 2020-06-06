<?php

final class KintabaHarbormasterApplication extends PhabricatorApplication {
  public function getName() {
    return pht('Kintaba Harbormaster');
  }

  public function getShortDescription() {
    return pht('Build/CI extensions for Kintaba.');
  }

  public function getIcon() {
    return 'fa-ship';
  }

  public function getTitleGlyph() {
    return "\xE2\x99\xBB";
  }

  public function getFlavorText() {
    return pht('Ship Some Freight');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRemarkupRules() {
    return [new HarbormasterRemarkupRule()];
  }

  public function getRoutes() {
    return [
      '/kintaba/' => [
        'harbormaster/' => [
          'hook/' => [
            'azure' => 'HarbormasterAzureDevopsHookController',
          ],
        ],
      ],
    ];
  }
}
