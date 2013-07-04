<?php

/**
 * IndexController - The default controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Blogs.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Photo.php';
require_once 'Brigade/Db/Table/Events.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Zend/Paginator.php';
require_once 'Zend/View/Helper/PaginationControl.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/GroupEmailAccounts.php';
require_once 'Brigade/Db/Table/Fundraisers.php';
require_once 'Brigade/Db/Table/Blog/Posts.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/EventTicketHolders.php';
require_once 'Brigade/Db/Table/GroupSurveys.php';
require_once 'Mailer.php';
require_once 'Facebook/facebook.php';
require_once 'BaseController.php';
require_once 'Organization.php';

class IndexController extends BaseController {

    public function init() {
        parent::init();
        $this->view->controller = 'index';
    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {
        $parameters = $this->_getAllParams();
        $Brigades = new Brigade_Db_Table_Brigades();
                
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->brigades = $Brigades;
        $this->view->groups = new Brigade_Db_Table_Groups();
        $this->view->donationManage = new Brigade_Db_Table_ProjectDonations();
        $this->view->volunteers = new Brigade_Db_Table_Volunteers();
        $this->view->DateFormat = $this;
        $this->view->isHomePage = 1;
        //$this->view->donations_feed = $this->view->donationManage->getDonationLivefeed();
    }

    /**
     * Do WE NEED THIS?
    public function homeAction() {
        $parameters = $this->_getAllParams();
        $Brigades = new Brigade_Db_Table_Brigades();
                
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->brigades = $Brigades;
        $this->view->groups = new Brigade_Db_Table_Groups();
        $this->view->donationManage = new Brigade_Db_Table_ProjectDonations();
        $this->view->volunteers = new Brigade_Db_Table_Volunteers();
        $this->view->DateFormat = $this;
        $this->view->isHomePage = 1;
        $this->view->donations_feed = $this->view->donationManage->getDonationLivefeed();
    }
	*/

    public function searchAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $text_search = trim($_POST['text_search']);
        $Brigades = new Brigade_Db_Table_Brigades();
        $Groups = new Brigade_Db_Table_Groups();
        $list = $Brigades->search($text_search);
        $contactinfo = new Brigade_Db_Table_ContactInformation();
        $sitemedia = new Brigade_Db_Table_Media();
        if (trim($text_search) != "") {
            echo '<h2>Search results for "'.$text_search.'"</h2><div class="clear"></div>';
        } else {
            echo '<h2 class="brigades">Brigades</h2><div class="clear"></div>';
        }
        if (count($list) > 0) {
            $ctr = 1;
            foreach ($list as $item) {
                $media_src = '';
                $media = $sitemedia->getSiteMediaGallery($item['ProjectId'], "");
                if (count($media) > 0) {
                    $media_src = '/public/Media/'.$media[0]['SystemMediaName'];
                } else {
                    // get the group image by group's LogoMediaId
                    $groupInfo = $Groups->loadInfo($item['GroupId']);
                    $media = $sitemedia->getSiteMediaById($groupInfo['LogoMediaId']);
                    // echo '$this->sitemedia->getSiteMediaById('.$groupInfo['LogoMediaId'].')';
                    $media_src = '/public/Media/'.$media['SystemMediaName'];
                }
                echo '
                    <div class="box06" style="width:510px; float:left; '.($ctr%2==1 ? "margin-right:30px;" : "margin-right:10px;").'">
                        <div class="bst01">
                            <img src="'.$media_src.'" alt="" width="74" height="50"/>
                            <div class="bst03">
                                <div class="bst04">
                                    <div class="bst05"><span><span><span id="ctl00_ContentPHMain_ctrlGroupBrigDtls_rptGroupBrigDtls_ctl00_lblVoluntSpaceEmpty">'.($item['total_volunteers'] > $item['VolunteerGoal'] ? 0 : $item['VolunteerGoal'] - $item['total_volunteers']).'</span></span> / </span>'.$item['VolunteerGoal'].'</div>
                                    Spaces
                                    <br />
                                    Available
                                </div>
                            </div>
                        </div>
                        <div class="bst02" style="width:400px;">
                            <div class="bst06">
                                <div class="bst07">Group: </div>
                                <a href="/group/?GroupId='.$item['GroupId'].'" >'.$item['GroupName'].'</a>
                            </div>
                            <div class="bst06">
                                <div class="bst07">Activity: </div>
                                '.$item['Name'].'
                            </div>
                            <div class="bst08">
                                <div class="bst07">Where: </div>
                                '.$contactinfo->getContactInfo($item['ProjectId'], 'Location').'
                            </div>
                            <div class="bst09">
                                <div class="bst07">When: </div>
                                '.date('M d, Y', strtotime($item['StartDate'])).' - '.date('M d, Y', strtotime($item['EndDate'])).'
                            </div>
                            <div class="bst10">
                                <div class="but006"><a href="/profile/login">Volunteer</a></div>
                                <div class="but006"><a href="/donation">Donate</a></div>
                            </div>
                        </div>
                        <div class="clear"></div>
                    </div>
                ';
                $ctr++;
            }
        } else {
            echo '<div class="box06"><h4>No record(s) found. Check your spelling or try another term.</h4></div><div class="clear"></div>';
        }
    }

    public function loadimageAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Brigades = new Brigade_Db_Table_Brigades();
        $row = $Brigades->loadInfo($_REQUEST['ProjectId']);
        header("Content-type: image/jpeg");
        echo $row['image'];
    }

    public function preDispatch() {
        parent::preDispatch();
        $this->view->media_path = "/public/Media/";
    }

    /*
     * Ajax search / autocomplete search
     */
    public function searchprojectAction(){
        try{
            if (!empty($_GET['msinp01'])) {
                $searchstr = $_GET['msinp01'];
                $projectManage = new Brigade_Db_Table_Brigades();
                //$projectList= $projectManage->listProjectName($searchstr);
                $projectList= $projectManage->listName($searchstr);
                if($this->_request->isXmlHttpRequest())
                {
                    $this->_helper->layout->disableLayout();
                    $this->_helper->viewRenderer->setNoRender();
                    $results = array();
                    foreach ($projectList as $list) {
                        $results[] = array("id" => $list["ProjectId"], "value" => "$list[Name]");
                    }
                    $payload = array("results"=>$results);
                    header("Content-type: text/json");
                    echo Zend_Json::encode($payload);
                }
            }
        }
        
        catch (Exception $e) {
            // throw $e;
            $this->view->error = $e->getMessage();
        }
    }

    public function participatingAction() {
            if (isset($_POST['Organization'])) {
                $this->_helper->layout->disableLayout();
                $this->_helper->viewRenderer->setNoRender();
                extract($_POST);

                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$NONPROFIT_SIGNUP_NOTIFICATION,
                                   array($Name, $Organization, $Phone, $Email, $Comments, "\"$Name\" <$Email>"));

            } else {
                $organizations_list = Organization::listAll();

                $this->view->data = $organizations_list;

                $paginator = Zend_Paginator::factory($this->view->data);
                $page = $this->_getParam('page', 1);
                $paginator->setItemCountPerPage(15);
                $paginator->setCurrentPageNumber($page);
                $this->view->paginator = $paginator;

                $this->view->total = count($this->view->data);
                $this->view->end = $page * 5;
                $this->view->start = $this->view->end - 4;
                $this->view->end = $this->view->end > $this->view->total ? $this->view->total : $this->view->end;
                $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
            }
    }


    public function testAction() {
        ini_set("memory_limit", "256M");
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        //$parameters = $this->_getAllParams();
        //$Brigades = new Brigade_Db_Table_Brigades();
        //$activities = new Brigade_Db_Table_SiteActivities();
        //$sitemedia = new Brigade_Db_Table_Media();
        //$Groups = new Brigade_Db_Table_Groups();
        //$Groups->populateSiteIDs();
       // $Programs = new Brigade_Db_Table_Programs();
       // $Programs->populateNetworkId();
        //$Organizations = new Brigade_Db_Table_Organizations();
        //$Organization2 = new Brigade_Db_Table_Organizations2();
        //$Users = new Brigade_Db_Table_Users();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $list = $Volunteers->fullList();
        foreach($list as $item) {
            if($item['Type'] == 1 && $item['isActive'] == 1 && $item['IsDeleted'] == 0 && $item['IsDenied'] == 0) {
                $Volunteers->activateVolunteer($item['VolunteerId']);
            }
        }
        //$donations = new Brigade_Db_Table_ProjectDonations();
        //$blogs = new Brigade_Db_Table_Blogs();
        //$GroupMembers = new Brigade_Db_Table_GroupMembers();

        /*
         * script for populating the activity feeds table
         */
        /*
        $donations->storeUserDonationActivities();
        $sitemedia->storeUploadActivities();
        $events->storeEventActivities();
        $blogs->storeBlogActivities();
        $Brigades->storeBrigadeActivities();
        $Groups->storeGroupActivities();
        $Volunteers->storeVolunteersJoined();
        // for changing the photos feed links to /photos/?ProjectId
        $acts = $activities->getSiteActivity('Uploads');
        foreach($acts as $act) {
            $info = $Brigades->loadInfo1($act['SiteId']);
            if (count($info) > 0) {
                $activities->updateSiteActivity($act['SiteActivityId'], '/photos/?ProjectId='.$act['SiteId']);
            }
        }
         *
         */
        /* end */
        /*
        
        // get all group volunteers and store it in group_members table
        $group_volunteers = $Volunteers->getAllVolunteers();
        foreach($group_volunteers as $member) {
            if (!$GroupMembers->isMemberExists($member['GroupId'], $member['UserId'])) {
                $GroupMembers->AddGroupMember(array(
                    'GroupId' => $member['GroupId'],
                    'UserId' => $member['UserId'],
                    'JoinedOn' => $member['CreatedOn']
                ));
            }
        }

        // get all group admins
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $group_administrators = $UserRoles->getGroupAdmins();
        foreach($group_administrators as $member) {
            $is_member_exists = $GroupMembers->isMemberExists($member['GroupId'], $member['UserId']);
            if (!$is_member_exists || empty($is_member_exists)) {
                $GroupMembers->AddGroupMember(array(
                    'GroupId' => $member['GroupId'],
                    'UserId' => $member['UserId'],
                    'isAdmin' => 1
                ));
            } else {
                $GroupMembers->EditGroupMember($is_member_exists['MemberId'], array('isAdmin' => 1));
            }
        }

        // get all group staffs
        $SiteStaffs = new Brigade_Db_Table_SiteStaffs();
        $group_staffs = $SiteStaffs->getAllGroupStaffs();
        foreach($group_staffs as $member) {
            $is_member_exists = $GroupMembers->isMemberExists($member['GroupId'], $member['UserId']);
            if (!$is_member_exists) {
                $GroupMembers->AddGroupMember(array(
                    'GroupId' => $member['GroupId'],
                    'UserId' => $member['UserId']
                ));
            } else {
                $GroupMembers->EditGroupMember($is_member_exists['MemberId'], array('Title' => $member['Title']));
            }
        }
       
        // get all group admins and store the emails to group_email_accounts table
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        $group_administrators = $UserRoles->getGroupAdmins();
        foreach($group_administrators as $member) {
            $userInfo = $Users->loadInfo($member['UserId']);
            $is_email_exists = $GroupEmailAccounts->isEmailExists($member['GroupId'], $member['UserId']);
            if (!$is_email_exists || empty($is_member_exists)) {
                $GroupEmailAccounts->AddEmailAccount(array(
                    'GroupId' => $member['GroupId'],
                    'Email' => $userInfo['Email'],
                    'isVerified' => 1
                ));
            }
        }

	*/

        // populate the GC/PP Id on groups table
        // $Groups->populateGCAndPPAccounts();

        // populate Currency and isNonprofit on groups table
        // $Groups->populateCurrencyAndisNonProfit();

        /*
        // populate data from fundraising_campaigns to projects table
        $FundraisingCampaigns = new Brigade_Db_Table_FundraisingCampaign();
        $FundraisingCampaigns->PopulateToProjectsTable();


        // populate data from fundraisers to volunteers
        $Fundraisers = new Brigade_Db_Table_Fundraisers();
        $Fundraisers->PopulateToVolunteers();

        // update lookup_table to set FieldId=ProjectId where Controller=fundraisingcampaign
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $LookupTable->updateCampaignsToProjects();

        // set project status, isOpen: Open=1, Close=0 and isFundraising: Yes=1, No=0
        $Brigades->setProjectStatus();
         *
         */
        
        // populate the NetworkId field in the group_members table
        //$GroupMembers->populateNetworkId();
        //
        // populate the NetworkId field in the group_membership_requests table
        //set_time_limit(0);

        //$GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
        //$GroupMembershipRequest->populateNetworkId();
        
        // populate the GroupId, ProgramId and NetworkId to the projects table
        //$Brigades->populateSiteIDs();
        
        // populate the GroupId, ProgramId and NetworkId to the volunteers table
        //$Volunteers = new Brigade_Db_Table_Volunteers();
        //$Volunteers->populateSiteIds();
        //set_time_limit(0);
        // populate data to the new columns in user_roles table
        //$UserRoles = new Brigade_Db_Table_UserRoles();
        //$UserRoles->populateNewColumns();
        
        // populate data to the new columns in contactinformation table
        //$ContactInfo = new Brigade_Db_Table_ContactInformation();
        //$ContactInfo->populateNewColumns();
                
        // populate SiteId events table
        //$Events = new Brigade_Db_Table_Events();
        //$Events->populateSiteIds();
        
        // populate the GCID, PPID and Currency fields in the projects table
        //$Brigades = new Brigade_Db_Table_Brigades();
        //$Brigades->populateGCandPPAccountIDs();
        
        // update unclassified groups reference table and add them up to networks table
        //set_time_limit(0);
        //$Groups = new Brigade_Db_Table_Groups();
        //$Groups->updateUnclassifiedGroupsReferenceTables();
        
        //$Organizations = new Brigade_Db_Table_Organizations();
        //$Organizations->populateCurrency();
        
        // populate PP, GC and Currency on projects and events tables based from the org they belong to
        //set_time_limit(0);
        //$Events = new Brigade_Db_Table_Events();
        //$Events->populateGCPPandCurrency();
        //$Brigades = new Brigade_Db_Table_Brigades();
        //$Brigades->populateGCPPandCurrency();

        //set_time_limit(0);
        //$Users = new Brigade_Db_Table_Users();
        //$Media = new Brigade_Db_Table_Media();
        //$Photo = new Brigade_Db_Table_Photo();
        //$Media->updateLogoMediaNames();
        //$Users->updateLogoMediaNames();
        //$Photo->updatePhotoNames();
        
        
        
        
        //         Check if there are any new 'File Added' records in the site_activities table
        // populate NetworkId, ProgramId, GroupId on project_donations table
        //$ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        //$ProjectDonations->populateSiteIds();
        
        
        
        
        // populate the activity feed for programs/group added
        //$Groups = new Brigade_Db_Table_Groups();
        //$Groups->populateCreatedGroupActivtyFeed();
        
        //$Programs = new Brigade_Db_Table_Programs();
        //$Programs->populateCreatedProgramActivtyFeed();
        
        // update all allowPercentageFee=no to allowPercentageFee=optional
        //$Users = new Brigade_Db_Table_Users();
        //$Brigades = new Brigade_Db_Table_Brigades();
        //$Organizations = new Brigade_Db_Table_Organizations();
        //$Users->updateAllowPercentageFee();
        //$Groups->updateAllowPercentageFee();
        //$Brigades->updateAllowPercentageFee();
        //$Organizations->updateAllowPercentageFee();
    }

    private function subtractDaysFromToday($number_of_days) {
        $today = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $subtract = $today - (86400 * $number_of_days);
        return date("Y-m-d", $subtract);
    }

    //need to update this to show months.
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
        } else {
            $durationFORMAT =  "0 seconds ago";
            Zend_Registry::get('logger')->err("There was an error trying to calculate the time diff between the actual time {$currentdate} with the feed created on {$date}");
        }

        return $durationFORMAT;
    }
        
    public function scriptsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Organizations = new Brigade_Db_Table_Organizations();
        $from_date = $this->subtractDaysFromToday(8);
        $to_date = $this->subtractDaysFromToday(1);
        $where = "CreatedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59'";
        $where1 = "v.CreatedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59'";
        
        $new_org_count = $Organizations->fetchRow($Organizations->select()
            ->from('networks', array('COUNT(*) as total_count'))
            ->where($where))->toArray();
        //echo "New Organizations signed up: ".$new_org_count['total_count'];
        
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $new_volunteers_count = $Volunteers->fetchRow($Volunteers->select()
            ->from(array('v' => 'volunteers'), array('COUNT(DISTINCT v.UserId) as total_count'))
            ->joinInner(array('p' => 'projects'), "v.ProjectId=p.ProjectId")
            ->where("p.Type = 0")
            ->where($where1)
            ->setIntegrityCheck(false))->toArray();
        //echo "<br>New Volunteers signed up: ".$new_volunteers_count['total_count'];
        
        $new_fundraisers_count = $Volunteers->fetchRow($Volunteers->select()
            ->from(array('v' => 'volunteers'), array('COUNT(DISTINCT v.UserId) as total_count'))
            ->joinInner(array('p' => 'projects'), "v.ProjectId=p.ProjectId")
            ->where("p.Type = 1")
            ->where($where1)
            ->setIntegrityCheck(false))->toArray();
        //echo "<br>New Fundraisers signed up: ".$new_fundraisers_count['total_count'];
        
        $Donations = new Brigade_Db_Table_ProjectDonations();
        $toal_fundraised = $Donations->fetchRow($Donations->select()
            ->from('project_donations', array('SUM(DonationAmount) as total_fundraised'))
            ->where("OrderStatusId BETWEEN 1 AND 2")
            ->where($where))->toArray();
        
        $TicketHolders = new Brigade_Db_Table_EventTicketHolders();
        $ticket_holders_count = $TicketHolders->fetchRow($TicketHolders->select()
            ->from('event_ticket_holders', array('COUNT(*) as total_count')))->toArray();
        
        $Users = new Brigade_Db_Table_Users();
        $user_loggedin_count = $Users->fetchRow($Users->select()
            ->from('users', array('COUNT(*) as total_count'))
            ->where("LastLogin BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59'"))->toArray();
        
        $total_users = $Users->fetchRow($Users->select()
            ->from('users', array('COUNT(*) as total_count')))->toArray();
        
        $average_user_loggedin = $user_loggedin_count['total_count'] / $total_users['total_count'];
        
        $modified_profile_count = $Users->fetchRow($Users->select()
            ->from('users', array('COUNT(*) as total_count'))
            ->where("ModifiedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59'"))->toArray();
        
        $Files = new Brigade_Db_Table_Files();
        $files_shared_count = $Files->fetchRow($Files->select()
            ->from(array('f' => 'files'), array('COUNT(*) as total_count'))
            ->where($where)
            ->where("GroupId IN (SELECT GroupId FROM groups) OR GroupId IN (SELECT NetworkId FROM networks)")
            ->setIntegrityCheck(false))->toArray();
        
        $Surveys = new Brigade_Db_Table_GroupSurveys();
        $surveys_created_count = $Surveys->fetchRow($Files->select()
            ->from(array('s' => 'group_surveys'), array('COUNT(*) as total_count'))
            ->where($where)
            ->where("GroupId IN (SELECT GroupId FROM groups) OR GroupId IN (SELECT NetworkId FROM networks)")
            ->setIntegrityCheck(false))->toArray();
        
        $Brigades = new Brigade_Db_Table_Brigades();
        $projects_created_count = $Surveys->fetchRow($Files->select()
            ->from('projects', array('COUNT(*) as total_count'))
            ->where($where)
            ->setIntegrityCheck(false))->toArray();
        $Events = new Brigade_Db_Table_Events();
        $events_created_count = $Surveys->fetchRow($Files->select()
            ->from('events', array('COUNT(*) as total_count'))
            ->where($where)
            ->setIntegrityCheck(false))->toArray();
        
        $headers = "Metrics \t Total Count \t";
        $data = "New Organizations signed up \t".$new_org_count['total_count']."\t \n";
        $data .= "New Volunteers signed up \t".$new_volunteers_count['total_count']."\t \n";
        $data .= "New Fundraisers signed up \t".$new_fundraisers_count['total_count']."\t \n";
        $data .= "Total Amount Fundraised \t".$toal_fundraised['total_fundraised']."\t \n";
        $data .= "Ticket Holders \t".$ticket_holders_count['total_count']."\t \n";
        $data .= "Total # of users that logged in \t".$user_loggedin_count['total_count']."\t \n";
        $data .= "Average # of users that logged in \t".$average_user_loggedin."\t \n";
        $data .= "Total # of users that Modified their account \t".$modified_profile_count['total_count']."\t \n";
        $data .= "Total # of Groups/ Organizations that shared a file \t".$files_shared_count['total_count']."\t \n";
        $data .= "Total # of Groups/ Organizations survey created \t".$surveys_created_count['total_count']."\t \n";
        $data .= "Total # of Volunteer opp/ Events/ Campaigns created \t".($projects_created_count['total_count'] + $events_created_count['total_count'])."\t \n";
        $output = "$headers\n$data";
        
        $temp_path = realpath(dirname(__FILE__).'/../../../')."/public/tmp/export.xls";
        $fp = fopen($temp_path, 'w');
        if ($fp) {
            fwrite($fp, $output);
        }
        fclose($fp);
        $Mailer = new Mailer();
        $Mailer->sendMetricsReport(file_get_contents($temp_path), "Metrics $from_date to $to_date");
        if (file_exists($temp_path)) {
            unlink($temp_path);
        }
    }
    
    public function scripts2Action() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Brigades = new Brigade_Db_Table_Brigades();
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $Organizations = new Brigade_Db_Table_Organizations();
        /*
        $org_without_event_act_camp = $Organizations->fetchAll($Organizations->select()
            ->from(array('g' => 'groups'), array('g.GroupId', 'g.GroupName', 'g.URLName', 'g.NetworkId', 'g.*'))
            ->where("g.GoogleCheckoutAccountId > 0 OR g.PaypalAccountId > 0")
            ->where("(SELECT COUNT(*) FROM project_donations pd INNER JOIN projects p ON pd.ProjectId = p.ProjectId WHERE p.GroupId = g.GroupId AND pd.OrderStatusId = 2) = 0")
            ->where("g.NetworkId = '2A3801E4-203D-11E0-92E6-0025900034B2'")
            ->setIntegrityCheck(false))->toArray();
         *
         */
        //echo "<b>Organization haven't created an event/campaign/activity</b> <pre>";
        //print_r($org_without_event_act_camp);
        //echo "</pre>";
        $rows = $Organizations->fetchAll($Organizations->select()
            ->from(array('n' => 'networks'), array('n.NetworkId', 'n.NetworkName', 'n.URLName', 'p.ProjectId', 'p.DonationGoal', 'n.hasGroups'))
            ->joinInner(array('p' => 'projects'), 'n.NetworkId=p.ProjectId')
            ->where("n.NetworkId = p.NetworkId")
            ->setIntegrityCheck(false))->toArray();
        /*
        $org_with_act_camp = array();
        foreach ($rows as $row) {
            $projInfo = $Brigades->loadInfo($row['ProjectId']);
            if ($row['DonationGoal'] > $projInfo['total_donations'] && !isset($org_with_act_camp[$row['NetworkId']])) {
                $org_with_act_camp[$row['NetworkId']] = array(
                    'NetworkId' => $row['NetworkId'],
                    'NetworkName' => $row['NetworkName'],
                    'URLName' => $row['URLName']
                );
            }
        }
         *
         */
        //echo "<b>Organization created an event/campaign/activity but haven't met their goals</b> <pre>";
        //print_r($org_with_act_camp);
        //echo "</pre>";
        /*
        $export_to_excel = array();
        foreach($org_without_event_act_camp as $org) {
            $siteAdmins = $UserRoles->getSiteAdmin($org['GroupId']);
            foreach ($siteAdmins as $admin) {
                $export_to_excel[] = array('Organization' => $org['GroupName'], 'AdminName' => $admin['FullName'], 'AdminEmail' => $admin['Email']);
            }
        }
         *
         */
        //foreach($org_with_act_camp as $org) {
         //   $siteAdmins = $UserRoles->getSiteAdmin($org['NetworkId']);
        //    foreach ($siteAdmins as $admin) {
        //        $export_to_excel[] = array('Organization' => $org['NetworkName'], 'AdminName' => $admin['FullName'], 'AdminEmail' => $admin['Email']);
        //    }
        //}
        
        $unsuccessful_orgs = array();
        foreach ($rows as $row) {
            $projInfo = $Brigades->loadInfo($row['ProjectId']);
            if ($projInfo['Type'] == 0 && ($projInfo['isFundraising'] == 'No' || $projInfo['isFundraising'] == 0) && $projInfo['total_volunteers'] == 0 && !isset($unsuccessful_orgs[$row['NetworkId']])) {
                $unsuccessful_orgs[$row['NetworkId']] = array(
                    'NetworkName' => $row['NetworkName'],
                );
            } else if ($projInfo['Type'] == 0 && ($projInfo['isFundraising'] == 'Yes' || $projInfo['isFundraising'] == 1) && $projInfo['total_volunteers'] == 0 && $projInfo['total_donations'] == 0 && !isset($unsuccessful_orgs[$row['NetworkId']])) {
                $unsuccessful_orgs[$row['NetworkId']] = array(
                    'NetworkName' => $row['NetworkName'],
                );
            } else if ($projInfo['Type'] == 1 && $projInfo['total_donations'] == 0 && !isset($unsuccessful_orgs[$row['NetworkId']])) {
                $unsuccessful_orgs[$row['NetworkId']] = array(
                    'NetworkName' => $row['NetworkName'],
                );
            }
        }
        
        $export_to_excel_2 = array();
        foreach($unsuccessful_orgs as $NetworkId => $info) {
            $siteAdmins = $UserRoles->getSiteAdmin($NetworkId);
            foreach ($siteAdmins as $admin) {
                $export_to_excel_2[] = array('Organization' => $info['NetworkName'], 'AdminName' => $admin['FullName'], 'AdminEmail' => $admin['Email']);
            }
        }
        
        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=Organizations-List.xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $headers = "Organization Name \t Admin Name \t Admin Email \t";
        $data = '';
        
        foreach($export_to_excel_2 as $row) {
            extract($row);
            $data .= stripslashes($Organization)." \t ".stripslashes($AdminName)." \t $AdminEmail \t\n";
        }
        $data = str_replace("\r", "", $data);
        print "$headers\n$data";
    }
    
    public function scripts3Action() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Groups = new Brigade_Db_Table_Groups();
        $Organizations = new Brigade_Db_Table_Organizations();
        $from_date = $this->subtractDaysFromToday(8);
        $to_date = $this->subtractDaysFromToday(1);
        $where = "CreatedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59'";
        $where1 = "v.CreatedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59'";
        
        $new_org = $Organizations->fetchAll($Organizations->select()
            ->from('networks', array('*'))
            ->where($where))->toArray();
        //echo "New Organizations signed up: ".$new_org_count['total_count'];
        $signed_up = $org_details = count($new_org);
        $uploaded_members = 0;
        foreach($new_org as $org) {
            if ($org['hasUploadedMembers']) $uploaded_members++;
        }
        $assigned_admins = 0;
        foreach($new_org as $org) {
            if ($org['hasAssignedAdmins']) $assigned_admins++;
        }
        $shared_to_social_networks = 0;
        foreach($new_org as $org) {
            if ($org['hasSharedSocialNetworks']) $shared_to_social_networks++;
        }
        
        $groups_created = $Groups->fetchAll($Groups->select()->where("NetworkId IN (SELECT n.NetworkId FROM networks n WHERE n.CreatedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59')"))->toArray();
        $total_groups_created = count($groups_created);
        $group_uploaded_members = 0;
        foreach($groups_created as $group) {
            if ($group['hasUploadedMembers']) $group_uploaded_members++;
        }
        $group_assigned_admins = 0;
        foreach($groups_created as $group) {
            if ($group['hasAssignedAdmins']) $group_assigned_admins++;
        }
        $group_shared_to_social_networks = 0;
        foreach($groups_created as $group) {
            if ($group['hasSharedSocialNetworks']) $group_shared_to_social_networks++;
        }
        
        $Brigades = new Brigade_Db_Table_Brigades();
        $projects_created = $Brigades->fetchAll($Brigades->select()
            ->from('projects', array('COUNT(*) as total_count'))
            ->where($where)
            ->setIntegrityCheck(false))->toArray();
        $total_projects_created = count($projects_created);
        $photos_added = 0;
        $Media = new Brigade_Db_Table_Media();
        foreach($projects_created as $project) {
            $logo = $Media->getSiteMediaBySiteId($project['ProjectId']);
			//this should be photos not logos
            if (!empty($logo)) $photos_added++;
        }
        $project_uploaded_members = 0;
        foreach($projects_created as $project) {
            if ($project['hasUploadedMembers']) $project_uploaded_members++;
        }
        $project_shared_to_social_networks = 0;
        foreach($projects_created as $project) {
            if ($project['hasSharedSocialNetworks']) $project_shared_to_social_networks++;
        }
        
        $shared_files = 0;
        $Files = new Brigade_Db_Table_Files();
        $files_shared = $Files->fetchAll($Files->select()
            ->from('files', array('COUNT(DISTINCT FileId) as files_count', 'GroupId')) // GroupId/NetworkId
            ->where("GroupId IN (SELECT NetworkId FROM networks n WHERE n.CreatedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59')")
            ->group(array('GroupId')))->toArray();
        foreach($files_shared as $file) {
            if ($file['files_count'] > 0) $shared_files++;
        }
        
        $created_surveys = 0;
        $Surveys = new Brigade_Db_Table_GroupSurveys();
        $surveys_created = $Surveys->fetchAll($Files->select()
            ->from(array('s' => 'group_surveys'), array('COUNT(DISTINCT SurveyId) as surveys_count', 'GroupId'))
            ->where("Level = 'organization'")
            ->where("GroupId IN (SELECT NetworkId FROM networks n WHERE n.CreatedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59')")
            ->group(array('GroupId')))->toArray();
        foreach($surveys_created as $survey) {
            if ($survey['surveys_count'] > 0) $created_surveys++;
        }
        
        $created_notes = 0;
        $VolunteerNotes = new Brigade_Db_Table_VolunteerNotes();
        $notes = $VolunteerNotes->fetchRow($VolunteerNotes->select()
            ->from(array('n' => 'volunteer_notes'), array('COUNT(DISTINCT VolunteerNoteId) as notes_count', 'NetworkID'))
            ->joinInner(array('v' => 'volunteers'), 'n.VolunteerId=v.VolunteerId')
            ->where("v.NetworkID IN (SELECT NetworkId FROM networks n WHERE n.CreatedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59')")
            ->group(array('NetworkID')))->toArray();
        foreach($notes as $note) {
            if ($survey['notes_count'] > 0) $created_notes++;
        }
        
        $downloaded_reports = 0;
        foreach($new_org as $org) {
            if ($org['hasDownloadedReports']) $downloaded_reports++;
        }
        
        $sent_emails = 0;
        foreach($new_org as $org) {
            if ($org['hasSentEmails']) $sent_emails++;
        }
        
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $new_volunteers = $Volunteers->fetchRow($Volunteers->select()
            ->from(array('v' => 'volunteers'), array('v.*'))
            ->joinInner(array('p' => 'projects'), "v.ProjectId=p.ProjectId")
            ->where("p.Type = 0")
            ->where($where1)
            ->setIntegrityCheck(false))->toArray();
        $volunteers_shared = 0;
        foreach($new_volunteers as $volunteer) {
            if ($volunteer['hasSharedSocialNetworks']) $volunteers_shared++;
        }
        
        $new_fundraisers = $Volunteers->fetchRow($Volunteers->select()
            ->from(array('v' => 'volunteers'), array('v.*'))
            ->joinInner(array('p' => 'projects'), "v.ProjectId=p.ProjectId")
            ->where("p.Type = 1")
            ->where($where1)
            ->setIntegrityCheck(false))->toArray();
        $edited_message = 0;
        foreach($new_fundraisers as $fundraiser) {
            if ($fundraiser['hasEditMessage']) $edited_message++;
        }
        $fundraisers_shared = 0;
        foreach($new_fundraisers as $fundraiser) {
            if ($fundraiser['hasSharedSocialNetworks']) $fundraisers_shared++;
        }
        
        $Users = new Brigade_Db_Table_Users();
        $new_users = $Users->fetchAll($Users->select()->where($where))->toArray();
        
        $edited_profile = 0;
        foreach($new_users as $user) {
            if ($user['hasEditedInfo']) $edited_profile++;
        }

        $user_volunteers = 0;
        foreach($new_users as $user) {
            $volunteer_count = $Volunteers->getBrigadesJoined($user['UserId']);
            if (count($volunteer_count)) $user_volunteers++;
        }
        
        $user_fundraises = 0;
        foreach($new_users as $user) {
            $volunteer_count = $Volunteers->getFundraisingBrigadesJoined($user['UserId']);
            if (count($volunteer_count)) $user_fundraises++;
        }
        
        $user_donates = 0;
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        foreach($new_users as $user) {
            $user_donations = $ProjectDonations->getUserDonations($user['UserId']);
            if (count($user_donations)) $user_donates++;
        }
        
        $user_wall_posts = 0;
        $WallPost = new Brigade_Db_Table_SiteActivityComments();
        foreach($new_users as $user) {
            $posts = $WallPost->fetchAll($WallPost->select()->where('CommentedBy = ?', $user['UserId']))->toArray();
            if (count($posts)) $user_wall_posts++;
        }
        
        $user_joins_org_chapters = 0;
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        foreach($new_users as $user) {
            $joins = $GroupMembers->fetchAll($GroupMembers->select()->where('UserId = ?', $user['UserId']))->toArray();
            if (count($joins)) $user_joins_org_chapters++;
        }
        
        $member_volunteers = 0;
        $members = $GroupMembers->fetchAll($GroupMembers->select()->where("JoinedOn BETWEEN '$from_date 00:00:00' AND '$to_date 11:59:59'")->group("UserId"))->toArray();
        foreach($members as $member) {
            $volunteer_count = $Volunteers->getBrigadesJoined($member['UserId']);
            if ($count($volunteer_count)) $member_volunteers++;
        }
        
        $member_fundraises = 0;
        foreach($members as $member) {
            $volunteer_count = $Volunteers->getFundraisingBrigadesJoined($member['UserId']);
            if ($count($volunteer_count)) $member_fundraises++;
        }
        
        $member_donates = 0;
        foreach($members as $member) {
            $user_donations = $ProjectDonations->getUserDonations($member['UserId']);
            if ($count($user_donations)) $member_donates++;
        }
        
        $donor_donates = $ProjectDonations->fetchAll($ProjectDonations->select()->where($where)->group('SupporterEmail'))->toArray();
        
        $chart = array();
        $chart['Organization Admins'] = array(
            'Signup Process' => array(
                'Sign Up' => "100%",
                'Org Details' => "100%",
                'Upload Members' => (($uploaded_members/count($new_org))*100)."%",
                'Admins Assigned' => (($assigned_admins/count($new_org))*100)."%",
                'Share' => (($shared_to_social_networks/count($new_org))*100)."%"
            ),
            'Engagement' => array(
                'Groups' => array(
                    'Group Created' => array(1 => "100%", 3 => $total_groups_created),
                    'Upload Members' => (($group_uploaded_members/$total_groups_created)*100)."%",
                    'Admins Assigned' => (($group_assigned_admins/$total_groups_created)*100)."%",
                    'Share' => (($group_shared_to_social_networks/$total_groups_created)*100)."%",
                ),
                'Projects' => array(
                    'Action Created' => "100%",
                    'Photos Added' => array(1 => (($photos_added/$total_projects_created)*100)."%", 2 => ''),
                    'Upload Participants' => (($project_uploaded_members/$total_projects_created)*100)."%",
                    'Share' => (($project_shared_to_social_networks/$total_projects_created)*100)."%",
                ),
                'Tools Usage' => array(
                    'Files' => (($shared_files/count($new_org))*100)."%",
                    'Surveys' => (($created_surveys/count($new_org))*100)."%",
                    'Notes' => (($created_notes/count($new_org))*100)."%",
                    'Reports' => (($downloaded_reports/count($new_org))*100)."%",
                    'Sent Emails' => (($sent_emails/count($new_org))*100)."%",
                )
            ),
            'Goal' => array()
        );
        $chart['Volunteer'] = array(
            'Engagement' => array(
                'Volunteer' => array(1 => '100%', 3 => count($new_volunteers)),
                'Upload' => 'N/A',
                'Share' => (($volunteers_shared/count($new_volunteers))*100)."%"
            )
        );
        $chart['Attendee'] = array(
            'Engagement' => array(
                'Attendee' => array(1 => '', 3 => ''),
                'Share' => 'N/A'
            )
        );
        $chart['Fundraiser'] = array(
            'Engagement' => array(
                'Fundraiser' => array(1 => '100%', 3 => count($new_fundraisers)),
                'Edit Message' => (($edited_message/count($new_fundraisers))*100)."%",
                'Upload' => 'N/A',
                'Share' => (($fundraisers_shared/count($new_fundraisers))*100)."%"
            )
        );
        $chart['Donor'] = array(
            'Engagement' => array(
                'Donates' => array(1 => '100%', count($donor_donates)),
                'Share' => 'N/A',
                'Fundraises' => 'N/A'
            )
        );
        $chart['Member'] = array(
            'Engagement' => array(
                'Volunteers' => array(2 => '', 3 => $member_volunteers),
                'Donates' => array(2 => '', 3 => $member_donates),
                'Attends Event' => '',
                'Shares' => 'N/A',
                'Fundraises' => array(2 => '', 3 => $member_fundraises)
            )
        );
        $chart['User'] = array(
            'Sign Up Process' => array(
                'Sign Up' => "100%",
                'Profile Photo/Details' => (($edited_profile/count($new_users))*100)."%",
                'Share' => 'N/A'
            ),
            'Engagement' => array(
                'Volunteers' => array(2 => '', 3 => $user_volunteers),
                'Donates' => array(2 => '', 3 => $user_donates),
                'Attends' => 'N/A',
                'Shares' => 'N/A',
                'Joins Org/Chapter' => array(2 => '', 3 => $user_joins_org_chapters),
                'Fundraises' => array(2 => '', 3 => $user_fundraises),
                'Wall Posts' => array(2 => '', 3 => $user_wall_posts)
            )
        );
        echo "<pre>";
        print_r($chart);
        echo "</pre>";
        
    }
}