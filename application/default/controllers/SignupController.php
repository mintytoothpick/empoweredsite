<?php

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/Survey.php';
require_once 'Mailer.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

require_once 'Project.php';
require_once 'Group.php';
require_once 'Organization.php';
require_once 'Infusionsoft.php';
require_once 'Salesforce.php';

class SignUpController extends BaseController {
    protected $_http;
    public function init() {
        parent::init();
    }

    /*
     * The default action - show the home page
     */
    public function indexAction() {
        Zend_Registry::get('logger')->info(__METHOD__);
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }

        $parameters = $this->_getAllParams();
        $Brigades   = new Brigade_Db_Table_Brigades();
        $Users      = new Brigade_Db_Table_Users();

        $project = $this->view->project = Project::get($parameters['ProjectId']);

        //already a volunteer
        if ($project->getVolunteerByUser($this->sessionUser)) {
            return $this->_redirect('/' . $project->urlName);
        }

        if(!empty($project->organizationId)) {
            $organization = $this->view->organization = $project->organization;
        }

        if(!empty($project->groupId)) {
            $group = $this->view->group = $project->group;

            // Check for group membership restrictions
            $config = Zend_Registry::get('configuration');
            if ($config->chapter->membership->enable &&
                !in_array($group->organizationId, $config->chapter->membership->settings->toArray()) &&
                in_array($group->organizationId, $config->chapter->membership->active->toArray())
            ) {
                $member = $group->getMember($this->sessionUser);
                if ($group->hasMembershipFee && $group->activityRequiresMembership
                    && (!$group->isMember($this->sessionUser)
                    && (empty($member) || (!empty($member) && !$member->paid)))
                ) {
                    $session = new Zend_Session_Namespace('volunteer_membership');
                    if(empty($session->membershipPaid)) {
                        $session->membershipPaid = false;
                        $session->projectUrlName = $project->urlName;

                        // Redirect to become a member.
                        return $this->_redirect('/' . $group->urlName . '/membership');
                    }
                } else if ($group->activityRequiresMembership) {

                    if (!empty($member) && !$member->activateEmail) {
                        //the user must be a member of the chapter and is waiting
                        //admin approval
                        $this->view->needAdminAppr = true;
                    }
                }
            }

            if(!empty($group->programId)) {
                $program = $this->view->program = $group->program;
            }

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');

        } else if(!empty($project->organizationId)) {
            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');
        } else {
            $this->view->render('project/header.phtml');
            $this->view->soloProject = true;
        }

        $this->view->render('signup/terms.phtml');

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($project);

        $this->view->volunteers  = $Brigades->loadVolunteers($project->id);
        $this->view->networkInfo = $Brigades->loadBrigadeTreeInfo($project->id);

        $this->view->user = $Users->loadInfo($_SESSION['UserId']);

        if ($_POST) {
            $this->view->error = '';
            $ProjectId         = $_POST['ProjectId'];
            $UserId            = $_POST['UserId'];

            $Volunteers   = new Brigade_Db_Table_Volunteers();
            $is_signed_up = $Volunteers->isUserSignedUp($ProjectId, $UserId);
            $is_denied    = $Volunteers->isDenied($ProjectId, $UserId);
            $is_deleted   = $Volunteers->isDeleted($ProjectId, $UserId);
            $stoped_user  = false;
            $error_msg    = false;
            if ($is_denied) {
                $error_msg = "You have been denied from this activity by an administrator. Please email the chapter's contact if you have any questions.";
            } else if($is_deleted) {
                $error_msg = "You have been deleted from this activity by an administrator. Please email the chapter's contact if you have any questions.";
            } else if($is_signed_up) {
                $stoped_user = $Volunteers->stopedVoluteering($ProjectId, $UserId);
                if (!$stoped_user){
                    $error_msg = "You have already signed up with this activity.";
                }
            }

            if($error_msg) {
                $this->view->error = $error_msg;
            } else {
                if (in_array($organization->id, Organization::$withSurvey)) {
                    if (!empty($parameters['signatureName']) && !empty($parameters['signatureAge'])) {
                        $session = new Zend_Session_Namespace('signature_signup');
                        $session->signatureName = $parameters['signatureName'];
                        $session->signatureAge  = $parameters['signatureAge'];
                        $session->signatureDate = date('Y-m-d');
                    }
                    //custom surveys
                    if (in_array($organization->id, $config->organization->customSurvey->toArray())) {
                        return $this->_redirect('/signup/customsurvey?ProjectId='.$ProjectId);
                    } else {
                        header('location: /signup/survey/?ProjectId='.$ProjectId);
                    }
                } else {
                    $this->coreTravelIntegration($project);
                    if ($is_signed_up && $stoped_user) {
                        $Volunteers->reSignupVolunteer(
                            $Volunteers->getVolunteerIdByProjectAndUser($ProjectId, $UserId),
                            ($project->status == 'Open')
                        );
                        // InfusionSoft
                        $member = $group->getMember($this->sessionUser);
                        if ($member) {
                            $this->infusionSoftIntegration($member);
                            $volunteer = $project->getVolunteerByUser($this->sessionUser);
                            if ($volunteer) {
                                $this->_salesForceIntegrationVolunteer($volunteer);
                            }
                        } else {
                            $volunteer = $project->getVolunteerByUser($this->sessionUser);
                            $this->_infusionSoftIntegrationVolunteer($volunteer);
                            $this->_salesForceIntegrationVolunteer($volunteer);
                        }
                    } else if (is_null($Volunteers->getVolunteerIdByProjectAndUser($ProjectId, $UserId))) {
                        $volunteer = $this->signupVolunteer($ProjectId, $UserId);
                        // Add signature
                        if ($volunteer) {
                            if (!empty($parameters['signatureName']) &&
                                !empty($parameters['signatureAge']) &&
                                !empty($parameters['signatureDate'])
                            ) {
                                $volunteer->signatureName = $parameters['signatureName'];
                                $volunteer->signatureAge  = $parameters['signatureAge'];
                                $volunteer->signatureDate = $parameters['signatureDate'];
                                $volunteer->save();
                            }
                            $this->_infusionSoftIntegrationVolunteer($volunteer);
                            $this->_salesForceIntegrationVolunteer($volunteer);
                        }
                    }
                    if ($organization->isOpen) {
                        header('location: /signup/tell-friends/?ProjectId='.$ProjectId);
                    } else {
                        header('location: /signup/next/?ProjectId='.$ProjectId);
                    }
                }
            }
        }

        if(!isset($this->view->soloProject)) {
            $this->view->render('nonprofit/breadcrumb.phtml');
        }
        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');

    }

    /**
     * Set a new survey page for custom orgs not GB USA.
     */
    public function customsurveyAction() {
        $params  = $this->_getAllParams();
        $project = Project::get($params['ProjectId']);

        $this->view->project = $project;
        $this->view->group   = $project->group;

        $this->_helper->viewRenderer->setRender(
            'survey-'.strtolower($project->organization->urlName)
        );

        $this->view->breadcrumb = $this->view->breadcrumbHelper($project);
        $this->view->render('nonprofit/footer.phtml');
        $this->view->render('project/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('group/tabs.phtml');
        $this->_helper->layout->setLayout('newlayout');
    }

    /**
     * Add member user to infusionsoft.
     *
     * @param Member $member Member instance.
     *
     * @return void.
     */
    protected function infusionSoftIntegration($member, $addMissingContact = true) {
        Zend_Registry::get('logger')->info('InfusionSoft::Member');
        $configIS = Zend_Registry::get('configuration')->infusionsoft;
        if (!($configIS->active &&
            in_array($member->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('InfusionSoft::Signup::MemberContact');
        $is = Infusionsoft::getInstance();
        if ($addMissingContact) {
            $is->addMemberContact($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Signup::Add/Update:'.$member->id);
        } else {
            $is->updateMemberContact($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Signup::Only Update:'.$member->id);
        }
    }

    /**
     * Add volunteer information under infusionsoft.
     */
    protected function _infusionSoftIntegrationVolunteer($volunteer, $addMissingContact = true) {
        Zend_Registry::get('logger')->info('InfusionSoft::Volunteer');
        $configIS = Zend_Registry::get('configuration')->infusionsoft;
        if (!($configIS->active &&
            in_array($volunteer->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('InfusionSoft::Signup::VolunteerContact');
        $is = Infusionsoft::getInstance();
        if ($addMissingContact) {
            $is->addVolunteerContact($volunteer);
            Zend_Registry::get('logger')->info('InfusionSoft::Signup::Add/Update:'.$volunteer->id);
        } else {
            $is->updateVolunteerContact($volunteer);
            Zend_Registry::get('logger')->info('InfusionSoft::Signup::Only Update:'.$volunteer->id);
        }
    }

    /**
     * Add volunteer information under infusionsoft.
     */
    protected function _salesForceIntegrationVolunteer($volunteer) {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($volunteer->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Signup::VolunteerContact');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($volunteer->project->organization)) {
            $salesforce->addVolunteer($volunteer);
            $salesforce->logout();
            Zend_Registry::get('logger')->info(
                'SalesForce::Signup::Added:'.$volunteer->id
            );
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$volunteer->organizationId
            );
        }
    }

    /**
     * Add volunteer/fundraiser user to core travel. Use logged in user.
     *
     * @param $project Initiative
     *
     * @return void.
     */
    protected function coreTravelIntegration($project) {
        if (!$this->view->isLoggedIn) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $configCT = Zend_Registry::get('configuration')->coretravel;
        if (!($configCT->active &&
            in_array($project->organizationId, $configCT->orgs->toArray()))
        ) {
            return;
        }
        $user  = $this->view->userNew;
        try {
            $soap  = new SoapClient($configCT->wsdl);
        } catch (SoapFault $sf) {
            Zend_Registry::get('logger')->info('CoreTravel::Error Loading WSDL');
            return;
        }
        $login = false;
        try {
            $login = $soap->Login(array(
                'username' => $configCT->user,
                'password' => $configCT->password
            ));
        } catch (SoapFault $sf) {
            Zend_Registry::get('logger')->info('CoreTravel::Error Login');
        }
        if ($login && $login->LoginResult->Authenticated) {
            $resp = $soap->EnrolleExists(array(
                'EnrolleeId' => $user->id
            ));

            $timezone = new DateTimeZone('PST');
            if(!$resp->EnrolleExistsResult) {
                $bParam = $this->_getParam('birthdate');
                if ((empty($user->dateOfBirth) || $user->dateOfBirth == '0000-00-00 00:00:00')
                    && !empty($bParam)
                ) {
                    $dateB = date("Y-m-d",strtotime($bParam));
                } else {
                    $dateB = date('Y-m-d', strtotime($user->dateOfBirth));
                }

                $time    = $dateB . 'T00:00:00';
                $date    = new DateTime($time, $timezone);
                $dateStr = str_replace($date->format( 'P' ), '', $date->format( 'c' ));
                $params  = array(
                    'AuthToken'  => $login->LoginResult->AuthToken,
                    'EnrolleeId' => $user->id,
                    'FirstName'  => $user->firstName,
                    'LastName'   => $user->lastName,
                    'DOB'        => $dateStr
                );
                try {
                    $insert = $soap->InsertEnrollee($params);
                    Zend_Registry::get('logger')->info(
                        'CoreTravel::User Added (Params:'.print_r($params, true).')'
                    );
                } catch (SoapFault $sf) {
                    Zend_Registry::get('logger')->info(
                        'CoreTravel::Error InsertEnrollee (Params:'.print_r($params, true).')'
                    );
                }
            } else {
                Zend_Registry::get('logger')->info(
                    'CoreTravel::User Exists (U:'.$user->id.'|P:'.$project->id.')'
                );
            }
            $time      = date('Y-m-d', strtotime($project->startDate)) . 'T00:00:00';
            $date      = new DateTime($time, $timezone);
            $startDate = str_replace($date->format( 'P' ), '', $date->format( 'c' ));

            //avoid destination if end date is empty
            if ($project->endDate != '0000-00-00 00:00:00' && $project->endDate != '') {
                $time    = date('Y-m-d', strtotime($project->endDate)) . 'T00:00:00';
                $date    = new DateTime($time, $timezone);
                $endDate = str_replace($date->format( 'P' ), '', $date->format( 'c' ));

                try {
                    //TODO: for new orgs, change org_id
                    $data = array(
                        'AuthToken' => $login->LoginResult->AuthToken,
                        'dest_id'   => $project->id,
                        'org_id'    => 'DAF7E701-4143-4636-B3A9-CB9469D44178',
                        'dest_name' => $project->name
                    );
                    $dest = $soap->AddOrginizationDestination($data);
                } catch (SoapFault $sf) {
                    Zend_Registry::get('logger')->info(
                        'CoreTravel::Error AddOrginizationDestination (Params:'.print_r($data, true).')'
                    );
                }
                if($dest->AddOrginizationDestinationResult->RowsAffected < 1) {
                    Zend_Registry::get('logger')->info(
                        'CoreTravel::AddOrginizationDestination Error (P:'.
                        $project->id.'|M:'.$dest->AddOrginizationDestinationResult->Message.')'
                    );
                } else {
                    Zend_Registry::get('logger')->info(
                        'CoreTravel::AddOrginizationDestination Success (P:'.$project->id.')'
                    );
                }

                try {
                    $data = array(
                        'AuthToken'     => $login->LoginResult->AuthToken,
                        'EnrolleeId'    => $user->id,
                        'DestinationId' => $project->id,
                        'StartDate'     => $startDate,
                        'EndDate'       => $endDate
                    );
                    $resp = $soap->AddEnrolleeToDestination($data);
                } catch (SoapFault $sf) {
                    Zend_Registry::get('logger')->info(
                        'CoreTravel::Error AddEnrolleeToDestination (Params:'.print_r($data, true).')'
                    );
                }

                if($resp->AddEnrolleeToDestinationResult->RowsAffected < 1) {
                    Zend_Registry::get('logger')->info(
                        'CoreTravel::AddEnrolleeToDestination Error (U:'.$user->id.
                        '|P:'.$project->id.'|E:'.$resp->AddEnrolleeToDestinationResult->Message.')'
                    );
                } else {
                    Zend_Registry::get('logger')->info(
                        'CoreTravel::AddEnrolleeToDestination Success (U:'.
                        $user->id.'|P:'.$project->id.')'
                    );
                }
            } else {
                Zend_Registry::get('logger')->err(
                    'CoreTravel::Error (Project with empty end date:'.$project->id.')'
                );
            }
        } else {
            Zend_Registry::get('logger')->err(
                'CoreTravel::Error (Invalid Login:'.$resp->ResponseMessage.')'
            );
        }
    }

    public function tellfriendsAction() {
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $Brigades = new Brigade_Db_Table_Brigades();
        $this->view->data = $Brigades->loadInfoBasic($parameters['ProjectId']);
        $this->view->userInfo = $Users->loadInfo($_SESSION['UserId']);
        if ($_POST && !empty($_POST['emails'])) {
            preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $_POST['emails'], $emails);
            $this->view->emails = $emails[0];
            foreach ($emails[0] as $email) {
                $email = is_array($email) ? $email[0] : $email;
                $subject = "I have joined ".stripslashes($this->view->data['Name']);
                $mailer = new Mailer();
                $mailer->sendMailToFrom($email, $subject, stripslashes($_POST['message']), "From: ".$this->view->userInfo['Email']);
                $this->view->sent = true;
            }
            $volunteerInfo = $Volunteers->loadInfo2($_SESSION['UserId'], $parameters['ProjectId']);
            if (!$volunteerInfo['hasTellFriends']) {
                $where = $Volunteers->getAdapter()->quoteInto("VolunteerId = ?", $volunteerInfo['VolunteerId']);
                $Volunteers->update(array('hasTellFriends' => 1), $where);
            }
        }
    }

    /**
     * Signup volunteer to initiative. Also add member to chapter if exists.
     *
     * Emails Notifications:
     * 1- If isset group -> send emails to contact group
     * 2- ElseIf isset org -> send emails to contact org
     * 3- ElseIf -> send emails to contact project
     * 4- If not set contact email -> send emails to admins chapter if it has chapter
     *
     */
    private function signupVolunteer($ProjectId, $UserId) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $user      = User::get($UserId);
        $project   = Project::get($ProjectId);
        $volunteer = null;
        if ($project->addVolunteer($user)) {
            if (!empty($project->groupId) && $project->group) {
                if ($project->group->activityRequiresMembership) {
                    if ($project->group->isMember($user)) {
                        $added = $project->group->getMember($user);
                    } else {
                        $added = $project->group->addMember($user);
                    }
                    if ($added) {
                        // InfusionSoft
                        $this->infusionSoftIntegration($added);
                        if (!$project->volunteer->active) {
                            $project->volunteer->activate();
                        }
                    }
                }
                $contactEmail = $project->group->contact->email;
                $contactURL   = $project->group->urlName;
            } else if(!empty($project->organizationId)) {
                $project->organization->addMember($user);
                $contactEmail = $project->organization->contact->email;
                $contactURL   = $project->organization->urlName;
            } else {
                $contactEmail = $project->contact->email;
                $contactURL   = $project->urlName;
            }
            $volunteer = $project->getVolunteerByUser($user);
            if ($volunteer) {
                $activity              = new Activity();
                $activity->siteId      = $project->id;
                $activity->type        = 'Joined Brigade';
                $activity->createdById = $this->sessionUser->id;
                $activity->date        = date('Y-m-d H:i:s');
                $activity->save();

                $this->_salesForceIntegrationVolunteer($volunteer);
            }
        }
        $eventDispatcher = Zend_Registry::get('eventDispatcher');
        if (isset($contactEmail) && !empty($contactEmail) && $contactEmail != '') {
            if ($project->status == 'Close') {
                $eventDispatcher->dispatchEvent(
                    EventDispatcher::$VOLUNTEER_REQUEST,
                    array(
                        $contactEmail,
                        $user->firstName,
                        $user->email,
                        $project->name,
                        $contactURL
                    )
                );
                $eventDispatcher->dispatchEvent(
                    EventDispatcher::$AWAITING_REQUEST,
                    array(
                        $user->email,
                        $project->name,
                        $user->firstName,
                        $contactEmail
                    )
                );
            } else {
                $eventDispatcher->dispatchEvent(
                    EventDispatcher::$VOLUNTEER_ACCEPTED,
                    array(
                        $user->email,
                        $user->firstName,
                        $project->name,
                        $contactEmail,
                        !empty($project->organizationId) && $project->organizationId == 'DAF7E701-4143-4636-B3A9-CB9469D44178')
                    );
            }
        } else {
            if ($project->status == 'Close') {
                $admins = $project->group->getAdminsRoles();
                if (count($admins) > 0) {
                    foreach ($admins as $admin) {
                        $eventDispatcher->dispatchEvent(
                            EventDispatcher::$VOLUNTEER_REQUEST,
                            array(
                                $admin->email,
                                $user->firstName,
                                $user->email,
                                $project->name,
                                $project->urlName
                            )
                        );
                    }
                }
                $eventDispatcher->dispatchEvent(
                    EventDispatcher::$AWAITING_REQUEST,
                    array(
                        $user->email,
                        $project->name,
                        $user->firstName,
                        $project->contact->email
                    )
                );
            }
        }
        $session = new Zend_Session_Namespace('signature_signup');
        if (!empty($session->signatureName)) {
            $volunteer->signatureName = $session->signatureName;
            $volunteer->signatureAge  = $session->signatureAge;
            $volunteer->signatureDate = $session->signatureDate;
            $volunteer->save();

            $session->signatureName = null;
            $session->signatureAge  = null;
            $session->signatureDate = null;
        }
        return $volunteer;
    }

    public function nextAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Brigades = new Brigade_Db_Table_Brigades();
        if (isset($parameters['ProjectId'])) {

            $project  =  $this->view->project  =  Project::get($parameters['ProjectId']);
            if(!empty($project->organizationId)) {
                $organization = $this->view->organization = $project->organization;
            }
            if(!empty($project->groupId)) {
                $group = $this->view->group = $project->group;

                if(!empty($group->programId)) {
                    $program = $this->view->program = $group->program;
                }

                $this->view->render('group/header.phtml');
                $this->view->render('group/tabs.phtml');

            } else if(!empty($project->organizationId)) {

                $this->view->render('nonprofit/header.phtml');
                $this->view->render('nonprofit/tabs.phtml');
            } else {
                $this->view->render('project/header.phtml');
                $this->view->soloProject = true;
            }

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper($project);

            if(!isset($this->view->soloProject)) {
                $this->view->render('nonprofit/breadcrumb.phtml');
            }
            $this->view->render('nonprofit/footer.phtml');
            $this->_helper->layout->setLayout('newlayout');

        } else {
            $this->view->message = 'Activity not found.';
        }
    }

    /**
     * Survey for USA . UK => Global Brigades to accept volunteer
     * into activity.
     */
    public function surveyAction(){
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $email = Zend_Auth::getInstance()->getIdentity();
        $Users = new Brigade_Db_Table_Users();

        $this->view->user_info = $Users->findBy($email);
        $UserId = $this->view->user_info['UserId'];
        $this->view->UserId = $this->view->user_info['UserId'];

        $parameters     = $this->_getAllParams();
        $BrigadesManage = new Brigade_Db_Table_Brigades();
        $SurveyManage   = new Brigade_Db_Table_Survey();

        $errors = array();
        if (!empty($parameters['ProjectId'])){
            $project = Project::get($parameters['ProjectId']);
            $this->view->project = $project;

            if(!empty($project->groupId)) {
                $group = $this->view->group = $project->group;

                $this->view->render('group/header.phtml');
                $this->view->render('group/tabs.phtml');
            } else {
                $this->view->organization = $project->organization;
                $this->view->siteBanner = false;
                if (!empty($project->organization->bannerMediaId)) {
                    $Media = new Brigade_Db_Table_Media();
                    $siteBanner = $Media->getSiteMediaById($project->organization->bannerMediaId);
                    $this->view->siteBanner = $siteBanner;
                    $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
                }
                $this->view->render('nonprofit/header.phtml');
                $this->view->render('nonprofit/tabs.phtml');
            }
            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper($project);

            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/footer.phtml');
            $this->_helper->layout->setLayout('newlayout');

            if($this->getRequest()->isPost()){

                if (empty($this->view->userNew->dateOfBirth)) {
                    $this->view->userNew->dateOfBirth = date("Y-m-d",strtotime($this->_getParam('birthdate')));
                    $this->view->userNew->save();
                }

                $this->coreTravelIntegration($project);

                $brigadeDetail = $BrigadesManage->loadInfo($project->id);
                $birthdate = $this->_getParam('birthdate');
                $passport_expiration = $this->_getParam('passport_expiration');
                if($passport_expiration != '') {
                    $passport_expiration = date("Y/m/d",strtotime($passport_expiration));
                }
                $data = array("UserId"=>$UserId,
                    "firstname"=>$this->_getParam('firstname'),
                    "middlename"=>$this->_getParam('middlename'),
                    "lastname"=>$this->_getParam('lastname'),
                    "nickname"=>$this->_getParam('nickname'),
                    "gender"=>$this->_getParam('gender'),
                    "birthday"=>date("Y/m/d",strtotime($birthdate)),
                    "email"=>$this->_getParam('email'),
                    "phone"=>$this->_getParam('phone'),
                    "citizenship" => $this->_getParam('citizenship'),
                    "passport_type"=>$this->_getParam('passport_type'),
                    "passport_number"=>$this->_getParam('passport_number'),
                    "passport_expirationdate"=> $passport_expiration,
                    "emergency_contactname"=>$this->_getParam('emergency_name'),
                    "emergency_contactnumber"=>$this->_getParam('emergency_number'),
                    "emergency_contactrelationship"=>$this->_getParam('emergency_relationship'),
                    "emergency_contactemail"=>$this->_getParam('emergency_email'),
                    "skills"=>$this->_getParam('skills'),
                    "degree"=>$this->_getParam('degree'),
                    "dietary_restrictions"=>$this->_getParam('diet_restriction'),
                    "medical_conditions"=>$this->_getParam('allergies'),
                    "other_information"=>$this->_getParam('other_information'),
                    "contact_america"=>$this->_getParam('contact_america'),
                    "spanish_level"=>$this->_getParam('spanish_level'),
                    "GroupId"=>$brigadeDetail['GroupId'],
                    "ProjectId"=>$this->_getParam('ProjectId'),
                    "discipline"=>$this->_getParam('discipline'),
                    "brigade_month"=>date('n',strtotime($brigadeDetail['StartDate'])),
                    "leadership_position"=>$this->_getParam('position'),
                    "question1"=> $this->_getParam('quest1') . (($this->_getParam('quest1') == 'Yes') ? ' - '.$this->_getParam('quest2') : ''),
                    "question2"=> ($this->_getParam('quest1') == 'Yes') ? $this->_getParam('quest3') : 'N/A',
                    "question3"=> $this->_getParam('quest4') . (($this->_getParam('quest4') == 'Yes') ? ' - '.$this->_getParam('quest5') : ''),
                    "question4"=> $this->_getParam('quest6') . (($this->_getParam('quest6') == 'Yes') ? ' - '.$this->_getParam('quest7') : ''),
                    "question5"=> $this->_getParam('quest8') . (($this->_getParam('quest8') == 'Yes') ? ' - '.$this->_getParam('quest9') : ''),
                    "question6"=> $this->_getParam('quest10'),
                    "date"=>date('Y-m-d H:i:s')
                );

                try{
                    $SurveyManage->addSurvey($data);
                    $volunteer = $this->signupVolunteer($project->id, $UserId);
                    if ($volunteer) {
                        $volunteer->user->phone = $this->_getParam('phone');
                        $volunteer->user->save();

                        $this->_infusionSoftIntegrationVolunteer($volunteer);
                        $this->_salesForceIntegrationVolunteer($volunteer);
                    }

                    header('location: /signup/next/?ProjectId='.$project->id);
                }catch (Exception  $e){
                    $errors[] =  "Error: ".$e->getMessage();
                }
            }
            $this->view->ProjectId = $project->id;
        }else {
            $errors[] ="Project not found";
        }
        $this->view->errors = $errors;
    }

    /**
     * Edit survey
     *
     */
    public function editsurveyAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $params = $this->_getAllParams();
        if (!empty($params['ProjectId'])) {
            $project = Project::get($params['ProjectId']);
            $survey  = Survey::getByProjectAndUser($project, $this->view->userNew);

            $this->view->project = $project;
        } else {
            $this->_helper->redirector('error', 'error');
        }
        if (empty($survey) || !isset($survey->userId)) {
            $survey               = new Survey();
            $survey->projectId    = $params['ProjectId'];
            $survey->groupId      = $project->groupId;
            $survey->userId       = $this->view->userNew->id;
            $survey->brigadeMonth = date('n',strtotime($project->startDate));
        }
        $this->view->survey = $survey;

        if(!empty($project->groupId)) {
            $group = $this->view->group = $project->group;

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper($project);
        }
        if ($this->getRequest()->isPost()) {
            $passport_expiration = $params['passport_expiration'];
            if($passport_expiration != '') {
                $passport_expiration = date("Y/m/d",strtotime($passport_expiration));
            }

            $survey->firstName  = $params['firstname'];
            $survey->middleName = $params['middlename'];
            $survey->lastName   = $params['lastname'];
            $survey->nickName   = $params['nickname'];
            $survey->gender     = $params['gender'];
            $survey->birthday   = date("Y/m/d",strtotime($params['birthdate']));
            $survey->email      = $params['email'];
            $survey->phone      = $params['phone'];
            $survey->skills     = $params['skills'];
            $survey->degree     = $params['degree'];

            $survey->dietaryRestrictions          = $params['diet_restriction'];
            $survey->emergencyContactName         = $params['emergency_name'];
            $survey->emergencyContactNumber       = $params['emergency_number'];
            $survey->emergencyContactRelationship = $params['emergency_relationship'];
            $survey->emergencyContactEmail        = $params['emergency_email'];

            $survey->medicalConditions  = $params['allergies'];
            $survey->otherInformation   = $params['other_information'];
            $survey->contactAmerica     = $params['contact_america'];
            $survey->spanishLevel       = $params['spanish_level'];
            $survey->discipline         = $params['discipline'];
            $survey->leadershipPosition = $params['position'];
            $survey->date               = date('Y-m-d H:i:s');
            $survey->citizenship        = $params['citizenship'];

            $survey->passportType           = $params['passport_type'];
            $survey->passportNumber         = $params['passport_number'];
            $survey->passportExpirationDate = $passport_expiration;

            $survey->question1 = $params['quest1'] . (($params['quest1'] == 'Yes') ? ' - '.$params['quest2'] : '');
            $survey->question2 = ($params['quest1'] == 'Yes') ? $params['quest3'] : 'N/A';
            $survey->question3 = $params['quest4'] . (($params['quest4'] == 'Yes') ? ' - '.$params['quest5'] : '');
            $survey->question4 = $params['quest6'] . (($params['quest6'] == 'Yes') ? ' - '.$params['quest7'] : '');
            $survey->question5 = $params['quest8'] . (($params['quest8'] == 'Yes') ? ' - '.$params['quest9'] : '');
            $survey->question6 = $params['quest10'];

            $survey->save();

            $this->view->updated = true;

            header("location: /".$this->view->userNew->urlName."/initiatives/".$project->urlName);
        }
        $this->_helper->viewRenderer('survey');
        $this->view->render('group/header.phtml');
        $this->view->render('group/tabs.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');
    }

    public function surveyreportAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProjectId'])) {
            $Survey = new Brigade_Db_Table_Survey();
            $Brigades = new Brigade_Db_Table_Brigades();
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $volunteers = $Survey->getProjectSurveyReport($parameters['ProjectId']);
            $projectinfo = $Brigades->loadInfo($parameters['ProjectId']);
            $projectname = str_replace(",", "-", $projectinfo['Name']);
            $projectname = str_replace(" ", "-", $projectname." Volunteer Survey Report.xls");
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$projectname");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('"First Name"', '"Middle Name"', '"Last Name"',
            'Nickname', 'Gender', '"Date of Birth"', 'Email', 'Phone', 'Citizenship',
            '"Passport Type"', '"Passport Number"', '"Passport Expiration"',
            '"Emergency Name"', '"Emergency Phone"', '"Emergency Relationship"',
            '"Emergency Email"', '"Notify on Arrival"', '"Spanish Level"', 'Discipline/Major',
            '"Leadership Position"', '"Special Training"', '"Degree/Profession"',
            'Dietary', 'Allergies', 'Other', '"Sign Up Date"',
            '"Medical condition (either physical or mental) for which you receive treatment?"',
            '"Name, phone number, and address of the doctor(s) that are providing you with treatment for the condition or injury."',
            '"Has any doctor ever restricted your physical activities"',
            '"Taking any medication for any serious injury, disability or medical condition"',
            '"Has any doctor ever restricted you from traveling due to any medical condition"',
            '"Any other information about yourself"'
            );
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($volunteers as $volunteer) {
                $line = '';
                foreach($volunteer as $col => $value) {
                    if ($col == 'gender') {
                        $volunteer[$col] = ($value == 1 ? "Male" : "Female");
                    } else {
                        $volunteer[$col] = str_replace('"', '""', $value);
                        $volunteer[$col] = '"'.stripslashes($value).'"';
                    }

                }
                extract($volunteer);
                $line = "$firstname\t$middlename\t$lastname\t$nickname\t$gender\t$birthday\t$email\t$phone\t$citizenship\t$passport_type\t$passport_number\t$passport_expirationdate\t$emergency_contactname\t$emergency_contactnumber\t$emergency_contactrelationship\t$emergency_contactemail\t$contact_america\t$spanish_level\t$discipline\t$leadership_position\t$skills\t$degree\t$dietary_restrictions\t$medical_conditions\t$other_information\t$date\t$question1\t$question2\t$question3\t$question4\t$question5\t$question6";
                $data .= trim($line)."\n";
            }

            print "$headers\n$data";
        }
    }
}
