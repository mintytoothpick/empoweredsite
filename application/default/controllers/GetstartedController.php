<?php

/**
 * GetstartedController - The "get started" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Countries.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Util/ImageCrop.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'BaseController.php';
require_once 'Organization.php';
require_once 'User.php';

class GetstartedController extends BaseController {

    public function init() {
        parent::init();
        $this->view->controller = 'benefits';
    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('index', 'index');
        } else {
            $this->_helper->redirector->gotoUrl('/profile/signup-step2');
        }
    }

    public function createorganizationAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $Media = new Brigade_Db_Table_Media();
        $SiteMedia = new Brigade_Db_Table_MediaSite();
        $Users = new Brigade_Db_Table_Users();
        $UserRole = new Brigade_Db_Table_UserRoles();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $Countries = new Brigade_Db_Table_Countries();
        $Organizations = new Brigade_Db_Table_Organizations();
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $this->view->country_list = $Countries->getAllCountries();

        $session = new Zend_Session_Namespace('profile_video');
        $session->showAdminVideo = true;

        if ($_POST) {
            extract($_POST);

            $UserName = '';
            if(isset($_SESSION['UserId'])) {
                $userInfo = $Users->loadInfo($_SESSION['UserId']);
                $UserName = $userInfo['FullName'];
            }
            $has_groups = 'no';
            if($isMultichaptered == 1) {
                $has_groups = 'yes';
            }
            if($phoneNumber == '1800-555-5555') {
                $phoneNumber = '';
            }

            $url_exists = 0;
            $badExt = false;

            if(!empty($_FILES['NetworkLogo']['name'])) {
                $filename = $_FILES['NetworkLogo']['name'];
                $file_ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
                if($file_ext != 'jpg' && $file_ext != 'jpeg' && $file_ext != 'png' && $file_ext != 'gif') {
                    $badExt = true;
                    $this->view->message = "Please upload a logo in jpeg, png and gif format only.";
                }
            }

            if (!$badExt && !(isset($_REQUEST['customrequest']) && $_REQUEST['customrequest'] == 'true') ) {
                $newOrg = new Organization();
                $newOrg->name        = $NetworkName;
                $newOrg->description = $Description;
                $newOrg->makeUrl();
                $newOrg->hasPrograms   = isset($hasPrograms) ? $hasPrograms : false;
                $newOrg->hasGroups     = $isMultichaptered;
                $newOrg->isOpen        = $isOpen;
                $newOrg->googleId      = 0;
                $newOrg->paypalId      = 0;
                $newOrg->bluePayId     = null;
                $newOrg->nonProfitId   = $nonprofit_id;
                $newOrg->hasActivities = $hasActivities;
                $newOrg->hasCampaigns  = $hasCampaigns;
                $newOrg->hasEvents     = $hasEvents;
                $newOrg->hasMembership = $hasMembership;
                //custom labels
                $newOrg->groupNamingPlural     = $groupNamingPlural;
                $newOrg->groupNamingSingular   = $groupNamingSingular;
                $newOrg->programNamingPlural   = $programNamingPlural;
                $newOrg->programNamingSingular = $programNamingSingular;
                $newOrg->save();

                $Message = "
                    Name: $UserName<br />
                    Organization: $NetworkName<br />
                    Email: $Email<br />
                    Fundraises: $fundraise_amount<br />
                    Volunteers: $volunteer_amount<br />
                    Registered NP: $registered_np<br />
                    Nonprofit ID: $nonprofit_id<br />
                    Multichaptered: $has_groups<br />
                    Phone Number: $phoneNumber";
                if($_SERVER['HTTP_HOST'] == 'empowered.org' || $_SERVER['HTTP_HOST'] == 'www.empowered.org' ) {
                    if(isset($_REQUEST['customrequest']) && $_REQUEST['customrequest'] == 'true') {
                        mail('steveatamian@gmail.com', 'Custom Organization Request',
                        $Message, "From: Empowered.org <admin@empowered.org>");
                    } else {
                        mail('iamjackross@gmail.com', 'Organization Created',
                        $Message, "From: Empowered.org <admin@empowered.org>");
                    }
                } else {
                    if(isset($_REQUEST['customrequest']) && $_REQUEST['customrequest'] == 'true') {
                        mail('empoweredqa@gmail.com', 'Custom Organization Request',
                        $Message, "From: Empowered.org <admin@empowered.org>");
                    } else {
                        mail('empoweredqa@gmail.com', 'Organization Created',
                        $Message, "From: Empowered.org <admin@empowered.org>");
                    }
                }

                $analytics = new Zend_Session_Namespace('Analytics');
                $analytics->organizationCreated = true;

                $_SESSION['newOrg'] = 1; //TODO: Replace it with zend

                // add record on the lookup_table
                Lookup::addOrganization($newOrg);

                // add default administrator for this organization
                $UserRoleId = $UserRole->addUserRole(array(
                    'UserId' => $_SESSION['UserId'],
                    'RoleId' => 'ADMIN',
                    'SiteId' => $newOrg->id
                ));

                // make default admin a member
                $GroupMembers->AddGroupMember(array(
                    'UserId' => $_SESSION['UserId'],
                    'NetworkId' => $newOrg->id,
                    'isAdmin' => 1,
                ));

                if(!empty($WebAddress)) {
                    preg_match("/^https?:\/\/[_a-zA-Z0-9-]+\.[\._a-zA-Z0-9-]+$/i", $WebAddress, $website);
                    if(empty($website[0])) {
                        $WebAddress = 'http://'.$WebAddress;
                    }
                }

                // save network contact info
                $ContactId = $ContactInfo->addContactInfo(array(
                    'Email' => $Email,
                    'WebAddress' => $WebAddress,
                    'Street' => $Street,
                    'CityId' => $CityId,
                    'City' => $City,
                    'RegionId' => $RegionId,
                    'Region' => $Region,
                    'CountryId' => $CountryId,
                    'Country' => $Country,
                    'SiteId' => $newOrg->id
                ));

                // assign the campaign/activity/event if from Expand to Org
                if (isset($_SESSION['assignActivity']) && $isMultichaptered == 0) {
                    $Brigades = new Brigade_Db_Table_Brigades();
                    $Brigades->editProject($_SESSION['assignActivity'], array('NetworkId' => $newOrg->id, 'UserId' => ''));
                    unset($_SESSION['assignActivity']);
                } else if (isset($_SESSION['assignEvent']) && $isMultichaptered == 0) {
                    $Events = new Brigade_Db_Table_Events();
                    $Events->updateEvent($EventId, array('SiteId' => $newOrg->id, 'UserId' => ''));
                    unset($_SESSION['assignEvent']);
                }

                $user = User::get($_SESSION['UserId']);
                Zend_Registry::get('eventDispatcher')->dispatchEvent(
                    EventDispatcher::$ORGANIZATION_CREATED,
                    array($user, $newOrg)
                );

                // save network media/image
                $MediaSize = $_FILES['NetworkLogo']['size'];
                $tmpfile = $_FILES['NetworkLogo']['tmp_name'];
                if ($MediaSize > 0) {
                    // save media
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $newOrg->urlName."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));

                    // save site media
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteId' => $newOrg->id
                    ));

                    // update network LogoMediaId
                    $logo_banner = $_POST['logotype'] == 'logo' ? array('LogoMediaId' => $MediaId) : array('BannerMediaId' => $MediaId);
                    $Organizations->editNetwork($newOrg->id, $logo_banner);

                    //Get the file information
                    $ImageCrop = new Brigade_Util_ImageCrop();
                    $temp_file_name = $_POST['logotype'] == 'logo' ? $newOrg->id : $MediaId;
                    $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$temp_file_name.jpg";

                    // Check if file size does not exceed 2MB
                    move_uploaded_file($tmpfile, $temp_image_location);
                    $width = $ImageCrop->getWidth($temp_image_location);
                    $height = $ImageCrop->getHeight($temp_image_location);
                    //Scale the image if it is greater than the width set above
                    if ($width > 900) {
                        $scale = 900/$width;
                    } else {
                        $scale = 1;
                    }
                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale,$file_ext);

                    // redirect to the page where users will crop the uploaded image
                    if ($_POST['logotype'] == 'logo') {
                        header("location: /nonprofit/cropimage/?NetworkId=".$newOrg->id);
                    } else {
                        header("location: /nonprofit/addbanner/?NetworkId=".$newOrg->id."&BannerMediaId=$MediaId&width=$width&height=$height&getstarted=yes");
                    }
                } else {
                    header("location: /".$newOrg->urlName);
                }

            } else if(isset($_REQUEST['customrequest']) && $_REQUEST['customrequest'] == 'true') {
                header("location: /getstarted/custom-request");
            }
        }
    }


    public function assignAction() {
        Zend_Registry::get('logger')->info("DELETE::[GetStarted::assign]");
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $Events = new Brigade_Db_Table_Events();
        $Brigades = new Brigade_Db_Table_Brigades();
        $this->view->groups = $Groups->listOrgGroups($_SESSION['assignToOrg']);
        if ($parameters['list'] == 'campaign' || $parameters['list'] == 'activity') {
            $this->view->data = $Brigades->loadInfo1(isset($_SESSION['assignCampaign']) ? $_SESSION['assignCampaign'] : $_SESSION['assignActivity']);
        } else {
            $this->view->eventInfo = $Events->loadInfo($_SESSION['assignEvent']);
        }
        $this->view->list = $parameters['list'];
        if ($_POST) {
            $groupInfo = $Groups->loadInfo1($_POST['GroupId']);
            if (isset($_POST['ProjectId'])) {
                $Brigades->editProject(isset($_SESSION['assignCampaign']) ? $_SESSION['assignCampaign'] : $_SESSION['assignActivity'], array(
                    'NetworkId' => $groupInfo['NetworkId'],
                    'ProgramId' => $groupInfo['ProgramId'],
                    'GroupId' => $groupInfo['GroupId'],
                    'UserId' => ''
                ));
                // delete sessions
                if (isset($_SESSION['assignCampaign'])) {
                    unset($_SESSION['assignCampaign']);
                }
                if (isset($_SESSION['assignActivity'])) {
                    unset($_SESSION['assignActivity']);
                }
                if (isset($_SESSION['assignToOrg'])) {
                    unset($_SESSION['assignToOrg']);
                }
                $projInfo = $Brigades->loadInfo1($_POST['ProjectId']);
                header("location: /".$projInfo['pURLName']);
            } else if (isset($_POST['EventId'])) {
                $Events->updateEvent($_POST['EventId'], array('SiteId' => $_POST['GroupId'], 'UserId' => ''));
                header("location: /".$groupInfo['URLName']."/events?EventId=".$_POST['EventId']);
                if (isset($_SESSION['assignEvent'])) {
                    unset($_SESSION['assignEvent']);
                }
            }
        }
    }
}
