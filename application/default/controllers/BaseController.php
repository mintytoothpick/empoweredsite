<?php

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/UserRoles.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Util/ImageCrop.php';
require_once 'Brigade/Util/FBConnect.php';
require_once 'Zend/Auth.php';

require_once 'User.php';
require_once 'SurveyGlobalStudentEmbassy.php';

/**
 * Base controller methods.
 *
 * @author Matias Gonzalez
 */
class BaseController extends Zend_Controller_Action {

    protected $userRoles = null;
    protected $facebook = null;
    protected $fbUserInfo = null;
    protected $sessionUser = null;

    public function init() {
        $Users      = new Brigade_Db_Table_Users();
        $parameters = $this->_getAllParams();

        // Cookie login
        if (!isset($_SESSION['UserId']) && isset($_COOKIE['siteAuth'])) {
            parse_str($_COOKIE['siteAuth']);
            $auth = Zend_Auth::getInstance();
            $authAdapter = new Brigade_Util_Auth();
            $authAdapter->setIdentity($user)->setCredential($hash);
            $authResult = $auth->authenticate($authAdapter);
            if ($authResult->isValid()) {
                $userInfo = $authAdapter->_resultRow;
                if ($userInfo->Active == 1) {
                    $_SESSION['FullName'] = $userInfo->FirstName." ".$userInfo->LastName;
                    $_SESSION['UserId']   = $userInfo->UserId;
                    header("Location: " . $_SERVER['REQUEST_URI']);
                }
            }
        }

        /**
         * Set environment specific variables
         * TODO: Move this params to config.ini
         */
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $this->view->envUsername     = "admin";
            $this->view->contentLocation = "/";
            $this->view->fbAppId = "179805142035878";
            $this->view->fbAppNamespace = "empoweredorg";
            $fbAppSecret = 'f344ee840138793d6e8148f1b0374046';
        } else if($_SERVER['HTTP_HOST'] == 'dev.empowered.org') {
            $this->view->envUsername     = "dev";
            $this->view->contentLocation = "/";
            $this->view->fbAppId = "132371653490037";
            $this->view->fbAppNamespace = "empowereddev";
            $fbAppSecret = '63e9adb25978deead3729d2d016f4f6f';
        } else {
            $this->view->envUsername     = "qat";
            $this->view->contentLocation = "/";
            $this->view->fbAppId = "179837585364534";
            $this->view->fbAppNamespace = "empoweredlocal";
            $fbAppSecret = 'd51bcdff4b3d9c43b473f3d698b35194';
        }

        /*
         * Start new Facebook Connect
         */
        try {
            $this->facebook = new Facebook(array(
                'appId'  => $this->view->fbAppId,
                'secret' => $fbAppSecret,
            ));

            $fbUserId = $this->facebook->getUser();
            if ($fbUserId) {
                $this->fbUserInfo = $this->facebook->api('/' . $fbUserId);
            }
        } catch(Exception $e) {
            // Nothing to do
        }
        /*
         * End new Facebook Connect
         */

        $this->view->isLoggedIn  = false;
        $this->view->isAdmin     = false;
        $this->view->isGlobAdmin = false;

        if(isset($_SESSION['UserId'])) {
            $this->view->isLoggedIn = true;
            $this->sessionUser = $this->view->userNew = User::get($_SESSION['UserId']);

            if(!empty($this->sessionUser)) {
                $this->checkMissinInfo();

                // check global admin status
                $UserRoles = new Brigade_Db_Table_UserRoles();
                $role = $UserRoles->getUserRole($this->sessionUser->id);
                if ($role['RoleId'] == 'GLOB-ADMIN') {
                    $this->view->isGlobAdmin = true;
                    $this->view->isAdmin     = true;
                } elseif ($role['RoleId'] == 'ADMIN') {
                    $this->view->isAdmin = true;
                }
                $this->userRoles = $role;

                // check admin status for specific entities
                if (!empty($parameters['ProjectId'])) {
                    $siteId    = $parameters['ProjectId'];
                    $siteLevel = 'brigade';
                } else if(!empty($parameters['GroupId'])) {
                    $siteId    = $parameters['GroupId'];
                    $siteLevel = 'group';
                } else if(!empty($parameters['ProgramId'])) {
                    $siteId    = $parameters['ProgramId'];
                    $siteLevel = 'program';
                } else if(!empty($parameters['NetworkId'])) {
                    $siteId    = $parameters['NetworkId'];
                    $siteLevel = 'network';
                } else if(!empty($parameters['SiteId'])) {
                    $siteId    = $parameters['SiteId'];
                    $siteLevel = 'netowrk';
                }
                if (isset($siteId) && isset($siteLevel) && !$this->view->isGlobAdmin) {
                    $siteRole = $UserRoles->UserHasAccess($siteId, $this->sessionUser->id, $siteLevel);
                    $this->view->isAdmin = $siteRole;
                }
            } else {
                unset($_SESSION['UserId']);
                unset($_SESSION['FullName']);

                // fix for cookies
                $this->view->isLoggedIn  = false;
                $this->view->isAdmin     = false;
                $this->view->isGlobAdmin = false;
            }
        }
    }


    /**
     * Pre dispatcher of execution methods.
     */
    public function preDispatch() {
        if ($this->_helper->authUser->isLoggedIn()) {
            $this->_helper->layout->setLayout('main');
        }
        $this->view->render('profile/popup_user.phtml');
        $this->view->render('profile/facebook_root.phtml');

        list($usec, $sec) = explode(" ",microtime());
        $this->view->starttime = (double)$sec + (double)$usec;
    }

    public function postDispatch() {
        $analytics = new Zend_Session_Namespace('Analytics');

        // Analytics Events Tracking
        $this->view->createdOrganization = false;
        if ($analytics->organizationCreated) {
            $this->view->createdOrganization = true;
            $analytics->organizationCreated  = false;
        }

        $this->view->render('common/analytics.phtml');
        $this->view->render('profile/popup_login.phtml');
    }

    /**
     * Check and validate missing info of the logged user.
     * Used for name and last name + surveys inclomplete.
     */
    protected function checkMissinInfo() {
        $config = Zend_Registry::get('configuration');

        //Validate complete name
        $this->view->needNameInfo = false;
        if (((trim($this->sessionUser->fullName) == trim($this->sessionUser->email))
            && $this->sessionUser->firstLogin == "0") ||
            trim($this->sessionUser->fullName) == "" ||
            trim($this->sessionUser->firstName) == "" ||
            trim($this->sessionUser->lastName) == "") {
            $this->view->needNameInfo = true;
        }

        //Validate incomplete survey
        if ($this->_getParam('action') != 'editsurvey' &&
            $this->_getParam('action') != 'customsurvey'
        ) {
            $this->view->missingSurveys = false;
            foreach ($this->sessionUser->initiatives as $project) {
                $isAdmin = false; //avoid popup for admins
                if (in_array($project->organizationId, Organization::$withSurvey)) {
                    if (!empty($project->groupId) && $project->group) {
                        $member = $project->group->getMember($this->sessionUser);
                        if ($member) {
                            $isAdmin = $member->isAdmin;
                        }
                    } else if (!empty($project->organizationId) && $project->organization) {
                        $isAdmin = $project->organization->isAdmin($this->sessionUser);
                    }
                    if (!$isAdmin && !$project->isFinished()) {
                        if (in_array($project->organizationId,
                            $config->organization->customSurvey->toArray())
                        ) {
                            $survey = SurveyGlobalStudentEmbassy::getByProjectAndUser($project,$this->sessionUser);
                        } else {
                            $survey = Survey::getByProjectAndUser($project,$this->sessionUser);
                        }
                        if (is_null($survey)) {

                            $url = "/signup/editsurvey?ProjectId=".$project->id;
                            if (in_array($project->organizationId,
                                $config->organization->customSurvey->toArray())
                            ) {
                                $url = "/signup/customsurvey?ProjectId=".$project->id;
                            }
                            $this->view->missingSurveys[] = array(
                                'id'   => $project->id,
                                'name' => $project->name,
                                'url'  => $url
                            );
                        }
                    }
                }
            }
        }
    }
}
