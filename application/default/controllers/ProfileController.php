<?php

/**
 * ProfileController - The "profile" controller class
 *
 * @author
 * @version
 */

ini_set("post_max_size", "32M");
ini_set("upload_max_filesize", "32M");

require_once 'Zend/Controller/Action.php';
require_once 'Zend/Form.php';
require_once 'Zend/Form/Element/Text.php';
require_once 'Zend/Form/Element/Textarea.php';
require_once 'Zend/Form/Element/Password.php';
require_once 'Zend/Form/Element/Submit.php';
require_once 'Zend/Form/Element/Checkbox.php';
require_once 'Zend/Form/Element/Hidden.php';
require_once 'Zend/Form/Element/Select.php';
require_once 'Zend/Form/Element/File.php';
require_once 'Zend/Form/Element/Button.php';
require_once 'Zend/Auth.php';
require_once 'Zend/Debug.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Zend/Validate/EmailAddress.php';
require_once 'Brigade/Lib/Validate/DbUnique.php';
require_once 'Brigade/Util/ImageResize.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/FundraisingCampaign.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/Photo.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/VolunteerFundraisingMessage.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/SiteActivityComments.php';
require_once 'Brigade/Db/Table/Survey.php';
require_once 'Brigade/Db/Table/GroupSurveys.php';
require_once 'Brigade/Util/ImageCrop.php';
require_once 'Brigade/Db/Table/Events.php';
require_once 'Brigade/Util/FBConnect.php';
require_once 'Mailer.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Zend/Paginator.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/FundraisingCampaign.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/PaypalAccounts.php';
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';
require_once 'Brigade/Db/Table/FundraisingSuggestedDonations.php';
require_once 'Brigade/Db/Table/Countries.php';
require_once 'Brigade/Db/Table/EventTickets.php';
require_once 'Facebook/facebook.php';
require_once 'BaseController.php';

require_once 'BluePay/BluePayment.php';
require_once 'Project.php';
require_once 'Group.php';
require_once 'User.php';
require_once 'Infusionsoft.php';
require_once 'Salesforce.php';

class ProfileController extends BaseController {

    public function preDispatch() {
        parent::preDispatch();
        $this->view->public = '/public/';
    }

    public function indexAction() {
        // get user info
        $parameters = $this->_getAllParams();
        $user       = User::get($parameters['UserId']);
        if ($user && $user->isDeleted) {
            $this->_helper->redirector('error', '');
        }

        $session = new Zend_Session_Namespace('profile_video');
        if(!empty($session->showProfileVideo)) {
            $this->view->showProfileVideo = true;
            $session->showProfileVideo = false;
        }

        $project = Project::getFeaturedUserInitiative($user->id);

        $this->view->headTitle(stripslashes($user->fullName));

        if(!empty($project->id)) {
            $this->view->showOpenGraphProfileMeta = true;
            $this->view->project                  = $project;
            $volunteer                            = $project->getVolunteerByUser($user);
            $this->view->userProjectRaised        = $volunteer->raised;
            $this->view->userProjectGoal          = $volunteer->userDonationGoal;

            $this->view->rightbarHelper($project, $volunteer);

            if(!empty($project->groupId)) {
                $this->view->group = $project->group;
                if(!empty($group->programId)) {
                    $this->view->program = $group->program;
                }
            }
            if(!empty($project->organizationId)) {
                $this->view->organization = $project->organization;
            }
            $this->view->project->user_message = $project->getMessageUser($user);

            $this->view->urlShare = "";
            if (!empty($project->groupId)) {
                $member = $project->group->getMember($user);
                if ($member) {
                     $this->view->urlShare = "member/" . $member->id;
                }
            }
            if ($this->view->urlShare == "") {
                $this->view->urlShare = $user->urlName;
            }
        }

        //for progress bar
        $this->view->toolsUsage = 15;
        if(count($user->initiatives)) {
          $this->view->toolsUsage += 25;
        }
        if(count($user->affiliationsOrganization)) {
          $this->view->toolsUsage += 40;
        }
        $image = @imagecreatefromstring($user->profileImage);
        if (file_exists(realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".$user->urlName."-logo.jpg")) {
          $this->view->toolsUsage += 10;
          $this->view->hasProfilePic = true;
        } else if ($image) {
          $this->view->toolsUsage += 10;
          $this->view->hasProfilePic = true;
        } else {
          $this->view->hasProfilePic = false;
        }
        if(!empty($user->faceBookId)) {
            $this->view->toolsUsage += 10;
        }

        $this->view->count = Project::countByUser($user, 'upcoming');
        if ($this->view->count > 0) {
            $this->view->initiatives = Project::getListByUser(
                $user,
                'upcoming',
                null,
                Project::ajaxLimit,
                1
            );
        } else {
            $this->view->initiatives = array();
        }
        $this->view->user         = $user;
        $this->view->page         = 1;
        $this->view->activityFeed = $user->activityFeed_5;
        $this->view->currentTab   = 'home';
        $this->renderPlaceHolders();
    }

    public function contactuserAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters =  $this->_getAllParams();

        $userTo   = User::get($parameters['UserId']);
        $userFrom = User::get($_SESSION['UserId']);
        try{
            Mailer::sendMessageToAdminGroup($userFrom, $userTo, $parameters['message']);
            echo json_encode(array('ok' => true));
        } catch(Exception $e) {
            echo json_encode(array('err' => true));
        }
    }

    public function editmessageAction() {
        $parameters =  $this->_getAllParams();

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if (isset($parameters['UserId']) &&
            isset($parameters['message'])) {
            $user = User::get($parameters['UserId']);
            if (isset($parameters['ProjectId'])) {
                $project = Project::get($parameters['ProjectId']);
            } else {
                $project = Project::getFeaturedUserInitiative($user->id);
            }
            $project->updateMessageUser($user, $parameters['message']);

            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('err' => true));
        }
    }

    public function livefeedAction() {
        $this->_helper->layout()->disableLayout();

        $parameters =  $this->_getAllParams();
        $user       =  User::get($parameters['UserId']);

        $this->view->activityFeed = $user->activityFeed;
        $this->view->user         = $user;
        $this->view->ajaxCall     = true;

        $this->render('index-live-feed');
    }

    public function initiativesAction() {
        // get user info
        $parameters = $this->_getAllParams();
        $user       = User::get($parameters['UserId']);
        if ($user && $user->isDeleted) {
            $this->_helper->redirector('error', '');
        }

        $this->view->headTitle(stripslashes($user->fullName).' | Initiatives');

        //initiatives
        $this->view->initiatives = Project::getListByUser($user, 'upcoming', null, 5);
        $this->view->pStatus     = 'upcoming';
        if(!$this->view->initiatives) {
            $this->view->pStatus     = 'completed';
            $this->view->initiatives = Project::getListByUser($user, 'completed', null, 5);
        }
        $this->view->user = $user;

        if(isset($parameters['ProjectId'])) {
            $project = Project::get($parameters['ProjectId']);
        } else if(count($user->initiatives)) {
            $project = $this->view->initiatives[0];
        }

        if(!empty($project->id)) {
            $this->view->project = $project;

            $volunteer                     = $project->getVolunteerByUser($user);
            $this->view->userProjectRaised = $volunteer->raised;
            $this->view->userProjectGoal   = $volunteer->userDonationGoal;

            $this->view->rightbarHelper($project, $volunteer);

            if(!empty($project->groupId)) {
                $group = $this->view->group = $project->group;
                if(!empty($group->programId)) {
                    $program = $this->view->program = $group->program;
                }
                $organization = $this->view->organization = $project->organization;
            } else if(!empty($project->organizationId)) {
                $organization = $this->view->organization = $project->organization;
            }

            $FundraisingMessage = new Brigade_Db_Table_VolunteerFundraisingMessage();
            $this->view->project->user_message = $FundraisingMessage->getFundraisingMessage($project->id, $user->id);

            $this->view->donations = Donation::getListByUserAndProject($user, $project);
        }

        //for progress bar
        $this->view->toolsUsage = 15;
        if(count($user->initiatives)) {
          $this->view->toolsUsage += 25;
        }
        if(count($user->affiliationsOrganization)) {
          $this->view->toolsUsage += 40;
        }
        $image = @imagecreatefromstring($user->profileImage);
        if (file_exists(realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".$user->urlName."-logo.jpg")) {
          $this->view->toolsUsage += 20;
          $this->view->hasProfilePic = true;
        } else if ($image) {
          $this->view->toolsUsage += 20;
          $this->view->hasProfilePic = true;
        } else {
          $this->view->hasProfilePic = false;
        }
        $config  = Zend_Registry::get('configuration');
        if ((in_array($project->organizationId, $config->organization->customSurvey->toArray()) ||
            in_array($project->organizationId, Organization::$withSurvey)) &&
            isset($this->sessionUser) && $this->sessionUser->id == $user->id &&
            !is_null($project->getVolunteerByUser($this->sessionUser))
        ) {
            if (in_array($project->organizationId, $config->organization->customSurvey->toArray())) {
                $urlSurvey = "/signup/customsurvey?ProjectId={$project->id}";
            } else {
                $urlSurvey = "/signup/editsurvey?ProjectId={$project->id}";
            }
            $this->view->surveyLink = $urlSurvey;
        }
        $this->view->currentTab = 'initiatives';
        $this->renderPlaceHolders();
    }

    /**
     * Ajax action to filter initiatives.
     * Used for photos and index page
     */
    public function filterinitiativesAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout()->disableLayout();

        if (isset($parameters['projectId'])) {
            $this->view->project = Project::get($parameters['projectId']);
        }
        $user                = User::get($parameters['UserId']);
        $this->view->user    = $user;
        $this->view->page    = (!isset($parameters['page'])) ? 1 : $parameters['page'];
        $this->view->filter  = true;
        $this->view->ajaxLim = Project::ajaxLimit;
        $this->view->count   = Project::countByUser(
            $user,
            $parameters['status'],
            (($parameters['type'] == 'all') ? null : $parameters['type'])
        );
        if ($this->view->count > 0) {
            $this->view->initiatives = Project::getListByUser(
                $user,
                $parameters['status'],
                (($parameters['type'] == 'all') ? null : $parameters['type']),
                Project::ajaxLimit,
                $this->view->page
            );
        } else {
            $this->view->initiatives = array();
        }
        if (isset($parameters['view'])) {
            $this->render('filter-index-initiatives');
        } else {
            $this->render('filter-initiatives');
        }
    }


    public function affiliationsAction() {
        // get user info
        $parameters   = $this->_getAllParams();
        $user         = User::get($parameters['UserId']);
        if ($user && $user->isDeleted) {
            $this->getResponse()->setHttpResponseCode(404);
        }

        $GroupMembers = new Brigade_Db_Table_GroupMembers();

        $this->view->headTitle(stripslashes($user->fullName).' | Affiliations');

        $affiliationsOrganization = $user->affiliationsOrganization;
        $affiliationsGroup        = $user->affiliationsGroup;

        if(isset($parameters['GroupId'])) {
            $group        = Group::get($parameters['GroupId']);
            $organization = $group->organization;
        } elseif (isset($parameters['ProgramId'])) {
            //to build out later
        } elseif(isset($parameters['NetworkId'])) {
            $organization = Organization::get($parameters['NetworkId']);
        } elseif(count($affiliationsOrganization) > 0) {
            $organization = Organization::get($affiliationsOrganization[0]->id);
        } elseif(count($affiliationsGroup) > 0) {
            $group        = Group::get($affiliationsGroup[0]->id);
            $organization = $group->organization;
        }

        if (count($affiliationsOrganization) == 0 && count($affiliationsGroup) == 0) {
            //error 404
            throw new Zend_Controller_Action_Exception('No affiliations for user', 404);
        }

        // check if user is a member of this organization to display the button "become a member"
        $this->view->is_member = false;
        if ($this->view->isLoggedIn) {
            if (isset($group) && $GroupMembers->isMemberExists($group->id, $_SESSION['UserId'], 'group')) {
                $this->view->is_member = true;
            } else if ($GroupMembers->isMemberExists($organization->id, $_SESSION['UserId'], 'organization')) {
                $this->view->is_member = true;
            }
            if (!$this->view->is_member) {
                if(isset($group)) {
                  $this->view->joinlink = 'javascript:joinGroup(\''.$group->id.'\', \''.$_SESSION['UserId'].'\')';
                } else {
                  $this->view->joinlink = 'javascript:joinOrganization(\''.$organization->id.'\', \''.$_SESSION['UserId'].'\')';
                }
            } else {
                $this->view->joinlink = '#';
            }
        } else {
            $this->view->joinlink = 'javascript:;" class="join';
        }

        //for progress bar
        $this->view->toolsUsage = 15;
        if(count($user->initiatives)) {
          $this->view->toolsUsage += 25;
        }
        if(count($user->affiliationsOrganization)) {
          $this->view->toolsUsage += 40;
        }
        $image = @imagecreatefromstring($user->profileImage);
        if (file_exists(realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".$user->urlName."-logo.jpg")) {
          $this->view->toolsUsage += 20;
          $this->view->hasProfilePic = true;
        } else if ($image) {
          $this->view->toolsUsage += 20;
          $this->view->hasProfilePic = true;
        } else {
          $this->view->hasProfilePic = false;
        }

        $this->view->user        = $user;
        $this->view->affiliation = isset($group) ? $group : $organization;
        $this->view->currentTab  = 'affiliations';
        $this->renderPlaceHolders();
    }

    public function photosAction() {
        // get user info
        $parameters = $this->_getAllParams();
        $user       = User::get($parameters['UserId']);
        if ($user && $user->isDeleted) {
            $this->_helper->redirector('error', '');
        }
        $this->view->headTitle(stripslashes($user->fullName).' | Photos');

        $this->view->count = Project::countByUser($user, 'upcoming', null);
        if ($this->view->count > 0) {
            $this->view->initiatives = Project::getListByUser($user, 'upcoming', null, Project::ajaxLimit);
        } else {
            $this->view->initiatives = array();
        }

        if(isset($parameters['ProjectId'])) {
            $this->view->project = Project::get($parameters['ProjectId']);
        } else  {
            if (count($this->view->initiatives) > 0) {
                $this->view->project = $this->view->initiatives[0];
            } else if (count($user->initiatives) > 0) {
                $this->view->project = $user->initiatives[0];
            }
        }

        //for progress bar
        $this->view->toolsUsage = 15;
        if(count($user->initiatives)) {
          $this->view->toolsUsage += 25;
        }
        if(count($user->affiliationsOrganization)) {
          $this->view->toolsUsage += 40;
        }
        $image = @imagecreatefromstring($user->profileImage);
        if (file_exists(realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".$user->urlName."-logo.jpg")) {
          $this->view->toolsUsage += 20;
          $this->view->hasProfilePic = true;
        } else if ($image) {
          $this->view->toolsUsage += 20;
          $this->view->hasProfilePic = true;
        } else {
          $this->view->hasProfilePic = false;
        }

        $this->view->ajaxLim    = Project::ajaxLimit;
        $this->view->user       = $user;
        $this->view->currentTab = 'photos';
        $this->renderPlaceHolders();
    }

    /**
     * Ajax action to filter albums.
     */
    public function filteralbumsAction() {
        $params = $this->_getAllParams();
        $this->_helper->layout()->disableLayout();

        $this->view->initiatives = array();
        if (!empty($params['projectId']) && !empty($params['UserId'])) {
            $project             = Project::get($params['projectId']);
            $user                = User::get($params['UserId']);
            $this->view->user    = $user;
            $this->view->page    = (!isset($params['page'])) ? 1 : $params['page'];
            $this->view->filter  = true;
            $this->view->project = $project;
            $this->view->ajaxLim = Project::ajaxLimit;
            $this->view->count   = Project::countByUser($user, 'upcoming', null);
            if ($this->view->count > 0) {
                $this->view->initiatives = Project::getListByUser(
                    $user,
                    'upcoming',
                    null,
                    Project::ajaxLimit,
                    $this->view->page
                );
            }
        }

        $this->render('filter-albums');
    }

    public function loadimageAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $user = User::get($_REQUEST['UserId']);
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Content-type: image/jpeg");
        $image = @imagecreatefromstring($user->profileImage);

        $pathToImage = realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".$user->urlName."-logo.jpg";
        if (file_exists($pathToImage)) {
            echo file_get_contents($pathToImage);
        } else if ($image) {
            echo  $user->profileImage;
        } else {
            echo file_get_contents(realpath(dirname(__FILE__) . '/../../../')."/public/images/Pictures/002.jpg");
        }
    }

    public function dologinAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $username = $_POST['login001'];
        $password = $_POST['pwd001'];
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : null;
        $remember = $_POST['remember'];
        $auth = Zend_Auth::getInstance();
        $authAdapter = new Brigade_Util_Auth();
        $authAdapter->setIdentity($username)->setCredential($password);
        $authResult = $auth->authenticate($authAdapter);
        if ($authResult->isValid()) {
            $userInfo = $authAdapter->_resultRow;
            //save userinfo in session
            $userInfo->Password = '';
            $_SESSION['errorLogin'] = false;
            $_SESSION['FullName']   = $userInfo->FirstName." ".$userInfo->LastName;
            $_SESSION['UserId']     = $userInfo->UserId;

            // if remember me has been checked save session
            if ($remember == 1) {
                $cookie_name = 'siteAuth';
                $cookie_time = time() + 3600*24*30; // 30 days
                setcookie($cookie_name, 'user='.$username.'&hash='.$password, $cookie_time, '/');
            }

            if (isset($redirect) && !empty($redirect)) {
                header('Location:' . $redirect);
            }

            if ($userInfo->FirstLogin == 1) {
                echo "first login";
            } else {
                echo 'success';
            }

        } else {
            $_SESSION['errorLogin'] = true;
            if (isset($redirect) && !empty($redirect)) {
                header('Location:' . $redirect);
            }
            $user = User::getByEmail($username);
            if ($user->password == $password && $user->isDeleted) {
                $str  = "The account was deactivated. To reactivate, please click ";
                $str .= "'Forgot Password' link.";
                echo $str;
            } else {
                echo "The username or password you entered is incorrect.";
            }
        }
    }

    public function donorlistAction() {
        if ($_POST) {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            if(empty($UserId) || !isset($this->view->userNew) || (isset($this->view->userNew) && $this->view->userNew->id != $UserId)) {
              $this->_helper->redirector('badaccess', 'error');
            }
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $donations = $ProjectDonations->getUserDonors($_POST['UserId']);
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=donor-list-report.xls");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $total_donation = 0;
            $columns = array('Volunteer Opportunity', 'Donor Name', 'Donor Email', "Donor's Comments", 'Donation Date', 'Donation Amount');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($donations as $donation) {
                $line = '';
                $total_donation += $donation['DonationAmount'];
                foreach($donation as $col =>  $value) {
                    if ((!isset($value)) || (trim($value) == "") || empty($value)) {
                        $donation[$col] = "\t";
                    } else {
                        $donation[$col] = str_replace('"', '""', $value);
                        $donation[$col] = '"' . $value . '"' . "\t";
                    }
                }
            extract($donation);
            if($TransactionSource == "Manual") {
                $DonationComments = "\t";
            }
            if($isAnonymous) {
                $SupporterName = "Anonymous";
                $SupoorterEmail = "Anonymous";
            }
                $line = "$VolunteerActivity$SupporterName$SupporterEmail$DonationComments$ModifiedOn$DonationAmount";
                $data .= trim($line)."\n";
            }
            $data .= trim("TOTAL\t\t\t\t\t".number_format($total_donation))."\n";
            $data = str_replace("\r","",$data);

            print "$headers\n$data";
        } else {
            $parameters = $this->_getAllParams();
            $Users = new Brigade_Db_Table_Users();
            $UserId = $parameters['UserId'];
            if(empty($UserId) || !isset($this->view->userNew) || (isset($this->view->userNew) && $this->view->userNew->id != $UserId)) {
              $this->_helper->redirector('badaccess', 'error');
            }
            $this->view->user = User::get($UserId);
            $ProjectId = isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL;
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $this->view->donor_list = $ProjectDonations->getUserDonors($UserId, $ProjectId);
            $this->view->ProjectId = $ProjectId;
            $this->view->activities_joined = $Users->getBrigadesJoined($UserId);

            $this->renderPlaceholders();
        }
    }

    public function resendactivationAction() {
        $http = $_SERVER['HTTP_HOST'];
        $Email = $_REQUEST['email'];
        $FirstName = $_REQUEST['firstname'];
        $UserId = $_REQUEST['UserId'];
        $activation_code = $_REQUEST['activation_code'];
        if($this->view->envUsername == 'admin') {
            $envSite = "www";
        } else {
            $envSite = $this->view->envUsername;
        }

        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$USER_REGISTERED,
                     array($Email, $FirstName, "http://$envSite.empowered.org/profile/activate/$UserId/$activation_code"));

        $this->view->email = $Email;
    }

    public function loginAction() {
        if (isset($_GET['url']) && !empty($_GET['url'])) {
            $_SESSION['url_redirect'] = $_GET['url'];
        }
        if ($this->_helper->authUser->isLoggedIn()) {
            if (isset($_GET['url']) && !empty($_GET['url'])) {
                header("location: ".$_GET['url']);
            } else {
                header("location: /".$this->view->userNew->urlName);
            }
        }

        $this->view->loginForm = $this->getLoginForm();
        if ($this->getRequest()->isPost()) {
            if ($this->view->loginForm->isValid($_POST)) {
                $email = $this->view->loginForm->getValue('email');
                $password = $this->view->loginForm->getValue('password');
                $auth = Zend_Auth::getInstance();
                if ($email != null) {
                    $authAdapter = new Brigade_Util_Auth();
                    $authAdapter->setIdentity($email)->setCredential($password);
                    $authResult = $auth->authenticate($authAdapter);
                    if ($authResult->isValid()) {
                        $userInfo = $authAdapter->_resultRow;
                        if ($userInfo->Active == 1) {
                        //save userinfo in session
                            $userInfo->Password = '';
                            // Zend_Registry::get('defSession')->currentUser = $userInfo;
                            $_SESSION['FullName'] = $userInfo->FirstName." ".$userInfo->LastName;
                            $_SESSION['UserId'] = $userInfo->UserId;

                            // if remember me has been checked save session
                            if (isset($_POST['remember'])) {
                                $cookie_name = 'siteAuth';
                                $cookie_time = time() + (3600 * 24 * 30); // 30 days
                                setcookie($cookie_name, 'user='.$email.'&hash='.$password, $cookie_time, '/');
                            }

                            //redirect to home
                            if ($userInfo->FirstLogin == 1) {
                                $this->_helper->redirector('edit', 'profile');
                            } else {
                                if ($_SESSION['url_redirect']) {
                                    $url = $_SESSION['url_redirect'];
                                    $_SESSION['url_redirect'] = null;
                                    header("location: ".$url);
                                } else {
                                    header("location: /".$this->view->userNew->urlName);
                                }
                            }
                        } else {
                            $activation_link = "/profile/resendactivation/?UserId=$userInfo->UserId&firstname=$userInfo->FirstName&email=$email&activation_code=$userInfo->activation_code";
                            $this->view->message = 'Your account is not yet activated, please click the activation link provided in your email or <a href="'.$activation_link.'">resend</a> the activation email.';
                            $this->view->loginError = true;
                            $auth->clearIdentity();
                        }
                    } else {
                        $this->view->message = 'The username or password you entered is incorrect.';
                        $this->view->loginError = true;
                    }
                }
                else {
                    $this->view->message = 'The username or password you entered is incorrect.';
                    $this->view->loginError = true;
                }
            }
        }
    }

    public function logoutAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $auth = Zend_Auth::getInstance();
        $auth->clearIdentity();
        Zend_Registry::get('defSession')->currentUser = null;
        unset($_SESSION['FullName']);
        unset($_SESSION['UserId']);

        if(isset($_SESSION['FacebookId'])) {
            //header("location: ".$this->view->logoutUrl);
            unset($_SESSION['FacebookId']);
        }
        //else {
            //$this->_helper->redirector('login', 'profile');
        //}
        if (isset($_COOKIE['siteAuth'])) {
            setcookie ("siteAuth", "", time() - (3600 * 24 * 30), '/');
        }
    }

    public function editAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $content="";
        $LookupTable = new Brigade_Db_Table_LookupTable();
        // get user info
        $identity = Zend_Auth::getInstance()->getIdentity();
        $Users = new Brigade_Db_Table_Users();
        $this->view->data = $Users->findBy($identity);
        if ($this->view->data['FirstLogin']) {
            $Users->edit($this->view->data['UserId'], array('FirstLogin' => 0));
        }
        if ($this->getRequest()->isPost()) {
            extract($_POST);
            $error = "";
            $validator = new Zend_Validate_EmailAddress();
            $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
            if (!$validator->isValid($email)) {
                $error .= 'Please specify a valid email address.<br>';
            } else if (!$unique_emailvalidator->isValid($email) && Zend_Auth::getInstance()->getIdentity() != $email) {
                $error .= "Email address $email already exists.<br>";
            }

            if($URLName == "") {
                $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), trim($FirstName)."-".trim($LastName));
                // replace other special chars with accents
                $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

                $Taken = $LookupTable->isSiteNameExists($URLName);
                $counter = 1;
                while($Taken) {
                    $NewURLName = "$URLName-$counter";
                    $counter++;
                    $Taken = $LookupTable->isSiteNameExists($NewURLName);
                }
                if($counter > 1) {
                    $URLName = $NewURLName;
                }
            } else if ($URLName == $this->view->data['URLName']) {

            } else {
                $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $URLName);
                // replace other special chars with accents
                $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

                if ($LookupTable->isSiteNameExists($URLName)) {
                    $error .= 'URL name already exists, please specify another.<br>';
                }
            }

            $editFields = array(
                'FirstName' => $FirstName,
                'LastName' => $LastName,
                'AboutMe' => $passion,
                'Email' => $email,
                'Location' => $Location,
                'URLName' => $URLName,
                'FullName' => $FirstName." ".$LastName,
                'Gender'     => $Gender,
                'ModifiedOn' => date('Y-m-d H:i:s')
            );
            if ($error != '') {
                $this->view->error = 'error';
                $this->view->message = $error;
                if ($password != '') {
                    $editFields['Password'] = $password;
                }
                $editFields['UserId'] = $UserId;
                $this->view->data = $editFields;
            } else {
                $Users = new Brigade_Db_Table_Users();
                if ($password != '') {
                    $editFields['Password'] = $password;
                }
                $Users->edit($UserId, $editFields);

                // log the site activity
                $SiteActivities = new Brigade_Db_Table_SiteActivities();
                $SiteActivities->addSiteActivity(array(
                    'SiteId' => $_SESSION['UserId'],
                    'ActivityType' => 'Profile Updated',
                    'CreatedBy' => $_SESSION['UserId'],
                    'ActivityDate' => date('Y-m-d H:i:s'),
                ));

                // check if email was changed
                if ($this->view->data['Email'] != $email) {
                    echo "Email has been changed";
                    // clear the current user credentials and sessions
                    $auth = Zend_Auth::getInstance();
                    $auth->clearIdentity();

                    // authenticate the new user credentials
                    $authAdapter = new Brigade_Util_Auth();
                    $authAdapter->setIdentity($editFields['Email'])->setCredential(isset($editFields['Password']) ? $editFields['Password'] : $this->view->data['Password']);
                    $authResult = $auth->authenticate($authAdapter);
                    if ($authResult->isValid()) {
                        $userInfo = $authAdapter->_resultRow;
                        if ($userInfo->Active == 1) {
                        //save userinfo in session
                            $userInfo->Password = '';
                            // Zend_Registry::get('defSession')->currentUser = $userInfo;
                            $_SESSION['FullName'] = $userInfo->FirstName." ".$userInfo->LastName;
                            $_SESSION['UserId'] = $userInfo->UserId;

                            // if remember me has been checked save session
                            if (isset($_POST['remember'])) {
                                $cookie_name = 'siteAuth';
                                $cookie_time = (3600 * 24 * 30); // 30 days
                                setcookie($cookie_name, 'user='.$email.'&hash='.$password, $cookie_time, '/');
                            }

                            //redirect to home
                            header("location: /".$this->view->userNew->urlName);
                        }
                    }
                }
                if (isset($_SESSION['promptDetails'])) {
                    unset($_SESSION['promptDetails']);
                }
                // get URL name ID from lookup_table
                $LookupTable->updateSiteName($UserId, array('SiteName'=>$URLName));
                // get user info
                $email = Zend_Auth::getInstance()->getIdentity();
                $user_info = $Users->findBy($UserId);
                $this->view->data = $user_info;
                $this->view->error = 'success';
                $this->view->message = array('Your profile information has been successfully updated.');
                $fileSize = $_FILES['upload']['size'];
                $type = $_FILES['upload']['type'];
                if ($fileSize > 0 && $fileSize < 2097152) {
                        $ImageCrop = new Brigade_Util_ImageCrop();
                        $userfile_name = $_FILES['upload']['name'];
                        $userfile_tmp = $_FILES['upload']['tmp_name'];
                        $userfile_size = $_FILES['upload']['size'];
                        $filename = basename($_FILES['upload']['name']);
                        $file_ext = substr($filename, strrpos($filename, '.') + 1);
                        $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_".strtolower($UserId).".jpg";

                        //Everything is ok, so we can upload the image.
                        if ($error == '') {
                            if (isset($_FILES['upload']['name'])) {
                                move_uploaded_file($userfile_tmp, $temp_image_location);
                                $width = $ImageCrop->getWidth($temp_image_location);
                                $height = $ImageCrop->getHeight($temp_image_location);
                                //Scale the image if it is greater than the width set above
                                if ($width > 900) {
                                    $scale = 900/$width;
                                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale,$file_ext);
                                } else {
                                    $scale = 1;
                                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale, $file_ext);
                                }
                            }
                        }
                        $redirect = "editcropimage?UserId=$UserId";
                } else if ($fileSize > 2097152) {
                    $error_message = 'Please select image with file size not greater than 2MB';
                } else if ($type != 'image/jpeg' && $fileSize > 0 && $fileSize < 2097152) {
                    $error_message = 'Please upload .jpeg images only';
                }
                if (isset($error_message)) {
                    $this->view->error = 'error';
                    $this->view->message = $error_message;
                } else if(isset($redirect)) {
                    header("location: /profile/$redirect");
                } else {
                    header("location: /".$user_info['URLName']);
                }
            }
        }
    }

    public function editcropimageAction() {
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        if ($_POST) {
            extract($_POST);
            $ImageCrop = new Brigade_Util_ImageCrop();
            $Users = new Brigade_Db_Table_Users();
            $userInfo = $Users->findBy($UserId);
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_".strtolower($UserId).".jpg";
            $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".$userInfo['URLName']."-logo.jpg";
            $x = $_POST["x"];
            $y = $_POST["y"];
            $width = $_POST["w"];
            $height = $_POST["h"];
            $scale = 100/$width;
            // get the current selected box width & height
            $ImageCrop->resizeThumbnailImage($thumb_image_location, $temp_image_location, $width, $height, $x, $y, $scale, 'jpg');

            // delete the temp file
            if (file_exists($temp_image_location)) {
                unlink($temp_image_location);
            }
            $identity = Zend_Auth::getInstance()->getIdentity();
            $Users = new Brigade_Db_Table_Users();
            $Users->edit($_POST['UserId'], array('ModifiedOn' => date('Y-m-d H:i:s')));
            $userInfo = $Users->findBy($_POST['UserId']);
            header("location: /".$userInfo['URLName']);
        } else if (isset($parameters['UserId'])) {
            $this->view->UserId = $parameters['UserId'];
        }
    }

    public function uploadphotoAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $this->view->UserId = $parameters['UserId'];
        $this->view->form = $this->getUploadPhotoForm($parameters['UserId']);
        $this->view->action = "upload";
        if (isset($_FILES["upload"]) && $_POST['action'] == "upload_image") {
            //Get the file information
            $ImageCrop = new Brigade_Util_ImageCrop();
            $userfile_name = $_FILES['upload']['name'];
            $userfile_tmp = $_FILES['upload']['tmp_name'];
            $userfile_size = $_FILES['upload']['size'];
            $filename = basename($_FILES['upload']['name']);
            $file_ext = substr($filename, strrpos($filename, '.') + 1);
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_".strtolower($this->view->UserId).".jpg";

            // Check if file size does not exceed 2MB
            if ($_FILES['upload']['size'] > 0 && $_FILES['upload']['size'] < 2097152) {
                if (isset($_FILES['upload']['name'])) {
                    move_uploaded_file($userfile_tmp, $temp_image_location);
                    $width = $ImageCrop->getWidth($temp_image_location);
                    $height = $ImageCrop->getHeight($temp_image_location);
                    //Scale the image if it is greater than the width set above
                    if($width > 900) {
                        $scale = 900/$width;
                    } else {
                        $scale = 1;
                    }
                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale,$file_ext);
                }
                //Refresh the page to show the new uploaded image
                $this->view->action = "crop";
                $this->view->image = "/public/tmp/tmp_".strtolower($this->view->UserId).".jpg";
                $this->view->ext = $file_ext;
                $this->view->width = $width;
                $this->view->height = $height;
            } else {
                $this->view->error = "Please select image with file size not greater than 2MB";
            }
        }
        else if (isset($_POST['action']) && $_POST['action'] == "crop_image") {
            $ImageCrop = new Brigade_Util_ImageCrop();
            $Users = new Brigade_Db_Table_Users();
            $userInfo = $Users->findBy($_POST["UserId"]);
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/resized_pic.jpg";
            $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".$userInfo["URLName"]."-logo.jpg";
            $x = $_POST["x"];
            $y = $_POST["y"];
            $width = $_POST["w"];
            $height = $_POST["h"];
            // get the current selected box width & height
            $ImageCrop->resizeThumbnailImage($thumb_image_location, $temp_image_location, $width, $height, $x, $y, 1);
            // scale it to 100 x 100
            $source = imagecreatefromjpeg($thumb_image_location);
            $new_image = imagecreatetruecolor(100, 100);
            imagecopyresampled($new_image, $source, 0, 0, 0, 0, 100, 100, $ImageCrop->getWidth($thumb_image_location), $ImageCrop->getHeight($thumb_image_location));
            imagejpeg($new_image,$thumb_image_location,75);

            // delete the temp file
            if (file_exists($temp_image_location)) {
                unlink($temp_image_location);
            }
            $Users = new Brigade_Db_Table_Users();
            $Users->edit($parameters['UserId'], array('ProfileImage' => NULL));
            $this->view->message = "Profile image has been successfully updated.";
            header("location: /profile/edit");
        }
    }

    public function instantregisterAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        if ($_POST) {
            extract($_POST);
            $validator = new Zend_Validate_EmailAddress();
            $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
            $error_message = array();
            if ($firstname == '' || $firstname == 'First Name') {
                $error_message[] = 'Please specify your first name.';
            }
            if ($lastname == '' || $lastname == 'Last Name') {
                $error_message[] = 'Please specify your last name.';
            }
            if ($email == '' || $email == 'Email') {
                $error_message[] = 'Please specify your email address.';
            } else if (!$validator->isValid($email)) {
                $error_message[] = 'Please specify a valid email address.';
            } else if (!$unique_emailvalidator->isValid($email)) {
                $error_message[] = "Email address $email already exists.";
            }
            if ($password == '' || $password == 'Password') {
                $error_message[] = 'Please specify your password.';
            }
            if (count($error_message) < 1) {

                $URLName = $firstname.' '.$lastname;
                // replace  special chars with accents
                $special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ', " ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$");
                $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n', "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-");
                $URLName = str_replace($special_chars, $char_replacement, $URLName);

                $Taken = $LookupTable->isSiteNameExists($URLName);
                $counter = 1;
                while($Taken) {
                    $NewURLName = "$URLName-$counter";
                    $counter++;
                    $Taken = $LookupTable->isSiteNameExists($NewURLName);
                }
                if($counter > 1) {
                    $URLName = $NewURLName;
                }

                $newUser = array(
                    'FirstName' => $firstname,
                    'LastName' => $lastname,
                    'Password' => $password,
                    'Email' => $email,
                    'Active' => 0,
                    'FirstLogin' => 0,
                    'URLName' => $URLName
                );
                $Users = new Brigade_Db_Table_Users();
                $UserId = $Users->addUser($newUser, false);

                $LookupTable->addSiteURL(array(
                    'SiteName' => $URLName,
                    'SiteId' => $UserId,
                    'Controller' => 'profile',
                    'FieldId' => 'UserId',
                ));

                $auth = Zend_Auth::getInstance();
                $authAdapter = new Brigade_Util_Auth();
                $authAdapter->setIdentity($email)->setCredential($password);
                $authResult = $auth->authenticate($authAdapter);
                if ($authResult->isValid()) {
                    $userInfo = $authAdapter->_resultRow;
                    $user     = User::get($userInfo->UserId);

                    //save userinfo in session
                    $_SESSION['FullName'] = $userInfo->FirstName." ".$userInfo->LastName;
                    $_SESSION['UserId'] = $userInfo->UserId;
                    $_SESSION['promptDetails'] = true;

                    if($startC == 'Yes') {
                        if(!empty($programId)) {
                            $program = Program::get($programId);
                            $_SESSION['startingChapter'] = true;
                            $_SESSION['newUserParam'] = $program->urlName.'/create-group';
                        } else if(!empty($orgId)) {
                            $org = Organization::get($orgId);
                            $_SESSION['startingChapter'] = true;
                            $_SESSION['newUserParam'] = $org->urlName.'/create-group';
                        }
                    } else {
                        if (!empty($projectId)) {
                            $projectInfo = Project::get($projectId);
                            $_SESSION['newUserParam'] = $URLName.'/initiatives/'.$projectInfo->urlName;
                            $_SESSION['joiningInitiative'] = $projectInfo->urlName;
                        } if (!empty($groupId)) {
                            $group = Group::get($groupId);

                            $config = Zend_Registry::get('configuration');
                            if(empty($group->hasMembershipFee)) {
                                $group->addMember($user);

                                if($group->isOpen) {
                                    // log the site activity
                                    $activity              = new Activity();
                                    $activity->siteId      = $group->id;
                                    $activity->type        = 'Group Member Joined';
                                    $activity->createdById = $this->sessionUser->id;
                                    $activity->date        = date('Y-m-d H:i:s');
                                    $activity->save();

                                    if(count($group->upcomingInitiatives)) {
                                      $_SESSION['newUserParam'] = $group->urlName."/participate";
                                    } else {
                                      $_SESSION['newUserParam'] = $URLName.'/affiliations/'.$group->urlName;
                                    }
                                } else {
                                    // send notification message to group admin(s)
                                    $GroupMembers = new Brigade_Db_Table_GroupMembers();
                                    $groupAdmins  = $GroupMembers->getGroupAdmins($group->id);
                                    foreach($groupAdmins as $admin) {
                                        Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                            EventDispatcher::$GROUP_MEMBER_NOTIFICATION,
                                            array($admin['Email'], stripslashes($group->name), stripslashes($userInfo->FirstName." ".$userInfo->LastName), ""));
                                    }

                                    $_SESSION['awaitingAcceptance'] = $group->name;
                                }
                            } else if (
                                $config->chapter->membership->enable &&
                                !in_array($group->organizationId, $config->chapter->membership->settings->toArray()) &&
                                in_array($group->organizationId, $config->chapter->membership->active->toArray())
                            ) {
                                $_SESSION['newUserParam'] = $group->urlName."/membership";
                            }
                        } else if (!empty($orgId)) {
                            $org = Organization::get($orgId);
                            if($org->hasGroups) {
                                $_SESSION['newUserParam'] = $org->urlName.'/affiliate';
                            } else {
                                $org->addMember($user);

                                if(count($org->upcomingInitiatives)) {
                                  $_SESSION['newUserParam'] = $org->urlName.'/participate';
                                } else {
                                  $_SESSION['newUserParam'] = $URLName.'/affiliations/'.$org->urlName;
                                }
                            }
                        } else if(empty($projectId)) {
                            // @TODO: check if this is not neccessary
                            //$_SESSION['newUserParam'] = 'getstarted';
                        }
                    }
                }

                echo "success";
            } else {
                $errors = implode("<br>", $error_message);
                echo $errors;
            }
        }
    }

    public function emailloginAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();

        $auth = Zend_Auth::getInstance();
        $authAdapter = new Brigade_Util_Auth();
        $authAdapter->setIdentity($parameters['e'])->setCredential($parameters['p']);
        $authResult = $auth->authenticate($authAdapter);
        if ($authResult->isValid()) {
            $userInfo = $authAdapter->_resultRow;
            $_SESSION['FullName']      = $userInfo->FirstName." ".$userInfo->LastName;
            $_SESSION['UserId']        = $userInfo->UserId;
            $_SESSION['promptDetails'] = true;
            $_SESSION['tempPass']      = $parameters['p'];
            header("location: /profile/edit");
        } else {
            //something went wrong - tell them to email support@empowered.org
        }
    }

    public function signupstep2Action() {
        $this->view->isLoggedIn = $this->_helper->authUser->isLoggedIn();
        if ($this->view->isLoggedIn) {
            $this->view->passion  = $this->view->userNew->aboutMe;
            $this->view->location = $this->view->userNew->location;
            $this->view->gender   = $this->view->userNew->gender;
        }

        $this->view->showCreateOrg = false;
        if (isset($_GET['getstarted'])) {
            $this->view->showCreateOrg = true;
        }

        if ($_POST) {
            extract($_POST);
            $Taken = false;
            $this->view->passion = $passion;
            $this->view->gender = isset($gender) ? $gender : 0;
            $this->view->location = $location;
            $this->view->URLName = $URLName;
            $LookupTable = new Brigade_Db_Table_LookupTable();
            $URLName = !empty($_POST['URLName']) ? $_POST['URLName'] : stripslashes($_SESSION['FullName']);
            if (!empty($_POST['URLName'])) {
                $Taken = $LookupTable->isSiteNameExists($_POST['URLName']);
            }
            if (!$Taken) {
                if (trim($URLName) != "") {
                    // replace  special chars with accents
                    $special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ', " ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$");
                    $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n', "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-");
                    $URLName = str_replace($special_chars, $char_replacement, $URLName);

                    $Taken = $LookupTable->isSiteNameExists($URLName);
                    $counter = 1;
                    while($Taken) {
                        $NewURLName = "$URLName-$counter";
                        $counter++;
                        $Taken = $LookupTable->isSiteNameExists($NewURLName);
                    }
                    if($counter > 1) {
                        $URLName = $NewURLName;
                    }
                }

                $destination = realpath(dirname(__FILE__) . '/../../../');
                $fileSize = $_FILES['upload']['size'];
                $name = $_FILES['upload']['name'];
                $type = $_FILES['upload']['type'];
                if ($fileSize > 0 && $fileSize < 2097152) {
                        $ImageCrop = new Brigade_Util_ImageCrop();
                        $userfile_name = $_FILES['upload']['name'];
                        $userfile_tmp = $_FILES['upload']['tmp_name'];
                        $userfile_size = $_FILES['upload']['size'];
                        $filename = basename($_FILES['upload']['name']);
                        $file_ext = substr($filename, strrpos($filename, '.') + 1);
                        $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_".strtolower($URLName).".jpg";

                        //Everything is ok, so we can upload the image.
                        if (!isset($error)) {
                            if (isset($_FILES['upload']['name'])) {
                                move_uploaded_file($userfile_tmp, $temp_image_location);
                                $width = $ImageCrop->getWidth($temp_image_location);
                                $height = $ImageCrop->getHeight($temp_image_location);
                                //Scale the image if it is greater than the width set above
                                if ($width > 900) {
                                    $scale = 900/$width;
                                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale, $file_ext);
                                } else {
                                    $scale = 1;
                                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale, $file_ext);
                                }
                            }
                        }
                        $redirect = "/profile/signup-cropimage?user_id=".strtolower($URLName);
                } else if ($fileSize > 2097152) {
                    $error_message = 'Please select image with file size not greater than 2MB';
                } else if ($type != 'image/jpeg' && $fileSize > 0 && $fileSize < 2097152) {
                    $error_message = 'Please upload .jpeg images only';
                }
            } else {
                $error_message = 'URL Name already exists, please specify another';
                $this->view->URLName = "";
            }
            if (isset($error_message)) {
                $this->view->error_message = $error_message;
            } else {
                $this->view->userNew->aboutMe = $passion;
                $this->view->userNew->gender = isset($gender) ? $gender : 0;
                $this->view->userNew->location = $location;
                $this->view->userNew->save();
//                $this->view->userNew->urlName = $URLName;    check into proper url name handlings
//                $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $URLName);
                  // replace other special chars with accents
//                $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
//                $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
//                $URLName = str_replace($other_special_chars, $char_replacement, $URLName);
                if ($createOrg == 'yes') {
                    $this->_helper->redirector->gotoUrl('/getstarted/create-organization');
                }
                header("location: ".(isset($redirect) ? $redirect : "/profile/signup-step3"));
            }
        }
    }

    public function signupcropimageAction() {
        $this->view->user_id = $UserId = $_REQUEST['user_id'];
        if (isset($_POST["action"]) && $_POST["action"] == "crop_image") {
            $ImageCrop = new Brigade_Util_ImageCrop();
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$UserId.jpg";
            $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/$UserId.jpg";
            $_SESSION['imagelogo'] = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/$UserId.jpg";
            // get the current selected box width & height
            $x = $_POST["x"];
            $y = $_POST["y"];
            $width = $_POST["w"];
            $height = $_POST["h"];
            $scale = 100/$width;

            $ImageCrop->resizeThumbnailImage($thumb_image_location, $temp_image_location, $width, $height, $x, $y, $scale, 'jpg');

            // delete the temp file
            if (file_exists($temp_image_location)) {
                unlink($temp_image_location);
            }
            header("location: /profile/signup-step3");
        }
    }

    public function signupstep3Action() {
        require_once('Captcha/recaptchalib.php');
        $this->view->captcha = recaptcha_get_html(RECAPTCHA_PUBLIC_KEY);

        $session = new Zend_Session_Namespace('profile_video');
        $session->showProfileVideo = true;

        if ($_POST) {
            $resp = recaptcha_check_answer (RECAPTCHA_PRIVATE_KEY,
                $_SERVER["REMOTE_ADDR"],
                $_POST["recaptcha_challenge_field"],
                $_POST["recaptcha_response_field"]);

            if ($resp->is_valid) {

                if(!isset($_SESSION['tempPass']) && !empty($this->view->userNew->password) && !empty($this->view->userNew->firstName) && !empty($this->view->userNew->lastName)) {
                    $this->view->userNew->isActive = 1;
                    $this->view->userNew->save();

                } else if(
                        ((isset($_POST['password']) && !empty($_POST['password'])) || (!empty($this->view->userNew->password) && !isset($_SESSION['tempPass']))) &&
                        ((isset($_POST['firstname']) && !empty($_POST['firstname'])) || !empty($this->view->userNew->firstName)) &&
                        ((isset($_POST['lastname']) && !empty($_POST['lastname'])) || !empty($this->view->userNew->lastName))) {
                    $this->view->userNew->password  = isset($_POST['password']) ? $_POST['password'] : $this->view->userNew->password;
                    $this->view->userNew->firstName = isset($_POST['firstname']) ? $_POST['firstname'] : $this->view->userNew->firstName;
                    $this->view->userNew->lastName  = isset($_POST['lastname']) ? $_POST['lastname'] : $this->view->userNew->lastName;
                    $this->view->userNew->isActive  = 1;
                    $this->view->userNew->save();

                    unset($_SESSION['tempPass']);
                }

                if($this->view->userNew->isActive == 1) {
                    // save uploaded profile photo if any
                    if (isset($_SESSION['imagelogo'])) {
                        $UserId = $this->view->userNew->id;
                        $user = User::get($UserId);
                        $temp_image_location = $_SESSION['imagelogo'];
                        $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/images/users/". $user->urlName."-logo.jpg";

                        // scale it to 100 x 100
                        rename($temp_image_location, $thumb_image_location) or die ('error');
                        // delete temp file
                        unlink($temp_image_location);
                    }

                    // redirect to profile page
                    $newUserParam = '';
                    if(isset($_SESSION['newUserParam'])) {
                        $newUserParam = $_SESSION['newUserParam'];
                        unset($_SESSION['newUserParam']);
                    }

                    if(isset($_SESSION['joiningInitiative'])) {
                        $initLink = $_SESSION['joiningInitiative'];
                        unset($_SESSION['joiningInitiative']);
                        header("location: /$initLink/signup");
                    } else if(isset($_SESSION['startingChapter']) && $_SESSION['startingChapter'] == 'Yes') {
                        unset($_SESSION['startingChapter']);
                        header("location: /".$newUserParam);
                    } else if(!empty($newUserParam)) {
                        header("location: /".$newUserParam);
                    } else {
                        header("location: /".$this->view->userNew->urlName);
                    }


                } else {
                    $this->view->error_message = "You haven't entered your password and/or name.";
                }

            } else {
                $this->view->error_message = "The reCAPTCHA wasn't entered correctly, please try again.";
            }
        }
    }

    private function signupVolunteer($ProjectId, $UserId) {
        $Users = new Brigade_Db_Table_Users();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $ContactInfo = new Brigade_Db_Table_Contactinformation();
        $Mailer = new Mailer();
        $user = User::get($UserId);
        $project = Project::get($ProjectId);
        $accept_volunteer = $project->status == 'Close' ? 0 : 1;
        $VolunteerId = $Volunteers->signUpVolunteer($UserId, $ProjectId, $accept_volunteer);

        // send email notifications to the administrator
        if(!empty($project->groupId)) {
            $contactEmail = $ContactInfo->getContactInfo($project->groupId, 'Email');
        } else if (!empty($project->organizationId)) {
            $contactEmail = $ContactInfo->getContactInfo($project->organizationId, 'Email');
        } else {
            $contactEmail = $ContactInfo->getContactInfo($ProjectId, 'Email');
        }

        if (isset($contactEmail)) {
            if ($project->status == 'Close') {
                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$VOLUNTEER_REQUEST,
                                   array($user, $contactEmail, $project));

            } else {
                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$VOLUNTEER_ACCEPTED,
                                       array($user, $contactEmail, $project, isset($project->organizationId) && $project->organizationId == 'DAF7E701-4143-4636-B3A9-CB9469D44178'));
            }
        }
    }

    public function registerAction() {
        if ($this->_helper->authUser->isLoggedIn() && !isset($_POST['session'])) {
            header("location: /".$this->view->userNew->urlName);
        }

        $LookupTable = new Brigade_Db_Table_LookupTable();
        if ($this->getRequest()->isPost() && !isset($_POST['session'])) {
            if ($_POST) {
                extract($_POST);
                $validator = new Zend_Validate_EmailAddress();
                $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
                $error_message = array();
                if ($firstname == '') {
                    $error_message[] = 'Please specify your first name.';
                } else {
                    $this->view->firstname = trim($firstname);
                }
                if ($lastname == '') {
                    $error_message[] = 'Please specify your last name.';
                } else {
                    $this->view->lastname = trim($lastname);
                }
                if ($email == '') {
                    $error_message[] = 'Please specify your email address.';
                } else if (!$validator->isValid($email)) {
                    $error_message[] = 'Please specify a valid email address.';
                } else if (!$unique_emailvalidator->isValid($email)) {
                    $error_message[] = "Email address $email already exists.";
                } else {
                    $this->view->email = trim($email);
                }
                if ($password == '') {
                    $error_message[] = 'Please specify your password.';
                } else if ($confirmpassword != $password) {
                    $error_message[] = 'Your passwords did not match.';
                } else if (strlen($password) < 6) {
                    $error_message[] = 'Your password must be at least 6 characters.';
                } else {
                    $this->view->password = $password;
                }
                /* if (trim($month) == "" && trim($day) == "" && trim($year) == "") {
                    $error_message[] = 'Please specify your date of birth.';
                } else if ((trim($month) == "" || trim($day) == "" || trim($year) == "") || $day < 0 || $day > 31 || $month < 0 || $month > 12 || $year < 1900 || $year > 2010) {
                    $error_message[] = 'Please specify a valid birth date.';
                } else {
                    $this->view->month = $month;
                    $this->view->day = $day;
                    $this->view->year = $year;
                } */
                if($URLName == "") {
                    $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), trim($firstname)."-".trim($lastname));
                    // replace other special chars with accents
                    $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                    $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                    $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

                    $Taken = $LookupTable->isSiteNameExists($URLName);
                    $counter = 1;
                    while($Taken) {
                        $NewURLName = "$URLName-$counter";
                        $counter++;
                        $Taken = $LookupTable->isSiteNameExists($NewURLName);
                    }
                    if($counter > 1) {
                        $URLName = $NewURLName;
                    }
                } else {
                    $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $URLName);
                    if ($LookupTable->isSiteNameExists(str_replace(" ", "-", $URLName))) {
                        $error_message[] = 'URL name already exists, please specify another.<br>';
                    }
                }

                $this->view->passion  = $passion;
                $this->view->gender   = $gender;
                $this->view->location = $location;
                $this->view->URLName  = $URLName;

                $destination = realpath(dirname(__FILE__) . '/../../../');
                $fileSize = $_FILES['upload']['size'];
                $name = $_FILES['upload']['name'];
                $type = $_FILES['upload']['type'];
                if ($fileSize > 0 && $fileSize < 2097152) {
                    if ($type == 'image/jpeg') {
                        $ImageCrop = new Brigade_Util_ImageCrop();
                        $userfile_name = $_FILES['upload']['name'];
                        $userfile_tmp = $_FILES['upload']['tmp_name'];
                        $userfile_size = $_FILES['upload']['size'];
                        $filename = basename($_FILES['upload']['name']);
                        $file_ext = substr($filename, strrpos($filename, '.') + 1);
                        $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/resized_pic.jpg";

                        //Everything is ok, so we can upload the image.
                        if (!isset($error)) {
                            if (isset($_FILES['upload']['name'])) {
                                move_uploaded_file($userfile_tmp, $temp_image_location);
                                $width = $ImageCrop->getWidth($temp_image_location);
                                $height = $ImageCrop->getHeight($temp_image_location);
                                //Scale the image if it is greater than the width set above
                                if ($width > $ImageCrop->max_width) {
                                    $scale = $ImageCrop->max_width/$width;
                                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale);
                                } else {
                                    $scale = 1;
                                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale);
                                }
                            }
                        }
                        $redirect = "/profile/cropimage";
                    } else {
                        $error_message[] = 'Please upload .jpeg images only';
                    }
                } else if ($fileSize > 2097152) {
                    $error_message[] = 'Please select image with file size not greater than 2MB';
                } else if ($type != 'image/jpeg' && $fileSize > 0 && $fileSize < 2097152) {
                    $error_message[] = 'Please upload .jpeg images only';
                }

                if (count($error_message)  == 0) {
                    $newUser = array(
                        'FirstName' => trim($firstname),
                        'LastName' => trim($lastname),
                        'Password' => $password,
                        'Email' => trim($email),
                        'AboutMe' => $passion,
                        'DateOfBirth' => "", //date('Y-m-d', strtotime("$month/$day/$year")),
                        'Gender' => $gender,
                        'Location' => $location,
                        'Active' => 0,
                        'ProfileImage' => $content,
                        'URLName' => str_replace(" ", "-", $URLName)
                    );
                    $Users = new Brigade_Db_Table_Users();
                    $UserId = $Users->addUser($newUser, false);

                    // log the site activity
                    $SiteActivities = new Brigade_Db_Table_SiteActivities();
                    $SiteActivities->addSiteActivity(array(
                        'SiteId' => $UserId,
                        'ActivityType' => 'User Joined',
                        'CreatedBy' => $UserId,
                        'ActivityDate' => date('Y-m-d H:i:s'),
                        'Link' => "/".(!empty($URLName) ? $URLName : $firstname."-".$lastname),
                    ));

                    $_SESSION['email'] = $email;
                    if (isset($redirect)) {
                        header("location: $redirect/?UserId=$UserId");
                    } else {
                        $this->_helper->redirector('confirmregistration', 'profile');
                    }
                } else {
                    $this->view->error_message = $error_message;
                }
            }
        } else if (isset($_REQUEST['session'])) {
            $fbconnect = new FBConnect();
            $session = $fbconnect->getSession();
            if ($session) {
                $uid = $fbconnect->getUser();
                $param  =   array(
                    'method'  => 'users.getinfo',
                    'uids'    => $uid,
                    'fields'  => 'first_name,last_name,email,sex,current_location,hometown_location,pic_big,uid',
                    'callback'=> ''
                );
                $user = $fbconnect->api($param);
                /*
                echo "<pre>";
                print_r($user);
                echo "</pre>";
                 */
                $Users = new Brigade_Db_Table_Users();
                if (count($Users->findBy($user[0]['email'])) < 1) {
                    $SiteName = $user[0]['first_name']."-".$user[0]['last_name'];
            $SiteName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $SiteName);
                    // replace other special chars with accents
                    $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                    $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                    $SiteName = str_replace($other_special_chars, $char_replacement, $SiteName);

                    $result = $LookupTable->isSiteNameExists($SiteName);
                    $ctr = 1;
                    while($result) {
                        $SiteName1 = "$SiteName-$ctr";
                        $result = $LookupTable->isSiteNameExists($SiteName1);
                        $ctr++;
                    }
            if($ctr > 1) {
            $SiteName = $SiteName1;
            }

                    $newUser = array(
                        'FirstName' => $user[0]['first_name'],
                        'LastName' => $user[0]['last_name'],
                        'Email' => $user[0]['email'],
                        'Password' => "temp",
                        'AboutMe' => "",
                        'DateOfBirth' => date('Y-m-d', strtotime($user[0]['birthday'])),
                        'Gender' => $user[0]['sex'] == "male" ? 2 : $user[0]['sex'] == "female" ? 1 : 0,
                        'Location' => "", //$user[0]['current_location'],
            'FullName' => $user[0]['first_name']." ".$user[0]['last_name'],
                        'Active' => 1,
                        'FaceBookId' => $uid,
                        'URLName' => $SiteName
                    );
                    $UserId = $Users->addUser($newUser, false);

                    // log the site activity
                    $SiteActivities = new Brigade_Db_Table_SiteActivities();
                    $SiteActivities->addSiteActivity(array(
                        'SiteId' => $UserId,
                        'ActivityType' => 'User Joined',
                        'CreatedBy' => $UserId,
                        'ActivityDate' => date('Y-m-d H:i:s'),
                        'Link' => "/$SiteName",
                    ));

                    // save profile image
                    if (!empty($user[0]['pic_big'])) {
                        $ImageCrop = new Brigade_Util_ImageCrop();
                        $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/images/users/$SiteName-logo.jpg";
                        $temp_image_location = $user[0]['pic_big'];

                        $source = imagecreatefromjpeg($temp_image_location);
                        $new_image = imagecreatetruecolor(100, 100);
                        imagecopyresampled($new_image, $source, 0, 0, 0, 0, 100, 100, $ImageCrop->getWidth($temp_image_location), $ImageCrop->getHeight($temp_image_location));
                        imagejpeg($new_image,$thumb_image_location,75);
                    }

                    /*
                    echo "<pre>";
                    print_r($newUser);
                    echo "</pre>";
                     */

                    $auth = Zend_Auth::getInstance();
                    $authAdapter = new Brigade_Util_Auth();
                    $authAdapter->setIdentity($user[0]['email'])->setCredential("temp");
                    $authResult = $auth->authenticate($authAdapter);
                    if ($authResult->isValid()) {
                        $userInfo = $authAdapter->_resultRow;
                        if ($userInfo->Active == 1) {
                            //save userinfo in session
                            $userInfo->Password = '';
                            $_SESSION['FullName'] = $userInfo->FirstName." ".$userInfo->LastName;
                            $_SESSION['UserId'] = $userInfo->UserId;
                            $_SESSION['first_fb_login'] = true;
                        }
                    }
                    $this->_helper->redirector('edit', 'profile');
        } else {
            echo "An account already exists with this email address";
            $this->_helper->redirector('login', 'profile');
        }
            }
        }
    }

    public function cropimageAction() {
        $this->view->UserId = $_REQUEST["UserId"];
        if (isset($_POST["action"]) && $_POST["action"] == "crop_image") {
            $ImageCrop = new Brigade_Util_ImageCrop();
            $Users = new Brigade_Db_Table_Users();
            $userInfo = $Users->findBy($this->view->UserId);
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/resized_pic.jpg";
            $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".$userInfo["URLName"]."-logo.jpg";
            $x = $_POST["x"];
            $y = $_POST["y"];
            $width = $_POST["w"];
            $height = $_POST["h"];
            // get the current selected box width & height
            $ImageCrop->resizeThumbnailImage($thumb_image_location, $temp_image_location, $width, $height, $x, $y, 1);
            // scale it to 100 x 100
            $source = imagecreatefromjpeg($thumb_image_location);
            $new_image = imagecreatetruecolor(100, 100);
            imagecopyresampled($new_image, $source, 0, 0, 0, 0, 100, 100, $ImageCrop->getWidth($thumb_image_location), $ImageCrop->getHeight($thumb_image_location));
            imagejpeg($new_image,$thumb_image_location,75);

            // delete the temp file
            if (file_exists($temp_image_location)) {
                unlink($temp_image_location);
            }
            header("location: /profile/confirmregistration");
        }
    }

    public function activateAction() {
      // I believe this is deprecated and can be removed
        if ($this->_helper->authUser->isLoggedIn()) {
            header("location: /".$this->view->userNew->urlName);
        }

        $parameters = $this->_getAllParams();
        if (isset($parameters['userID']) && isset($parameters['activationCode'])) {
        // activate the user if the activation code is valid
            $Users = new Brigade_Db_Table_Users();
            if ($Users->activateUser($parameters['userID'], $parameters['activationCode'])) {
                $this->view->valid = true;
            } else {
                $this->view->valid = false;
            }
        }
    }

    public function deactivateAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters = $this->_getAllParams();

        if(!isset($this->sessionUser) || $this->sessionUser->id != $parameters['UserId']) {
            echo 'bad access';
        } else {
            $membershipList = $this->sessionUser->getMembership();
            if ($membershipList) {
                foreach($membershipList as $member) {
                    if ($member->payment) {
                        //send bluepay notification
                        $this->_stopMembershipPayments($member);
                    }
                    $member->stopMembership();
                    $this->salesforceMemberIntegration($member);
                }
            }
            $this->sessionUser->delete();
            $this->logoutAction();
            echo 'success';
        }

    }

    protected function salesforceMemberIntegration($member) {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($member->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Member::Profile');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($member->organization)) {
            $salesforce->updateMember($member);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$chapter->organizationId
            );
        }
    }


    /**
     * Delete user account
     */
    public function deleteAction() {
        if ($this->view->isLoggedIn && $this->view->isGlobAdmin) {
            $params = $this->_getAllParams();
            $user   = User::get($params['UserId']);
            if ($user->id != $this->view->userNew->id) {
                $user->delete();

                $this->_helper->layout()->disableLayout();
                $this->_helper->viewRenderer->setNoRender();

                echo "ok";
            }
        } else {
            $this->_helper->redirector('badaccess', 'error');
        }
    }

    public function confirmregistrationAction() {
        if ($this->_helper->authUser->isLoggedIn()) {
            header("location: /".$this->view->userNew->urlName);
        }

        $this->view->email = $_SESSION['email'];
        unset($_SESSION['email']);
    }

    public function editfundraisingAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $FundraisingMessage = new Brigade_Db_Table_VolunteerFundraisingMessage();
        if ($_POST) {
            $ProjectId = $_POST['ProjectId']; // ProjectId or FundraisingCampaignId
            $FundraisingMessageId = $_POST['FundraisingMessageId'];
            $Message = $_POST['FundraisingMessage'];
            $volunteerInfo = $Volunteers->loadInfo($_POST['VolunteerId']);
            if (!$volunteerInfo['hasEditMessage']) {
                $where = $Volunteers->getAdapter()->quoteInto('VolunteerId = ?', $_POST['VolunteerId']);
                $Volunteers->update(array('hasEditMessage' => 1), $where);
            }
            if (!empty($FundraisingMessageId)) {
                $msgInfo = $FundraisingMessage->loadInfo($FundraisingMessageId);
                $FundraisingMessage->updateFundraisingMessage($FundraisingMessageId, array('FundraisingMessage' => $Message));
                $this->view->message = "Your fund raising message for this brigade has been successfully updated.";
            } else {
                $FundraisingMessageId = $FundraisingMessage->addFundRaisingMessage(array(
                    'FundraisingMessage' => $Message,
                    'BrigadeId' => $ProjectId,
                    'VolunteerId' => $_SESSION['UserId'],
                ));
            }

            echo "success";
        } else {
            $this->view->data = $FundraisingMessage->loadInfo($parameters['FundraisingMessageId']);
        }
    }

    public function forgotpasswordAction() {
        if ($_POST) {
            $Email = $_POST['email'];
            $user = User::getByEmail($Email);

            if ($user) {
                if ($user->isDeleted) {
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(
                        EventDispatcher::$REACTIVATE_USER,
                        array(
                           $user->fullName,
                           $user->email,
                           $user->password,
                           $user->id
                        )
                    );
                } else {
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(
                        EventDispatcher::$FORGOT_PASSWORD,
                        array(
                           $Email,
                           $user->firstName,
                           $user->lastName,
                           $user->password
                        )
                    );
                }
                $this->view->message = "An email notification has been successfully sent, please check your email and click on the link to login.";
            } else {
                $this->view->error_message = "Email does not exist, kindly check if you entered the correct email.";
            }
        }
    }

    /**
     * Reactivate user profile from email link
     */
    public function reactivateuserAction() {
        $params = $this->_getAllParams();

        if (empty($params['i']) || empty($params['h'])) {
            $this->_helper->redirector('error', 'error');
        }
        $user = User::get($params['i']);
        if ($user) {
            if (sha1($user->password.'-reactivate-'.$user->email) == $params['h']) {
                Zend_Registry::get('logger')->info(__METHOD__.'::'.$user->email);
                $user->isDeleted = false;
                $user->save();
            }
        } else {
            $this->_helper->redirector('error', 'error');
        }
    }

    public function editdonationgoalAction() {
        try {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            extract($_POST);
            if (!empty($_POST['DonationGoal']) && isset($_POST['VolunteerId'])) {
                $Volunteers = new Brigade_Db_Table_Volunteers();
                $Volunteers->setDonationGoal($VolunteerId, $DonationGoal);
            } else if (!empty($_POST['DonationGoal']) && isset($_POST['FundraiserId'])) {
                $Volunteers = new Brigade_Db_Table_Volunteers();
                $Volunteers->setDonationGoal($FundraiserId, $DonationGoal);
            }
        }catch (Exception  $e ) {
            echo  "Error: ".$e->getMessage();
        }
    }

    public function addcommentAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (!empty($_POST['Comment'])) {
            $Comments = new Brigade_Db_Table_SiteActivityComments();
            $Users = new Brigade_Db_Table_Users();
            $time = date('Y-m-d H:i:s');
            $Comments->addSiteActivityComment(array(
                'SiteActivityId' => $_POST['SiteActivityId'],
                'Comment' => $_POST['Comment'],
                'CommentedBy' => $_SESSION['UserId'],
                'CommentedOn' => $time
            ));
            $userInfo = $Users->findBy($_SESSION['UserId']);
            echo '<li><table><tr><td style="width:34px;"><img src="/profile/loadimage?UserId='.$userInfo['UserId'].'" /></td><td style="width:316px;"><span class="comment"><a href="/'.$userInfo['URLName'].'">'.stripslashes($userInfo['FullName']).'</a>&nbsp;&nbsp;'.$_POST['Comment'].'<br><span class="time">'.$this->getDateFormat($time).'</span></span></td></tr></table></li>';
        }
    }

    public function addwallpostAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (!empty($_POST['Comment'])) {
            $SiteActivities = new Brigade_Db_Table_SiteActivities();
            $Users = new Brigade_Db_Table_Users();
            $userInfo = $Users->findBy($_SESSION['UserId']);
            $time = date('Y-m-d H:i:s');
            $activity_link = '/'.$userInfo['URLName'];
            $SiteActivityId = $SiteActivities->addSiteActivity(array(
                'SiteId' => '',
                'ActivityType' => 'Wall Post',
                'CreatedBy' => $_SESSION['UserId'],
                'ActivityDate' => $time,
                'Link' => $activity_link,
                'Details' => $_POST['Comment'],
                'Recipient' => $_POST['Recipient']
            ));
            $comments = "<ul id='ul_".$SiteActivityId."' style='display:none'></ul>";
            $avatar = "<img id='avatar_".$SiteActivityId."' src='/profile/loadimage?UserId=".$_SESSION['UserId']."' height='25' width='25' style='display:none; margin-right:3px; vertical-align:top;' />";
            $comment_box = '<div style="padding:3px; background-color:#e5e5e5; width:90%; margin-left:34px; margin-bottom:3px;">'.$avatar.'<textarea id="comment_'.$SiteActivityId.'" cols="50" rows="1" style="font-size:11px; height:15px; width:98%;" onfocus="commentfocus(this)" onblur="commentblur(this)">Write a comment...</textarea><input id="submit_'.$SiteActivityId.'" class="btn btngreen" style="display:none;" type="submit" value="Comment" onclick="commentpost(this)"/></div>';
            $display = "<p style='margin-bottom:-20px;'><table style='margin-bottom:-20px;'><tr><td width=34><img src='/profile/loadimage?UserId=".$userInfo['UserId']."' width='30' height='30'></td><td><a href='/".$userInfo['URLName']."'>".stripslashes($userInfo['FullName'])."</a>&nbsp;&nbsp;".$_POST['Comment']."<br>".$this->getDateFormat($time).".</td></tr></table>";
            echo "$display<br><br>$comments$comment_box<br></p><div class='clear1'></div>";
        }
    }

    private function getLoginForm() {
        $_elementDecorators = array(
            'ViewHelper',
            'Errors',
            array('Label'),
            // array(array('row' => 'HtmlTag'), array('class' => 'textfield')),
        );

        $_chkboxDecorators = array(
            'ViewHelper',
            array('Label', array('placement' => 'append')),
            array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'ppst06')),
        );

        $_buttonDecorators = array(
            'ViewHelper',
            // array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'button')),
        );

        $_hiddenElementDecorator = array(
            'ViewHelper',
            array('Label', array('tag' => 'a')),
            array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'ppst06')),
        );

        $form = new Zend_Form('login');
        $form->setAction('/profile/login')
            ->setMethod('post')
            ->setAttrib('id', 'login')
            ->setName('login')
            ->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'div', 'class' => 'form01')),
            'Form',
        ));
        ;

        $username = new Zend_Form_Element_Text('email', array(
            'label' => 'Email:',
            'decorators' => $_elementDecorators,
            'class' => 'textfield'
        ));
        $username->removeDecorator('Errors');

        $password = new Zend_Form_Element_Password('password', array(
            'label' => 'Password:',
            'decorators' => $_elementDecorators,
            'class' => 'textfield'
        ));
        $password->removeDecorator('Errors');

        $remember = new Zend_Form_Element_Checkbox('remember', array(
            'label' => 'Remember me?',
            'decorators' => $_chkboxDecorators,
            'class' => 'ppcheck'
        ));

        $submit = new Zend_Form_Element_Submit('login', array(
            'label' => 'Login',
            'decorators' => $_buttonDecorators,
            'class' => 'button'
        ));

        $form->addElements(array($username, $password, $remember, $submit));

        return $form;
    }

    public function getUploadPhotoForm($UserId) {
        $_elementDecorators = array(
            'ViewHelper',
            // array(array('row' => 'HtmlTag'), array('class' => 'textfield')),
        );

        $_buttonDecorators = array(
            'ViewHelper',
            // array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'button')),
        );

        $form = new Zend_Form('uploadphoto');
        $form->setAction('/profile/uploadphoto/'.$UserId)
            ->setMethod('post')
            ->setName('uploadphoto')
            ->setAttrib('enctype', 'multipart/form-data')
            ->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'div', 'class' => 'form03')),
            'Form',
        ));
        ;

        $back = new Zend_Form_Element_Button('back', array(
            'label' => 'Back',
            'decorators' => $_buttonDecorators,
            'class' => 'button',
            'onclick' => 'history.go(-1)'
        ));

        $upload = new Zend_Form_Element_File('upload', array(
            'class' => 'textfield',
            'style' => 'margin-left: -38px'
        ));
        $upload->addValidator('Extension', false, 'jpeg,jpg,png')
            ->removeDecorator('Errors');

        $next = new Zend_Form_Element_Submit('submit', array(
            'label' => 'Submit',
            'decorators' => $_buttonDecorators,
            'class' => 'button'
        ));

        $form->addElements(array($upload, $next));

        return $form;
    }

    public function getDateFormat($date) {
        $currentdate = time();
        $finaldate = $currentdate - strtotime($date);
        if($finaldate >= 0) {
            $days =  $finaldate/86400;
            $day = floor($days);
            $hours =  $finaldate%86400;
            $hours1 =  $hours/3600;
            $hours1 = floor($hours1);
            $min1 =  $hours%3600;
            $min =  $min1/60;
            $min = floor($min);
            $sec =  $min1%60;
            $counter = 0;
            $final = "";

            if($day > 0) {
                $counter++;
                $final = $day.($day > 1 ? " days " : " day");
            } else if(($hours1 > 0)||(($hours1 == 0)&&($final != ""))) {
                    $counter++;
                    $final = $hours1.($hours1 > 1 ? " hours" : " hour");
                } else if($counter < 2) {
                        $counter++;
                        $final = $min.($min > 1 ? " minutes" : " minute");
                    } else if($counter < 2) {
                            $counter++;
                            $final = $sec.($sec > 1 ? " seconds" : " second");
                        }
            $final.= " ago";
            $durationFORMAT =  $final;
        }

        return $durationFORMAT;
    }

    public function validateuseremailAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Users = new Brigade_Db_Table_Users();
        $email = $_REQUEST['email'];
        $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique($Users, 'email');
        if (!$unique_emailvalidator->isValid($email)) {
            echo 1;
        } else {
            echo 0;
        }
    }

    public function leaveactivityAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (isset($_POST['VolunteerId']) && $_POST['VolunteerId'] != "") {
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $Volunteers->removeVolunteer($_POST['VolunteerId'], 1);
        }
    }

    public function activatemailAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        if (isset($parameters['GroupId']) && isset($parameters['UserId']) && isset($parameters['ActivateEmail'])) {
            extract($parameters);
            $GroupMembers->ActivateEmail($GroupId, $UserId, $ActivateEmail);
        }
    }

    public function leavegroupAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        if (isset($parameters['GroupId']) && isset($parameters['UserId'])) {
            $GroupMembers->leaveGroup($parameters['GroupId'], $parameters['UserId']);
        }
    }

    public function emaildonorsAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $this->view->ProjectId = $parameters['ProjectId'];
        $Brigades = new Brigade_Db_Table_Brigades();
        $this->view->data = $Brigades->loadInfoBasic($parameters['ProjectId']);
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        if (isset($parameters['Prev'])) {
            $this->view->level = "user";
            $this->view->donors = $ProjectDonations->getProjectDonors($parameters['ProjectId']);
        } else {
            $this->view->donors = $ProjectDonations->getVolunteerDonors($_SESSION['UserId'], $parameters['ProjectId']);
        }
        if ($_POST) {
            extract($_POST);
            $userInfo = $Users->loadInfo($_SESSION['UserId']);
            foreach ($donors as $email) {
                if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
                    mail($email, $subject, stripslashes($message), "From: ".stripslashes($userInfo['FullName'])." <".stripslashes($userInfo['Email']).">");
                } else {
                    mail('empoweredqa@gmail.com', $subject, stripslashes($message), "From: ".stripslashes($userInfo['FullName'])." <".stripslashes($userInfo['Email']).">");
                }
            }
            $this->view->sent = true;
            $this->view->message = "Your message has been successfully sent.";
        }
    }

    public function cpAction(){}

    public function organizationAction(){}

    public function summaryAction(){}

    public function createcampaignAction() {
        $this->_helper->redirector('error', 'error');
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $this->view->data = $Users->loadInfo($_SESSION['UserId']);
        $PaypalAccounts = new Brigade_Db_Table_PaypalAccounts();
        $GoogleCheckoutAccounts = new Brigade_Db_Table_GoogleCheckoutAccounts();
        $last_rec = $GoogleCheckoutAccounts->getMaxCheckoutId();
        $this->view->responsehandler = $last_rec['GoogleCheckoutAccountId'] + 1;
        if ($_POST) {
            extract($_POST);
            if(isset($_POST['payment_method']) && $_POST['payment_method'] == 'Paypal') {
                $PaypalAccountId = $PaypalAccounts->addPaypalAccount(array(
                    'email' => trim($_POST['paypalEmail']),
                    'currencyCode' => trim($_POST['paypalCurrency']),
                ));
                $Currency = $_POST['paypalCurrency'] == 'USD' ? '$' : '&#163;';
                // update PaypalAccountId field in the users table
                $Users->edit($_SESSION['UserId'], array('PaypalAccountId' => $PaypalAccountId, 'Currency' => $Currency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
            } else if (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Google Checkout') {
                $GoogleCheckoutAccountId = $GoogleCheckoutAccounts->addGoogleCheckoutAccount(array(
                    'GoogleCheckoutAccountName' => $this->view->data['FullName'],
                    'GoogleMerchantId' => trim($_POST['MerchantID']),
                    'GoogleMerchantKey' => trim($_POST['MerchantKey']),
                    'Currency' => ($_POST['Currency'] == 'USD' ? '$' : '&#163;'),
                    'CurrencyType' => $_POST['Currency'],
                ));
                $Currency = $_POST['Currency'] == 'USD' ? '$' : '&#163;';
                $Users->edit($_SESSION['UserId'], array('GoogleCheckoutAccountId' => $GoogleCheckoutAccountId, 'Currency' => $Currency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                // create the responsehandler file
                $this->create_response_handler($this->view->responsehandler);
            }
            // create the URLName
            $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), trim($Name));
            // replace other special chars with accents
            $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
            $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
            $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

            $Taken = $LookupTable->isSiteNameExists($URLName);
            $counter = 1;
            while($Taken) {
                $NewURLName = "$URLName-$counter";
                $counter++;
                $Taken = $LookupTable->isSiteNameExists($NewURLName);
            }
            if($counter > 1) {
                $URLName = $NewURLName;
            }
            $Projects = new Brigade_Db_Table_Brigades();
            $newCampaign = array(
                'Name' => $Name,
                'Description' => $Description,
                'DonationGoal' => $DonationGoal,
                'isRecurring' => 0,
                'EndDate' => date('Y-m-d H:i:s', strtotime($EndDate)),
                'URLName' => $URLName,
                'Type' => 1,
                'PaypalAccountId' => isset($PaypalAccountId) ? $PaypalAccountId : 0,
                'GoogleCheckoutAccountId' => isset($GoogleCheckoutAccountId) ? $GoogleCheckoutAccountId : 0,
                'Currency' => $Currency,
                'UserId' => $_SESSION['UserId'],
                'PercentageFee' => isset($_POST['PercentageFee']) ? $_POST['PercentageFee'] : $this->view->data['PercentageFee'],
                'allowPercentageFee' => isset($_POST['allowPercentageFee']) ? $_POST['allowPercentageFee'] : $this->view->data['allowPercentageFee']
            );
            $ProjectId = $Projects->addProject($newCampaign);

            // signup the user as a volunteer of the newly created project
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $Volunteers->signUpVolunteer($_SESSION['UserId'], $ProjectId, 1);

            // add suggested amounts
            $SuggestedDonations = new Brigade_Db_Table_FundraisingSuggestedDonations();
            if(isset($suggestedamount)){
                for ($ctr = 0; $ctr < count($suggestedamount); $ctr++) {
                    $suggested_donations = array('ProjectId' => $ProjectId, 'Amount' => $suggestedamount[$ctr], 'Description' => $suggestedamountdesc[$ctr]);
                    $SuggestedDonations->addSuggestedDonation($suggested_donations);
                }
            }

            // add record on the lookup_table
            $LookupTable->addSiteURL(array(
                'SiteName' => $URLName,
                'SiteId' => $ProjectId,
                'Controller' => 'fundraisingcampaign',
                'FieldId' => 'ProjectId'
            ));

            // add default administrator for this group
            $UserRole = new Brigade_Db_Table_UserRoles();
            $UserRoleId = $UserRole->addUserRole(array(
                'UserId' => $_SESSION['UserId'],
                'RoleId' => 'ADMIN',
                'SiteId' => $ProjectId
            ));

            // log the site activity
            $SiteActivities = new Brigade_Db_Table_SiteActivities();
            $SiteActivities->addSiteActivity(array(
                'SiteId' => $ProjectId,
                'ActivityType' => 'Campaign Added',
                'CreatedBy' => $_SESSION['UserId'],
                'ActivityDate' => date('Y-m-d H:i:s'),
                'Link' => "/$URLName",
            ));

            $MediaSize = $_FILES['CampaignLogo']['size'];
            $filename = $_FILES['CampaignLogo']['name'];
            $tmpfile = $_FILES['CampaignLogo']['tmp_name'];

            if(!empty($_FILES['CampaignLogo']['name'])) {
                $filename = $_FILES['CampaignLogo']['name'];
                $file_ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
                if($file_ext != 'jpg' && $file_ext != 'jpeg' && $file_ext != 'JPEG' && $file_ext != 'JPG') {
                    $bad_ext = 1;
                    $this->view->message = "Please upload a logo in jpg format.";
                } else {
                    $bad_ext = 0;
                }
            }

            if ($MediaSize > 0 && $filename != "" && !$bad_ext) {
                //Get the file information
                $ImageCrop = new Brigade_Util_ImageCrop();
                $file_ext = substr($filename, strrpos($filename, '.') + 1);
                $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_".strtolower($ProjectId).".jpg";
                // Check if file size does not exceed 2MB
                move_uploaded_file($tmpfile, $temp_image_location);
                $width = $ImageCrop->getWidth($temp_image_location);
                $height = $ImageCrop->getHeight($temp_image_location);
                if ($width > $ImageCrop->max_width) {
                    $scale = $ImageCrop->max_width/$width;
                } else {
                    $scale = 1;
                }
                $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale, $file_ext);
                // save media
                $Media = new Brigade_Db_Table_Media();
                $MediaId = $Media->addMedia(array(
                    'MediaSize' => $MediaSize,
                    'SystemMediaName' => strtolower($ProjectId).".jpg",
                    'UploadedMediaName' => $filename,
                    'CreatedBy' => $_SESSION['UserId'],
                    'CreatedOn' => date('Y-m-d H:i:s'),
                    'ModifiedBy' => $_SESSION['UserId'],
                    'ModifiedOn' => date('Y-m-d H:i:s'),
                ));

                // save site media
                $SiteMedia = new Brigade_Db_Table_MediaSite();
                $SiteMedia->addMediaSite(array(
                    'MediaId' => $MediaId,
                    'SiteID' => $ProjectId
                ));
            }

            $project = Project::get($ProjectId);
            $user = User::get($_SESSION['UserId']);
            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                    EventDispatcher::$USER_CREATE_ACTION, array($user, $project, "Campaing"));

            if ($MediaSize > 0) {
                header("location: /fundraisingcampaign/cropimage/?ProjectId=$ProjectId&MediaId=$MediaId&from=create_page&newcampaign=getstarted");
            } else {
                $this->view->message = "Fundraising Campaign \"$Name\" has been created successfully.";
                header("location: /$URLName/add-fundraisers?newcampaign=getstarted");
            }
        }
    }

    public function createactivityAction() {
        $this->_helper->redirector('error', 'error');
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $this->view->data = $Users->loadInfo($_SESSION['UserId']);
        $PaypalAccounts = new Brigade_Db_Table_PaypalAccounts();
        $GoogleCheckoutAccounts = new Brigade_Db_Table_GoogleCheckoutAccounts();
        $last_rec = $GoogleCheckoutAccounts->getMaxCheckoutId();
        $this->view->responsehandler = $last_rec['GoogleCheckoutAccountId'] + 1;
        $Countries = new Brigade_Db_Table_Countries();
        $this->view->country_list = $Countries->getAllCountries();

        if ($_POST) {
            extract($_POST);
            if(isset($_POST['payment_method']) && $_POST['payment_method'] == 'Paypal') {
                $PaypalAccountId = $PaypalAccounts->addPaypalAccount(array(
                    'email' => trim($_POST['paypalEmail']),
                    'currencyCode' => trim($_POST['paypalCurrency']),
                ));
                $Currency = $_POST['paypalCurrency'] == 'USD' ? '$' : '&#163;';
                // update PaypalAccountId field in the users table
                $Users->edit($_SESSION['UserId'], array('PaypalAccountId' => $PaypalAccountId, 'Currency' => $Currency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => isset($_POST['allowPercentageFee']) ? $_POST['allowPercentageFee'] : 'optional'));
            }
            $Name = trim($Name);
            $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $Name);
            // replace other special chars with accents
            $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
            $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
            $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

            $Taken = $LookupTable->isSiteNameExists($URLName);
            $counter = 1;
            while($Taken) {
                $NewURLName = "$URLName-$counter";
                $counter++;
                $Taken = $LookupTable->isSiteNameExists($NewURLName);
            }
            if($counter > 1) {
                $URLName = $NewURLName;
            }
            $bad_ext = 0;
            if(!empty($_FILES['ProjectLogo']['name'])) {
                $filename = $_FILES['ProjectLogo']['name'];
                $file_ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
                if($file_ext != 'jpg' && $file_ext != 'jpeg' && $file_ext != 'png' && $file_ext != 'gif') {
                    $bad_ext = 1;
                    $this->view->message = "Please upload a logo in jpeg, png and gif format only.";
                } else {
                    $bad_ext = 0;
                }
            }
            if ((isset($_FILES['ProjectLogo']) && $_FILES['ProjectLogo']['size'] <= 2097152) && !$bad_ext) {
                $StartTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['StartTime']);
                $StartDate = trim($_POST['StartDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['StartDate']." ".$StartTime)) : "0000-00-00 00:00:00";
                $EndTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
                $EndDate = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";
                $Status = $_POST['Status'] == 'Open' ? 'Open' : 'Close';
                // save project info first
                $Brigades = new Brigade_Db_Table_Brigades();
                $ProjectId = $Brigades->addProject(array(
                    'Name' => $Name,
                    'Description' => $Description,
                    'StartDate' => $StartDate,
                    'EndDate' => $_POST['with_end_date'] == 1 ? $EndDate : "",
                    'VolunteerGoal' => $VolunteerGoal,
                    'DonationGoal' => $DonationGoal,
                    'VolunteerMinimumGoal' => $VolunteerMinimumGoal,
                    'Status' => $Status,
                    'isFundraising' => $isFundraising,
                    'URLName' => $URLName,
                    'PaypalAccountId' => isset($PaypalAccountId) ? $PaypalAccountId : 0,
                    'GoogleCheckoutAccountId' => isset($GoogleCheckoutAccountId) ? $GoogleCheckoutAccountId : 0,
                    'Currency' => $Currency,
                    'UserId' => $_SESSION['UserId'],
                    'PercentageFee' => isset($_POST['PercentageFee']) ? $_POST['PercentageFee'] : $this->view->data['PercentageFee'],
                    'allowPercentageFee' => isset($_POST['allowPercentageFee']) ? $_POST['allowPercentageFee'] : $this->view->data['allowPercentageFee']
                ));

                // signup the user as a volunteer of the newly created project
                $Volunteers = new Brigade_Db_Table_Volunteers();
                $Volunteers->signUpVolunteer($_SESSION['UserId'], $ProjectId, 1);

                // add record on the lookup_table
                $LookupTable->addSiteURL(array(
                    'SiteName' => $URLName,
                    'SiteId' => $ProjectId,
                    'Controller' => 'project',
                    'FieldId' => 'ProjectId'
                ));

                // add default administrator for this group
                $UserRole = new Brigade_Db_Table_UserRoles();
                $UserRoleId = $UserRole->addUserRole(array(
                    'UserId' => $_SESSION['UserId'],
                    'RoleId' => 'ADMIN',
                    'SiteId' => $ProjectId
                ));

                // save project contact info
                $ContactInfo = new Brigade_Db_Table_ContactInformation();
                $ContactId = $ContactInfo->addContactInfo(array(
                    'Street' => trim($Location),
                    'CityId' => $CityId,
                    'City' => $City,
                    'RegionId' => $RegionId,
                    'Region' => $Region,
                    'CountryId' => $CountryId,
                    'Country' => $Country,
                    'SiteId' => $ProjectId
                ));

                // save project media/image
                $MediaSize = $_FILES['ProjectLogo']['size'];
                $tmpfile = $_FILES['ProjectLogo']['tmp_name'];
                if ($MediaSize > 0 && $filename != "") {
                    //Get the file information
                    $ImageCrop = new Brigade_Util_ImageCrop();
                    $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$ProjectId.jpg";

                    move_uploaded_file($tmpfile, $temp_image_location);
                    $width = $ImageCrop->getWidth($temp_image_location);
                    $height = $ImageCrop->getHeight($temp_image_location);
                    if ($width > 900) {
                        $scale = 900/$width;
                    } else {
                        $scale = 1;
                    }
                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale,$file_ext);

                    // save media
                    $Media = new Brigade_Db_Table_Media();
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => strtolower($ProjectId).".jpg",
                        'UploadedMediaName' => $filename,
                        'CreatedBy' => $_SESSION['UserId'],
                        'CreatedOn' => date('Y-m-d H:i:s'),
                        'ModifiedBy' => $_SESSION['UserId'],
                        'ModifiedOn' => date('Y-m-d H:i:s'),
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $ProjectId
                    ));

                }

                // log the site activity
                $SiteActivities = new Brigade_Db_Table_SiteActivities();
                $SiteActivities->addSiteActivity(array(
                    'SiteId' => $ProjectId,
                    'ActivityType' => 'Brigade Added',
                    'CreatedBy' => $_SESSION['UserId'],
                    'ActivityDate' => date('Y-m-d H:i:s'),
                    'Link' => "/$URLName",
                    'Details' => $Name
                ));

                $project = Project::get($ProjectId);
                $user = User::get($_SESSION['UserId']);
                Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                    EventDispatcher::$USER_CREATE_ACTION, array($user, $project, "Project"));

                if ($MediaSize > 0) {
                    header("location: /project/cropimage/?ProjectId=$ProjectId&MediaId=$MediaId&newactivity=getstarted");
                } else {
                    $this->view->message = "Volunteer Opportunity \"$Name\" has been created successfully.";
                    header("location: /$URLName/add-volunteers?newactivity=getstarted");
                }
            } else {
                foreach($_POST as $key=>$val) {
                    $this->view->$key = $val;
                }
                $message = "";
                if (isset($_FILES['ProjectLogo']) && $_FILES['ProjectLogo']['size'] > 2097152) {
                    $message .= "Please select an image not greater than 2MB.<br>";
                }
                if (isset($_FILES['ProjectLogo']) && $_FILES['ProjectLogo']['size'] == 0) {
                    $message .= "Please select an image.<br>";
                }
                if ($url_exists) {
                    $message .= "URL name already exists, please try another.";
                }
                $this->view->message = $message;
            }
        }
    }

    private function create_response_handler($checkoutID) {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        // create the responsehandler file
        $controller_path = realpath(dirname(__FILE__));
        $templates_path = realpath(dirname(__FILE__).'/../../../')."/configs/templates";
        $responsehandler = $controller_path."/Responsehandler".$checkoutID."Controller.php";
        $template = $templates_path."/ResponsehandlerControllerTemplate.txt";

        // open template file and replace [checkoutID] with the corresponding GoogleCheckoutId
        $handle = fopen($template, "rb");
        $contents = '';
        while (!feof($handle)) {
            $contents .= fread($handle, 8192);
            $contents = str_replace("[checkoutID]", $checkoutID, $contents)."\n";
        }

        $fp = fopen($responsehandler, 'w');
        if ($fp) {
            fwrite($fp, $contents);
        }
    }

    /**
     * Duplicated calls but not exactly the same in Event Controller create.
     * @TODO: check to use only one controller.
     */
    public function createeventAction() {
        $Tickets = new Brigade_Db_Table_EventTickets();
        $PaypalAccounts = new Brigade_Db_Table_PaypalAccounts();
        $GoogleCheckoutAccounts = new Brigade_Db_Table_GoogleCheckoutAccounts();

        $user = User::get($_SESSION['UserId']);
        if ($_POST) {
            extract($_POST);
            // create the payment processor if user haven't done it yet
            if(isset($_POST['payment_method']) && $_POST['payment_method'] == 'Paypal') {
                $PaypalAccountId = $PaypalAccounts->addPaypalAccount(array(
                    'email' => trim($_POST['paypalEmail']),
                    'currencyCode' => trim($_POST['paypalCurrency']),
                ));
                $Currency = $_POST['paypalCurrency'] == 'USD' ? '$' : '&#163;';
                // update PaypalAccountId field in the users table
                $Users->edit($_SESSION['UserId'], array('PaypalAccountId' => $PaypalAccountId, 'Currency' => $Currency));
            } else if (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Google Checkout') {
                $GoogleCheckoutAccountId = $GoogleCheckoutAccounts->addGoogleCheckoutAccount(array(
                    'GoogleCheckoutAccountName' => $user->fullName,
                    'GoogleMerchantId' => trim($_POST['MerchantID']),
                    'GoogleMerchantKey' => trim($_POST['MerchantKey']),
                    'Currency' => ($_POST['Currency'] == 'USD' ? '$' : '&#163;'),
                    'CurrencyType' => $_POST['Currency'],
                ));
                $Currency = $_POST['Currency'] == 'USD' ? '$' : '&#163;';
                $Users->edit($_SESSION['UserId'], array('GoogleCheckoutAccountId' => $GoogleCheckoutAccountId, 'Currency' => $Currency));
                // create the responsehandler file
                $this->create_response_handler($this->view->responsehandler);
            }

            $StartTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['StartTime']);
            $StartDate = trim($_POST['StartDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['StartDate']." ".$StartTime)) : "0000-00-00 00:00:00";
            $EndTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
            $EndDate = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";

            // add event record first
            $event                = new Event();
            $event->title         = $Title;
            $event->text          = $Description;
            $event->location      = $Location;
            $event->startDate     = $StartDate;
            $event->endDate       = $EndDate;
            $event->isSellTickets = $isSellTickets;
            $event->siteId        = '';
            $event->createdById   = $user->id;
            $event->currency      = !is_null($user->currency) ? $user->currency : '$';
            $gCheckoutId = 0;
            if (!is_null($user->googleCheckoutAccountId)) {
                $gCheckoutId = $user->googleCheckoutAccountId;
            }
            $pAccountId = 0;
            if (!is_null($user->paypalAccountId)) {
                $pAccountId = $user->paypalAccountId;
            }
            $event->googleCheckoutAccountId = $gCheckoutId;
            $event->paypalAccountId         = $pAccountId;

            if ($total_tickets > 0 && $isSellTickets == 1) {
                $totalTickets = 0;
                for($ctr = 1; $ctr <= $total_tickets; $ctr++) {
                    if (isset($_POST['TicketName'][$ctr])) {
                        $ticket              = new Ticket();
                        $ticket->eventId     = $event->id;
                        $ticket->name        = $_POST['TicketName'][$ctr];
                        $ticket->description = $_POST['TicketDescription'][$ctr];
                        if ($_POST['TicketPrice'][$ctr] > 0) {
                            $ticket->price = $_POST['TicketPrice'][$ctr];
                        }
                        if ($_POST['TicketQuantity'][$ctr] > 0) {
                            $ticket->quantity  = $_POST['TicketQuantity'][$ctr];
                            $totalTickets     += $ticket->quantity;
                        }
                        if (!empty($_POST['TicketStartDate'][$ctr])) {
                            $ticket->startDate = date('Y-m-d', strtotime($_POST['TicketStartDate'][$ctr]));
                        }
                        if (!empty($_POST['TicketEndDate'][$ctr])) {
                            $ticket->endDate = date('Y-m-d', strtotime($_POST['TicketEndDate'][$ctr]));
                        }
                        $ticket->save();
                    }
                }
            }
            // RSVP or Yes
            if ($isSellTickets > 0) {
                if ($isSellTickets == 2) {
                    $event->limitTickets = $numTickets;
                } else {
                    $event->limitTickets = $totalTickets;
                }
            }
            $event->save();

            $user = User::get($_SESSION['UserId']);
            $homeUrl = "aaa";
            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                    EventDispatcher::$USER_CREATE_ACTION, array($user, $event, "Event", $homeUrl));

            $this->view->message = "Event has been successfully added.";
            header("location: /".$user->urlName."/share-event?EventId={$event->id}&newevent=getstarted");
        }
    }

    public function activatefundraisingAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $this->view->UserId = $parameters['UserId'];
        if (isset($parameters['ProjectId'])) {
            $this->view->ProjectId = $parameters['ProjectId'];
        }
        if (isset($parameters['EventId'])) {
            $this->view->EventId = $parameters['EventId'];
        }
        $this->view->data = $Users->loadInfo($parameters['UserId']);
        $PaypalAccounts = new Brigade_Db_Table_PaypalAccounts();
        $GoogleCheckoutAccounts = new Brigade_Db_Table_GoogleCheckoutAccounts();
        $last_rec = $GoogleCheckoutAccounts->getMaxCheckoutId();
        $this->view->responsehandler = $last_rec['GoogleCheckoutAccountId'] + 1;

        if ($_POST) {
            if ($_POST['activate_fundraising'] == 'Yes') {
                if($_POST['pass_donations'] == 0) {
                    $_POST['feePercentage'] = 0;
                    $_POST['empoweredPercentage'] = 0;
                }
                if($_POST['payment_method'] == 'Paypal') {
                    $PaypalId = $PaypalAccounts->addPaypalAccount(array(
                        'email' => trim($_POST['paypalEmail']),
                        'currencyCode' => trim($_POST['paypalCurrency']),
                    ));

                    $ppCurrency = $_POST['paypalCurrency'] == 'USD' ? '$' : '&#163;';
                    $Users->edit($_POST['UserId'], array('PaypalAccountId' => $PaypalId, 'Currency' => $ppCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                } else if ($_POST['payment_method'] == 'Google Checkout') {
                    $GoogleCheckoutAccountId = $GoogleCheckoutAccounts->addGoogleCheckoutAccount(array(
                        'GoogleCheckoutAccountName' => $this->view->data['NetworkName'],
                        'GoogleMerchantId' => trim($_POST['MerchantID']),
                        'GoogleMerchantKey' => trim($_POST['MerchantKey']),
                        'Currency' => ($_POST['Currency'] == 'USD' ? '$' : '&#163;'),
                        'CurrencyType' => $_POST['Currency'],
                    ));
                    $gcCurrency = $_POST['Currency'] == 'USD' ? '$' : '&#163;';
                    $Users->edit($_POST['UserId'], array('GoogleCheckoutAccountId' => $GoogleCheckoutAccountId, 'Currency' => $gcCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                    // create the responsehandler file
                    $this->create_response_handler($this->view->responsehandler);
                }

                // update the projects and events table
                $Brigades = new Brigade_Db_Table_Brigades();
                $updated_userInfo = $Users->loadInfo($parameters['UserId']);
                $where = $Brigades->getAdapter()->quoteInto("UserId = ?", $_POST['UserId']);
                $Brigades->update(array(
                    'GoogleCheckoutAccountId' => $updated_userInfo['GoogleCheckoutAccountId'],
                    'PaypalAccountId' => $updated_userInfo['PaypalAccountId'],
                    'Currency' => $updated_userInfo['Currency'],
                    'PercentageFee' => $updated_userInfo['PercentageFee'],
                    'allowPercentageFee' => $updated_userInfo['allowPercentageFee']
                ), $where);

                $Events = new Brigade_Db_Table_Events();
                $where = $Events->getAdapter()->quoteInto("UserId = ?", $_POST['UserId']);
                $Events->update(array(
                    'GoogleCheckoutAccountId' => $updated_userInfo['GoogleCheckoutAccountId'],
                    'PaypalAccountId' => $updated_userInfo['PaypalAccountId'],
                    'Currency' => $updated_userInfo['Currency'],
                ), $where);
            }
            if (isset($parameters['ProjectId'])) {
                $Brigades = new Brigade_Db_Table_Brigades();
                $projInfo = $Brigades->loadInfoBasic($parameters['ProjectId']);
                header("location: /".$projInfo['URLName']);
            }
            if (isset($parameters['EventId'])) {
                header("location: /".$this->view->data['URLName']."/events?EventId=".$parameters['EventId']);
            }
        }
    }

    /**
     * Membership portal for user profile.
     * @TODO: Membership portal. Now deactivate membership from profile page.
     */
    public function membershipAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $this->view->user = $user = $this->sessionUser;
        //for progress bar
        $this->view->toolsUsage = 100;

        $this->renderPlaceHolders();
    }

    /**
     * Remove membership rebilling from user member.
     * This will stop automatic payment rebill.
     */
    public function membershipremoveAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        $config = Zend_Registry::get('configuration');
        if (empty($params['memberId'])) {
            $this->_helper->redirector('error', 'error');
        }
        Zend_Registry::get('logger')->info('InfusionSoft::Membership Remove:'.$params['memberId']);

        $member = Member::get($params['memberId']);
        $group  = $member->group;
        if (!$config->chapter->membership->enable ||
            in_array($group->organizationId, $config->chapter->membership->settings->toArray()) ||
            !in_array($group->organizationId, $config->chapter->membership->active->toArray())
        ) {
            //only enabled for settings
            $this->_helper->redirector('error', 'error');
        }
        if ($member->userId != $this->sessionUser->id) {
            $this->_helper->redirector('error', 'error');
        }
        //update member status
        $member->stopMembership();
        if ($member->rebillId != '') {
            $this->_stopMembershipPayments($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Membership Deactivated:'.$member->id);
        }
        $configIS = $config->infusionsoft;
        if ($configIS->active &&
            in_array($member->organizationId, $configIS->orgs->toArray())
        ) {
            $is = Infusionsoft::getInstance();
            $is->updateMemberContact($member);
        }
        $this->salesforceMemberIntegration($member);
    }

    /**
     * Prepare all plceholders for the new design.
     *
     */
    public function renderPlaceHolders() {
        $this->view->render('profile/header.phtml');
        $this->view->render('profile/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');
    }

    public function editnameinfoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if ($_POST && $this->_helper->authUser->isLoggedIn()) {
            $this->sessionUser->firstName = trim($_POST['firstName']);
            $this->sessionUser->lastName  = trim($_POST['lastName']);
            $this->sessionUser->fullName  = trim($_POST['firstName']) . ' ' .
                                            trim($_POST['lastName']);
            $this->sessionUser->save();
        }
    }

    /**
     * Send bluepay notification to stop membersip
     *
     * @param Member $member
     */
    protected function _stopMembershipPayments(Member $member) {
        $config = Zend_Registry::get('configuration');

        if (in_array($member->group->organizationId,
            $config->chapter->membership->bluepay->orgs->toArray())
        ) {
            //custom bluepay id for specific brigades using other gateway
            $bluePay = BluePay::get($config->chapter->membership->bluepay->id);
            $bpay = new BluePayment(
                $bluePay->accountId,
                $bluePay->secretKey,
                $bluePay->mode
            );
        } else {
            $bpay = new BluePayment(
                $member->group->bluePay->accountId,
                $member->group->bluePay->secretKey,
                $member->group->bluePay->mode
            );
        }
        Zend_Registry::get('logger')->info("Membership::[Action Stop][Member: {$member->id}][RebillId: {$member->rebillId}]");
        $bpay->setRebillId($member->rebillId);
        $bpay->stopRebill();
        if ($bpay->getStatus() == 'stopped') {
            Zend_Registry::get('logger')->info("Membership::[Stopped][Member: {$member->id}][RebillId: {$member->rebillId}]");
            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                EventDispatcher::$MEMBERSHIP_REMOVE,
                array(
                    $member->user->email,
                    $member->user->fullName,
                    $member->group->name
                )
            );
        }
    }

}
