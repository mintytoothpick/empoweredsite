<?php

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/ReceiptMessages.php';
require_once 'Mailer.php';

class PaypalipnController extends Zend_Controller_Action {

    public function indexAction() {
	$this->_helper->layout->disableLayout();
	$this->_helper->viewRenderer->setNoRender();

	// Paypal POSTs HTML FORM variables to this page. We must post all the variables back to paypal exactly unchanged and add an extra parameter cmd with value _notify-validate

	$raw_post = file_get_contents("php://input");
	$ipn_array = $this->decodePayPalIPN($raw_post);
	$req = $raw_post."&cmd=_notify-validate";

	// post back to PayPal system to validate
	$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
	$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

	//if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
	//    $fp = fsockopen ('www.paypal.com', 80, $errno, $errstr, 30);
	//} else {
	//    $fp = fsockopen ('www.sandbox.paypal.com', 80, $errno, $errstr, 30);
	//}
	// or use port 443 for an SSL connection
	$fp = fsockopen ('ssl://www.paypal.com', 443, $errno, $errstr, 30);


	if (!$fp) {
		// HTTP ERROR Failed to connect
		// Send email or add other error handling. 
		 
			// If you want to log to a file as well then uncomment the following lines:
			//
			//$fh = fopen("logipn.txt", 'a');//open file and create if does not exist
			//fwrite($fh, "\r\n/////////////////////////////////////////\r\n HTTP ERROR \r\n");//Just for spacing in log file
			//
			//fwrite($fh, $errstr);//write data
			//fclose($fh);//close file


	} else {
	    fputs ($fp, $header . $req);
	    $email_body = "";
	    while (!feof($fp)) {
	    	$res = fgets ($fp, 1024);
		$email_body .= $res;

	    	if (strcmp ($res, "VERIFIED") == 0) {

		    //$type = $_POST['transaction_type'];
			$status = $ipn_array['status'];
		    //$empowered_statsus = ['transaction[0].status']
		    //$group_status = ['transaction[1].status'];
			//$amount = $_POST['gross'];
		    $paykey = $ipn_array['pay_key'];
			$donor_email = $ipn_array['sender_email'];

			if ($status == "COMPLETED") { // &&   //payment_status = Completed
			//($receiver_email == "<insert your business account email>") &&   // receiver_email is same as your account email
			//($payment_amount == $amount_they_should_have_paid ) &&  //check they payed what they should have
			//($payment_currency == "GBP") &&  // and its the correct currency 
			//(!txn_id_used_before($txn_id)))   //txn_id isn't same as previous to stop duplicate payments. You will need to write a function to do this check.
                            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
                            if ($ProjectDonations->isTransactionIdExists($paykey)) {
                                $ProjectDonations->updateOrderStatus($paykey, array(
                                    'OrderStatusId' => 2,
                                    'SupporterEmail' => $donor_email,
                                    'ModifiedBy' => 'Paypal IPN',
                                    'ModifiedOn' => date('Y-m-d H:i:s')
                                ));

                                $DonationDetails = $ProjectDonations->getInfoByTransactionId($paykey);
                                $currencyType = '';
                                $DonationAmount = $DonationDetails['DonationAmount'];
                                $Brigades = new Brigade_Db_Table_Brigades();
                                $treeInfo = $Brigades->loadBrigadeTreeInfo($DonationDetails['ProjectId']);
                                $projectInfo = $Brigades->loadInfoBasic($DonationDetails['ProjectId']);
                                $projectLink = $projectInfo['pURLName'];
                                $currencyType = $projectInfo['pCurrency'];
                                $ReceiptMessages = new Brigade_Db_Table_ReceiptMessages();
                                if(!empty($DonationDetails['SupportName'])) {
                                    $SupporterName = $DonationDetails['SupporterName'];
                                } else {
                                    $SupporterName = 'Anonymous';
                                }

                                if(empty($projectInfo['NetworkId'])) {
                                    $organization = $projectInfo['Name'];
                                    $NPMessage = '';
                                } else {
                                    $organization = $treeInfo['NetworkName'];
                                    $NPMessage = $ReceiptMessages->getMessage($treeInfo['NetworkId']);
                                }
                                $organization = stripslashes($organization);
                                if($NPMessage != '') {
                                    $NPMessage .= '<br /><br />';
                                }

                                $shareLink = "http://www.empowered.org/$projectLink";
                                    
                                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$DONATION_RECEIPT,
                                        array($donor_email, $organization,
                                         $organization, $organization, $DonationAmount,
                                         $currencyType, $paykey, $shareLink, $NPMessage));
                            } else {
                                $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                                $TicketsPurchased->updateTicketByTransactionId($paykey, array(
                                    'OrderStatusId' => 2,
                                    'BuyerEmail' => $donor_email,
                                    'ModifiedBy' => 'Paypal IPN'
                                ));
                            }
			} else {
				//
				// paypal replied with something other than completed or one of the security checks failed.
				// you might want to do some extra processing here
				//
				//in this application we only accept a status of "Completed" and treat all others as failure. You may want to handle the other possibilities differently
				//payment_status can be one of the following
				//Canceled_Reversal: A reversal has been canceled. For example, you won a dispute with the customer, and the funds for
				//                           Completed the transaction that was reversed have been returned to you.
				//Completed:            The payment has been completed, and the funds have been added successfully to your account balance.
				//Denied:                 You denied the payment. This happens only if the payment was previously pending because of possible
				//                            reasons described for the PendingReason element.
				//Expired:                 This authorization has expired and cannot be captured.
				//Failed:                   The payment has failed. This happens only if the payment was made from your customerâ€™s bank account.
				//Pending:                The payment is pending. See pending_reason for more information.
				//Refunded:              You refunded the payment.
				//Reversed:              A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from
				//                          your account balance and returned to the buyer. The reason for the
				//                           reversal is specified in the ReasonCode element.
				//Processed:            A payment has been accepted.
				//Voided:                 This authorization has been voided.
				//
		
		
			}
	        } else if (strcmp ($res, "INVALID") == 0) {
		//
		// Paypal didnt like what we sent. If you start getting these after system was working ok in the past, check if Paypal has altered its IPN format
		//
	        }
	    } //end of while
	    fclose ($fp);
	}
	
    }

    private function decodePayPalIPN($raw_post) {
	    if (empty($raw_post)) {
		return array();
	    } #else
	    $post = array();
	    $pairs = explode('&', $raw_post);
	    foreach ($pairs as $pair) {
		list($key, $value) = explode('=', $pair, 2);
		$key = urldecode($key);
		$value = urldecode($value);
		# This is look for a key as simple as 'return_url' or as complex as 'somekey[x].property'
		preg_match('/(\w+)(?:\[(\d+)\])?(?:\.(\w+))?/', $key, $key_parts);
		switch (count($key_parts)) {
		    case 4:
			# Original key format: somekey[x].property
			# Converting to $post[somekey][x][property]
			if (!isset($post[$key_parts[1]])) {
			    $post[$key_parts[1]] = array($key_parts[2] => array($key_parts[3] => $value));
			} else if (!isset($post[$key_parts[1]][$key_parts[2]])) {
			    $post[$key_parts[1]][$key_parts[2]] = array($key_parts[3] => $value);
			} else {
			    $post[$key_parts[1]][$key_parts[2]][$key_parts[3]] = $value;
			}
			break;
		    case 3:
			# Original key format: somekey[x]
			# Converting to $post[somkey][x] 
			if (!isset($post[$key_parts[1]])) {
			    $post[$key_parts[1]] = array();
			}
			$post[$key_parts[1]][$key_parts[2]] = $value;
			break;
		    default:
			# No special format
			$post[$key] = $value;
			break;
		}#switch
	    }#foreach
	    
	    return $post;
    }

} 

?>
