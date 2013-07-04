<?php

/**
 * ResponsehandlerController - The Responsehandler controller class
 *
 * @author
 * @version
 */
require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/ReceiptMessages.php';
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';
require_once 'Brigade/Db/Table/EventTicketsPurchased.php';
require_once 'Mailer.php';
require_once 'BaseController.php';
require_once 'Project.php';
require_once 'Donation.php';

class ResponsehandlerController extends BaseController {

    protected $_http;
    protected $merchantID;
    protected $merchantKey;
    protected $server_type = 'Production'; // change this to anything other than 'sandbox' to go live
    protected $currency;
    protected $Gresponse;
    protected $Grequest;

    public function init() {

    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        // include all the required files
        $auth = $this->authenticate(1); // try to use the USD account first
        if (!$auth['status']) {
            $auth = $this->authenticate(2); // if the first one failed, use the GBP account
        }
        $root = $auth['root'];
        $data = $auth['data'];
        //Zend_Registry::get('logger')->info('GoogleCheckout::ResponseHandler::Params ('.print_r($data[$root], true).')');
        switch ($root) {
            case 'new-order-notification':
                $transaction_id = $data[$root]['google-order-number']['VALUE'];
                $items = $this->get_arr_result($data[$root]['shopping-cart']['items']['item']);
                foreach ($items as $item) {
                    // update donation
                    $ProjectDonationId = $item['merchant-item-id']['VALUE'];
                    if ($ProjectDonations->isProjectDonationIdExists($ProjectDonationId)) {
                        $ProjectDonations->editProjectDonationInfo($ProjectDonationId, array(
                            'TransactionId' => $transaction_id,
                            'OrderStatusId' => 1,
                            'SupporterName' => $data[$root]['buyer-billing-address']['contact-name']['VALUE'],
                            'SupporterEmail' => $data[$root]['buyer-billing-address']['email']['VALUE'],
                            'ModifiedOn' => date('Y-m-d H:i:s')
                        ));
                    } else {
                        $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                        $TicketsPurchased->updateTicket($ProjectDonationId, array(
                            'TransactionId' => $transaction_id,
                            'OrderStatusId' => 1,
                            'BuyerName' => $data[$root]['buyer-billing-address']['contact-name']['VALUE'],
                            'BuyerEmail' => $data[$root]['buyer-billing-address']['email']['VALUE'],
                        ));
                    }
                }
                $Gresponse->SendAck();
                break;
            case 'order-state-change-notification':
                $new_financial_state = $data[$root]['new-financial-order-state']['VALUE'];
                $new_fulfillment_order = $data[$root]['new-fulfillment-order-state']['VALUE'];
                switch ($new_financial_state) {
                    case 'REVIEWING':
                        // do something here…
                        break;
                    case 'CHARGEABLE':
                        $Grequest->SendProcessOrder($data[$root]['google-order-number']['VALUE']);
                        $Grequest->SendChargeOrder($data[$root]['google-order-number']['VALUE'], '');
                        break;
                    case 'CHARGING':
                        // do something here…
                        break;
                    case 'CHARGED':
                        // update the donation OrderStatusId to 2
                        $transaction_id = $data[$root]['google-order-number']['VALUE'];
                        $donation       = Donation::getByTransactionId($transaction_id);
                        Zend_Registry::get('logger')->info(
                            'GoogleCheckout::ResponseHandler::CHARGED::'.$transaction_id);
                        if ($donation) {
                            Zend_Registry::get('logger')->info(
                                'GoogleCheckout::ResponseHandler::DELIVERED::'.$transaction_id);
                            $donation->orderStatusId = 2;
                            $donation->modifiedOn    = date('Y-m-d H:i:s');
                            $donation->save();

                            $ReceiptMessages = new Brigade_Db_Table_ReceiptMessages();
                            if ($donation->organizationId == '2A3801E4-203D-11E0-92E6-0025900034B2') {
                                $NPMessage = $ReceiptMessages->getMessage($donation->groupId);
                            } else {
                                $NPMessage = $ReceiptMessages->getMessage($donation->organizationId);
                            }
                            if ($NPMessage != '') {
                                $NPMessage .= '<br /><br />';
                            }
                            if (!$donation->isReceiptSent) {
                                $message = "Dear {$donation->supporterName},<br /><br />
                                Thank you for your donation to {$donation->organization->name}";
                                if (!empty($donation->userId) && $donation->user) {
                                    $message .= " on behalf of ". $donation->user->fullName;
                                    $share    = "http://www.empowered.org/" . $donation->user->urlName .
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

                                $message .= ". You donation details are as follows:<br /><br />
                                Here are your donation details:<br />
                                Recipient: {$donation->organization->name}<br />
                                Amount: {$donation->project->currency}" . number_format($donation->amount, 2) . "<br />
                                Donation #: {$donation->transactionId}<br /><br />
                                Know someone who would love to help? Share this cause with family and" .
                                " friends by sending them this link: $share<br /><br />
                                $NPMessage
                                Regards,<br />
                                {$donation->organization->name}";

                                if ($donation->supporterEmail != '') {
                                    Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                        EventDispatcher::$DONATION_RECEIPT,
                                        array(
                                            $donation->supporterEmail,
                                            $message,
                                            true
                                        )
                                    );
                                    Zend_Registry::get('logger')->info(
                                        'GoogleCheckout::ResponseHandler::Send Email Receipt::' .
                                        $donation->transactionId . ' to: ' . $donation->supporterEmail
                                    );
                                } else {
                                    $Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], $message, true);
                                }

                                $donation->isReceiptSent = true;
                                $donation->save();

                                // log the site activity
                                $activity              = new Activity();
                                $activity->siteId      = $donation->project->id;
                                $activity->type        = 'Guest Donation';
                                $activity->createdById = 'Anonymous';
                                $activity->details     = $donation->amount;
                                if (!empty($donation->donorUserId)) {
                                    $activity->createdById = $donation->donorUserId;
                                    $activity->type        = 'User Donation';
                                }
                                $activity->recipientId = $donation->userId;
                                $activity->date        = date('Y-m-d H:i:s');
                                $activity->save();
                            }
                        } else {
                            $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                            $TicketsPurchased->updateTicketByTransactionId($transaction_id, array('OrderStatusId' => 2));
                        }
                        break;
                    case 'PAYMENT_DECLINED':
                        // update the donation OrderStatusId to 4
                        $transaction_id = $data[$root]['google-order-number']['VALUE'];
                        if ($ProjectDonations->isTransactionIdExists($transaction_id)) {
                            $ProjectDonations->updateOrderStatus($transaction_id, array(
                                'OrderStatusId' => 4,
                                'XmlResponse' => (string) $auth['xmlResponse'],
                                'ModifiedOn' => date('Y-m-d H:i:s')
                            ));
                            $message = "Your donation of $DonationAmount to $recipient has been declined by Google Checkout. The transaction ID for this donation was $TransactionID. Please try making this donation again.";
                            $Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], $message, true);
                        } else {
                            $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                            $TicketsPurchased->updateTicketByTransactionId($transaction_id, array(
                                'OrderStatusId' => 4,
                                'XmlResponse' => (string) $auth['xmlResponse'],
                            ));
                        }
                        break;
                    case 'CANCELLED':
                        // update the donation OrderStatusId to 3
                        $transaction_id = $data[$root]['google-order-number']['VALUE'];
                        if ($ProjectDonations->isTransactionIdExists($transaction_id)) {
                            $ProjectDonations->updateOrderStatus($transaction_id, array(
                                'OrderStatusId' => 3,
                                'XmlResponse' => (string) $auth['xmlResponse'],
                                'ModifiedOn' => date('Y-m-d H:i:s')
                            ));
                            $donationInfo = $ProjectDonations->getInfoByTransactionId($transaction_id);
                            extract($donationInfo);
                            if (trim($VolunteerId) != '') {
                                $Users = new Brigade_Db_Table_Users();
                                $userInfo = $Users->findBy($VolunteerId);
                                $recipient = $userInfo['FirstName'] . " " . $userInfo['LastName'];
                            }

                            $project = Project::get($donationInfo['ProjectId']);
                            if(!is_null($project->groupId)) {
                                $adminEmail = $project->group->contact->email;
                                $message    = "Dear {$project->group->name} Admin,<br><br>
                                                This notice is to inform you that transaction #$transaction_id has been cancelled.  Please take any necessary steps to reconcile this within your organization's accounting or other administrative processes. If you believe this cancellation was made in error, please pull your donor report from Empowered to contact the payee/donor directly or call the credit card company for explanation.<br><br>
                                                Best Regards,<br>
                                                The Empowered.org Team";
                                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$DONATION_CANCELLED,
                                        array($adminEmail, $message));
                            }
                            $message = "Your donation of $DonationAmount to $recipient has been cancelled. The transaction ID for this donation was $TransactionID. Please try making this donation again.";
                            $Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], $message, true);
                        } else {
                            $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                            $TicketsPurchased->updateTicketByTransactionId($transaction_id, array(
                                'OrderStatusId' => 3,
                                'XmlResponse' => (string) $auth['xmlResponse'],
                            ));
                        }
                        break;
                    case 'CANCELLED_BY_GOOGLE':
                        // update the donation OrderStatusId to 3
                        $transaction_id = $data[$root]['google-order-number']['VALUE'];
                        if ($ProjectDonations->isTransactionIdExists($transaction_id)) {
                            $ProjectDonations->updateOrderStatus($transaction_id, array(
                                'OrderStatusId' => 3,
                                'XmlResponse' => (string) $auth['xmlResponse'],
                                'ModifiedOn' => date('Y-m-d H:i:s')
                            ));
                            $donationInfo = $ProjectDonations->getInfoByTransactionId($transaction_id);
                            extract($donationInfo);
                            if (trim($VolunteerId) != '') {
                                $Users = new Brigade_Db_Table_Users();
                                $userInfo = $Users->findBy($VolunteerId);
                                $recipient = $userInfo['FirstName'] . " " . $userInfo['LastName'];
                                $message = "A donation of $DonationAmount to you has been cancelled by Google Checkout.";
                                //$sent = $MailChimp->sendDonationCancellation($userInfo['Email'], $message, true);
                            }
                            // send notification to donor
                            $message = "Your donation of $DonationAmount to $recipient has been cancelled by Google Checkout. The transaction ID for this donation was $TransactionID. Please try making this donation again.";
                            $Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], $message, true);
                        } else {
                            $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                            $TicketsPurchased->updateTicketByTransactionId($transaction_id, array(
                                'OrderStatusId' => 3,
                                'XmlResponse' => (string) $auth['xmlResponse'],
                            ));
                        }
                        break;
                    default:
                        break;
                }
                Zend_Registry::get('logger')->info('GoogleCheckout::ResponseHandler::ACK'.(isset($transaction_id) ? $transaction_id:''));
                $Gresponse->SendAck();
                break;
            case 'chargeback-amount-notification':
                $transaction_id = $data[$root]['google-order-number']['VALUE'];
                $chargeback_amount = $data[$root]['total-chargeback-amount']['VALUE'];
                if ($ProjectDonations->isTransactionIdExists($transaction_id)) {
                    $donation_info = $ProjectDonations->getInfoByTransactionId($transaction_id);
                    // add activity feed for user donations

                   if (!empty($donation_info['$VolunteerId']) && $donation_info['DonorUserId'] != '00000000-0000-0000-0000-000000000000' && !empty($donation_info['DonorUserId'])) {
                        $SiteActivities = new Brigade_Db_Table_SiteActivities();
                        $SiteActivities->addSiteActivity(array(
                            'SiteId' => $donation_info['ProjectId'],
                            'ActivityType' => 'User Donation Chargeback',
                            'CreatedBy' => $donation_info['VolunteerId'],
                            'ActivityDate' => $donationInfo['CreatedOn'],
                            'Details' => $donation_info['DonationAmount'],
                            'Recipient' => $donation_info['DonorUserId']
                        ));
                    }
                    // add new donation record with a (-) DonationAmount value
                    $ProjectDonations->addProjectDonation(array(
                        'ProjectId' => $donation_info['ProjectId'],
                        'VolunteerId' => $donation_info['VolunteerId'],
                        'DonationAmount' => -$chargeback_amount,
                        'DonorUserId' => $donation_info['DonorUserId'],
                        'DonationComments' => $donation_info['DonationComments'],
                        'OrderStatusId' => 2,
                        'TransactionSource' => "Google Checkout",
                        'TransactionId' => $transaction_id,
                        'SupporterName' => $donation_info['SupporterName'],
                        'SupporterEmail' => $donation_info['SupporterEmail'],
                        'CreatedOn' => date('Y-m-d H:i:s')
                    ));
                    $Gresponse->SendAck();
                } else {
                    $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                    $eventInfo = $TicketsPurchased->getInfoByTransactionId($transaction_id);
                    $TicketPurchaseId = $TicketsPurchased->AddTicketPurchased(array(
                                'EventId' => $eventInfo['EventId'],
                                'GroupId' => $eventInfo['GroupId'],
                                'TotalAmount' => -$chargeback_amount,
                                'BuyerUserId' => $eventInfo['BuyerUserId'],
                                'OrderStatusId' => 2,
                                'TransactionSource' => "Google Checkout",
                                'TransactionId' => $transaction_id,
                                'BuyerName' => $eventInfo['BuyerName'],
                                'BuyerEmail' => $eventInfo['BuyerEmail'],
                            ));
                }
                break;
            case 'refund-amount-notification':
                $transaction_id = $data[$root]['google-order-number']['VALUE'];
                $refund_amount = $data[$root]['latest-refund-amount']['VALUE'];
                if ($ProjectDonations->isTransactionIdExists($transaction_id)) {
                    $donation_info = $ProjectDonations->getInfoByTransactionId($transaction_id);
                    // add activity feed for user donations
                    if (!empty($donation_info['$VolunteerId']) && $donation_info['DonorUserId'] != '00000000-0000-0000-0000-000000000000' && !empty($donation_info['DonorUserId'])) {
                        $SiteActivities = new Brigade_Db_Table_SiteActivities();
                        $SiteActivities->addSiteActivity(array(
                            'SiteId' => $donation_info['ProjectId'],
                            'ActivityType' => 'User Donation Refunded',
                            'CreatedBy' => $donation_info['VolunteerId'],
                            'ActivityDate' => $donationInfo['CreatedOn'],
                            'Details' => $donation_info['DonationAmount'],
                            'Recipient' => $donation_info['DonorUserId']
                        ));
                    }
                    // add new donation record with a (-) DonationAmount value
                    $ProjectDonationId = $ProjectDonations->addProjectDonation(array(
                                'ProjectId' => $donation_info['ProjectId'],
                                'VolunteerId' => $donation_info['VolunteerId'],
                                'DonationAmount' => -$refund_amount,
                                'DonorUserId' => $donation_info['DonorUserId'],
                                'DonationComments' => $donation_info['DonationComments'],
                                'OrderStatusId' => 2,
                                'TransactionSource' => "Google Checkout",
                                'TransactionId' => $transaction_id,
                                'SupporterName' => $donation_info['SupporterName'],
                                'SupporterEmail' => $donation_info['SupporterEmail'],
                                'CreatedOn' => date('Y-m-d H:i:s')
                            ));
                    $donationInfo = $ProjectDonations->getInfoByTransactionId($transaction_id);
                    extract($donationInfo);
                    if (trim($VolunteerId) != '') {
                        $Users = new Brigade_Db_Table_Users();
                        $userInfo = $Users->findBy($VolunteerId);
                        $recipient = $userInfo['FirstName'] . " " . $userInfo['LastName'];
                        $message = "A donation of $DonationAmount to you has been refunded by donor.";
                        //$sent = $MailChimp->sendDonationRefund($userInfo['Email'], $message);
                    }
                } else {
                    $TicketsPurchased = new Brigade_Db_Table_EventTicketsPurchased();
                    $eventInfo = $TicketsPurchased->getInfoByTransactionId($transaction_id);
                    $TicketPurchaseId = $TicketsPurchased->AddTicketPurchased(array(
                                'EventId' => $eventInfo['EventId'],
                                'GroupId' => $eventInfo['GroupId'],
                                'TotalAmount' => -$refund_amount,
                                'BuyerUserId' => $eventInfo['BuyerUserId'],
                                'OrderStatusId' => 2,
                                'TransactionSource' => "Google Checkout",
                                'TransactionId' => $transaction_id,
                                'BuyerName' => $eventInfo['BuyerName'],
                                'BuyerEmail' => $eventInfo['BuyerEmail'],
                            ));
                }
                $Gresponse->SendAck();
                break;
            default:
                if (!is_null($Gresponse)) {
                    $Gresponse->SendBadRequestStatus('Invalid or not supported Message');
                }
                break;
        }
    }

    private function get_arr_result($child_node) {
        $result = array();
        if (isset($child_node)) {
            if ($this->is_associative_array($child_node)) {
                $result[] = $child_node;
            } else {
                foreach ($child_node as $curr_node) {
                    $result[] = $curr_node;
                }
            }
        }
        return $result;
    }

    private function is_associative_array($var) {

        return is_array($var) && !is_numeric(implode('', array_keys($var)));
    }

    private function authenticate($GoogleCheckoutAccountId) {
        include('GoogleCheckout/googlecart.php');
        include('GoogleCheckout/googleitem.php');
        include('GoogleCheckout/googleshipping.php');
        include('GoogleCheckout/googletax.php');
        include('GoogleCheckout/googleresponse.php');
        include('GoogleCheckout/googlerequest.php');

        // get Google Checkout Account credentials
        $GoogleCheckoutAccounts = new Brigade_Db_Table_GoogleCheckoutAccounts();
        $GC_account = $GoogleCheckoutAccounts->loadInfo($GoogleCheckoutAccountId);
        $merchant_id = $GC_account['GoogleMerchantID'];
        $merchant_key = $GC_account['GoogleMerchantKey'];
        $currency = $GC_account['CurrencyType'];

        $Gresponse = new GoogleResponse($merchant_id, $merchant_key);
        $Grequest  = new GoogleRequest($merchant_id, $merchant_key, $this->server_type, $currency);
        // retrieve the XML sent in the HTTP POST request to the ResponseHandler
        $xml_response = isset($HTTP_RAW_POST_DATA) ?
                $HTTP_RAW_POST_DATA : file_get_contents("php://input");
        if (get_magic_quotes_gpc()) {
            $xml_response = stripslashes($xml_response);
        }
        list($root, $data) = $Gresponse->GetParsedXML($xml_response);
        $Gresponse->SetMerchantAuthentication($merchant_id, $merchant_key);
        $headers = array();
        $headers[] = "Authorization: Basic " . base64_encode($merchant_id . ':' . $merchant_key);
        $headers[] = "Content-Type: application/xml; charset=UTF-8";
        $headers[] = "Accept: application/xml; charset=UTF-8";
        $headers['Authorization'] = "Basic " . base64_encode($merchant_id . ':' . $merchant_key);
        $status = $Gresponse->HttpAuthentication($headers);

        return array('status' => $status, 'root' => $root, 'data' => $data, 'xmlResponse' => $xml_response);
    }
}

