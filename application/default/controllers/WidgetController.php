<?php

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/Paypal.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Paypal/Paypal.php';
require_once 'Mailer.php';

class WidgetController extends Zend_Controller_Action {

    public function ppdonationAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if($_POST) {
            $donationAmount = $_POST['empoweredDonationAmount'];
            $projectId = $_POST['empoweredProjectId'];
            $currencyCode = $_POST['empoweredCurrencyCode'];
            $returnURL = $_POST['empoweredReturnURL'];

            if($_POST['empoweredIsAnonymous']) {
                $isAnonymous = 1;
            } else {
                $isAnonymous = 0;
            }
            //$isCommenting = $_POST['empoweredIsCommenting'];
            if(0){ //$isCommenting){
                $comments = $_POST['empoweredComment'];
            } else {
                $comments = '';
            }

            $actionType = "CREATE";
            $cancelUrl = "$returnURL";
            $returnUrl = "$returnURL";
            $receiverEmailArray = array("paypal@empowered.org", "paypal@netdna.com");
            //load info for paypal account based on projectId

            $groupAmount = number_format(($donationAmount * 0.985), 2);
            $empoweredAmount = number_format(($donationAmount - $groupAmount), 2);
            $receiverAmountArray = array("$empoweredAmount", "$groupAmount");
            $receiverPrimaryArray = array();
            $invoiceId1 = strtotime(date('Y-m-d H:i:s')) + (int)$projectId + 1;
            $invoiceId2 = strtotime(date('Y-m-d H:i:s')) + (int)$projectId + 2;
            $receiverInvoiceIdArray = array("$invoiceId1", "$invoiceId2");
            $feesPayer = '';
            $ipnNotificationUrl = "http://dev.empowered.org/paypalipn"; //Response Handler
            $memo = '';
            $pin = '';
            $preapprovalKey = "";
            $reverseAllParallelPaymentsOnError = true;
            $senderEmail = "";

            $Paypal = new Paypal_API();
            $trackingId = $Paypal->generateTrackingID();    // generateTrackingID function is found in paypalplatform.php
            $resArray = $Paypal->CallPay($actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray, $receiverPrimaryArray, $receiverInvoiceIdArray, $feesPayer, $ipnNotificationUrl, $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $senderEmail, $trackingId);

            $ack = strtoupper($resArray["responseEnvelope.ack"]);
            if($ack == "SUCCESS") {
                if ($preapprovalKey == '') {
                    $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
                    $projectDonationId = $ProjectDonations->addProjectDonation(array(
                        'ProjectId' => $projectId,
                        'VolunteerId' => '',
                        'DonationAmount' => $donationAmount,
                        'DonorUserId' => '',
                        'DonationComments' => $comments,
                        'OrderStatusId' => 0,
                        'TransactionId' => $resArray['payKey'],
                        'TransactionSource' => "Paypal - Widget",
                        'CreatedOn' => date('Y-m-d H:i:s'),
                        'isAnonymous' => $isAnonymous
                    ));

                    //print_r($resArray);
                    $PaypalURL = 'https://www.paypal.com/webapps/adaptivepayment/flow/pay?expType='.$_POST['expType'].'&paykey='.$resArray['payKey'];
                    header('location: '.$PaypalURL);

                }
            }

        }
    }

    public function gcdonationAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        require_once('GoogleCheckout/googlecart.php');
        require_once('GoogleCheckout/googleitem.php');
        require_once('GoogleCheckout/googleshipping.php');
        require_once('GoogleCheckout/googletax.php');

        if($_POST) {
            $donationAmount = $_POST['empoweredDonationAmount'];
            $projectId = $_POST['empoweredProjectId'];
            $currencyCode = $_POST['empoweredCurrencyCode'];
            $isAnonymous = $_POST['empoweredIsAnonymous'];
            $isCommenting = $_POST['empoweredIsCommenting'];
            $returnURL = $_POST['empoweredReturnURL'];
            if($isCommenting){
                $comments = $_POST['empoweredComment'];
            } else {
                $comments = '';
            }

            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $projectDonationId = $ProjectDonations->addProjectDonation(array(
                'ProjectId' => $projectId,
                'VolunteerId' => '',
                'DonationAmount' => $donationAmount,
                'DonorUserId' => '',
                'DonationComments' => $comments,
                'OrderStatusId' => 0,
                //'TransactionId' =>
                'TransactionSource' => "Google Checkout - Widget",
                'CreatedOn' => date('Y-m-d H:i:s'),
                'isAnonymous' => $isAnonymous
            ));

            $Projects = new Brigade_Db_Table_Brigades();
            $networkinfo = $Projects->loadBrigadeTreeInfo($projectId); // get the Brigade's parent Organization
            if($networkinfo['GoogleCheckoutAccountId'] == 0) {
                $Groups = new Brigade_Db_Table_Groups();
                $GC_account = $Groups->getGoogleCheckoutAccount($networkinfo['GroupId']);
            } else {
                $Organizations = new Brigade_Db_Table_Organizations();
                $GC_account = $Organizations->getGoogleCheckoutAccount($networkinfo['NetworkId']);
            }

            $merchantID = $GC_account['GoogleMerchantID'];
            $merchantKey = $GC_account['GoogleMerchantKey'];
            $currency = $GC_account['CurrencyType'];

            $cart = new GoogleCart($merchantID, $merchantKey, "Production", $currency);
            $item = new GoogleItem(
                "Your donation is being processed", // Item name
                "A receipt will be emailed to you after processing your donation", // Item description
                1, // Quantity
                $donationAmount // Donation Amount
            );
            $item->SetMerchantItemId($ProjectDonationId); // this will served as the reference ID to the project_donations table [ProjectDonationId]
            $item->SetEmailDigitalDelivery('true');
            $cart->AddItem($item);
            // Specify the <edit-cart-url>
            $cart->SetEditCartUrl("$returnURL");
            // Specify the <continue-shoppingcart-url>
            $cart->SetContinueShoppingUrl("$returnURL");
            list($status, $error) = $cart->CheckoutServer2Server('');
            // if i reach this point, something was wrong
            echo "An error had ocurred: <br />HTTP Status: " . $status. ":";
            echo "<br />Error message:<br />";
            echo $error;

        }
    }

    public function volunteerAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if(isset($parameters['empoweredVolunteerType'])){
            $Brigades = new Brigade_Db_Table_Brigades();
            $LookupTable = new Brigade_Db_Table_LookupTable();
            $Mailer = new Mailer();
            $Users = new Brigade_Db_Table_Users();
            $Volunteers = new Brigade_Db_Table_Volunteers();

            $projectId = $parameters['empoweredProjectId'];
            $email = $parameters['empoweredEmailAccount'];
            $password = $parameters['empoweredPassword'];

            if($parameters['empoweredAutoAccept']){
                $acceptVolunteer = 1;
            } else {
                $acceptVolunteer = 0;
            }

            //grab project name
            $project = $Brigades->loadInfo($projectId);
            $projectName = $project['Name'];

            if($parameters['empoweredVolunteerType'] == 'returning') {
                //the volunteer is already registered with the site

                $AuthAdapter = new Brigade_Util_Auth();
                $AuthAdapter->setIdentity($email)->setCredential($password);
                $authResult = $AuthAdapter->authenticate($AuthAdapter);

                if ($authResult->isValid()) {
                    //user's creditials were approved
                    $userInfo = $AuthAdapter->_resultRow;
                    if ($userInfo->Active == 1) {
                        //credentials approved & account is active
                        if($Volunteers->isUserSignedUp($projectId, $userInfo['UserId'])){
                            //check if user is already volunteered for the activity
                            $Mailer->sendWidgetUserIsAlreadySignedUp($email, $userInfo['FirstName'], $projectName);

                        } else {
                            // sign up volunteer for project
                            $volunteerId = $Volunteers->signUpVolunteer($userInfo['UserId'], $projectId, $acceptVolunteer);

                            //send success email to user
                            $Mailer->sendWidgetReturningVolunteerSuccess($email, $userInfo['FirstName'], $projectName);
                        }
                    } else {
                        //creditials passed but user's account is not activated -> send inactive user email
                        $Mailer->sendWidgetInactiveUser($email, $userInfo['FirstName'], $projectName);
                    }
                } else {
                    //user's credentials were not approved -> alert email owner
                    $userInfo = $Users->findBy($email);
                    if($userInfo != NULL) {
                        //email exists, password was incorrect
                        $Mailer->sendWidgetBadAuthorization($email, $userInfo['FirstName'], $projectName, 'password');

                    } else {
                        //email is not registered -> prompt them to register or alert them that someone used their account.
                        $Mailer->sendWidgetBadAuthorization($email, $userInfo['FirstName'], $projectName, 'email');

                    }

                }

            } else {
                //the volunteer is also signing up with empowered.org

                $userInfo = $Users->findBy($email);

                if($userInfo != NULL) {
                    $Mailer->sendWidgetEmailIsAlreadyRegistered($email, $userInfo['FirstName'], $projectName);

                } else {
                    $fullName = $parameters['empoweredName'];
                    $arrayName = explode(' ', $fullName);
                    $firstName = $arrayName[0];
                    $count = 0;
                    $lastName = '';
                    foreach($arrayName as $name){
                        if($count > 0){
                            $lastName .= $name.' ';
                        }
                        $count++;
                    }
                    $lastName = trim($lastName);

                    $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), trim($fullName));
                    // replace other special chars with accents
                    $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
                    $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
                    $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

                    $taken = $LookupTable->isSiteNameExists($URLName);
                    $counter = 1;
                    while($taken) {
                        $NewURLName = "$URLName-$counter";
                        $counter++;
                        $taken = $LookupTable->isSiteNameExists($NewURLName);
                    }
                    if($counter > 1) {
                        $URLName = $NewURLName;
                    }

                    $newUser = array(
                        'FirstName' => $firstName,
                        'LastName' => $lastName,
                        'FullName' => $fullName,
                        'Password' => $password,
                        'Email' => $email,
                        'AboutMe' => '',
                        'DateOfBirth' => '',
                        'Gender' => 0,
                        'Location' => '',
                        'Active' => 0,
                        'URLName' => $URLName
                        );

                    $project = $Brigades->loadInfo($projectId);
                    $projectName = $project['Name'];

                    $userId = $Users->addUser($newUser, false); //register user and grab UserId

                    $volunteerId = $Volunteers->signUpVolunteer($userId, $projectId, $acceptVolunteer);

                    $Mailer->sendWidgetNewVolunteerSuccess($email, $firstName, $projectName);
                }
            }

        }
    }

}

?>
