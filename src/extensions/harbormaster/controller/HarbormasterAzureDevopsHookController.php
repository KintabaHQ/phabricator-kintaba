<?php

final class HarbormasterAzureDevopsHookController extends
  HarbormasterController {
  public function shouldRequireLogin() {
    return false;
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function handleRequest(AphrontRequest $request) {
    $raw_body = PhabricatorStartup::getRawInput();
    $body = phutil_json_decode($raw_body);

    $resource = $body['resource'];

    $target_phid = idxv($resource, [
      'triggerInfo',
      'HARBORMASTER_BUILD_TARGET_PHID',
    ]);
    if ($target_phid) {
      $viewer = PhabricatorUser::getOmnipotentUser();
      $target = id(new HarbormasterBuildTargetQuery())
        ->setViewer($viewer)
        ->withPHIDs([$target_phid])
        ->needBuildSteps(true)
        ->executeOne();

      if ($target) {
        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $this->updateTarget($target, $resource);
      }
    }

    $response = new AphrontWebpageResponse();
    $response->setContent(pht("Request OK\n"));
    return $response;
  }

  private function updateTarget(
    HarbormasterBuildTarget $target,
    array $resource
  ) {
    $step = $target->getBuildStep();
    $impl = $step->getStepImplementation();

    switch (idx($resource, 'result')) {
      case 'succeeded':
      case 'partiallySucceeded':
        $message_type = HarbormasterMessageType::MESSAGE_PASS;
        break;

      default:
        $message_type = HarbormasterMessageType::MESSAGE_FAIL;
        break;
    }

    $viewer = PhabricatorUser::getOmnipotentUser();

    $api_method = 'harbormaster.sendmessage';
    $api_params = [
      'buildTargetPHID' => $target->getPHID(),
      'type' => $message_type,
    ];

    id(new ConduitCall($api_method, $api_params))
      ->setUser($viewer)
      ->execute();
  }
}
