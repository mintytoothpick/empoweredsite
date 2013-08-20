<?php

/**
 * OrganizationController - The "organizations" controller class
 *
 * @author
 * @version
 */
set_time_limit(0);
require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Cities.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Countries.php';
require_once 'Brigade/Db/Table/GroupSurveys.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/UserRoles.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Regions.php';
require_once 'Brigade/Db/Table/Blogs.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Util/ImageResize.php';
require_once 'Brigade/Util/ImageCrop.php';
require_once 'Zend/Paginator.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/VolunteerNotes.php';
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';
require_once 'Brigade/Db/Table/Paypal.php';
require_once 'Brigade/Db/Table/ProjectDonationNotes.php';
require_once 'Brigade/Db/Table/GroupEmailAccounts.php';
require_once 'Brigade/Reporting/FusionCharts.php';
require_once 'Brigade/Reporting/FusionCharts/MSLine.php';
require_once 'Brigade/Util/DateTime.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/FundraisingSuggestedDonations.php';
require_once 'Brigade/Db/Table/ReceiptMessages.php';
require_once 'Brigade/Db/Table/EventTickets.php';
require_once 'Brigade/Lib/Validate/DbUnique.php';
require_once 'Brigade/Db/Table/PaypalAccounts.php';
require_once 'Zend/Validate/EmailAddress.php';
require_once 'BaseController.php';

require_once 'Group.php';
require_once 'Event.php';
require_once 'Organization.php';
require_once 'GiftAid.php';
require_once 'Salesforce.php';
require_once 'FlyForGood.php';

class NonprofitController extends BaseController {

    protected $_http;

    function init() {
        parent::init();
    }

    public function indexAction() {
        $parameters = $this->_getAllParams();
        $UserRoles        = new Brigade_Db_Table_UserRoles();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();

        // new url for cms redirect
        $config = Zend_Registry::get('configuration');
        if ($config->cms_migrate->active &&
            in_array($parameters['NetworkId'], $config->cms_migrate->org->toArray())
        ) {
            if (!($this->view->isLoggedIn && $this->view->isAdmin)) {
                $this->_helper->redirector->gotoUrl($config->cms_migrate->host);
            }
        }

        $organization = Organization::get($parameters['NetworkId']);

        $session = new Zend_Session_Namespace('profile_video');
        if(!empty($session->showAdminVideo)) {
            $this->view->showAdminVideo = true;
            $session->showAdminVideo = false;
        }

        $this->view->headTitle(stripslashes($organization->name));

        $GroupMembers              = new Brigade_Db_Table_GroupMembers();
        $this->view->members       = $GroupMembers->getOrganizationMembers($organization->id, array(0,1), 0, true, "", false, 2);
        $this->view->members_count = $organization->countMembers;

        // check if user is a member of this organization to display the button "become a member"
        $this->view->is_member = false;
        $this->view->joinlink = 'href="#"';
        if ($this->view->isLoggedIn) {
            if ($GroupMembers->isMemberExists($organization->id, $_SESSION['UserId'], 'organization')) {
                $this->view->is_member = true;
            }
            if (!$this->view->is_member) {
                $this->view->joinlink = 'href="javascript:joinOrganization(\''.$organization->id.'\', \''.$_SESSION['UserId'].'\')"';
            }
        } else {
            $this->view->joinlink = 'href="javascript:;" class="join"';
        }

        // get organization uploaded files
        $Files = new Brigade_Db_Table_Files();
        $this->view->files = $Files->getSiteFiles($organization->id);

        $Countries = new Brigade_Db_Table_Countries();
        $this->view->country_list = $Countries->getCountries('preset');

        if ($organization->contact->countryId) {
            $Regions = new Brigade_Db_Table_Regions();
            $this->view->region_list = $Regions->getCountryRegions($organization->contact->countryId);
        }
        if ($organization->contact->stateId) {
            $Cities = new Brigade_Db_Table_Cities();
            $this->view->city_list = $Cities->getRegionCities($organization->contact->stateId);
        }

        $this->view->projectDonations = $ProjectDonations->getProjectDonations($organization->id);
        $this->view->administrators   = $UserRoles->getSiteAdmin($organization->id);

        //membership
        $this->view->hasMembership = false;
        $config = Zend_Registry::get('configuration');
        if($config->chapter->membership->enable &&
          !in_array(
            $organization->id,
            $config->chapter->membership->settings->toArray()) &&
          in_array(
            $organization->id,
            $config->chapter->membership->active->toArray())
        ) {
            $this->view->hasMembership = true;
        }

        //fly for good
        $this->view->hasFlyForGood = false;
        if (in_array($organization->id, Organization::$withFlyForGood)) {
            $this->view->hasFlyForGood = true;
        }

        $this->getHeaderMedia($organization);

        $this->view->currentTab   = 'home';
        $this->view->organization = $organization;
        $this->view->urlName = $organization->urlName;
        $this->view->toolPopupObj = $organization; // for logo upload toolbox

        if ($this->view->isAdmin) {
            //progress bar
            $this->view->toolsUsage = 15;
            if ($organization->hasUploadedMembers) {
                $this->view->toolsUsage += 15;
            }
            if ($organization->hasSharedSocialNetworks) {
                $this->view->toolsUsage += 20;
            }
            if ($organization->countActivities > 0 || $organization->countCampaigns > 0 ||
                $organization->countEvents > 0) {
                $this->view->toolsUsage += 40;
            }
            if (!empty($organization->logo->systemMediaName) ||
                isset($siteBanner) &&
                !empty($siteBanner['SystemMediaName'])) {
                $this->view->toolsUsage += 10;
            }
            $this->view->render('administrator/progress_bar.phtml');
            $this->view->render('administrator/popup_upload_logo.phtml');
            $this->view->render('administrator/popup_upload_banner.phtml');
            $this->view->render('nonprofit/toolbox.phtml');
        }
        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($organization);
        $this->renderPlaceHolders();
    }

    /**
     * Programs Actions - New Design.
     *
     * @return void
     */
    public function programsAction() {
        $parameters   = $this->_getAllParams();
        $organization = Organization::get($parameters['NetworkId']);

        $this->view->headTitle(stripslashes($organization->name).' | '. $organization->programNamingPlural);

        $_REQUEST['subpage'] = 'programs';
        $_REQUEST['URLName'] = $organization->urlName;

        $this->getHeaderMedia($organization);

        $this->view->currentTab = 'programs';

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper(
            $organization,
            $organization->programNamingPlural
        );

        $this->view->organization = $organization;
        if (isset($parameters['searchFilter']) && trim($parameters['searchFilter']) != '') {
            $programs = Program::getSearchList(
                                    $parameters['searchFilter'],
                                    $organization);

            $this->view->searchtxt = $parameters['searchFilter'];
        } else {
            $programs = $organization->programs;
        }
        $this->view->programs = $programs;

        $this->renderPlaceHolders();
    }

    /**
     * Tab groups for selected network.
     * (new design).
     *
     * @return void
     */
    public function chaptersAction() {
        $parameters = $this->_getAllParams();

        if(isset($parameters['ProgramId'])) {
            $program             = Program::get($parameters['ProgramId']);
            $organization        = $program->organization;
            $_REQUEST['URLName'] = $program->urlName;

            $this->view->headTitle(stripslashes($program->name).' | Chapters');
        } else {
            $organization        = Organization::get($parameters['NetworkId']);
            $_REQUEST['URLName'] = $organization->urlName;

            $this->view->headTitle(stripslashes($organization->name).' | '.$organization->groupNamingPlural);
        }


        if (isset($program)) {
            if (isset($parameters['Coalition'])) {
                $program->showCoalitions = true;
            }
            if (isset($parameters['searchFilter']) &&
                trim($parameters['searchFilter']) != '') {
                $groups = Group::getListByProgram(
                                        $program,
                                        $parameters['searchFilter']);
            } else {
                $groups = $program->groups;
            }
            $countGroups = count($groups);
        } else {
            if (isset($parameters['searchFilter']) &&
                trim($parameters['searchFilter']) != '') {
                $groups = Group::getListByOrganization(
                                        $organization->id,
                                        $parameters['searchFilter']);
            } else {
                $groups = $organization->groups;
            }
            $countGroups = count($groups);
        }

        //pagination
        $_REQUEST['subpage']   = 'chapters';
        $this->view->paginator = Zend_Paginator::factory($groups);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);

        if (isset($program)) {
            $this->view->program = $program;
        }
        $this->view->organization = $organization;
        $this->view->countGroups  = $countGroups;

        if (!isset($parameters['filter'])) {
            // tabs
            $this->view->currentTab = 'chapters';

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                (isset($program)) ? $program : $organization,
                (isset($organization)) ? $organization->groupNamingPlural : 'Chapters'
            );

            $this->getHeaderMedia($organization);

            //modules
            $this->renderPlaceHolders();
        } else {
            $this->view->filter = true;
            $this->render('tabscontent/groups');
            $this->_helper->layout()->disableLayout();
        }
    }

    /**
     * Ajax response to filter by groups.
     *
     * @param String ProgramId Id of program selected.
     *
     * @return json All groups filtered by program sent.
     */
    public function getgroupsAction() {
        $parameters = $this->_getAllParams();
        $Group      = new Brigade_Db_Table_Groups();
        $this->_helper->json($Group->listByProgram($parameters['ProgramId']));
    }

    /**
     * Ajax response to filter by projects/actions.
     *
     * @param String ProgramId Id of program selected.
     * @param String GroupId Id of program selected.
     *
     * @return json All projects filtered by program sent.
     */
    public function getprojectsAction() {
        $parameters = $this->_getAllParams();

        if (isset($parameters['GroupId']) && !empty($parameters['GroupId'])) {
            $Group = new Brigade_Db_Table_Groups();
            $this->_helper->json($Group->loadBrigades($parameters['GroupId'], 'all'));
        } else if (isset($parameters['ProgramId']) && !empty($parameters['ProgramId'])) {
            $Program = new Brigade_Db_Table_Programs();
            $this->_helper->json($Program->loadBrigades($parameters['ProgramId']));
        }
    }

    public function activitiesAction() {
        $parameters = $this->_getAllParams();

        $status = explode('-',$parameters['List']);
        $status = $status[0];

        if (isset($parameters['Status'])) {
            $status = $parameters['Status'];
        }
        $this->view->status=$status;

        if(isset($parameters['GroupId'])) {
            $group               = Group::get($parameters['GroupId']);
            $organization        = Organization::get($group->organizationId);
            $_REQUEST['URLName'] = $group->urlName;

            $this->view->headTitle(stripslashes($group->name).' | Activities');
        } else if(isset($parameters['ProgramId'])) {
            $program             = Program::get($parameters['ProgramId']);
            $organization        = $program->organization;
            $_REQUEST['URLName'] = $program->urlName;

            $this->view->headTitle(stripslashes($program->name).' | Activities');
        } else {
            $organization        = Organization::get($parameters['NetworkId']);
            $_REQUEST['URLName'] = $organization->urlName;

            $this->view->headTitle(stripslashes($organization->name).' | Activities');
        }
        if (!$organization->hasActivities) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $searchText = false;
        if (isset($parameters['searchFilter']) && trim($parameters['searchFilter']) != '') {
            $searchText = $parameters['searchFilter'];
        }

        if (isset($group)) {
            $activities = Project::getListByGroup($group,
                                    $status,
                                    0,
                                    $searchText
            );
            $this->view->group = $group;

        } else if (isset($program)) {
            if (isset($parameters['Coalition'])) {
                $program->showCoalitions = true;
            }
            $activities = Project::getListByProgram(
                                    $program,
                                    $status,
                                    0,
                                    $searchText
            );
            $this->view->program = $program;
        } else {
            $activities = Project::getListByOrganization(
                                    $organization,
                                    $status,
                                    0,
                                    $searchText
            );
        }
        $this->view->countRes = count($activities);
        $this->getHeaderMedia($organization);

        //pagination
        $_REQUEST['subpage']   = $parameters['List'];
        $this->view->paginator = Zend_Paginator::factory($activities);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);

        //display each tab?
        $this->view->list         = ucfirst($status);
        $this->view->organization = $organization;

        if (!isset($parameters['filter'])) {
            //curent tab
            $this->view->currentTab = 'activities';

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                (isset($program)) ? $program : $organization,
                'Volunteer Activities'
            );
            $this->renderPlaceHolders();
        } else {
            $this->view->filter = true;
            $this->render('tabscontent/activities');
            $this->_helper->layout()->disableLayout();
        }
    }

    public function campaignsAction() {
        $parameters = $this->_getAllParams();
        $status     = explode('-',$parameters['List']);
        $status     = $status[0];
        if (isset($parameters['Status'])) {
            $status = $parameters['Status'];
        }
        $this->view->status=$status;

        $searchText = false;
        if (isset($parameters['searchFilter']) && trim($parameters['searchFilter']) != '') {
            $searchText = $parameters['searchFilter'];
        }

        if (isset($parameters["GroupId"])) {
            $group             = Group::get($parameters['GroupId']);
            $organization        = Organization::get($group->organizationId);
            $_REQUEST['URLName'] = $group->urlName;

            $campaigns = Project::getListByGroup($group,
                                    ($status=='active') ? 'in progress' : 'completed',
                                    1,
                                    $searchText
            );

        } else if(isset($parameters['ProgramId'])) {
            $program             = Program::get($parameters['ProgramId']);
            $_REQUEST['URLName'] = $program->urlName;

            $this->view->headTitle(stripslashes($program->name).' | Campaigns');

            if (isset($parameters['Coalition'])) {
                $program->showCoalitions = true;
            }
            $organization = $program->organization;
            $campaigns    = Project::getListByProgram(
                                $program,
                                ($status=='active') ? 'in progress' : 'completed',
                                1,
                                $searchText);

            $this->view->countRes = $program->countCampaigns;
        } else {
            $organization = Organization::get($parameters['NetworkId']);
            $this->view->headTitle(stripslashes($organization->name).' | Campaigns');

            $_REQUEST['URLName'] = $organization->urlName;
            $campaigns           = Project::getListByOrganization(
                                    $organization,
                                    ($status=='active') ? 'upcoming' : 'completed',
                                    1,
                                    $searchText);
        }
        if (!$organization->hasCampaigns) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->view->countRes = count($campaigns);

        $this->getHeaderMedia($organization);

        $_REQUEST['subpage']   = $parameters['List'];
        $this->view->paginator = Zend_Paginator::factory($campaigns);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);

        $this->view->list         = ucfirst($status);
        $this->view->organization = $organization;

        if (!isset($parameters['filter'])) {
            $this->view->currentTab = 'campaigns';
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                (isset($program)) ? $program : $organization,
                'Fundraising Campaigns'
            );
            $this->renderPlaceHolders();
        } else {
            $this->view->filter = true;
            $this->render('tabscontent/campaigns');
            $this->_helper->layout()->disableLayout();
        }
    }

    public function eventsAction() {
        $parameters = $this->_getAllParams();
        $status     = explode('-',$parameters['List']);
        $status     = $status[0];
        $searchText = false;
        if (isset($parameters['searchFilter']) && trim($parameters['searchFilter']) != '') {
            $searchText = $parameters['searchFilter'];
        }
        if (isset($parameters['ProgramId'])) {
            $program             = Program::get($parameters['ProgramId']);
            $organization        = $program->organization;
            $events              = Event::getListByProgram(
                                        $program->id,
                                        $status,
                                        $searchText
            );
            $_REQUEST['URLName'] = $program->urlName;

            $this->view->headTitle(stripslashes($program->name).' | Events');
        } elseif (isset($parameters['GroupId']) && $parameters['GroupId'] != 'all') {
            $group               = Group::get($parameters['GroupId']);
            $organization        = $group->organization;
            $events              = Event::getListByGroup(
                                        $group,
                                        $status,
                                        $searchText
            );
            $_REQUEST['URLName'] = $group->urlName;
            $this->view->headTitle(stripslashes($group->name).' | Events');
        } else {
            $organization        = Organization::get($parameters['NetworkId']);
            $events              = Event::getListByOrganization(
                                        $organization->id,
                                        $status,
                                        $searchText
            );
            $_REQUEST['URLName'] = $organization->urlName;
        }
        $this->view->countRes = count($events);
        $this->getHeaderMedia($organization);

        if (isset($parameters['List'])) {
            $list = $parameters['List'];
        } else {
            $list = 'upcoming';
        }
        $this->view->list      = ucfirst($status);
        $_REQUEST['subpage']   = $list;
        $this->view->paginator = Zend_Paginator::factory($events);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);
        if (isset($program)) {
            $this->view->program = $program;
        }
        $this->view->organization = $organization;

        if (!isset($parameters['filter'])) {
            $this->view->currentTab = 'events';
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                (isset($program)) ? $program : $organization,
                'Events'
            );
            $this->renderPlaceHolders();
        } else {
            $this->view->filter = true;
            $this->render('tabscontent/events');
            $this->_helper->layout()->disableLayout();
        }
    }

    public function membersAction() {
        // Lazy hack. Please optimize memory usage.
        ini_set('memory_limit', '256M');

        $parameters   = $this->_getAllParams();
        $organization = Organization::get($parameters['NetworkId']);

        $this->view->headTitle(stripslashes($organization->name).' | Members');

        $_REQUEST['URLName'] = $organization->urlName;

        $searchText = null;
        if (isset($parameters['searchFilter']) && trim($parameters['searchFilter']) != '') {
            $parameters['searchFilter'] = preg_replace('/\s\s+/', ' ', $parameters['searchFilter']);
            $searchText = $parameters['searchFilter'];
        }
        $this->view->list    = 'Members';
        $_REQUEST['subpage'] = 'members';

        if($parameters['List'] == 'Leadership' || isset($parameters['leaderSearch'])) {
            $members   = User::getSiteAdmin($organization->id, $searchText);
            $_REQUEST['subpage'] = 'leadership';
            $this->view->list    = 'Leaders';
        } else if (!empty($parameters['GroupId'])) {
            $members = User::getByGroup(
                        Group::get($parameters['GroupId']),
                        $searchText
            );
        } else if(!empty($parameters['ProgramId'])) {
            $members = User::getByProgram(
                        Program::get($parameters['ProgramId']),
                        $searchText
            );
        } else {
            $members = Member::getListByOrganization(
                        $organization,
                        array(1), //TODO remove this
                        $searchText
            );
        }
        $this->view->countRes = count($members);
        $this->getHeaderMedia($organization);

        $this->view->paginator = Zend_Paginator::factory($members);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);

        $this->view->organization = $organization;

        if (!isset($parameters['filter'])) {
            //current tab
            $this->view->currentTab = 'members';
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                $organization,
                'Members'
            );
            $this->renderPlaceHolders();
        } else {
            $this->view->filter = true;
            $this->render('tabscontent/members');
            $this->_helper->layout()->disableLayout();
        }
    }

    /**
     * Add / remove admin of org.
     */
    public function adminuserAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->_getAllParams();
        if ($params['UserId'] && $params['NetworkId']) {
            $org  = Organization::get($params['NetworkId']);
            $user = User::get($params['UserId']);
            if (isset($params['removeAdmin'])) {
                $org->removeAdmin($user);
            } elseif (isset($params['setAdmin'])) {
                $org->setAdmin($user);
            } elseif (isset($params['removeMember'])) {
                $org->removeMember($user);
            }
        }
        echo 'success';
    }


    /**
     * Admin change styles of the organization.
     */
    public function savestylesAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters = $this->_getAllParams();
        $org = Organization::get($parameters['NetworkId']);

        $org->cssStyles = $parameters['css'];
        $org->save();
    }

    /**
     * View and generate Gift Aid report for organizations that have it.
     */
    public function giftaidreportAction() {
        $params = $this->_getAllParams();
        $org    = Organization::get($params['NetworkId']);
        if (!$org->hasGiftAid()) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $this->view->breadcrumb = $this->view->breadcrumbHelper(
            $org, 'Gift Aid Report'
        );
        $objFilter    = $org;
        $filterMethod = 'getListByOrganization';
        if (!empty($params['ProjectId'])) {
            $filterMethod = 'getListByProject';
            $objFilter    = Project::get($params['ProjectId']);

            $this->view->project = $objFilter;
            $this->view->group   = $objFilter->group;
            $this->view->program = $objFilter->program;
        } elseif (!empty($params['GroupId'])) {
            $filterMethod = 'getListByGroup';
            $objFilter    = Group::get($params['GroupId']);

            $this->view->group   = $objFilter;
            $this->view->program = $objFilter->program;
        } elseif (!empty($params['ProgramId'])) {
            $filterMethod = 'getListByProgram';
            $objFilter    = Program::get($params['ProgramId']);

            $this->view->program = $objFilter;
        }
        if (empty($_REQUEST['FromDate'])) {
            $_REQUEST['FromDate']  = date('Y/m/01');
            $_REQUEST['ToDate']    = date('Y/m/31');
        }
        $list = GiftAid::$filterMethod(
            $objFilter,
            !empty($params['search']) ? $params['search'] : false,
            !empty($params['startDate']) ? $params['startDate'] : date('Y/m/01'),
            !empty($params['endDate']) ? $params['endDate'] : date('Y/m/31')
        );

        $paginator = Zend_Paginator::factory($list);
        $page      = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage(isset($_POST['limit']) ? $_POST['limit'] : 10);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator    = $paginator;
        $this->view->list         = $list;
        $this->view->organization = $org;

        $this->renderPlaceHolders();
    }


    public function filterbrigadeAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $NetworkId = $_REQUEST['NetworkId'];
        $Type = $_REQUEST['Type'];
        $search_text = $_REQUEST['search_text'];
        $Organizations = new Brigade_Db_Table_Organizations();
        $contactinfo = new Brigade_Db_Table_ContactInformation();
        $sitemedia = new Brigade_Db_Table_Media();
        $brigades = new Brigade_Db_Table_Brigades();
        $Groups = new Brigade_Db_Table_Groups();
        $brigades_list = $Organizations->loadProjects($NetworkId, $Type, false, $search_text);
        foreach ($brigades_list as $brigade) {
            $total_volunteers = count($brigades->loadVolunteers($brigade["ProjectId"]));
            $space_available = $total_volunteers > $brigade["VolunteerGoal"] ? 0 : $brigade["VolunteerGoal"] - $total_volunteers;
            $media_src = '';
            $media = $sitemedia->getSiteMediaGallery($brigade['ProjectId'], "");
            if (count($media) > 0) {
                $media_src = '/public/Media/'.$media[0]['SystemMediaName'];
            } else {
                // get the group image by group's LogoMediaId
                $groupInfo = $Groups->loadInfo($brigade['GroupId']);
                $media = $sitemedia->getSiteMediaById($groupInfo['LogoMediaId']);
                // echo '$sitemedia->getSiteMediaById('.$groupInfo['LogoMediaId'].')';
                $media_src = '/public/Media/'.$media['SystemMediaName'];
            }
            echo '
                <div class="box06">
                    <div class="bst01">
                        <a href="/'.$brigade["URLName"].'">
                            <img src="'.$media_src.'" alt="" width="74" height="50"/>
                        </a>
                        <div class="bst03">
                            <div class="bst04">
                                <div class="bst05"><span><span><span id="ctl00_ContentPHMain_ctrlGroupBrigDtls_rptGroupBrigDtls_ctl00_lblVoluntSpaceEmpty">'.$space_available.'</span></span> / </span> '.$brigade["VolunteerGoal"].'</div>
                                Spaces<br />Available
                            </div>
                        </div>
                    </div>
                    <div class="bst02">
                        <div class="bst06">
                            <div class="bst07">Group: </div>
                            <a href="/group/?GroupId='.$brigade["GroupId"].'" >'.$brigade["GroupName"].'</a>
                        </div>
                        <div class="bst06">
                            <div class="bst07">Brigade: </div>
                                <a href="/'.$brigade['URLName'].'">'.$brigade["Name"].'</a>
                        </div>
                        <div class="bst08">
                            <div class="bst07">Where: </div>
                                '.$contactinfo->getContactInfo($brigade["ProjectId"], "Location").'
                        </div>
                        <div class="bst09">
                            <div class="bst07">When: </div>
                                '.date("M d, Y", strtotime($brigade["StartDate"])).' - '.date("M d, Y", strtotime($brigade["EndDate"])).'
                        </div>
                        <div class="bst10">
                            <div class="but006"><a href="/profile/login" target="_blank">Volunteer</a></div>
                            <div class="but006"><a href="/donation/'.$brigade["ProjectId"].'">Donate</a></div>
                        </div>
                    </div>
                    <div class="clear"></div>
                </div>
            ';
        }
    }

    public function preDispatch() {
        parent::preDispatch();
        $this->view->media_path = "/public/Media/";
    }

    public function cropimageAction() {
        $parameters           =  $this->_getAllParams();
        $this->view->network  =  Organization::get($parameters['NetworkId']);

        if ($_POST) {
            //grab values from jcrop
            $x = $_POST["x"];
            $y = $_POST["y"];
            $width = $_POST["w"];
            $height = $_POST["h"];

            $ImageCrop = new Brigade_Util_ImageCrop();
            $this->view->image_preview = $media_name = $this->view->network->urlName."-logo";
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_{$this->view->network->id}.jpg";
            $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Media/$media_name.jpg";
            $bigger_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Media/full/$media_name.jpg";
            if ($width > 0 && $height > 0) {
                if (file_exists($thumb_image_location)) {
                    unlink($thumb_image_location);
                }
                if (file_exists($bigger_image_location)) {
                    unlink($bigger_image_location);
                }

                // resize the selection to 140x70
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
                $Organizations = new Brigade_Db_Table_Organizations();
                $URLName = $Organizations->getURLName($this->view->network->id);
                header("location: /$URLName");
            } else {
                $this->view->preview_image = 1;
            }
        }
    }

    public function programdetailAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout->disableLayout();
        $Programs = new Brigade_Db_Table_Programs();

        $ProgramId = $parameters['ProgramId'];
        if (!empty($ProgramId)) {
            //get the details based on $ProgramId
            $this->view->data = $Programs->loadInfo($ProgramId);
        }
    }

    public function volprogramdetailAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout->disableLayout();
        $Programs = new Brigade_Db_Table_Programs();
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $Media = new Brigade_Db_Table_Media();

        $ProgramId = $parameters['ProgramId'];
        if (!empty($ProgramId)) {
            //get the details based on $ProgramId
            $this->view->data = $Programs->loadInfo($ProgramId);
            $this->view->contactinfo = $ContactInfo->getContactInfo($ProgramId);
            $this->view->completed_brigades = count($Programs->loadBrigades($ProgramId, 'completed'));
            $this->view->profile_image = $Media->getSiteMediaById($this->view->data['LogoMediaId']);
            $this->view->NetworkId = $parameters['NetworkId'];

            // admin panel for programs
            if($this->_helper->authUser->isLoggedIn()) {
                $UserRoles = new Brigade_Db_Table_UserRoles();
                $role = $UserRoles->getUserRole($_SESSION['UserId']);
                $hasAccess = $UserRoles->UserHasAccess($ProgramId, $_SESSION['UserId'], 'program');
                if ($hasAccess && $role['RoleId'] == 'ADMIN' || $role['RoleId'] == 'GLOB-ADMIN') {
                    $this->view->isAdmin = true;
                }
            }
        }
    }

    public function searchgroupAction(){
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters = $this->_getAllParams();
        $ProgramId = $parameters['ProgramId'];
        $keyword = $parameters['keyword'];
        $sortby = $parameters['sortby'];
        $where = "";

        if (!empty($ProgramId) ) {
            $groupManage = new Brigade_Db_Table_Groups();
            if (!empty($keyword)){
                $where = "GroupName LIKE '%$keyword%' OR Description LIKE '%$keyword%'";
            }
            $groupList = $groupManage->listOrgGroups($ProgramId,$where);
            if (count($groupList)) {
                foreach ($groupList as $i=>$group){
                    $contactinfo = new Brigade_Db_Table_ContactInformation();
                    $donations = new Brigade_Db_Table_ProjectDonations();
                    $logoManage = new Brigade_Db_Table_Media();

                    $groupList[$i]['groupLocation'] = $contactinfo->getContactInfo($group['GroupId'], 'Location');
                    $groupList[$i]['groupsupporters'] = $groupManage->loadSupporters($group['GroupId']);
                    $groupList[$i]['upcoming'] = $groupManage->loadBrigadesCount($group['GroupId'],'upcoming');
                    $groupList[$i]['completed'] = $groupManage->loadBrigadesCount($group['GroupId'],'completed');
                    $groupList[$i]['total_donations'] = $donations->getGroupDonations($group['GroupId']);
                    $groupList[$i]['NetworkLogo'] = $logoManage->getSiteMediaById($group['LogoMediaId']);
                }
            }

            $payload = json_encode($groupList);
            header("content-type: application/x-json; charset=utf-8");
            echo $payload;
        }
    }

    public function loadlocationsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_REQUEST['field'] == 'state') {
        $Regions = new Brigade_Db_Table_Regions();
            $CountryRegions = $Regions->getCountryRegions($_REQUEST['location']);
            $list = "";
            $list .= '<select type="text" name="RegionId" id="RegionId" style="width:300px" onchange="populateLocation(\'city\', this.value); $(\'#Region\').val(this.options[this.selectedIndex].text);">';
            $list .= '<option value="all" selected>All</option>';
            foreach($CountryRegions as $Country) {
                $list .= '<option value="'.$Country['RegionId'].'">'.$Country['Region'].'</option>';
            }
            $list .= '</select>';
        $list .= '<input type="text" id="Region" name="Region" value="" style="display:none;"/>';
            //echo "<script>alert('$list');</script>";
            echo $list;
        } else {
        $Cities = new Brigade_Db_Table_Cities();
            $RegionCities = $Cities->getRegionCities($_REQUEST['location']);
            $list = '<select type="text" name="CityId" id="CityId" style="width:300px" onchange="$(\'#City\').val(this.options[this.selectedIndex].text);">';
            $list .= '<option value="all" selected>All</option>';
            foreach($RegionCities as $City) {
                $list .= '<option value="'.$City['CityId'].'">'.$City['City'].'</option>';
            }
            $list .= '</select>';
        $list .= '<input type="text" id="City" name="City" value="" style="display:none;"/>';
        echo $list;
        }
    }

    public function volunteersAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        ini_set("memory_limit", "512M");
        $parameters = $this->_getAllParams();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        if(isset($parameters['ProgramId'])) {
            $program = Program::get($parameters['ProgramId']);
            $organization  =  $program->organization;
            $_REQUEST['URLName'] = $program->urlName;
        } else {
            $organization  =  Organization::get($parameters['NetworkId']);
            $_REQUEST['URLName'] = $organization->urlName;
        }

        $org_volunteers = $Volunteers->getVolunteersByOrganization($organization->id, 'all', 'all', isset($program) ? $program->id : NULL, isset($_POST['GroupId']) && $_POST['GroupId'] != '' ? $_POST['GroupId'] : NULL, NULL, isset($_POST['search_text']) && trim($_POST['search_text']) != 'Search for Volunteer' && $_POST['search_text'] != '' ? $_POST['search_text'] : NULL, isset($parameters['ProjectId']) && $parameters['ProjectId'] != '' ? $parameters['ProjectId'] : NULL);
        $this->view->total_volunteers = count($org_volunteers);

        $_REQUEST['subpage'] = "volunteers";
        $paginator = Zend_Paginator::factory($org_volunteers);
        $items_per_page = isset($_POST['limit']) ? $_POST['limit'] : 10;
        $page = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage($items_per_page);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;
        $this->view->volunteer_class = $Volunteers;
        $this->view->volunteer_notes = new Brigade_Db_Table_VolunteerNotes();
        $this->view->project_donations = new Brigade_Db_Table_ProjectDonations();

        $this->getHeaderMedia($organization);

        //breadcrumb
        if(isset($program)) {
            $this->view->breadcrumb = $this->view->breadcrumbHelper($program, 'Volunteer List');
        } else {
            $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Volunteer List');
        }

        $this->view->organization = $organization;
        if(isset($program)) {
            $this->view->program = $program;
        }

        $this->renderPlaceHolders();
    }

    /**
     * @TODO: remove - change to get-groups return type json
     * @author: Matias Gonzalez
     */
    public function loadgroupsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Groups = new Brigade_Db_Table_Groups();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProgramId'])) {
            $groups = $Groups->listByProgram($parameters['ProgramId']);
            echo '<option value="">All Groups</option>';
            foreach($groups as $group) {
                echo '<option value="'.$group['GroupId'].'">'.stripslashes($group['GroupName']).'</option>';
            }
        }
    }

    /**
     * @TODO: remove - change to get-projects return type json
     * @author: Matias Gonzalez
     */
    public function loadprojectsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Groups = new Brigade_Db_Table_Groups();
        $parameters = $this->_getAllParams();
        if (isset($parameters['GroupId']) && !empty($parameters['GroupId'])) {
            $projects = $Groups->loadBrigades($parameters['GroupId'], 'all');
            if (isset($parameters['list'])) {
                $ctr = 1;
                foreach($projects as $project) {
                    $class = $ctr%2==1 ? "leftCol" : "rightCol";
                    echo '<li class="'.$class .'">';
                    echo '<input type="checkbox" name="ProjectId[]" value="'.$project['ProjectId'].'" style="width:auto; line-height:14px;" />&nbsp;';
                    echo strlen(stripslashes($project['Name'])) > 40 ? substr(stripslashes($project['Name']), 0, 37)."..." : stripslashes($project['Name']);
                    echo '</li>';
                    $ctr++;
                }
            } else {
                echo '<option value="">All</option>';
                foreach($projects as $project) {
                    echo '<option value="'.$project['ProjectId'].'">'.stripslashes($project['Name']).'</option>';
                }
            }
        } else if (isset($parameters['ProgramId']) && !empty($parameters['ProgramId'])) {
            $Programs = new Brigade_Db_Table_Programs();
            $projects = $Programs->loadBrigades($parameters['ProgramId']);
            $ctr = 1;
            foreach($projects as $project) {
                $class = $ctr%2==1 ? "leftCol" : "rightCol";
                echo '<li class="'.$class .'">';
                echo '<input type="checkbox" name="ProjectId[]" value="'.$project['ProjectId'].'" style="width:auto; line-height:14px;" />&nbsp;';
                echo strlen(stripslashes($project['Name'])) > 40 ? substr(stripslashes($project['Name']), 0, 37)."..." : stripslashes($project['Name']);
                echo '</li>';
                $ctr++;
            }
        }
    }

    public function affiliateAction() {
        $parameters = $this->_getAllParams();

        $organization = Organization::get($parameters['NetworkId']);

        if (isset($parameters['searchFilter']) &&
            trim($parameters['searchFilter']) != '') {
            $groups = Group::getListByOrganization(
                                    $organization->id,
                                    $parameters['searchFilter']);
        } else {
            $groups = $organization->groups;
        }

        //pagination
        $_REQUEST['URLName'] = $organization->urlName;
        $_REQUEST['subpage']   = 'affiliate';
        $this->view->paginator = Zend_Paginator::factory($groups);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);

        $this->view->organization = $organization;

        if (!isset($parameters['filter'])) {

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                $organization,
                'Select Affiliation'
            );

            $this->view->headTitle(stripslashes($organization->name).' | Select Affiliation');
            $this->getHeaderMedia($organization);

            //modules
            $this->renderPlaceHolders();
        } else {
            $this->view->filter = true;
            $this->render('tabscontent/affiliate');
            $this->_helper->layout()->disableLayout();
        }

    }

    public function participateAction() {
        $parameters = $this->_getAllParams();

        if(isset($parameters['GroupId'])) {
          $group = Group::get($parameters['GroupId']);
          $organization = $group->organization;

          if (isset($parameters['searchFilter']) &&
              trim($parameters['searchFilter']) != '') {
              $initiatives = Project::getListByGroup(
                                      $group,
                                      'upcoming',
                                      null,
                                      $parameters['searchFilter']);
          } else {
              $initiatives = $group->upcomingInitiatives;
          }

          //pagination
          $_REQUEST['URLName'] = $group->urlName;

          $this->view->group        = $group;

        } else {
          $organization = Organization::get($parameters['NetworkId']);

          if (isset($parameters['searchFilter']) &&
              trim($parameters['searchFilter']) != '') {
              $initiatives = Project::getListByOrganization(
                                      $organization,
                                      'upcoming',
                                      null,
                                      $parameters['searchFilter']);
          } else {
              $initiatives = $organization->upcomingInitiatives;
          }

          //pagination
          $_REQUEST['URLName'] = $organization->urlName;

        }

        $_REQUEST['subpage']   = 'participate';
        $this->view->paginator = Zend_Paginator::factory($initiatives);
        $page = $this->_getParam('page', 1);
        $this->view->paginator->setItemCountPerPage(10);
        $this->view->paginator->setCurrentPageNumber($page);

        $this->view->organization = $organization;

        if (!isset($parameters['filter'])) {

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                isset($group) ? $group : $organization,
                'Participate'
            );

            $this->view->headTitle(stripslashes(isset($group) ? $group->name : $organization->name).' | Participate');
            $this->getHeaderMedia($organization);

            //modules
            $this->renderPlaceHolders();
        } else {
            $this->view->filter = true;
            $this->render('tabscontent/participate');
            $this->_helper->layout()->disableLayout();
        }

    }

    public function fundraisersAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        ini_set("memory_limit","512M");
        $parameters = $this->_getAllParams();
        $Organizations = new Brigade_Db_Table_Organizations();
        $Programs = new Brigade_Db_Table_Programs();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $Groups = new Brigade_Db_Table_Groups();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        if (isset($parameters['NetworkId'])) {
            $organization  =  Organization::get($parameters['NetworkId']);
            $this->view->users_class = new Brigade_Db_Table_Users();
            $this->view->projects_class = new Brigade_Db_Table_Brigades();
            $this->view->project_donations = new Brigade_Db_Table_ProjectDonations();
            $this->view->volunteer_notes = new Brigade_Db_Table_VolunteerNotes();
            $this->view->volunteer_class = $Volunteers;
            $this->view->fundraisers = $Volunteers->getVolunteersByOrganization($organization->id, 'all', 'all', isset($parameters['ProgramId']) ? $parameters['ProgramId'] : NULL, isset($parameters['GroupId']) ? $parameters['GroupId'] : NULL, 1, isset($parameters['search_text']) && trim($parameters['search_text']) != 'Search for a person fundraising' ? $parameters['search_text'] : NULL, isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL);
            $paginator = Zend_Paginator::factory($this->view->fundraisers);
            $page = $this->_getParam('page', 1);
            $paginator->setItemCountPerPage(isset($_POST['limit']) ? $_POST['limit'] : 10);
            $paginator->setCurrentPageNumber($page);
            $this->view->paginator = $paginator;

            $this->getHeaderMedia($organization);

            if ($organization->hasPrograms) {
                $this->view->programs = $Programs->simpleListByNetwork($organization->id);
                if (isset($_REQUEST['ProgramId'])) {
                    $this->view->groups = $Groups->simpleListByProgram($_REQUEST['ProgramId']);
                }
            } else {
                $this->view->groups = $Groups->getNetworkGroups($organization->id, 0);
            }
            if (isset($_REQUEST['GroupId']) && !empty($_REQUEST['GroupId'])) {
                $this->view->activities = $Groups->loadBrigades($_REQUEST['GroupId'], 'all');
            }
            if ($organization->hasGroups != 0) {
                $this->view->activities = $Organizations->loadBrigades($organization->id);
            }

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Fundraiser List');

            $this->view->organization = $organization;
            $this->renderPlaceHolders();
        }
    }

    public function donorsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();

        if(!empty($parameters['ProgramId'])) {
            $program = Program::get($parameters['ProgramId']);
            $organization  =  $program->organization;
        } else {
            $organization  =  Organization::get($parameters['NetworkId']);
        }
        $_REQUEST['URLName'] = $organization->urlName;

        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->project_donations = $ProjectDonations;
        $this->view->users_class = new Brigade_Db_Table_Users();
        $this->view->projects_class = new Brigade_Db_Table_Brigades();
        $this->view->donation_notes = new Brigade_Db_Table_ProjectDonationNotes();

        $this->view->donors = $ProjectDonations->getSiteDonors($organization->id, 'nonprofit', isset($program) ? $program->id : NULL, isset($parameters['GroupId']) ? $parameters['GroupId'] : NULL, isset($parameters['search_text']) ? $parameters['search_text'] : NULL, isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL);

        $_REQUEST['subpage'] = 'donors';
        $paginator = Zend_Paginator::factory($this->view->donors);
        $page = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage(isset($parameters['limit']) ? $parameters['limit'] : 10);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;

        $this->getHeaderMedia($organization);

        //breadcrumb
        if(isset($program)) {
            $this->view->breadcrumb = $this->view->breadcrumbHelper($program, 'Donor History');
        } else {
            $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Donor History');
        }

        $this->view->organization  =  $organization;
        if(isset($program)) {
            $this->view->program   =  $program;
        }

        $this->renderPlaceHolders();
    }

    public function donordonationsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $Organizations = new Brigade_Db_Table_Organizations();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Users = new Brigade_Db_Table_Users;
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->users_class = new Brigade_Db_Table_Users();
        $this->view->projects_class = new Brigade_Db_Table_Brigades();
        $this->view->donation_notes = new Brigade_Db_Table_ProjectDonationNotes();

        $organization  =  Organization::get($parameters['NetworkId']);

        //breadcrumb
        $this->view->breadcrumb   = array();
        $this->view->breadcrumb[] = '<a href="/'.$organization->urlName.'">'.$organization->name.'</a>';
        if(isset($program)) {
            $this->view->breadcrumb[] = '<a href="/'.$program->urlName.'">'.$program->name.'</a>';
        }

        if(isset($parameters['SupporterEmail'])) {
            $this->view->list = "Donor";
            $this->view->param = "SupporterEmail=".$parameters['SupporterEmail'];
            $this->view->paginator = $ProjectDonations->getDonorDonations($parameters['SupporterEmail'], $organization->id, 'nonprofit');
            if (isset($parameters['List'])) {
                $this->view->Prev = $parameters['List'];
            }
            $this->view->breadcrumb[] = 'Donor History';

        } else if(isset($parameters['UserId'])) {
            $this->view->list = "Fundraiser";
            $this->view->param = "UserId=".$parameters['UserId'];
            $this->view->fundraiser = $Users->loadInfo($parameters['UserId']);
            $this->view->paginator = $ProjectDonations->getUserDonationsBySite($parameters['UserId'], $organization->id, 'nonprofit');
            $this->view->Prev = $parameters['List'];
            $this->view->breadcrumb[] = 'Fundraising History';

        }



        $this->view->organization = $organization;

        $this->getHeaderMedia($organization);
        $this->renderPlaceholders();
    }

    public function exportdonordonationsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Organizations = new Brigade_Db_Table_Organizations();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Users = new Brigade_Db_Table_Users();
        $Projects = new Brigade_Db_Table_Brigades();
        if (isset($parameters['NetworkId']) && (isset($parameters['SupporterEmail']) || isset($parameters['UserId']))) {
            $orgInfo = $Organizations->loadInfo($parameters['NetworkId'], false);
            if (!$orgInfo['hasDownloadedReports']) {
                $Organizations->editNetwork($parameters['NetworkId'], array('hasDownloadedReports' => 1));
            }
            $NetworkId = $parameters['NetworkId'];
            if (isset($parameters['SupporterEmail'])) {
                $filename = "Donor-Donations-Report.xls";
                $rows = $ProjectDonations->getDonorDonations($parameters['SupporterEmail'], $NetworkId, 'nonprofit', isset($_REQUEST['ProgramId']) ? $_REQUEST['ProgramId'] : "", isset($_REQUEST['GroupId']) ? $_REQUEST['GroupId'] : "", isset($_REQUEST['ProjectId']) ? $_REQUEST['ProjectId'] : "");
            } else if (isset($parameters['UserId'])) {
                $fundraiser = $Users->loadInfo($parameters['UserId']);
                $filename = "Donations on Behalf of ".stripslashes($fundraiser['FullName'])." Report.xls";
                $filename = str_replace(" ", "-", $filename);
                $rows = $ProjectDonations->getUserDonationsBySite($parameters['UserId'], $NetworkId, 'nonprofit', isset($_REQUEST['ProgramId']) ? $_REQUEST['ProgramId'] : "", isset($_REQUEST['GroupId']) ? $_REQUEST['GroupId'] : "", isset($_REQUEST['ProjectId']) ? $_REQUEST['ProjectId'] : "");
            } else {
                $orgInfo = $Organizations->loadInfo($NetworkId, false);
                $filename = stripslashes($orgInfo['NetworkName'])." Donor Donations Report.xls";
                $filename = str_replace(" ", "-", $filename);
                $rows = $ProjectDonations->getSiteDonorDonationsReport($NetworkId, 'nonprofit', isset($_REQUEST['ProjectId']) ? $_REQUEST['ProjectId'] : "", isset($_REQUEST['ProgramId']) ? $_REQUEST['ProgramId'] : "", isset($_REQUEST['GroupId']) ? $_REQUEST['GroupId'] : "");
            }
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$filename");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Transaction ID', 'Donor', 'Donor Email', 'Donation Amount', 'Donation Destination', 'Donation Date');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($rows as $row) {
                if(!empty($row['VolunteerId'])) {
                    $userInfo = $Users->loadInfo($row['VolunteerId']);
                    $Recipient = '"' . stripslashes($userInfo['FullName']) . '"' . "\t";
                } else {
                    $projInfo = $Projects->loadInfo1($row['ProjectId']);
                    $Recipient = '"' . stripslashes($projInfo['Name']) . '"' . "\t";
                }
                $line = '';
                foreach($row as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $row[$col] = "\t";
                    } else {
                        $row[$col] = str_replace('"', '""', $value);
                        $row[$col] = '"' . $value . '"' . "\t";
                        $row[$col] = stripslashes($row[$col]);
                    }
                }
                extract($row);
                $line = "$TransactionId$SupporterName$SupporterEmail$DonationAmount$Recipient$CreatedOn";
                $data .= trim($line)."\n";
            }
            $data = str_replace("\r","",$data);

            print "$headers\n$data";
        } else if (isset($parameters['NetworkId'])) {
            $orgInfo = $Organizations->loadInfo($parameters['NetworkId'], false);
            if (!$orgInfo['hasDownloadedReports']) {
                $Organizations->editNetwork($parameters['NetworkId'], array('hasDownloadedReports' => 1));
            }
            $rows = $ProjectDonations->getSiteDonorDonationsReport($parameters['NetworkId'], 'nonprofit', isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL, isset($_REQUEST['ProgramId']) ? $_REQUEST['ProgramId'] : "", isset($_REQUEST['GroupId']) ? $_REQUEST['GroupId'] : "");
            header("Content-Disposition: attachment; filename=Donor-Donations-Report.xls");
            header("Content-type: application/x-msdownload");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Donor Name', 'Donor Email', 'Projects Supported', 'Total Donated', 'Donor Notes');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($rows as $row) {
                $line = '';
                foreach($row as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $row[$col] = "\t";
                    } else {
                        $row[$col] = str_replace('"', '""', $value);
                        $row[$col] = '"' . $value . '"' . "\t";
                        $row[$col] = stripslashes($row[$col]);
                    }
                }
                $row['Notes'] = "\t";
                extract($row);
                $line = "$SupporterName$SupporterEmail$ProjectSupported$TotalDonation";
                $data .= trim($line)."\n";
            }
            $data = str_replace("\r","",$data);

            print "$headers\n$data";
        }
    }

    public function donationsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();

        if(isset($params['ProgramId'])  && !empty($params['ProgramId'])) {
            $program = Program::get($params['ProgramId']);
            $organization = $program->organization;
        } else {
            $organization = Organization::get($params['NetworkId']);
        }
        $_REQUEST['URLName'] = $organization->urlName;


        if(!empty($params['ProjectId'])) {
            $project = Project::get($params['ProjectId']);
            if(!empty($project->groupId)) {
                $group  =  $project->group;
            }
            if (!empty($project->organizationId)) {
                $this->view->organization = $project->organization;
            }
            if (empty($params['search_text']) && empty($params['FromDate']) &&
                empty($params['ToDate'])
            ) {
                $this->view->donations = $project->donations;
            } else {
                $this->view->donations = Donation::getListByProject(
                    $project,
                    (!empty($params['search_text'])) ? $params['search_text'] : false,
                    (!empty($params['FromDate'])) ? $params['FromDate'] : false,
                    (!empty($params['ToDate'])) ? $params['ToDate'] : false
                );
            }
        } else if (!empty($params['GroupId'])) {
            $group = Group::get($params['GroupId']);
            if (empty($params['search_text']) && empty($params['FromDate']) &&
                empty($params['ToDate'])
            ) {
                $this->view->donations = $group->donations;
            } else {
                $this->view->donations = Donation::getListByGroup(
                    $group,
                    (!empty($params['search_text'])) ? $params['search_text'] : false,
                    (!empty($params['FromDate'])) ? $params['FromDate'] : false,
                    (!empty($params['ToDate'])) ? $params['ToDate'] : false
                );
            }
        } else if (!empty($params['ProgramId'])) {
            if (empty($params['search_text']) && empty($params['FromDate']) &&
                empty($params['ToDate'])
            ) {
                $this->view->donations = $program->donations;
            } else {
                $this->view->donations = Donation::getListByProgram(
                    $program,
                    (!empty($params['search_text'])) ? $params['search_text'] : false,
                    (!empty($params['FromDate'])) ? $params['FromDate'] : false,
                    (!empty($params['ToDate'])) ? $params['ToDate'] : false
                );
            }
        } else {
            if (empty($params['search_text']) && empty($params['FromDate']) &&
                empty($params['ToDate'])
            ) {
                $this->view->donations = $organization->donations_month;
                $_REQUEST['FromDate']  = date('Y/m/01');
                $_REQUEST['ToDate']    = date('Y/m/31');
            } else {
                $this->view->donations = Donation::getListByOrganization(
                    $organization,
                    (!empty($params['search_text'])) ? $params['search_text'] : false,
                    (!empty($params['FromDate'])) ? $params['FromDate'] : false,
                    (!empty($params['ToDate'])) ? $params['ToDate'] : false
                );
            }
        }

        $showList = isset($params['show_list']) ? $params['show_list'] : 25;
        $_REQUEST['subpage'] = 'donations';
        if (is_null($this->view->donations)) {
            $this->view->donations = array();
        }
        $paginator = Zend_Paginator::factory($this->view->donations);
        $page = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage($showList);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;

        $this->getHeaderMedia($organization);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper(
            (isset($program)) ? $program : $organization,
            'Donation History'
        );

        $this->view->organization = $organization;
        if(isset($program)) {
            $this->view->program = $program;
        }

        $this->view->fundraisingProjects = Project::getListByOrganization(
            $organization,
            'all',
            null,
            false,
            true
        );
        $this->renderPlaceHolders();
    }

    public function addnoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $DonationNotes = new Brigade_Db_Table_ProjectDonationNotes();
        if ($_POST) {
            $DonationNotes->addDonationNote(array(
                'ProjectDonationId' => $_POST['ProjectDonationId'],
                'Notes' => $_POST['Notes'],
                'isPrivate' => isset($_POST['isPrivate']) ? 1 : 0
            ));
            echo "Note has been successfully added";
        }
    }

    public function editnoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $DonationNotes = new Brigade_Db_Table_ProjectDonationNotes();
        if ($_POST) {
            $DonationNotes->editDonationNote($_POST['DonationNoteId'], array(
                'Notes' => $_POST['Notes'],
                'isPrivate' => isset($_POST['isPrivate']) ? 1 : 0
            ));
            echo "Note has been successfully updated";
        }
    }

    public function deletenoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $DonationNotes = new Brigade_Db_Table_ProjectDonationNotes();
        if ($_POST) {
            $DonationNotes->deleteDonationNote($_POST['DonationNoteId']);
            echo "Note has been successfully deleted";
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
        $Mailer = new Mailer();
        $Organizations = new Brigade_Db_Table_Organizations();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Programs = new Brigade_Db_Table_Programs();
        $ContactInfo  = new Brigade_Db_Table_ContactInformation();
        if (isset($parameters['NetworkId'])) {
            $NetworkId = $parameters['NetworkId'];
            $this->view->network = $Organizations->loadInfo($NetworkId, false);
            if ($this->view->network['hasPrograms'] == 1) {
                $this->view->programs = $Programs->simpleListByNetwork($NetworkId);
            }
            $this->view->groups = $Groups->getNetworkGroups($parameters['NetworkId'], $this->view->network['hasPrograms']);
            $this->view->activities = $Organizations->loadProjects($NetworkId, 'all', false, NULL, 0);
            $this->view->campaigns = $Organizations->loadProjects($NetworkId, 'all', false, NULL, 1);
            $this->view->default_email = $ContactInfo->getContactInfo($NetworkId, 'Email');
            $this->view->emails = $GroupEmailAccounts->getGroupEmailAccounts($NetworkId);
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
            $NetworkId = $_POST['NetworkId'];
            $From = $_POST['From'];
            $orgInfo = $Organizations->loadInfo($NetworkId, false);
            $FromEmails = str_replace(" ", "", trim($_POST['FromEmails']));
            $FromEmails = explode(",", $FromEmails);
            foreach ($FromEmails as $email) {
                $verification_code = $GroupEmailAccounts->AddEmailAccount(array(
                    'GroupId' => $NetworkId,
                    'Email' => $email
                ));
                $Link = "$envSite.empowered.org/nonprofit/verify-email/$NetworkId/$verification_code";
                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_EMAIL_VERIFICATION,
                                   array($email, $orgInfo['NetworkName'], $Link, $From));
            }

            echo implode(", ", $FromEmails);
        } else if (isset($_POST['action']) && $_POST['action'] == "Send Email") {
            extract($_POST);
            $orgInfo = $Organizations->loadInfo($NetworkId, false);
            if ($sendTo == "All Donors") {
                $donors = $ProjectDonations->getSiteDonors($NetworkId, 'nonprofit');
                $email_lists = array();
                $ctr = 1;
                foreach ($donors as $donor) {
                    if ($ctr <= 100 && !empty($donor['SupporterEmail']) && count($donors) != $ctr) {
                        $email_lists[] = $donor['SupporterEmail'];
                    } else {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $orgInfo['NetworkName']));
                        $email_lists = array();
                        $ctr = 0;
                    }
                    $ctr++;
                }
            } else if ($sendTo == "Program") {
                foreach($programs as $program) {
                    $donors = $ProjectDonations->getSiteDonors($program, 'program');
                    $email_lists = array();
                    $ctr = 1;
                    foreach ($donors as $donor) {
                        if ($ctr <= 100 && !empty($program['SupporterEmail']) && count($donors) != $ctr) {
                            $email_lists[] = $donor['SupporterEmail'];
                        } else {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $orgInfo['NetworkName']));
                            $email_lists = array();
                            $ctr = 0;
                        }
                        $ctr++;
                    }
                }
            } else if ($sendTo == "Group") {
                foreach($groups as $group) {
                    $donors = $ProjectDonations->getSiteDonors($group, 'group');
                    $email_lists = array();
                    $ctr = 1;
                    foreach ($donors as $donor) {
                        if ($ctr <= 100 && !empty($donor['SupporterEmail']) && count($donors) != $ctr) {
                            $email_lists[] = $donor['SupporterEmail'];
                        } else {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $orgInfo['NetworkName']));
                            $email_lists = array();
                            $ctr = 0;
                        }
                        $ctr++;
                    }
                }
            } else if ($sendTo == "Activity") {
                foreach($activities as $activity) {
                    $donors = $ProjectDonations->getSiteDonors($activity, 'activity');
                    $email_lists = array();
                    $ctr = 1;
                    foreach ($donors as $donor) {
                        if ($ctr <= 100 && !empty($donor['SupporterEmail']) && count($donors) != $ctr) {
                            $email_lists[] = $donor['SupporterEmail'];
                        } else {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $orgInfo['NetworkName']));
                            $email_lists = array();
                            $ctr = 0;
                        }
                        $ctr++;
                    }
                }
            } else if ($sendTo == "Activity") {
                foreach($campaigns as $campaign) {
                    $donors = $ProjectDonations->getSiteDonors($campaign, 'activity');
                    $email_lists = array();
                    $ctr = 1;
                    foreach ($donors as $donor) {
                        if ($ctr <= 100 && !empty($donor['SupporterEmail']) && count($donors) != $ctr) {
                            $email_lists[] = $donor['SupporterEmail'];
                        } else {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $orgInfo['NetworkName']));
                            $email_lists = array();
                            $ctr = 0;
                        }
                        $ctr++;
                    }
                }
            } else if ($sendTo == "Donors") {
                $email_lists = array();
                $ctr = 1;
                foreach ($donors as $email) {
                    if ($ctr <= 100 && !empty($email) && count($donors) != $ctr) {
                        $email_lists[] = $email;
                    } else {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $orgInfo['NetworkName']));
                        $email_lists = array();
                        $ctr = 0;
                    }
                    $ctr++;
                }
            }
            $sentTo = array('All Donors' => 'all donors', 'Activity Donors' => 'selected volunteer activities donors', 'Donors' => 'selected donors');
            $this->view->sent = true;
            $this->view->message = "Your message has been successfully sent to ".$sentTo[$sendTo].".";
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
                $this->view->message = "Your email has been successfully verified, it is now added to ".$groupInfo['GroupName']." group email accounts.";
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

    public function sendemailAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $Mailer = new Mailer();
        $Organizations = new Brigade_Db_Table_Organizations();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Programs = new Brigade_Db_Table_Programs();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $Media = $this->view->sitemedia = new Brigade_Db_Table_Media();
        if (isset($parameters['NetworkId'])) {
            $NetworkId = $parameters['NetworkId'];

            // @TODO: REMOVE THIS OLD CODE - (USED IN OLD NONPROFIT HEADER)
            $this->view->network = $Organizations->loadInfo($NetworkId, false);


            $organization = Organization::get($NetworkId);
            $this->view->data = $this->view->organization = $organization;
            if ($organization->hasPrograms == 1) {
                $this->view->programs = $Programs->simpleListByNetwork($NetworkId);
            }
            if ($organization->hasGroups) {
                $this->view->groups = $Groups->getNetworkGroups($NetworkId, $organization->hasPrograms);
            }
            if (isset($parameters['Type']) && $parameters['Type'] == 'fundraisers') {
                $this->view->Type = 'fundraisers';
            } else if (isset($parameters['Type']) && $parameters['Type'] == 'volunteers') {
                $this->view->Type = 'volunteers';
            } else {
                $this->view->Type = 'members';
            }
            $this->view->activities = $Organizations->simpleProjectsList($NetworkId);
            $this->view->campaigns = $Organizations->simpleProjectsList($NetworkId, 1);
            $this->view->default_email = $ContactInfo->getContactInfo($NetworkId, 'Email');
            $this->view->emails = $GroupEmailAccounts->getGroupEmailAccounts($NetworkId);
            if ($organization->logoMediaId != '') {
                $this->view->image = $Media->getSiteMediaById($organization->logoMediaId);
            }

            if (isset($parameters['ProjectId'])) {
                $Brigades = new Brigade_Db_Table_Brigades();
                $this->view->projInfo = $Brigades->loadInfoBasic($parameters['ProjectId']);
            }
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
            $From    = $_POST['From'];
            $FromEmails = str_replace(" ", "", trim($_POST['FromEmails']));
            $FromEmails = explode(",", $FromEmails);
            foreach ($FromEmails as $email) {
                $verification_code = $GroupEmailAccounts->AddEmailAccount(array(
                            'GroupId' => $NetworkId,
                            'Email' => $email
                        ));
                $Link = "$envSite.empowered.org/nonprofit/verify-email/$NetworkId/$verification_code";
                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_EMAIL_VERIFICATION,
                                   array($email, $organization->name, $Link, $From));
            }
            echo implode(", ", $FromEmails);
        } else if (isset($_POST['action']) && $_POST['action'] == "Send Email") {
            extract($_POST);
            if (!$organization->hasSentEmails) {
                $organization->hasSentEmails = true;
                $organization->save();
            }
            if ($sendTo == "Organization") {
                if ($this->view->Type == 'members') {
                    $members = $GroupMembers->getOrganizationMembers($NetworkId, array(0, 1), 0, true, "");
                } else if ($this->view->Type == 'fundraisers') {
                    $members = $Volunteers->getVolunteersByOrganization($NetworkId, 'all', 'all', NULL, NULL, 1, NULL, NULL);
                } else if ($this->view->Type == 'volunteers') {
                    $members = $Volunteers->getVolunteersByOrganization($NetworkId, 'all', 'all', NULL, NULL, NULL, NULL, NULL);
                }
                $email_lists = array();
                $ctr = 1;
                foreach ($members as $member) {
                    if ($ctr <= 100 && !empty($member['Email']) && count($members) != $ctr) {
                        $email_lists[] = $member['Email'];
                    } else {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $organization->name));
                        $email_lists = array();
                        $ctr = 0;
                    }
                    $ctr++;
                }
            } else if ($sendTo == "Programs") {
                foreach ($programs as $program) {
                    if ($this->view->Type == 'members') {
                        $members = $GroupMembers->getProgramMembers($NetworkId, array(0, 1), 0, false);
                    } else if ($this->view->Type == 'fundraisers') {
                        $members = $Volunteers->getVolunteersByOrganization($NetworkId, 'all', 'all', $program, NULL, 1, NULL, NULL);
                    } else if ($this->view->Type == 'volunteers') {
                        $members = $Volunteers->getVolunteersByOrganization($NetworkId, 'all', 'all', $program, NULL, NULL, NULL, NULL);
                    }
                    $email_lists = array();
                    $ctr = 1;
                    foreach ($members as $member) {
                        if ($ctr <= 100 && !empty($member['Email']) && count($members) != $ctr) {
                            $email_lists[] = $member['Email'];
                        } else {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $organization->name));
                            $email_lists = array();
                            $ctr = 0;
                        }
                        $ctr++;
                    }
                }
            } else if ($sendTo == "Groups") {
                foreach ($groups as $group) {
                    if ($this->view->Type == 'members') {
                        $members = $GroupMembers->getGroupMembers($group, array(1));
                    } else if ($this->view->Type == 'fundraisers') {
                        $members = $Volunteers->getVolunteersByGroup($group, 'all', NULL, 1, NULL, NULL);
                    } else if ($this->view->Type == 'volunteers') {
                        $members = $Volunteers->getVolunteersByGroup($group, 'all', NULL, NULL, NULL, NULL);
                    }
                    $email_lists = array();
                    $ctr = 1;
                    foreach ($members as $member) {
                        if ($ctr <= 100 && !empty($member['Email']) && count($members) != $ctr) {
                            $email_lists[] = $member['Email'];
                        } else {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $organization->name));
                            $email_lists = array();
                            $ctr = 0;
                        }
                        $ctr++;
                    }
                }
            } else if ($sendTo == "Activity") {
                $Brigades = new Brigade_Db_Table_Brigades();
                foreach ($activities as $activity) {
                    if ($this->view->Type == 'members') {
                        ;
                    } else if ($this->view->Type == 'fundraisers') {
                        $members = $Volunteers->getVolunteersByOrganization($NetworkId, 'all', 'all', NULL, NULL, 1, NULL, $activity);
                    } else if ($this->view->Type == 'volunteers') {
                        $members = $Volunteers->getVolunteersByOrganization($NetworkId, 'all', 'all', NULL, NULL, NULL, NULL, $activity);
                    }
                    $email_lists = array();
                    $ctr = 1;
                    foreach ($members as $member) {
                        if ($ctr <= 100 && !empty($member['Email']) && count($members) != $ctr) {
                            $email_lists[] = $member['Email'];
                        } else {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $organization->name));
                            $email_lists = array();
                            $ctr = 0;
                        }
                        $ctr++;
                    }
                }
            } else if ($sendTo == "Campaign") {
                $Brigades = new Brigade_Db_Table_Brigades();
                foreach ($campaigns as $activity) {
                    if ($this->view->Type == 'members') {
                        ;
                    } else if ($this->view->Type == 'fundraisers') {
                        $members = $Volunteers->getVolunteersByOrganization($NetworkId, 'all', 'all', NULL, NULL, 1, NULL, $activity);
                    } else if ($this->view->Type == 'volunteers') {
                        ;
                    }
                    $email_lists = array();
                    $ctr = 1;
                    foreach ($members as $member) {
                        if ($ctr <= 100 && !empty($member['Email']) && count($members) != $ctr) {
                            $email_lists[] = $member['Email'];
                        } else {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $organization->name));
                            $email_lists = array();
                            $ctr = 0;
                        }
                        $ctr++;
                    }
                }
            } else if ($sendTo == "Members") {
                $email_lists = array();
                $ctr = 1;
                foreach ($members as $member) {
                    if ($ctr <= 100 && !empty($member['Email']) && count($members) != $ctr) {
                        $email_lists[] = $member['Email'];
                    } else {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array(implode(',', $email_lists), $subject, $message, $sentFrom, $organization->name));
                        $email_lists = array();
                        $ctr = 0;
                    }
                    $ctr++;
                }
            }
            $sentTo = array('Organization' => 'the entire organization', 'Programs' => 'the selected program(s)', 'Groups' => 'the selected group(s)', 'Activity' => 'the selected volunteer activities', 'Campaign' => 'the selected fundraising campaign(s)', 'Members' => 'the selected users',);
            $this->view->sent = true;
            $this->view->message = "Your message has been successfully sent to " . $this->view->Type . " of " . $sentTo[$sendTo] . ".";
        }
    }

    public function joinrequestAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();

        if (isset($_POST['NetworkId']) && isset($_POST['UserId'])) {
            $organization = Organization::get($_POST['NetworkId']);

            if(count($organization->groups)) {
              echo 'affiliate';
            } else {
              // double check if user is already a member of this organization
              if (!$GroupMembers->isMemberExists($organization->id, $_POST['UserId'], 'organization')) {
                  $GroupMembers->AddGroupMember(array(
                    'NetworkId' => $organization->id,
                    'UserId' => $_POST['UserId']
                  ));
                  // log the site activity
                  $SiteActivities = new Brigade_Db_Table_SiteActivities();
                  $SiteActivities->addSiteActivity(array(
                      'SiteId' => $organization->id,
                      'ActivityType' => 'Org Member Joined',
                      'CreatedBy' => $_SESSION['UserId'],
                      'ActivityDate' => date('Y-m-d H:i:s'),
                  ));
                  echo "Congratulations you have joined ".stripslashes($organization->name)." organization";
              }
            }

        }
    }

    public function editinfoAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        if (empty($params['NetworkId'])) {
            $this->_helper->redirector('error', '');
        }
        $org = Organization::get($params['NetworkId']);
        if (!$org) {
            $this->_helper->redirector('error', '');
        }
        if ($_POST) {
            $oldName                 = $org->name;
            $org->name               = $params['NetworkName'];
            $org->description        = $params['Description'];
            $org->contact->street    = $params['Street'];
            $org->contact->countryId = $params['Country'];
            $org->contact->stateId   = $params['Region'];
            $org->contact->cityId    = $params['City'];
            $org->contact->email     = $params['Email'];
            $org->contact->website   = $params['WebAddress'];
            $org->contact->phone     = $params['phoneNumber'];
            $org->hasGroups          = $params['isMultichaptered'];
            $org->hasPrograms        = $params['hasPrograms'];
            $org->hasActivities      = $params['hasActivities'];
            $org->hasCampaigns       = $params['hasCampaigns'];
            $org->hasMembership      = $params['hasMembership'];
            $org->hasEvents          = $params['hasEvents'];
            $org->isOpen             = $params['isOpen'];
            if ($org->hasGroups) {
                $org->groupNamingPlural   = $params['groupNamingPlural'];
                $org->groupNamingSingular = $params['groupNamingSingular'];
                if ($org->hasPrograms) {
                    $org->programNamingPlural   = $params['programNamingPlural'];
                    $org->programNamingSingular = $params['programNamingSingular'];
                }
            }
            $org->save();
            $org->contact->save();

            $this->_updateSalesForceOrgInfo($org, $oldName);

            // log the site activity
            $activity              = new Activity();
            $activity->siteId      = $params['NetworkId'];
            $activity->type        = 'Org Updated';
            $activity->createdById = $this->sessionUser->id;
            $activity->date        = date('Y-m-d H:i:s');
            $activity->save();

            $this->_helper->redirector->gotoUrl('/'.$org->urlName);
        }
        $Countries = new Brigade_Db_Table_Countries();
        $this->view->country_list = $Countries->getAllCountries();

        $this->view->organization = $org;
        $this->_helper->viewRenderer->setNeverController(true)
             ->setRender('getstarted/createorganization');
    }

    /**
     * Update program information under infusionsoft.
     */
    protected function _updateSalesForceOrgInfo($organization, $oldName = '') {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($organization->id, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Organization::Update');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($organization)) {
            $salesforce->updateAccountInfo($organization, $oldName);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$organization->id
            );
        }
    }

    public function editlogoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Media = new Brigade_Db_Table_Media();
        $Organizations = new Brigade_Db_Table_Organizations();
        if ($_POST) {
            // save organization media/image
            extract($_POST);
            $orgInfo = $Organizations->loadInfo($NetworkId);
            $this->view->image = $Media->getSiteMediaById($orgInfo['LogoMediaId']);
            $MediaSize = $_FILES['NetworkLogo']['size'];
            $tmpfile   = $_FILES['NetworkLogo']['tmp_name'];
            $filename  = $_FILES['NetworkLogo']['name'];
            $file_ext  = strtolower(substr($filename, strrpos($filename, '.') + 1));
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
                        'SystemMediaName' => $orgInfo['URLName']."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));
                } else {
                    // save media
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $orgInfo['URLName']."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $NetworkId
                    ));

                    $Organizations->editNetwork($NetworkId, array('LogoMediaId' => $MediaId));
                }

                //Get the file information
                $ImageCrop = new Brigade_Util_ImageCrop();
                $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$NetworkId.jpg";

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
                header("location: /nonprofit/cropimage/?NetworkId=$NetworkId");
            }
        }
    }

    /**
     * Delete account information under infusionsoft.
     */
    protected function salesForceIntegrationDeleteAccount($organization) {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($organization->id, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Program::Delete');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($organization)) {
            $salesforce->deleteAccount($organization);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$organization->id
            );
        }
    }



    /**
     * Delete org from database.
     * Only by glob-admin.
     */
    public function deleteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        if ($this->view->isGlobAdmin) {
            $org = Organization::get($params['OrganizationId']);
            $org->delete();

            $this->_helper->redirector->gotoUrl('/');
        }
    }

    public function loadhistoryAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $activities_participated = $Volunteers->getProjectsParticipatedByOrganization($parameters['NetworkId'], $parameters['UserId'], NULL, isset($parameters['ProgramId']) ? $parameters['ProgramId'] : '', isset($parameters['GroupId']) ? $parameters['GroupId'] : '', isset($parameters['ProjectId']) ? $parameters['ProjectId'] : '');
        foreach($activities_participated as $activity) {
            echo '<a href="/'.$activity['URLName'].'">'.stripslashes($activity['Name']).'</a> '.($activity['DateParticipated'] != '0000-00-00 00:00:00' ? "on ".date('F d, Y', strtotime($activity['DateParticipated'])) : '').'<br />';
        }
    }

    public function donationreceiptAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $Mailer = new Mailer();
        $ReceiptMessages = new Brigade_Db_Table_ReceiptMessages();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        $organization  =  Organization::get($parameters['NetworkId']);
        $this->view->message = $ReceiptMessages->getMessage($organization->id);

        $this->getHeaderMedia($organization);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Customize Receipts');

        $this->view->organization = $organization;

        $this->view->render('nonprofit/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');


        if ($_POST) {
            extract($_POST);

            if ($this->view->message == '') {
                $ReceiptMessages->addMessage($SiteId, $Message);
            } else {
                $ReceiptMessages->editMessage($SiteId, $Message);
            }

            header('location: /' . $organization->urlName . '/custom-receipt');
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
            $Organizations = new Brigade_Db_Table_Organizations();
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
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

                    // register the user as member of the group
                    $GroupMembers->AddGroupMember(array(
                        'NetworkId' => $_POST['NetworkId'],
                        'UserId' => $UserId
                    ));

                    // email a notification to the newly added user with the temp password attached
                } else {
                    $invalid++;
                }
            }
            echo '<script> alert("You have successfully uploaded the new members for this organization and have been registered to Empowered.org"); </script>';
            $orgInfo = $Organizations->loadInfo($_POST['NetworkId'], false);
            header("location: /".$orgInfo['URLName']);
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

    public function upgradeAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $Organizations = new Brigade_Db_Table_Organizations();
            $Organizations->editNetwork($_POST['NetworkId'], array('hasPrograms' => 1));
            // create session which we will serve as a reference that we're doing an upgrade of org
            $_SESSION['upgradeOrg'] = 1;
            $_SESSION['upgradeNetworkId'] = $_POST['NetworkId'];
        }
    }

    public function assigngroupsAction() {
        $Groups = new Brigade_Db_Table_Groups();
        $Programs = new Brigade_Db_Table_Programs();
        $Organizations = new Brigade_Db_Table_Organizations();
        $this->view->groups = $Groups->listOrgGroups($_SESSION['upgradeNetworkId']);
        $this->view->programs = $Programs->simpleListByNetwork($_SESSION['upgradeNetworkId']);
        if ($_POST) {
            foreach($_POST['GroupId'] as $index => $GroupId) {
                $Groups->editGroup($GroupId, array('ProgramId' => $_POST['ProgramId'][$index]));
            }
            $orgInfo = $Organizations->loadInfo($_SESSION['upgradeNetworkId'], false);
            if (isset($_SESSION['upgradeNetworkId'])) {
                unset($_SESSION['upgradeNetworkId']);
            }
            if (isset($_SESSION['upgradeOrg'])) {
                unset($_SESSION['upgradeOrg']);
            }
            if(isset($_SESSION['create_program_again'])) {
                unset($_SESSION['create_program_again']);
            }
            header("location: /".$orgInfo['URLName']);
        }
    }

    public function upgradeorganizationAction() {
        $parameters = $this->_getAllParams();
        $Media = new Brigade_Db_Table_Media();
        $SiteMedia = new Brigade_Db_Table_MediaSite();
        $UserRole = new Brigade_Db_Table_UserRoles();
        $Countries = new Brigade_Db_Table_Countries();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $Organizations = new Brigade_Db_Table_Organizations();
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $this->view->country_list = $Countries->getAllCountries();
        $this->view->data = $Organizations->loadInfo($parameters['NetworkId'], false);
        $this->view->contactInfo = $ContactInfo->getContactInfo($parameters['NetworkId']);
        if ($_POST) {
            extract($_POST);
            $bad_ext = 0;
            if(!empty($_FILES['NetworkLogo']['name'])) {
                $filename = $_FILES['NetworkLogo']['name'];
                $file_ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
                if($file_ext != 'jpg' && $file_ext != 'jpeg' && $file_ext != 'png' && $file_ext != 'gif') {
                    $bad_ext = 1;
                    $this->view->message = "Please upload a logo in jpeg, png and gif format only.";
                }
            }

            if (!$bad_ext) {
                // create the new organization
                $hasPrograms = isset($hasPrograms) ? $hasPrograms : 0;
                $Organizations->editNetwork($NetworkId, array(
                    'NetworkName' => $NetworkName,
                    'AboutUs' => $Description,
                    'hasPrograms' => $hasPrograms,
                    'hasGroups' => 1,
                ));

                if(!empty($WebAddress)) {
                    preg_match("/^https?:\/\/[_a-zA-Z0-9-]+\.[\._a-zA-Z0-9-]+$/i", $WebAddress, $website);
                    if(empty($website[0])) {
                        $WebAddress = 'http://'.$WebAddress;
                    }
                }

                // save network contact info
                $ContactId = $ContactInfo->editContactInfo($ContactId, array(
                    'Email' => $Email,
                    'WebAddress' => $WebAddress,
                    'Street' => $Street,
                    'CityId' => $CityId,
                    'City' => $City,
                    'RegionId' => $RegionId,
                    'Region' => $Region,
                    'CountryId' => $CountryId,
                    'Country' => $Country,
                    'SiteId' => $NetworkId
                ));

                // save network media/image
                $MediaSize = $_FILES['NetworkLogo']['size'];
                $tmpfile = $_FILES['NetworkLogo']['tmp_name'];
                if ($MediaSize > 0) {
                    //Get the file information
                    $ImageCrop = new Brigade_Util_ImageCrop();
                    $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$NetworkId.jpg";

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
                    if ($uploaded === false) {
                        $this->view->error = true;
                    } else {
                        $Media->editMedia($MediaId, array(
                            'MediaSize' => $MediaSize,
                            'SystemMediaName' => strtolower($NetworkId).".jpg",
                            'UploadedMediaName' => $filename,
                            'ModifiedBy' => $_SESSION['UserId'],
                        ));

                        // redirect to the page where users will crop the uploaded image
                        header("location: /nonprofit/cropimage/?NetworkId=$NetworkId&hasPrograms=$hasPrograms".(isset($isMultichaptered) ? "&isMultichaptered=$isMultichaptered" : '&isMultichaptered=1'));
                    }
                } else {
                    if ($hasPrograms) {
                        header("location: /".$this->view->data['URLName']."/create-program");
                    } else {
                        header("location: /".$this->view->data['URLName']."/create-group");
                    }
                    $_SESSION['upgradeOrg'] = 1;
                    $_SESSION['hasPrograms'] = $hasPrograms;
                    $_SESSION['upgradeNetworkId'] = $_POST['NetworkId'];
                }
            }
        }
    }

    public function addmembersAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $Organizations = new Brigade_Db_Table_Organizations();
        $Mailer = new Mailer();
        $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        $NetworkId = $parameters['NetworkId'];
        $organization  =  $this->view->organization  =  Organization::get($NetworkId);

        $this->getHeaderMedia($organization);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Add Members');

        $this->view->organization = $organization;

        $this->renderPlaceholders();

        if ($_POST) {
            $orgInfo = $Organizations->loadInfo($NetworkId, false);
            if (!empty($_POST['emails'])) {
                preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $_POST['emails'], $emails);
                $this->view->emails = $emails[0];
                foreach ($emails[0] as $email) {
                    $email = is_array($email) ? $email[0] : $email;
                    if ($unique_emailvalidator->isValid($email)) {
                        $name = explode("@", $email);
                        $URLName = $this->createURLName($name[0], "");
                        $Password = $this->generatePassword();
                        $UserId = $Users->addUser(array(
                            'FirstName' => $email,
                            'LastName' => "",
                            'Email' => $email,
                            'Password' => $Password,
                            'URLName' => $URLName,
                            'Active' => 0,
                            'FirstLogin' => 0
                        ), false);

                        $newUser = User::get($UserId);

                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$SEND_UPLOADED_MEMBER,
                                   array($newUser, $organization, $this->view->userNew , $_POST['message']));

                        $this->view->sent = true;
                    } else {
                        $userInfo = $Users->findBy($email);
                        $UserId = $userInfo['UserId'];
                        $newUser = User::get($UserId);

                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$SEND_UPLOADED_MEMBER,
                                   array($newUser, $organization, $this->view->userNew , $_POST['message']));

                        $this->view->sent = true;
                    }

                    // register the user as member of the organization
                    if(!empty($UserId) && !$GroupMembers->isMemberExists($_POST['NetworkId'], $UserId, 'organization')) {
                        $GroupMembers->AddGroupMember(array(
                            'NetworkId' => $_POST['NetworkId'],
                            'UserId' => $UserId
                        ));
                    }
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
                $invalid = 0;
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

                            $newUser = User::get($UserId);

                            // email a notification to the newly added user with the temp password attached
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$SEND_UPLOADED_MEMBER,
                                   array($newUser, $organization, $this->view->userNew , $_POST['message']));

                            $this->view->sent = true;
                        } else {
                            $userInfo = $Users->findBy($rows[$i][3]);
                            $UserId = $userInfo['UserId'];
                            $newUser = User::get($UserId);

                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$SEND_UPLOADED_MEMBER,
                                   array($newUser, $organization, $this->view->userNew , $_POST['message']));
                            $this->view->sent = true;
                        }

                        // register the user as member of the organization
                        if(!empty($UserId) && !$GroupMembers->isMemberExists($_POST['NetworkId'], $UserId, 'organization')) {
                            $GroupMembers->AddGroupMember(array(
                                'NetworkId' => $_POST['NetworkId'],
                                'UserId' => $UserId
                            ));
                        }
                    }
                }
                $this->view->emails = $email_list;
                $this->view->invalid_emails = $invalid_emails;
            }
            if (!$this->view->network['hasUploadedMembers']) {
                $Organizations->editNetwork($NetworkId, array('hasUploadedMembers' => 1));
            }
        }
    }

    public function addadminsAction() {
        ini_set("memory_limit","256M");

        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();

        $Users = new Brigade_Db_Table_Users();
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $Mailer = new Mailer();
        $unique_emailvalidator = new Brigade_Lib_Validate_DbUnique(new Brigade_Db_Table_Users(), 'email');
        $Media = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        $NetworkId     =  $parameters['NetworkId'];
        $organization  =  Organization::get($NetworkId);

        $this->getHeaderMedia($organization);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Add Administrators');

        $this->view->organization = $organization;

        $this->renderPlaceholders();

        if ($_POST) {

            if(isset($organization->createdBy)) {
                $creator = $organization->createdBy->fullName;
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
                            'FirstName' => $email,
                            'LastName' => "",
                            'Email' => $email,
                            'Password' => $Password,
                            'URLName' => $URLName,
                            'Active' => 0,
                            'FirstLogin' => 0
                        ), false);

                        Zend_Registry::get('eventDispatcher')->dispatchEvent(
                            EventDispatcher::$ADDED_NEW_ADMIN,
                            array($email, $email, $organization->name,
                                isset($creator) ? $creator : NULL, $Password,
                                $_POST['message'], $this->view->userNew, $organization->id
                            )
                        );

                        $this->view->sent = true;
                    } else {
                        $userInfo = $Users->findBy($email);
                        $UserId = $userInfo['UserId'];
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$ADDED_EXISTING_ADMIN,
                                        array($email, $email, $organization->name, isset($creator) ? $creator : NULL, $_POST['message'], $this->view->userNew));

                        $this->view->sent = true;
                    }

                    // register the user as member of the organization
                    if(!empty($UserId) && !$GroupMembers->isMemberExists($organization->id, $UserId, 'organization')) {
                        $GroupMembers->AddGroupMember(array(
                            'NetworkId' => $_POST['NetworkId'],
                            'UserId' => $UserId,
                            'isAdmin' => 1
                        ));
                    }

                    if (!$UserRoles->isUserRoleExists($_POST['NetworkId'], $UserId)) {
                        $UserRoles->addUserRole(array(
                            'SiteId' => $_POST['NetworkId'],
                            'UserId' => $UserId,
                            'RoleId' => 'ADMIN',
                            'Level' => 'Organization'
                        ));
                    }
                }
            } else if (!empty($_FILES['uploadExcel']) && $_FILES['uploadExcel']['size'] > 0 && empty($_POST['emails'])) {
                require_once 'Brigade/Util/ExcelReader.php';
                $tmpfile = $_FILES['uploadExcel']['tmp_name'];
                $filename = $_FILES['uploadExcel']['name'];
                $temp_file_location = realpath(dirname(__FILE__) . '/../../../') . "/public/tmp/$filename";
                move_uploaded_file($tmpfile, $temp_file_location);

                    try {
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

                                    // email a notification to the newly added user with the temp password attached
                                    Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                        EventDispatcher::$ADDED_NEW_ADMIN,
                                        array(
                                            $rows[$i][3], $rows[$i][1],
                                            $organization->name,
                                            isset($creator) ? $creator : NULL,
                                            $Password, $_POST['message'],
                                            $this->view->userNew,
                                            $organization->id
                                        )
                                    );

                                    $this->view->sent = true;
                                } else {
                                    $userInfo = $Users->findBy($rows[$i][3]);
                                    $UserId = $userInfo['UserId'];
                                    Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                        EventDispatcher::$ADDED_EXISTING_ADMIN,
                                        array(
                                            $rows[$i][3], $rows[$i][1],
                                            $organization->name,
                                            isset($creator) ? $creator : NULL,
                                            $_POST['message'],
                                            $this->view->userNew,
                                            $organization->id
                                        )
                                    );

                                    $this->view->sent = true;
                                }

                                // register the user as member of the organization
                                if(!empty($UserId) && !$GroupMembers->isMemberExists($_POST['NetworkId'], $UserId, 'organization')) {
                                    $GroupMembers->AddGroupMember(array(
                                        'NetworkId' => $_POST['NetworkId'],
                                        'UserId' => $UserId,
                                        'isAdmin' => 1
                                    ));
                                }

                                if (!$UserRoles->isUserRoleExists($_POST['NetworkId'], $UserId)) {
                                    $UserRoles->addUserRole(array(
                                        'SiteId' => $_POST['NetworkId'],
                                        'UserId' => $UserId,
                                        'RoleId' => 'ADMIN',
                                        'Level' => 'Organization'
                                    ));
                                }
                            }
                        }
                        $this->view->emails = $email_list;
                        $this->view->invalid_emails = $invalid_emails;

                    } catch (Exception $ex) {
                       $this->view->fileError="The file has a wrong format.";
                    }
            }
            if (!$this->view->network['hasAssignedAdmins']) {
                $Organizations = new Brigade_Db_Table_Organizations();
                $Organizations->editNetwork($NetworkId, array('hasAssignedAdmins' => 1));
            }
        }
    }

    public function activatefundraisingAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if(!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters              =  $this->_getAllParams();
        $PaypalAccounts          =  new Brigade_Db_Table_PaypalAccounts();
        $GoogleCheckoutAccounts  =  new Brigade_Db_Table_GoogleCheckoutAccounts();

        $organization = $this->view->organization = Organization::get($parameters['NetworkId']);

        if (($organization->googleId != 0 || $organization->paypalId != 0 ||
            $organization->bluePayId != 0) && $organization->allowPercentageFee != ''
        ) {
            $this->_helper->redirector->gotoUrl('/'.$organization->urlName.'/edit-fundraising');
        }

        $last_rec = $GoogleCheckoutAccounts->getMaxCheckoutId();
        $this->view->responsehandler = $last_rec['GoogleCheckoutAccountId'] + 1;

        //breadcrumb
        $this->view->breadcrumb = array(
            $organization->name,
            'Activate Fundraising'
        );

        $this->renderPlaceHolders();

        if ($_POST) {
            if ($_POST['activate_fundraising'] == 'Yes') {
                if($_POST['allowPercentageFee'] == 0) {
                    $_POST['feePercentage'] = 0;
                    $_POST['empoweredPercentage'] = 0;
                }
                if($_POST['payment_method'] == 'Paypal') {
                    $PaypalId = $PaypalAccounts->addPaypalAccount(array(
                        'email' => trim($_POST['paypalEmail']),
                        'currencyCode' => trim($_POST['paypalCurrency']),
                    ));

                    $ppCurrency = $_POST['paypalCurrency'] == 'USD' ? '$' : '&#163;';
                    $organization->paypalId                = $PaypalId;
                    $organization->googleCheckoutAccountId = 0;
                    $organization->currency                = $ppCurrency;
                    $organization->percentageFee           = !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0;
                    $organization->allowPercentageFee      = $_POST['allowPercentageFee'];
                    $organization->save();

                } else if ($_POST['payment_method'] == 'Google Checkout') {
                    $Organizations = new Brigade_Db_Table_Organizations();
                    $Network       = $Organizations->loadInfo($_POST['NetworkId']);

                    $Message = "Organization ".$Network['NetworkName']." has requested to use Google Checkout as their fundraising processor.<br />";
                    if($_SERVER['HTTP_HOST'] == 'www.empowered.org' ) {
                        mail('iamjackross@gmail.com', 'Google Checkout Request', $Message, "From: Empowered.org <admin@empowered.org>");
                    } else {
                        mail('empoweredqa@gmail.com', 'Chapter Automation', $Message, "From: Empowered.org <admin@empowered.org>");
                    }

                    //this is removed because organizations now require custom set up for Google Checkout.
                    //left commented because it will most likely be used again

                    /*$GoogleCheckoutAccountId = $GoogleCheckoutAccounts->addGoogleCheckoutAccount(array(
                        'GoogleCheckoutAccountName' => $this->view->data['NetworkName'],
                        'GoogleMerchantId' => trim($_POST['MerchantID']),
                        'GoogleMerchantKey' => trim($_POST['MerchantKey']),
                        'Currency' => ($_POST['Currency'] == 'USD' ? '$' : '&#163;'),
                        'CurrencyType' => $_POST['Currency'],
                    ));
                    $gcCurrency = $_POST['Currency'] == 'USD' ? '$' : '&#163;';
                    $Organizations->editNetwork($_POST['NetworkId'], array('GoogleCheckoutAccountId' => $GoogleCheckoutAccountId, 'Currency' => $gcCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                    // create the responsehandler file
                    $this->create_response_handler($this->view->responsehandler); */

                }

                if ($this->view->data['hasGroups'] == 1) {
                    $Groups = new Brigade_Db_Table_Groups();
                    $where = $Groups->getAdapter()->quoteInto("NetworkId = ?", $_POST['NetworkId']);
                    $Groups->update(array(
                        'GoogleCheckoutAccountId' => $organization->googleCheckoutAccountId,
                        'PaypalAccountId'         => $organization->paypalId,
                        'isNonProfit'             => $organization->googleCheckoutAccountId >= 1 && $organization->googleCheckoutAccountId <= 3 ? 1 : 0,
                        'Currency'                => $organization->currency,
                        'PercentageFee'           => $organization->percentageFee,
                        'allowPercentageFee'      => $organization->allowPercentageFee
                    ), $where);
                }

                $Brigades = new Brigade_Db_Table_Brigades();
                $where = $Brigades->getAdapter()->quoteInto("NetworkId = ?", $_POST['NetworkId']);
                $Brigades->update(array(
                    'GoogleCheckoutAccountId' => $organization->googleCheckoutAccountId,
                    'PaypalAccountId'         => $organization->paypalId,
                    'Currency'                => $organization->currency,
                    'PercentageFee'           => $organization->percentageFee,
                    'allowPercentageFee'      => $organization->allowPercentageFee
                ), $where);

                $Events = new Brigade_Db_Table_Events();
                $where = $Events->getAdapter()->quoteInto("SiteId = ?", $_POST['NetworkId']);
                $Events->update(array(
                    'GoogleCheckoutAccountId' => $organization->googleCheckoutAccountId,
                    'PaypalAccountId'         => $organization->paypalId,
                    'Currency'                => $organization->currency,
                ), $where);
            }

            header("location: /".$organization->urlName);
        }
    }

    public function editfundraisingAction() {
        if(!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $parameters             = $this->_getAllParams();
        $PaypalAccounts         = new Brigade_Db_Table_PaypalAccounts();
        $GoogleCheckoutAccounts = new Brigade_Db_Table_GoogleCheckoutAccounts();
        $Organizations          = new Brigade_Db_Table_Organizations();

        $organization  =  Organization::get($parameters['NetworkId']);

        if ($organization->bluePayId != 0) {
            $this->_helper->redirector('badaccess', 'error');
        }

        if ($organization->paypalId) {
            $this->view->payapalInfo = $PaypalAccounts->loadInfo($organization->paypalId);
        } else if ($organization->googleId) {
            $this->view->gcInfo = $GoogleCheckoutAccounts->loadInfo($organization->googleId);
        }

        $this->getHeaderMedia($organization);

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Edit Fundraising');

        $this->view->organization = $organization;

        $this->renderPlaceholders();

        if ($_POST) {
            if($_POST['payment_method'] == 'Paypal') {
                if($_POST['paypalCurrency'] == 'EUR') {
                    $ppCurrency = '&euro;';
                } else if($_POST['paypalCurrency'] == 'GBP') {
                    $ppCurrency = '&#163;';
                } else {
                    $ppCurrency = '$';
                }
                if (isset($_POST['PaypalAccountId'])) {
                    $PaypalAccounts->editPaypalAccount($organization->paypalId, array(
                        'email' => trim($_POST['paypalEmail']),
                        'currencyCode' => trim($_POST['paypalCurrency']),
                    ));
                    $Organizations->editNetwork($organization->id, array('Currency' => $ppCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                } else {
                    $PaypalAccountId = $PaypalAccounts->addPaypalAccount(array(
                        'email' => trim($_POST['paypalEmail']),
                        'currencyCode' => trim($_POST['paypalCurrency']),
                    ));
                    $Organizations->editNetwork($organization->id, array('PaypalAccountId' => $PaypalAccountId, 'GoogleCheckoutAccountId' => 0, 'Currency' => $ppCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                }
            } else if ($_POST['payment_method'] == 'Google Checkout') {
                if($organization->googleId == 0) {
                    $Message = "Organization {$organization->name} has requested to use Google Checkout as their fundraising processor.<br />";

                    if($_SERVER['HTTP_HOST'] == 'empowered.org' || $_SERVER['HTTP_HOST'] == 'www.empowered.org' ) {
                        mail('iamjackross@gmail.com', 'Google Checkout Request', $Message, "From: Empowered.org <admin@empowered.org>");
                    } else {
                        mail('empoweredqa@gmail.com', 'Chapter Automation', $Message, "From: Empowered.org <admin@empowered.org>");
                    }

                } else {
                    $gcCurrency = $_POST['Currency'] == 'USD' ? '$' : '&#163;';
                    if (isset($_POST['GoogleCheckoutAccountId'])) {
                        $GoogleCheckoutAccounts->editGoogleCheckoutAccount($organization->googleId, array(
                            'GoogleMerchantId' => trim($_POST['MerchantID']),
                            'GoogleMerchantKey' => trim($_POST['MerchantKey']),
                            'Currency' => ($_POST['Currency'] == 'USD' ? '$' : '&#163;'),
                            'CurrencyType' => $_POST['Currency'],
                            ));
                        $Organizations->editNetwork($organization->id, array('Currency' => $gcCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));

                    } else {
                        $GoogleCheckoutAccountId = $GoogleCheckoutAccounts->addGoogleCheckoutAccount(array(
                            'GoogleCheckoutAccountName' => $organization->name,
                            'GoogleMerchantId' => trim($_POST['MerchantID']),
                            'GoogleMerchantKey' => trim($_POST['MerchantKey']),
                            'Currency' => ($_POST['Currency'] == 'USD' ? '$' : '&#163;'),
                            'CurrencyType' => $_POST['Currency'],
                            ));
                        $Organizations->editNetwork($organization->id, array('GoogleCheckoutAccountId' => $GoogleCheckoutAccountId, 'PaypalAccountId' => 0, 'Currency' => $gcCurrency, 'PercentageFee' => !empty($_POST['PercentageFee']) ? $_POST['PercentageFee'] : 0, 'allowPercentageFee' => $_POST['allowPercentageFee']));
                        // create the responsehandler file
                        $this->create_response_handler($this->view->responsehandler);
                    }
                }
            }

            $updated_organization = Organization::get($parameters['NetworkId']);
            if ($organization->hasGroups) {
                $Groups = new Brigade_Db_Table_Groups();
                $where = $Groups->getAdapter()->quoteInto("NetworkId = ?", $organization->id);
                $Groups->update(array(
                    'GoogleCheckoutAccountId' => $updated_organization->googleId,
                    'PaypalAccountId' => $updated_organization->paypalId,
                    'isNonProfit' => !empty($updated_organization->nonProfitId) ? 1 : 0,
                    'Currency' => $updated_organization->currency,
                    'PercentageFee' => $updated_organization->percentageFee,
                    'allowPercentageFee' => $updated_organization->allowPercentageFee
                ), $where);
            }

            $Brigades = new Brigade_Db_Table_Brigades();
            $where = $Brigades->getAdapter()->quoteInto("NetworkId = ?", $_POST['NetworkId']);
            $Brigades->update(array(
                'GoogleCheckoutAccountId' => $updated_organization->googleId,
                'PaypalAccountId' => $updated_organization->paypalId,
                'Currency' => $updated_organization->currency,
                'PercentageFee' => $updated_organization->percentageFee,
                'allowPercentageFee' => $updated_organization->allowPercentageFee
            ), $where);

            $Events = new Brigade_Db_Table_Events();
            $where = $Events->getAdapter()->quoteInto("SiteId = ?", $_POST['NetworkId']);
            $Events->update(array(
                'GoogleCheckoutAccountId' => $updated_organization->googleId,
                'PaypalAccountId' => $updated_organization->paypalId,
                'Currency' => $updated_organization->currency,
            ), $where);

            header("location: /".$organization->urlName);
        }
    }

    public function shareAction() {
        $parameters = $this->_getAllParams();
        $this->view->organization = Organization::get($parameters['NetworkId']);
        if ($_POST && isset($_POST['NetworkId'])) {
            if (!$this->view->organization->hasSharedSocialNetworks) {
                $Organizations = new Brigade_Db_Table_Organizations();
                $Organizations->editNetwork($this->view->organization->id, array('hasSharedSocialNetworks' => 1));
            }
        }

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($this->view->organization, 'Spread the Word');

        $this->view->render('nonprofit/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');

    }


    public function addbannerAction() {
        $parameters = $this->_getAllParams();
        $Organizations = new Brigade_Db_Table_Organizations();
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        if ($_POST && isset($_POST['action']) && $_POST['action'] == 'upload' && isset($_FILES['NetworkBanner'])) {
            extract($_POST);
            $MediaSize = $_FILES['NetworkBanner']['size'];
            $tmpfile = $_FILES['NetworkBanner']['tmp_name'];
            $filename = $_FILES['NetworkBanner']['name'];
            $type = str_replace('image/', '', $_FILES['NetworkBanner']['type']);
            if ($MediaSize > 0) {
                // get group info
                $orgInfo = $Organizations->loadInfo($NetworkId, false);

                if (empty($orgInfo['BannerMediaId'])) {
                    // save media
                    $Media = new Brigade_Db_Table_Media();
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $orgInfo['URLName']."-banner.jpg",
                        'UploadedMediaName' => $filename,
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $NetworkId
                    ));

                    // update group BannerMediaId
                    $Organizations->editNetwork($NetworkId, array('BannerMediaId' => $MediaId));
                } else {
                    $MediaId = $orgInfo['BannerMediaId'];
                    $Media = new Brigade_Db_Table_Media();
                    $Media->editMedia($MediaId, array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $orgInfo['URLName']."-banner.jpg",
                        'UploadedMediaName' => $filename,
                    ));
                }

                //Get the file information
                $ImageCrop = new Brigade_Util_ImageCrop();
                $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$MediaId.jpg";

                // Check if file size does not exceed 2MB
                move_uploaded_file($tmpfile, $temp_image_location);
                $width = $ImageCrop->getWidth($temp_image_location);
                $height = $ImageCrop->getHeight($temp_image_location);

                //Scale the image if it is greater than the width set above
                $scale = 1;
                $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale,$type);
                if ($uploaded === false) {
                    $this->view->error = true;
                } else {
                    $this->view->action = 'crop';
                    $this->view->BannerMediaId = $MediaId;
                    $this->view->NetworkId = $NetworkId;
                    $this->view->width = $width;
                    $this->view->height = $height;
                }
            }
        } else if (isset($_POST['action']) && $_POST['action'] == 'crop') {
            extract($_POST);
            $orgInfo = $Organizations->loadInfo($NetworkId, false);
            $ImageCrop = new Brigade_Util_ImageCrop();
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_".$orgInfo['BannerMediaId'].".jpg";
            $image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/banner/".$orgInfo['URLName']."-banner.jpg";
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

            header("location: /".$orgInfo['URLName']);
        } else if (isset($parameters['NetworkId'])) {
            $this->view->NetworkId = $parameters['NetworkId'];
            if (isset($parameters['getstarted'])) {
                $this->view->action = 'crop';
                $this->view->BannerMediaId = $parameters['BannerMediaId'];
                $this->view->width = $parameters['width'];
                $this->view->height = $parameters['height'];
            } else {
                $this->view->action = 'upload';
            }
        }
    }

    public function removebannerAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $Media = new Brigade_Db_Table_Media();
        $SiteMedia = new Brigade_Db_Table_MediaSite();
        $Organizations = new Brigade_Db_Table_Organizations();
        if ($_POST) {
            $orgInfo = $Organizations->loadInfo($_POST['NetworkId'], false);
            // delete table from media tables
            $SiteMedia->deleteSiteMedia($orgInfo['BannerMediaId']);
            $Media->deleteMedia($orgInfo['BannerMediaId']);
            // set BannerMediaId to NULL in groups table
            $Organizations->editNetwork($_POST['NetworkId'], array('BannerMediaId' => ''));
            // display success message
            echo "Organization banner has been successfully removed.";
        }
    }

    /**
     * Membership reports. List of payments.
     */
    public function membershipreportAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        $org    = Organization::get($params['NetworkId']);

        $this->view->headTitle(stripslashes($org->name).' | Membership Report');

        //filter
        $perPage  = $this->_getParam('show_list', 50);
        $search   = $this->_getParam('searchFilter', false);
        $page     = $this->_getParam('page', 1);
        $fromDate = $this->_getParam('FromDate', false);
        $toDate   = $this->_getParam('ToDate', false);

        //breadcrumb
        $this->view->breadcrumb   = $this->view->breadcrumbHelper($org, 'Membership Report');
        $this->view->currentTab   = 'members';
        $this->view->organization = $org;
        $this->view->searchText   = $search;
        $this->view->showList     = $perPage;
        $this->view->fromDate     = $fromDate;
        $this->view->toDate       = $toDate;
        $this->view->totalDon     = Payment::getRaisedByOrganization($org);

        //payments
        if (!$search && !$fromDate && !$toDate) {
            $payments = $org->payments;
        } else {
            if ($fromDate) {
                $fromDate = date('Y-m-d', strtotime($fromDate));
            }
            if ($toDate) {
                $toDate = date('Y-m-d', strtotime($toDate));
            }
            $payments = Payment::getListByOrganization($org, $search, $fromDate, $toDate);
        }

        $paginator = Zend_Paginator::factory($payments);
        $paginator->setItemCountPerPage($perPage);
        $paginator->setCurrentPageNumber($page);
        $this->view->payments = $paginator;
        $_REQUEST['URLName']  = $org->urlName;
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
        $org    = Organization::get($params['NetworkId']);

        $this->view->headTitle(stripslashes($org->name).' | Membership Funds');

        //filter
        $perPage  = $this->_getParam('show_list', 50);
        $page     = $this->_getParam('page', 1);

        //breadcrumb
        $this->view->breadcrumb   = $this->view->breadcrumbHelper($org, 'Membership Funds');
        $this->view->currentTab   = 'members';
        $this->view->organization = $org;
        $this->view->showList     = $perPage;

        $this->view->membershipFunds = MembershipFund::getListByOrg($org);

        $paginator = Zend_Paginator::factory($this->view->membershipFunds);
        $paginator->setItemCountPerPage($perPage);
        $paginator->setCurrentPageNumber($page);

        $this->view->funds   = $paginator;
        $_REQUEST['URLName'] = $org->urlName;
        $_REQUEST['subpage'] = 'membership-funds';

        $this->renderPlaceHolders();
    }

    /**
     * Fly For good report
     */
    public function ffgreportAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        $org    = Organization::get($params['NetworkId']);

        $this->view->headTitle(stripslashes($org->name).' | Fly For Good Transactions');

        //filter
        $perPage = $this->_getParam('show_list', 50);
        $page    = $this->_getParam('page', 1);

        //breadcrumb
        $this->view->breadcrumb   = $this->view->breadcrumbHelper($org, 'Fly For Good Transactions');
        $this->view->currentTab   = '';
        $this->view->organization = $org;
        $this->view->showList     = $perPage;

        $this->view->ffgFunds = FlyForGood::getListByOrganization($org);

        $_REQUEST['pageUrl'] = $org->urlName;
        $_REQUEST['subPage'] = 'ffg-report';
        $paginator = Zend_Paginator::factory($this->view->ffgFunds);
        $paginator->setItemCountPerPage($perPage);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;

        $this->renderPlaceHolders();
    }


    /**
     * Members titles setup for chapters membership.
     */
    public function memberstitlesAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        $org    = Organization::get($params['NetworkId']);

        $this->view->headTitle(stripslashes($org->name).' | Members Titles Setup');

        //breadcrumb
        $this->view->breadcrumb   = $this->view->breadcrumbHelper($org, 'Members Titles Setup');
        $this->view->organization = $org;
        $page      = $this->_getParam('page', 1);
        $perPage   = $this->_getParam('show_list', 50);
        $paginator = Zend_Paginator::factory($org->memberTitles);
        $paginator->setItemCountPerPage($perPage);
        $paginator->setCurrentPageNumber($page);
        $this->view->titles  = $paginator;
        $_REQUEST['URLName'] = $org->urlName;
        $_REQUEST['subpage'] = 'memberstitle';

        $this->renderPlaceHolders();
    }

    /**
     * Add/edit member titles to use in chapters
     * Ajax
     */
    public function addnewmembertitleAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        if ($params['Edit']!= 'false') {
            $orgTitle               = MemberTitle::get($params['Edit']);
            $orgTitle->modifiedById = $this->sessionUser->id;
            $orgTitle->modifiedOn   = date('Y-m-d');
        } else {
            $orgTitle                 = new MemberTitle();
            $orgTitle->organizationId = $params['OrgId'];
            $orgTitle->createdById    = $this->sessionUser->id;
        }
        $orgTitle->title = $params['Title'];
        $orgTitle->save();
    }

    /**
     * delete member titles to use in chapters
     * Ajax
     */
    public function deletemembertitleAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        if (!empty($params['id'])) {
            $title               = MemberTitle::get($params['id']);
            $title->modifiedById = $this->sessionUser->id;
            $title->modifiedOn   = date('Y-m-d');
            $title->isDeleted    = true;
            $title->save();
        }
    }

    /**
     * Get the list of members by title
     * Ajax
     */
    public function listmemberstitleAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        $list   = array();
        if (!empty($params['TitleId'])) {
            $title   = MemberTitle::get($params['TitleId']);
            $members = Member::getByMemberTitle($title);
            foreach ($members as $member) {
                $list[] = array(
                    'id'          => $member->id,
                    'fullName'    => $member->user->fullName,
                    'chapterName' => $member->group->name
                );
            }
        }
        echo json_encode($list);
    }

    /**
     * Remove the title from a member
     * Ajax
     */
    public function removemembertitleAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        $member = Member::get($params['MemberId']);
        $member->memberTitleId = false;
        $member->save();

        $this->infusionSoftIntegration($member);
    }


    /**
     * Update member user to infusionsoft.
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
        Zend_Registry::get('logger')->info('InfusionSoft::Organization::MemberContact');
        $is = Infusionsoft::getInstance();
        if ($addMissingContact) {
            $is->addMemberContact($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Organization::Add/Update:'.$member->id);
        } else {
            $is->updateMemberContact($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Organization::Only Update:'.$member->id);
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
        $Organizations = new Brigade_Db_Table_Organizations();
        $Brigades = new Brigade_Db_Table_Brigades();
        $Programs = new Brigade_Db_Table_Programs();
        $Groups = new Brigade_Db_Table_Groups();
        $Users = new Brigade_Db_Table_Users();
        $activities = $SiteActivities->getRecentSiteActivity($parameters['NetworkId'], 'Organization', $_POST['limit'], $_POST['offset']);
        $data = $Organizations->loadInfo($parameters['NetworkId'], false);
        foreach ($activities as $activity) {
            $avatar = $comment_box = '';
            $comments_list = $ActivitiesComments->getSiteActivityComments($activity['SiteActivityId']);
            $comments = "<ul id='ul_" . $activity['SiteActivityId'] . "'" . (count($comments_list) > 0 ? "" : "style='display:none'") . ">";
            foreach ($comments_list as $comment) {
                $comments .= '<li><table><tr><td style="width:34px;"><img src="/profile/loadimage?UserId=' . $comment['UserId'] . '" /></td><td style="width:316px;"><span class="comment"><a href="/' . $comment['URLName'] . '">' . stripslashes($comment['FirstName']) . ' ' . stripslashes($comment['LastName']) . '</a>&nbsp;&nbsp;' . stripslashes($comment['Comment']) . '<br><span class="time">' . $this->class->getDateFormat($comment['CommentedOn']) . '</span></span></td></tr></table></li>';
            }
            $comments .= "</ul>";
            if ($_SESSION['UserId']) {
                $comment_link = "<a href='javascript:;' id='commentlink_" . $activity['SiteActivityId'] . "' style='float:right;'>Comment</a>";
                $avatar = "<img id='avatar_" . $activity['SiteActivityId'] . "' src='/profile/loadimage?UserId=" . $_SESSION['UserId'] . "' height='25px' width='25px' style='float:left; margin-right:3px; vertical-align:top; display:none;' />";
                $comment_box = '<div style="padding:3px; width:90%; margin:0 0 3px 34px; float:left;">' . $comment_link . $avatar . '<textarea id="comment_' . $activity['SiteActivityId'] . '" cols="50" rows="1" style="float:left; font-size:11px; height:20px; width:98%; display:none;">Write a comment...</textarea><input id="submit_' . $activity['SiteActivityId'] . '" class="btn btngreen" style="display:none; float:right;" type="submit" value="Comment"/></div>';
            }
            if ($activity['ActivityType'] == 'Uploads') {
                $display = "<p style='margin-bottom:-20px;min-height:20px;'><img src='" . $this->view->contentLocation . "public/images/ico/photo.gif' />&nbsp;&nbsp;" . $activity['TotalCount'] . ($activity['TotalCount'] > 1 ? " photos were " : " photo was ") . "added " . $this->class->getDateFormat($activity['ActivityDate']) . ".</p>";
            } else if ($activity['ActivityType'] == 'File Added') {
                $display = "<p style='margin-bottom:-20px;min-height:20px;'><img src='" . $this->view->contentLocation . "public/images/ico/photo.gif' />&nbsp;&nbsp;" . $activity['TotalCount'] . ($activity['TotalCount'] > 1 ? " files were " : " file was ") . "added " . $this->class->getDateFormat($activity['ActivityDate']) . ".</p>";
            } else if ($activity['ActivityType'] == 'User Donation') {
                $brigadeInfo = $Brigades->loadInfo1($activity['SiteId']);
                $display = "<p style='margin-bottom:-20px;min-height:20px;'><img src='" . $this->view->contentLocation . "public/images/ico/donation.gif'>&nbsp;&nbsp;" . stripslashes($activity['FirstName']) . " " . stripslashes($activity['LastName']) . " donated " . $this->data['Currency'] . number_format($activity['Details']) . "to the <a href='" . $activity['Link'] . "'>" . stripslashes($brigadeInfo['Name']) . "</a> brigade " . $this->class->getDateFormat($activity['ActivityDate']) . ".";
            } else if ($activity['ActivityType'] == 'Events') {
                //temp fix so that only one event is shown in the feed.
                $activity['TotalCount'] = 1;
                $display = "<p style='margin-bottom:-20px;min-height:20px;'><img src='" . $this->view->contentLocation . "public/images/ico/file.gif'>&nbsp;&nbsp;" . $activity['TotalCount'] . ($activity['TotalCount'] > 1 ? " events were " : " event was ") . "added " . $this->class->getDateFormat($activity['ActivityDate']) . ".";
            } else if ($activity['ActivityType'] == 'Campaign Added') {
                $campaignInfo = $Brigades->loadInfo1($activity['SiteId']);
                $display = "The <a href='/" . $campaignInfo['projectLink'] . "'>" . stripslashes($campaignInfo['Name']) . "</a> was created " . $this->class->getDateFormat($activity['ActivityDate']) . ".";
            } else if ($activity['ActivityType'] == 'Brigade Added') {
                $brigadeInfo = $Brigades->loadInfo1($activity['SiteId']);
                $display = "The <a href='/" . $brigadeInfo['projectLink'] . "'>" . stripslashes($brigadeInfo['Name']) . "</a> was created " . $this->class->getDateFormat($activity['ActivityDate']) . ".";
            } else if ($activity['ActivityType'] == 'Joined Brigade') {
                $brigadeInfo = $Brigades->loadInfo1($activity['SiteId']);
                if ($activity['TotalCount'] == 0) {
                    $userInfo = $Users->loadInfo($activity['Recipient']);
                    $display = "<a href='/".$userInfo['URLName']."'>".stripslashes($userInfo['FullName'])."</a> joined <a href='/".$brigadeInfo['projectLink']."'>".stripslashes($brigadeInfo['Name'])."</a> - ".$this->class->getDateFormat($activity['ActivityDate']);
                } else {
                    $display = $activity['TotalCount']." users joined <a href='/".$brigadeInfo['projectLink']."'>".stripslashes($brigadeInfo['Name'])."</a> - ".$this->class->getDateFormat($activity['ActivityDate']);
                }
            } else if ($activity['ActivityType'] == 'Org Updated') {
                $display = "The organization details were changed " . $this->getDateFormat($activity['ActivityDate']) . ".";
            } else if ($activity['ActivityType'] == 'Group Member Joined') {
                $userInfo = $Users->loadInfo($activity['CreatedBy']);
                $display = "<a href='" . $userInfo['URLName'] . "'>" . stripslashes($userInfo['FullName']) . "</a> joined this chapter " . $this->class->getDateFormat($activity['ActivityDate']) . ".";
            } else if ($activity['ActivityType'] == 'Program Added') {
                if ($activity['TotalCount'] == 0) {
                    $progInfo = $Programs->loadInfo1($activity['Details'], false);
                    $display = "<a href='/".$data['URLName']."/".$progInfo['URLName']."'>".stripslashes($progInfo['ProgramName'])."</a> was created - ".$this->class->getDateFormat($activity['ActivityDate']);
                } else {
                    $display = $activity['TotalCount'].($activity['TotalCount'] > 1 ? " programs were " : " program was ")." created - ".$this->class->getDateFormat($activity['ActivityDate']);
                }
            } else if ($activity['ActivityType'] == 'Group Added') {
                if ($activity['TotalCount'] == 0) {
                    $groupInfo = $Groups->loadInfo1($activity['Details'], false);
                    $display = "<a href='/".$progInfo['URLName']."'>".stripslashes($groupInfo['GroupName'])."</a> was created - ".$this->class->getDateFormat($activity['ActivityDate']);
                } else {
                    $display = $activity['TotalCount'].($activity['TotalCount'] > 1 ? " chapters were " : " chapter was ")." created - ".$this->class->getDateFormat($activity['ActivityDate']);
                }
            } else if ($activity['ActivityType'] == 'Wall Post') {
                $display = "<p style='margin-bottom:-20px;min-height:20px;'><table style='margin-bottom:-20px;'><tr><td width=34><img src='/profile/loadimage?UserId=" . $activity['CreatedBy'] . "' width='30' height='30'></td><td><a href='/" . $activity['URLName'] . "'>" . stripslashes($activity['FirstName']) . " " . stripslashes($activity['LastName']) . "</a>&nbsp;&nbsp;" . stripslashes($activity['Details']) . "<br>" . $this->class->getDateFormat($activity['ActivityDate']) . ".</td></tr></table>";
            }
            if (!empty($display)) {
                echo "$display<br><br>$comments$comment_box</p><div class='clear1'></div>";
            }
        }
        if (count($activities) < 5) {
            echo '<script> $("#see-more").hide(); </script>';
        }
    }

/*  Actions for Searching an Organization */

    public function searchAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout->setLayout('newlayout');

        $Organizations = new Brigade_Db_Table_Organizations();
        $this->view->network = $Organizations->loadInfo($parameters['NetworkId']);

        //breadcrumb
        $this->view->breadcrumb = array(
            '<a href="/'.$this->view->network['URLName'].'">'.$this->view->network['NetworkName'].'</a>',
            'Search'
        );
        $this->view->organization  =  Organization::get($parameters['NetworkId']);
        $this->view->render('nonprofit/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->view->categories = array('all' => 'All Results', 'people' => 'People', 'group' => 'Chapters', 'activity' => 'Volunteer Activities', 'campaign' => 'Fundraising Campaigns', 'event' => 'Events', 'nonprofit' => 'Organizations', 'program' => 'Programs');
        $this->view->search_icons = array('all' => 'search.png', 'people' => 'people.jpg', 'group' => 'groups.jpg', 'activity' => 'activities.jpg', 'campaign' => 'campaigns.png', 'event' => 'events.png', 'nonprofit' => 'nonprofits.png', 'program' => 'programs.png');

        if (isset($parameters['search_text']) && $parameters['search_text'] != '') {
            $search_results = '';
            $parameters['search_text'] = str_replace("'", "", $parameters['search_text']);
            $parameters['search_text'] = str_replace('"', '', $parameters['search_text']);
            $parameters['search_text'] = preg_replace('/\s\s+/', ' ', $parameters['search_text']);

            if (!isset($parameters['category']) || $parameters['category'] == 'all') {
                $search_results = $this->searchAll($this->view->network['NetworkId'], $parameters['search_text'], 5);
                } else if (isset($parameters['category'])) {
                    $method = 'search'.ucfirst($parameters['category']);
                    $search_results = array();
                    $results = $this->$method($this->view->network['NetworkId'], $parameters['search_text'], true, 10);
                    foreach($results as $row) {
                        $search_results[] = $row;
                    }
                    $other_results = $this->$method($this->view->network['NetworkId'], $parameters['search_text'], false, 10);
                    foreach($other_results as $row) {
                        $search_results[] = $row;
                    }
                    if (!empty($search_results) && count($search_results) >= 10) {
                        $this->view->total_results = count($this->$method($this->view->network['NetworkId'], $parameters['search_text'], false));
                    }
                    if (empty($search_results)) {
                        if (strpos(strtolower($parameters['search_text']), "santa cruz") !== false) {
                            $search_results = $this->$method($this->view->network['NetworkId'], "santa cruz", false, 10);
                        }
                        if (empty($search_results) && strpos(strtolower($parameters['search_text']), "global") !== false) {
                            $search_results = $this->$method($this->view->network['NetworkId'], "global", false, 10);
                            if (!empty($search_results) && count($search_results) >= 10) {
                                $this->view->total_results = count($this->$method($this->view->network['NetworkId'], "global", false));
                            }
                        }
                        if (empty($search_results) && strpos(strtolower($parameters['search_text']), "brigades") !== false) {
                            $search_results = $this->$method($this->view->network['NetworkId'], "brigades", false, 10);
                            if (!empty($search_results) && count($search_results) >= 10) {
                                $this->view->total_results = count($this->$method($this->view->network['NetworkId'], "brigades", false));
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

    private function hasResults($NetworkId, $search_text) {
        if (count($this->searchGroup($NetworkId, $search_text, false, 1))) {
            return true;
        } else if (count($this->searchActivity($NetworkId, $search_text, false, 1))) {
            return true;
        } else if (count($this->searchCampaign($NetworkId, $search_text, false, 1))) {
            return true;
        } else if (count($this->searchEvent($NetworkId, $search_text, false, 1))) {
            return true;
        } else if (count($this->searchProgram($NetworkId, $search_text, false, 1))) {
            return true;
        } else if (count($this->searchPeople($NetworkId, $search_text, false, 1))) {
            return true;
        } else {
            return false;
        }

    }

    private function searchAll($NetworkId, $search_text, $limit = NULL) {
        $search_results = array();
        // load perfect match first
        $activities = $this->searchActivity($NetworkId, $search_text, true, $limit);
        foreach ($activities as $row) {
            $search_results['activity'][] = $row;
        }
        $campaigns = $this->searchCampaign($NetworkId, $search_text, true, $limit);
        foreach ($campaigns as $row) {
            $search_results['campaign'][] = $row;
        }
        $events = $this->searchEvent($NetworkId, $search_text, true, $limit);
        foreach ($events as $row) {
            $search_results['event'][] = $row;
        }
        $groups = $this->searchGroup($NetworkId, $search_text, true, $limit);
        foreach ($groups as $row) {
            $search_results['group'][] = $row;
        }
        $programs = $this->searchProgram($NetworkId, $search_text, true, $limit);
        foreach ($programs as $row) {
            $search_results['program'][] = $row;
        }
        $members = $this->searchPeople($NetworkId, $search_text, true, $limit);
        foreach ($members as $row) {
            $search_results['people'][] = $row;
        }

        // load other matches
        if (!isset($search_results['activity'])) {
            $activities = $this->searchActivity($NetworkId, $search_text, false, $limit);
            foreach ($activities as $row) {
                $search_results['activity'][] = $row;
            }
        }
        if (!isset($search_results['campaign'])) {
            $campaigns = $this->searchCampaign($NetworkId, $search_text, false, $limit);
            foreach ($campaigns as $row) {
                $search_results['campaign'][] = $row;
            }
        }
        if (!isset($search_results['event'])) {
            $events = $this->searchEvent($NetworkId, $search_text, false, $limit);
            foreach ($events as $row) {
                $search_results['event'][] = $row;
            }
        }
        if (!isset($search_results['group'])) {
            $groups = $this->searchGroup($NetworkId, $search_text, false, $limit);
            foreach ($groups as $row) {
                $search_results['group'][] = $row;
            }
        }
        if (!isset($search_results['program'])) {
            $programs = $this->searchProgram($NetworkId, $search_text, false, $limit);
            foreach ($programs as $row) {
                $search_results['program'][] = $row;
            }
        }
        if (!isset($search_results['people'])) {
            $members = $this->searchPeople($NetworkId, $search_text, false, $limit);
            foreach ($members as $row) {
                $search_results['people'][] = $row;
            }
        }

        return $search_results;
    }

    private function searchProgram($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Programs = new Brigade_Db_Table_Programs();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $donations = new Brigade_Db_Table_ProjectDonations();
        $sitemedia = new Brigade_Db_Table_Media();
        $list = $Programs->searchOrganizationProgram($NetworkId, $search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $sitemedia->getSiteMediaById($item['LogoMediaId']);
                $media_image = $media['SystemMediaName'];
                $media_caption = $media['Caption'];
                $image_exists = file_exists("/public/Media/$media_image");
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/Media/'.$media_image.'" alt="'.$media_caption.'" /></a></center>';
                $search_result[] = '
                <div class="program-row item">
                    <div class="logo">
                        '.($image_exists && trim($media_image) != '' ? $logo : '&nbsp;').'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['nonprofitLink'].'/'.$item['programLink'].'">'.stripslashes($item['ProgramName']).'</a></h4>
                        <a href="/'.$item['nonprofitLink'].'">'.$item['NetworkName'].'</a>
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchGroup($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Groups = new Brigade_Db_Table_Groups();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $sitemedia = new Brigade_Db_Table_Media();
        $list = $Groups->searchOrganizationGroup($NetworkId, $search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $sitemedia->getSiteMediaById($item['LogoMediaId']);
                $media_image = $media['SystemMediaName'];
                $media_caption = $media['Caption'];
                $image_exists = file_exists("/public/Media/$media_image");
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/Media/'.$media_image.'" alt="'.$media_caption.'" /></a></center>';
                $location = ((!empty($item['City']) ? $item['City'].", " : '').(!empty($item['State']) ? $item['State'].", " : '').(!empty($item['Country']) ? $item['Country'] : ''));
                $members = count($GroupMembers->getGroupMembers($item['GroupId']));
                $search_result[] = '
                <div class="group-row item">
                    <div class="logo">
                        '.($image_exists && trim($media_image) != '' ? $logo : '&nbsp;').'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['URLName'].'">'.stripslashes($item['GroupName']).'</a></h4>
                        <div class="site-desc">
                            '.(($location != ', , ' && trim($location) != '') ? $location.'<br/>' : "").'
                            '.($members > 0 ? '<a href="/'.$item['URLName'].'/members">'.$members.' Members</a><br/>' : '').'
                            <div id="divLessContent'.$item['GroupId'].'">
                                <span>'.(strlen($item['Description']) > 100 ? stripslashes(substr($item['Description'], 0, 100))."..." : stripslashes($item['Description'])).'</span>
                                '.(strlen($item['Description']) > 100 ? '<a name="divMoreContent'.$item['GroupId'].'" class="read-more-or-less" id="ReadMore" href="javascript:;">Read More</a>' : "").'
                            </div>
                            '.(strlen($item['Description']) > 100 ? '
                            <div id="divMoreContent'.$item['GroupId'].'" style="display:none;">
                                <span>'.stripslashes($item['Description']).'</span>
                                <a name="divLessContent'.$item['GroupId'].'" class="read-more-or-less" id="ReadFewer" href="javascript:;">Read Less</a>
                            </div>
                            ' : "").'
                        </div>
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchActivity($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Brigades = new Brigade_Db_Table_Brigades();
        $sitemedia = new Brigade_Db_Table_Media();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $list = $Brigades->searchOrganizationActivity($NetworkId, $search_text, $perfect_match, $limit, $offset);
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

    private function searchPeople($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $list = $GroupMembers->searchOrganizationMembers($NetworkId, $search_text, $perfect_match, $limit, $offset);
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
                        <h4><a href="/'.stripslashes($item['URLName']).'">'.stripslashes($item['FirstName']).' '.stripslashes($item['LastName']).'</a></h4>
                        '.$item['Location'].'<br/>'.'
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchCampaign($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $sitemedia = new Brigade_Db_Table_Media();
        $Projects = new Brigade_Db_Table_Brigades();
        $list = $Projects->searchOrganizationCampaign($NetworkId, $search_text, $perfect_match, $limit, $offset);
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

    private function searchEvent($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $sitemedia = new Brigade_Db_Table_Media();
        $Events = new Brigade_Db_Table_Events();
        $list = $Events->searchOrganizationEvent($NetworkId, $search_text, $perfect_match, $limit, $offset);
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

    public function getHeaderMedia(Organization $organization) {
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
     * Prepare all plceholders for the new design.
     *
     */
    public function renderPlaceHolders() {
        $this->view->render('nonprofit/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');
    }
}
