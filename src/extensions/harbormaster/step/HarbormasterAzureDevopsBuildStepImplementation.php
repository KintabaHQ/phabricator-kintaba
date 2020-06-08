<?php

final class HarbormasterAzureDevopsBuildStepImplemention extends
  HarbormasterBuildStepImplementation {
  public function getName() {
    return pht('Build with Azure Devops');
  }

  public function getGenericDescription() {
    return pht('Trigger a build in Azure Devops.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterExternalBuildStepGroup::GROUPKEY;
  }

  public function getDescription() {
    return pht('Run a build in Azure Devops.');
  }

  public function getEditInstructions() {
    $hook_uri = '/kintaba/harbormaster/hook/azure';
    $hook_uri = PhabricatorEnv::getProductionURI($hook_uri);

    return pht(
      <<<EOTEXT
WARNING: This build step is totally not under warrenty.
Use at your owh risk.

# Get a Personal Access Token

See https://docs.microsoft.com/en-us/azure/devops/organizations/accounts/use-personal-access-tokens-to-authenticate?view=azure-devops&tabs=preview-page

You'll want to create a token that has the "Read and execute builds" permission.  Without that permission this won't work.

Take note of the token and paste into the tokens field.  Use your Azure username alongside the token.

# Set up Webhook
Follow these instructions: https://docs.microsoft.com/en-us/azure/devops/service-hooks/services/webhooks?view=azure-devops

Set up a POST via HTTP webhook to: `%s`

# Additional Setup

You'll need three remaining pieces of information:

1. Your project
2. Your organization
3. The build definition ID you'll want to use.
EOTEXT
      ,
      $hook_uri
    );
  }

  public function getFieldSpecifications() {
    return [
      'organization' => [
        'name' => pht('Organization Name'),
        'type' => 'text',
        'required' => true,
      ],
      'project' => [
        'name' => pht('Project Name'),
        'type' => 'text',
        'required' => true,
      ],
      'user' => [
        'name' => pht('Username'),
        'type' => 'text',
        'required' => true,
      ],
      'buildDefinitionID' => [
        'name' => pht('Build Definition ID'),
        'type' => 'text',
        'required' => true,
      ],
      'token' => [
        'name' => pht('API Token'),
        'type' => 'credential',
        'credential.type' => PassphraseTokenCredentialType::CREDENTIAL_TYPE,
        'credential.provides' => PassphraseTokenCredentialType::PROVIDES_TYPE,
        'required' => true,
      ],
    ];
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target
  ) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      $this->logSilencedCall($build, $build_target, pht('Azure Devops'));
      throw new HarbormasterBuildFailureException();
    }

    $buildable = $build->getBuildable();

    $object = $buildable->getBuildableObject();

    $source_branch = $this->getSourceBranch($object);
    $organization = $this->getSetting('organization');
    $project = $this->getSetting('project');

    $uri = urisprintf(
      'https://dev.azure.com/%s/%s/_apis/build/builds?api-version=5.1',
      $organization,
      $project
    );

    $data_structure = [
      'sourceBranch' => $source_branch,
      'definition' => [
        'id' => $this->getSetting('buildDefinitionID'),
      ],
      'triggerInfo' => [
        'HARBORMASTER_BUILD_TARGET_PHID' => $build_target->getPHID(),
      ],
    ];

    $engine = HarbormasterBuildableEngine::newForObject($object, $viewer);

    $json_data = phutil_json_encode($data_structure);

    $username = $this->getSetting('user');
    $credential_phid = $this->getSetting('token');
    $api_token = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withPHIDs([$credential_phid])
      ->needSecrets(true)
      ->executeOne();
    if (!$api_token) {
      throw new Exception(
        pht('Unable to load API token ("%s")', $credential_phid)
      );
    }

    $token = $api_token->getSecret()->openEnvelope();

    $encoded_auth = "{$username}:{$token}";
    $encoded_auth = base64_encode($encoded_auth);
    $future = id(new HTTPSFuture($uri, $json_data))
      ->setMethod('POST')
      ->addHeader('Content-Type', 'application/json')
      ->addHeader('Content-Length', strlen($json_data))
      ->addHeader('Accept', 'application/json')
      ->addHeader('Authorization', "Basic {$encoded_auth}")
      ->setTimeout(60);

    $this->resolveFutures($build, $build_target, [$future]);

    $this->logHTTPResponse($build, $build_target, $future, pht('Azure Devops'));

    list($status, $body) = $future->resolve();
    if ($status->isError()) {
      throw new HarbormasterBuildFailureException();
    }

    $response = phutil_json_decode($body);

    $build_uri = idxv($response, ['_links', 'web', 'href']);
    if (!$build_uri) {
      throw new Exception(pht('Azure Devops did not return a "%s"!', $uri_key));
    }

    $target_phid = $build_target->getPHID();

    $api_method = 'harbormaster.createartifact';
    $api_params = [
      'buildTargetPHID' => $target_phid,
      'artifactType' => HarbormasterURIArtifact::ARTIFACTCONST,
      'artifactKey' => 'azure.uri',
      'artifactData' => [
        'uri' => $build_uri,
        'name' => pht('View in Azure Devops'),
        'ui.external' => true,
      ],
    ];

    id(new ConduitCall($api_method, $api_params))
      ->setUser($viewer)
      ->execute();
  }

  private function getSourceBranch($build_target) {
    if ($build_target instanceof DifferentialDiff) {
      return $build_target->getStagingRef();
    } elseif ($build_target instanceof PhabricatorRepositoryCommit) {
      $viewer = PhabricatorUser::getOmnipotentUser();
      $repository = $build_target->getRepository();

      $branches = DiffusionQuery::callConduitWithDiffusionRequest(
        $viewer,
        DiffusionRequest::newFromDictionary([
          'repository' => $repository,
          'user' => $viewer,
        ]),
        'diffusion.branchquery',
        [
          'contains' => $build_target->getCommitIdentifier(),
          'repository' => $repository->getPHID(),
        ]
      );

      if (!$branches) {
        throw new Exception(
          pht(
            'Commit "%s" is not an ancestor of any branch head, so it can not ' .
              'be built with Azure Devops.',
            $build_target->getCommitIdentifier()
          )
        );
      }

      $branch = head($branches);

      return 'refs/heads/' . $branch['shortName'];
    }

    throw new Exception(
      pht('This object does not support builds with Azure Devops.')
    );
  }

  public function supportsWaitForMessage() {
    // NOTE: We always wait for a message, but don't need to show the UI
    // control since "Wait" is the only valid choice.
    return false;
  }

  public function shouldWaitForMessage(HarbormasterBuildTarget $target) {
    return true;
  }
}
