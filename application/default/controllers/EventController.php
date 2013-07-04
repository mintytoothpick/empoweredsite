<?php

/**
 * EventController - The "event" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Events.php';
require_once 'Brigade/Db/Table/EventTickets.php';
require_once 'Brigade/Db/Table/EventTicketHolders.php';
require_once 'Brigade/Db/Table/EventTicketsPurchased.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/PaypalAccounts.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/Photo.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/UserRoles.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/Survey.php';
require_once 'Brigade/Db/Table/Paypal.php';
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';
require_once 'Paypal/Paypal.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/GroupSurveys.php';
require_once 'BaseController.php';

require_once 'Event.php';


class EventController extends BaseController {

    protected $checkout_url;
    protected $merchantID;
    protected $merchantkey;
    protected $currency = "USD";
    protected $server_type = ''; // change this to anything other than 'sandbox' to go live
    protected $editcarturl;
    protected $returncarturl;

    protected $event;

    public function init() {
        $front = Zend_Controller_Front::getInstance();
        $actionName = $front->getRequest()->getActionName();
        parent::init();

        $parameters = $this->_getAllParams();
        if (isset($parameters['EventId'])) {
            $this->event = Event::get($parameters['EventId']);
            if ($this->event->isDeleted) {
                throw new Zend_Controller_Action_Exception('Event Deleted', 404);
            }
            $parameters['SiteId'] = $this->event->siteId;
        }
        if (isset($parameters['SiteId']) && isset($_SESSION['UserId'])) {
            $SiteId = $parameters['SiteId'];
            if(!isset($parameters['Level'])) {
                $parameters['Level'] = 'group';
            }
            $UserRoles = new Brigade_Db_Table_UserRoles();
            if($this->_helper->authUser->isLoggedIn()) {
                $role = $UserRoles->getUserRole($_SESSION['UserId']);
                $hasAccess = $UserRoles->UserHasAccess($SiteId, $_SESSION['UserId'], $parameters['Level']);
                if (($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
                    $this->view->isAdmin = true;
                    $this->view->toggleAdminView = $role['isToggleAdminView'];
                    $this->view->UserRoleId = $role['UserRoleId'];
                }
            }
        }

        /* Moved to BaseController. TO DELETE.
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
                    $_SESSION['UserId'] = $userInfo->UserId;
                    header("Location: " . $_SERVER['PHP_SELF']);
                }
            }
        }
        */
    }

    public function indexAction() {
        $parameters = $this->_getAllParams();

        $this->view->shareUrlPrefix;
        //Data
        if (!empty($parameters['SiteId'])) {
            $group = $this->view->group = Group::get($parameters['SiteId']);
            $this->view->shareUrlPrefix = $group->urlName;
        } else if (isset($parameters['NetworkId'])) {
            $organization = Organization::get($parameters['NetworkId']);
            $this->view->shareUrlPrefix = $organization->urlName;
        } else if (isset($parameters['UserId'])) {
            $user = User::get($parameters['UserId']);
            $this->view->shareUrlPrefix = $user->urlName;
        }

        $status = '';
        if (isset($parameters['status'])) {
            $status = $parameters['status'];
        } else if(isset($parameters['List'])) {
            $status = explode('-', $parameters['List']);
            $status = $status[0];
        }
        if ($status != '') {
            $eventsCall = 'events_'.$status;
        } else {
            $eventsCall = 'events';
            $status     = 'upcoming';
        }

        if (!empty($group)) {
            $Events = $group->$eventsCall;
        } else if (!empty($user)) {
            $Events = $user->$eventsCall;
        }

        // Current Event
        if (isset($parameters['EventId'])) {
            $Event = Event::get($parameters['EventId']);
        } elseif (isset($Events)) {
            $Event = $Events[0];
        }

        //If not available
        if (!$Event) {
            if (!empty($group)) {
                $this->_helper->redirector->gotoUrl('/'.$group->urlName);
            } else if (!empty($user)) {
                $this->_helper->redirector->gotoUrl('/'.$user->urlName);
            } else {
                $this->_helper->redirector->gotoUrl('/'.$organization->urlName);
            }
        }
        if (isset($Events)) {
            $this->view->events = $Events;
        }

        $this->view->currentTab = 'events';
        $this->view->daysToGo   = $Event->getDaysToGo();

        $this->view->event  = $Event;
        $this->view->status = $status;

        //breadcrumb
        $this->view->breadcrumb = array();
        if (isset($organization)) {
            $this->view->organization = $organization;
            $this->view->breadcrumb   = $this->view->breadcrumbHelper($organization);
            $this->view->render('nonprofit/tabs.phtml');
            $this->view->render('nonprofit/header.phtml');
        } else if (isset($group)) {
            $this->view->organization = $group->organization;
            $this->view->group        = $group;
            $this->view->breadcrumb   = $this->view->breadcrumbHelper($group);
            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');
        } else if (isset($user)) {
            $this->view->user = $user;
            $this->view->render('profile/header.phtml');
            $this->view->render('profile/tabs.phtml');
        } else {
            $this->view->render('event/header.phtml');
        }
        $this->view->breadcrumb[] = stripslashes($Event->title);

        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('event/right_bar.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->view->render('event/toolbox.phtml');

        $this->_helper->layout->setLayout('newlayout');
    }

    /**
     * Ajax action to filter initiatives.
     */
    public function filtereventsAction() {
        $parameters = $this->_getAllParams();

        $Event = Event::get($parameters['eventId']);
        if (isset($parameters['SiteId'])) {
            $group              = Group::get($parameters['SiteId']);
            $this->view->events = Event::getListByGroup(
                $group,
                $parameters['status']
            );
        } else if (isset($parameters['UserId'])) {
            $user               = User::get($parameters['UserId']);
            $this->view->events = Event::getListByUser(
                $user,
                $parameters['status']
            );
        } else if (isset($parameters['NetworkId'])) {
            $this->view->events = Event::getListByOrganization(
                $parameters['NetworkId'],
                $parameters['status']
            );
        }
        $this->view->filter = true;
        $this->view->status = $parameters['status'];
        $this->view->event  = $Event;

        $this->render('events');
        $this->_helper->layout()->disableLayout();
    }

    /**
     * Ajax action to get all attendees.
     */
    public function allattendeesAction() {
        $parameters = $this->_getAllParams();

        $Event              = Event::get($parameters['eventId']);
        $this->view->event  = $Event;
        $this->view->all    = true;

        $this->render('attendees');
        $this->_helper->layout()->disableLayout();
    }

    public function manageAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        //$this->_helper->layout->disableLayout();
        $parameters = $this->_getAllParams();
        if (isset($parameters['Type'])) {
            $this->view->Type = $parameters['Type'];
        }
        if (isset($parameters['SiteId'])) {
            $Events = new Brigade_Db_Table_Events();
            $Groups = new Brigade_Db_Table_Groups();
            $LookupTable = new Brigade_Db_Table_LookupTable();
            $this->view->sitemedia = new Brigade_Db_Table_Media();
            $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
            $this->view->events = $Events->getSiteEvents($parameters['SiteId']);
            $this->view->SiteId = $parameters['SiteId'];
            $this->view->data = $Groups->loadInfo($this->view->SiteId);
            $this->view->UserId = $_SESSION['UserId'];
            $this->view->URLName = $LookupTable->getURLbyId($this->view->SiteId);
        }
    }

    public function deleteeventAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters = $this->_getAllParams();
        if (isset($parameters['EventId']) && $this->view->isAdmin) {
            $event = Event::get($parameters['EventId']);
            $event->delete();
            $this->_helper->redirector->gotoUrl('/'.$event->entity->urlName);
        }
    }

    public function addAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout->disableLayout();
        $this->view->SiteId = $parameters['SiteId'];
        $this->view->UserId = $_SESSION['UserId'];
        $this->view->action = "/event/add/".$parameters['SiteId'];
        if ($_POST) {
            $SiteId = $_POST['SiteId'];
            $UserId = $_POST['UserId'];
            $Title = $_POST['Title'];
            $Description = $_POST['Description'];
            $Events = new Brigade_Db_Table_Events();
            $SiteEvents = new Brigade_Db_Table_EventSites();
            // add event record first
            $EventId = $Events->AddEvent(array(
                'Title' => $Title,
                'Description' => $Description,
                'SiteId' => $SiteId
            ));

            // rediect to edit event page
            header('location: /event/edit/'.$EventId);
        }
    }

    /**
     * Admin edit event details.
     */
    public function editAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->viewRenderer('create');
        $this->_helper->layout->setLayout('newlayout');

        $this->view->isEdit = true;

        $Tickets = new Brigade_Db_Table_EventTickets();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $event = Event::get($parameters['EventId']);
        if (!empty($event->siteId)) {
            $siteURL = $LookupTable->getURLbyId($event->siteId);
        } else if (!empty($event->userId)) {
            $siteURL = $LookupTable->getURLbyId($event->userId);
        }
        $this->view->event = $event;
        if ($event->group) {
            $this->view->googleId = $event->group->organization->googleId;
            $this->view->paypalId = $event->group->organization->paypalId;
        }

        $this->view->action = "/event/edit/".$parameters['EventId'];

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

            $event->title         = $Title;
            $event->text          = $Description;
            $event->location      = $Location;
            $event->startDate     = $StartDate;
            $event->endDate       = $EndDate;
            $event->isSellTickets = $isSellTickets;
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
            $this->_helper->redirector->gotoUrl('/'.$event->entity->urlName.'/events?EventId='.$event->id);
        }
        $this->view->render('event/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('event/right_bar.phtml');
        $this->view->render('nonprofit/footer.phtml');
    }

    public function saveinfoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Events = new Brigade_Db_Table_Events();
        if (isset($_POST['EventId'])) {
            $data = array();
            foreach($_POST as $key => $val) {
                if ($key != "EventId" && $key != 'field' && $key != "StartTime" && $key != "EndTime") {
                    $data[$key] = $val;
                }
            }
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
            $Events->updateEvent($_POST['EventId'], $data);

            echo "Event ".$_POST['field']." has been successfully updated.";
        }
    }

    /**
     * Create new event.
     */
    public function createAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();

        $Groups = new Brigade_Db_Table_Groups();
        $Programs = new Brigade_Db_Table_Programs();
        $Organizations = new Brigade_Db_Table_Organizations();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        if (isset($parameters['Type'])) {
            $this->view->Type = $parameters['Type'];
        }

        if(isset($parameters['GroupId'])) {
            $this->view->level      = 'group';
            $group                  = Group::get($parameters['GroupId']);
            $this->view->googleId   = $group->googleId;
            $this->view->paypalId   = $group->paypalId;
            $SiteId                 = $parameters['GroupId'];
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                $group,
                'Create Event'
            );

            $organization      = $group->organization;
            $this->view->group = $group;

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');

        } else if(isset($parameters['NetworkId']) || isset($parameters['ProgramId'])) {
            $this->view->level = 'organization';

            if(isset($parameters['ProgramId'])) {
                $SiteId       = $parameters['ProgramId'];
                $program      = Program::get($parameters['ProgramId']);
                $organization = $this->view->organization = $program->organization;
            } else {
                $SiteId       = $parameters['NetworkId'];
                $organization = $this->view->organization = Organization::get($parameters['NetworkId']);
            }

            $this->view->googleId = $organization->googleId;
            $this->view->paypalId = $organization->paypalId;

            $this->view->programs  =  $organization->programs;
            if(isset($_REQUEST['pid']) && $_REQUEST['pid'] != '') {
                $this->view->groups = $Groups->simpleListByProgram($_REQUEST['pid']);
            } else if(isset($program)) {
                $this->view->groups = $program->groups;
            } else if($organization->hasGroups && !$organization->hasPrograms) {
                $this->view->groups = $organization->groups;
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
                'Create Event'
            );

            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');

        }

        $this->view->organization = $organization;
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');

        if ($_POST) {
            extract($_POST);
            $StartTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['StartTime']);
            $StartDate = trim($_POST['StartDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['StartDate']." ".$StartTime)) : "0000-00-00 00:00:00";
            $EndTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
            $EndDate = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";
            $SiteEvents = new Brigade_Db_Table_EventSites();
            if ($this->view->level == "organization" || $this->view->level == "program") {
                $NetworkId = $organization->id;
                // if the org has no programs yet, create it
                if (isset($ProgramName) && $ProgramName != 'New Program Name') {
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
                        'URLName' => $progURLName,
                        'NetworkId' => $organization->id,
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
                    $SiteId = $ProgramId;
                }
                // if the org has no groups yet, create it
                if (isset($GroupName) && $GroupName != 'New Chapter Name') {
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

                    // save group info first
                    $Groups = new Brigade_Db_Table_Groups();
                    $GroupId = $Groups->addGroup(array(
                        'GroupName' => $GroupName,
                        'Description' => $orgInfo['AboutUs'],
                        'URLName' => $groupURLName,
                        'isOpen' => isset($_POST['isOpen']) ? 1 : 0,
                        'GoogleCheckoutAccountId' => $GoogleCheckoutAccountId,
                        'PaypalAccountId' => $PaypalAccountId,
                        'isNonProfit' => $isNonProfit,
                        'Currency' => $group_currency,
                        'ProgramId' => $orgInfo['hasPrograms'] == 1 ? $ProgramId : '',
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
                        'SiteId' => $GroupId
                    ));

                    // log the site activity
                    $activity              = new Activity();
                    $activity->siteId      = $orgInfo['NetworkId'];
                    $activity->type        = 'Group Added';
                    $activity->createdById = $this->view->userNew->id;
                    $activity->date        = date('Y-m-d H:i:s');
                    $activity->details     = $GroupId;
                    $activity->save();
                }
            }
            if(isset($GroupId)) {
                $SiteId = $GroupId;
            }

            // add event record first
            $event                = new Event();
            $event->title         = $Title;
            $event->text          = $Description;
            $event->location      = $Location;
            $event->startDate     = $StartDate;
            $event->endDate       = $EndDate;
            $event->isSellTickets = $isSellTickets;
            $event->siteId        = $SiteId;
            $event->currency      = !is_null($organization->currency) ? $organization->currency : '$';

            $event->googleCheckoutAccountId = !is_null($organization->googleId) ? $organization->googleId : 0;
            $event->paypalAccountId         = !is_null($organization->paypalId) ? $organization->paypalId : 0;

            // add tickets
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

            // log the site activity
            $activity              = new Activity();
            $activity->siteId      = $SiteId;
            $activity->type        = 'Events';
            $activity->createdById = $this->userNew->id;
            $activity->date        = date('Y-m-d H:i:s');
            $activity->details     = $event->id;
            $activity->save();

            $this->view->message = "Event has been successfully added.";
            header("location: /".$LookupTable->getURLbyId($SiteId)."/share-event?EventId={$event->id}");
        }
    }


    public function addeventAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (isset($_REQUEST['form'])) {
            echo '
                <script>
                    $(function() {
                        $("#StartDate").datepicker({ changeMonth: true, changeYear: true });
                        $("#StartTime").calendricalTime();
                        $("#EndDate").datepicker({ changeMonth: true, changeYear: true });
                        $("#EndTime").calendricalTime();
                    });
                </script>
                <form id="add-form" onsubmit="return addEvent();">
                    <div style="width:675px;">
                        <h2>Add Event</h2>
                        <div id="message" style="display:none; border:2px solid silver; color:red; padding:5px;"></div>
                        <div class="txt01">
                            Title:
                        </div>
                        <input name="Title" type="text" value="" id="Title" style="width:675px;" />
                        <div class="txt01">
                            Details (no html formatting):
                        </div>
                        <textarea name="EventText" rows="2" cols="50" id="EventText" style="height:182px;width:675px;"></textarea>
                        <div class="txt01">
                            When:
                        </div>
                        <div class="date" style="margin-top:3px; width:100%">
                            <div style="width:70px; display:inline; margin-top:4px">Start Date:</div>
                            <input style="margin-left:2px; background:url('.$this->view->contentLocation.'public/images/Pictures/003.gif) no-repeat right; width:100px;" class="date" name="StartDate" type="text" maxlength="10" id="StartDate" value="" />
                            <span style="color:Red;vertical-align:top;">*</span>
                            <div style="width:70px; display:inline; margin-left:10px; margin-top:4px">End Date:</div>
                            <input style="background:url('.$this->view->contentLocation.'public/images/Pictures/003.gif) no-repeat right; width:100px; margin-left:2px" class="date" name="EndDate" type="text" maxlength="10" id="EndDate" value="" />
                            <span style="color:Red;vertical-align:top;">*</span>
                        </div>
                        <div class="date" style="margin-top:3px; width:100%">
                            <div style="width:70px; display:inline; margin-top:4px">Start Time:</div>
                            <input style="width:100px;" class="date" name="StartTime" type="text" maxlength="10" id="StartTime" value="" onkeypress="return false" />
                            <div style="width:70px; display:inline; margin-left:18px; margin-top:4px">End Time:</div>
                            <input class="date" style="width:100px;" name="EndTime" type="text" maxlength="10" id="EndTime" value="" onkeypress="return false" />
                        </div>
                        <div class="txt01">
                            Location:
                        </div>
                        <input name="Location" type="text" value="" id="Location" style="width:300px;" />
                        <br><br>
                        <input class="btn btngreen" style="float:right;" type="submit" name="Submit" value="Submit">
                    </div>
                </form>
            ';
        } else {
            extract($_POST);
            $StartTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['StartTime']);
            $StartDate = trim($_POST['StartDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['StartDate']." ".$StartTime)) : "0000-00-00 00:00:00";
            $EndTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
            $EndDate = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";
            $Events = new Brigade_Db_Table_Events();
            $SiteEvents = new Brigade_Db_Table_EventSites();
            // add event record first
            $EventId = $Events->AddEvent(array(
                'Title' => $Title,
                'EventText' => $EventText,
                'Link' => $Location,
                'StartDate' => $StartDate,
                'EndDate' => $EndDate,
                'SiteId' => $SiteId
            ));

            // log the site activity
            $SiteActivities = new Brigade_Db_Table_SiteActivities();
            $SiteActivities->addSiteActivity(array(
                'SiteId' => $SiteId,
                'ActivityType' => 'Events',
                'CreatedBy' => $UserId,
                'ActivityDate' => date('Y-m-d H:i:s'),
                'Link' => '/event/'.$EventId,
        'Details' => $EventId
            ));
            echo "Event has been successfully added.";
        }
    }

    public function updateeventAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (isset($_REQUEST['form']) && isset($_REQUEST['EventId'])) {
            $Events = new Brigade_Db_Table_Events();
            $eventInfo = $Events->loadInfo($_REQUEST['EventId']);
            echo '
                <script>
                    $(function() {
                        $("#StartDate").datepicker({ changeMonth: true, changeYear: true });
                        $("#StartTime").calendricalTime();
                        $("#EndDate").datepicker({ changeMonth: true, changeYear: true });
                        $("#EndTime").calendricalTime();
                    });
                </script>
                <form id="edit-form" onsubmit="return updateEvent()">
                    <div style="width:675px;">
                        <h2>Edit Event</h2>
                        <div id="message" style="display:none; border:2px solid silver; color:red; padding:5px;"></div>
                        <div class="txt01">
                            Title:
                        </div>
                        <input name="Title" type="text" value="'.$eventInfo['Title'].'" id="Title" style="width:675px;" />
                        <div class="txt01">
                            Details (no html formatting):
                        </div>
                        <textarea name="EventText" rows="2" cols="50" id="EventText" style="height:182px;width:675px;">'.$eventInfo['EventText'].'</textarea>
                        <div class="txt01">
                            When:
                        </div>
                        <div class="date" style="margin-top:3px; width:100%">
                            <div style="width:70px; display:inline; margin-top:4px">Start Date:</div>
                            <input style="margin-left:2px; cursor: pointer; width:75px;" class="text smaller" name="StartDate" type="text" maxlength="10" id="StartDate" value="'.(date('m/d/Y', strtotime($eventInfo['StartDate']))).'" />
                            <span style="color:Red;vertical-align:top;">*</span>
                            <div style="width:70px; display:inline; margin-left:10px; margin-top:4px">End Date:</div>
                            <input style="cursor: pointer; width:75px; margin-left:2px" class="text smaller" name="EndDate" type="text" maxlength="10" id="EndDate" value="'.(date('m/d/Y', strtotime($eventInfo['EndDate']))).'" />
                            <span style="color:Red;vertical-align:top;">*</span>
                        </div>
                        <div class="date" style="margin-top:3px; width:100%">
                            <div style="width:70px; display:inline; margin-top:4px">Start Time:</div>
                            <input style="width:100px;" class="date" name="StartTime" type="text" maxlength="10" id="StartTime" value="'.date('g:ia', strtotime($eventInfo['StartDate'])).'" onkeypress="return false" />
                            <div style="width:70px; display:inline; margin-left:18px; margin-top:4px">End Time:</div>
                            <input class="date" style="width:100px;" name="EndTime" type="text" maxlength="10" id="EndTime" value="'.date('g:ia', strtotime($eventInfo['EndDate'])).'" onkeypress="return false" />
                        </div>
                        <div class="txt01">
                            Location:
                        </div>
                        <input name="Location" type="text" value="'.$eventInfo['Link'].'" id="Location" style="width:300px;" />
                        <input type="hidden" id="EventId" name="EventId" value="'.$eventInfo['EventId'].'" />
                        <br><br>
                        '.(isset($_REQUEST['close_btn']) ? '<input type="button" class="btn btngreen" value="Close" onclick="togglePopup(\'\')" />&nbsp;&nbsp;' : '').'
                        <input class="btn btngreen" type="submit" name="Submit" value="Submit">
                    </div>
                </form>
            ';
        } else {
            extract($_POST);
            $StartTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['StartTime']);
            $StartDate = trim($_POST['StartDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['StartDate']." ".$StartTime)) : "0000-00-00 00:00:00";
            $EndTime = str_replace(array('am', 'pm'), array(' am', ' pm'), $_POST['EndTime']);
            $EndDate = trim($_POST['EndDate']) != "" ? date('Y-m-d H:i:s', strtotime($_POST['EndDate']." ".$EndTime)) : "0000-00-00 00:00:00";
            $Events = new Brigade_Db_Table_Events();
            $Events->updateEvent($EventId, array(
                'Title' => $Title,
                'EventText' => $EventText,
                'Link' => $Location,
                'StartDate' => $StartDate,
                'EndDate' => $EndDate,
                'ModifiedOn' => date('Y-m-d H:i:s'),
                'ModifiedBy' => $_SESSION['UserId']
            ));
            echo "Event has been successfully updated.";
        }
    }

    public function addticketAction()  {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        echo '<span class="new-ticket-'.$_POST['ctr'].'">
            <li class="field-label tickets">&nbsp;</li>
            <li class="field-input tickets">
                <strong style="color: #669933">Ticket #'.$_POST['ctr'].'</strong> - <a href="javascript:;" onclick="removeTicket('.$_POST['ctr'].')">Remove Ticket</a>
            </li>
            <div class="clear"></div>
            <li class="field-label tickets"><span style="color:#F00;font-size:16px;font-weight:bold;margin-top:4px;">* </span>Ticket Name?</li>
            <li class="field-input tickets">
                <input class="input ticket-name" type="text" id="TicketName-'.$_POST['ctr'].'" name="TicketName['.$_POST['ctr'].']" value="" />
            </li>
            <div class="clear"></div>
            <li class="field-label tickets">Ticket Description?</li>
            <li class="field-input tickets">
                <textarea class="input ticket-desc" type="text" id="TicketDescription-'.$_POST['ctr'].'" name="TicketDescription['.$_POST['ctr'].']" cols="20" rows="3"></textarea>
            </li>
            <div class="clear"></div>
            <li class="field-label tickets"><span style="color:#F00;font-size:16px;font-weight:bold;margin-top:4px;">* </span>Ticket Price?</li>
            <li class="field-input tickets">
                <input class="input ticket-price" type="text" id="TicketPrice-'.$_POST['ctr'].'" name="TicketPrice['.$_POST['ctr'].']" value="" />
            </li>
            <div class="clear"></div>
            <li class="field-label tickets">&nbsp;</li>
            <li class="field-input tickets">
                <a href="javascript:;" onclick="$(\'.adv-opts-'.$_POST['ctr'].'\').toggle()">Advanced Options</a>
            </li>
            <div class="clear"></div>
            <li class="field-label adv-opts adv-opts-'.$_POST['ctr'].'">&nbsp;&nbsp;Limit ticket quantity:</li>
            <li class="field-input adv-opts adv-opts-'.$_POST['ctr'].'">
                <input class="input ticket-qty" type="text" id="TicketQuantity-'.$_POST['ctr'].'" name="TicketQuantity['.$_POST['ctr'].']" value="" />
            </li>
            <div class="clear"></div>
            <li class="field-label adv-opts adv-opts-'.$_POST['ctr'].'">&nbsp;&nbsp;Limit ticket availability:</li>
            <li class="field-input adv-opts adv-opts-'.$_POST['ctr'].'">
                <input style="padding: 4px" class="text smaller cal-date ticket-dates" id="TicketStartDate-'.$_POST['ctr'].'" name="TicketStartDate['.$_POST['ctr'].']" type="text" maxlength="10" value="" />
                &nbsp;thru&nbsp;
                <input style="padding: 4px" class="text smaller cal-date ticket-dates" id="TicketEndDate-'.$_POST['ctr'].'" name="TicketEndDate['.$_POST['ctr'].']" type="text" maxlength="10" value="" />
            </li>
            <div class="clear"></div>
            <script>$(function() { $(".cal-date").datepicker({ changeMonth: true, changeYear: true }); })</script>
            </span>
        ';
    }

    public function addticket2Action() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        echo '
            <div style="margin-bottom:5px;" class="tickets new-ticket-'.$_POST['ctr'].'">
                <input class="input ticket-name" type="hidden" name="NewTicketId['.$_POST['ctr'].']" value="'.$_POST['ctr'].'" />
                <strong style="color: #669933">Ticket #'.$_POST['ctr'].'</strong> - <a href="javascript:;" onclick="removeTicket('.$_POST['ctr'].')">Remove Ticket</a><br>
                <div style="float: left; width: 120px; margin-bottom: 3px">Ticket Name:</div>
                <div style="float: left; width: 250px; margin-bottom: 3px">
                    <input class="input ticket-name" type="text" name="NewTicketName['.$_POST['ctr'].']" value="" />
                </div>
                <div style="float: left; width: 120px; margin-bottom: 3px">Ticket Description:</div>
                <div style="float: left; width: 250px; margin-bottom: 3px">
                    <textarea class="input ticket-desc" type="text" name="NewTicketDescription['.$_POST['ctr'].']" cols="20" rows="2"></textarea>
                </div>
                <div style="float: left; width: 120px; margin-bottom: 3px">Ticket Price:</div>
                <div style="float: left; width: 250px; margin-bottom: 3px">
                    <input class="input ticket-price" type="text" name="NewTicketPrice['.$_POST['ctr'].']" value="" />
                </div>
                <div style="float: left; width: 120px; margin-bottom: 3px">&nbsp;</div>
                <div style="float: left; width: 250px; margin-bottom: 3px">
                    <a href="javascript:;" onclick="$(\'.adv-opts-'.$_POST['ctr'].'\').toggle()">Advance Options</a>
                </div>
                <div style="float: left; width: 120px; margin-bottom: 3px" class="adv-opts adv-opts-'.$_POST['ctr'].'">Limit ticket quantity:</div>
                <div style="float: left; width: 250px; margin-bottom: 3px" class="adv-opts adv-opts-'.$_POST['ctr'].'">
                    <input class="input ticket-qty" type="text" name="NewTicketQuantity['.$_POST['ctr'].']" value="" />
                </div>
                <div style="float: left; width: 120px; margin-bottom: 3px" class="adv-opts adv-opts-'.$_POST['ctr'].'">Limit ticket quantity:</div>
                <div style="float: left; width: 250px; margin-bottom: 3px" class="adv-opts adv-opts-'.$_POST['ctr'].'">
                    <input style="padding: 4px; width: 80px" class="text smaller cal-date" name="NewTicketStartDate['.$_POST['ctr'].']" type="text" maxlength="10" value="" />
                    &nbsp;thru&nbsp;
                    <input style="padding: 4px; width: 80px" class="text smaller cal-date" name="NewTicketEndDate['.$_POST['ctr'].']" type="text" maxlength="10" value="" />
                </div>
            </div>
            <script>$(function() { $(".cal-date").datepicker({ changeMonth: true, changeYear: true }); })</script>
            ';
    }

    public function shareAction() {
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $Groups = new Brigade_Db_Table_Groups();
        $Events = new Brigade_Db_Table_Events();
        $Organizations = new Brigade_Db_Table_Organizations();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $siteInfo = $LookupTable->getSiteType(isset($parameters['UserId']) ? $parameters['UserId'] : $parameters['SiteId']);
        if ($siteInfo == 'group') {
            $this->view->data = $Groups->loadInfo1($parameters['SiteId']);
        } else if ($siteInfo == 'nonprofit') {
            $this->view->data = $Organizations->loadInfo($parameters['SiteId'], false);
        } else if ($siteInfo == 'profile') {
            $this->view->data = $Users->loadInfo($parameters['UserId']);
        }
        if(isset($parameters['newevent'])) {
            $this->view->newevent = true;
        }
        $this->view->eventInfo = $Events->loadInfo($parameters['EventId']);
        if ($_POST) {
            if (!$this->view->eventInfo['hasSharedSocialNetworks']) {
                $Events->updateEvent($parameters['EventId'], array('hasSharedSocialNetworks' => 1));
            }
            header("location: /".$this->view->data['URLName']."/events?EventId=".$this->view->eventInfo['EventId']);
        }
    }

    public function purchaseticketsAction() {
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $Events = new Brigade_Db_Table_Events();
        $Groups = new Brigade_Db_Table_Groups();
        $Tickets = new Brigade_Db_Table_EventTickets();
        $Organizations = new Brigade_Db_Table_Organizations();
        $this->view->is_loggedin = false;
        if (isset($_SESSION['UserId'])) {
            $this->view->userInfo = $Users->loadInfo($_SESSION['UserId']);
            $this->view->is_loggedin = true;
        }
        if ($parameters['Level'] == 'organization') {
            $this->view->data = $Organizations->loadInfo($parameters['NetworkId'], false);
            $this->view->level = 'organization';
        } else if ($parameters['Level'] == 'group') {
            $this->view->data = $Groups->loadInfo1($parameters['GroupId']);
            $this->view->level = 'group';
        } else if ($parameters['Level'] == 'user') {
            $this->view->data = $Users->loadInfo($parameters['UserId']);
            $this->view->level = 'user';
        }
        $this->view->eventInfo = $Events->loadInfo($parameters['EventId']);
        $this->view->tickets = $Tickets->getEventTickets($parameters['EventId']);
        $this->view->eventTickets = $Tickets;

        if($this->view->eventInfo['PaypalAccountId'] > 0) {
            $PaypalAccounts = new Brigade_Db_Table_PaypalAccounts();
            $this->view->paypal = $PaypalAccounts->loadInfo($this->view->eventInfo['PaypalAccountId']);
        }

        if (isset($_POST['__action']) && $_POST['__action'] == 'order tickets') {
            $tickets_order = $_POST['tickets'];
            foreach($tickets_order as $id => $order) {
                if ($order == 'other') {
                    $tickets_order[$id] = $_POST['other_ticket_amount'][$id];
                }
            }
            $this->view->tickets_order = $tickets_order;
        }
    }

    /**
     * Purchase tickets for RSVP events.
     *
     */
    public function purchaseticketsholderAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters = $this->_getAllParams();
        $event      = Event::get($parameters['EventId']);
        echo $event->id;
        foreach($parameters['name'] as $k => $toName) {
            $toEmail = $parameters['email'][$k];
            $user    = User::getByEmail($toEmail);

            // create ticket holder
            $ticketHolder           = new TicketHolder();
            $ticketHolder->eventId  = $event->id;
            $ticketHolder->fullName = $toName;
            $ticketHolder->email    = $toEmail;
            if ($user) {
                $ticketHolder->userId = $user->id;
            }
            $ticketHolder->save();
        }
    }

    public function newticketAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Users = new Brigade_Db_Table_Users();
        $Groups = new Brigade_Db_Table_Groups();
        $Organizations = new Brigade_Db_Table_Organizations();
        if ($_POST) {
            require_once('GoogleCheckout/googlecart.php');
            require_once('GoogleCheckout/googleitem.php');
            require_once('GoogleCheckout/googleshipping.php');
            require_once('GoogleCheckout/googletax.php');

            // add record in the tickets_purchased table
            $total_price = 0;
            for ($i = 1; $i <= $_POST['total_tickets']; $i++) {
                if ($_POST["item_quantity_$i"] > 0) {
                    $total_price += ($_POST["item_price_$i"] * $_POST["item_quantity_$i"]);
                }
            }

            // it means that tickets purchased are not FREE
            if ($total_price > 0) {
                if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'mandatory') {
                    $total_price = $total_price * (1 + ($_POST['PercentageFee']/100));
                } else if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'optional' && isset($_POST['coverTransFee']) && $_POST['coverTransFee']) {
                    $total_price = $total_price * (1 + ($_POST['PercentageFee']/100));
                }
            }
            if ($total_price > 0) {
                $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                $TicketPurchaseId = $TicketsPurchased->AddTicketPurchased(array(
                    'EventId' => $_POST['EventId'],
                    'GroupId' => isset($_POST['GroupId']) ? $_POST['GroupId'] : $_POST['NetworkId'],
                    'TotalAmount' => $total_price,
                    'BuyerUserId' => isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "", //the current logged in user
                    'OrderStatusId' => 0,
                    'TransactionSource' => 'Google Checkout',
                ));
            }

            // add record in the event_ticket_holders table, a verification code is created for each ticket which will be used for validation
            $TicketHolders = new Brigade_Db_Table_EventTicketHolders();
            $total_tickets_purchased = array();
            for ($i = 0; $i <= $_POST['ticket_holders']; $i++) {
                if (isset($_POST["ticketHolder_$i"])) {
                    for ($j = 1; $j <= count($_POST["ticketHolder_$i"]); $j++) {
                        if ($_POST["ticketHolder_$i"][$j] == 'User') { // the current logged in user is the ticket holder
                            $TicketHolders->AddTicketHolder(array(
                                'EventId' => $_POST['EventId'],
                                'TicketId' => $_POST["TicketId_$i"],
                                'UserId' => $_SESSION['UserId']
                            ));
                        } else { // it's someone else
                            $TicketHolders->AddTicketHolder(array(
                                'EventId' => $_POST['EventId'],
                                'TicketId' => $_POST["TicketId_$i"],
                                'FullName' => $_POST["ticketHolderName"][$i],
                                'Email' => $_POST["ticketHolderEmail"][$i],
                            ));
                        }
                        if (!isset($total_tickets_purchased[$_POST["TicketId_$i"]])) {
                            $total_tickets_purchased[$_POST["TicketId_$i"]] = 1;
                        } else {
                            $total_tickets_purchased[$_POST["TicketId_$i"]] += 1;
                        }
                    }
                }
            }
            // update the event tickets quantity
            print_r($total_tickets_purchased);
            $Tickets = new Brigade_Db_Table_EventTickets();
            foreach($total_tickets_purchased as $TicketId => $Quantity) {
                // load event ticket info, get the latest quantity and subtract it from the total tickets purchased
                $ticketInfo = $Tickets->loadInfo($TicketId);
                if (!empty($ticketInfo)) {
                    $tickets_left = $ticketInfo['Quantity'] - $Quantity;
                    $Tickets->updateTicket($TicketId, array('Quantity' => $tickets_left));
                }
            }
            // log the site activity
            $SiteActivities = new Brigade_Db_Table_SiteActivities();
            $SiteActivities->addSiteActivity(array(
                'SiteId' => isset($_POST['GroupId']) ? $_POST['GroupId'] : $_POST['NetworkId'],
                'ActivityType' => 'Purchased Ticket',
                'CreatedBy' => $_SESSION['UserId'],
                'ActivityDate' => date('Y-m-d H:i:s'),
                'Details' => $_POST['EventId'] // store the EventId
            ));

            if ($total_price > 0) {
                // get organization Google Checkout credentials
                if (isset($_POST['NetworkId'])) {
                    $networkinfo = $Organizations->loadInfo($_POST['NetworkId'], false);
                    $GC_account = $Organizations->getGoogleCheckoutAccount($networkinfo['NetworkId']);
                } else if (isset($_POST['GroupId'])) {
                    $groupInfo = $Groups->loadInfo1($_POST['GroupId']);
                    if($groupInfo['GoogleCheckoutAccountId'] == 0) {
                        $GC_account = $Organizations->getGoogleCheckoutAccount($networkinfo['NetworkId']);
                    } else {
                        $GC_account = $Groups->getGoogleCheckoutAccount($networkinfo['GroupId']);
                    }
                } else if (isset($_POST['UserId'])) {
                    $userInfo = $Users->loadInfo($_POST['UserId']);
                    $GC_account = $Users->getGoogleCheckoutAccount($_POST['UserId']);
                }
                $merchantID = $GC_account['GoogleMerchantID'];
                $merchantKey = $GC_account['GoogleMerchantKey'];
                $currency = $GC_account['CurrencyType'];
                if ($this->server_type == 'sandbox') {
                    $merchantID = '844523113325635';
                    $merchantKey = 'zY47NYBKzPVzmhcLLWHMNA';
                }

                $cart = new GoogleCart($merchantID, $merchantKey, $this->server_type, $currency);
                for ($i = 1; $i <= $_POST['total_tickets']; $i++) {
                    if ($_POST["item_quantity_$i"] > 0) {
                        if ($_POST["item_price_$i"] > 0) {
                            if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'mandatory') {
                                $_POST["item_quantity_$i"] += $_POST["item_quantity_$i"] * (1 + ($_POST['PercentageFee']/100));
                            } else if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'optional' && isset($_POST['coverTransFee']) && $_POST['coverTransFee']) {
                                $_POST["item_quantity_$i"] += $_POST["item_quantity_$i"] * (1 + ($_POST['PercentageFee']/100));
                            }
                        }
                        $item = new GoogleItem(
                            $_POST["item_name_$i"], // Item name
                            $_POST["item_description_$i"], // Item description
                            $_POST["item_quantity_$i"], // Quantity
                            $_POST["item_price_$i"] // Ticket Price
                        );
                        $item->SetMerchantItemId($TicketPurchaseId);
                        $cart->AddItem($item);
                    }
                }
                // this will served as the reference ID to the project_donations table [ProjectDonationId]
                // Specify the <edit-cart-url>
                $cart->SetEditCartUrl("http://www.empowered.org/".$_POST['editCartURI']);
                // Specify the <continue-shoppingcart-url>
                $cart->SetContinueShoppingUrl("http://www.empowered.org/".$groupInfo['URLName']."/events?EventId=".$_POST['EventId']);
                list($status, $error) = $cart->CheckoutServer2Server('');
                // if i reach this point, something was wrong
                echo "An error had ocurred: <br />HTTP Status: " . $status. ":";
                echo "<br />Error message:<br />";
                echo $error;
            } else {
                if (isset($_POST['NetworkId'])) {
                    $siteInfo = $Organizations->loadInfo($_POST['NetworkId'], false);
                } else {
                    $siteInfo = $Groups->loadInfo1($_POST['GroupId']);
                }
                header("location: /".$siteInfo['URLName']."/events?EventId=".$_POST['EventId']);
            }
        }
    }

    public function chainedpaymentAction(){  //actually for parallel payments
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Groups = new Brigade_Db_Table_Groups();
        $Organizations = new Brigade_Db_Table_Organizations();
        if($_POST) {
            $Paypal = new Paypal_API($this->server_type);
            $LookupTable = new Brigade_Db_Table_LookupTable();
            $currencyCode = $_POST['CurrencyCode'];
            $groupPaypal = $_POST['PaypalEmail'];
            $actionType = "CREATE";
            $feesPayer = "";
            // get the total cost
            $total_price = 0;
            for ($i = 1; $i <= $_POST['total_tickets']; $i++) {
                if (isset($_POST["item_quantity_$i"]) && $_POST["item_quantity_$i"] > 0) {
                    $total_price += ($_POST["item_price_$i"] * $_POST["item_quantity_$i"]);
                }
            }
            if($total_price > 0) {
                if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'mandatory') {
                    $groupAmount = number_format($total_price * (1 + ($_POST['PercentageFee']/100)));
                } else if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'optional' && isset($_POST['coverTransFee']) && $_POST['coverTransFee']) {
                    $groupAmount = number_format($total_price * (1 + ($_POST['PercentageFee']/100)));
                } else {
                    $groupAmount = number_format($total_price, 2);
                }
                if (isset($_POST['supportEmpowered']) && $_POST['supportEmpowered']) {
                    $empoweredAmount = number_format($total_price * (0.015), 2);
                    if ($this->server_type == "Production") {
                        $receiverEmailArray = array("paypal@empowered.org", "$groupPaypal");
                    } else {
                        $receiverEmailArray = array("oconno_1301866574_biz@gmail.com", "oconno_1301865577_biz@gmail.com");
                    }
                    $receiverPrimaryArray = array();
                    $invoiceid1 = strtotime(date('Y-m-d H:i:s')) + (int)$_POST['EventId'] + 1;
                    $invoiceid2 = strtotime(date('Y-m-d H:i:s')) + (int)$_POST['EventId'] + 2;
                    $receiverInvoiceIdArray = array("$invoiceid1", "$invoiceid2");
                    $receiverAmountArray = array("$empoweredAmount", "$groupAmount");
                } else {
                    if ($this->server_type == "Production") {
                        $receiverEmailArray = array("$groupPaypal");
                    } else {
                        $receiverEmailArray = array("oconno_1301865577_biz@gmail.com");
                    }
                    $receiverPrimaryArray = array();
                    $invoiceid1 = strtotime(date('Y-m-d H:i:s')) + (int)$_POST['EventId'];
                    $receiverInvoiceIdArray = array("$invoiceid1");
                    $receiverAmountArray = array("$groupAmount");
                }
                if ($this->server_type == "Production") {
                    $ipnNotificationUrl = "http://www.empowered.org/paypalipn"; //Response Handler
                    $cancelUrl = "http://www.empowered.org/event/unsuccessful?EventId=".$_POST['EventId'];
                    $returnUrl = "http://www.empowered.org/event/successful?EventId=".$_POST['EventId'];
                } else {
                    $ipnNotificationUrl = "http://dev.empowered.org/paypalipn"; //Response Handler
                    $cancelUrl = "http://dev.empowered.org/event/unsuccessful?EventId=".$_POST['EventId'];
                    $returnUrl = "http://dev.empowered.org/event/successful?EventId=".$_POST['EventId'];
                }
                $memo = "";
                $pin = "";
                $pinType = "NOT_REQUIRED";
                $preapprovalKey = "";
                $reverseAllParallelPaymentsOnError = true;
                $senderEmail = "";
                $dateOfMonth = "";
                $dayOfWeek = "";
                $maxNumberOfPaymentsPerPeriod = "";
                $trackingId = $Paypal->generateTrackingID();    // generateTrackingID function is found in paypalplatform.php

                $resArray = $Paypal->CallPay($actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray, $receiverPrimaryArray, $receiverInvoiceIdArray, $feesPayer, $ipnNotificationUrl, $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $senderEmail, $trackingId);

                $ack = strtoupper($resArray["responseEnvelope.ack"]);
                if($ack=="SUCCESS") {
                    if ("" == $preapprovalKey) {
                        // add record in the tickets_purchased table
                        $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                        $TicketPurchaseId = $TicketsPurchased->AddTicketPurchased(array(
                            'EventId' => $_POST['EventId'],
                            'GroupId' => isset($_POST['GroupId']) ? $_POST['GroupId'] : $_SESSION['UserId'],
                            'TotalAmount' => $total_price,
                            'BuyerUserId' => isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "", //the current logged in user
                            'OrderStatusId' => 0,
                            'TransactionId' => $resArray['payKey'],
                            'TransactionSource' => "Paypal",
                        ));

                        // add record in the event_ticket_holders table, a verification code is created for each ticket which will be used for validation
                        $TicketHolders = new Brigade_Db_Table_EventTicketHolders();
                        $total_tickets_purchased = array();
                        for ($i = 0; $i <= $_POST['ticket_holders']; $i++) {
                            if (isset($_POST["ticketHolder_$i"])) {
                                for ($j = 1; $j <= count($_POST["ticketHolder_$i"]); $j++) {
                                    if ($_POST["ticketHolder_$i"][$j] == 'User') { // the current logged in user is the ticket holder
                                        $TicketHolders->AddTicketHolder(array(
                                            'EventId' => $_POST['EventId'],
                                            'TicketId' => $_POST["TicketId_$i"],
                                            'UserId' => $_SESSION['UserId']
                                        ));
                                    } else { // it's someone else
                                        $TicketHolders->AddTicketHolder(array(
                                            'EventId' => $_POST['EventId'],
                                            'TicketId' => $_POST["TicketId_$i"],
                                            'FullName' => $_POST["ticketHolderName"][$i],
                                            'Email' => $_POST["ticketHolderEmail"][$i],
                                        ));
                                    }
                                }
                            }
                        }
                        // update the event tickets quantity
                        $Tickets = new Brigade_Db_Table_EventTickets();
                        foreach($total_tickets_purchased as $TicketId => $Quantity) {
                            // load event ticket info, get the latest quantity and subtract it from the total tickets purchased
                            $ticketInfo = $Tickets->loadInfo($TicketId);
                            if (!empty($ticketInfo)) {
                                $tickets_left = $ticketInfo['Quantity'] - $Quantity;
                                $Tickets->updateTicket($TicketId, array('Quantity' => $tickets_left));
                            }
                        }

                        // log the site activity
                        $SiteActivities = new Brigade_Db_Table_SiteActivities();
                        $SiteActivities->addSiteActivity(array(
                            'SiteId' => isset($_POST['GroupId']) ? $_POST['GroupId'] : $_POST['NetworkId'],
                            'ActivityType' => 'Purchased Ticket',
                            'CreatedBy' => $_SESSION['UserId'],
                            'ActivityDate' => date('Y-m-d H:i:s'),
                            'Details' => $_POST['EventId'] // store the EventId
                        ));

                        //echo "POSTED FROM VIEW:<br>";
                        //print_r($_POST);
                        //echo "<br><br>";

                        if($this->server_type == "Production") {
                            $PaypalURL = 'https://www.paypal.com/webapps/adaptivepayment/flow/pay?expType='.$_POST['expType'].'&paykey='.$resArray['payKey'];
                        } else {
                            $PaypalURL = 'https://www.sandbox.paypal.com/webapps/adaptivepayment/flow/pay?expType='.$_POST['expType'].'&paykey='.$resArray['payKey'];
                        }
                        header('location: '.$PaypalURL);
                    } else {
                        $payKey = urldecode($resArray["payKey"]);
                        // paymentExecStatus is the status of the payment
                        $paymentExecStatus = urldecode($resArray["paymentExecStatus"]);
                    }
                } else {
                    $ErrorCode = urldecode($resArray["error(0).errorId"]);
                    $ErrorMsg = urldecode($resArray["error(0).message"]);
                    $ErrorDomain = urldecode($resArray["error(0).domain"]);
                    $ErrorSeverity = urldecode($resArray["error(0).severity"]);
                    $ErrorCategory = urldecode($resArray["error(0).category"]);

                    echo "Pay API call failed. ";
                    echo "<br>Detailed Error Message: " . $ErrorMsg;
                    echo "<br>Error Code: " . $ErrorCode;
                    echo "<br>Error Severity: " . $ErrorSeverity;
                    echo "<br>Error Domain: " . $ErrorDomain;
                    echo "<br>Error Category: " . $ErrorCategory;
                }
            } else {
                // add record in the event_ticket_holders table, a verification code is created for each ticket which will be used for validation
                $TicketHolders = new Brigade_Db_Table_EventTicketHolders();
                $total_tickets_purchased = array();
                for ($i = 0; $i <= $_POST['ticket_holders']; $i++) {
                    if (isset($_POST["ticketHolder_$i"])) {
                        for ($j = 1; $j <= count($_POST["ticketHolder_$i"]); $j++) {
                            if ($_POST["ticketHolder_$i"][$j] == 'User') { // the current logged in user is the ticket holder
                                $TicketHolders->AddTicketHolder(array(
                                    'EventId' => $_POST['EventId'],
                                    'TicketId' => $_POST["TicketId_$i"],
                                    'UserId' => $_SESSION['UserId']
                                ));
                            } else { // it's someone else
                                $TicketHolders->AddTicketHolder(array(
                                    'EventId' => $_POST['EventId'],
                                    'TicketId' => $_POST["TicketId_$i"],
                                    'FullName' => $_POST["ticketHolderName"][$i],
                                    'Email' => $_POST["ticketHolderEmail"][$i],
                                ));
                            }
                        }
                    }
                }
                // update the event tickets quantity
                $Tickets = new Brigade_Db_Table_EventTickets();
                foreach($total_tickets_purchased as $TicketId => $Quantity) {
                    // load event ticket info, get the latest quantity and subtract it from the total tickets purchased
                    $ticketInfo = $Tickets->loadInfo($TicketId);
                    if (!empty($ticketInfo)) {
                        $tickets_left = $ticketInfo['Quantity'] - $Quantity;
                        $Tickets->updateTicket($TicketId, array('Quantity' => $tickets_left));
                    }
                }

                if (isset($_POST['NetworkId'])) {
                    $siteInfo = $Organizations->loadInfo($_POST['NetworkId'], false);
                } else {
                    $siteInfo = $Groups->loadInfo1($_POST['GroupId']);
                }
                header("location: /".$siteInfo['URLName']."/events?EventId=".$_POST['EventId']);
            }
        }
    }

    public function assignticketsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST['TicketId'] && $_POST['Quantity']) {
            $counter = 0;
            $Users = new Brigade_Db_Table_Users();
            $Groups = new Brigade_Db_Table_Groups();
            $Organizations = new Brigade_Db_Table_Organizations();
            $Tickets = new Brigade_Db_Table_EventTickets();
            if ($_POST['level'] == 'group') {
                $data = $Groups->loadInfo1($_POST['SiteId']);
            } else if ($_POST['level'] == 'organization') {
                $data = $Organizations->loadInfo($_POST['SiteId'], false);
            } else if ($_POST['level'] == 'user') {
                $data = $Users->loadInfo($_POST['SiteId']);
            }
            $info = $Tickets->loadInfo($_POST['TicketId']);
            $userInfo = $Users->loadInfo($_SESSION['UserId']);
            for ($ctr = 1; $ctr <= $_POST['Quantity']; $ctr++) {
                echo '
                <input type="hidden" value="'.$info['Price'].'" class="ticket-prices" />
                <input type="hidden" name="TicketId_'.$counter.'" value="'.$_POST['TicketId'].'" />
                <div style="margin-bottom: 5px; font-size: 13px">
                    1. '.$info['Description'].'('.$data['Currency'].$info['Price'].')<br>
                    <div style="margin-left: 15px">
                        '.(isset($_SESSION['UserId']) ? '
                        <input type="radio" name="ticketHolder_'.$counter.'['.$ctr.']" value="User" onclick="$(\'.others-'.$counter.'-'.$ctr.'\').hide()" /> This is for me ('.stripslashes($userInfo['FullName']).' <'.stripslashes($userInfo['Email']).'>)<br>' : '').'
                        <input type="radio" name="ticketHolder_'.$counter.'['.$ctr.']" value="Others" onclick="$(\'.others-'.$counter.'-'.$ctr.'\').show()" /> This is for someone else<br>
                        <input class="others-'.$counter.'-'.$ctr.'" style="margin: 3px 0 3px 25px; '.(isset($_SESSION['UserId']) ? 'display:none' : '').'" type="text" name="ticketHolderName['.$counter.']" value="First & Last Name" onfocus="if (this.value == \'First & Last Name\') { this.value=\'\' }" onblur="if (this.value == \'\') { this.value=\'First & Last Name\' }" /><br>
                        <input class="others-'.$counter.'-'.$ctr.'" style="margin: 3px 0 3px 25px; '.(isset($_SESSION['UserId']) ? 'display:none' : '').'" type="text" name="ticketHolderEmail['.$counter.']" value="Email" onfocus="if (this.value == \'Email\') { this.value=\'\' }" onblur="if (this.value == \'\') { this.value=\'Email\' }" />
                    </div>
                </div>';
                $counter++;
            }
            if ($_POST['Quantity'] == 0) {
                echo "";
            }
            //echo '<script>$("#total-cost").html("'.($info['Price'] * $_POST['Quantity']).'")</script>';
        }
    }

    public function ticketholdersAction() {
        $parameters = $this->_getAllParams();
        $Users = new Brigade_Db_Table_Users();
        $Groups = new Brigade_Db_Table_Groups();
        $Events = new Brigade_Db_Table_Events();
        $TicketHolders = new Brigade_Db_Table_EventTicketHolders();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $Organizations = new Brigade_Db_Table_Organizations();
        if (isset($parameters['SiteId']) && isset($parameters['EventId'])) {
            $this->view->eventInfo = $Events->loadInfo($parameters['EventId']);
            $this->view->ticket_holders = $TicketHolders->getTicketHoldersByEvent($parameters['EventId']);
            if ($parameters['Level'] == 'group') {
                $this->view->progOrg = $Groups->loadProgOrg($parameters['SiteId']);
                $this->view->data = $Groups->loadInfo1($parameters['SiteId']);
                $this->view->level = 'group';
            } else if ($parameters['Level'] == 'organization') {
                $this->view->data = $Organizations->loadInfo($parameters['SiteId'], false);
                $this->view->level = 'organization';
            } else if ($parameters['Level'] == 'user') {
                $this->view->data = $Users->loadInfo($parameters['SiteId']);
                $this->view->header_title = $this->view->eventInfo['Title'];
                $this->view->level = 'user';
            }
            $this->view->users_class = new Brigade_Db_Table_Users();
            $this->view->sitemedia = new Brigade_Db_Table_Media();
        }
    }

    public function deleteticketAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $EventTickets = new Brigade_Db_Table_EventTickets();
        if ($_POST && isset($_POST['TicketId'])) {
            $EventTickets->deleteTicket($_POST['TicketId']);
            echo "Ticket has been successfully deleted";
        }
    }

}
