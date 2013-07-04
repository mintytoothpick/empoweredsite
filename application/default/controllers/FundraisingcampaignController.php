<?php
/**
 * FundraisingcampaignController - The "fundraising campaign" controller class
 *
 * @author
 * @version
 */
require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Util/ImageCrop.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/Events.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/LookupTableHistory.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/Paypal.php';
require_once 'Brigade/Db/Table/Photo.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/FundraisingSuggestedDonations.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/Survey.php';
require_once 'Paypal/Paypal.php';
require_once 'Brigade/Db/Table/Countries.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/GroupSurveys.php';
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';
require_once 'Brigade/Lib/Validate/DbUnique.php';
require_once 'BaseController.php';

require_once 'Project.php';
require_once 'Salesforce.php';

class FundraisingcampaignController extends BaseController {
    protected $_http;
    protected $merchantID;
    protected $merchantkey;
    protected $currency = "USD";
    protected $server_type = ''; // change this to anything other than 'sandbox' to go live

    public function init() {
      parent::init();

      if (isset($_SESSION['UserId'])) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        if (isset($parameters['ProjectId'])) {
            $FC = new Brigade_Db_Table_Brigades();
            $Projects = $FC->loadInfo($parameters['ProjectId']);
            $GroupId = $Projects['GroupId'];
        } else if (isset($parameters['GroupId'])) {
            $GroupId = $parameters['GroupId'];
        }

        if (isset($parameters['ProjectId'])) {
          $role = $UserRoles->getUserRole($_SESSION['UserId']);
          $hasAccess = $UserRoles->UserHasAccess($parameters['ProjectId'], $_SESSION['UserId'], 'brigade');
          if (($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
              $this->view->isAdmin = true;
              $this->view->toggleAdminView = $role['isToggleAdminView'];
              $this->view->UserRoleId = $role['UserRoleId'];
          }
          $Brigades = new Brigade_Db_Table_Brigades();
          $brigadeInfo = $Brigades->loadInfo($parameters['ProjectId']);
          if(isset($brigadeInfo['NetworkId'])) {
            $hasNetworkAccess = $UserRoles->hasAccessOnNetwork($brigadeInfo['NetworkId'], $_SESSION['UserId']);
            if($hasNetworkAccess) {
              $this->view->isNetworkAdmin = true;
            }
          }
        } else if (isset($parameters['GroupId'])) {
          $role = $UserRoles->getUserRole($_SESSION['UserId']);
          $hasAccess = $UserRoles->UserHasAccess($GroupId, $_SESSION['UserId'], 'group');
          if (($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
            $this->view->isAdmin = true;
            $this->view->toggleAdminView = $role['isToggleAdminView'];
            $this->view->UserRoleId = $role['UserRoleId'];
          }
          $Groups = new Brigade_Db_Table_Groups();
          $groupInfo = $Groups->loadInfo($parameters['GroupId']);
          if(isset($groupInfo['NetworkId'])) {
            $hasNetworkAccess = $UserRoles->hasAccessOnNetwork($groupInfo['NetworkId'], $_SESSION['UserId']);
            if($hasNetworkAccess) {
              $this->view->isNetworkAdmin = true;
            }
          }
        } else if (isset($parameters['NetworkId'])) {
          $role = $UserRoles->getUserRole($_SESSION['UserId']);
          $hasAccess = $UserRoles->UserHasAccess($parameters['NetworkId'], $_SESSION['UserId'], 'nonprofit');
          if (($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
              $this->view->isAdmin = true;
              $this->view->toggleAdminView = $role['isToggleAdminView'];
              $this->view->UserRoleId = $role['UserRoleId'];
              $this->view->isNetworkAdmin = true;
          }
        }
      }
    }

    public function loadgroupsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Groups = new Brigade_Db_Table_Groups();
        $parameters = $this->_getAllParams();
        $list = '';
        if (isset($parameters['ProgramId']) && $parameters['ProgramId'] != '') {
            $groups = $Groups->listByProgram($parameters['ProgramId']);
            if(count($groups) > 0) {
                $list .= '<select name="GroupId" id="GroupId" style="float:left;">';
                foreach($groups as $group) {
                    $list .= '<option value="'.$group['GroupId'].'">'.stripslashes($group['GroupName']).'</option>';
                }
                $list .= '</select><a id="CreateGroupLink" href="javascript:;" onclick="$(\'#GroupId\').remove(); $(\'#GroupName\').show(); $(\'#CreateGroupLink\').hide();" style="margin-left:10px;display:block;float:left;"> or Create a New Group</a>';
            } else {
                $list .= '<div id="CreateProgramExplanation" style="border:1px solid #dcbd00; background-color:#fff7c8; padding:10px; margin-bottom:10px;">You must create a group to associate this volunteer activity with.</div>';
            }
            $list .= '<input type="text" id="GroupName" name="GroupName" value="New Chapter Name" class="input" onfocus="this.value=\'\'; $(\'#GroupName\').css(\'color\', \'#000\');" style="color:#AAA;';
            if(count($groups) > 0) {
                $list .= 'display:none;';
            }
            $list .= '" />';
        } else {
            $list .= '<a id="CreateGroupLink" href="javascript:;" onclick="$(\'#GroupId\').hide(); $(\'#GroupName\').show(); $(\'#CreateGroupLink\').hide();" style="margin-left:10px;display:block;float:left;"> or Create a New Group</a>';
            $list .= '<input type="text" id="GroupName" name="GroupName" value="New Chapter Name" class="input" onfocus="this.value=\'\'; $(\'#GroupName\').css(\'color\', \'#000\');" style="color:#AAA; display:none;" />';
        }
        echo $list;
    }

    public function createAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        //$this->_helper->layout->disableLayout();
        $parameters = $this->_getAllParams();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $Groups = new Brigade_Db_Table_Groups();
        $Programs = new Brigade_Db_Table_Programs();
        $Organizations = new Brigade_Db_Table_Organizations();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        if(isset($parameters['GroupId'])) {
            $this->view->level = 'group';

            $group = Group::get($parameters['GroupId']);
            if (!$group->organization->hasCampaigns) {
                $this->_helper->redirector('error', '');
            }

            $this->view->googleId  = $group->googleId;
            $this->view->paypalId  = $group->paypalId;
            $this->view->bluePayId = $group->bluePayId;

            $this->view->breadcrumb = $this->view->breadcrumbHelper($group,
                                        'Create Fundraising Campaign');
            $this->view->group        = $group;
            $this->view->organization = $group->organization;

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');

        } else if(isset($parameters['ProgramId']) || isset($parameters['NetworkId'])) {
            $this->view->level = 'organization';

            if(isset($parameters['ProgramId'])) {
                $program      = Program::get($parameters['ProgramId']);
                $organization = $program->organization;
            } else {
                $organization = Organization::get($parameters['NetworkId']);
            }
            if (!$organization->hasCampaigns) {
                $this->_helper->redirector('error', '');
            }
            $this->view->googleId  = $organization->googleId;
            $this->view->paypalId  = $organization->paypalId;
            $this->view->bluePayId = $organization->bluePayId;

            $this->view->programs = $organization->programs;
            if(isset($_REQUEST['pid']) && $_REQUEST['pid'] != '') {
                $this->view->groups = $Groups->simpleListByProgram($_REQUEST['pid']);
            } else if($organization->hasGroups && !$organization->hasPrograms) {
                $this->view->groups = $Groups->getNetworkGroups($organization->id, 0);
            }

            $Media = new Brigade_Db_Table_Media();
            $this->view->siteBanner = false;
            if (!empty($organization->bannerMediaId)) {
                $siteBanner = $Media->getSiteMediaById($organization->bannerMediaId);
                $this->view->siteBanner = $siteBanner;
                $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
            } else {
                $siteMedia = $Media->getSiteMediaById($organization->logoMediaId);
            }

            $this->view->breadcrumb   = $this->view->breadcrumbHelper(
                                        (isset($program)) ? $program : $organization,
                                        'Create Fundraising Campaign');
            $this->view->organization = $organization;

            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/tabs.phtml');

        }

        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');

        if ($_POST) {
            extract($_POST);
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
            if (isset($NetworkId)) {
                $orgInfo = $Organizations->loadInfo($NetworkId, false);
                // if the org has no programs yet, create it
                if ($orgInfo['hasPrograms'] && isset($ProgramName) && $ProgramName != 'New Program Name') {
                    // create the URLName
                    $progURLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $ProgramName);
                    // replace other special chars with accents
                    $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                    $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                    $progURLName = str_replace($other_special_chars, $char_replacement, $progURLName);

                    $Taken = $LookupTable->isSiteNameExists($progURLName);
                    $counter = 1;
                    while($Taken) {
                        $NewURLName = "$progURLName-$counter";
                        $counter++;
                        $Taken = $LookupTable->isSiteNameExists($NewURLName);
                    }
                    if($counter > 1) {
                        $progURLName = $NewURLName;
                    }
                    $Programs = new Brigade_Db_Table_Programs();
                    $ProgramId = $Programs->addProgram(array(
                        'ProgramName' => $ProgramName,
                        'Description' => $orgInfo['AboutUs'],
                        'URLName' => $progURLName,
                        'NetworkId' => $orgInfo['NetworkId'],
                    ));

                    // add record on the lookup_table
                    $LookupTable->addSiteURL(array(
                        'SiteName' => $progURLName,
                        'SiteId' => $ProgramId,
                        'Controller' => 'program',
                        'FieldId' => 'ProgramId'
                    ));

                    // add default administrator for this program
                    $Users = new Brigade_Db_Table_Users();
                    $userInfo = $Users->loadInfo($_SESSION['UserId']);
                    $UserRole = new Brigade_Db_Table_UserRoles();
                    $UserRoleId = $UserRole->addUserRole(array(
                        'UserId' => $userInfo['UserId'],
                        'RoleId' => 'ADMIN',
                        'SiteId' => $ProgramId
                    ));

                    // save program contact info
                    $ContactInfo = new Brigade_Db_Table_ContactInformation();
                    $orgcontactinfo = $ContactInfo->getContactInfo($NetworkId);
                    $ContactId = $ContactInfo->addContactInfo(array(
                        'WebAddress' => $orgcontactinfo['WebAddress'],
                        'SiteId' => $ProgramId
                    ));

                    // log the site activity
                    $SiteActivities = new Brigade_Db_Table_SiteActivities();
                    $SiteActivities->addSiteActivity(array(
                        'SiteId' => $orgInfo['NetworkId'],
                        'ActivityType' => 'Program Added Updated',
                        'CreatedBy' => $_SESSION['UserId'],
                        'ActivityDate' => date('Y-m-d H:i:s'),
                        'Details' => $ProgramId
                    ));
                }
                $ProgramId = isset($ProgramId) ? $ProgramId : '';
                // if the org has no groups yet, create it
                if ($orgInfo['hasGroups'] && isset($GroupName) && $GroupName != 'New Chapter Name') {
                    // create the URLName
                    $groupURLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $GroupName);
                    // replace other special chars with accents
                    $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                    $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                    $groupURLName = str_replace($other_special_chars, $char_replacement, $groupURLName);

                    $Taken = $LookupTable->isSiteNameExists($groupURLName);
                    $counter = 1;
                    while($Taken) {
                        $NewURLName = "$groupURLName-$counter";
                        $counter++;
                        $Taken = $LookupTable->isSiteNameExists($NewURLName);
                    }
                    if($counter > 1) {
                        $groupURLName = $NewURLName;
                    }
                    $orgInfo = $Organizations->loadInfo($NetworkId, false);
                    $GoogleCheckoutAccountId = $orgInfo['GoogleCheckoutAccountId'];
                    $PaypalAccountId = $orgInfo['PaypalAccountId'];
                    $BluePayAccountId = !empty($orgInfo['BluePayAccountId']) ? $orgInfo['BluePayAccountId'] : 0 ;
                    if(($GoogleCheckoutAccountId == 1 || $BluePayAccountId == 1)
                        || $GoogleCheckoutAccountId == 2 || $GoogleCheckoutAccountId == 3
                    ) {
                        $isNonProfit = 1;
                    } else {
                        $isNonProfit = 0;
                    }
                    if($GoogleCheckoutAccountId == 2) {
                        $group_currency = '&#163;';
                    } else {
                        $group_currency = '$';
                    }

                    // save group info first
                    $Groups = new Brigade_Db_Table_Groups();
                    $GroupId = $Groups->addGroup(array(
                        'GroupName' => $GroupName,
                        'Description' => $orgInfo['AboutUs'],
                        'URLName' => $groupURLName,
                        'isOpen' => isset($_POST['isOpen']) ? 1 : 0,
                        'GoogleCheckoutAccountId' => $GoogleCheckoutAccountId,
                        'PaypalAccountId' => $PaypalAccountId,
                        'BluePayAccountId' => $BluePayAccountId,
                        'isNonProfit' => $isNonProfit,
                        'Currency' => $group_currency,
                        'ProgramId' => $ProgramId,
                        'NetworkId' => $orgInfo['NetworkId'],
                    ));

                    // add record on the lookup_table
                    $LookupTable->addSiteURL(array(
                        'SiteName' => $groupURLName,
                        'SiteId' => $GroupId,
                        'Controller' => 'group',
                        'FieldId' => 'GroupId'
                    ));

                    // add default administrator for this group
                    $Users = new Brigade_Db_Table_Users();
                    $userInfo = $Users->loadInfo($_SESSION['UserId']);
                    $UserRole = new Brigade_Db_Table_UserRoles();
                    $UserRoleId = $UserRole->addUserRole(array(
                        'UserId' => $userInfo['UserId'],
                        'RoleId' => 'ADMIN',
                        'SiteId' => $GroupId
                    ));

                    // save group contact info
                    $ContactInfo = new Brigade_Db_Table_ContactInformation();
                    $orgcontactinfo = $ContactInfo->getContactInfo($NetworkId);
                    $ContactId = $ContactInfo->addContactInfo(array(
                        'Email' => $orgcontactinfo['Email'],
                        'WebAddress' => $orgcontactinfo['WebAddress'],
                        'CreatedBy' => $_SESSION['UserId'],
                        'CreatedOn' => date('Y-m-d H:i:s'),
                        'ModifiedBy' => $_SESSION['UserId'],
                        'ModifiedOn' => date('Y-m-d H:i:s'),
                        'SiteId' => $GroupId
                    ));

                    // log the site activity
                    $SiteActivities = new Brigade_Db_Table_SiteActivities();
                    $SiteActivities->addSiteActivity(array(
                        'SiteId' => $orgInfo['NetworkId'],
                        'ActivityType' => 'Group Added',
                        'CreatedBy' => $_SESSION['UserId'],
                        'ActivityDate' => date('Y-m-d H:i:s'),
                        'Details' => $GroupId
                    ));
                }
            } else {
                $progOrg = $Groups->loadProgOrg($GroupId);
                $ProgramId = $progOrg['hasPrograms'] == 1 ? $progOrg['ProgramId'] : '';
                $NetworkId = $progOrg['NetworkId'];
            }
            // load group info and populate the GC, PP and Currency fields
            if ($this->view->level == "organization") {
                $siteInfo = $Organizations->loadInfo($NetworkId, false);
            } else {
                $siteInfo = $Groups->loadInfo1($GroupId);
            }
            $newCampaign = array(
                'GroupId' => isset($GroupId) ? $GroupId : '',
                'ProgramId' => $ProgramId,
                'NetworkId' => $NetworkId,
                'Name' => $Name,
                'Description' => $Description,
                'DonationGoal' => $DonationGoal,
                'VolunteerGoal' => $VolunteerGoal,
                'isRecurring' => 0,
                'EndDate' => date('Y-m-d H:i:s', strtotime($EndDate)),
                'URLName' => $URLName,
                'Type' => 1,
                'GoogleCheckoutAccountId' => !empty($siteInfo['GoogleCheckoutAccountId']) ? $siteInfo['GoogleCheckoutAccountId'] : 0,
                'PaypalAccountId' => !empty($siteInfo['PaypalAccountId']) ? $siteInfo['PaypalAccountId'] : 0,
                'BluePayAccountId' => !empty($siteInfo['BluePayAccountId']) ? $siteInfo['BluePayAccountId'] : 0,
                'Currency' => !empty($siteInfo['Currency']) ? $siteInfo['Currency'] : '$',
                'PercentageFee' => isset($_POST['PercentageFee']) ? $_POST['PercentageFee'] : (!empty($siteInfo['PercentageFee']) ? $siteInfo['PercentageFee'] : 0),
                'allowPercentageFee' => isset($_POST['allowPercentageFee']) ? $_POST['allowPercentageFee'] : (!empty($siteInfo['allowPercentageFee']) ? $siteInfo['allowPercentageFee'] : 'no')
            );
            $ProjectId = $Projects->addProject($newCampaign);
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
                    'SystemMediaName' => "$URLName-logo.jpg",
                    'UploadedMediaName' => $filename,
                ));
                // save site media
                $SiteMedia = new Brigade_Db_Table_MediaSite();
                $SiteMedia->addMediaSite(array(
                    'MediaId' => $MediaId,
                    'SiteID' => $ProjectId
                ));
            }
            if ($MediaSize > 0) {
                header("location: /fundraisingcampaign/cropimage/?ProjectId=$ProjectId&MediaId=$MediaId&from=create_page");
            } else {
                $this->view->message = "Fundraising Campaign \"$Name\" has been created successfully.";
                header("location: /$URLName/add-fundraisers?newcampaign=yes");
            }
        }
    }

    public function cropimageAction() {
        $parameters = $this->_getAllParams();
        $Projects = new Brigade_Db_Table_Brigades();
        $this->view->ProjectId = $parameters['ProjectId'];
        $this->view->MediaId = isset($parameters['MediaId']) ? $parameters['MediaId'] : "";
        $this->view->from = isset($parameters['from']) ? $parameters['from'] : "";

        if(isset($parameters['newcampaign'])) {
            $this->view->newcampaign = true;
            $campaignType = $parameters['newcampaign'];
        }

        if ($_POST) {
            $ImageCrop = new Brigade_Util_ImageCrop();
            $campaignInfo = $Projects->loadInfoBasic($_POST['ProjectId']);
            $this->view->image_preview = $media_name = $campaignInfo['URLName']."-logo";
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_".$this->view->ProjectId.".jpg";
            $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Media/$media_name.jpg";
            $bigger_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Media/full/$media_name.jpg";

            $x = $_POST["x"];
            $y = $_POST["y"];
            $width = $_POST["w"];
            $height = $_POST["h"];

            if ($width > 0 && $height > 0) {
                // get the current selected box width & height
                $uploaded = $ImageCrop->resizeThumbnailImage($bigger_image_location, $temp_image_location, $width, $height, $x, $y, 0, 'jpg', true);
                if ($uploaded === false) {
                    $this->view->error = true;
                }
            }

            if (!$_POST['preview']) {
                // delete the temp file
                if (file_exists($temp_image_location)) {
                    unlink($temp_image_location);
                }
                if ($_POST['from'] == 'create_page') {
                    header("location: /".$campaignInfo['pURLName']."/add-fundraisers?newcampaign=$campaignType");
                } else {
                    header("location: /".$campaignInfo['pURLName']);
                }
            } else {
                $this->view->preview_image = 1;
            }
        }
    }

    public function shareAction() {
        $parameters = $this->_getAllParams();
        $Projects = new Brigade_Db_Table_Brigades();

        if(isset($parameters['newcampaign'])) {
            $this->view->newcampaign = true;
        }

        $this->view->campaignInfo = $Projects->loadInfoBasic($parameters['ProjectId']);
        if ($_POST && isset($_POST['ProjectId'])) {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            if (!$this->view->campaignInfo['hasSharedSocialNetworks']) {
                $Projects->editProject($_POST['ProjectId'], array('hasSharedSocialNetworks' => 1));
            }
        } else {
            $project  =  $this->view->project   =  Project::get($parameters['ProjectId']);

            if(!empty($project->groupId)) {
                $group  =  $this->view->group   = $project->group;
                $this->view->level              = 'group';
                $this->view->organization       = $group->organization;
                //breadcrumb
                $this->view->breadcrumb         =  array();
                $this->view->breadcrumb[]       =  '<a href="/'.$group->organization->urlName.'">'.$group->organization->name.'</a>';
                if (!empty($group->programId)) {
                    $this->view->breadcrumb[]   =  '<a href="/'.$group->program->urlName.'">'.$group->program->name.'</a>';
                }
                $this->view->breadcrumb[]       =  '<a href="/'.$group->urlName.'">'.$group->name.'</a>';
                $this->view->breadcrumb[]       =  '<a href="/'.$project->urlName.'">'.$project->name.'</a>';

            } else if(!empty($project->organizationId)) {
                $this->view->organization  =  $project->organization;

                $this->view->level = 'organization';

                //breadcrumb
                $this->view->breadcrumb         =  array();
                $this->view->breadcrumb[]       =  '<a href="/'.$project->organization->urlName.'">'.$project->organization->name.'</a>';
                $this->view->breadcrumb[]       =  '<a href="/'.$project->urlName.'">'.$project->name.'</a>';
                $this->view->breadcrumb[]       =  'Add Volunteers';
            }
            $this->view->render('project/header.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/tabs.phtml');
            $this->view->render('nonprofit/footer.phtml');
            $this->_helper->layout->setLayout('newlayout');
        }
    }

    public function editAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if ($_POST) {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
        }
        $parameters = $this->_getAllParams();
        $Media = new Brigade_Db_Table_Media();
        $Projects = new Brigade_Db_Table_Brigades();
        $SuggestedDonations = new Brigade_Db_Table_FundraisingSuggestedDonations();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
        $project = Project::get($parameters['ProjectId']);
        $this->view->suggested_donations = $SuggestedDonations->getSuggestedDonations($project->id);
        $this->view->campaign_photo = $Media->getSiteMediaBySiteId($project->id);
        if($_POST) {
            $emptyURL = false;
            if (!empty($_POST['URLName']) && $project->urlName != $_POST['URLName']) {
                $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), trim($_POST['URLName']));
                // replace other special chars with accents
                $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                $URLName = str_replace($other_special_chars, $char_replacement, $URLName);
            } else if (empty($_POST['URLName'])) {
                $emptyURL = true;
            }
        }
        $url_exists = !empty($URLName) ? $LookupTable->isSiteNameExists($URLName, $_POST['ProjectId']) : false;
        if($_POST && $project->urlName == $URLName) {
            $url_exists = 0;
        }
        if ($_POST && !$url_exists && !$emptyURL) {
            extract($_POST);
            $Projects = new Brigade_Db_Table_Brigades();
            $data = array(
                'Name' => $Name,
                'URLName' => $URLName,
                'Description' => $Description,
                'DonationGoal' => $DonationGoal,
                'VolunteerGoal' => $VolunteerGoal,
                'isRecurring' => 0,
                'EndDate' => date('Y-m-d H:i:s', strtotime($EndDate)),
            );
            $Projects->editProject($ProjectId, $data);

            //update the lookup_table
            $LookupTable->updateSiteName($ProjectId, array('SiteName'=>$URLName));

            // if URLName has been changed, add the old URLName in lookup table history
            if ($this->view->data['pURLName'] != $URLName) {
                $LookupTableHistory = new Brigade_Db_Table_LookupTableHistory();
                $LookupTableHistory->addSiteURL(array(
                    'SiteName' => $this->view->data['pURLName'],
                    'SiteId' => $ProjectId,
                    'Controller' => 'project',
                    'FieldId' => 'ProjectId'
                ));
                $this->view->data['pURLName'] = $URLName;
            }

            // update suggested amounts
            $SuggestedDonations = new Brigade_Db_Table_FundraisingSuggestedDonations();
            $SuggestedDonations->deleteCampaignSuggestedDonations($ProjectId);
            if(isset($suggestedamount)) {
                for ($ctr = 0; $ctr < count($suggestedamount); $ctr++) {
                    $suggested_donations = array('ProjectId' => $ProjectId, 'Amount' => $suggestedamount[$ctr], 'Description' => $suggestedamountdesc[$ctr]);
                    $SuggestedDonations->addSuggestedDonation($suggested_donations);
                }
            }
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
                $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_campaign_pic.jpg";
                // Check if file size does not exceed 2MB
                move_uploaded_file($tmpfile, $temp_image_location);
                $width = $ImageCrop->getWidth($temp_image_location);
                $height = $ImageCrop->getHeight($temp_image_location);
                if ($width > $ImageCrop->max_width) {
                    $scale = $ImageCrop->max_width/$width;
                } else {
                    $scale = 1;
                }
                $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale);
                // save media
                $Media = new Brigade_Db_Table_Media();
                if (isset($MediaId)) {
                    $Media->editMedia($MediaId, array(
                        'MediaSize' => $MediaSize,
                        'UploadedMediaName' => $filename,
                        'ModifiedBy' => $_SESSION['UserId'],
                        'ModifiedOn' => date('Y-m-d H:i:s'),
                    ));
                } else {
                    $MediaId = $Media->addMediaGallery(array(
                        'MediaSize' => $MediaSize,
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
            }
            if ($MediaSize > 0) {
                header("location: /fundraisingcampaign/cropimage/?ProjectId=$ProjectId&MediaId=$MediaId&from=edit_page");
            } else {
                $this->view->message = "Fundraising Campaign \"$Name\" has been successfully updated.";
                $pURLName = $this->view->data['pURLName'];
                header('location: /'.(isset($URLName) ? $URLName : $this->view->data['URLName']));
            }
        } else if ($url_exists) {
            $this->view->message = "URL Name already exists, please specify another.";
        } else if ($emptyURL) {
            $this->view->message = "Please specify the campaign URL Name";
        }
    }

    public function deleteAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Projects = new Brigade_Db_Table_Brigades();
        $this->view->data = $Projects->loadInfo($parameters['ProjectId']);
        $this->view->GroupId = $this->view->data['GroupId'];
        if ($_POST) {
            $ProjectId = $_POST['ProjectId'];
            $Projects->deleteProject($ProjectId);
            // redirect to parent group
            if(!isset($_POST['reload'])) {
                header('location: /'.$this->view->data['gURLName']);
            }
        }
    }

    public function donateAction() {
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $Groups = new Brigade_Db_Table_Groups();
        $Programs       =  new Brigade_Db_Table_Programs();
        $Organizations  =  new Brigade_Db_Table_Organizations();
        $Projects = new Brigade_Db_Table_Brigades();
        $SuggestedDonations = new Brigade_Db_Table_FundraisingSuggestedDonations();

        $parameters = $this->_getAllParams();
        if (isset($parameters['ProjectId'])) {
            $ProjectId = $parameters['ProjectId'];

            $project  =  $this->view->project  =  Project::get($parameters['ProjectId']);
            if(!empty($project->organizationId)) {
                $organization  =  $this->view->organization  =  $project->organization;

                $Media = new Brigade_Db_Table_Media();
                $this->view->siteBanner = false;
                if (!empty($organization->bannerMediaId)) {
                    $siteBanner = $Media->getSiteMediaById($organization->bannerMediaId);
                    $this->view->siteBanner = $siteBanner;
                    $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
                }

                if(!empty($organization->nonProfitId)) {
                    $this->view->nonProfitId = $organization->nonProfitId;
                    $this->view->nonProfit = $organization->name;
                }

            }
            if(!empty($project->programId)) {
                $program = $project->program;
                if ($program->canSupport($this->sessionUser)) {
                    $path = realpath(dirname(__FILE__) . '/../../../')."/public/Media/full/";
                    if (file_exists($path.$program->urlName.'-supporterImg.jpg')) {
                        $this->view->supportersImg = $program->urlName.'-supporterImg.jpg';
                    } else {
                        $this->view->supportersImg = false;
                    }
                    $this->view->render('program/become_supporter.phtml');
                }
            }
            if(!empty($project->groupId)) {
                $group = $this->view->group = $project->group;

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
            $this->view->breadcrumb = $this->view->breadcrumbHelper($project, 'Donate');
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/footer.phtml');
            $this->_helper->layout->setLayout('newlayout');

            if ($project->googleId == 0 && $project->paypalId == 0 && $project->bluePayId == 0
                && !empty($project->groupId)
            ) {
                $this->view->error = true;
            }
            $this->view->suggested_donations = $SuggestedDonations->getSuggestedDonations($project->id);

            if ((!empty($project->organizationId) && $project->organization->bluePayId > 0)
                || ($project->bluePayId && $project->bluePayId > 0)
                && BluePay::isActive
            ) {
                $enableEcheck = false;
                if (strtotime($project->startDate." -2 weeks") > time()) {
                    $enableEcheck = true;
                }
                $this->view->enableEcheck = $enableEcheck;
                $this->view->render('donation/bluepayform_cc.phtml');
                $this->view->render('donation/bluepay.phtml');
            }elseif(!empty($project->googleId)) {
                $GoogleCheckoutAccounts = new Brigade_Db_Table_GoogleCheckoutAccounts();
                $gc_account = $GoogleCheckoutAccounts->loadInfo($project->googleId);

                if ($this->server_type == 'sandbox') {
                    $this->view->merchant_id = '844523113325635';
                } else {
                    $this->view->merchant_id = isset($gc_account['GoogleMerchantID']) ? $gc_account['GoogleMerchantID'] : "";
                }

            } else {
                $Paypal = new Brigade_Db_Table_Paypal();
                $this->view->paypal = $Paypal->loadInfo($project->paypalId);
            }

            if (isset($parameters['UserId'])) {
                $this->view->UserId = $parameters['UserId'];
            }

            if(isset($_SESSION['UserId'])) {
                $Users = new Brigade_Db_Table_Users();
                $this->view->donorsName = $Users->getFullNameById($_SESSION['UserId']);
            }
            $this->view->fundraisers = $Volunteers->getCampaignFundraisers($project->id);
            $Country = new Brigade_Db_Table_Countries();
            $this->view->country_list = $Country->getAllCountries(true);
        }
    }

    public function newdonationAction() {
        if ($_POST) {
            require_once('GoogleCheckout/googlecart.php');
            require_once('GoogleCheckout/googleitem.php');
            require_once('GoogleCheckout/googleshipping.php');
            require_once('GoogleCheckout/googletax.php');
            require_once('GoogleCheckout/googlesubscription.php');
            require_once('GoogleCheckout/googlerequest.php');

            $ProjectId = $_POST['ProjectId'];
            $VolunteerId = $_POST['VolunteerId'];
            $item_name = stripslashes($_POST['item_name_1']);
            $item_description = $_POST['item_description_1'];
            $item_quantity = $_POST['item_quantity_1'];
            $item_price = $_POST['suggested_amount'] == "Other Amount" ? $_POST['other_amount'] : $_POST['suggested_amount'];
            $comments = $_POST['DonationComments'];
            $isAnonymous = isset($_POST['isAnonymous']) ? 1 : 0;
            $recurrence_period = isset($_POST['recurrence_period']) ? $_POST['recurrence_period'] : "";
            $paidFee = false;
            if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'mandatory') {
                $item_price = $item_price * (1 + ($_POST['PercentageFee']/100));
                $paidFee = true;
            } else if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'optional' && isset($_POST['coverTransFee']) && $_POST['coverTransFee']) {
                $item_price = $item_price * (1 + ($_POST['PercentageFee']/100));
                $paidFee = true;
            }

            $Projects = new Brigade_Db_Table_Brigades();
            $projInfo = $Projects->loadInfoBasic($ProjectId);

            // save data to project_donations table
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $ProjectDonationId = $ProjectDonations->addProjectDonation(array(
                'ProjectId' => $ProjectId,
                'GroupId' => $projInfo['GroupId'],
                'ProgramId' => $projInfo['ProgramId'],
                'NetworkId' => $projInfo['NetworkId'],
                'VolunteerId' => $VolunteerId,
                'DonationAmount' => $item_price,
                'DonorUserId' => isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "",
                'DonationComments' => $comments,
                'OrderStatusId' => 0,
                'TransactionSource' => "Google Checkout",
                'CreatedOn' => date('Y-m-d H:i:s'),
                'isAnonymous' => $isAnonymous,
                'PaidFees'     => $paidFee
            ));

            // get organization Google Checkout credentials
            if(!empty($projInfo['GroupId'])) {
                $Groups = new Brigade_Db_Table_Groups();
                $GC_account = $Groups->getGoogleCheckoutAccount($projInfo['GroupId']);
            } else if(!empty($projInfo['NetworkId'])) {
                $Organizations = new Brigade_Db_Table_Organizations();
                $GC_account = $Organizations->getGoogleCheckoutAccount($projInfo['NetworkId']);
            } else if(!empty($projInfo['UserId'])) {
                $Users = new Brigade_Db_Table_Users();
                $GC_account = $Users->getGoogleCheckoutAccount($projInfo['UserId']);
            }
            $merchantID = $GC_account['GoogleMerchantID'];
            $merchantKey = $GC_account['GoogleMerchantKey'];
            $currency = $GC_account['CurrencyType'];

            if ($merchantID == "246462324732671") {
                $type = "sandbox";
            } else {
                $type = "";
            }

            $cart = new GoogleCart($merchantID, $merchantKey, $type, $currency);
            $item = new GoogleItem(
                $item_name, // Item name
                $item_description, // Item description
                $item_quantity, // Quantity
                $item_price // Donation Amount
            );
            $item->SetMerchantItemId($ProjectDonationId); // this will served as the reference ID to the project_donations table [ProjectDonationId]
            $item->SetEmailDigitalDelivery('true');
            if (isset($_POST['isRecurring']) && $_POST['isRecurring'] == "Yes") {
                $subscription_item = new GoogleSubscription("merchant", $recurrence_period, $item_price);
                $item->SetSubscription($subscription_item);
            }
            $cart->AddItem($item);
            // Specify the <edit-cart-url>
            $cart->SetEditCartUrl("http://www.empowered.org/fundraisingcampaign/donate/$ProjectDonationId");
            // Specify the <continue-shoppingcart-url>
            $cart->SetContinueShoppingUrl("http://www.empowered.org/");
            list($status, $error) = $cart->CheckoutServer2Server('');
            // if i reach this point, something was wrong
            echo "An error had ocurred: <br />HTTP Status: " . $status. ":";
            echo "<br />Error message:<br />";
            echo $error;
        }
    }

    public function processpaymentAction() {
        $Projects = new Brigade_Db_Table_Brigades();
        $this->view->ProjectId = $_POST['ProjectId'];
        $campaignInfo = $Projects->loadInfo($_POST['ProjectId']);
        $PaypalAccounts = new Brigade_Db_Table_PaypalAccounts();
        $paypalInfo = $PaypalAccounts->loadInfo($campaignInfo['PaypalAccountId']);
        $Paypal = new Paypal_API(array(
            'API_UserName' => $paypalInfo['ApiUsername'],
            'API_Password' => $paypalInfo['ApiPassword'],
            'API_Signature' => $paypalInfo['ApiSignature']
        ));
        if (isset($_POST) && $_POST['payment-option'] == "Express Checkout") {
            extract($_POST);
            $currencyCodeType = "USD";
            $paymentType = 'Sale'; // Authorization or Sale or Order
            $returnURL = "fundraisingcampaign/confirmpayment?ProjectId=$ProjectId".($_POST['VolunteerId'] != "" ? "&VolunteerId=$VolunteerId" : "");
            $cancelURL = "fundraisingcampaign/cancelpayment?ProjectId=$ProjectId";
            $donationAmount = $_POST['suggested_amount'] == "Other Amount" ? $_POST['other_amount'] : $_POST['suggested_amount'];
            $resArray = $Paypal->CallMarkExpressCheckout($donationAmount, $currencyCodeType, $paymentType, $returnURL, $cancelURL);
            $ack = strtoupper($resArray["ACK"]);
            $billing_periods = array('WEEKLY' => 'Week', 'SEMI_MONTHLY' => 'SemiMonth', 'MONTHLY' => 'Month', 'YEARLY' => 'Year');
            if ($ack == "SUCCESS") {
                $_SESSION['donationAmount'] = $donationAmount;
                $_SESSION['DonationComments'] = $DonationComments;
                if (isset($_POST['isAnonymous'])) {
                    $_SESSION['isAnonymous'] = $isAnonymous;
                }
                $token = urldecode($resArray["TOKEN"]);
                if (isset($_SESSION['isRecurring']) && $_SESSION['isRecurring']) {
                    $startDate = urlencode(date('Y-m-d')."T0:0:0");
                    $billingPeriod = urlencode($billing_periods[$_POST['recurrence_period']]);
                    $billingFreq = $_POST['recurrence_period'] == "SEMI_MONTHLY" ? 1 : 4;
                    $desc = urlencode($_POST['item_description']);
                    $nvpStr = "&TOKEN=$token&AMT=$donationAmount&CURRENCYCODE=$currencyCodeType&PROFILESTARTDATE=$startDate";
                    $nvpStr .= "&PROFILESTARTDATE=$startDate&BILLINGPERIOD=$billingPeriod&BILLINGFREQUENCY=$billingFreq&DESC=$desc";
                    $httpParsedResponseAr = $Paypal->PPHttpPost('CreateRecurringPaymentsProfile', $nvpStr);
                }
                /*
                if (isset($_POST['isRecurring']) && $_POST['isRecurring'] == "Yes") {
                    $_SESSION['isRecurring'] = true;
                    $_SESSION['billingPeriod'] = $billing_periods[$_POST['recurrence_period']];
                    $_SESSION['billingFreq'] = $_POST['recurrence_period'] == "SEMI_MONTHLY" ? 1 : 4;
                    $_SESSION['startDate'] = urlencode(date('Y-m-d')."T0:0:0");
                    $_SESSION['desc'] = urlencode($_POST['item_description']);
                }
                 *
                 */
                $_SESSION['reshash'] = $token;
                $Paypal->RedirectToPayPal($token);
            } else {
                //Display a user friendly Error on the page using any of the following error information returned by PayPal
                $this->view->error = true;
                $this->view->error_code = "SetExpressCheckout API call failed.<br>";
                $this->view->error_code = urldecode($resArray["L_LONGMESSAGE0"])."<br>";
                $this->view->error_code = urldecode($resArray["L_SHORTMESSAGE0"])."<br>";
                $this->view->error_code = urldecode($resArray["L_ERRORCODE0"])."<br>";
                //echo urldecode($resArray["L_SEVERITYCODE0"])."<br>";
            }
        } else if (isset($_POST) && $_POST['payment-option'] == "Direct Payment") {
            $currencyCodeType = "USD";
            $paymentType =  urlencode('Sale'); // Authorization or 'Sale'
            $firstName = urlencode($_POST['firstName']);
            $lastName = urlencode($_POST['lastName']);
            $creditCardType = urlencode($_POST['creditCardType']);
            $creditCardNumber = urlencode($_POST['creditCardNumber']);
            $expDateMonth = $_POST['expDateMonth'];
            $donationAmount = $_POST['suggested_amount'] == "Other Amount" ? $_POST['other_amount'] : $_POST['suggested_amount'];
            $donationComments = stripslashes($_POST['DonationComments']);
            $email = trim($_POST['email']);
            // Month must be padded with leading zero
            $padDateMonth = urlencode(str_pad($expDateMonth, 2, '0', STR_PAD_LEFT));
            $expDateYear  = urlencode($_POST['expDateYear']);
            $cvv2         = urlencode($_POST['cvv2Number']);
            $address      = urlencode($_POST['address']);
            $city         = urlencode($_POST['city']);
            $state        = urlencode($_POST['state']);
            $zipcode      = urlencode($_POST['zipcode']);
            $country      = urlencode($_POST['country']); // US or other valid country code
            // Add request-specific fields to the request string.
            $expDate = "$padDateMonth$expDateYear";
            $nvpStr = "&PAYMENTACTION=$paymentType".
                "&ACCT=$creditCardNumber".
                "&CREDITCARDTYPE=$creditCardType".
                "&EXPDATE=$expDate".
                "&CVV2=$cvv2".
                "&AMT=".urlencode(number_format($donationAmount, 2, '.', '')).
                "&STREET=$address".
                "&ZIP=$zipcode".
                "&COUNTRYCODE=$country".
                "&CITY=$city".
                "&STATE=$state".
                "&FIRSTNAME=$firstName".
                "&LASTNAME=$lastName".
                "&CURRENCYCODE=$currencyCodeType";
            if (isset($_POST['isRecurring']) && $_POST['isRecurring'] == "Yes") {
                $billing_periods = array('WEEKLY' => 'Week', 'SEMI_MONTHLY' => 'SemiMonth', 'MONTHLY' => 'Month', 'YEARLY' => 'Year');
                $startDate = urlencode(date('Y-m-d')."T0:0:0");
                $billingPeriod = urlencode($billing_periods[$_POST['recurrence_period']]); // or "Day", "Week", "SemiMonth", "Year"
                $billingFreq = urlencode($_POST['recurrence_period'] == "SEMI_MONTHLY" ? 1 : 4); // combination of this and billingPeriod must be at most a year
                $desc = urlencode($_POST['item_description']);
                $nvpStr .= "&PROFILESTARTDATE=$startDate&BILLINGPERIOD=$billingPeriod&BILLINGFREQUENCY=$billingFreq&DESC=$desc";
                $httpParsedResponseAr = $Paypal->PPHttpPost('CreateRecurringPaymentsProfile', $nvpStr);
            }
            $httpParsedResponseAr = $Paypal->DirectPayment("DoDirectPayment", $nvpStr);
            /*
            echo "<pre>";
            print_r($httpParsedResponseAr);
            echo "</pre>";
             */
            if ("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
                $this->view->error = false;
                if ($_POST['VolunteerId'] != '') { // VolunteerId is actually the UserId
                    $Users = new Brigade_Db_Table_Users();
                    $userInfo = $Users->loadInfo($_POST['VolunteerId']);
                    $this->view->message = "You have successfuly made a donation on behalf of <a href='/".stripslashes($userInfo['URLName'])."'>".stripslashes($userInfo['FullName'])."</a>";
                    $shareLink = "http://www.empowered.org/".$userInfo['URLName']."?utm_campaign=DonationReceipt&utm_medium=Email&utm_source=UserAction";
                    $recipient = stripslashes($userInfo['FullName']);
                } else {
                    $Projectss = new Brigade_Db_Table_Brigades();
                    $campaignInfo = $Projectss->loadInfo($_POST['ProjectId']);
                    $this->view->message = "You have successfuly made a general donation to <a href='/".stripslashes($campaignInfo['pURLName'])."'>".stripslashes($campaignInfo['Name'])."</a>";
                    $shareLink = "http://www.empowered.org/".$campaignInfo['pURLName']."?utm_campaign=DonationReceipt&utm_medium=Email&utm_source=UserAction";
                    $recipient = stripslashes($campaignInfo['Name']);
                }
                $transactionId = urldecode($httpParsedResponseAr['TRANSACTIONID']);
                // save data to project_donations table
                $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
                $ProjectDonationId = $ProjectDonations->addProjectDonation(array(
                    'ProjectId' => $_POST['ProjectId'],
                    'VolunteerId' => $_POST['VolunteerId'],
                    'DonationAmount' => $donationAmount,
                    'DonorUserId' => isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "",
                    'DonationComments' => $donationComments,
                    'OrderStatusId' => 2,
                    'TransactionId' => $transactionId,
                    'TransactionSource' => "Paypal - Direct Payment",
                    'CreatedOn' => date('Y-m-d H:i:s'),
                    'isAnonymous' => isset($_POST['isAnonymous']) ? 1 : 0,
                    'SupporterEmail' => $email,
                    'SupporterName' => $_POST['firstName']." ".$_POST['lastName']
                ));
                // send donation receipt
                $NPMessage = $recipient;

                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$DONATION_RECEIPT,
                                   array($email, $_POST['firstName'],
                                         $recipient, $recipient, $donationAmount,
                                         "$",$transactionId, $shareLink, $NPMessage));

            } else {
                $this->view->error = true;
                $this->view->error_code = urldecode($httpParsedResponseAr["L_ERRORCODE0"])."<br>";
                $this->view->short_msg = urldecode($httpParsedResponseAr["L_SHORTMESSAGE0"])."<br>";
                $this->view->long_msg = urldecode($httpParsedResponseAr["L_LONGMESSAGE0"])."<br>";
            }
        }
    }

    public function confirmpaymentAction() {
        $Projects = new Brigade_Db_Table_Brigades();
        $campaignInfo = $Projects->loadInfo($_REQUEST['ProjectId']);
        $PaypalAccounts = new Brigade_Db_Table_PaypalAccounts();
        $paypalInfo = $PaypalAccounts->loadInfo($campaignInfo['PaypalAccountId']);
        $Paypal = new Paypal_API(array(
            'API_UserName' => $paypalInfo['ApiUsername'],
            'API_Password' => $paypalInfo['ApiPassword'],
            'API_Signature' => $paypalInfo['ApiSignature']
        ));
        $resArray = $Paypal->ConfirmPayment($_SESSION['donationAmount']);
        $ack = strtoupper($resArray["ACK"]);
        $parameters = $this->_getAllParams();
        $Projects = new Brigade_Db_Table_Brigades();
        $this->view->data = $Projects->loadInfo($parameters['ProjectId']);
        if ($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
            $this->view->error = false;
            if (isset($_REQUEST['VolunteerId']) && $_REQUEST['VolunteerId'] != '') { // VolunteerId is actually the UserId
                $Users = new Brigade_Db_Table_Users();
                $userInfo = $Users->loadInfo($_REQUEST['VolunteerId']);
                $this->view->message = "You have successfuly made a donation on behalf of <a href='/".stripslashes($userInfo['URLName'])."'>".stripslashes($userInfo['FullName'])."</a>";
            } else {
                $Projectss = new Brigade_Db_Table_Brigades();
                $campaignInfo = $Projectss->loadInfo($_REQUEST['ProjectId']);
                $this->view->message = "You have successfuly made a general donation to <a href='/".stripslashes($campaignInfo['pURLName'])."'>".stripslashes($campaignInfo['Name'])."</a>";
            }
            $transactionId = $resArray["TRANSACTIONID"];
            $paymentStatus = $resArray["PAYMENTSTATUS"];
            // save data to project_donations table
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $ProjectDonationId = $ProjectDonations->addProjectDonation(array(
                'ProjectId' => $_REQUEST['ProjectId'],
                'VolunteerId' => isset($_REQUEST['VolunteerId']) ? $_REQUEST['VolunteerId'] : "",
                'DonationAmount' => $_SESSION['donationAmount'],
                'DonorUserId' => isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "",
                'DonationComments' => $_SESSION['DonationComments'],
                'OrderStatusId' => 2,
                'TransactionId' => $transactionId,
                'TransactionSource' => "Paypal - Express Checkout",
                'CreatedOn' => date('Y-m-d H:i:s'),
                'isAnonymous' => isset($_SESSION['isAnonymous']) ? 1 : 0
            ));
            /*
            if (isset($_SESSION['isRecurring']) && $_SESSION['isRecurring']) {
                $startDate = $_SESSION['startDate'];
                $billingPeriod = urlencode($_SESSION['billingPeriod']);
                $billingFreq = $_SESSION['billingFreq'];
                $desc = $_SESSION['desc'];
                $token = $_SESSION['reshash'];
                $currencyCodeType = "USD";
                $nvpStr = "&TOKEN=$token&AMT=".$_SESSION['donationAmount']."&CURRENCYCODE=$currencyCodeType&PROFILESTARTDATE=$startDate";
                $nvpStr .= "&PROFILESTARTDATE=$startDate&BILLINGPERIOD=$billingPeriod&BILLINGFREQUENCY=$billingFreq&DESC=$desc";
                $httpParsedResponseAr = $Paypal->PPHttpPost('CreateRecurringPaymentsProfile', $nvpStr);
                // unset sessions
                unset($_SESSION['isRecurring']);
                unset($_SESSION['startDate']);
                unset($_SESSION['billingPeriod']);
                unset($_SESSION['billingFreq']);
                unset($_SESSION['desc']);
            }
            */
            if (isset($_SESSION['donationAmount'])) {
                unset($_SESSION['donationAmount']);
            }
            if (isset($_SESSION['DonationComments'])) {
                unset($_SESSION['DonationComments']);
            }
            if (isset($_SESSION['isAnonymous'])) {
                unset($_SESSION['isAnonymous']);
            }
        } else {
            $this->error = true;
            $this->view->error_code = urldecode($resArray["L_ERRORCODE0"])."<br>";
            $this->view->short_msg = urldecode($resArray["L_SHORTMESSAGE0"])."<br>";
            $this->view->long_msg = urldecode($resArray["L_LONGMESSAGE0"])."<br>";
            //$this->view->error_code = urldecode($resArray["L_SEVERITYCODE0"])."<br>";
        }
    }

    public function cancelpaymentAction() {
        $parameters = $this->_getAllParams();
        $Projects = new Brigade_Db_Table_Brigades();
        $this->view->data = $Projects->loadInfo($parameters['ProjectId']);
    }

    public function joinAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $Projects = new Brigade_Db_Table_Brigades();

        if (isset($parameters['ProjectId']) && isset($_SESSION['UserId'])) {
            $ProjectId = $parameters['ProjectId'];
            $UserId = $_SESSION['UserId'];
            $is_signed_up = $Volunteers->isUserSignedUp($ProjectId, $UserId);
            $is_denied    = $Volunteers->isDenied($ProjectId, $UserId);
            $is_deleted   = $Volunteers->isDeleted($ProjectId, $UserId);
            $stoped_user  = false;
            $error_msg    = false;
            if ($is_denied) {
                $error_msg = "You have been denied from this Campaign by an administrator. Please email the chapter's contact if you have any questions.";
            } else if($is_deleted) {
                $error_msg = "You have been deleted from this Campaign by an administrator. Please email the chapter's contact if you have any questions.";
            } else if($is_signed_up) {
                $stoped_user = $Volunteers->stopedVoluteering($ProjectId, $UserId);
                if (!$stoped_user){
                    $error_msg = "You have already signed up with this Campaign.";
                }
            }

            if (!$error_msg) {
                $campaignInfo = $Projects->loadInfo($ProjectId);
                $accept_volunteer = ($campaignInfo["Status"] == 'Open');
                if ($is_signed_up && $stoped_user) {
                        $Volunteers->reSignupVolunteer(
                            $Volunteers->getVolunteerIdByProjectAndUser($ProjectId, $UserId),
                            $accept_volunteer
                        );

                } else if (is_null($Volunteers->getVolunteerIdByProjectAndUser($ProjectId, $UserId))) {
                    $VolunteerId = $Volunteers->signUpVolunteer($UserId, $ProjectId, $accept_volunteer);
                    if ($VolunteerId) {
                        $volunteer = Volunteer::get($VolunteerId);
                    }
                }
                $project   = Project::get($ProjectId);
                $volunteer = $project->getVolunteerByUser($this->sessionUser);
                if ($volunteer) {
                    $this->_salesForceIntegrationVolunteer($volunteer);
                }

                $GroupMembers = new Brigade_Db_Table_GroupMembers();
                if (!$GroupMembers->isMemberExists($campaignInfo['GroupId'], $_SESSION['UserId'])) {
                    $GroupMembers->AddGroupMember(array(
                        'GroupId' => $campaignInfo['GroupId'],
                        'UserId' =>  $_SESSION['UserId']
                    ));
                }

                $userURLName = $Users->getURLNameById($_SESSION['UserId']);
                header("location: /signup/tell-friends?ProjectId=$ProjectId");
                //header("location: /$userURLName/".$campaignInfo['pURLName']);
            } else {
                echo "<script type='text/javascript'>alert('. $error . ')</script>";
                header("location: /".$campaignInfo['pURLName']);
            }
        } else if (isset($parameters['ProjectId']) && !isset($_SESSION['UserId'])) {
            header("location: /profile/login");
        }
    }

    public function fundraiseAction() {
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $Groups = new Brigade_Db_Table_Groups();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Projects = new Brigade_Db_Table_Brigades();
        if (isset($parameters['ProjectId']) && isset($parameters['UserId'])) {
            $UserId = $parameters['UserId'];
            $ProjectId = $parameters['ProjectId'];
            $this->view->data = $Users->findBy($UserId);
            // load fundraising campaign info
            $this->view->campaign = $Projects->loadInfo($ProjectId);
            // load group's organization
            $this->view->organization = $Groups->loadProgOrg($this->view->campaign['GroupId']);
            $this->view->project_donations = $ProjectDonations;
            $this->view->media = new Brigade_Db_Table_Media();
            $this->view->UserId = $UserId;
            $this->view->DonationGoal = $this->view->campaign['DonationGoal'];
            $this->view->ProjectId = $ProjectId;
            $this->view->donationlist = $ProjectDonations->getVolunteerProjectDonations($UserId, $ProjectId);
        }
    }

    public function endcampaignAction() {
        $parameters = $this->_getAllParams();
        if (isset($parameters['VolunteerId'])) {
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $Volunteers->EndCampaign($parameters['VolunteerId']);
        }
    }

    public function managedonationsAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $ProjectId  = $parameters['ProjectId'];

        $ProjectDonations        = new Brigade_Db_Table_ProjectDonations();
        $this->view->sitemedia   = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        $project = Project::get($parameters['ProjectId']);

        if(!empty($project->organizationId)) {
            $organization  =  $this->view->organization  =  $project->organization;

            if(isset($organization->nonProfitId)) {
                $this->view->nonProfitId = $organization->nonProfitId;
                $this->view->nonProfit   = $organization->name;
            }

        }
        if(!empty($project->programId)) {
            $program  = $project->program;
        }
        if(!empty($project->groupId)) {
            $this->view->group = $project->group;
            $this->view->level = "group";

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');

        } else if(!empty($project->organizationId)) {
            $this->view->level = "organization";

            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');
        } else {
            //create header / tabs for personal activities?
            $this->view->header_title = $project->name;
            $this->view->level        = "user";
        }

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($project);
        $this->view->project    = $project;
        $this->view->volunteers = $project->volunteers;
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');

        $this->view->general_donations = $ProjectDonations->getGeneralDonations($project->id);
    }

    public function updategoalAction() {
        if ($_POST) {
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $Volunteers->setDonationGoal($_POST['FundraiserId'], $_POST['NewGoal']);
        }
    }

    public function generatereportAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $Projects = new Brigade_Db_Table_Brigades();
            $donations = $Projects->getDetailDonationReport($_POST['ProjectId']);
            $campaignInfo = $Brigades->loadInfo($_POST['ProjectId']);
            $campaignName = str_replace(' ', '-', $campaignInfo['Name']."-Donation-Report.xls");
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$campaignName");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Volunteer', 'Donation #', 'Donation Amount', 'Donation Date', 'Donor', 'Donor Email', 'Status');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($donations as $donation) {
                if($donation['isAnonymous']) {
                    $donation['SupporterName'] = "Anonymous\t";
                    $donation['SupporterEmail'] = "Anonymous\t";
                }
                if($donation['DonationAmount'] > 0 && $donation['OrderStatusId'] == 2) {
                    $donation['Status'] = "Processed";
                } else if($donation['DonationAmount'] < 0 && $donation['OrderStatusId'] == 2) {
                    $donation['Status'] = "Refund";
                } else {
                    $donation['Status'] = "Pending";
                }
                $line = '';
                foreach($donation as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $donation[$col] = "\t";
                    } else {
                        $donation[$col] = str_replace('"', '""', $value);
                        $donation[$col] = '"' . $value . '"' . "\t";
                    }
                }
                extract($donation);
                $line = stripslashes($Fundraiser)."$TransactionId$DonationAmount$ModifiedOn$SupporterName$SupporterEmail$Status";
                $data .= trim($line)."\n";
            }
            print "$headers\n$data";
        }
    }
    public function donationdetailsAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $ProjectId = $parameters['ProjectId'];
        $Projects = new Brigade_Db_Table_Brigades();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $this->view->usersClass = new Brigade_Db_Table_Users();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
        $Groups = new Brigade_Db_Table_Groups();
        $this->view->ProjectId = $ProjectId;
        $campaignInfo = $Projects->loadInfo($ProjectId);
        $this->view->URLName = $campaignInfo['pURLName'];
        $this->view->ProjectName = $campaignInfo['Name'];
        if (!empty($campaignInfo['GroupId'])) {
            $this->view->GroupId = $campaignInfo['GroupId'];
            $this->view->data = $Groups->loadInfo($this->view->GroupId);
            $this->view->progOrg = $Groups->loadProgOrg($this->view->GroupId);
            $this->view->level = 'group';
        } else if (!empty($campaignInfo['NetworkId'])) {
            $Organizations = new Brigade_Db_Table_Organizations();
            $this->view->data = $Organizations->loadInfo($campaignInfo['NetworkId'], false);
            $this->view->level = 'organization';
        } else if (!empty($campaignInfo['UserId'])) {
            $this->view->header_title = $campaignInfo['Name'];
            $this->view->level = 'user';
        }
        $this->view->donations = $ProjectDonations->getProjectDonationList($ProjectId);
        $this->view->general_donations = $ProjectDonations->getGeneralDonations($ProjectId);
        $total_user_donation_goal = 0;
        $fundraisers = $Volunteers->getCampaignFundraisers($ProjectId);
        //foreach ($fundraisers as $fundraiser) {
        //    $total_user_donation_goal += $Volunteers->getDonationGoal($ProjectId, $fundraiser["UserId"], true);
        //}
        $this->view->DonationGoal = $campaignInfo['DonationGoal'];
    }

    public function saveinfoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Brigades = new Brigade_Db_Table_Brigades();
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        if ($_POST) {
            $data = array();
            foreach($_POST as $key => $val) {
                if ($key != "field" && $key != "action" && $key != "ProjectId" && $key != "ContactId" && $key != "EndTime") {
                    $data[$key] = $val;
                }
            }
            if ($_POST['action'] == 'edit') {
                if ($_POST['field'] == 'description') {
                    if (isset($_POST['EndDate'])) {
                        $EndTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
                        $EndDate = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";
                        $data['EndDate'] = $EndDate;
                    }
                    $Brigades->editProject($_POST['ProjectId'], $data);
                    echo "Fundraising campaign ".$_POST['field']." has been successfully updated.";
                }
            }
        }
    }

    public function validateurlnameAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $emptyURL = false;
        if (!empty($_POST['URLName'])) {
            $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), trim($_POST['URLName']));
            // replace other special chars with accents
            $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
            $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
            $URLName = str_replace($other_special_chars, $char_replacement, $URLName);
        } else {
            $emptyURL = true;
        }
        $url_exists = !empty($URLName) ? $LookupTable->isSiteNameExists($URLName, $_POST['ProjectId']) : false;
        if ($url_exists) {
            echo "URL Name already exists, please specify another.";
        } else if ($emptyURL) {
            echo "Please specify the URL Name.";
        } else {
            echo "valid";
        }
    }

    public function uploadfundraisersAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            require_once 'Brigade/Util/ExcelReader.php';
            $tmpfile = $_FILES['uploadExcel']['tmp_name'];
            $filename = $_FILES['uploadExcel']['name'];
            $temp_file_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/$filename";
            move_uploaded_file($tmpfile, $temp_file_location);
            $data = new Spreadsheet_Excel_Reader($temp_file_location ,false);
            // convert to array
            $rows = $data->dumptoarray();
            // add each user to the users table
            $Users = new Brigade_Db_Table_Users();
            $Groups = new Brigade_Db_Table_Groups();
            $Brigades = new Brigade_Db_Table_Brigades();
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $Mailer = new Mailer();
            $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
            $projectInfo = $Brigades->loadInfo($_POST['ProjectId']);
            $groupInfo = $Groups->loadInfo($projectInfo['GroupId']);
            if(isset($projectInfo['CreatedBy'])) {
                $creator = $Users->loadInfo($projectInfo['CreatedBy']);
                $creator = $creator['FullName'];
            }
            $invalid = 0;
            for ($i = 2; $i <= count($rows); $i++) {
                // register the user if email is not taken
                if ($unique_emailvalidator->isValid($rows[$i][3])) {
                    $URLName = $this->createURLName($rows[$i][1], $rows[$i][2]);
                    $Password = $this->generatePassword();
                    $UserId = $Users->addUser(array(
                        'FirstName' => $rows[$i][1],
                        'LastName' => $rows[$i][2],
                        'Email' => $rows[$i][3],
                        'Password' => $Password,
                        'URLName' => $URLName,
                        'Active' => 1
                    ), false);

                    // register the user as volunteer of the activity
                    $Volunteers->signUpFundraiser($UserId, $_POST['ProjectId']);

                    // email a notification to the newly added user with the temp password attached
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$UPLOAD_NEW_FUNDRAISER,
                                        array($rows[$i][3], $rows[$i][1], $groupInfo['GroupName'], $projectInfo['Name'], isset($creator) ? $creator : NULL, $Password, $this->view->userNew));

                } else {
                    $userInfo = $Users->findBy($rows[$i][3]);
                    $UserId = $userInfo['UserId'];
                    if(!empty($UserId) && !$Volunteers->isUserSignedUp($_POST['ProjectId'], $UserId)) {
                        $Volunteers->signUpFundraiser($UserId, $_POST['ProjectId']);

                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$UPLOAD_EXISTING_FUNDRAISER,
                                        array($rows[$i][3], $rows[$i][1], $groupInfo['GroupName'], $projectInfo['Name'], isset($creator) ? $creator : NULL, $this->view->userNew));
                    }
                    $invalid++;
                }
            }
            echo '<script> alert("Your volunteers list has been successfully uploaded and all users have been registered on Empowered.org"); </script>';
            header("location: /".$projectInfo['pURLName']);
        }
    }

    private function createURLName($FirstName, $LastName) {
        $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), "$FirstName $LastName");

        // replace other special chars with accents
        $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
        $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
        $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

        $LookupTable = new Brigade_Db_Table_LookupTable();
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

        return $URLName;
    }

    private function generatePassword($length = 8) {

        // start with a blank password
        $password = "";

        // define possible characters - any character in this string can be
        // picked for use in the password, so if you want to put vowels back in
        // or add special characters such as exclamation marks, this is where
        // you should do it
        $possible = "12346789abcdfghjkmnpqrtvwxyzABCDFGHJKLMNPQRTVWXYZ";

        // we refer to the length of $possible a few times, so let's grab it now
        $maxlength = strlen($possible);

        // check for length overflow and truncate if necessary
        if ($length > $maxlength) {
            $length = $maxlength;
        }

        // set up a counter for how many characters are in the password so far
        $i = 0;

        // add random characters to $password until $length is reached
        while ($i < $length) {

            // pick a random character from the possible ones
            $char = substr($possible, mt_rand(0, $maxlength - 1), 1);

            // have we already used this character in $password?
            if (!strstr($password, $char)) {
                // no, so it's OK to add it onto the end of whatever we've already got...
                $password .= $char;
                // ... and increase the counter by one
                $i++;
            }
        }

        // done!
        return $password;
    }

    public function uploadAction() {
        $parameters = $this->_getAllParams();
        $Brigades = new Brigade_Db_Table_Brigades();
        $this->view->data = $Brigades->loadInfo1($parameters['ProjectId']);
    }

    public function addfundraisersAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $Users = new Brigade_Db_Table_Users();
        $Brigades = new Brigade_Db_Table_Brigades();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $Mailer = new Mailer();
        $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        if(isset($parameters['newcampaign'])) {
            $this->view->newcampaign = true;
            $campaignType = $parameters['newcampaign'];
        }

        $project  =  Project::get($parameters['ProjectId']);
        $this->view->project  =  $project;

        if(!empty($project->groupId)) {
            $group  =  $this->view->group   =  $project->group;
            $this->view->level              =  'group';

            //breadcrumb
            $this->view->breadcrumb         =  array();
            $this->view->breadcrumb[]       =  '<a href="/'.$group->organization->urlName.'">'.$group->organization->name.'</a>';
            if (!empty($group->programId)) {
                $this->view->breadcrumb[]   =  '<a href="/'.$group->program->urlName.'">'.$group->program->name.'</a>';
            }
            $this->view->breadcrumb[]       =  '<a href="/'.$group->urlName.'">'.$group->name.'</a>';
            $this->view->breadcrumb[]       =  '<a href="/'.$project->urlName.'">'.$project->name.'</a>';

            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');


        } else if(!empty($project->organizationId)) {
            $this->view->organization       =  $organization  =  $project->organization;

            //breadcrumb
            $this->view->breadcrumb         =  array();
            $this->view->breadcrumb[]       =  '<a href="/'.$organization->urlName.'">'.$organization->name.'</a>';
            $this->view->breadcrumb[]       =  '<a href="/'.$project->urlName.'">'.$project->name.'</a>';
            $this->view->breadcrumb[]       =  'Add Volunteers';


            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');

        } else {
            $Users = new Brigade_Db_Table_Users();
            $this->view->data = $Users->loadInfo($project->userId);
            $this->view->header_title = $project->name;
            $this->view->level = 'user';
        }

        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');

        if ($_POST) {
            $projectInfo = $Brigades->loadInfoBasic($_POST['ProjectId']);
            if(!empty($projectInfo['pCreatedBy'])) {
                $creator = $Users->loadInfo($projectInfo['pCreatedBy']);
                $creator = $creator['FullName'];
            }
            if (!empty($_POST['emails'])) {
                preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $_POST['emails'], $emails);
                $this->view->emails = $emails[0];
                foreach ($emails[0] as $email) {
                    if ($unique_emailvalidator->isValid($email)) {
                        $name = explode("@", $email);
                        $URLName = $this->createURLName(str_replace("@", "", $name[0]), "");
                        $Password = $this->generatePassword();
                        $UserId = $Users->addUser(array(
                            'FirstName' => $email,
                            'LastName' => "",
                            'Email' => $email,
                            'Password' => $Password,
                            'URLName' => $URLName,
                            'Active' => 1,
                            'FirstLogin' => 1
                        ), false);

                        // email a notification to the newly added user with the temp password attached
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$UPLOAD_NEW_FUNDRAISER,
                                        array($email, $email, !isset($session_user) ? $this->view->network['NetworkName'] : $session_user['FullName'], $projectInfo['Name'], isset($creator) && !isset($session_user) ? $creator : NULL, $Password, $_POST['message'], $this->view->userNew));

                        $this->view->sent = true;
                    } else {
                        $userInfo = $Users->findBy($email);
                        $UserId = $userInfo['UserId'];
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$UPLOAD_EXISTING_FUNDRAISER,
                                        array($email, $userInfo['FirstName'], !isset($session_user) ? $this->view->network['NetworkName'] : $session_user['FullName'], $projectInfo['Name'], isset($creator) && !isset($session_user) ? $creator : NULL, $_POST['message'], $this->view->userNew));

                        $this->view->sent = true;
                    }

                    // register the user as volunteer of the activity
                    if(!empty($UserId) && !$Volunteers->isUserSignedUp($_POST['ProjectId'], $UserId)) {
                        $Volunteers->signUpFundraiser($UserId, $_POST['ProjectId']);
                    }
                }
                if(isset($parameters['newcampaign'])) {
                    header("loaction: /".$projectInfo['pURLName']."/share?newcampaign=$campaignType");
                } else {
                    header("loaction: /".$projectInfo['pURLName']."/share");
                }
            } else if (!empty($_FILES['uploadExcel']) && $_FILES['uploadExcel']['size'] > 0 && empty($_POST['emails'])) {
                require_once 'Brigade/Util/ExcelReader.php';
                $tmpfile = $_FILES['uploadExcel']['tmp_name'];
                $filename = $_FILES['uploadExcel']['name'];
                $temp_file_location = realpath(dirname(__FILE__) . '/../../../') . "/public/tmp/$filename";
                move_uploaded_file($tmpfile, $temp_file_location);
                $data = new Spreadsheet_Excel_Reader($temp_file_location, false);
                // convert to array
                $rows = $data->dumptoarray();
                // add each user to the users table
                $email_list = array();
                $invalid_emails = array();
                $invalid_chars = array();
                $validator = new Zend_Validate_EmailAddress();
                for ($i = 1; $i <= count($rows); $i++) {
                    if (!$validator->isValid($rows[$i][3])) {
                        $invalid_emails[] = $rows[$i][3];
                    } else {
                        $email_list[] = $rows[$i][3];
                        // register the user if email is not taken
                        if ($unique_emailvalidator->isValid($rows[$i][3])) {
                            $URLName = $this->createURLName($rows[$i][1], $rows[$i][2]);
                            $Password = $this->generatePassword();
                            $UserId = $Users->addUser(array(
                                'FirstName' => $rows[$i][1],
                                'LastName' => $rows[$i][2],
                                'Email' => $rows[$i][3],
                                'Password' => $Password,
                                'URLName' => $URLName,
                                'Active' => 1,
                                'FirstLogin' => 1
                            ), false);

                            // email a notification to the newly added user with the temp password attached
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$UPLOAD_NEW_FUNDRAISER,
                                        array($rows[$i][3], $rows[$i][1], !isset($session_user) ? $this->view->network['NetworkName'] : $session_user['FullName'], $projectInfo['Name'], isset($creator) && !isset($session_user) ? $creator : NULL, $Password, $_POST['message'], $this->view->userNew));
                            $this->view->sent = true;
                        } else {
                            $userInfo = $Users->findBy($rows[$i][3]);
                            $UserId = $userInfo['UserId'];

                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$UPLOAD_EXISTING_FUNDRAISER,
                                        array($rows[$i][3], $rows[$i][1], !isset($session_user) ? $this->view->network['NetworkName'] : $session_user['FullName'], $projectInfo['Name'], isset($creator) && !isset($session_user) ? $creator : NULL, $_POST['message'], $this->view->userNew));

                            $this->view->sent = true;
                        }

                        // register the user as volunteer of the activity
                        if(!empty($UserId) && !$Volunteers->isUserSignedUp($_POST['ProjectId'], $UserId)) {
                            $Volunteers->signUpFundraiser($UserId, $_POST['ProjectId']);
                        }
                    }
                }
                $this->view->emails = $email_list;
                $this->view->invalid_emails = $invalid_emails;

                if(isset($parameters['newcampaign'])) {
                    header("loaction: /".$projectInfo['pURLName']."/share?newcampaign=$campaignType");
                } else {
                    header("loaction: /".$projectInfo['pURLName']."/share");
                }
            }
            if (!$project->hasUploadedMembers) {
                $Brigades->editProject($_POST['ProjectId'], array('hasUploadedMembers' => 1));
            }

        }
    }

    public function editlogoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Media = new Brigade_Db_Table_Media();
        $Brigades = new Brigade_Db_Table_Brigades();
        if ($_POST) {
            // save organization media/image
            extract($_POST);
            $projInfo = $Brigades->loadInfoBasic($ProjectId);
            $this->view->image = $Media->getSiteMediaBySiteId($ProjectId);
            $MediaSize = $_FILES['ProjectLogo']['size'];
            $tmpfile = $_FILES['ProjectLogo']['tmp_name'];
            $filename = $_FILES['ProjectLogo']['name'];
            $file_ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
            if ($MediaSize > 0) {
                $destination_thumb = realpath(dirname(__FILE__) . '/../../../')."/public/Media";
                $destination_big = realpath(dirname(__FILE__) . '/../../../')."/public/Media/full";
                // delete existing media image if any
                $old_logo = $this->view->image['SystemMediaName'];
                if (!empty($old_logo)) {
                    if (file_exists("$destination_thumb/$old_logo")) {
                        unlink("$destination_thumb/$old_logo");
                    }
                    if (file_exists("$destination_big/$old_logo")) {
                        unlink("$destination_big/$old_logo");
                    }
                }
                if (isset($MediaId) && !empty($MediaId)) {
                    $Media->editMedia($MediaId, array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $projInfo['URLName']."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));
                } else {
                    // save media
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $projInfo['URLName']."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $ProjectId
                    ));
                }

                //Get the file information
                $ImageCrop = new Brigade_Util_ImageCrop();
                $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$ProjectId.jpg";

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
                header("location: /fundraisingcampaign/cropimage/?ProjectId=$ProjectId");
            }
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

}
