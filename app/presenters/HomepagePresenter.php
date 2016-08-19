<?php

namespace App\Presenters;

use Nette;
use Facebook,
    Facebook\FacebookRequest;

class HomepagePresenter extends Nette\Application\UI\Presenter {

    /**
     * @var array config of integrated facebook app
     */
    protected $facebookAppConfig = [];

    /**
     * @var Facebook\Facebook
     */
    protected $facebookHandler;

    /**
     * @persistent
     * @var string default fb access token
     */
    public $defaultAccessToken;

    /**
     * @persistent
     * @var string logged account ID
     */
    public $loggedAccountId = null;

    /**
     * @persistent
     * @var string selected account ID
     */
    public $accountId = null;

    public function startup() {
	parent::startup();

	$this->facebookAppConfig = $this->context->parameters['facebookApp'];

	$this->facebookHandler = new Facebook\Facebook([
	    'app_id' => $this->facebookAppConfig['appId'],
	    'app_secret' => $this->facebookAppConfig['appSecret'],
	    'default_graph_version' => $this->facebookAppConfig['graphVersion'],
	]);
    }

    public function beforeRender() {
	parent::beforeRender();

	if ($this->loggedAccountId !== null) {
	    try {
		$response = $this->facebookHandler->get('/' . $this->accountId . '?metadata=1&fields=id,name,picture,metadata{type}', $this->defaultAccessToken);
	    } catch (Facebook\Exceptions\FacebookResponseException $e) {
		//echo 'Graph returned an error: ' . $e->getMessage();
		$this->flashMessage("There have been some error during loading informations about logged user. Please log in again.", "danger");
		$this->actionLogout(false);
	    } catch (Facebook\Exceptions\FacebookSDKException $e) {
		//echo 'Facebook SDK returned an error: ' . $e->getMessage();
		$this->flashMessage("There have been some error during loading informations about logged user. Please log in again.", "danger");
		$this->actionLogout(false);
	    }

	    $this->template->loggedUserInfo = $response->getGraphNode();
	}

	$this->template->facebookAppId = $this->facebookAppConfig['appId'];
	$this->template->facebookDefaultGraphVersion = $this->facebookAppConfig['graphVersion'];
	$this->template->accountId = $this->accountId;
    }

    public function renderJsLogin() {


	$helper = $this->facebookHandler->getJavaScriptHelper();

	try {
	    $accessToken = $helper->getAccessToken();
	} catch (Facebook\Exceptions\FacebookResponseException $e) {
	    //echo 'Graph returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during logging in. Please log in again.", "danger");
	    $this->actionLogout(false);
	} catch (Facebook\Exceptions\FacebookSDKException $e) {
	    // When validation fails or other local issues
	    //echo 'Facebook SDK returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during logging in. Please log in again.", "danger");
	    $this->actionLogout(false);
	}

	if (!isset($accessToken)) {
	    echo 'No cookie set or no OAuth data could be obtained from cookie.';
	    exit;
	}

	$this->defaultAccessToken = (string) $accessToken;
	$this->redirect("listAccounts");
    }

    public function renderListAccounts() {
	if ($this->defaultAccessToken === null) {
	    $this->flashMessage("No facebook access token.", "danger");
	    $this->redirect("default");
	}

	try {
	    $response = $this->facebookHandler->get('/me?fields=id,name,picture', $this->defaultAccessToken);
	} catch (Facebook\Exceptions\FacebookResponseException $e) {
	    //echo 'Graph returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during loading informations about you. Please log in again.", "danger");
	    $this->actionLogout(false);
	} catch (Facebook\Exceptions\FacebookSDKException $e) {
	    $this->flashMessage("There have been some error during loading informations about you. Please log in again.", "danger");
	    $this->actionLogout(false);
	}

	$this->template->loggedUserInfo = $response->getGraphUser();
	$this->loggedAccountId = $this->template->loggedUserInfo['id'];

	try {
	    $response = $this->facebookHandler->get('/me/accounts?fields=id,name,access_token,picture', $this->defaultAccessToken);
	} catch (Facebook\Exceptions\FacebookResponseException $e) {
	    //echo 'Graph returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during loading informations about your accounts. Please log in again.", "danger");
	    $this->actionLogout(false);
	} catch (Facebook\Exceptions\FacebookSDKException $e) {
	    //echo 'Facebook SDK returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during loading informations about your accounts. Please log in again.", "danger");
	    $this->actionLogout(false);
	}
	$this->template->userAccounts = $response->getGraphEdge();
    }

    public function renderListResourcesToBroadcast($accountId, $accessToken = null) {

	$isCapableGroups = false;
	$isUser = false;

	if ($accessToken === null) {
	    $accessToken = $this->defaultAccessToken;
	}


	try {
	    $response = $this->facebookHandler->get('/' . $accountId . '?metadata=1&fields=id,name,picture,metadata{type}', $accessToken);
	} catch (Facebook\Exceptions\FacebookResponseException $e) {
	    //echo 'Graph returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during loading information about selected account. Please log in again.", "danger");
	    $this->actionLogout(false);
	} catch (Facebook\Exceptions\FacebookSDKException $e) {
	    //echo 'Facebook SDK returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during loading information about selected account. Please log in again.", "danger");
	    $this->actionLogout(false);
	}

	$this->template->selectedUserInfo = $response->getGraphNode();

	$isCapableGroups = $isUser = ($this->template->loggedUserInfo['metadata']['type'] === "user");

	try {
	    $response = $this->facebookHandler->get('/' . $accountId . '/events?fields=id,name,description,start_time,picture', $accessToken);
	} catch (Facebook\Exceptions\FacebookResponseException $e) {
	    //echo 'Graph returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during loading your events. Please log in again.", "danger");
	    $this->actionLogout(false);
	} catch (Facebook\Exceptions\FacebookSDKException $e) {
	    $this->flashMessage("There have been some error during loading your events. Please log in again.", "danger");
	    $this->actionLogout(false);
	}
	$eventsPages = [];
	$eventsPages[] = $currentPage = $response->getGraphEdge();


	while (($nextFeed = $this->facebookHandler->next($currentPage)) !== null) {
	    $eventsPages[] = $currentPage = $nextFeed;
	}

	$this->accountId = $accountId;

	$this->template->eventsPages = $eventsPages;
	$this->template->resourceAccessToken = $accessToken;

	if ($isCapableGroups) {
	    //load groups
	    //publish to groups are not allowed by Facebook API right now (v2.7)
	}
    }

    public function renderGenerateNewTokenForFbId($fbId, $accessToken = null) {

	if ($accessToken === null) {
	    $accessToken = $this->defaultAccessToken;
	}

	try {
	    $response = $this->facebookHandler->post('/' . $fbId . '/live_videos', [], $accessToken);
	} catch (Facebook\Exceptions\FacebookResponseException $e) {
	    //echo 'Graph returned an error: ' . $e->getMessage();
	    $this->flashMessage("There have been some error during generating token. Please log in again.", "danger");
	    $this->actionLogout(false);
	} catch (Facebook\Exceptions\FacebookSDKException $e) {
	    $this->flashMessage("There have been some error during generating token. Please log in again.", "danger");
	    $this->actionLogout(false);
	}
	$edge = $response->getGraphNode();

	$this->flashMessage("Token has been successfully generated.", "success");
	$this->template->liveVideoInfo = $edge;
	$this->template->fbId = $fbId;
	$this->template->accessToken = $accessToken;
    }

    public function actionLogout($displayLogoutFlash = true) {

	$token = $this->defaultAccessToken;
	try {
	    $this->facebookHandler->delete("/me/permissions", ['access_token' => $token], $token);
	} catch (Exception $e) {
	    //no handling, only "try to logout" - it could be damaged session
	}

	$this->defaultAccessToken = null;
	$this->accountId = null;
	$this->loggedAccountId = null;

	if ($displayLogoutFlash) {
	    $this->flashMessage("You have been logged out.", "info");
	}


	$this->redirect("default");
    }

}
