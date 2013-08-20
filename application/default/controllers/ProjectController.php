<?php

/**
 * BrigadeController - The "brigades" controller class
 *
 * @author
 * @version
 */

require_once 'Brigade/Db/Table/Blogs.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Cities.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Countries.php';
require_once 'Brigade/Db/Table/Events.php';
require_once 'Brigade/Db/Table/FundraisingCampaign.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/LookupTableHistory.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/Photo.php';
require_once 'Brigade/Db/Table/Regions.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/SiteActivityComments.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/Projects.php';
require_once 'Brigade/Util/ImageCrop.php';
require_once 'Brigade/Util/ImageResize.php';
require_once 'Brigade/Util/FBConnect.php';
require_once 'Zend/Paginator.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Lib/Validate/DbUnique.php';
require_once 'BaseController.php';

require_once 'Project.php';
require_once 'Infusionsoft.php';
require_once 'Salesforce.php';
require_once 'Role.php';

class ProjectController extends BaseController {
    protected $_http;
    public function init() {
        parent::init();
    }

    /**
     * Home Project Initiatives.
     * Includes: General Info, Fundraisers, Sponsors, Activity Feed.
     *
     * @return void
     */
    public function indexAction() {
        $parameters = $this->_getAllParams();
        if(isset($parameters['ProjectId'])) {
            $project = $this->view->project = Project::get($parameters['ProjectId']);
            if ($project->isDeleted) {
                $this->getResponse()->setHttpResponseCode(404);
            }
        } else {
            $project = Project::getFeaturedGroupInitiative($parameters['GroupId']);
        }

        // new url for cms redirect
        $config = Zend_Registry::get('configuration');
        if ($config->cms_migrate->active &&
            in_array($project->organizationId, $config->cms_migrate->org->toArray())
        ) {
            if (!($this->view->isLoggedIn && $this->view->isAdmin)) {
                $this->_helper->redirector->gotoUrl(
                    $config->cms_migrate->host . '/chapter/' . $project->groupId .
                    '/initiatives/type:' . (($project->type === 0) ? 'activity' : 'campaign') .
                    '/id:' . $project->id
                );
            }
        }

        $this->view->headTitle(stripslashes($project->name));
        $this->view->showOpenGraphInitiativeMeta = true;

        //get upcoming or past initiatives
        if (!isset($parameters['status'])) {
            $pStatus = 'completed';
            if(((strtotime($project->endDate) - time()) >= 0) ||
                $project->endDate == '0000-00-00 00:00:00') {
                $pStatus = 'upcoming';
            }
        } else {
            $pStatus = $parameters['status'];
        }
        $this->view->pStatus = $pStatus;

        if(!empty($project->groupId)) {
            $Initiatives = Project::getListByGroup($project->group, $pStatus, $project->type);
        } else if(!empty($project->organizationId)) {
            $Initiatives = Project::getListByOrganization($project->organization, $pStatus, $project->type);
        }

        $this->view->showToolBox = false;
        if ( isset($_SESSION['UserId']) && $_SESSION['UserId'] ==  $project->userId ) {
            $this->view->showToolBox = true;
        }

        $this->getHeaderMedia($project);

        if($project->type == 0) {
            //get initiative location

            if ($project->contact->countryId) {
                $Regions = new Brigade_Db_Table_Regions();
                $this->view->region_list = $Regions->getCountryRegions($project->contact->countryId);
            }
            if ($project->contact->stateId) {
                $Cities = new Brigade_Db_Table_Cities();
                $this->view->city_list = $Cities->getRegionCities($project->contact->stateId);
            }
        }

        //Activityfeed
        $this->view->isIndex = true;

        if(!empty($project->organizationId)) {
            $this->view->network = $project->organization;
        }
        $this->view->project = $project;
        if(!empty($Initiatives)) {
            $this->view->initiatives = $Initiatives;
        }

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($project);
        $this->view->rightbarHelper($project);

        $this->view->currentTab   = 'initiatives';
        $this->view->toolPopupObj = $project; // for logo upload toolbox
        if(!empty($project->programId)) {
            $this->view->program  = $project->program;
        }

        if(!empty($project->groupId)) {
            $this->view->group = $project->group;

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');
        } else if(!empty($project->organizationId)) {
            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');
        } else {
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('project/header.phtml');
            $this->view->soloProject = true;
        }

        //for upload logo
        $this->view->uploadUrlLogo = '/project/editlogo';
        $this->view->uploadIdName  = 'Project';
        $this->view->toolPopupObj  = $project;
        $this->view->urlName       = $project->urlName;

        $Files             =  new Brigade_Db_Table_Files();
        $this->view->files = $Files->getProjectFiles($project->id);

        $this->view->render('administrator/popup_upload_logo.phtml');
        $this->view->render('project/toolbox.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');
    }

    /**
     * Ajax action to filter initiatives.
     */
    public function allactivitiesAction() {
        $parameters = $this->_getAllParams();

        $project             = Project::get($parameters['projectId']);
        $this->view->project = $project;

        $this->render('activityfeed');
        $this->_helper->layout()->disableLayout();
    }

    /**
     * Ajax action to filter initiatives.
     */
    public function filterinitiativesAction() {
        $parameters = $this->_getAllParams();

        $project             = Project::get($parameters['projectId']);
        $this->view->filter  = true;
        $this->view->project = $project;
        if ($project->group) {
            $this->view->initiatives = Project::getListByGroup(
                $project->group,
                $parameters['status'],
                $parameters['type']
            );
        } else {
            $this->view->initiatives = Project::getListByOrganization(
                $project->organization,
                $parameters['status'],
                $parameters['type']
            );
        }

        $this->render('initiatives');
        $this->_helper->layout()->disableLayout();
    }

    public function infoAction() {
        $parameters = $this->_getAllParams();
        $Brigades = new Brigade_Db_Table_Brigades();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $media = new Brigade_Db_Table_Media();
        if (isset($parameters['ProjectId'])) {
            $ProjectId = $parameters['ProjectId'];
            $this->view->data = $Brigades->loadInfo($ProjectId);
            $this->view->data['Location'] = $contact_info->getContactInfo($ProjectId, 'Location');
            // load brigade image gallery
            $this->view->media_gallery = $Brigades->getMediaGallery($ProjectId);
            // load volunteers
            $this->view->volunteers = $Brigades->loadVolunteers($ProjectId);
            // load brigade's network tree: org -> program -> group
            $this->view->brigadeTree = $Brigades->loadBrigadeTreeInfo($ProjectId);
            // load brigade contact info
            $this->view->contactinfo = $contact_info;
            $this->view->sitemedia = $media;
            // load brigade's blogs
            $Blogs = new Brigade_Db_Table_Blogs();
            $this->view->blogs = $Blogs->getSiteBlogs($ProjectId);
            // load brigade's events
            $Events = new Brigade_Db_Table_Events();
            $this->view->events = $Events->getSiteEvents($ProjectId);
            // check if user is logged in then check if he/she is a volunteer of this brigade
            if (isset($this->view->UserId)) {
                $this->view->isVolunteer = $Brigades->isVolunteer($ProjectId, $this->view->UserId);
            }
        } else {
            echo 'Brigade not found.';
        }
    }

    public function preDispatch() {
        parent::preDispatch();
        $this->view->media_path = "/public/Media/";
    }

    public function searchAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $text_search = trim($_POST['text_search']);
        $text_search = preg_replace('/\s\s+/', ' ', $text_search);

        $Brigades = new Brigade_Db_Table_Brigades();
        $Groups = new Brigade_Db_Table_Groups();
        $contactinfo = new Brigade_Db_Table_ContactInformation();
        $sitemedia = new Brigade_Db_Table_Media();
        $list = $Brigades->search($text_search);
        echo '<h2>Search results for "'.$text_search.'"</h2><div class="clear"></div>';
        if (count($list) > 0) {
            $ctr = 1;
            foreach ($list as $item) {
                $media_src = '';
                $media = $sitemedia->getSiteMediaGallery($list['ProjectId'], "");
                if (count($media) > 0) {
                    $media_src = '/public/Media/'.$media[0]['SystemMediaName'];
                } else {
                    // get the group image by group's LogoMediaId
                    $groupInfo = $Groups->loadInfo($list['GroupId']);
                    $media = $sitemedia->getSiteMediaById($groupInfo['LogoMediaId']);
                    // echo '$this->sitemedia->getSiteMediaById('.$groupInfo['LogoMediaId'].')';
                    $media_src = '/public/Media/'.$media['SystemMediaName'];
                }
                echo '
                    <div class="box06" >
                        <div class="bst01">
                            <img src="'.$media_src.'" alt="" width="74" height="50"/>
                            <div class="bst03">
                                <div class="bst04">
                                    <div class="bst05"><span><span><span id="ctl00_ContentPHMain_ctrlBrigade$item1_brigades_repeater_ctl00_lblVoluntSpaceEmpty">'.($item['total_volunteers'] > $item['VolunteerGoal'] ? 0 : $item['VolunteerGoal'] - (int)$item['total_volunteers']).'</span></span> / </span> '.$item['VolunteerGoal'].'</div>Spaces<br />Available
                                </div>
                            </div>
                        </div>
                        <div class="bst02">
                                <div class="bst06">
                                    <div class="bst07">Group: </div>
                                    <a href="/group/?GroupId='.$item['GroupId'].'" >'.$item['GroupName'].'</a>
                                </div>
                                <div class="bst06">
                                    <div class="bst07">Brigade: </div>
                                    '.$item['Name'].'
                                </div>
                                <div class="bst08">
                                    <div class="bst07">Where: </div>
                                    '.$contactinfo->getContactInfo($item['ProjectId'], 'Location').'
                                </div>
                                <div class="bst08">
                                    <div class="bst07">When: </div>
                                    '.date('M d, Y', strtotime($item['StartDate'])).' - '.date('M d, Y', strtotime($item['EndDate'])).'
                                </div>
                                <div class="bst08">
                                    <div class="bst07">Description: </div>
                                        <div class="bst11">
                                            <div id="divLessContent'.$item['ProjectId'].'" style="display:block;">
                                                <span id="ctl00_ContentPHMain_ctrlBrigade$item1_brigades_repeater_ctl00_lblDescriptionLessContent">
                                                    '.(strlen($item['Description']) > 100 ? substr($item['Description'], 0, 100) : $item['Description']).'
                                                </span>'
                                                .(strlen($item['Description']) > 100 ? '
                                                <a id="ReadMore" href="javascript:ShowHide(\'divLessContent'.$item['ProjectId'].'\',\'divMoreContent'.$item['ProjectId'].'\');">
                                                    <span id="ctl00_ContentPHMain_ctrlBrigade$item1_brigades_repeater_ctl00_lblReadMore">Read More</span>
                                                </a>
                                                ' : "").'
                                            </div>
                                            '.(strlen($item['Description']) > 100 ? '
                                            <div id="divMoreContent'.$item['ProjectId'].'" style="display:none;">
                                                <span id="ctl00_ContentPHMain_ctrlBrigade$item1_brigades_repeater_ctl00_lblDescriptionMoreContent">'.$item['Description'].'</span>
                                                <a id="ReadFewer" href="javascript:ShowHide(\'divMoreContent'.$item['ProjectId'].'\',\'divLessContent'.$item['ProjectId'].'\')">Read Less</a>
                                            </div>
                                            ' : "").'
                                        </div>
                                    </div>
                                </div>
                                <div class="clear"></div>
                        </div>
                ';
                $ctr++;
            }
        } else {
            echo '<div class="box06"><h4>No reord(s) found.</h4><div class="clear"></div></div>';
        }
    }

    public function createAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();

        $LookupTable = new Brigade_Db_Table_LookupTable();
        $Groups = new Brigade_Db_Table_Groups();
        $Organizations = new Brigade_Db_Table_Organizations();

        $Countries = new Brigade_Db_Table_Countries();
        $this->view->country_list = $Countries->getAllCountries();

        if (isset($parameters['GroupId'])) {
            $this->view->level = 'group';
            $group             = Group::get($parameters['GroupId']);
            if (!$group->organization->hasActivities) {
                $this->_helper->redirector('error', '');
            }

            //breadcrumb
            $this->view->breadcrumb   = $this->view->breadcrumbHelper($group);
            $this->view->group        = $group;
            $this->view->organization = $group->organization;

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');

        } else if(isset($parameters['ProgramId']) || isset($parameters['NetworkId'])) {
            $this->view->level = 'organization';

            if(!empty($parameters['ProgramId'])) {
                $program      = Program::get($parameters['ProgramId']);
                $organization = $program->organization;
            } else {
                $organization = Organization::get($parameters['NetworkId']);
            }
            if (!$organization->hasActivities) {
                $this->_helper->redirector('error', '');
            }
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

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                                        (isset($program)) ? $program : $organization,
                                        'Create Volunteer Activity'
            );
            $this->view->organization = $organization;
            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/tabs.phtml');
        }

        if (isset($group)) {
            $this->view->googleId  = $group->googleId;
            $this->view->paypalId  = $group->paypalId;
            $this->view->bluePayId = $group->bluePayId;
        } elseif (isset($organization)) {
            $this->view->googleId  = $organization->googleId;
            $this->view->paypalId  = $organization->paypalId;
            $this->view->bluePayId = $organization->bluePayId;
        }


        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');

        $this->view->Prev = isset($parameters['Prev']) ? $parameters['Prev'] : "";

        if ($_POST) {
            extract($_POST);
            $Users = new Brigade_Db_Table_Users();
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
                if ($this->view->level == "organization") {
                    $orgInfo = $Organizations->loadInfo($NetworkId, false);
                    // if the org has no programs yet, create it
                    if (isset($ProgramName) && $ProgramName != 'New Program Name' && !empty($ProgramName)) {
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
                            'NetworkId' => $NetworkId,
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
                    if (isset($GroupName) && $GroupName != 'New Group Name') {
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
                        $BluePayAccountId = $orgInfo['BluePayAccountId'];
                        if($GoogleCheckoutAccountId == 1 || $GoogleCheckoutAccountId == 2 || $GoogleCheckoutAccountId == 3) {
                            $isNonProfit = 1;
                        } else {
                            $isNonProfit = 0;
                        }
                        if($GoogleCheckoutAccountId == 2) {
                            $group_currency = '&#163;';
                        } else {
                            $group_currency = '$';
                        }
                        $hasMembershipFee = false;
                        $config           = Zend_Registry::get('configuration');
                        if (in_array($organization->id,
                                     $config->chapter->membership->active->toArray())
                        ) {
                            $hasMembershipFee = true;
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
                            'hasMembershipFee' => ($hasMembershipFee) ? 1 : 0
                        ));

                        if ($hasMembershipFee) {
                            //create default frequency amount
                            $defVals = $config->chapter->membership->default;

                            $membershipFreq          = new MembershipFrequency();
                            $membershipFreq->id      = $defVals->frequencyId;
                            $membershipFreq->amount  = $defVals->amount;
                            $membershipFreq->groupId = $GroupId;
                            $membershipFreq->save();
                        }


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
                $StartTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['StartTime']);
                $StartDate = trim($_POST['StartDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['StartDate']." ".$StartTime)) : "0000-00-00 00:00:00";
                $EndTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
                $EndDate = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";
                $Status = $_POST['Status'] == 'Open' ? 'Open' : 'Close';

                // load group info and populate the GC, PP and Currency fields
                if ($this->view->level == "organization") {
                    $siteInfo = $Organizations->loadInfo($NetworkId, false);
                } else {
                    $siteInfo = $Groups->loadInfo1($GroupId);
                }

                // save project info first
                $Brigades = new Brigade_Db_Table_Brigades();
                $ProjectId = $Brigades->addProject(array(
                    'GroupId' => isset($GroupId) ? $GroupId : '',
                    'ProgramId' => $ProgramId,
                    'NetworkId' => $NetworkId,
                    'Name' => $Name,
                    'Description' => $Description,
                    'StartDate' => $StartDate,
                    'EndDate' => $_POST['with_end_date'] == 1 ? $EndDate : "",
                    'VolunteerGoal' => ($VolunteerGoal) ? $VolunteerGoal : 0,
                    'DonationGoal' => ($DonationGoal >= 0 ) ? $DonationGoal : 0,
                    'VolunteerMinimumGoal' => ($VolunteerMinimumGoal) ? $VolunteerMinimumGoal : 0,
                    'Status' => $Status,
                    'isFundraising' => $isFundraising,
                    'URLName' => $URLName,
                    'GoogleCheckoutAccountId' => isset($siteInfo['GoogleCheckoutAccountId']) ? $siteInfo['GoogleCheckoutAccountId'] : 0,
                    'PaypalAccountId' => isset($siteInfo['PaypalAccountId']) ? $siteInfo['PaypalAccountId'] : 0,
                    'BluePayAccountId' => isset($siteInfo['BluePayAccountId']) ? $siteInfo['BluePayAccountId'] : 0,
                    'Currency' => isset($siteInfo['Currency']) ? $siteInfo['Currency'] : '$',
                    'PercentageFee' => $siteInfo['PercentageFee'],
                    'allowPercentageFee' => $siteInfo['allowPercentageFee']
                ));

                // add record on the lookup_table
                $LookupTable->addSiteURL(array(
                    'SiteName' => $URLName,
                    'SiteId' => $ProjectId,
                    'Controller' => 'project',
                    'FieldId' => 'ProjectId'
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

                // log the site activity
                $activity              = new Activity();
                $activity->siteId      = $ProjectId;
                $activity->type        = 'Brigade Added';
                $activity->createdById = $this->view->userNew->id;
                $activity->date        = date('Y-m-d H:i:s');
                $activity->save();

                if ($MediaSize > 0) {
                    header("location: /project/cropimage/?ProjectId=$ProjectId&MediaId=$MediaId&newactivity=yes");
                } else {
                    $this->view->message = "Volunteer Opportunity \"$Name\" has been created successfully.";
                    header("location: /$URLName/add-volunteers?newactivity=yes");
                    $_SESSION['newActivity'] = 1;
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

    public function shareAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();

        $project = $this->view->project = Project::get($parameters['ProjectId']);

        $this->view->headTitle("{$project->name} | Share");

        if(!empty($project->groupId)) {
            $group  =  $this->view->group   = $project->group;
            $this->view->level              = 'group';
            $this->view->organization       = $project->organization;

            $this->view->render('group/tabs.phtml');
            $this->view->render('group/header.phtml');

        } else if(!empty($project->organizationId)) {
            $this->view->organization  =  $project->organization;

            $this->view->level = 'organization';

            $Media = new Brigade_Db_Table_Media();
            $this->view->siteBanner = false;
            if (!empty($project->organization->bannerMediaId)) {
                $siteBanner = $Media->getSiteMediaById($project->organization->bannerMediaId);
                $this->view->siteBanner = $siteBanner;
                $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
            } else {
                $siteMedia = $Media->getSiteMediaById($project->organization->logoMediaId);
            }

            $this->view->render('nonprofit/tabs.phtml');
            $this->view->render('nonprofit/header.phtml');

        } else {
            $this->view->render('project/header.phtml');
        }

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper(
                                    $project,
                                    'Share'
        );

        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');
    }

    public function editAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }

        $parameters = $this->_getAllParams();
        $ProjectId = $parameters['ProjectId'];

        $project = Project::get($ProjectId);
        $this->view->edit         = true;
        $this->view->project      = $project;
        if ($project->groupId) {
            $this->view->group = $project->group;
        }
        if ($project->organizationId) {
            $this->view->organization = $project->organization;
        }

        if ($project->organizationId) {
            $this->view->googleId  = $project->organization->googleId;
            $this->view->paypalId  = $project->organization->paypalId;
            $this->view->bluePayId = $project->organization->bluePayId;
        } else if($project->userId) {
            $user = User::get($project->userId);
            $this->view->googleId = $user->googleCheckoutAccountId;
            $this->view->paypalId = $user->paypalAccountId;
        }


        $Countries = new Brigade_Db_Table_Countries();
        $this->view->country_list = $Countries->getAllCountries();

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper(
                                    $project,
                                    'Edit Volunteer Activity'
        );

        $this->_helper->layout->setLayout('newlayout');

        // TODO: change way header is loading
        if(!empty($project->groupId)) {
            $group  =  $this->view->group = $project->group;
            $this->view->level            = 'group';
            $this->view->organization     = $project->organization;

            $this->view->render('group/tabs.phtml');
            $this->view->render('group/header.phtml');

        } else if(!empty($project->organizationId)) {
            $this->view->organization = $project->organization;

            $this->view->level = 'organization';

            $Media = new Brigade_Db_Table_Media();
            $this->view->siteBanner = false;
            if (!empty($project->organization->bannerMediaId)) {
                $siteBanner = $Media->getSiteMediaById($project->organization->bannerMediaId);
                $this->view->siteBanner = $siteBanner;
                $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
            } else {
                $siteMedia = $Media->getSiteMediaById($project->organization->logoMediaId);
            }

            $this->view->render('nonprofit/tabs.phtml');
            $this->view->render('nonprofit/header.phtml');

        } else {
            $this->view->level = 'user';

            $this->view->render('project/header.phtml');
        }

        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->viewRenderer->setRender('create');


        $Media = new Brigade_Db_Table_Media();
        $this->view->sitemedia = new Brigade_Db_Table_Media();

        $this->view->image = $Media->getSiteMediaBySiteId($ProjectId);


        if($_POST && $project) {
            $error   = false;
            $oldUrl  = $project->urlName;
            $oldName = $project->name;

            $LookupTable = new Brigade_Db_Table_LookupTable();
            if (!empty($_POST['Name']) && $project->title != $_POST['Name']) {
                $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), trim($_POST['Name']));
                // replace other special chars with accents
                $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

                if ($URLName != $oldUrl) {
                    $retry = -1;
                    do {
                        $retry++;
                        if ($retry > 0) {
                            $URLName .= '-'.$retry;
                        }
                        $urlExists = $LookupTable->isSiteNameExists($URLName, $ProjectId);
                    } while ($urlExists);

                    //update the lookup_table
                    $LookupTable->updateSiteName($ProjectId, array('SiteName'=>$URLName));
                    $LookupTableHistory = new Brigade_Db_Table_LookupTableHistory();
                    $LookupTableHistory->addSiteURL(array(
                        'SiteName' => $oldUrl,
                        'SiteId' => $ProjectId,
                        'Controller' => 'project',
                        'FieldId' => 'ProjectId'
                    ));
                }
            } else {
                $error = "Missing initiative title.";
            }

            if (!$error) {
                $StartTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['StartTime']);
                $StartDate = trim($_POST['StartDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['StartDate']." ".$StartTime)) : "0000-00-00 00:00:00";
                $EndTime   = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
                $EndDate   = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";

                $project->name             = $_POST['Name'];
                $project->description      = $_POST['Description'];
                $project->endDate          = $EndDate;
                $project->urlName          = $URLName;
                $project->donationGoal     = $_POST['DonationGoal'];
                $project->volunteerGoal    = $_POST['VolunteerGoal']; //volunteers needed

                if ($project->type == 0) {
                    $project->startDate        = $StartDate;
                    $project->volunteerMinGoal = $_POST['VolunteerMinimumGoal'];
                    $project->status           = isset($_POST['Status']) ? $_POST['Status'] : "Close";
                    $project->isFundraising    = $_POST['isFundraising'];
                }

                $project->modifiedBy = $_SESSION['UserId'];
                $project->modifiedOn = date('Y-m-d H:i:s');
                $project->save();

                $this->salesForceIntegrationProject($project, $oldName);

                if ($project->type == 0) {

                    // update user donation goal on volunteers table
                    if ($this->view->data['VolunteerMinimumGoal'] != $VolunteerMinimumGoal) {
                        $Volunteers = new Brigade_Db_Table_Volunteers();
                        $rows = $Volunteers->getProjectVolunteers($ProjectId);
                        foreach ($rows as $row) {
                            if ($row['UserDonationlGoal'] < $VolunteerMinimumGoal || $row['UserDonationlGoal'] == $this->view->data['VolunteerMinimumGoal']) {
                                $Volunteers->setDonationGoal($row['VolunteerId'], $VolunteerMinimumGoal);
                            }
                        }
                    }

                    // brigade contactinfo
                    $ContactId = $_POST['ContactId'];
                    $CityId    = $_POST['CityId'];
                    $City      = $_POST['City'];
                    $RegionId  = $_POST['RegionId'];
                    $Region    = $_POST['Region'];
                    $CountryId = $_POST['CountryId'];
                    $Country   = $_POST['Country'];
                    $Street    = $_POST['Location'];

                    // save project contact info
                    $ContactInfo = new Brigade_Db_Table_ContactInformation();
                    $ContactInfo->editContactInfo($ContactId, array(
                        'Street' => trim($Street),
                        'CityId' => $CityId,
                        'City' => $City,
                        'RegionId' => $RegionId,
                        'Region' => $Region,
                        'CountryId' => $CountryId,
                        'Country' => $Country,
                        'ModifiedBy' => $_SESSION['UserId'],
                        'ModifiedOn' => date('Y-m-d H:i:s'),
                    ));
                }

                // log the site activity
                $activity              = new Activity();
                $activity->siteId      = $ProjectId;
                $activity->type        = 'Site Updated';
                $activity->createdById = $this->view->userNew->id;
                $activity->date        = date('Y-m-d H:i:s');
                $activity->save();

                if (isset($redirect)) {
                    header("location: $redirect");
                } else {
                    $this->_helper->redirector->gotoUrl('/'.$project->urlName);
                }
            }
        }
    }

    public function cropimageAction() {
        $parameters = $this->_getAllParams();
        $project    = Project::get($parameters['ProjectId']);

        $this->view->project = $project;
        $this->view->MediaId = isset($parameters['MediaId']) ? $parameters['MediaId'] : "";

        if(isset($parameters['newactivity'])) {
            $this->view->newactivity = true;
            $activityType            = $parameters['newactivity'];
        }
        if ($_POST) {
            $this->view->image_preview = $media_name = $project->urlName."-logo";
            $ImageCrop = new Brigade_Util_ImageCrop();
            $temp_image_location   = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_{$project->id}.jpg";
            $thumb_image_location  = realpath(dirname(__FILE__) . '/../../../')."/public/Media/$media_name.jpg";
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
                if (isset($_SESSION['newActivity']) || isset($parameters['newactivity'])) {
                    unset($_SESSION['newActivity']);
                    header("location: /{$project->urlName}/add-volunteers?newactivity=$activityType");
                } else {
                    header("location: /{$project->urlName}");
                }
            } else {
                $this->view->preview_image = 1;
            }
        }
    }

    /*
     * Ajax search / autocomplete search
     */
    public function searchbrigadeAction() {
        try {
            if (!empty($_GET['text_search'])) {
                $searchstr = $_GET['text_search'];
                $brigadeManage = new Brigade_Db_Table_Brigades();
                $brigadeList= $brigadeManage->listName($searchstr);

                if($this->_request->isXmlHttpRequest()) {
                    $this->_helper->layout->disableLayout();
                    $this->_helper->viewRenderer->setNoRender();
                    $results = array();
                    foreach ($brigadeList as $list) {
                        $results[] = array("id" => $list["ProjectId"], "value" => "$list[Name]");
                    }
                    $payload = array("results"=>$results);
                    header("Content-type: text/json");
                    echo Zend_Json::encode($payload);
                }
            }
        }

        catch (Exception $e) {
         throw $e;
            $this->view->error = $e->getMessage();
        }
    }

    public function saveinfoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Brigades = new Brigade_Db_Table_Brigades();
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        if ($_POST) {
            $data = array();
            foreach($_POST as $key => $val) {
                if ($key != "field" && $key != "action" && $key != "ProjectId" && $key != "ContactId" && $key != "StartTime" && $key != "EndTime") {
                    $data[$key] = $val;
                }
            }
            if ($_POST['field'] == 'location' || $_POST['field'] == 'website') {
                $locationInfo = $ContactInfo->getContactInfo($_POST['ProjectId']);
            }
            if ($_POST['action'] == 'edit') {
                if ($_POST['field'] == 'description' || $_POST['field'] == 'dates') {
                    if (isset($_POST['StartDate'])) {
                        $StartTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['StartTime']);
                        $StartDate = trim($_POST['StartDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['StartDate']." ".$StartTime)) : "0000-00-00 00:00:00";
                        $data['StartDate'] = $StartDate;
                    }
                    if (isset($_POST['EndDate'])) {
                        $EndTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
                        $EndDate = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";
                        $data['EndDate'] = $EndDate;
                    }
                    $Brigades->editProject($_POST['ProjectId'], $data);
                    echo "Volunteer activity ".$_POST['field']." has been successfully updated.";
                } else if ($_POST['field'] == 'location' || $_POST['field'] == 'website') {
                    $data['ModifiedBy'] = $_SESSION['UserId'];
                    $data['ModifiedOn'] = date('Y-m-d H:i:s');
                    $ContactId = $ContactInfo->editContactInfo($_POST['ContactId'], $data);
                    echo "Volunteer activity ".$_POST['field']." has been successfully updated.";
                }
            } else if ($_POST['action'] == 'add') {
                if ($_POST['field'] == 'location' || $_POST['field'] == 'website') {
                    if (count($locationInfo)) {
                        $ContactInfo->editContactInfo($_POST['ContactId'], $data);
                    } else {
                        $data['SiteId'] = $_POST['ProjectId'];
                        $ContactId = $ContactInfo->addContactInfo($data);
                    }
                    echo "Volunteer activity ".$_POST['field']." has been successfully added.";
                }
            } else if ($_POST['action'] == 'delete') {
                if ($_POST['field'] == 'location' || $_POST['field'] == 'website') {
                    $ContactInfo->editContactInfo($_POST['ContactId'], $data);
                    echo "Volunteer activity ".$_POST['field']." has been successfully deleted.";
                }
            }
        }
    }

    public function uploadvolunteersAction() {
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
                    $Volunteers->signUpVolunteer($UserId, $_POST['ProjectId'], 1);

                    // email a notification to the newly added user with the temp password attached
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$UPLOAD_NEW_VOLUNTEER,
                                        array($rows[$i][3], $rows[$i][1], $groupInfo['GroupName'], $projectInfo['Name'], isset($creator) ? $creator : NULL, $Password, $this->view->userNew, $this->view->serverUrl() . "/profile/login"));

                } else {
                    $userInfo = $Users->findBy($rows[$i][3]);
                    $UserId = $userInfo['UserId'];
                    if(!empty($UserId) && !$Volunteers->isUserSignedUp($_POST['ProjectId'], $UserId)) {
                        $Volunteers->signUpVolunteer($UserId, $_POST['ProjectId'], 1);
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$UPLOAD_EXISTING_VOLUNTEER,
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

    public function activatefundraisingAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        Zend_Registry::get('logger')->info('::activatefundraising:: Group['.$parameters['GroupId'].'] - User['.$this->userNew->id.']');
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $this->view->GroupId = $parameters['GroupId'];
        $this->view->data = $Groups->loadInfo1($parameters['GroupId']);
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
                    $Groups->editGroup($_POST['GroupId'], array('PaypalAccountId' => $PaypalId, 'Currency' => $ppCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                } else if ($_POST['payment_method'] == 'Google Checkout') {
                    $GoogleCheckoutAccountId = $GoogleCheckoutAccounts->addGoogleCheckoutAccount(array(
                        'GoogleCheckoutAccountName' => $this->view->data['GroupName'],
                        'GoogleMerchantId' => trim($_POST['MerchantID']),
                        'GoogleMerchantKey' => trim($_POST['MerchantKey']),
                        'Currency' => ($_POST['Currency'] == 'USD' ? '$' : '&#163;'),
                        'CurrencyType' => $_POST['Currency'],
                    ));
                    $gcCurrency = $_POST['Currency'] == 'USD' ? '$' : '&#163;';
                    $Groups->editGroup($_POST['GroupId'], array('GoogleCheckoutAccountId' => $GoogleCheckoutAccountId, 'Currency' => $gcCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                    // create the responsehandler file
                    $this->create_response_handler($this->view->responsehandler);
                }
            }
            header("location: /".$this->view->data['URLName']."/spread-the-word");
        }
    }


    public function addvolunteersAction() {
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
        $Organizations = new Brigade_Db_Table_Organizations();

        if(isset($parameters['newactivity'])) {
            $this->view->newactivity = true;
            $activityType = $parameters['newactivity'];
        }

        if (isset($parameters['ProjectId'])) {
            $project             = Project::get($parameters['ProjectId']);
            $this->view->project = $project;

            if(!empty($project->groupId)) {
                $group             = $project->group;
                $this->view->group = $group;
                $this->view->level ='group';

                $this->view->render('group/header.phtml');
                $this->view->render('group/tabs.phtml');
            } else if(!empty($project->organizationId)) {
                $this->view->organization = $project->organization;
                $this->view->level        = 'organization';

                $Media = new Brigade_Db_Table_Media();
                $this->view->siteBanner = false;
                if (!empty($project->organization->bannerMediaId)) {
                    $siteBanner                     = $Media->getSiteMediaById(
                        $project->organization->bannerMediaId
                    );
                    $this->view->siteBanner         = $siteBanner;
                    $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
                } else {
                    $siteMedia = $Media->getSiteMediaById($project->organization->logoMediaId);
                }
                $this->view->render('nonprofit/header.phtml');
                $this->view->render('nonprofit/tabs.phtml');

            } else {
                $this->view->render('project/header.phtml');
                $this->view->soloProject = true;
            }

            $this->view->breadcrumb = $this->view->breadcrumbHelper($project,'Add Volunteers');
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/footer.phtml');
            $this->_helper->layout->setLayout('newlayout');
        }

        if ($_POST) {
            $creator = null;
            if(!empty($project->createdById) && $project->createdBy) {
                $creator = $project->createdBy->fullName;
            }
            if (!empty($_POST['emails'])) {
                preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $parameters['emails'], $emails);
                $this->view->emails = $emails[0];
                $this->_addVolunteersByEmails($project, $emails);

                if (isset($_SESSION['newActivity'])) {
                    unset($_SESSION['newActivity']);
                    header("location: /".$project->urlName."/share?newactivity=$activityType");
                } else {
                    header("location: /".$project->urlName);
                }
            } else if (!empty($_FILES['uploadExcel']) && $_FILES['uploadExcel']['size'] > 0 && empty($_POST['emails'])) {
                require_once 'Brigade/Util/ExcelReader.php';
                $tmpfile = $_FILES['uploadExcel']['tmp_name'];
                $filename = $_FILES['uploadExcel']['name'];
                $temp_file_location = realpath(dirname(__FILE__) . '/../../../') . "/public/tmp/".str_replace(" ","_",$filename);
                move_uploaded_file($tmpfile, $temp_file_location);
                $data = new Spreadsheet_Excel_Reader($temp_file_location, false);
                // convert to array
                $rows = $data->dumptoarray();
                // add each user to the users table
                $email_list     = array();
                $invalid_emails = array();
                $invalid_chars  = array();
                $validator = new Zend_Validate_EmailAddress();
                for ($i = 1; $i <= count($rows); $i++) {
                    if (!$validator->isValid($rows[$i][3])) {
                        $invalid_emails[] = $rows[$i][3];
                    } else {
                        $email_list[] = $rows[$i][3];

                        // register the user if email is not taken
                        if ($unique_emailvalidator->isValid($rows[$i][3])) {
                            $user             = new User();
                            $user->firstName  = $rows[$i][1];
                            $user->lastName   = $rows[$i][2];
                            $user->email      = $rows[$i][3];
                            $user->password   = $this->generatePassword();
                            $user->isActive   = true;
                            $user->firstLogin = true;
                            $user->save();
                            // email a notification to the newly added user with the temp password attached
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                EventDispatcher::$UPLOAD_NEW_VOLUNTEER,
                                array(
                                    $user->email,
                                    $user->firstName,
                                    $this->sessionUser->fullName,
                                    $project->name,
                                    isset($creator) && !isset($session_user) ? $creator : NULL,
                                    $user->password,
                                    $parameters['message'],
                                    $this->view->userNew,
                                    $this->view->serverUrl() . "/profile/login"
                                )
                            );
                            $this->view->sent = true;
                        } else {
                            $user = User::getByEmail($rows[$i][3]);
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                EventDispatcher::$UPLOAD_EXISTING_VOLUNTEER,
                                array(
                                    $user->email,
                                    $user->firstName,
                                    $this->sessionUser->fullName,
                                    $project->name,
                                    isset($creator) && !isset($session_user) ? $creator : NULL,
                                    $parameters['message'],
                                    $this->view->userNew
                                )
                            );
                            $this->view->sent = true;
                        }

                        // register the user as volunteer of the activity
                        $project->addVolunteer($user);
                    }
                }
                $this->view->emails = $email_list;
                $this->view->invalid_emails = $invalid_emails;
                echo "bbbbbb";

                if (isset($_SESSION['newActivity'])) {
                    unset($_SESSION['newActivity']);
                    echo "ccccc";
                    header("location: /".$project->urlName."/share?newactivity=$activityType");
                } else {
                    echo "dddd";
                    header("location: /".$project->urlName);
                }
            }
            echo "aaaaaa";
            if (!$this->view->data['hasUploadedMembers']) {
                $Brigades->editProject($_POST['ProjectId'], array('hasUploadedMembers' => 1));
            }

        }
    }

    /**
     * Add volunteers by emails list. Create users and send notification emails.
     *
     * @param Project $project
     * @param Emails  $emails
     */
    protected function _addVolunteersByEmails($project, $emails) {
        $params  = $this->_getAllParams();
        $creator = null;
        if(!empty($project->createdById) && $project->createdBy) {
            $creator = $project->createdBy->fullName;
        }

        $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
        foreach ($emails[0] as $email) {
            if ($unique_emailvalidator->isValid($email)) {
                $name             = explode("@", $email);
                $user             = new User();
                $user->firstName  = str_replace("@", "", $name[0]);
                $user->lastName   = '';
                $user->email      = $email;
                $user->password   = $this->generatePassword();
                $user->isActive   = true;
                $user->firstLogin = true;
                $user->isDeleted  = false;
                $user->save();

                // email a notification to the newly added user with the temp password attached
                Zend_Registry::get('eventDispatcher')->dispatchEvent(
                    EventDispatcher::$UPLOAD_NEW_VOLUNTEER,
                    array(
                        $email,
                        $email,
                        $this->sessionUser->fullName,
                        $project->name,
                        $creator,
                        $user->password,
                        $params['message'],
                        $this->sessionUser,
                        $this->view->serverUrl() . "/profile/login"
                    )
                );

                $this->view->sent = true;
            } else {

                $user = User::getByEmail($email);
                Zend_Registry::get('eventDispatcher')->dispatchEvent(
                    EventDispatcher::$UPLOAD_EXISTING_VOLUNTEER,
                    array(
                        $email,
                        $email,
                        $this->sessionUser->fullName,
                        $project->name,
                        $creator,
                        $params['message'],
                        $this->sessionUser
                    )
                );
                $this->view->sent = true;
            }

            // register the user as volunteer of the activity
            $project->addVolunteer($user);
        }
    }

    /**
     * @TODO: Remove this
     *
     */
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

    public function morefeedsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $ActivitiesComments = new Brigade_Db_Table_SiteActivityComments();
        $Brigades = new Brigade_Db_Table_Brigades();
        $Users = new Brigade_Db_Table_Users();
        $activities = $Brigades->getActivityFeed($parameters['ProjectId'], $_POST['limit'], $_POST['offset']);
        foreach ($activities as $activity) {
            if (date('Y-m-d', strtotime($activity['ActivityDate'])) < date('Y-m-d')) {
                $activity['ActivityDate'] = date('Y-m-d', strtotime($activity['ActivityDate']));
            }
            $avatar = $comment_box = '';
            $comments_list = $ActivitiesComments->getSiteActivityComments($activity['SiteActivityId']);
            $comments = "<ul id='ul_".$activity['SiteActivityId']."'".(count($comments_list) > 0 ? "" : "style='display:none'").">";
            foreach($comments_list as $comment) {
                $comments .= '<li><table><tr><td style="width:34px;"><img src="/profile/loadimage?UserId='.$comment['UserId'].'" /></td><td style="width:316px;"><span class="comment"><a href="/'.$comment['URLName'].'">'.stripslashes($comment['FirstName']).' '.stripslashes($comment['LastName']).'</a>&nbsp;&nbsp;'.stripslashes($comment['Comment']).'<br><span class="time">'.$this->getDateFormat($comment['CommentedOn']).'</span></span></td></tr></table></li>';
            }
            $comments .= "</ul>";
            /* if ($this->isLoggedIn) {
                //$comment_link = "<a href='javascript:;' id='commentlink_".$activity['SiteActivityId']."' style='float:right;'>Comment</a>";
                $avatar = "<img id='avatar_".$activity['SiteActivityId']."' src='/profile/loadimage?UserId=".$this->curr_user['UserId']."' height='25px' width='25px' style='float:left; margin-right:3px; vertical-align:top; display:none;' />";
                //$comment_box = '<div style="padding:3px; width:50%; margin:0 0 3px 34px; float:left;">'.$comment_link.$avatar.'<textarea id="comment_'.$activity['SiteActivityId'].'" cols="50" rows="1" style="float:left; font-size:11px; height:20px; width:90%; display:none;">Write a comment...</textarea><input id="submit_'.$activity['SiteActivityId'].'" class="btn btngreen" style="display:none; float:right;" type="submit" value="Comment"/></div>';
            } */
            if ($activity['ActivityType'] == 'Uploads') {
                $brigadeInfo = $Brigades->loadInfo1($activity['SiteId']);
                if (!empty($activity['CreatedBy'])) {
                    $userInfo = $Users->loadInfo($activity['CreatedBy']);
                    $display = "<p style='margin-bottom:-20px;'><img src='/public/images/ico/photo.gif'>&nbsp;&nbsp;<a href='/".$userInfo['URLName']."'>".stripslashes($userInfo['FullName'])."</a> added ".$activity['TotalCount']." photo".($activity['TotalCount'] > 1 ? "s" : "")." to <a href='/".$brigadeInfo['projectLink']."'>".stripslashes($brigadeInfo['Name'])."</a> - ".$this->getDateFormat($activity['ActivityDate']).".";
                }
            } else if ($activity['ActivityType'] == 'User Donation') {
                $brigadeInfo = $Brigades->loadInfo1($activity['SiteId']);
                $userInfo = $Users->loadInfo($activity['CreatedBy']);
                $display = "<p style='margin-bottom:-20px;'><img src='/public/images/ico/donation.gif'>&nbsp;&nbsp;<a href='/".$userInfo['URLName']."'>".stripslashes($userInfo['FullName'])."</a> donated ".$brigadeInfo['Currency'].number_format($activity['Details'])." - ".$this->getDateFormat($activity['ActivityDate']).".";
            } else if ($activity['ActivityType'] == 'Joined Brigade') {
                $brigadeInfo = $Brigades->loadInfo1($activity['SiteId']);
                if ($activity['TotalCount'] == 0) {
                    $userInfo = $Users->loadInfo($activity['Recipient']);
                    $display = "<a href='/".$userInfo['URLName']."'>".stripslashes($userInfo['FullName'])."</a> joined <a href='/".$brigadeInfo['projectLink']."'>".stripslashes($brigadeInfo['Name'])."</a> - ".$this->getDateFormat($activity['ActivityDate']);
                } else {
                    $display = $activity['TotalCount']." users joined <a href='/".$brigadeInfo['projectLink']."'>".stripslashes($brigadeInfo['Name'])."</a> - ".$this->getDateFormat($activity['ActivityDate']);
                }
            } else if ($activity['ActivityType'] == 'Wall Post') {
                $display = "<p style='margin-bottom:-20px;'><table style='margin-bottom:-20px;'><tr><td width=34><img src='/profile/loadimage?UserId=".$activity['CreatedBy']."' width='30' height='30'></td><td><a href='/".$activity['URLName']."'>".stripslashes($activity['FirstName'])." ".stripslashes($activity['LastName'])."</a>&nbsp;&nbsp;".stripslashes($activity['Details'])."<br>".$this->getDateFormat($activity['ActivityDate']).".</td></tr></table>";
            }
            if(!empty($display)) { echo "$display<br></p><div class='clear1'></div>"; }
        }
        if (count($activities) < 5) {
            echo '<script> $("#see-more").hide(); </script>';
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
                header("location: /project/cropimage/?ProjectId=$ProjectId");
            }
        }
    }

    public function getHeaderMedia(Project $project) {
        $siteMedia = new Brigade_Db_Table_Media();

        $this->view->siteBanner = false;
        if(!empty($project->groupId)) {
            if (!empty($project->group->bannerMediaId)) {
                $siteBanner = $siteMedia->getSiteMediaById($project->group->bannerMediaId);
                $this->view->siteBanner = $siteBanner;
                $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
            }
        } else if(!empty($project->organizationId)) {
            if (!empty($project->organization->bannerMediaId)) {
                $siteBanner = $siteMedia->getSiteMediaById($project->organization->bannerMediaId);
                $this->view->siteBanner = $siteBanner;
                $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
            } else {
                $siteMedia = $siteMedia->getSiteMediaById($project->organization->logoMediaId);
            }
            $this->view->sitemedia    = $siteMedia;
            $this->view->organization = $project->organization;
        }
    }

    /**
     * Volunteer Profile Page
     */
    public function volunteerAction() {
        $params    = $this->_getAllParams();
        $project   = Project::getByUrl($params['ProjectURL']);
        $user      = User::getByUrl($params['UserUrl']);
        $volunteer = $project->getVolunteerByUser($user);

        $this->view->user    = $user;
        $this->view->project = $project;
        $this->view->group   = $project->group;

        $this->view->currentTab        = 'initiatives';
        $this->view->userProjectRaised = $volunteer->raised;
        $this->view->userProjectGoal   = $volunteer->userDonationGoal;
        $this->view->breadcrumb        = $this->view->breadcrumbHelper($project);
        $this->view->rightbarHelper($project, $volunteer);
        $this->view->headTitle(stripslashes($project->name));

        $this->view->activityFeed = Activity::getByProjectAndUser($project, $user, 5);

        if(!empty($project->programId)) {
            $this->view->program  = $project->program;
        }

        if(!empty($project->groupId)) {
            $this->view->group = $project->group;

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');
        } else if(!empty($project->organizationId)) {
            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');
        } else {
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('project/header.phtml');
            $this->view->soloProject = true;
        }

        $this->view->urlName = $project->urlName;

        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');
        $this->_helper->viewRenderer->setRender('volunteerprofile');
    }

    /**
     * Stop volunteering from a project.
     * Remove it from right_bar. Ussage: User itself and admin.
     */
    public function stopvolunteeringAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters = $this->_getAllParams();
        $project    = Project::get($parameters['ProjectId']);
        $siteId     = ($project->groupId) ? $project->groupId : $project->organizationId;
        if (Role::isAdmin($this->sessionUser->id, $siteId)) {
            Role::deleteRolesBySite($siteId, $this->sessionUser->id);
        }

        $volunteer  = $project->getVolunteerByUser($this->view->userNew);
        $project->stopVolunteering($this->view->userNew);


        // InfusionSoft
        if ($volunteer) {
            $this->infusionSoftIntegrationVol($volunteer, false);
            $this->salesForceIntegrationVolunteer($volunteer, true);
        }

        if($project->googleId == 1 || $project->bluepayId = 1 ||
           $project->googleId == 2 || $project->paypalId == 211) {
            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                EventDispatcher::$VOLUNTEER_QUIT,
                array($this->view->userNew, $project)
            );
        }
    }

    /**
     * Update member user to infusion soft.
     *
     * @param Member $member Member instance.
     *
     * @return void.
     */
    protected function infusionSoftIntegration($member, $addMissingContact = true) {
        $configIS = Zend_Registry::get('configuration')->infusionsoft;
        if (!($configIS->active &&
            in_array($member->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('InfusionSoft::Project::MemberContact');
        $is = Infusionsoft::getInstance();
        if ($addMissingContact) {
            $is->addMemberContact($member);
        } else {
            $is->updateMemberContact($member);
        }
    }

    /**
     * Update volunteer user to infusion soft.
     *
     * @param Member $member Member instance.
     *
     * @return void.
     */
    protected function infusionSoftIntegrationVol($volunteer, $addMissingContact = true) {
        $configIS = Zend_Registry::get('configuration')->infusionsoft;
        if (!($configIS->active &&
            in_array($member->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('InfusionSoft::Project::MemberContact');
        $is = Infusionsoft::getInstance();
        if ($addMissingContact) {
            $is->addVolunteerContact($member);
        } else {
            $is->updateVolunteerContact($member);
        }
    }

    /**
     * Add volunteer information under infusionsoft.
     */
    protected function salesForceIntegrationVolunteer($volunteer, $stop = false) {
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
            if ($stop) {
                $salesforce->removeVolunteer($volunteer);
            } else {
                $salesforce->addVolunteer($volunteer);
            }
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
     * Update ptoject information under infusionsoft.
     */
    protected function salesForceIntegrationProject($project, $oldName = '') {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($project->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Project::Update');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($project->organization)) {
            $salesforce->updateOpportunityInfo($project, $oldName);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$project->organizationId
            );
        }
    }

    /**
     * Delete ptoject information under infusionsoft.
     */
    protected function salesForceIntegrationDeleteProject($project) {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($project->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Project::Delete');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($project->organization)) {
            $salesforce->deleteOpportunity($project);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$project->organizationId
            );
        }
    }

    /**
     * Delete project from toolbar for only admin
     */
    public function deleteAction() {
        if ($this->view->isAdmin) {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $params  = $this->_getAllParams();
            $project = Project::get($params['ProjectId']);
            $project->delete();
            $this->salesForceIntegrationDeleteProject($project);
        } else {
            $this->getResponse()->setHttpResponseCode(404);
        }
    }
}
