<?php
/**
 * DonationController - The "donations" controller class
 *
 * @author
 * @version
 */
require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/FundraisingCampaign.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/Paypal.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/Country.php';
require_once 'Brigade/Db/Table/State.php';
require_once 'Brigade/Db/Table/GiftAid.php';
require_once 'Paypal/Paypal.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';
require_once 'Brigade/Db/Table/ReceiptMessages.php';

require_once 'BaseController.php';
require_once 'Activity.php';
require_once 'Project.php';
require_once 'BluePay/BluePayment.php';
require_once 'Mailer.php';

class DonationController extends BaseController {
    protected $_http;
    protected $checkout_url;
    protected $merchantID;
    protected $merchantkey;
    protected $currency = "USD";
    protected $server_type = ''; // change this to anything other than 'sandbox' to go live
    protected $editcarturl;
    protected $returncarturl;

    public function init() {
        parent::init();

        if (isset($_SESSION['UserId'])) {
            $UserRoles = new Brigade_Db_Table_UserRoles();
            $parameters = $this->_getAllParams();

            // I believe that everything dealing with
            // parameters['GroupId'] && its checks can
            // be removed. verify when there is time to test

            if (isset($parameters['ProjectId'])) {
                $Brigades = new Brigade_Db_Table_Brigades();
                $project = $Brigades->loadInfo($parameters['ProjectId']);
                $GroupId = $project['GroupId'];
            } else if (isset($parameters['GroupId'])) {
                $GroupId = $parameters['GroupId'];
            }
            if (isset($parameters['GroupId'])) {
                $role = $UserRoles->getUserRole($_SESSION['UserId']);
                $hasAccess = $UserRoles->UserHasAccess($GroupId, $_SESSION['UserId'], 'group');
                if (($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
                    $this->view->isAdmin = true;
                }
                $Groups = new Brigade_Db_Table_Groups();
                $groupInfo = $Groups->loadInfo($parameters['GroupId']);
                if(isset($groupInfo['NetworkId'])) {
                  $hasNetworkAccess = $UserRoles->hasAccessOnNetwork($groupInfo['NetworkId'], $_SESSION['UserId']);
                  if($hasNetworkAccess) {
                    $this->view->isNetworkAdmin = true;
                  }
                }
            } else if (isset($parameters['ProjectId'])) {
                $role = $UserRoles->getUserRole($_SESSION['UserId']);
                $hasAccess = $UserRoles->UserHasAccess($parameters['ProjectId'], $_SESSION['UserId'], 'brigade');
                if (($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
                    $this->view->isAdmin = true;
                }
                $Brigades = new Brigade_Db_Table_Brigades();
                $brigadeInfo = $Brigades->loadInfo($parameters['ProjectId']);
                if(isset($brigadeInfo['NetworkId'])) {
                  $hasNetworkAccess = $UserRoles->hasAccessOnNetwork($brigadeInfo['NetworkId'], $_SESSION['UserId']);
                  if($hasNetworkAccess) {
                    $this->view->isNetworkAdmin = true;
                  }
                }
            }

        }
    }

    /**
     * The default action - show the donation page
     */
    public function indexAction() {
        $parameters = $this->_getAllParams();
        $Brigades   = new Brigade_Db_Table_Brigades();
        $Groups     = new Brigade_Db_Table_Groups();

        $project = $this->view->project = Project::get($parameters['ProjectId']);
        if(!empty($project->organizationId)) {
            $organization = $this->view->organization = $project->organization;

            $Media = new Brigade_Db_Table_Media();
            $this->view->siteBanner = false;
            if (!empty($organization->bannerMediaId)) {
                $siteBanner = $Media->getSiteMediaById($organization->bannerMediaId);
                $this->view->siteBanner = $siteBanner;
                $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
            }
            if(isset($organization->nonProfitId)) {
                $this->view->nonProfitId = $organization->nonProfitId;
                $this->view->nonProfit   = $organization->name;
            }
        }
        if(!empty($project->programId)) {
            $program = $project->program;
            $this->view->enableSupporter = false;
            if ($program->canSupport($this->sessionUser)) {
                $this->view->enableSupporter = true;
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
            /*&& $project->stripeId == 0*/ && !empty($project->groupId)
        ) {
            $this->view->error = true;
        }
        if ((!empty($project->organizationId) && $project->organization->bluePayId > 0)
            || ($project->bluePayId && $project->bluePayId > 0)
            && BluePay::isActive
        ) {
            $enableEcheck = false;
            if (strtotime($project->startDate." -13 days") > time()) {
                $enableEcheck = true;
            }
            $this->view->enableEcheck = $enableEcheck;
            $this->view->render('donation/bluepayform_cc.phtml');
            $this->view->render('donation/bluepay.phtml');
        /*} elseif ($project->stripeId && $project->stripeId > 0) {
            $this->view->render('donation/stripe.phtml');*/
        } elseif ($project->googleId) {
            $GoogleCheckoutAccounts = new Brigade_Db_Table_GoogleCheckoutAccounts();
            $gc_account = $GoogleCheckoutAccounts->loadInfo($project->googleId);
            if ($this->server_type == 'sandbox') {
                $this->view->merchant_id = '844523113325635';
            } else {
                $this->view->merchant_id = isset($gc_account['GoogleMerchantID']) ? $gc_account['GoogleMerchantID'] : "";
            }
            $this->view->render('donation/googlecheckout.phtml');
        } else if($project->paypalId) {
            $Paypal = new Brigade_Db_Table_Paypal();
            $this->view->paypal = $Paypal->loadInfo($project->paypalId);
            $this->view->render('donation/paypal.phtml');
        }
        $this->view->UserId = '';
        if (isset($parameters['UserId'])) {
            $this->view->UserId = $parameters['UserId'];
        }
        if(isset($_SESSION['UserId'])) {
            $Users = new Brigade_Db_Table_Users();
            $this->view->donorsName = $Users->getFullNameById($_SESSION['UserId']);
        }

        //gift aid
        if (!empty($project->organizationId) && $project->organization->hasGiftAid()) {
            $this->view->render('donation/giftaid.phtml');
        }
    }

    public function getstatelistAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_REQUEST['countryID']) {
            $State = new Brigade_Db_Table_State();
            if ($_REQUEST['countryID'] == "CA" || $_REQUEST['countryID'] == "US") {
                $rows = $State->listByCountry($_REQUEST['countryID']);
                echo "<select id='state' name='state' style='width:240px'>";
                foreach ($rows as $row) {
                    echo "<option value='".$row['stateShort']."'>".$row['stateLong']."</option>";
                }
                echo "</select>";
            } else {
                echo '<input type="text" size="30" id="state" name="state" value="" />';
            }
        }
    }

    public function unsuccessfulAction() {
        if(isset($_REQUEST['ProjectId'])) {
            $this->view->project = Project::get($_REQUEST['ProjectId']);
        }
    }

    public function successfulAction() {
        if(isset($_REQUEST['ProjectId'])) {
            $this->view->ProjectId = $_REQUEST['ProjectId'];
            $Brigades = new Brigade_Db_Table_Brigades();
            $this->view->project = $Brigades->loadInfo($this->view->ProjectId);
        }
    }

    public function refundAction() { }


    public function newdonationAction() {
        if ($_POST) {
            require_once('GoogleCheckout/googlecart.php');
            require_once('GoogleCheckout/googleitem.php');
            require_once('GoogleCheckout/googleshipping.php');
            require_once('GoogleCheckout/googletax.php');

            // get post vars
            $ProjectId = $_POST['ProjectId'];
            $VolunteerId = $_POST['VolunteerId'];
            $item_name = stripslashes($_POST['item_name_1']);
            $item_description = $_POST['item_description_1'];
            $item_quantity = $_POST['item_quantity_1'];
            $item_price = $_POST['item_price_1'];
            $comments = $_POST['DonationComments'];
            $editURI = $_POST['editCartURI'];
            $pURLName = $_POST['pURLName'];
            $isAnonymous = isset($_POST['isAnonymous']) ? 1 : 0;

            // save data to project_donations table
            $paidFee = false;
            $unaltered_price = $item_price;
            if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'mandatory') {
                $item_price = $item_price * (1 + ($_POST['PercentageFee']/100));
                $paidFee = true;
            } else if (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'optional' && isset($_POST['coverTransFee']) && $_POST['coverTransFee']) {
                $item_price = $item_price * (1 + ($_POST['PercentageFee']/100));
                $paidFee = true;
            }
            $Projects = new Brigade_Db_Table_Brigades();
            $projInfo = $Projects->loadInfoBasic($ProjectId);

            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $ProjectDonationId = $ProjectDonations->addProjectDonation(array(
                'ProjectId' => $ProjectId,
                'GroupId' => $projInfo['GroupId'],
                'ProgramId' => $projInfo['ProgramId'],
                'NetworkId' => $projInfo['NetworkId'],
                'VolunteerId' => $VolunteerId,
                'DonationAmount' => $unaltered_price,
                'DonorUserId' => isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "",
                'DonationComments' => $comments,
                'OrderStatusId' => 0,
                'TransactionSource' => "Google Checkout",
                'CreatedOn' => date('Y-m-d H:i:s'),
                'isAnonymous' => $isAnonymous,
                'PaidFees'     => $paidFee
            ));

            //gift aid for uk
            if (isset($ProjectId) && isset($_POST['giftAidId']) && $_POST['giftAidId'] > 0) {
                $prj = Project::get($ProjectId);
                if ($prj && !empty($prj->organizationId) && $prj->organization->hasGiftAid()) {
                    $GiftAid = new Brigade_Db_Table_GiftAid();
                    $GiftAid->editDeclaration($_POST['giftAidId'], array(
                        'project_donation_id' => $ProjectDonationId
                    ));
                }
            }

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
            //echo "$merchantID";
            if ($this->server_type == 'sandbox') {
                $merchantID = '844523113325635';
                $merchantKey = 'zY47NYBKzPVzmhcLLWHMNA';
            }


            $cart = new GoogleCart($merchantID, $merchantKey, $this->server_type, $currency);
            $item = new GoogleItem(
                $item_name, // Item name
                $item_description, // Item description
                $item_quantity, // Quantity
                $item_price // Donation Amount
            );
            $item->SetMerchantItemId($ProjectDonationId); // this will served as the reference ID to the project_donations table [ProjectDonationId]
            $item->SetEmailDigitalDelivery('true');
            $cart->AddItem($item);
            // Specify the <edit-cart-url>
            $cart->SetEditCartUrl("http://www.empowered.org/$editURI");
            // Specify the <continue-shoppingcart-url>
            $cart->SetContinueShoppingUrl("http://www.empowered.org/$pURLName");
            list($status, $error) = $cart->CheckoutServer2Server('');
            // if i reach this point, something was wrong
            echo "An error had ocurred: <br />HTTP Status: " . $status. ":";
            echo "<br />Error message:<br />";
            echo $error;
        }
    }
    public function manualentryAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        } else {
            if ($_POST) {
                // get post vars
                $ProjectId = $_POST['ProjectId'];
                $VolunteerId = $_POST['VolunteerId'];
                $DonationAmount = $_POST['DonationAmount'];
                $Comments = $_POST['DonationComments'];

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
                    'TransactionId' => 'MANUAL' . time(),
                    'DonationAmount' => $DonationAmount,
                    'DonorUserId' => $_SESSION['UserId'],
                    'DonationComments' => $Comments,
                    'OrderStatusId' => 2,
                    'SupporterName' => 'Organization Admin',
                    'SupporterEmail' => 'Organization Admin',
                    'Status' => 1,
                    'TransactionSource' => "Manual",
                    'CreatedOn' => date('Y-m-d H:i:s'),
                    'CreatedBy' => $_SESSION['UserId'],
                    'ModifiedOn' => date('Y-m-d H:i:s'),
                    'ModifiedBy' => $_SESSION['UserId']
                ));

                // log the site activity
                $SiteActivities = new Brigade_Db_Table_SiteActivities();
                $SiteActivities->addSiteActivity(array(
                    'SiteId' => $ProjectId,
                    'ActivityType' => 'User Donation',
                    'CreatedBy' => $_SESSION['UserId'],
                    'ActivityDate' => date('Y-m-d H:i:s'),
                    'Recipient' => $VolunteerId
                ));
                echo "Manual donation has been successfully processed.";
            }
        }
    }

    public function manageAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin){
            $this->_helper->redirector('badaccess', 'error');
        }
        if ($_POST) {
            $Volunteers = $Brigades = new Brigade_Db_Table_Volunteers();
            foreach($_POST['VolunteerId'] as $VolunteerId) {
                if (isset($_POST["newgoal_$VolunteerId"])
                    && $_POST["newgoal_$VolunteerId"] != ''
                    && $_POST["newgoal_$VolunteerId"] >= 0) {
                    $Volunteers->setDonationGoal($VolunteerId, $_POST["newgoal_$VolunteerId"]);
                }
            }
        }
        $parameters = $this->_getAllParams();
        $Brigades = new Brigade_Db_Table_Brigades();
        $Groups = new Brigade_Db_Table_Groups();
        $Programs = new Brigade_Db_Table_Programs();
        $Organizations = new Brigade_Db_Table_Organizations();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        $project  =  $this->view->project  =  Project::get($parameters['ProjectId']);
        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($project);

        if(!empty($project->organization->nonProfitId)) {
            $this->view->nonProfitId  = $project->organization->nonProfitId;
            $this->view->nonProfit    = $project->organization->name;
        }

        if(!empty($project->groupId)) {
            $group  =  $this->view->group  =  $project->group;
            $this->view->level = "group";

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');

        } else if(!empty($project->organizationId)) {

            $organization = $this->view->organization = $project->organization;

            $this->view->level = "organization";

            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');
        } else {
            //create header / tabs for personal activities?
            $this->view->header_title = $project->name;
            $this->view->level = "user";
        }

        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');

        $this->view->donations = $Brigades->getProjectVolunteerDonations($project->id);
        $this->view->deleted_member_donations = $Brigades->getProjectVolunteerDonations($project->id, true);
    }

    public function updategoalAction() {
        if (!$this->view->isAdmin){
            $this->_helper->redirector('badaccess', 'error');
        }
        if ($_POST) {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            $Volunteers = $Brigades = new Brigade_Db_Table_Volunteers();
            $Volunteers->setDonationGoal($_POST['VolunteerId'], $_POST['NewGoal']);
        }
    }

    public function generatereportAction() {
        if (!$this->view->isAdmin){
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProjectId'])) {
            $Brigades = new Brigade_Db_Table_Brigades();
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $donations = $Brigades->getDetailDonationReport($parameters['ProjectId'], '', '');
            $projectinfo = $Brigades->loadInfo($parameters['ProjectId']);
            $projectname = str_replace(' ', '-', $projectinfo['Name']." Donation Report.xls");
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$projectname");
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
                $line = stripslashes($Volunteer)."$TransactionId$DonationAmount$ModifiedOn$SupporterName$SupporterEmail$Status";
                $data .= trim($line)."\n";
            }
            print "$headers\n$data";
        }
    }

    public function pullreportAction() {
        if (!$this->view->isAdmin){
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProjectId'])) {
            $Brigades         = new Brigade_Db_Table_Brigades();
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();

            $donations = $Brigades->getDonationReport($parameters['ProjectId'], '', '',false);
            $project   = Project::get($parameters['ProjectId']);
            $projectname = str_replace(' ', '-', $project->urlName." Donation Report.xls");
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$projectname");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Volunteer', 'Amount Raised', 'Donation Goal');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            $totalRaised = 0;
            $totalGoal   = 0;
            foreach($donations as $donation) {
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
                $DonationGoal = $UserDonationGoal > $VolunteerMinimumGoal ? $UserDonationGoal : $VolunteerMinimumGoal;
                $line  = stripslashes($Volunteer)."$AmountRaised$DonationGoal";
                $data .= trim($line)."\n";

                $totalRaised += $AmountRaised;
                $totalGoal   += $DonationGoal;
            }
            $totalGoal   += $project->donationGoal;
            $totalRaised += $project->getGeneralDonations() + $project->getMembershipFunds();
            $line         = "General Donation Funds\t".$project->currency.number_format($project->getGeneralDonations())."\t".$project->currency.$project->donationGoal;
            $data        .= trim($line)."\n";
            $line         = "Membership Transfered\t".$project->currency.number_format($project->getMembershipFunds())."\t";
            $data        .= trim($line)."\n";
            $line         = "Total Raised\t".$project->currency.number_format($totalRaised)."\t".$project->currency.$totalGoal;
            $data        .= trim($line)."\n";
            print "$headers\n$data";
        }
    }

    public function chainedpaymentAction(){  //actually using parallel payments
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if($_POST) {
            $Paypal = new Paypal_API($this->server_type);
            $isRecurring = 'No'; // $_POST['isRecurring'];
            $ProjectId = $_POST['ProjectId'];
            $LookupTable = new Brigade_Db_Table_LookupTable();
            $currencyCode = $_POST['CurrencyCode'];
            $siteType = $LookupTable->getSiteType($_POST['ProjectId']);
            if ($siteType == 'project') {
                $DonationAmount = $_POST['donationAmount'];
            } else {
                $DonationAmount = $_POST['suggested_amount'] == "Other Amount" ? $_POST['other_amount'] : $_POST['suggested_amount'];
            }
            $groupPaypal = $_POST['PaypalEmail'];
            $DonationComments = $_POST['DonationComments'];
            if($isRecurring == 'Yes') {
                $paymentPeriod = $_POST['recurrence_period'];
                if($paymentPeriod == 'WEEKLY') {
                    $paymentPeriodInt = 4;
                } else if($paymentPeriod == 'BIWEEKLY') {
                    $paymentPeriodInt = 2;
                } else {
                    $paymentPeriodInt = 1;
                }
                $DonationLength = $_POST['recurrence_ends'];
                //need to tighten up number of payments etc
                $maxAmountPerPayment = $DonationAmount;
                $maxNumberOfPayments = $DonationLength * $paymentPeriodInt;
                $maxTotalAmountOfAllPayments = $maxAmountPerPayment * $maxNumberOfPayments;
                $startDate = date('Y-m-d H:i:s');
                if($DonationLength == 1) {
                    $endDate = date('Y-m-d H:i:s', strtotime("+1 month"));
                } else {
                    $endDate = date('Y-m-d H:i:s', strtotime("+$DonationLength months"));
                }
            }
            $actionType = "CREATE";
            $feesPayer = "";
            $unaltered_price = $DonationAmount;
            if ((isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'mandatory') || (isset($_POST['allowPercentageFee']) && $_POST['allowPercentageFee'] == 'optional' && isset($_POST['coverTransFee']) && $_POST['coverTransFee'])) {
                $groupAmountNumber = $DonationAmount * (1 + ($_POST['PercentageFee']/100));
                $groupAmount = number_format($groupAmountNumber, 2);

                // automatically add the empowered percentage
                $empoweredAmountNumber = $DonationAmount * (0.015);
                $empoweredAmount = number_format($empoweredAmountNumber, 2);
                $groupAmount = str_replace(',', '', $groupAmount);
                $empoweredAmount = str_replace(',', '', $empoweredAmount);

                $receiverPrimaryArray = array();
                if($this->server_type != "sandbox") {
                    if ($groupPaypal != "paypal@empowered.org") {
                        $receiverAmountArray = array("$empoweredAmount", "$groupAmount");
                        $receiverEmailArray  = array("paypal@empowered.org", "$groupPaypal");
                        $invoiceid1 = strtotime(date('Y-m-d H:i:s')) + (int)$ProjectId + 1;
                        $invoiceid2 = strtotime(date('Y-m-d H:i:s')) + (int)$ProjectId + 2;
                        $receiverInvoiceIdArray = array("$invoiceid1", "$invoiceid2");
                    } else {
                        $amount = str_replace(',', '', number_format(($empoweredAmountNumber+$groupAmountNumber),2));
                        $receiverAmountArray = array("$amount");
                        $receiverEmailArray  = array("paypal@empowered.org");
                        $invoiceid1 = strtotime(date('Y-m-d H:i:s')) + (int)$ProjectId + 1;
                        $receiverInvoiceIdArray = array("$invoiceid1");
                    }
                } else {
                    $receiverEmailArray = array("oconno_1301866574_biz@gmail.com", "oconno_1301865577_biz@gmail.com");
                }

            } else {
                $groupAmount = number_format($DonationAmount, 2);
                $groupAmount = str_replace(',', '', $groupAmount);
                $receiverAmountArray = array("$groupAmount");
                $receiverPrimaryArray = array();
                if($this->server_type != "sandbox") {
                    $receiverEmailArray = array("$groupPaypal");
                } else {
                    $receiverEmailArray = array("oconno_1301865577_biz@gmail.com");
                }
                $invoiceid = strtotime(date('Y-m-d H:i:s')) + (int)$ProjectId + 1;
                $receiverInvoiceIdArray = array("$invoiceid");

            }
            if($this->server_type != "sandbox") {
                $ipnNotificationUrl = "http://www.empowered.org/paypalipn"; //Response Handler
                $cancelUrl = "http://www.empowered.org/donation/unsuccessful?ProjectId=$ProjectId";
                $returnUrl = "http://www.empowered.org/donation/successful?ProjectId=$ProjectId";
            } else {
                $ipnNotificationUrl = "http://dev.empowered.org/paypalipn"; //Response Handler
                $cancelUrl = "http://dev.empowered.org/donation/unsuccessful?ProjectId=$ProjectId";
                $returnUrl = "http://dev.empowered.org/donation/successful?ProjectId=$ProjectId";
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

            if($isRecurring == 'Yes') {
                $resArray = $Paypal->CallPreapproval($returnUrl, $cancelUrl, $currencyCode, $startDate, $endDate, $maxTotalAmountOfAllPayments, $senderEmail, $maxNumberOfPayments, $paymentPeriod, $dateOfMonth, $dayOfWeek, $maxAmountPerPayment, $maxNumberOfPaymentsPerPeriod, $pinType);
            } else {
                //if (isset($_POST['supportEmpowered']) && $_POST['supportEmpowered']) {
                $resArray = $Paypal->CallPay($actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray, $receiverPrimaryArray, $receiverInvoiceIdArray, $feesPayer, $ipnNotificationUrl, $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $senderEmail, $trackingId);
                //} else {
                //  $resArray = $Paypal->CallPay($actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray, $receiverInvoiceIdArray, $feesPayer, $ipnNotificationUrl, $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $senderEmail, $trackingId);
                //}
            }

            $ack = strtoupper($resArray["responseEnvelope.ack"]);
            if($ack=="SUCCESS") {
                if ("" == $preapprovalKey) {
                    $Projects = new Brigade_Db_Table_Projects();
                    $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
                    $projInfo = $Projects->loadInfo($ProjectId);
                    $ProjectDonationId = $ProjectDonations->addProjectDonation(array(
                        'ProjectId' => $ProjectId,
                        'GroupId' => $projInfo['GroupId'],
                        'ProgramId' => $projInfo['ProgramId'],
                        'NetworkId' => $projInfo['NetworkId'],
                        'VolunteerId' => $_POST['VolunteerId'],
                        'DonationAmount' => $unaltered_price,
                        'DonorUserId' => isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "",
                        'DonationComments' => $DonationComments,
                        'OrderStatusId' => 0,
                        'TransactionId' => $resArray['payKey'],
                        'TransactionSource' => "Paypal",
                        'CreatedOn' => date('Y-m-d H:i:s'),
                        'isAnonymous' => isset($_POST['isAnonymous']) ? 1 : 0,
                        'SupporterEmail' => $senderEmail,
                        'SupporterName' => $_POST['DonorsName']
                    ));

                    //echo "POSTED FROM VIEW:<br>";
                    //print_r($_POST);
                    //echo "<br><br>";

                    if($this->server_type != "sandbox") {
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
        }
    }

    /**
     * Add gift aid data for GB UK
     * Ajax call before submit donation to payment platform.
     */
    public function giftaidAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params  = $this->_getAllParams();
        $project = Project::get($params['ProjectId']);
        if (!empty($project->organizationId) && $project->organization->hasGiftAid()) {
            if($_POST) {
                extract($_POST);
                $GiftAid = new Brigade_Db_Table_GiftAid();
                $data    = array(
                    'salutation'    => $salutation,
                    'first_name'    => $first_name,
                    'last_name'     => $last_name,
                    'email'         => $email,
                    'phone'         => $phone,
                    'address'       => $address,
                    'family_member' => (!isset($family)),
                    'ProjectId'     => $project->id,
                    'NetworkId'     => $project->organizationId,
                    'GroupId'       => $project->groupId,
                    'ProgramId'     => $project->programId,
                    'CreatedOn'     => date('Y-m-d H:i:s')
                );
                if ($this->_helper->authUser->isLoggedIn()) {
                    $data['UserId'] = $this->view->userNew->id;
                }
                $giftAidId = $GiftAid->addDeclaration($data);

                echo json_encode(array('status' => 'ok', 'id' => $giftAidId));
            }
        } else {
            echo json_encode(array('status' => 'error'));
        }
    }

    /**
     * Donation action for BluePay system
     * Use credit card or echecks
     */
    public function bluepayAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->_getAllParams();

        $validParams = $this->_validateBluePayParams();

        if ($validParams === true) {
            $project = Project::get($params['ProjectId']);
            if (!($project->bluePayId > 0)) {
                die('error - invalid bluepayid');
            }
            $paidFee = false;
            if (isset($params['donationAmountRadio']) && $params['donationAmountRadio'] >= 1) {
                $unalteredPrice = $params['donationAmountRadio'];
            } else {
                $unalteredPrice = $params['donationAmount'];
            }
            $paymentAmount = $unalteredPrice;
            if ($params['typePayment'] != 'check') {
                //credit card fee mandatory
                $paymentAmount = $unalteredPrice * (1 + ($project->percentageFee/100));
                $paidFee       = true;
            }
            if (!empty($params['DonationId'])) {
                $donation = Donation::get($params['DonationId']);
            }
            if (!isset($donation) || !$donation) {
                $donation = new Donation();
            }
            $destinationId = '';
            if (!empty($params['VolunteerId']) && $params['VolunteerId'] != 'none') {
                $destinationId = $params['VolunteerId'];
            }
            $donation->projectId   = $project->id;
            $donation->userId      = $destinationId;
            $donation->amount      = $unalteredPrice;
            $donation->comments    = $params['DonationComments'];
            $donation->createdOn   = date('Y-m-d H:i:s');
            $donation->paidFees    = $paidFee;
            $donation->isAnonymous = isset($params['isAnonymous']) ? 1 : 0;
            if (!empty($project->groupId)) {
                $donation->groupId = $project->groupId;
            }
            if (!empty($project->programId)) {
                $donation->programId = $project->programId;
            }
            if (!empty($project->organizationId)) {
                $donation->organizationId = $project->organizationId;
            }
            $donation->donorUserId = '';
            if ($this->view->isLoggedIn) {
                $donation->donorUserId = $this->view->userNew->id;
            }
            $donation->isReceiptSent     = 0;
            $donation->orderStatusId     = Payment::PENDING;
            $donation->transactionSource = 'Blue Pay';
            $donation->supporterName     = trim($params['firstName'].' '.$params['lastName']);
            $donation->supporterEmail    = trim($params['email']);
            $donation->save();

            $params['comment'] = 'Your donation ';
            if (!empty($donation->userId) && $donation->user) {
                $params['comment'] .= 'on behalf of '.$donation->user->fullName;
            }
            $params['comment'] .= ' is being processed. A receipt will be'.
                        ' emailed to you after processing your donation to '.
                        $donation->project->name.'. Please address '.
                        'any concerns to accounting@globalbrigades.org';

            $bpay = $project->bluePay->createInstanceGateway(
                $donation->id,
                $paymentAmount,
                $params
            );
            if ($params['typePayment'] != 'check') {
                $bpay->process();
            } else {
                $bpay->processACH();
            }

            $donation->transactionId = $bpay->getTransId();
            if ($bpay->getStatus() == BluePayment::STATUS_APPROVED) {
                $donation->orderStatusId = Payment::PROCESSED;
            } else if ($bpay->getStatus() == BluePayment::STATUS_ERROR ||
                       $bpay->getStatus() == BluePayment::STATUS_DECLINE
            ) {
                $donation->orderStatusId = Payment::DECLINED;
            }
            $donation->save();

            // log the site activity
            if ($bpay->getStatus() == BluePay::PROCESSED) {
                $activity              = new Activity();
                $activity->siteId      = $project->id;
                $activity->type        = 'Guest Donation';
                $activity->createdById = 'Anonymous';
                $activity->details     = $unalteredPrice;
                if ($this->view->isLoggedIn && !$donation->isAnonymous) {
                    $activity->createdById = $this->view->userNew->id;
                    $activity->type        = 'User Donation';
                } else if (!$this->view->isLoggedIn && !$donation->isAnonymous) {
                    $activity->createdById = $donation->supporterName;
                }
                $activity->recipientId = $donation->userId;
                $activity->date        = date('Y-m-d H:i:s');
                $activity->save();

                //send receipt
                $this->_sendReceipt($donation, $paymentAmount);

                if ($project->program && $project->program->hasSupporters &&
                    !empty($params['supportersFreqId'])
                ) {
                    //add payment and program supporter
                    $this->_addProgramSupporter($project->program);
                }

                echo json_encode(array(
                    'status'     => 'ok',
                    'donationId' => $donation->id,
                    'payAmount'  => $paymentAmount
                ));
            } else {
                echo json_encode(array(
                    'donationId' => $donation->id,
                    'status'     => 'error',
                    'msg'        => $bpay->getMessage()
                ));
            }

        } else {
            echo $validParams;
        }
    }

    /**
     * Create bluepay rebill request.
     * Save payment and create supporter user for program.
     */
    private function _addProgramSupporter($program) {
        $params            = $this->_getAllParams();
        $params['comment'] = null; //clean comments of donation

        $frequency = SupporterFrequency::get($params['supportersFreqId']);
        if (!$frequency) {
            return;
        }
        if (isset($this->sessionUser) && $this->view->userNew) {
            $supporter = $program->addSupporter($this->sessionUser);
        } else {
            $supporter = $program->addSupporter();
        }
        $bpaySupp = $program->organization->bluePay->createInstanceGateway(
            'SUP'.$supporter->id,
            $frequency->amount,
            $params,
            $frequency
        );

        if ($frequency->bluePayFreq != '') {
            Zend_Registry::get('logger')->info(
                'Supporter::Pay::SUP'.$supporter->id.'::Until('.$frequency->paidUntil.
                ')::Freq('.$frequency->bluePayFreq.')::Amount('.$frequency->amount.')'
            );
        } else {
            Zend_Registry::get('logger')->info('Supporter::Pay::SUP'.$supporter->id.
            '::OneTime::Amount('.$frequency->amount.')');
        }
        if ($params['typePayment'] != 'check') {
            $bpaySupp->process();
        } else {
            $bpaySupp->processACH();
        }
        if ($bpaySupp->getStatus() == BluePay::PROCESSED) {
            $supporter->joinedOn          = date('Y-m-d');
            $supporter->frequencyId       = $frequency->frequencyId;
            $supporter->paidUntil         = $frequency->paidUntil;
            $supporter->paid              = true;
            $supporter->isActive          = true;
            $supporter->lastTransactionId = $bpaySupp->getTransId();
            $supporter->save();

            $payment            = new Payment();
            $payment->programId = $program->id;
            if (!empty($program->organizationId)) {
                $payment->organizationId = $program->organizationId;
            }
            $payment->userId            = $supporter->userId;
            $payment->createdById       = $supporter->userId;
            $payment->orderStatusId     = Payment::PROCESSED;
            $payment->transactionSource = Payment::BLUEPAY;
            $payment->createdOn         = date('Y-m-d');
            $payment->paidUntil         = $supporter->paidUntil;
            $payment->transactionId     = $bpaySupp->getTransId();
            $payment->rebillingId       = $bpaySupp->getRebid();
            $payment->amount            = $frequency->amount;
            $payment->type              = 'Supporter';
            $payment->save();
        } else {

        }
    }

    /**
     * Send email receipt
     */
    private function _sendReceipt($donation, $paymentAmount) {
        $custom = false;
        if ($donation->organization->id == "DAF7E701-4143-4636-B3A9-CB9469D44178") {
            $custom = true;
        }

        $message = "Dear {$donation->supporterName},<br /><br />";
        if ($custom) {
            $message .= "Thank you for your generous donation.";
        } else {
            $message .= "Thank you for your donation to {$donation->organization->name}";
        }
        if (!empty($donation->userId) && $donation->user) {
            if (!$custom) {
                $message .= " on behalf of ". $donation->user->fullName;
            }
            $share = "http://www.empowered.org/" . $donation->user->urlName .
                     "/initiatives/" . $donation->project->urlName .
                     "?utm_campaign=DonationReceipt&utm_medium=Email&utm_source=UserAction";
        } else {
            $share = "http://www.empowered.org/" . $donation->project->urlName .
                     "?utm_campaign=DonationReceipt&utm_medium=Email&utm_source=UserAction";
        }

        $ReceiptMessages = new Brigade_Db_Table_ReceiptMessages();
        if ($donation->organizationId == '2A3801E4-203D-11E0-92E6-0025900034B2') {
            $NPMessage = $ReceiptMessages->getMessage($donation->groupId);
        } else {
            $NPMessage = $ReceiptMessages->getMessage($donation->organizationId);
        }
        if ($NPMessage != '') {
            $NPMessage .= '<br /><br />';
        }

        if (!$custom) {
            $message .= ". You donation details are as follows:<br />";
        }
        $message .= "<br />Here are your donation details:<br /><br />";
        if ($custom) {
            // for gb usa change to INC
            $message .= "Recipient: <span style='color:blue'>Global Brigades, Inc.</span><br />";
        } else {
            $message .= "Recipient: {$donation->organization->name}<br />";
        }
        $message .= "In support of: {$donation->destination}<br />
        Amount: {$donation->project->currency}" . number_format($paymentAmount, 2) . "<br />
        Donation #: {$donation->transactionId}<br />
        Date of Donation: ".date('m/d/Y', strtotime($donation->createdOn))."<br /><br />
        $NPMessage
        Know someone who would love to help? Share this cause with family and" .
        " friends by sending them this link: $share
        Regards,<br />
        {$donation->organization->name}";

        if (!$donation->isReceiptSent) {
            if ($donation->supporterEmail != '') {
                Zend_Registry::get('eventDispatcher')->dispatchEvent(
                    EventDispatcher::$DONATION_RECEIPT,
                    array(
                        $donation->supporterEmail,
                        $message
                    )
                );
            }

            $donation->isReceiptSent = true;
            $donation->save();
        }
    }

    /**
     * Payment notification success
     * TODO: SIMPLIFY HEADERS AND FORMATING HTML WITH INDEX
     */
    public function processbluepayAction() {
        $parameters = $this->_getAllParams();

        $project = $this->view->project = Project::get($parameters['ProjectId']);
        if(!empty($project->organizationId)) {
            $organization = $this->view->organization = $project->organization;

            $Media = new Brigade_Db_Table_Media();
            $this->view->siteBanner = false;
            if (!empty($organization->bannerMediaId)) {
                $siteBanner = $Media->getSiteMediaById($organization->bannerMediaId);
                $this->view->siteBanner = $siteBanner;
                $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
            }
            if(isset($organization->nonProfitId)) {
                $this->view->nonProfitId = $organization->nonProfitId;
                $this->view->nonProfit   = $organization->name;
            }
        }
        if(!empty($project->programId)) {
            $program = $project->program;
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

        //Donations details
        $this->view->donation = false;
        if (!empty($parameters['dId'])) {
            $donation = Donation::get($parameters['dId']);
            if ($donation) {
                $this->view->donation      = $donation;
                $this->view->paymentAmount = $parameters['pA'];
            }
        }

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper($project, 'Donate');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');
    }


    /**
     * Validate parameters and if something is wrong we return status in json
     * to display in html.
     */
    protected function _validateBluePayParams() {
        $params = $this->_getAllParams();
        $error  = "";
        if (!isset($params['ProjectId']) || empty($params['ProjectId'])) {
            $error .= "Missing initiative id. <br />";
        }
        if (isset($params['donationAmountRadio']) && $params['donationAmountRadio'] < 1) {
            $error .= "Invalid donation amount.<br />";
        } else if (!isset($params['donationAmountRadio']) &&
                   (empty($params['donationAmount']) || !($params['donationAmount'] > 0))
        ) {
            $error .= "Invalid donation amount.<br />";
        }
        $error .= BluePay::validateParams($params);
        if ($error != '') {
            return json_encode(array(
                'status' => 'error',
                'msg' => '<label class="error">'.$error.'</label>'
            ));
        } else {
            return true;
        }
    }

    /**
     * Stripe payment
     */
    public function stripeAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        if (empty($params['stripeToken'])) {
            die;
        }

        $project = Project::get($params['ProjectId']);
        if (!($project->stripeId > 0)) {
            die('error - invalid stripeid');
        }
        $paidFee = false;
        if (isset($params['donationAmountRadio']) && $params['donationAmountRadio'] >= 1) {
            $unalteredPrice = $params['donationAmountRadio'];
        } else {
            $unalteredPrice = $params['donationAmount'];
        }
        $paymentAmount = $unalteredPrice;

        //credit card fee mandatory
        $paymentAmount = $unalteredPrice * (1 + ($project->percentageFee/100));
        $feeAmount     = ($project->percentageFee * $unalteredPrice)/100;
        $paidFee       = true;
        $donation      = new Donation();
        $destinationId = '';
        if (!empty($params['VolunteerId']) && $params['VolunteerId'] != 'none') {
            $destinationId = $params['VolunteerId'];
        }
        $donation->projectId   = $project->id;
        $donation->userId      = $destinationId;
        $donation->amount      = $unalteredPrice;
        $donation->comments    = $params['DonationComments'];
        $donation->createdOn   = date('Y-m-d H:i:s');
        $donation->paidFees    = $paidFee;
        $donation->isAnonymous = isset($params['isAnonymous']) ? 1 : 0;
        if (!empty($project->groupId)) {
            $donation->groupId = $project->groupId;
        }
        if (!empty($project->programId)) {
            $donation->programId = $project->programId;
        }
        if (!empty($project->organizationId)) {
            $donation->organizationId = $project->organizationId;
        }
        $donation->donorUserId = '';
        if ($this->view->isLoggedIn) {
            $donation->donorUserId = $this->view->userNew->id;
        }
        $donation->isReceiptSent     = 0;
        $donation->orderStatusId     = Payment::PENDING;
        $donation->transactionSource = 'Stripe';
        $donation->supporterName     = trim($params['firstName'].' '.$params['lastName']);
        $donation->supporterEmail    = trim($params['email']);

        $charge = $project->stripe->createCharge(
            $unalteredPrice,
            $tokenCard,
            '',
            $feeAmount
        );
        if ($charge && is_null($charge['failure_message']) && $charge['paid']) {
            $donation->transactionId = $charge['id'];
            $donation->orderStatusId = Payment::PROCESSED;
        } else {
            $donation->orderStatusId = Payment::DECLINED;
        }
        $donation->save();

        // log the site activity
        if ($donation->orderStatusId == Payment::PROCESSED) {
            $activity              = new Activity();
            $activity->siteId      = $project->id;
            $activity->type        = 'Guest Donation';
            $activity->createdById = 'Anonymous';
            $activity->details     = $unalteredPrice;
            if ($this->view->isLoggedIn && !$donation->isAnonymous) {
                $activity->createdById = $this->view->userNew->id;
                $activity->type        = 'User Donation';
            } else if (!$this->view->isLoggedIn && !$donation->isAnonymous) {
                $activity->createdById = $donation->supporterName;
            }
            $activity->recipientId = $donation->userId;
            $activity->date        = date('Y-m-d H:i:s');
            $activity->save();

            //send receipt
            $this->_sendReceipt($donation, $paymentAmount);

            echo json_encode(array(
                'status'     => 'ok',
                'donationId' => $donation->id,
                'payAmount'  => $paymentAmount
            ));
        } else {
            echo json_encode(array(
                'donationId' => $donation->id,
                'status'     => 'error',
                'msg'        => $charge['failure_message']
            ));
        }
    }
}
