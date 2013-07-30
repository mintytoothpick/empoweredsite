<?php

/**
 * GroupController - The "groups" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Blogs.php';
require_once 'Brigade/Db/Table/Events.php';
require_once 'Brigade/Util/ImageResize.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/SiteActivityComments.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/LookupTableHistory.php';
require_once 'Brigade/Db/Table/Countries.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/Photo.php';
require_once 'Brigade/Db/Table/Regions.php';
require_once 'Brigade/Db/Table/Survey.php';
require_once 'Brigade/Db/Table/Cities.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Util/ImageCrop.php';
require_once 'Zend/Paginator.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Zend/Validate/EmailAddress.php';
require_once 'Brigade/Lib/Validate/DbUnique.php';
require_once 'Brigade/Db/Table/GroupEmailAccounts.php';
require_once 'Brigade/Db/Table/ReceiptMessages.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

require_once 'Mailer.php';
require_once 'Project.php';
require_once 'GroupEmail.php';
require_once 'BluePay/BluePayment.php';
require_once 'Payment.php';
require_once 'Member.php';
require_once 'MembershipFrequency.php';
require_once 'MembershipFund.php';
require_once 'Infusionsoft.php';
require_once 'Salesforce.php';

class GroupController extends BaseController {

    protected $_http;
    protected $GroupId;

    public function init() {
        $this->media_path = "Media/";
        $front = Zend_Controller_Front::getInstance();
        $actionName = $front->getRequest()->getActionName();

        parent::init();
    }

    public function indexAction() {
        $parameters   = $this->_getAllParams();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $group        = Group::get($parameters['GroupId']);

        // new url for cms redirect
        $config = Zend_Registry::get('configuration');
        if ($config->cms_migrate->active &&
            in_array($group->organizationId, $config->cms_migrate->org->toArray())
        ) {
            if (!($this->view->isLoggedIn && $this->view->isAdmin)) {
                $this->_helper->redirector->gotoUrl(
                    $config->cms_migrate->host . '/chapter/' . $group->id
                );
            }
        }

        $this->view->headTitle(stripslashes($group->name));

        $this->getHeaderMedia($group);

        //get group members and staff
        $this->view->member_count   = count($group->members);
        $this->view->administrators = $GroupMembers->getGroupAdmins($group->id);
        $this->view->staff_count    = count($this->view->administrators);
        $this->view->breadcrumb     = $this->view->breadcrumbHelper($group);
        $this->view->currentTab     = 'home';
        $this->view->network        = $group->organization;
        $this->view->group          = $group;
        $this->view->showAll        = false; //for group wall
        $this->view->urlName        = $group->urlName;

        $Files             = new Brigade_Db_Table_Files();
        $this->view->files = $Files->getSiteFiles($group->id);

        //get the group's upcoming initiative
        $this->view->project = Project::getFeaturedGroupInitiative($group->id);
        if ($this->view->project) {
            $this->view->rightbarHelper($this->view->project);
        }

        // check if user is a member of this organization to display the button "become a member"
        $this->view->is_member        = false;
        $this->view->waiting_approval = false;
        $this->view->joinlink         = 'href="#"';
        if ($this->view->isLoggedIn) {
            if ($group->isMember($this->sessionUser)) {
                $this->view->is_member = true;
            }
            if (!$this->view->is_member) {
                $deletedMember = $group->getMember($this->sessionUser);
                if ($group->hasMembershipPendingReq($this->sessionUser)) {
                   $this->view->waiting_approval = true;
                } else if ($deletedMember && $deletedMember->isDeleted) {
                    $this->view->is_member = true;
                } else {
                   $this->view->joinlink = 'href="javascript:joinGroup(\''.$group->id.'\', \''.$_SESSION['UserId'].'\')"';
                }
            }
        } else {
            $this->view->joinlink = 'href="javascript:;" class="join"';
        }

        if (!is_null($group->contact)) {
            if ($group->contact->countryId) {
                $Regions = new Brigade_Db_Table_Regions();
                $this->view->region_list = $Regions->getCountryRegions($group->contact->countryId);
            }
            if ($group->contact->stateId) {
                $Cities = new Brigade_Db_Table_Cities();
                $this->view->city_list = $Cities->getRegionCities($group->contact->stateId);
            }
        }
        $this->view->contactAdminActive = Zend_Registry::get('configuration')
                                                ->chapter->contactadmin->active;

        //for upload logo
        $this->view->uploadUrlLogo = '/group/editlogo';
        $this->view->uploadIdName  = 'Group';
        $this->view->toolPopupObj  = $group;

        $this->view->render('administrator/popup_upload_logo.phtml');
        $this->view->render('administrator/popup_upload_banner.phtml');
        $this->view->render('group/toolbox.phtml');

        $this->renderPlaceHolders();
    }

    /**
     * Ajax send message to administrator group.
     */
    public function contactadminAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters = $this->_getAllParams();

        // Allow to send 4 messages per hour.
        if (Zend_Registry::get('configuration')->chapter->contactadmin->active) {
            $allow = true;
            $cacheKey = 'GroupController_contactadminAction_' . str_replace('-', '_', $_SESSION['UserId']);

            $cache = Zend_Registry::get('cache');
            $quota = $cache->load($cacheKey);

            // If no quota for this user, start a new one.
            if($quota === false) {
                $quota = array('time' => time(), 'count' => 0);
            }

            // We're inside the first hour
            if($quota['time'] > time() - 3600) {
                // Check count
                if($quota['count'] >= 4) {
                    $allow = false;
                }
            } else {
                // Restart. Hour has passed since first message cached.
                $quota['time'] = time();
                $quota['count'] = 0;
            }

            if($allow) {
                $user  = User::get($_SESSION['UserId']);
                $group = Group::get($parameters['GroupId']);

                if (!isset($group->contact->user)) {
                    $userTo      = new User();
                    $userTo->email = $group->contact->email;
                    $userTo->name  = $group->contact->email;
                } else {
                    $userTo = $group->contact->user;
                }
                $ok = Zend_Registry::get('eventDispatcher')->dispatchEvent(
                        EventDispatcher::$MESSAGE_TO_ADMIN,
                        array($user, $userTo, $parameters['message'])
                );

                // Count and cache
                $quota['count']++;
                $cache->save($quota, $cacheKey);

                echo json_encode(array('ok' => $ok));
            } else {
                echo json_encode(array('alert' => "You have reached your limit of messages"));
            }
        }
    }

    /**
     * Ajax action to get all wall posts of the group index page.
     */
    public function wallAction() {
        $parameters          = $this->_getAllParams();
        $this->view->group   = Group::get($parameters['groupId']);
        $this->view->showAll = true;
        $this->render('wall');
        $this->_helper->layout()->disableLayout();
    }

    /**
     * Post into group wall or activity
     *
     * @return void
     */
    public function postwallAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout()->disableLayout();
        if (isset($parameters['GroupId'])) {
            $group = Group::get($parameters['GroupId']);

            //post comment for an activity
            if (isset($parameters['activityId'])) {
                $activity = Activity::get($parameters['activityId']);
                $activity->postComment($parameters['message'],
                                       User::get($_SESSION['UserId']));

                $this->view->showAll = $parameters['filter'];
            } else {
            //post comment for group wall
                $group->postWall($parameters['message'],
                                 User::get($_SESSION['UserId']));

                $this->view->showAll = false;
            }
            $this->view->group = $group;
            $this->render('wall');
        } else {
            echo 'Invalid group';
        }
    }

    public function getprojectsAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $Project  = new Brigade_Db_Table_Projects();
        $projects = $Project->getProjects($parameters["GroupId"]);
        echo Zend_Json::encode($projects);
    }

    public function preDispatch() {
        parent::preDispatch();
        $this->view->media_path = "/public/Media/";
        $this->view->MLKchallengeId = "405B8C76-DEEC-11DF-867B-0025900034B2";
    }

    public function searchAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $text_search = $_POST['text_search'];
        $text_search = preg_replace('/\s\s+/', ' ', $text_search);

        $Groups = new Brigade_Db_Table_Groups();
        $contactinfo = new Brigade_Db_Table_ContactInformation();
        $list = $Groups->search($text_search);
        $sitemedia = new Brigade_Db_Table_Media();

        if (trim($text_search) != "") {
            echo '
                    <h2>Search results for "'.$text_search.'"</h2>
                    <div class="clear"></div>
            ';
        } else {
            echo '<h2>Groups</h2><div class="clear"></div>';
        }
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $sitemedia->getSiteMediaById($item['LogoMediaId']);
                $media_image = $this->view->media_path.$media['SystemMediaName'];
                $media_caption = $media['Caption'];
                $total_volunteers = $Groups->loadSupporters($item['GroupId']);
                echo '
                    <div class="sr2st05">
                        <center><img src="'.$media_image.'" alt="'.$media_caption.'" height="auto" width="65%" /></center>
                    </div>
                    <div class="sr2st04">
                    <h4><a href="/group/?GroupId='.$item['GroupId'].'">'.$item['GroupName'].'</a></h4>
                        <strong class="txt01">Location: </strong>'.$contactinfo->getContactInfo($item['GroupId'], 'Location').'<br/>
                        <strong class="txt01">Number of Volunteers: </strong>'.$total_volunteers.'<br/>
                        <strong class="txt01">Amount Raised to Date: </strong> $'.$Groups->loadDonationsRaised($item['GroupId']).'<br/>
                        <strong class="txt01">About Us: </strong>
                        <div id="divLessContent'.$item['GroupId'].'" style="display:block;">
                            <span id="ctl00_ContentPHMain_ctrlGroupList1_repeatGroups_ctl00_lblDescriptionLessContent">
                                '.(strlen($item['Description']) > 100 ? substr($item['Description'], 0, 100) : $item['Description']).'
                            </span>
                            '.(strlen($item['Description']) > 100 ? '<a id="ReadMore" href="javascript:ShowHide(\'divLessContent'.$item['GroupId'].'\',\'divMoreContent'.$item['GroupId'].'\');">Read More</a>' : "").'
                        </div>
                        '.(strlen($item['Description']) > 100 ? '
                        <div id="divMoreContent'.$item['GroupId'].'" style="display:none;">
                            <span id="ctl00_ContentPHMain_ctrlGroupList1_repeatGroups_ctl00_lblDescriptionMoreContent">'.$item['Description'].'</span>
                            <a id="ReadFewer" href="javascript:ShowHide(\'divMoreContent'.$item['GroupId'].'\',\'divLessContent'.$item['GroupId'].'\')">Read Less</a>
                        </div>
                        ' : "").'
                    </div>
                ';
            }
        } else {
            echo '<div class="sr2st04"><h4>No record(s) found. Check your spelling or try another term.</h4></div><div class="clear"></div>';
        }
    }

    public function loadprogramsAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (isset($parameters['NetworkId'])) {
            $Programs = new Brigade_Db_Table_Programs();
            $programs = $Programs->listByNetwork($parameters['NetworkId'], false);
            foreach($programs as $program) {
                echo '<option value="'.$program['ProgramId'].'">'.$program['ProgramName'].'</option>';
            }
        }
    }

    public function createAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Programs   = new Brigade_Db_Table_Programs();
        $Countries   = new Brigade_Db_Table_Countries();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $this->view->country_list = $Countries->getAllCountries();

        if (isset($parameters['Type'])){
            $this->view->Type = $parameters['Type'];
        }else {
            $this->view->Type = "";
        }
        if (!empty($parameters['ProgramId'])) {
            $program      = Program::get($parameters['ProgramId']);
            $organization = $program->organization;
        } else {
            $organization = Organization::get($parameters['NetworkId']);
        }
        if (!($this->view->isAdmin || (isset($program) && $program->isOpen) ||
            (isset($organization) && $organization->isOpen))
        ) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $this->getOrganizationHeaderMedia($organization);

        //breadcrumb
        if(isset($program)) {
            $this->view->breadcrumb = $this->view->breadcrumbHelper($program, 'Create Chapter');
        } else {
            $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Create Chapter');
        }

        $this->view->organization = $organization;
        if(isset($program)) {
            $this->view->program  = $program;
        }

        $this->view->render('nonprofit/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');

        if ($_POST) {
            extract($_POST);
            $Users = new Brigade_Db_Table_Users();
            $GroupName = trim($GroupName);

            $bad_ext = 0;
            if(!empty($_FILES['GroupLogo']['name'])) {
                $filename = $_FILES['GroupLogo']['name'];
                $file_ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
                if($file_ext != 'jpg' && $file_ext != 'jpeg' && $file_ext != 'png' && $file_ext != 'gif') {
                    $bad_ext = 1;
                    $this->view->message = "Please upload a logo in jpeg, png and gif format only.";
                }
            }
            if (!$bad_ext) {
                // if the org has no programs yet or user decides to create one, create it
                if (isset($ProgramName) && $ProgramName != 'New Program Name' && !empty($ProgramName)) {
                    $Organizations = new Brigade_Db_Table_Organizations();
                    $orgInfo = $Organizations->loadInfo($NetworkId, false);
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
                        'Description' => $organization->description,
                        'CreatedBy' => $_SESSION['UserId'],
                        'CreatedOn' => date('Y-m-d H:i:s'),
                        'ModifiedBy' => $_SESSION['UserId'],
                        'ModifiedOn' => date('Y-m-d H:i:s'),
                        'URLName' => $progURLName,
                        'NetworkId' => $organization->id
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
                    $UserRole = new Brigade_Db_Table_UserRoles();
                    $UserRoleId = $UserRole->addUserRole(array(
                        'UserId' => $this->view->userNew->id,
                        'RoleId' => 'ADMIN',
                        'SiteId' => $ProgramId
                    ));

                    // save program contact info
                    $ContactInfo = new Brigade_Db_Table_ContactInformation();
                    $ContactId = $ContactInfo->addContactInfo(array(
                        'WebAddress' => $organization->contact->website,
                        'CreatedBy' => $_SESSION['UserId'],
                        'CreatedOn' => date('Y-m-d H:i:s'),
                        'ModifiedBy' => $_SESSION['UserId'],
                        'ModifiedOn' => date('Y-m-d H:i:s'),
                    ));

                    // log the site activity
                    $activity              = new Activity();
                    $activity->siteId      = $organization->id;
                    $activity->type        = 'Program Added';
                    $activity->createdById = $this->view->userNew->id;
                    $activity->date        = date('Y-m-d H:i:s');
                    $activity->details     = $ProgramId;
                    $activity->save();
                } else {
                    $ProgramId = isset($_POST['ProgramId']) ? $_POST['ProgramId'] : '';
                }
                $Description = $_POST['Description'];

                $city    = City::get($_POST['CityId']);
                $region  = State::get($_POST['RegionId']);
                $country = Country::get($_POST['CountryId']);

                // group contactinfo
                $Email      = $_POST['Email'];
                $Street     = $_POST['Street'];
                $CityId     = $_POST['CityId'];
                $RegionId   = $_POST['RegionId'];
                $CountryId  = $_POST['CountryId'];
                $City       = ($city) ? $city->name : '';
                $Region     = ($region) ? $region->name : '';
                $Country    = ($country) ? $country->name : '';
                $WebAddress = $_POST['WebAddress'];

                $GoogleCheckoutAccountId = $organization->googleId;
                $PaypalAccountId         = $organization->paypalId;
                $BluePayAccountId        = $organization->bluePayId;

                if($GoogleCheckoutAccountId == 1 || $GoogleCheckoutAccountId == 2 ||
                   $GoogleCheckoutAccountId == 3
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
                $newGroup                 = new Group();
                $newGroup->name           = $GroupName;
                $newGroup->description    = $Description;
                $newGroup->isOpen         = isset($_POST['isOpen']) ? 1 : 0;
                $newGroup->isActive       = (isset($program) && $program->isOpen) || (!isset($program) && $organization->isOpen) ? 1 : 0;
                $newGroup->isNonProfit    = $isNonProfit;
                $newGroup->currency       = !is_null($group_currency) ? $group_currency : '$';
                $newGroup->programId      = $ProgramId;
                $newGroup->organizationId = $organization->id;
                $newGroup->percentageFee  = $organization->percentageFee;
                $newGroup->makeUrl();

                $newGroup->allowPercentageFee     = $organization->allowPercentageFee;
                $newGroup->fundraiseMembershipFee = false;
                if (!empty($_POST['activityRequiresMembership'])) {
                    $newGroup->activityRequiresMembership = $_POST['activityRequiresMembership'];
                }
                if (!is_null($GoogleCheckoutAccountId)) {
                    $newGroup->googleId = $GoogleCheckoutAccountId;
                }
                if (!is_null($PaypalAccountId)) {
                    $newGroup->paypalId = $PaypalAccountId;
                }
                if (!is_null($BluePayAccountId)) {
                    $newGroup->bluePayId = $BluePayAccountId;
                }
                $GroupId = $newGroup->save();

                $config  = Zend_Registry::get('configuration');
                if (!empty($parameters['feeFreq'])) {
                    MembershipFrequency::clean($group);
                    foreach($parameters['feeFreq'] as $id) {
                        $membershipFreq          = new MembershipFrequency();
                        $membershipFreq->id      = $id;
                        $membershipFreq->amount  = $parameters['feeAmnt_'.$id];
                        $membershipFreq->groupId = $GroupId;
                        $membershipFreq->save();
                    }
                } else if (in_array($organization->id,
                           $config->chapter->membership->active->toArray())
                ) {
                    //create default frequency amount
                    $defVals = $config->chapter->membership->default;

                    $membershipFreq          = new MembershipFrequency();
                    $membershipFreq->id      = $defVals->frequencyId;
                    $membershipFreq->amount  = $defVals->amount;
                    $membershipFreq->groupId = $GroupId;
                    $membershipFreq->save();

                    $newGroup->hasMembershipFee = true;
                    $newGroup->save();
                }

                // add record on the lookup_table
                $LookupTable->addSiteURL(array(
                    'SiteName' => $newGroup->urlName,
                    'SiteId' => $GroupId,
                    'Controller' => 'group',
                    'FieldId' => 'GroupId'
                ));

                // add default administrator for this group
                $UserRole = new Brigade_Db_Table_UserRoles();
                $UserRoleId = $UserRole->addUserRole(array(
                    'UserId' => $this->view->userNew->id,
                    'RoleId' => 'ADMIN',
                    'SiteId' => $GroupId
                ));

                //add creator as a member of the group
                $GroupMembers = new Brigade_Db_Table_GroupMembers();
                $GroupMembers->AddGroupMember(array(
                   'UserId'    => $this->view->userNew->id,
                   'GroupId'   => $GroupId,
                   'NetworkId' => $organization->id,
                   'isAdmin'   => 1
                ));

                if(!$this->view->isAdmin) {
                  $admins = $UserRole->getSiteAdmin($organization->id);
                  foreach($admins as $admin) {
                    $administrator = $admin;
                    break;
                  }
                  $isOpen = (isset($program) && $program->isOpen) || (!isset($program) && $organization->isOpen);

                  Zend_Registry::get('eventDispatcher')->dispatchEvent(
                    EventDispatcher::$CHAPTER_CREATED_BY_USER,
                    array($administrator, $this->view->userNew, $newGroup, $isOpen));
                }

                if(!empty($WebAddress)) {
                    preg_match("/^https?:\/\/[_a-zA-Z0-9-]+\.[\._a-zA-Z0-9-]+$/i", $WebAddress, $website);
                    if(empty($website[0])) {
                        $WebAddress = 'http://'.$WebAddress;
                    }
                }

                // save group contact info
                $ContactInfo = new Brigade_Db_Table_ContactInformation();
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
                    'SiteId' => $GroupId
                ));

                // log the site activity
                $activity              = new Activity();
                $activity->siteId      = $organization->id;
                $activity->type        = 'Group Added';
                $activity->createdById = $this->view->userNew->id;
                $activity->date        = date('Y-m-d H:i:s');
                $activity->details     = $GroupId;
                $activity->save();

                // save group media/image
                $MediaSize = $_FILES['GroupLogo']['size'];
                $tmpfile = $_FILES['GroupLogo']['tmp_name'];
                if ($MediaSize > 0) {
                    //Get the file information
                    $ImageCrop = new Brigade_Util_ImageCrop();
                    $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_".strtolower($GroupId).".jpg";

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
                    $uploaded = $ImageCrop->resizeImage($temp_image_location, $width, $height, $scale, $file_ext);

                    // save media
                    $Media = new Brigade_Db_Table_Media();
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => strtolower($GroupId).".jpg",
                        'UploadedMediaName' => $filename,
                        'CreatedBy' => $_SESSION['UserId'],
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $GroupId
                    ));

                    // update group LogoMediaId
                    $newGroup->logoMediaId = $MediaId;
                    $newGroup->save();
                    header("location: /group/cropimage/?GroupId=$GroupId".(isset($_REQUEST['getstarted']) ? "&getstarted=yes" : "").(isset($_SESSION['upgradeOrg']) ? "&_action=upgrade" : '').(isset($_POST['create_again']) && $_POST['create_again'] ? "&create_again=1" : ""));
                } else {
                    $Groups = new Brigade_Db_Table_Groups();
                    if ((isset($parameters['_action']) || isset($parameters['getstarted'])) && isset($parameters['create_again']) && $parameters['create_again']) {
                        $network = $Groups->loadProgOrg($this->view->GroupId);
                        header("location: /".$network['nURLName']."/create-group?getstarted=yes");
                    } else if (isset($_SESSION['assignCampaign'])) {
                        header("location: /getstarted/assign-campaign");
                    } else if (isset($_SESSION['assignActivity'])) {
                        header("location: /getstarted/assign-activity");
                    } else if (isset($_SESSION['assignEvent'])) {
                        header("location: /getstarted/assign-event");
                    } else if (isset($parameters['getstarted'])) {
                        $network = $Groups->loadProgOrg($GroupId);
                        header("location: /".$network['nURLName']."/add-admins?getstarted=yes");
                    } else if (isset($_SESSION['upgradeOrg'])) {
                        unset($_SESSION['upgradeOrg']);
                        $network = $Groups->loadProgOrg($GroupId);
                        $Organizations = new Brigade_Db_Table_Organizations();
                        $projects = $Organizations->simpleProjectsList2($NetworkId);
                        header("location: /".$network['nURLName'].(count($projects) ? "/assign-projects" : ""));
                    } else {
                        header("location: /".$newGroup->urlName);
                    }
                }

                $this->view->message = "Group \"".$newGroup->name."\" has been created successfully.";
            } else {
                foreach($_POST as $key => $val) {
                    $this->view->$key = $val;
                }
                $message = "";
                if (isset($_FILES['GroupLogo']) && $_FILES['GroupLogo']['size'] > 2097152) {
                    $message .= "Please select an image not greater than 2MB.<br>";
                }
                $this->view->message = $message;
            }
        }
    }

    public function editlogoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Media = new Brigade_Db_Table_Media();
        $Groups = new Brigade_Db_Table_Groups();
        if ($_POST) {
            // save group media/image
            extract($_POST);
            $groupInfo = $Groups->loadInfo1($GroupId);
            $MediaSize = $_FILES['GroupLogo']['size'];
            $tmpfile = $_FILES['GroupLogo']['tmp_name'];
            $filename = $_FILES['GroupLogo']['name'];
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
                if (!empty($MediaId)) {
                    $Media->editMedia($MediaId, array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $groupInfo['URLName']."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));
                } else {
                    // save media
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $groupInfo['URLName']."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $GroupId
                    ));

                    $Groups = new Brigade_Db_Table_Groups();
                    $Groups->editGroup($GroupId, array('LogoMediaId' => $MediaId));
                }

                //Get the file information
                $ImageCrop = new Brigade_Util_ImageCrop();
                $GroupId = strtolower($GroupId);
                $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$GroupId.jpg";

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
                header("location: /group/cropimage/?GroupId=$GroupId");
            }
        }
    }

    public function cropimageAction() {
        $parameters = $this->_getAllParams();
        $GroupId = strtolower($parameters['GroupId']);
        $this->view->GroupId = $GroupId;
        if (isset($parameters['getstarted'])) {
            $this->view->getstarted = 1;
        }
        if (isset($_SESSION['upgradeOrg'])) {
            $this->view->upgradeOrg = 1;
        }
        if ($_POST) {
            $Groups  = new Brigade_Db_Table_Groups();
            $group   = Group::get($GroupId);
            $URLName = $group->urlName;
            $ImageCrop = new Brigade_Util_ImageCrop();
            $this->view->image_preview = $media_name = "$URLName-logo";
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$GroupId.jpg";
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
                } else {
                    $group->logo->systemMediaName = $media_name.'.jpg';
                    $group->logo->save();
                }
            }


            if (!$_POST['preview']) {
                // delete the temp file
                if (file_exists($temp_image_location)) {
                    unlink($temp_image_location);
                }

                if ((isset($parameters['_action']) || isset($parameters['getstarted'])) && isset($parameters['create_again']) && $parameters['create_again']) {
                    $network = $Groups->loadProgOrg($this->view->GroupId);
                    header("location: /".$network['nURLName']."/create-group?getstarted=yes");
                } else if (isset($_SESSION['assignCampaign'])) {
                    header("location: /getstarted/assign-campaign");
                } else if (isset($_SESSION['assignActivity'])) {
                    header("location: /getstarted/assign-activity");
                } else if (isset($_SESSION['assignEvent'])) {
                    header("location: /getstarted/assign-event");
                } else if (isset($parameters['getstarted'])) {
                    $network = $Groups->loadProgOrg($this->view->GroupId);
                    header("location: /".$network['nURLName']."/add-admins?getstarted=yes");
                } else if (isset($_SESSION['upgradeOrg'])) {
                    unset($_SESSION['upgradeOrg']);
                    $network = $Groups->loadProgOrg($this->view->GroupId);
                    $Organizations = new Brigade_Db_Table_Organizations();
                    $projects = $Organizations->simpleProjectsList2($NetworkId);
                    header("location: /".$network['nURLName'].(count($projects) ? "/assign-projects" : ""));
                } else {
                    header("location: /$URLName");
                }
            } else {
                $this->view->preview_image = 1;
            }
        }
    }


    /**
     * Delete account information under infusionsoft.
     */
    protected function salesForceIntegrationDeleteAccount($group) {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($group->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Group::Delete');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($group->organization)) {
            $salesforce->deleteAccount($group);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$group->organizationId
            );
        }
    }

    public function deleteAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        if ($_POST) {
            $GroupId = $params['GroupId'];
            $group   = Group::get($GroupId);
            if ($group->program) {
                $site = $group->program;
            } else {
                $site = $group->organization;
            }
            $group->delete();
            $this->salesForceIntegrationDeleteAccount($group);

            $this->_helper->redirector->gotoUrl('/'.$site->urlName);
        }
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
            echo '<li><table><tr><td style="width:34px;"><img src="/profile/loadimage?UserId='.$userInfo['UserId'].'" /></td><td style="width:316px;"><span class="comment"><a href="/'.$userInfo['URLName'].'">'.stripslashes($userInfo['FirstName'].' '.$userInfo['LastName']).'</a>&nbsp;&nbsp;'.$_POST['Comment'].'<br><span class="time">'.$this->getDateFormat($time).'</span></span></td></tr></table></li>';
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
                'SiteId' => $_POST['Recipient'],
                'ActivityType' => 'Wall Post',
                'CreatedBy' => $_SESSION['UserId'],
                'ActivityDate' => $time,
                'Link' => $activity_link,
                'Details' => $_POST['Comment'],
                'Recipient' => $_POST['Recipient']
            ));
            $comments = "<ul id='ul_".$SiteActivityId."' style='display:none'></ul>";
            $avatar = "<img id='avatar_".$SiteActivityId."' src='/profile/loadimage?UserId=".$_SESSION['UserId']."' height='25' width='25' style='display:none; margin-right:3px; vertical-align:top;' />";
            $comment_box = '<div style="padding:3px; background-color:#e5e5e5; width:90%; margin-left:34px; margin-bottom:3px;">'.$avatar.'<textarea id="comment_'.$SiteActivityId.'" cols="50" rows="1" style="font-size:11px; height:15px; width:98%;" onfocus="commentfocus(this)" onblur="commentblur(this)">Write a comment...</textarea><input id="submit_'.$SiteActivityId.'" class="btn btngreen" style="float:right; display:none;" type="submit" value="Comment" onclick="commentpost(this)"/></div>';
            $display = "<table style='margin-bottom:-20px;'><tr><td width=34><img src='/profile/loadimage?UserId=".$userInfo['UserId']."' width='30' height='30'></td><td><a href='/profile/info/".$userInfo['UserId']."'>".stripslashes($userInfo['FirstName'])." ".stripslashes($userInfo['LastName'])."</a>&nbsp;&nbsp;".stripslashes(nl2br($_POST['Comment']))."<br>".$this->getDateFormat($time).".</td></tr></table>";
            echo "$display<br><br>$comments$comment_box<br><div class='clear1'></div>";
        }
    }

    public function findAction() {
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        //$Countries = new Brigade_Db_Table_Countries();
        $Regions = new Brigade_Db_Table_Regions();
        $Cities = new Brigade_Db_Table_Cities();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->donations = new Brigade_Db_Table_ProjectDonations();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $this->view->contact_info = $contact_info;
        $this->view->groups_class = $Groups;
        // TO DELETE
        //$this->view->country_list = $Countries->getCountries('find');
        $this->view->realpath = realpath(dirname(__FILE__) . '/../../../');
        if (isset($parameters['ProgramId'])) {
            $Programs = new Brigade_Db_Table_Programs();
            $progInfo = $Programs->loadInfo1($parameters['ProgramId']);
            $this->view->search = "All Groups on ".$progInfo['ProgramName'];
            $this->view->groups = $Groups->listByProgram($parameters['ProgramId']);
        } else if ($_POST) {
            $_POST['text_search'] = $_POST['text_search'] == 'Search...' ? "" : $_POST['text_search'];
            $this->view->groups = $Groups->search($_POST['text_search'], $_POST['City'], $_POST['State'], $_POST['Country'], false, false);
            $this->view->search = 'Search results'.(!empty($_POST['text_search']) ? ' for "'.$_POST['text_search'].'"' : "");
            $_SESSION['text_search'] = $_POST['text_search'];
        } else {
            $this->view->search = "All Groups on Empowered.org";
            $this->view->groups = $Groups->listAll();
        }
        $paginator = Zend_Paginator::factory($this->view->groups);
        $page = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage(10);
        $paginator->setCurrentPageNumber($page);
        $this->view->groups = $paginator;
        $this->view->group_members = new Brigade_Db_Table_GroupMembers();;
    }

    public function loadlocationsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $ContactInformation = new Brigade_Db_Table_ContactInformation();
        if ($_REQUEST['field'] == 'state') {
        $CountryRegions = $ContactInformation->getRegions($_REQUEST['location']);
            echo '<option value="0" selected>All</option>';
            foreach($CountryRegions as $Country) {
                echo '<option value="'.$Country['RegionId'].'">'.$Country['Region'].'</option>';
            }
        } else {
        $RegionCities = $ContactInformation->getCities($_REQUEST['location']);
            echo '<option value="0" selected>All</option>';
            foreach($RegionCities as $City) {
                echo '<option value="'.$City['CityId'].'">'.$City['City'].'</option>';
            }
        }
    }

    public function membersAction() {
        $params        = $this->_getAllParams();
        $Groups        = new Brigade_Db_Table_Groups();
        $GroupMembers  = new Brigade_Db_Table_GroupMembers();
        $Organizations = new Brigade_Db_Table_Organizations();
        $group         = Group::get($params['GroupId']);

        $this->view->headTitle(stripslashes($group->name).' | Members');

        $this->view->list = $params['List'];
        $search           = !empty($params['search_text']) ? $params['search_text'] : null;
        //get only active members
        $this->view->members = Member::getListByGroup($group, array(1), $search);

        //coun pending requests
        $this->view->pendingRequests = Group::countPendingRequests($params['GroupId']);
        $this->view->searchtxt       = '';
        if (!empty($params['search_text'])) {
            $this->view->searchtxt =  preg_replace('/\s\s+/', ' ', $params['search_text']);
        }

        // check if user is a member of this organization to display the button "become a member"
        $this->view->is_member        = false;
        $this->view->waiting_approval = false;
        $this->view->joinlink         = 'href="#"';
        $this->view->group            = $group;
        if ($this->view->isLoggedIn) {
            if ($group->isMember($this->sessionUser)) {
                $this->view->is_member = true;
            }
            if (!$this->view->is_member) {
                $deletedMember = $group->getMember($this->sessionUser);
                if ($group->hasMembershipPendingReq($this->sessionUser)) {
                   $this->view->waiting_approval = true;
                } else if ($deletedMember && $deletedMember->isDeleted) {
                    $this->view->is_member = true;
                } else {
                   $this->view->joinlink = 'href="javascript:joinGroup(\''.$group->id.'\', \''.$_SESSION['UserId'].'\')"';
                }
            }
        } else {
            $this->view->joinlink = 'href="javascript:;" class="join"';
        }
        $this->view->render('group/memberstoolbox.phtml');
        $this->getHeaderMedia($group);

        //pagination
        $_REQUEST['URLName']   = $group->urlName;
        $_REQUEST['subpage']   = 'members';
        $this->view->paginator = Zend_Paginator::factory($this->view->members);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Members');
        $this->view->currentTab = 'members';

        $this->renderPlaceHolders();
    }

    /**
     * Show admins of the group
     */
    public function leadershipAction() {
        $params = $this->_getAllParams();
        $group  = Group::get($params['GroupId']);
        $search = !empty($params['search_text']) ? $params['search_text'] : null;

        $this->view->headTitle(stripslashes($group->name).' | Leaders');
        $admins = $group->getAdminsRoles($search);

        //coun pending requests
        $this->view->group           = $group;
        $this->view->pendingRequests = Group::countPendingRequests($params['GroupId']);
        $this->view->searchtxt       = '';
        if (!empty($params['search_text'])) {
            $this->view->searchtxt = preg_replace('/\s\s+/', ' ', $params['search_text']);
        }

        $this->view->render('group/memberstoolbox.phtml');
        $this->getHeaderMedia($group);

        //pagination
        $_REQUEST['URLName']   = $group->urlName;
        $_REQUEST['subpage']   = 'leadership';
        $this->view->paginator = Zend_Paginator::factory($admins);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Administrators');
        $this->view->currentTab = 'members';

        $this->renderPlaceHolders();
    }

    public function pendingmembersrequestsAction() {
        $this->_helper->layout->disableLayout();

        $parameters =  $this->_getAllParams();

        if ($_POST) {
            $this->_helper->viewRenderer->setNoRender();
            if ($parameters['accept']) {
                $member = Group::acceptMembership(
                    $parameters['GroupId'],
                    $parameters['UserId']
                );
                if ($member) {
                    $this->infusionSoftIntegration($member);
                    $this->salesforceMemberIntegration($member);
                }
            } else {
                Group::denyMembership(
                    $parameters['GroupId'],
                    $parameters['UserId']
                );
            }
        } else {
            $this->view->members = Group::getMembersPendingRequests($parameters['GroupId']);
            $this->render('pending_requests');
        }
    }

    public function addmembersAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters            = $this->_getAllParams();
        $Users                 = new Brigade_Db_Table_Users();
        $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(
            new Brigade_Db_Table_Users(),
            'email'
        );

        $group = Group::get($parameters['GroupId']);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Add Members');
        $this->view->group      = $group;

        $this->renderPlaceHolders();

        if ($_POST) {
            if (!empty($_POST['emails'])) {
                preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $_POST['emails'], $emails);
                $this->view->emails = $emails[0];
                foreach ($emails[0] as $email) {
                    $email = is_array($email) ? $email[0] : $email;
                    if ($unique_emailvalidator->isValid($email)) {
                        $newUser = User::getByEmail($email);
                        if (empty($newUser)) {
                            $name = explode("@", $email);
                            $URLName = $this->createURLName($name[0], "");
                            $Password = $this->generatePassword();
                            $UserId = $Users->addUser(array(
                                'FirstName' => $email,
                                'LastName' => "",
                                'FullName' => $email,
                                'Email' => $email,
                                'Password' => $Password,
                                'URLName' => $URLName,
                                'Active' => 0,
                                'FirstLogin' => 0
                            ), false);
                            $newUser = User::get($UserId);
                        }
                    } else {
                        $newUser = User::getByEmail($email);
                    }

                    // register the user as member of the group
                    if(!$group->addMember($newUser)) {
                        $this->view->invalid_emails = array($newUser->email);
                    } else {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(
                            EventDispatcher::$SEND_UPLOADED_MEMBER,
                            array(
                                $newUser,
                                $group,
                                $this->view->userNew ,
                                $_POST['message']
                            )
                        );
                        $this->view->sent = true;
                    }
                }
            } else if (!empty($_FILES['uploadExcel']) && $_FILES['uploadExcel']['size'] > 0 && empty($_POST['emails'])) {
                require_once 'Brigade/Util/ExcelReader.php';
                $tmpfile = $_FILES['uploadExcel']['tmp_name'];
                $filename = $_FILES['uploadExcel']['name'];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                if ($ext != 'xls') {
                    $this->view->sent = false;
                    $this->view->invalidFormat = true;
                } else {
                    $temp_file_location = realpath(dirname(__FILE__) . '/../../../') . "/public/tmp/$filename";
                    move_uploaded_file($tmpfile, $temp_file_location);
                    $data = new Spreadsheet_Excel_Reader($temp_file_location, false);
                    // convert to array
                    $rows = $data->dumptoarray();
                    // add each user to the users table
                    $invalid = 0;
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
                                $newUser = User::getByEmail($rows[$i][3]);
                                if (empty($newUser)) {
                                    $URLName = $this->createURLName($rows[$i][1], $rows[$i][2]);
                                    $Password = $this->generatePassword();
                                    $UserId = $Users->addUser(array(
                                        'FirstName' => $rows[$i][1],
                                        'LastName' => $rows[$i][2],
                                        'Email' => $rows[$i][3],
                                        'Password' => $Password,
                                        'URLName' => $URLName,
                                        'Active' => 0,
                                        'FirstLogin' => 0
                                    ), false);
                                    $newUser = User::get($UserId);
                                }
                            } else {
                                $newUser = User::getByEmail($rows[$i][3]);
                            }

                            // register the user as member of the group
                            if(!$group->addMember($newUser)) {
                                $this->view->invalid_emails[] = $newUser->email;
                            } else {
                                Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                    EventDispatcher::$SEND_UPLOADED_MEMBER,
                                    array(
                                        $newUser,
                                        $group,
                                        $this->view->userNew ,
                                        $_POST['message']
                                    )
                                );
                                $this->view->sent = true;
                            }
                        }
                    }
                    $this->view->emails = $email_list;
                    $this->view->invalid_emails = $invalid_emails;
                }
            }
            if (!$group->hasUploadedMembers) {
                $group->hasUploadedMembers = true;
                $group->save();
            }
        }
    }

    /**
     * Add admin user for initiative.
     */
    public function addadminAction() {
        if ($this->view->isAdmin) {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            $params = $this->_getAllParams();
            if (empty($params['userId']) && empty($params['groupId'])) {
                $this->_helper->redirector('error', 'error');
            }

            $role = Role::getByUserAndSite($params['userId'], $params['groupId']);
            if (!$role) {
                $role = new Role();
                $role->siteId = $params['groupId'];
                $role->userId = $params['userId'];
                $role->type   = Role::ADMIN;
                $role->level  = 'Group';
                $role->createdById = $this->sessionUser->id;
                $role->save();
            }
        } else {
            $this->_helper->redirector('badaccess', 'error');
        }
    }

    /**
     * Add admin user for initiative.
     */
    public function removeadminAction() {
        if ($this->view->isAdmin) {
            $params = $this->_getAllParams();
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            if (empty($params['userId']) && empty($params['groupId'])) {
                $this->_helper->redirector('error', 'error');
            }

            $role = Role::getByUserAndSite($params['userId'], $params['groupId']);
            if ($role) {
                $role->delete();
            }
        } else {
            $this->_helper->redirector('badaccess', 'error');
        }
    }

    public function addadminsAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        } else if(!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $Organizations = new Brigade_Db_Table_Organizations();
        $Users = new Brigade_Db_Table_Users();
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $Mailer = new Mailer();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
        $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');

        $group = Group::get($parameters['GroupId']);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Add Administrators');

        $this->view->network = $group->organization;
        $this->view->group   = $group;

        $this->renderPlaceHolders();

        if ($_POST) {
            if(isset($group->createdBy)) {
                $creator = $Users->loadInfo($group->createdBy);
                $creator = $creator['FullName'];
            }
            if (!empty($_POST['emails'])) {
                preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $_POST['emails'], $emails);
                $this->view->emails = $emails[0];
                foreach ($emails[0] as $email) {
                    if ($unique_emailvalidator->isValid($email)) {
                        $name = explode("@", $email);
                        $URLName = $this->createURLName($name[0], "");
                        $Password = $this->generatePassword();
                        $UserId = $Users->addUser(array(
                            'FirstName' => "",
                            'LastName' => "",
                            'Email' => $email,
                            'Password' => $Password,
                            'URLName' => $URLName,
                            'Active' => 0,
                            'FirstLogin' => 0
                        ), false);

                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$ADDED_NEW_ADMIN,
                                   array($email, $email, $group->name, isset($creator) ? $creator : NULL, $Password, $_POST['message'], $this->view->userNew));

                        $this->view->sent = true;
                    } else {
                        $userInfo = $Users->findBy($email);
                        $UserId = $userInfo['UserId'];

                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$ADDED_EXISTING_ADMIN,
                                   array($email, $userInfo['FirstName'], $group->name, isset($creator) ? $creator : NULL, $_POST['message'], $this->view->userNew));

                        $this->view->sent = true;
                    }

                    $member = $group->getMember(User::get($UserId));
                    if ($member) {
                        $member->setAdmin(true);
                    }

                    if (!$UserRoles->isUserRoleExists($group->id, $UserId)) {
                        $UserRoles->addUserRole(array(
                            'SiteId' => $group->id,
                            'UserId' => $UserId,
                            'RoleId' => 'ADMIN',
                            'Level' => 'Group'
                        ));
                    }
                }
            } else if (!empty($_FILES['uploadExcel']) && $_FILES['uploadExcel']['size'] > 0 && empty($_POST['emails'])) {
                require_once 'Brigade/Util/ExcelReader.php';
                $tmpfile = $_FILES['uploadExcel']['tmp_name'];
                $filename = $_FILES['uploadExcel']['name'];
                $temp_file_location = realpath(dirname(__FILE__) . '/../../../') . "/public/tmp/$filename";
                move_uploaded_file($tmpfile, $temp_file_location);

                if (is_readable($temp_file_location)) {
                    try {

                        $data = new Spreadsheet_Excel_Reader($temp_file_location, false);
                        // convert to array
                        $rows = $data->dumptoarray();
                        // add each user to the users table
                        if (isset($group->createdBy)) {
                            $creator = $Users->loadInfo($group->createdBy);
                            $creator = $creator['FullName'];
                        }
                        $invalid = 0;
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
                                        'Active' => 0,
                                        'FirstLogin' => 0
                                    ), false);

                                    //all FirstLogin info can probably be removed

                                    // email a notification to the newly added user with the temp password attached
                                    Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$ADDED_NEW_ADMIN,
                                           array($rows[$i][3], $rows[$i][1], $group->name, isset($creator) ? $creator : NULL, $Password));

                                    $this->view->sent = true;
                                } else {
                                    $userInfo = $Users->findBy($rows[$i][3]);
                                    $UserId = $userInfo['UserId'];
                                    Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$ADDED_EXISTING_ADMIN,
                                        array($rows[$i][3], $rows[$i][1], $group->name, isset($creator) ? $creator : NULL));

                                    $this->view->sent = true;
                                }

                                // register the user as member of the group
                                if (!empty($UserId) && !$GroupMembers->isMemberExists($group->id, $UserId)) {
                                    $GroupMembers->AddGroupMember(array(
                                        'GroupId' => $group->id,
                                        'UserId' => $UserId,
                                        'isAdmin' => 1
                                    ));
                                }

                                if (!$UserRoles->isUserRoleExists($group->id, $UserId)) {
                                    $UserRoles->addUserRole(array(
                                        'SiteId' => $group->id,
                                        'UserId' => $UserId,
                                        'RoleId' => 'ADMIN',
                                        'Level' => 'Group'
                                    ));
                                }
                            }
                        }
                        $this->view->emails = $email_list;
                        $this->view->invalid_emails = $invalid_emails;
                    } catch (Exception $ex) {
                        $this->view->fileError="The file has a wrong format.";
                    }
                } else {
                    $this->view->fileError="The file is not readable.";
                }
            }
            if (!$group->hasAssignedAdmins) {
                $Groups->editGroup($group->id, array('hasAssignedAdmins' => 1));
            }
        }

    }

    public function photosAction() {
        $parameters     =  $this->_getAllParams();
        $Groups         =  new Brigade_Db_Table_Groups();
        $Photos         =  new Brigade_Db_Table_Photo();

        // get project, group, program, & network info
        if(isset($parameters['ProjectId'])) {
            $project  =  Project::get($parameters['ProjectId']);
            $group    =  $project->group;

            $this->view->headTitle(stripslashes($project->name).' | Photos');

        } else {
            $group    =  Group::get($parameters['GroupId']);

            $this->view->headTitle(stripslashes($group->name).' | Photos');

            if(count($group->initiatives)) {
                $project =  Project::get($group->initiatives[0]->id);
            }
        }

        $this->getHeaderMedia($group);

        if(isset($parameters['PhotoId'])) {
            $this->view->photo = $Photos->loadInfo($parameters['PhotoId']);
        } else if(isset($project) && count($project->photos)) {
            $this->view->photo = $Photos->loadInfo($project->photos[0]->id);
        }

        //breadcrumb
        if(isset($project)) {
            $this->view->breadcrumb = $this->view->breadcrumbHelper($project, 'Albums');
        } else {
            $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Albums');
        }

        $this->view->currentTab = 'photos';
        $this->view->network    = $group->organization;
        $this->view->group      = $group;
        if(isset($project)) {
            $this->view->project = $project;
        }

        $this->renderPlaceHolders();
        $this->view->render('photos/upload.phtml');
    }

    public function acceptinviteAction() {
        if ($this->_helper->authUser->isLoggedIn()) {
            header("location: /".$this->view->userNew->urlName);
        }
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        if ($_POST) {
            $this->view->valid_invite = true;
            extract($_POST);
            $validator = new Zend_Validate_EmailAddress();
            $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
            $error_message = array();
            if ($firstname == '' || $firstname == 'First Name') {
                $error_message[] = 'Please specify your first name.';
            } else {
                $this->view->firstname = $firstname;
            }
            if ($lastname == '' || $lastname == 'Last Name') {
                $error_message[] = 'Please specify your last name.';
            } else {
                $this->view->lastname = $lastname;
            }
            if ($email == '' || $email == 'Email') {
                $error_message[] = 'Please specify your email address.';
            } else if (!$validator->isValid($email)) {
                $error_message[] = 'Please specify a valid email address.';
            } else if (!$unique_emailvalidator->isValid($email)) {
                $error_message[] = "Email address $email already exists.";
            } else {
                $this->view->email = $email;
            }
            if ($password == '' || $password == 'Password') {
                $error_message[] = 'Please specify your password.';
            } else {
                $this->view->password = $password;
            }
            if (count($error_message) < 1) {
                $LookupTable = new Brigade_Db_Table_LookupTable();
                $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $firstname.' '.$lastname);
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
                    'Active' => 1,
                    'URLName' => str_replace(" ", "-", $URLName)
                );
                $Users = new Brigade_Db_Table_Users();
                $UserId = $Users->addUser($newUser, false);

                // add group member
                $GroupMembers->AddGroupMember(array(
                    'GroupId' => $GroupId,
                    'UserId' => $UserId
                ));

                // log the site activity
                $SiteActivities = new Brigade_Db_Table_SiteActivities();
                $SiteActivities->addSiteActivity(array(
                    'SiteId' => $GroupId,
                    'ActivityType' => 'Group Member Joined',
                    'CreatedBy' => $UserId,
                    'ActivityDate' => date('Y-m-d H:i:s'),
                    'Link' => "/$URLName",
                ));

                $auth = Zend_Auth::getInstance();
                $authAdapter = new Brigade_Util_Auth();
                $authAdapter->setIdentity($email)->setCredential($password);
                $authResult = $auth->authenticate($authAdapter);
                if ($authResult->isValid()) {
                    $userInfo = $authAdapter->_resultRow;
                    if ($userInfo->Active == 1) {
                        //save userinfo in session
                        $userInfo->Password = '';
                        $_SESSION['FullName'] = $userInfo->FirstName." ".$userInfo->LastName;
                        $_SESSION['UserId'] = $userInfo->UserId;
                    }
                }

                header("location: /group/joinstep2/$GroupId/$UserId");
            } else {
                $errors = implode("<br>", $error_message);
                $this->view->error_message = $errors;
            }
        } else if (isset($parameters['GroupId']) && isset($parameters['ActivationCode'])) {
            $GroupId = $parameters['GroupId'];
            $this->view->ActivationCode = $parameters['ActivationCode'];
            $this->view->data = $Groups->loadInfo1($GroupId);
            $this->view->valid_invite = true;
            $this->view->valid_invite = $GroupEmailAccounts->verifyEmail($parameters['GroupId'], $parameters['ActivationCode']);
            if ($this->_helper->authUser->isLoggedIn() && $this->view->valid_invite) {
                $GroupMembers->AddGroupMember(array(
                    'GroupId' => $parameters['GroupId'],
                    'UserId' => $_SESSION['UserId']
                ));
            $this->view->data = $Groups->loadInfo1($parameters['GroupId']);
            } else if ($this->view->valid_invite) {
                $this->view->email = $this->view->valid_invite['Email'];
            }
        }
    }

    public function acceptinvite2Action() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        if (isset($parameters['GroupId']) && isset($parameters['ActivationCode'])) {
            $GroupId = $parameters['GroupId'];
            $ActivationCode = $parameters['ActivationCode'];
            $data = $Groups->loadInfo1($GroupId);
            $valid_invite = $GroupEmailAccounts->verifyEmail($parameters['GroupId'], $parameters['ActivationCode']);
            if ($valid_invite) {
                $GroupMembers->AddGroupMember(array(
                    'GroupId' => $parameters['GroupId'],
                    'UserId' => $parameters['UserId']
                ));
            }
            header("location: /".$data['URLName']."/participate");
        }
    }

    public function joinstep2Action() {
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $Groups = new Brigade_Db_Table_Groups();
        if ($_POST) {
            extract($_POST);
            $URLName = (!empty($_POST['URLName']) && $_POST['URLName'] != 'www.empowered.org/') ? $_POST['URLName'] : "";
            $LookupTable = new Brigade_Db_Table_LookupTable();
            if (trim($URLName) != "") {
                $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), $URLName);
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
                    $redirect = "/group/joincropimage/?GroupId=$GroupId&UserId=$UserId";
                } else {
                    $error_message = 'Please upload .jpeg images only';
                }
            } else if ($fileSize > 2097152) {
                $error_message = 'Please select image with file size not greater than 2MB';
            } else if ($type != 'image/jpeg' && $fileSize > 0 && $fileSize < 2097152) {
                $error_message = 'Please upload .jpeg images only';
            }
            if (isset($error_message)) {
                $this->view->error_message = $error_message;
            } else {
                $data = array('AboutMe' => $passion, 'Gender' => isset($gender) ? $gender : 0, 'Location' => $location);
                if (!empty($URLName)) {
                    $data['URLName'] = $URLName;
                }
                $Users->edit($UserId, $data);
                $groupInfo = $Groups->loadInfo1($GroupId);
                header("location: ".(isset($redirect) ? $redirect : "/".$groupInfo['URLName']));
            }
        } else if (isset($parameters['GroupId']) && isset($parameters['UserId'])) {
            $this->view->UserId = $parameters['UserId'];
            $this->view->GroupId = $parameters['GroupId'];
            $this->view->userInfo = $Users->loadInfo($parameters['UserId']);
            $this->view->groupInfo = $Groups->loadInfo1($parameters['GroupId']);
        }
    }

    public function joincropimageAction() {
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        if ($_POST) {
            extract($_POST);
            $ImageCrop = new Brigade_Util_ImageCrop();
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/resized_pic.jpg";
            $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/images/users/".strtolower($UserId).".jpg";
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
            $groupInfo = $Groups->loadInfo1($GroupId);
            header("location: /".$groupInfo['URLName']);
        } else if (isset($parameters['GroupId']) && isset($parameters['UserId'])) {
            $this->view->GroupId = $parameters['GroupId'];
            $this->view->UserId = $parameters['UserId'];
        }
    }

    public function managemembersAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        if ($_POST) {
            if (isset($_POST['accept_request'])) {
                foreach ($_POST['accept_request'] as $MembershipRequestId) {
                    $GroupMembershipRequest->acceptMembershipRequest($MembershipRequestId);
                    $membershipInfo = $GroupMembershipRequest->loadInfo($MembershipRequestId);
                    $GroupMembers->AddGroupMember(array(
                        'GroupId' => $_POST['GroupId'],
                        'UserId' => $membershipInfo['UserId'],
                    ));
                }
            }
            if (isset($_POST['deny_request'])) {
                foreach ($_POST['deny_request'] as $MembershipRequestId) {
                    $GroupMembershipRequest->denyMembershipRequest($MembershipRequestId);
                }
            }
            if (isset($_POST['is_admin'])) {
                $GroupMembers->setAdminStatus("", 0, $_POST['GroupId']);
                foreach ($_POST['is_admin'] as $MemberId) {
                    $GroupMembers->setAdminStatus($MemberId, 1);
                }
            }
            if (isset($_POST['delete_member'])) {
                foreach ($_POST['delete_member'] as $MemberId) {
                    $GroupMembers->deleteMember($MemberId);
                }
            }
            if (isset($_POST['MemberId'])) {
                foreach ($_POST['MemberId'] as $MemberId) {
                    if (isset($_POST["new_title_".$MemberId]))
                    $GroupMembers->setMemberTitle($MemberId, $_POST["new_title_".$MemberId]);
                }
            }
            if (isset($_POST['MemberId'])) {
                foreach ($_POST['MemberId'] as $MemberId) {
                    if (isset($_POST["edit_title_".$MemberId]))
                    $GroupMembers->setMemberTitle($MemberId, $_POST["edit_title_".$MemberId]);
                }
            }
            if (isset($_POST['delete_tile'])) {
                foreach ($_POST['delete_tile'] as $MemberId) {
                    $GroupMembers->setMemberTitle($MemberId, "");
                }
            }
            if (isset($_POST['undo_deny'])) {
                foreach ($_POST['undo_deny'] as $MembershipRequestId) {
                    $GroupMembershipRequest->denyMembershipRequest($MembershipRequestId, false);
                }
            }
            if (isset($_POST['undo_delete'])) {
                foreach ($_POST['undo_delete'] as $MemberId) {
                    $GroupMembers->EditGroupMember($MemberId, array('isDeleted' => 0));
                }
            }
        }
        if (isset($parameters['GroupId'])) {
            $GroupId = $parameters['GroupId'];
            $Groups = new Brigade_Db_Table_Groups();
            $this->view->data = $Groups->loadInfo1($GroupId);
            // load membership requests
            $this->view->membership_requests = $GroupMembershipRequest->getMembershipRequests($GroupId);
            // load group members
            $this->view->members = $GroupMembers->getGroupMembers($GroupId);
            // load denied membership request and deleted members
            $this->view->deleted_members = $GroupMembers->getGroupMembers($GroupId, array(0,1), 1);
            $this->view->denied_membership_requests = $GroupMembershipRequest->getMembershipRequests($GroupId, 1);
        }
    }

    public function joinrequestAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters             = $this->_getAllParams();
        $GroupMembers           = new Brigade_Db_Table_GroupMembers();
        $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
        $SiteActivities         = new Brigade_Db_Table_SiteActivities();

        if (isset($_POST['GroupId']) && isset($_POST['UserId'])) {
            $group = Group::get($_POST['GroupId']);
            // double check if user is already a member of this group
            if (!$group->isOpen) {
                if ($group->hasMembershipPendingReq($this->view->userNew)) {
                    echo 'You have already sent a membership request to this chapter, please wait for the admin to accept it.';
                } else if ($group->addMember($this->view->userNew)) {

                    // log the site activity
                    $SiteActivities->addSiteActivity(array(
                        'SiteId' => $group->id,
                        'ActivityType' => 'Group Member Joined',
                        'CreatedBy' => $this->view->userNew->id,
                        'ActivityDate' => date('Y-m-d H:i:s'),
                    ));

                    // send notification message to group admin(s)
                    $groupAdmins = $GroupMembers->getGroupAdmins($group->id);
                    foreach($groupAdmins as $admin) {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_MEMBER_NOTIFICATION,
                                   array($admin['Email'], stripslashes($group->name), stripslashes($this->view->userNew->fullName), ""));
                    }
                    echo 'Your membership request has been sent! Please wait for the admin to accept it.';
                } else {
                    echo 'Your membership request has been sent! Please wait for the admin to accept it.';
                }
            } else {
                if (!$group->isMember($this->view->userNew)) {
                    $GroupMembers->AddGroupMember(array(
                        'GroupId'   => $group->id,
                        'UserId'    => $this->view->userNew->id,
                        'NetworkId' => !empty($group->organizationId) ? $group->organizationId : null,
                        'isAdmin'   => (Role::isAdmin($this->view->userNew->id, $group->id))
                    ));

                    // log the site activity
                    $SiteActivities->addSiteActivity(array(
                        'SiteId'       => $group->id,
                        'ActivityType' => 'Group Member Joined',
                        'CreatedBy'    => $this->view->userNew->id,
                        'ActivityDate' => date('Y-m-d H:i:s'),
                    ));

                    if (count($group->upcomingInitiatives)) {
                        echo "participate";
                    } else {
                        echo "Congratulations! You have joined ".stripslashes($group->name);
                    }
                } else {
                    echo "You are already a member of ".stripslashes($group->name);
                }
            }
        }
    }

    public function participateAction() {
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $Projects = new Brigade_Db_Table_Brigades();
        $this->view->donations = new Brigade_Db_Table_ProjectDonations();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
        if (isset($parameters['GroupId'])) {
            $GroupId = $parameters['GroupId'];
            $this->view->data = $Groups->loadInfo1($GroupId);
            $this->view->brigades = $Groups->loadBrigades($GroupId, "upcoming");
            $this->view->campaigns = $Projects->listGroupCampaigns($GroupId, 'active');
        }
    }

    /**
     * Send email to volunteers of initiatives in the group
     * Call from toolbar in group page "email volunteers"
     */
    public function sendemailAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();

        $Groups = new Brigade_Db_Table_Groups();
        $Brigades = new Brigade_Db_Table_Brigades();
        $Programs = new Brigade_Db_Table_Programs();
        $Organizations = new Brigade_Db_Table_Organizations();
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $Volunteers = new Brigade_Db_Table_Volunteers();

        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        if(isset($params['ProjectId'])) {
            $project             = Project::get($params['ProjectId']);
            $this->view->project = $project;
            $siteName            = $project->name;

            if(!empty($project->groupId)) {
                $group = $project->group;
            } else if(!empty($project->userId)) {
                $this->view->data = $project;
                $this->view->Type = isset($params['Type']) ? $params['Type'] : "";
                $this->view->members = $Volunteers->getProjectVolunteers($params['ProjectId'], 'active');

                $emails   = array();
                $emails[] = array('Email' => $this->view->userNew->email);
                $admin_emails = $UserRoles->getSiteAdmin($params['ProjectId']);
                foreach($admin_emails as $email) {
                    $emails[] = $email;
                }
                $this->view->emails = $emails;
                $this->view->header_title = $this->view->data->name;
            }
        } else if(isset($params['GroupId'])) {
            $group    = Group::get($params['GroupId']);
            $siteName = $group->name;
        }

        if(isset($group)) {

            if (isset($params['Type']) && $params['Type'] == 'fundraisers') {
                $this->view->Type = 'fundraisers';
                $this->view->members = $Volunteers->getVolunteersByGroup($group->id, 'all', NULL, 1);
                $this->view->activities = $Brigades->loadGroupProjects($group->id, "all", NULL, 'p.Name', 4);
            } else if (isset($params['Type']) && $params['Type'] == 'volunteers') {
                $this->view->Type = 'volunteers';
                $this->view->members = $Volunteers->getVolunteersByGroup($group->id, "all");
                $this->view->activities = $Brigades->loadGroupProjects($group->id, "all", NULL, 'p.Name', 0);
            } else {
                $this->view->Type = 'members';
                $this->view->members = $group->getActiveEmailMembers();
                $this->view->activities = $Brigades->loadGroupProjects($group->id, "all", NULL, 'p.Name', 0);
            }
            $this->view->campaigns = $Brigades->loadGroupProjects($group->id, "all", NULL, 'p.Name', 1);
            $this->view->emails = GroupEmail::getByGroup($group);

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                (isset($project)) ? $project : $group
            );
            $this->view->group = $group;
            $this->renderPlaceholders();
        }

        if (isset($params['actionEmail']) && $params['actionEmail'] == "Send Email") {
            extract($_POST);

            if ($sendTo == "Group") {
                if($this->view->Type == 'members') {
                    $members = $group->getActiveEmailMembers();
                } else if ($this->view->Type == 'fundraisers') {
                    $members = $Volunteers->getVolunteersByGroup($group->id, 'all', NULL, 1);
                } else if ($this->view->Type == 'volunteers') {
                    $members = $Volunteers->getVolunteersByGroup($group->id, 'all');
                }
                foreach ($members as $member) {
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(
                        EventDispatcher::$GROUP_NOTIFICATION,
                        array((isset($member['Email'])) ? $member['Email'] : $member->email, $subject, $message, $sentFrom, $group->name)
                    );
                }
            } else if ($sendTo == "Activity") {
                $Brigades = new Brigade_Db_Table_Brigades();
                foreach($activities as $activity) {
                    if ($Type == 'members') {

                    } else if ($Type == 'fundraisers') {
                        $members = $Volunteers->getVolunteersByGroup($group->id, 'all', NULL, 1, NULL, $activity);
                    } else if ($Type == 'volunteers') {
                        $members = $Volunteers->getVolunteersByGroup($group->id, 'all', NULL, NULL, NULL, $activity);
                    }
                    foreach ($members as $member) {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(
                            EventDispatcher::$GROUP_NOTIFICATION,
                            array($member['Email'], $subject, $message, $sentFrom, $group->name)
                        );
                    }
                }
            } else if ($sendTo == "Campaign") {
                foreach($params['campaigns'] as $campaign) {
                    if ($Type == 'members') {

                    } else if ($Type == 'fundraisers') {
                        $members = $Volunteers->getVolunteersByGroup($group->id, 'all', NULL, 1, NULL, $campaign);
                    } else if ($Type == 'volunteers') {

                    }
                    foreach ($members as $member) {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(
                            EventDispatcher::$GROUP_NOTIFICATION,
                            array($member['Email'], $subject, $message, $sentFrom, $group->name)
                        );
                    }
                }
            } else if ($sendTo == "Members" || strpos("specific", $sendTo) > -1) {
                foreach ($members as $email) {
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(
                        EventDispatcher::$GROUP_NOTIFICATION,
                        array($email, $subject, $message, $sentFrom, $siteName)
                    );
                }
            } else if ($sendTo == "All") {
                $members = $Volunteers->getProjectVolunteers($ProjectId, 'active');
                foreach ($members as $email) {
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(
                        EventDispatcher::$GROUP_NOTIFICATION,
                        array($email, $subject, $message, $sentFrom, $siteName)
                    );
                }
            }
            $sentTo = array('Group' => 'entire chapter members', 'Activity' => 'selected volunteer activities', 'Campagin' => 'selected fundraising campaign members', 'Members' => 'selected group member', $sendTo => $sendTo);
            $this->view->sent = true;
            $this->view->message = "Your message has been successfully sent to ".$sentTo[$sendTo].".";
        }
    }

    /**
     * Add emails to group to verify by user receipt.
     * Call by ajax.
     */
    public function addemailsvalidationAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->_getAllParams();

        $group = Group::get($params['GroupId']);
        $FromEmails = str_replace(" ", "", trim($params['FromEmails']));
        $FromEmails = explode(",", $FromEmails);
        foreach ($FromEmails as $email) {
            $gemail          = new GroupEmail();
            $gemail->email   = $email;
            $gemail->groupId = $group->id;
            $gemail->save();
            if ($this->view->envUsername == 'admin') {
                $envSite = "www";
            } else if ($this->view->envUsername == 'dev') {
                $envSite = "dev";
            } else {
                $envSite = "local";
            }

            $Link = "$envSite.empowered.org/group/verify-email/{$group->id}/{$gemail->verificationCode}";
            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                EventDispatcher::$GROUP_EMAIL_VERIFICATION,
                array($gemail->email, $group->name, $Link, 'no-reply@empowered.org')
            );
        }
    }

    public function verifyemailAction() {
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        if (isset($parameters['GroupId']) && isset($parameters['VerificationCode'])) {
            $groupInfo = $Groups->loadInfo($parameters['GroupId']);
            if ($GroupEmailAccounts->verifyEmail($parameters['GroupId'], $parameters['VerificationCode'])) {
                $this->view->verified = true;
                $this->view->message = "Your email has been successfully verified, it is now added to ".$groupInfo['GroupName']." chapter email accounts.";
            } else {
                $this->view->verified = false;
                $this->view->message = "Invalid verification link, please check your email and click the verification link provided";
            }
            $this->view->data = $groupInfo;
        } else {
            $this->view->verified = false;
            $this->view->message = "Invalid verification link, please check your email and click the verification link provided";
        }
    }

    public function addbannerAction() {
        $parameters = $this->_getAllParams();
        $group      = Group::get($parameters['GroupId']);
        $Groups     = new Brigade_Db_Table_Groups();

        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        if ($_POST && $_POST['action'] == 'upload' && isset($_FILES['GroupBanner'])) {
            extract($_POST);
            $MediaSize = $_FILES['GroupBanner']['size'];
            $tmpfile   = $_FILES['GroupBanner']['tmp_name'];
            $filename  = $_FILES['GroupBanner']['name'];
            $type      = str_replace('image/', '', $_FILES['GroupBanner']['type']);
            if ($MediaSize > 0) {

                if (empty($group->bannerMediaId)) {
                    // save media
                    $Media = new Brigade_Db_Table_Media();
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $group->urlName."-banner.jpg",
                        'UploadedMediaName' => $filename,
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $group->id
                    ));

                    // update group BannerMediaId
                    $Groups->editGroup($GroupId, array('BannerMediaId' => $MediaId));
                } else {
                    $MediaId = $group->bannerMediaId;
                    $Media = new Brigade_Db_Table_Media();
                    $Media->editMedia($MediaId, array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $group->urlName."-banner.jpg",
                        'UploadedMediaName' => $filename,
                    ));
                }

                //Get the file information
                $ImageCrop = new Brigade_Util_ImageCrop();
                $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_{$MediaId}.jpg";

                // Check if file size does not exceed 2MB
                move_uploaded_file($tmpfile, $temp_image_location);
                $width = $ImageCrop->getWidth($temp_image_location);
                $height = $ImageCrop->getHeight($temp_image_location);

                //Scale the image if it is greater than the width set above
                $scale = 1;
                $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale,$type);

                $this->view->action = 'crop';
                $this->view->BannerMediaId = $MediaId;
                $this->view->GroupId = $group->id;
                $this->view->width = $width;
                $this->view->height = $height;
            }
        } else if ($_POST && $_POST['action'] == 'crop') {
            extract($_POST);
            $ImageCrop = new Brigade_Util_ImageCrop();
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$BannerMediaId.jpg";
            $image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/banner/{$group->urlName}-banner.jpg";
            $x = $_POST["x"];
            $y = $_POST["y"];
            $width = $_POST["w"];
            $height = $_POST["h"];
            if($width > 1045){
                $scale = 1045 / $width;
            } else {
                $scale = 1;
            }
            // get the current selected box width & height
            $ImageCrop->resizeThumbnailImage($image_location, $temp_image_location, $width, $height, $x, $y, $scale);

            // delete the temp file
            if (file_exists($temp_image_location)) {
                unlink($temp_image_location);
            }

            header("location: /".$group->urlName);
        } else {
            $this->view->GroupId = $parameters['GroupId'];
            $this->view->action = 'upload';
        }
    }

    public function removebannerAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $Media = new Brigade_Db_Table_Media();
        $Groups = new Brigade_Db_Table_Groups();
        $SiteMedia = new Brigade_Db_Table_MediaSite();
        if ($_POST) {
            $groupInfo = $Groups->loadInfo1($_POST['GroupId']);
            // delete table from media tables
            $SiteMedia->deleteSiteMedia($groupInfo['BannerMediaId']);
            $Media->deleteMedia($groupInfo['BannerMediaId']);
            // set BannerMediaId to NULL in groups table
            $Groups->editGroup($_POST['GroupId'], array('BannerMediaId' => ''));
            // display success message
            echo "Chapter banner has been successfully removed.";
        }
    }

    public function morefeedsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $ActivitiesComments = new Brigade_Db_Table_SiteActivityComments();
        $Brigades = new Brigade_Db_Table_Brigades();
        $Users = new Brigade_Db_Table_Users();
        $activities = $SiteActivities->getRecentSiteActivity($parameters['GroupId'], 'Group', $_POST['limit'], $_POST['offset']);
        foreach ($activities as $activity) {
            $avatar = $comment_box = '';
            $comments_list = $ActivitiesComments->getSiteActivityComments($activity['SiteActivityId']);
            $comments = "<ul id='ul_".$activity['SiteActivityId']."'".(count($comments_list) > 0 ? "" : "style='display:none'").">";
            foreach($comments_list as $comment) {
                $comments .= '<li><table><tr><td style="width:34px;"><img src="/profile/loadimage?UserId='.$comment['UserId'].'" /></td><td style="width:316px;"><span class="comment"><a href="/'.$comment['URLName'].'">'.stripslashes($comment['FirstName']).' '.stripslashes($comment['LastName']).'</a>&nbsp;&nbsp;'.stripslashes($comment['Comment']).'<br><span class="time">'.$this->getDateFormat($comment['CommentedOn']).'</span></span></td></tr></table></li>';
            }
            $comments .= "</ul>";
            if (isset($_SESSION['UserId'])) {
                $userInfo = $Users->findBy($_SESSION['UserId']);
                $comment_link = "<a href='javascript:;' id='commentlink_".$activity['SiteActivityId']."' style='float:right;'>Comment</a>";
                $avatar = "<img id='avatar_".$activity['SiteActivityId']."' src='/profile/loadimage?UserId=".$_SESSION['UserId']."' height='25px' width='25px' style='float:left; margin-right:3px; vertical-align:top; display:none;' />";
                $comment_box = '<div style="padding:3px; width:90%; margin:0 0 3px 34px; float:left;">'.$comment_link.$avatar.'<textarea id="comment_'.$activity['SiteActivityId'].'" cols="50" rows="1" style="float:left; font-size:11px; height:20px; width:98%; display:none;">Write a comment...</textarea><input id="submit_'.$activity['SiteActivityId'].'" class="btn btngreen" style="display:none; float:right;" type="submit" value="Comment"/></div>';
            }
            if ($activity['ActivityType'] == 'Uploads') {
                $display = "<p style='margin-bottom:-20px;'><img src='".$this->view->contentLocation."public/images/ico/photo.gif'>&nbsp;&nbsp;".$activity['TotalCount'].($activity['TotalCount'] > 1 ? " photos were " : " photo was ")."added ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'File Added') {
                $display = "<p style='margin-bottom:-20px;min-height:20px;'><img src='" . $this->view->contentLocation . "public/images/ico/photo.gif' />&nbsp;&nbsp;" . $activity['TotalCount'] . ($activity['TotalCount'] > 1 ? " files were " : " file was ") . "added " . $this->getDateFormat($activity['ActivityDate']) . ".</p>";
            } else if ($activity['ActivityType'] == 'User Donation') {
                $brigadeInfo = $Brigades->loadInfo1($activity['SiteId']);
                $display = "<p style='margin-bottom:-20px;'><img src='".$this->view->contentLocation."public/images/ico/donation.gif'>&nbsp;&nbsp;".stripslashes($activity['FirstName'])." ".stripslashes($activity['LastName'])." donated ".$brigadeInfo['Currency'].number_format($activity['Details'])."to the <a href='".$activity['Link']."'>".stripslashes($brigadeInfo['Name'])."</a> brigade ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'Events') {
                //temp fix so that only one event is shown in the feed.
                $activity['TotalCount'] = 1;
                $display = "<p style='margin-bottom:-20px;'><img src='".$this->view->contentLocation."public/images/ico/file.gif'>&nbsp;&nbsp;".$activity['TotalCount'].($activity['TotalCount'] > 1 ? " events were " : " event was ")."added ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'Blogs') {
                $display = "<p style='margin-bottom:-20px;'><img src='".$this->view->contentLocation."public/images/ico/file.gif'>&nbsp;&nbsp;".stripslashes($activity['FirstName'])." ".stripslashes($activity['LastName'])." wrote about an <a href='".$activity['Link']."'>experience</a> ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'Campaign Added') {
                $campaignInfo = $Brigades->loadInfo1($activity['SiteId']);
                $display = "The <a href='/".$campaignInfo['projectLink']."'>".stripslashes($campaignInfo['Name'])."</a> was created ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'Brigade Added') {
                $brigadeInfo = $Brigades->loadInfo1($activity['SiteId']);
                $display = "The <a href='/".$brigadeInfo['projectLink']."'>".stripslashes($brigadeInfo['Name'])."</a> was created ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'Group Updated') {
                $display = "The chapter details were changed ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'Group Member Joined') {
                $userInfo = $Users->loadInfo($activity['CreatedBy']);
                $display = "<a href='".$userInfo['URLName']."'>".stripslashes($userInfo['FullName'])."</a> joined this group ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'Wall Post') {
                $display = "<p style='margin-bottom:-20px;'><table style='margin-bottom:-20px;'><tr><td width=34><img src='/profile/loadimage?UserId=".$activity['CreatedBy']."' width='30' height='30'></td><td><a href='/".$activity['URLName']."'>".stripslashes($activity['FirstName'])." ".stripslashes($activity['LastName'])."</a>&nbsp;&nbsp;".stripslashes($activity['Details'])."<br>".$this->getDateFormat($activity['ActivityDate']).".</td></tr></table>";
            }
            if(!empty($display)) { echo "$display<br><br>$comments$comment_box</p><div class='clear1'></div>"; }
        }
        if (count($activities) <= 5) {
            echo '<script> $("#see-more").hide(); </script>';
        }
    }

    public function activategroupAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $group = Group::get($_POST['GroupId']);
            $group->isActive = 1;
            $group->save();
        }
    }

    public function editinfoAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $group      = Group::get($parameters['GroupId']);

        if (!isset($_POST['GroupName'])) {

            $this->view->program      = $group->program;
            $this->view->organization = $group->organization;
            $this->view->group        = $group;
            $this->view->edit         = true;

            $Countries = new Brigade_Db_Table_Countries();
            $this->view->country_list = $Countries->getAllCountries();

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                                        $group,
                                        'Edit Chapter'
            );

            $this->_helper->layout->setLayout('newlayout');
            $this->_helper->viewRenderer->setRender('create');
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/footer.phtml');
            $this->view->render('group/tabs.phtml');
            $this->view->render('group/header.phtml');
        } else {
            if ($group->contact) {
                $contact               = $group->contact;
                $contact->modifiedById = $this->view->userNew->id;
            } else {
                $contact              = new Contact();
                $contact->siteId      = $group->id;
                $contact->createdById = $this->view->userNew->id;
                $contact->createdOn   = date('Y-m-d H:i:s');
            }
            $contact->countryId   = $parameters['CountryId'];
            $contact->stateId     = $parameters['RegionId'];
            $contact->cityId      = $parameters['CityId'];
            $contact->email       = $parameters['Email'];
            $contact->website     = $parameters['WebAddress'];
            $contact->street      = $parameters['Street'];
            $contact->countryName = $parameters['Country'];
            $contact->regionName  = $parameters['Region'];
            $contact->cityName    = $parameters['City'];
            $contact->save();

            if ($parameters['ProgramId'] != '') {
                $group->programId = $parameters['ProgramId'];
            }
            if (!empty($parameters['isOpen'])) {
                $group->isOpen = true;
            }
            $updateInfusion = false;
            if ($group->name != $parameters['GroupName']) {
                $oldName        = $group->name;
                $updateInfusion = true;
            }
            $group->name        = $parameters['GroupName'];
            $group->description = $parameters['Description'];
            $group->modifiedBy  = $this->view->userNew->id;
            $group->save();

            if ($updateInfusion) {
                //update members the chapter info data
                $this->_updateInfusionSoftChapterInfo($group);
                $this->_updateSalesForceChapterInfo($group, $oldName);
            }

            // log the site activity
            $activity              = new Activity();
            $activity->siteId      = $group->id;
            $activity->type        = 'Group Updated';
            $activity->createdById = $this->view->userNew->id;
            $activity->date        = date('Y-m-d H:i:s');
            $activity->save();

            $this->_helper->redirector->gotoUrl('/'.$group->urlName);
        }
    }

    public function loadlocations2Action() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_REQUEST['field'] == 'state') {
        $Regions = new Brigade_Db_Table_Regions();
            $CountryRegions = $Regions->getCountryRegions($_REQUEST['location']);
            $list = '<option value="0"'.(isset($_REQUEST['selected']) ? "" : " selected").'>All</option>';
            foreach($CountryRegions as $Country) {
                $list .= '<option value="'.$Country['RegionId'].'"'.((isset($_REQUEST['selected']) && $_REQUEST['selected'] == $Country['RegionId']) ? " selected" : "").'>'.$Country['Region'].'</option>';
            }
            echo $list;
        } else {
        $Cities = new Brigade_Db_Table_Cities();
            $RegionCities = $Cities->getRegionCities($_REQUEST['location']);
            $list = '<option value="0" '.(isset($_REQUEST['selected']) ? "" : "selected").'>All</option>';
            foreach($RegionCities as $City) {
                $list .= '<option value="'.$City['CityId'].'"'.((isset($_REQUEST['selected']) && $_REQUEST['selected'] == $City['CityId']) ? " selected" : "").'>'.$City['City'].'</option>';
            }
        echo $list;
        }
    }

    public function deletefileAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $Files = new Brigade_Db_Table_Files();
            $Files->deleteFile($_POST['FileId']);
            echo "File has been successfully deleted.";
        }
    }

    public function updaterequestsAction() {
        if ($_POST) {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
            if ($_POST['action'] == 'accept') {
                $GroupMembershipRequest->acceptMembershipRequest($_POST['MembershipRequestId']);
                $membershipInfo = $GroupMembershipRequest->loadInfo($_POST['MembershipRequestId']);
                $GroupMembers = new Brigade_Db_Table_GroupMembers();
                $GroupMembers->AddGroupMember(array('GroupId' => $membershipInfo['GroupId'], 'UserId' => $membershipInfo['UserId']));
                echo "You have successfully accepted the membership request";
            } else if ($_POST['action'] == 'deny') {
                $GroupMembershipRequest->denyMembershipRequest($_POST['MembershipRequestId']);
                echo "You have successfully denied the membership request";
            } else if ($_POST['action'] == 'undo deny request') {
                $GroupMembershipRequest->denyMembershipRequest($_POST['MembershipRequestId'], 0);
                $GroupMembershipRequest->acceptMembershipRequest($_POST['MembershipRequestId']);
                echo "You have successfully accepted the membership request";
            } else if ($_POST['action'] == 'undo delete') {
                $Volunteers = new Brigade_Db_Table_Volunteers();
                $Volunteers->undoDeleteOrDeny($VolunteerId, array("$status" => 0));
                echo "You have successfully accepted a member";
            } else if ($_POST['action'] == 'undo delete member') {
                $GroupMembers = new Brigade_Db_Table_GroupMembers();
                $GroupMembers->EditGroupMember($_POST['MemberId'], array('isDeleted' => 0));
                echo "You have successfully accepted a member";
            }
        }
    }

    /**
     * Update members status from admin tools
     */
    public function updatemembersAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if (!($this->view->isAdmin)) {
            echo "Error admin missing";
            return;
        }
        $params = $this->_getAllParams();
        $member = Member::get($params['MemberId']);
        if (!$member) {
            echo "Error member missing";
            return;
        }
        if ($_POST['action'] == 'setAdminStatus') {
            $member->setAdmin((bool)$params['value']);
            $this->infusionSoftIntegration($member);
            if ($_POST['value'] == 1) {
                echo "You have successfully added an admin access to a member";
            } else {
                echo "You have successfully removed an admin access to a member";
            }
        } else if ($_POST['action'] == 'deleteMember') {
            $stopped = "";
            if ($member->rebillId) {
                $config = Zend_Registry::get('configuration');

                $member->stopMembership();
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
                $bpay->setRebillId($member->rebillId);
                $bpay->stopRebill();
                if ($bpay->getStatus() == 'stopped') {
                    $stopped = "\n\rThe rebill membership donation payment was stopped.";
                    Zend_Registry::get('logger')->info("Membership::[Stopped] [RebillId: {$member->rebillId}]");
                }
            }
            $member->delete();
            $this->infusionSoftIntegration($member,false);
            echo "You have successfully deleted a member$stopped";
        } else if ($_POST['action'] == 'setMemberTitle') {
            $GroupMembers->setMemberTitle($params['MemberId'], $_POST['value']);
            if ($_POST['value'] != "") {
                echo "You have successfully added a title to a member";
            } else {
                echo "You have successfully removed a title to a member";
            }
        }
    }


    public function emaildonorsAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $Programs = new Brigade_Db_Table_Programs();
        $Organizations = new Brigade_Db_Table_Organizations();
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
        if (isset($parameters['GroupId'])) {
            $group = Group::get($parameters['GroupId']);

            $this->view->donors = $ProjectDonations->getGroupDonors($group->id);
            $this->view->activities = $Groups->loadUpcomingBrigades($group->id, "all", NULL, 'p.Name');
            $this->view->emails = $GroupEmailAccounts->getGroupEmailAccounts($group->id);

            $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Email Donors');

            $this->view->group = $group;
            $this->renderPlaceholders();

        }
        if (isset($_POST['action']) && $_POST['action'] == "Add Emails") {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            if ($this->view->envUsername == 'admin') {
                $envSite = "www";
            } else if ($this->view->envUsername == 'dev') {
                $envSite = "dev";
            } else {
                $envSite = "qat";
            }
            $GroupId = $_POST['GroupId'];
            $From = $_POST['From'];
            $groupInfo = $Groups->loadInfo($GroupId);
            $FromEmails = str_replace(" ", "", trim($_POST['FromEmails']));
            $FromEmails = explode(",", $FromEmails);
            foreach ($FromEmails as $email) {
                $verification_code = $GroupEmailAccounts->AddEmailAccount(array(
                    'GroupId' => $GroupId,
                    'Email' => $email
                ));
                $Link = "$envSite.empowered.org/group/verify-email/$GroupId/$verification_code";
                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_EMAIL_VERIFICATION,
                                   array($email, $groupInfo['GroupName'], $Link, $From));
            }

            echo implode(", ", $FromEmails);
        } else if (isset($_POST['action']) && $_POST['action'] == "Send Email") {
            extract($_POST);
            $groupInfo = $Groups->loadInfo1($GroupId);
            if ($sendTo == "All Donors") {
                $donors = $ProjectDonations->getSiteDonors($GroupId, 'group');
                foreach ($donors as $donor) {
                    if (!empty($donor['SupporterEmail'])) {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array($donor['SupporterEmail'], $subject, $message, $sentFrom, $groupInfo['GroupName']));
                    }
                }
            } else if ($sendTo == "Activity Donors") {
                foreach($activities as $activity) {
                    $donors = $ProjectDonations->getSiteDonors($activity, 'activity');
                    foreach ($donors as $donor) {
                        if (!empty($donor['SupporterEmail'])) {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array($donor['SupporterEmail'], $subject, $message, $sentFrom, $groupInfo['GroupName']));
                        }
                    }
                }
            } else if ($sendTo == "Specific Donors") {
                foreach ($donors as $email) {
                    if (!empty($email)) {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array($email, $subject, $message, $sentFrom, $groupInfo['GroupName']));
                    }
                }
            }
            $sentTo = array('Group' => 'entire group members', 'Activity' => 'selected volunteer activities', 'Members' => 'selected group member');
            $this->view->sent = true;
            $this->view->message = "Your message has been successfully sent to ".$sentTo[$sendTo].".";
        }
    }

    public function toggleviewAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $UserRoles = new Brigade_Db_Table_UserRoles();
            $UserRoles->editUserRole($_POST['UserRoleId'], array('isToggleAdminView' => $_POST['isToggleAdminView']));
        }
    }

    public function donationreceiptAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $ReceiptMessages = new Brigade_Db_Table_ReceiptMessages();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        if (isset($parameters['GroupId'])) {
            $GroupId = $parameters['GroupId'];
            $this->view->data = $Groups->loadInfo($GroupId);
            $this->view->progOrg = $Groups->loadProgOrg($GroupId);
            $this->view->message = $ReceiptMessages->getMessage($GroupId);
        }

        if ($_POST) {
            extract($_POST);

            if ($this->view->message == '') {
                $ReceiptMessages->addMessage($SiteId, $Message);
            } else {
                $ReceiptMessages->editMessage($SiteId, $Message);
            }

            header('location: /' . $this->view->data['URLName'] . '/custom-receipt');
        }
    }

    public function uploadexcelAction() {
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
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            $Mailer = new Mailer();
            $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
            $group = Group::get($_POST['GroupId']);
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
                        'Active' => 0,
                        'FirstLogin' => 0
                    ), false);

                    $newUser = User::get($UserId);
                    $group->addMember($newUser);

                    // email a notification to the newly added user with the temp password attached
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$SEND_UPLOADED_MEMBER,
                                   array($newUser, $group, $this->view->userNew , $_POST['message']));

                } else {
                    $userInfo = $Users->findBy($rows[$i][3]);
                    $UserId = $userInfo['UserId'];
                    $newUser = User::get($UserId);

                    if($group->addMember($newUser)) {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$SEND_UPLOADED_MEMBER,
                                   array($newUser, $group, $this->view->userNew , $_POST['message']));

                    }
                    $invalid++;
                }
            }
            echo '<script> alert("Your members list has been successfully uploaded and all users have been registered on Empowered.org"); </script>';
            if(isset($_POST['PostGroupCheck'])) {
                header("location: /".$group->urlName);
            } else {
                header("location: /".$group->urlName."/members");
            }
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

    /*  Actions for Searching a Chapter */

    public function searchingAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout->setLayout('newlayout');

        $Organizations = new Brigade_Db_Table_Organizations();
        $Groups = new Brigade_Db_Table_Groups();
        $group = Group::get($parameters['GroupId']);

        $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Search');

        $this->view->group = $group;

        $this->renderPlaceholders();

        $this->view->categories = array('all' => 'All Results', 'people' => 'Members', 'activity' => 'Volunteer Activities', 'campaign' => 'Fundraising Campaigns', 'event' => 'Events');
        $this->view->search_icons = array('all' => 'search.png', 'people' => 'people.jpg', 'activity' => 'activities.jpg', 'campaign' => 'campaigns.png', 'event' => 'events.png');

        if (isset($parameters['search_text']) && $parameters['search_text'] != '') {
            $search_results = '';
            $parameters['search_text'] = str_replace("'", "", $parameters['search_text']);
            $parameters['search_text'] = str_replace('"', '', $parameters['search_text']);
            if (!isset($parameters['category']) || $parameters['category'] == 'all') {
                $search_results = $this->searchAll($group->id, $parameters['search_text'], 5);
                } else if (isset($parameters['category'])) {
                    $method = 'search'.ucfirst($parameters['category']);
                    $search_results = array();
                    $results = $this->$method($group->id, $parameters['search_text'], true, 10);
                    foreach($results as $row) {
                        $search_results[] = $row;
                    }
                    $other_results = $this->$method($group->id, $parameters['search_text'], false, 10);
                    foreach($other_results as $row) {
                        $search_results[] = $row;
                    }
                    if (!empty($search_results) && count($search_results) >= 10) {
                        $this->view->total_results = count($this->$method($group->id, $parameters['search_text'], false));
                    }
                    if (empty($search_results)) {
                        if (strpos(strtolower($parameters['search_text']), "santa cruz") !== false) {
                            $search_results = $this->$method($group->id, "santa cruz", false, 10);
                        }
                        if (empty($search_results) && strpos(strtolower($parameters['search_text']), "global") !== false) {
                            $search_results = $this->$method($group->id, "global", false, 10);
                            if (!empty($search_results) && count($search_results) >= 10) {
                                $this->view->total_results = count($this->$method($group->id, "global", false));
                            }
                        }
                        if (empty($search_results) && strpos(strtolower($parameters['search_text']), "brigades") !== false) {
                            $search_results = $this->$method($group->id, "brigades", false, 10);
                            if (!empty($search_results) && count($search_results) >= 10) {
                                $this->view->total_results = count($this->$method($group->id, "brigades", false));
                            }
                        }
                        foreach($search_results as $row) {
                            $search_results[] = $row;
                        }
                    }
                }
                $this->view->search_results = $search_results;
            }
            if(isset($parameters['search_text'])) {
                $this->view->search_text = $parameters['search_text'];
            } else {
                $this->view->search_text = '';
            }
            $this->view->category = isset($parameters['category']) ? $parameters['category'] : 'all';

        }

    public function moreresultsAction() {

        // needs to be set up to have $networkId

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        extract($parameters);
        $category = "search".ucfirst($category);
        $search_results = $this->$category($search_text, true, $limit, $offset);
        if (count($search_results) > 0) {
            foreach($search_results as $item) {
                echo $item;
            }
        } else {
            $search_results = $this->$category($search_text, false, $limit, $offset);
            foreach($search_results as $item) {
                echo $item;
            }
        }
    }

    private function hasResults($GroupId, $search_text) {
        if (count($this->searchActivity($GroupId, $search_text, false, 1))) {
            return true;
        } else if (count($this->searchCampaign($GroupId, $search_text, false, 1))) {
            return true;
        } else if (count($this->searchEvent($GroupId, $search_text, false, 1))) {
            return true;
        } else if (count($this->searchPeople($GroupId, $search_text, false, 1))) {
            return true;
        } else {
            return false;
        }

    }

    private function searchAll($GroupId, $search_text, $limit = NULL) {
        $search_results = array();
        // load perfect match first
        $activities = $this->searchActivity($GroupId, $search_text, true, $limit);
        foreach ($activities as $row) {
            $search_results['activity'][] = $row;
        }
        $campaigns = $this->searchCampaign($GroupId, $search_text, true, $limit);
        foreach ($campaigns as $row) {
            $search_results['campaign'][] = $row;
        }
        $events = $this->searchEvent($GroupId, $search_text, true, $limit);
        foreach ($events as $row) {
            $search_results['event'][] = $row;
        }
        $members = $this->searchPeople($GroupId, $search_text, true, $limit);
        foreach($members as $row) {
            $search_results['people'][] = $row;
        }

        // load other matches
        if (!isset($search_results['activity'])) {
            $activities = $this->searchActivity($GroupId, $search_text, false, $limit);
            foreach ($activities as $row) {
                $search_results['activity'][] = $row;
            }
        }
        if (!isset($search_results['campaign'])) {
            $campaigns = $this->searchCampaign($GroupId, $search_text, false, $limit);
            foreach ($campaigns as $row) {
                $search_results['campaign'][] = $row;
            }
        }
        if (!isset($search_results['event'])) {
            $events = $this->searchEvent($GroupId, $search_text, false, $limit);
            foreach ($events as $row) {
                $search_results['event'][] = $row;
            }
        }
        if (!isset($search_results['people'])) {
            $members = $this->searchPeople($GroupId, $search_text, false, $limit);
            foreach ($members as $row) {
                $search_results['people'][] = $row;
            }
        }

        return $search_results;
    }

    private function searchActivity($GroupId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Brigades = new Brigade_Db_Table_Brigades();
        $sitemedia = new Brigade_Db_Table_Media();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $list = $Brigades->searchGroupActivity($GroupId, $search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $Brigades->getMediaGallery($item['ProjectId'], "");
                $default = 'images/defaultbrigade.jpg';
                if (count($media) > 0) {
                    $media_src = "Media/".$media[0]['SystemMediaName'];
                } else {
                    $media = $sitemedia->getSiteMediaBySiteId($item['ProjectId']);
                    $media_src = "Media/".$media['SystemMediaName'];
                }
                $image_exists = file_exists("/public/".$media_src);
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/'.($image_exists && trim($media_src) != 'Media/' ? $media_src : $default).'" alt="" /></a></center>';
                $search_result[] = '
                <div class="activity-row item">
                    <div class="logo">
                        '.$logo.'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['URLName'].'">'.stripslashes($item['Name']).'</a></h4>
                        '.($item['StartDate'] != '0000-00-00 00:00:00' && $item['EndDate'] != '0000-00-00 00:00:00' ? date('l M d, Y', strtotime($item['StartDate'])).' at '.date('h:i', strtotime($item['StartDate'])).' '.date('a', strtotime($item['StartDate'])) : "").'
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchPeople($GroupId, $search_text, $perfect_match = true, $limit = NULL) {
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $list = $GroupMembers->searchGroupMembers($GroupId, $search_text, $perfect_match, $limit);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $search_result[] = '
                <div class="people-row item">
                    <div class="logo">
                        <center>
                        <a class="name" href="/'.$item['URLName'].'">
                            <img class="user" src="/profile/loadimage/?UserId='.$item['UserId'].'" alt="" />
                        </a>
                        </center>
                    </div>
                    <div class="info">
                        <h4><a href="/'.stripslashes($item['URLName']).'">'.stripslashes($item['FullName']).'</a></h4>
                        '.$item['Location'].'<br/>'.'
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchCampaign($GroupId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $sitemedia = new Brigade_Db_Table_Media();
        $Projects = new Brigade_Db_Table_Brigades();
        $list = $Projects->searchGroupCampaign($GroupId, $search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $sitemedia->getSiteMediaBySiteId($item['ProjectId']);
                $media_image = $media['SystemMediaName'];
                $media_caption = $media['Caption'];
                $image_exists = file_exists("/public/Media/$media_image");
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/Media/'.$media_image.'" alt="'.$media_caption.'" /></a></center>';
                $search_result[] = '
                <div class="campaign-row item">
                    <div class="logo">
                        '.($image_exists && trim($media_image) != '' ? $logo : '&nbsp;').'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['URLName'].'">'.stripslashes($item['Name']).'</a></h4>
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchEvent($GroupId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $sitemedia = new Brigade_Db_Table_Media();
        $Events = new Brigade_Db_Table_Events();
        $list = $Events->searchOrganizationEvent($GroupId, $search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                if (!empty($item['UserId'])) {
                    $Users = new Brigade_Db_Table_Users();
                    $userInfo = $Users->loadInfo($item['UserId']);
                    $URLName = $userInfo['URLName'];
                } else {
                    $LookupTable = new Brigade_Db_Table_LookupTable();
                    $siteType = $LookupTable->getSiteType($item['SiteId']);
                    if ($siteType == 'group') {
                        $Groups = new Brigade_Db_Table_Groups();
                        $siteInfo = $Groups->loadInfo1($item['SiteId']);
                    } else if ($siteType == 'organization') {
                        $Organizations = new Brigade_Db_Table_Organizations();
                        $siteInfo = $Organizations->loadInfo($item['SiteId'], false);
                    }
                    $URLName = $siteInfo['URLName'];
                }
                $search_result[] = '
                <div class="event-row item">
                    <div class="logo">&nbsp;</div>
                    <div class="info">
                        <h4><a class="name" href="/'.$URLName.'/events?EventId='.$item['EventId'].'">'.stripslashes($item['Title']).'</a></h4>
                        '.date('l M d, Y', strtotime($item['StartDate'])).' at '.date('H:i', strtotime($item['StartDate'])).' '.date('a', strtotime($item['StartDate'])).'
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }


    public function getHeaderMedia(Group $group) {
        $Media = new Brigade_Db_Table_Media();
        $this->view->siteBanner = false;
        if (!empty($group->bannerMediaId)) {
            $siteBanner = $Media->getSiteMediaById($group->bannerMediaId);
            $this->view->siteBanner = $siteBanner;
            $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
        }
    }

    public function getOrganizationHeaderMedia(Organization $organization) {
        $Media = new Brigade_Db_Table_Media();
        $this->view->siteBanner = false;
        if (!empty($organization->bannerMediaId)) {
            $siteBanner = $Media->getSiteMediaById($organization->bannerMediaId);
            $this->view->siteBanner = $siteBanner;
            $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
        } else {
            $siteMedia = $Media->getSiteMediaById($organization->logoMediaId);
        }
    }

    /**
     * Payment Form for membership with fee in chapters.
     *
     */
    public function membershipAction() {
        $params = $this->_getAllParams();

        if (!$this->_helper->authUser->isLoggedIn()) {
            if (empty($params['UserId']) || empty($params['hash'])) {
                $this->_helper->redirector('error', 'error');
            } else {
                // validate hash
                $hash = sha1('membership'.$params['UserId'].$params['GroupId'].'cafeconleche');
                if ($hash != $params['hash']) {
                    $this->_helper->redirector('error', 'error');
                }
                // login
                $_SESSION['UserId'] = $params['UserId'];
                parent::init();
                if (!$this->_helper->authUser->isLoggedIn()) {
                    $this->_helper->redirector('error', 'error');
                }
                $cms         = new Zend_Session_Namespace('membership_api_payment');
                $cms->urlCMS = $params['url'];
            }
        }



        $group = Group::get($params['GroupId']);
        if (!$group) {
            $this->_helper->redirector('error', 'error');
        }
        $config = Zend_Registry::get('configuration');
        if (!$config->chapter->membership->enable ||
            in_array($group->organizationId, $config->chapter->membership->settings->toArray()) ||
            !in_array($group->organizationId, $config->chapter->membership->active->toArray())
        ) {
            //only enabled for settings
            $this->_helper->redirector('error', 'error');
        }

        if ($group->hasMembershipFee) {
            $member = Member::getByGroupUser($group, $this->sessionUser);
            if ($group->isMember($this->sessionUser)) {
                $this->_helper->redirector('error', 'error');
            } else if (!empty($member) && $member->paid && !$member->isDeleted) {
                $this->_helper->redirector('error', 'error');
            }
        } else {
            $this->_helper->redirector('error', 'error');
        }

        $this->view->headTitle(stripslashes($group->name).' | Members');

        //breadcrumb
        $this->view->breadcrumb   = $this->view->breadcrumbHelper($group, 'Members');
        $this->view->currentTab   = 'members';
        $this->view->group        = $group;
        $this->view->msgVolunteer = false;

        $session = new Zend_Session_Namespace('volunteer_membership');
        if (!empty($session->projectUrlName)) {
            $this->view->msgVolunteer = true;
        }

        $this->renderPlaceHolders();

        if ($group->bluePayId > 0 || $group->organization->id == 'DB04F20F-59FE-468F-8E55-AD75F60FB0CB') {
            $this->view->render('donation/bluepayform_cc.phtml');
        }
    }

    /**
     * Membership Payment by ajax call.
     * Gateway: BluePay
     */
    public function membershippayAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        if (!$this->getRequest()->isPost()) {
            return;
        }
        $group  = Group::get($params['GroupId']);
        $config = Zend_Registry::get('configuration');
        if (!$config->chapter->membership->enable ||
            in_array($group->organizationId, $config->chapter->membership->settings->toArray()) ||
            !in_array($group->organizationId, $config->chapter->membership->active->toArray())
        ) {
            //only enabled for settings
            $this->_helper->redirector('error', 'error');
        }

        $params['typePayment'] = 'card';

        $msg = BluePay::validateParams($params);
        if (empty($params['freqId'])) {
            $msg .= 'Please, select payment type.';
        }
        if ($msg != '') {
            //error
            $response = json_encode(array(
                'msg' => $msg,
            ));
        } else {
            $member = $group->addMember($this->sessionUser);
            if (!$member && $group->isMember($this->sessionUser)) {
                $member = $group->getMember($this->sessionUser);
            }
            if (in_array($group->organizationId, $config->chapter->membership->bluepay->orgs->toArray())) {
                //custom bluepay id for specific brigades using other gateway
                $bluePay = BluePay::get($config->chapter->membership->bluepay->id);
                $bpay = new BluePayment(
                    $bluePay->accountId,
                    $bluePay->secretKey,
                    $bluePay->mode
                );
            } else {
                $bpay = new BluePayment(
                    $group->bluePay->accountId,
                    $group->bluePay->secretKey,
                    $group->bluePay->mode
                );
            }
            $frequency = $group->getMembershipFrequency($params['freqId']);
            $bpay->sale($frequency->amount);
            $bpay->setOrderId($member->id);
            $bpay->setCustInfo(
                $params['cardNumber'], //The customer's credit card number
                $params['validationCode'], //The customer's Card Validation Code.  This is the three-digit code
                $params['expirationDateMM'].substr($params['expirationDateYY'],2,2),
                $params['firstName'], //The customer's first name (32 characters)
                $params['lastName'], //The customer's last name (32 characters)
                $params['street'], //The customer's street address,  for AVS. (64 Chars)
                $params['city'], //The customer's city (32 Characters)
                $params['state'], //The customers' state(16 Characters max)
                $params['zipcode'], //The customer's zipcode or equivalent. (16 Characters)
                $params['country'],//The customer's country (64 Characters)
                $params['phone'], //The cusotmer's phone number.
                $params['email'] //The customer's email address.
            );
            if ($frequency->bluePayFreq != '') {
                $bpay->rebAdd(
                    $frequency->amount,
                    $frequency->paidUntil,
                    $frequency->bluePayFreq,
                    null
                );
                Zend_Registry::get('logger')->info(
                    'Membership::Pay::'.$member->id.'::Until('.$frequency->paidUntil.
                    ')::Freq('.$frequency->bluePayFreq.')::Amount('.$frequency->amount.')'
                );
            } else {
                Zend_Registry::get('logger')->info('Membership::Pay::'.$member->id.
                '::OneTime::Amount('.$frequency->amount.')');
            }
            $bpay->process();
            if ($bpay->getStatus() == 1) {
                if (!empty($member)) {
                    $member->paidUntil   = $frequency->paidUntil;
                    $member->frequencyId = $frequency->id;
                    $member->paid        = true;
                    $member->save();

                    // InfusionSoft
                    if (!empty($params['news'])) {
                        $this->infusionSoftIntegration($member);
                    }
                }
                $payment = $this->_paymentMembership($member);
                if ($payment) {
                    $payment->transactionId = $bpay->getTransId();
                    $payment->rebillingId   = $bpay->getRebid();
                    $payment->amount        = $frequency->amount;
                    $payment->save();

                    $raisedProject = MembershipFund::getByGroup($member->group);
                    if ($raisedProject) {
                        $raisedProject->amount += $frequency->amount;
                        $raisedProject->save();
                    }
                }
                if ($member) {
                    $this->salesforceMemberIntegration($member);
                }

                //if user tried to volunteer an initiative we need to forward him
                $session = new Zend_Session_Namespace('volunteer_membership');
                if(!empty($session->projectUrlName)) {
                    $session->membershipPaid = true;
                }
                $response = json_encode(array(
                    'status' => 'ok',
                ));

                // If is a member coming from API
                $cms = new Zend_Session_Namespace('membership_api_payment');
                if (!empty($cms->urlCMS)) {
                    $url = $cms->urlCMS;
                    $cms->urlCMS = null;
                    $cms         = null;

                    $this->_helper->redirector->gotoUrl($url);
                }
            } else {
                $response = json_encode(array(
                    'donationId' => $donation->id,
                    'status'     => 'error',
                    'msg'        => $bpay->getMessage()
                ));
            }
        }

        echo $response;
    }

    /**
     * Add member user to infusion soft.
     *
     * @param Member $member Member instance.
     *
     * @return void.
     */
    protected function infusionSoftIntegration($member,$addMissingContact = true) {
        $configIS = Zend_Registry::get('configuration')->infusionsoft;
        if (!($configIS->active &&
            in_array($member->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('InfusionSoft::Group::MemberContact');
        $is = Infusionsoft::getInstance();
        if ($addMissingContact) {
            $is->addMemberContact($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Group::Add/Update:'.$member->id);
        } else {
            $is->updateMemberContact($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Group::Only Update:'.$member->id);
        }
    }

    /**
     * On chapter edit settings, update infusionsoft members information.
     *
     * @param Group $group
     *
     * @return void
     */
    protected function _updateInfusionSoftChapterInfo($group) {
        $configIS = Zend_Registry::get('configuration')->infusionsoft;
        if (!($configIS->active &&
            in_array($group->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }

        Zend_Registry::get('logger')->info('InfusionSoft::Group::EditInfo');
        $is = Infusionsoft::getInstance();
        foreach ($group->members as $k => $member) {
            $is->updateMemberContact($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Group::Member Update:'.$member->id);
        }
        Zend_Registry::get('logger')->info('InfusionSoft::Group::Total Updated:'.$k);
    }


    /**
     * Update chapter information under infusionsoft.
     */
    protected function _updateSalesForceChapterInfo($chapter, $oldName = '') {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($chapter->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Chapter::Update');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($chapter->organization)) {
            $salesforce->updateAccountInfo($chapter, $oldName);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$chapter->organizationId
            );
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
        Zend_Registry::get('logger')->info('SalesForce::Member::Group');
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
     * Membership Send email to Steve
     */
    public function membershipturnoffAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        if (!$this->getRequest()->isPost()) {
            return;
        }
        if (!$this->view->isLoggedIn) {
            return;
        }
        $group  = Group::get($params['GroupId']);
        $msg    = 'Chapter: '.$group->name.' (http://www.empowered.org/'.$group->urlName.')<br />';
        $msg   .= 'User: '.$this->sessionUser->fullName.' ('.$this->sessionUser->email.')<br />';
        $msg   .= 'Comment:' . $params['message'];
        $ok    = Zend_Registry::get('eventDispatcher')->dispatchEvent(
            EventDispatcher::$MEMBERSHIP_TURN_OFF,
            array(
                $msg, $this->sessionUser->email
            )
        );
        echo '';
        return;
    }

    /**
     * Create history of payment
     *
     * @param Member $member
     *
     * @return Payment
     */
    protected function _paymentMembership($member) {
        $payment          = new Payment();
        $payment->groupId = $member->group->id;
        if (!empty($member->group->programId)) {
            $payment->programId = $member->group->programId;
        }
        if (!empty($member->group->organizationId)) {
            $payment->organizationId = $member->group->organizationId;
        }
        $payment->userId            = $member->userId;
        $payment->createdById       = $member->userId;
        $payment->orderStatusId     = 2;
        $payment->transactionSource = Payment::BLUEPAY;
        $payment->createdOn         = date('Y-m-d');
        $payment->paidUntil         = $member->paidUntil;

        return $payment;
    }

    /**
     * Membership reports. List of payments.
     */
    public function membershipreportAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        $group  = Group::get($params['GroupId']);

        $this->view->headTitle(stripslashes($group->name).' | Membership Report');

        //filter
        $perPage  = $this->_getParam('show_list', 50);
        $search   = $this->_getParam('searchFilter', false);
        $page     = $this->_getParam('page', 1);
        $fromDate = $this->_getParam('FromDate', false);
        $toDate   = $this->_getParam('ToDate', false);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Membership Report');
        $this->view->currentTab = 'members';
        $this->view->group      = $group;
        $this->view->searchText = $search;
        $this->view->showList   = $perPage;
        $this->view->fromDate   = $fromDate;
        $this->view->toDate     = $toDate;
        $this->view->totalDon   = Payment::getRaisedByGroup($group);

        //payments
        if (!$search && !$fromDate && !$toDate) {
            $payments = $group->payments;
        } else {
            if ($fromDate) {
                $fromDate = date('Y-m-d', strtotime($fromDate));
            }
            if ($toDate) {
                $toDate = date('Y-m-d', strtotime($toDate));
            }
            $payments = Payment::getListByGroup($group, $search, $fromDate, $toDate);
        }

        $paginator = Zend_Paginator::factory($payments);
        $paginator->setItemCountPerPage($perPage);
        $paginator->setCurrentPageNumber($page);
        $this->view->payments = $paginator;
        $_REQUEST['URLName']  = $group->urlName;
        $_REQUEST['subpage']  = 'membership-report';

        $this->renderPlaceHolders();
    }


    /**
     * Membership funds - used to transfer funds for initiatives.
     */
    public function membershipfundsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        $group  = Group::get($params['GroupId']);

        $this->view->headTitle(stripslashes($group->name).' | Membership Funds');

        //filter
        $perPage  = $this->_getParam('show_list', 50);
        $page     = $this->_getParam('page', 1);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Membership Funds');
        $this->view->currentTab = 'members';
        $this->view->group      = $group;
        $this->view->showList   = $perPage;

        $this->view->totalMembership = MembershipFund::getRaisedByGroup($group);
        $this->view->chapMembership  = ($this->view->totalMembership * MembershipFund::transferLimit) / 100;
        $this->view->membershipFunds = MembershipFund::getListByGroup($group);
        $this->view->fundsTransfered = MembershipFund::getTotalTransferedByGroup($group);

        $paginator = Zend_Paginator::factory($this->view->membershipFunds);
        $paginator->setItemCountPerPage($perPage);
        $paginator->setCurrentPageNumber($page);
        $this->view->funds = $paginator;
        $_REQUEST['URLName']  = $group->urlName;
        $_REQUEST['subpage']  = 'membership-report';

        $this->renderPlaceHolders();
    }

    /**
     * Ajax transfer manual membership funds
     */
    public function membershiptransferAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        $group  = Group::get($params['GroupId']);

        if ($_POST) {
            $totalMembership = MembershipFund::getRaisedByGroup($group);
            $chapMembership  = ($totalMembership * MembershipFund::transferLimit) / 100;
            $fundsTransfered = MembershipFund::getTotalTransferedByGroup($group);
            $available       = $chapMembership-$fundsTransfered;
            if ($available >= $params['amountTransfer']) {
                $transfer              = new MembershipTransfer();
                $transfer->amount      = $params['amountTransfer'];
                $transfer->createdById = $this->sessionUser->id;
                $transfer->createdOn   = date('Y-m-d H:i:s');

                $project       = Project::get($params['projectId']);
                $raisedProject = MembershipFund::getByProject($project);
                if (!$raisedProject) {
                    $membershipFund                 = new MembershipFund();
                    $membershipFund->groupId        = $group->id;
                    $membershipFund->organizationId = $group->organizationId;
                    $membershipFund->amount         = $params['amountTransfer'];
                    $membershipFund->projectId      = $params['projectId'];
                    $membershipFund->save();

                    $transfer->membershipFundId = $membershipFund->id;
                } else {
                    $transfer->membershipFundId = $raisedProject->id;

                    $raisedProject->amount += $params['amountTransfer'];
                    $raisedProject->save();
                }
                $transfer->save();

                echo 'success';
            } else {
                echo 'Not enough funds to transfer. You only have '.
                    $group->currency.$available;
            }
        }
    }

    /**
     * Membership settings
     */
    public function membershipsettingsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        $group  = Group::get($params['GroupId']);

        $this->view->headTitle(stripslashes($group->name).' | Membership Settings');

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Membership Settings');
        $this->view->currentTab = 'members';
        $this->view->group      = $group;

        if ($this->getRequest()->isPost()) {
            $group->isOpen                     = false;
            $group->activityRequiresMembership = false;
            if (!empty($params['activityRequiresMembership'])) {
                $group->activityRequiresMembership = true;
            }
            if (!empty($params['isOpen'])) {
                $group->isOpen = true;
            }

            if (!empty($params['feeFreq'])) {
                $errorAmount = false;
                foreach($params['feeFreq'] as $id) {
                    if ($params['feeAmnt_'.$id] <= 0) {
                        $errorAmount = true;
                    }
                }
                if (!$errorAmount) {
                    MembershipFrequency::clean($group);
                    foreach($params['feeFreq'] as $id) {
                        $membershipFreq          = new MembershipFrequency();
                        $membershipFreq->id      = $id;
                        $membershipFreq->amount  = $params['feeAmnt_'.$id];
                        $membershipFreq->groupId = $group->id;
                        $membershipFreq->save();
                    }
                }
            }

            $group->save();

            // log the site activity
            $activity              = new Activity();
            $activity->siteId      = $group->id;
            $activity->type        = 'Group Updated';
            $activity->createdById = $this->view->userNew->id;
            $activity->date        = date('Y-m-d H:i:s');
            $activity->save();

            $this->view->updated = true;

        }

        $this->renderPlaceHolders();
    }

    /**
     * Change member title from member list
     */
    public function changemembertitleAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        $member = Member::get($params['MemberId']);
        $member->memberTitleId = $params['TitleId'];
        $member->save();

        //update member under infusionsoft (the new title setup)
        $this->infusionSoftIntegration($member);
        $this->salesforceMemberIntegration($member);
    }

    public function memberprofileAction() {
        $params = $this->_getAllParams();
        $member = Member::get($params['MemberId']);

        $request = $this->getRequest();
        $this->view->refererUrl = true;
        if (strpos($request->getHeader('referer'), 'empowered.org') > -1) {
            $this->view->refererUrl = false;
        }

        $this->view->user         = $member->user;
        $this->view->group        = $member->group;
        $this->view->activityFeed = $member->user->activityFeed_5;
        $this->view->breadcrumb   = $this->view->breadcrumbHelper(
            $member->group,
            $member->user->fullName
        );
        $this->view->currentTab = 'members';
        $this->view->headTitle(stripslashes($member->group->name));
        $this->getHeaderMedia($member->group);
        $project = Project::getFeaturedUserInitiative($member->user->id);
        if(!empty($project->id)) {
            $this->view->project           = $project;
            $volunteer                     = $project->getVolunteerByUser($member->user);
            $this->view->userProjectRaised = $volunteer->raised;
            $this->view->userProjectGoal   = $volunteer->userDonationGoal;

            $this->view->rightbarHelper($project, $volunteer);
            $this->view->project->user_message = $project->getMessageUser($member->user);
            $this->view->urlShare = "";
        }

        //$this->view->render('profile/header.phtml');
        $this->view->render('group/header.phtml');
        $this->view->render('group/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');

        $this->_helper->layout->setLayout('newlayout');
    }

    /**
     * Prepare all plceholders for the new design.
     *
     */
    public function renderPlaceHolders() {
        $this->view->render('group/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('group/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');
    }
}
