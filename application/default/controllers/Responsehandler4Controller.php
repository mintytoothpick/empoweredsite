<?php

/**
 * ResponsehandlerController - The Responsehandler controller class
 * AMIZADE
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';
require_once 'Brigade/Db/Table/ReceiptMessages.php';
require_once 'Mailer.php';
require_once 'BaseController.php';

class Responsehandler4Controller extends BaseController {

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
        $auth = $this->authenticate(4);
        $root = $auth['root'];
        $data = $auth['data'];
        switch ($root) {
            case 'new-order-notification':
                $transaction_id = $data[$root]['google-order-number']['VALUE'];
                $items = $this->get_arr_result($data[$root]['shopping-cart']['items']['item']);
                foreach ($items as $item) {
                    // update donation
                    $ProjectDonationId = $item['merchant-item-id']['VALUE'];
                    $supporter_name = $user_info['contact-name']['VALUE'];
                    $supporter_email = $user_info['email']['VALUE'];
                    $ProjectDonations->editProjectDonationInfo($ProjectDonationId, array(
                        'TransactionId' => $transaction_id,
                        'OrderStatusId' => 1,
                        'SupporterName' => $data[$root]['buyer-billing-address']['contact-name']['VALUE'],
                        'SupporterEmail' => $data[$root]['buyer-billing-address']['email']['VALUE'],
                        'ModifiedOn' => date('Y-m-d H:i:s')
                    ));
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
                        $ProjectDonations->updateOrderStatus($transaction_id, array(
                            'OrderStatusId' => 2,
                            'ModifiedOn' => date('Y-m-d H:i:s')
                        ));
                        $donationInfo = $ProjectDonations->getInfoByTransactionId($transaction_id);
                        extract($donationInfo);
                        $Brigades = new Brigade_Db_Table_Brigades();
                        $brigadeTree = $Brigades->loadBrigadeTreeInfo($ProjectId);
                        $brigadeInfo = $Brigades->loadInfo($ProjectId);
                        if (trim($VolunteerId) != '') {
                            $Users = new Brigade_Db_Table_Users();
                            $userInfo = $Users->findBy($VolunteerId);
                            $behalfOfUser = ' on behalf of '.$userInfo['FullName'];
                            $shareLink = "http://www.empowered.org/".$userInfo['URLName']."/initiatives/".$brigadeInfo['pURLName']."?utm_campaign=DonationReceipt&utm_medium=Email&utm_source=UserAction";

                            // add activity feed for user donations
                            if ($donationInfo['DonorUserId'] != '00000000-0000-0000-0000-000000000000' && !empty($donationInfo['DonorUserId'])) {
                                $SiteActivities = new Brigade_Db_Table_SiteActivities();
                                $SiteActivities->addSiteActivity(array(
                                    'SiteId' => $donationInfo['ProjectId'],
                                    'ActivityType' => 'User Donation',
                                    'CreatedBy' => $donationInfo['VolunteerId'],
                                    'ActivityDate' => $donationInfo['CreatedOn'],
                                    'Details' => $donationInfo['DonationAmount'],
                                    'Recipient' => $donationInfo['DonorUserId']
                                    ));
                            }
                        } else {
                            $behalfOfUser = '';
                            $shareLink = "http://www.empowered.org/".$brigadeInfo['pURLName']."?utm_campaign=DonationReceipt&utm_medium=Email&utm_source=UserAction";
                        }
                        $ReceiptMessages = new Brigade_Db_Table_ReceiptMessages();
                        if($brigadeTree['NetworkId'] == '2A3801E4-203D-11E0-92E6-0025900034B2') {
                            $organization = $brigadeTree['GroupName'];
                            $NPMessage = $ReceiptMessages->getMessage($brigadeTree['GroupId']);
                        } else {
                            $organization = $brigadeTree['NetworkName'];
                            $NPMessage = $ReceiptMessages->getMessage($brigadeTree['NetworkId']);
                        }
                        if($NPMessage != '') {
                            $NPMessage .= '<br /><br />';
                        }

                        $currencyType = $brigadeTree['Currency'];
                        $message = "Dear $SupporterName,<br /><br />
                            Thank you for your donation to $organization$behalfOfUser. You donation details are as follows:<br /><br />
                            Here are your donation details:<br />
                            Recipient: $organization<br />
                            Amount: $currencyType".number_format($DonationAmount, 2)."<br />
                            Donation #: $TransactionId<br /><br />
                            Know someone who would love to help? Share this cause with family and friends by sending them this link: $shareLink<br /><br />
                            $NPMessage
                            Regards,<br />
                            $organization";
                        if(!$donationInfo['isReceiptSent']){
                            if ($SupporterEmail != '') {
                                Zend_Registry::get('eventDispatcher')->dispatchEvent(
                                    EventDispatcher::$DONATION_RECEIPT,
                                    array($SupporterEmail, $message)
                                );
                             } else {
                                $Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], $message, true);
                             }
                            // set isReceiptSent = 1 so that the receipts wont be sent many times
                            $where = $ProjectDonations->getAdapter()->quoteInto('TransactionId = ?', $TransactionId);
                            $ProjectDonations->update(array('isReceiptSent'=>1), $where);
                        }
                        break;
                    case 'PAYMENT_DECLINED':
                        // update the donation OrderStatusId to 4
                        $transaction_id = $data[$root]['google-order-number']['VALUE'];
                        $ProjectDonations->updateOrderStatus($transaction_id, array(
                            'OrderStatusId' => 4,
                            'XmlResponse' => (string)$auth['xmlResponse'],
                            'ModifiedOn' => date('Y-m-d H:i:s')
                        ));
            $message = "Your donation of $DonationAmount to $recipient has been declined by Google Checkout. The transaction ID for this donation was $TransactionID. Please try making this donation again.";
            $Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], $message, true);
                        break;
                    case 'CANCELLED':
                        // update the donation OrderStatusId to 3
                        $transaction_id = $data[$root]['google-order-number']['VALUE'];
                        $ProjectDonations->updateOrderStatus($transaction_id, array(
                            'OrderStatusId' => 3,
                            'XmlResponse' => (string)$auth['xmlResponse'],
                            'ModifiedOn' => date('Y-m-d H:i:s')
                        ));
                        $donationInfo = $ProjectDonations->getInfoByTransactionId($transaction_id);
                        extract($donationInfo);
                        if (trim($VolunteerId) != '') {
                            $Users = new Brigade_Db_Table_Users();
                            $userInfo = $Users->findBy($VolunteerId);
                            $recipient = $userInfo['FirstName']." ".$userInfo['LastName'];
                            $message = "A donation of $DonationAmount to you has been cancelled by donor.";
                            //$sent = $MailChimp->sendDonationCancellation($userInfo['Email'], $message);
                        }
            $message = "Your donation of $DonationAmount to $recipient has been cancelled. The transaction ID for this donation was $TransactionID. Please try making this donation again.";
                        $Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], $message, true);
                        break;
                    case 'CANCELLED_BY_GOOGLE':
                        // update the donation OrderStatusId to 3
                        $transaction_id = $data[$root]['google-order-number']['VALUE'];
                        $ProjectDonations->updateOrderStatus($transaction_id, array(
                            'OrderStatusId' => 3,
                            'XmlResponse' => (string)$auth['xmlResponse'],
                            'ModifiedOn' => date('Y-m-d H:i:s')
                        ));
                        $donationInfo = $ProjectDonations->getInfoByTransactionId($transaction_id);
                        extract($donationInfo);
                        if (trim($VolunteerId) != '') {
                            $Users = new Brigade_Db_Table_Users();
                            $userInfo = $Users->findBy($VolunteerId);
                            $recipient = $userInfo['FirstName']." ".$userInfo['LastName'];
                            $message = "A donation of $DonationAmount to you has been cancelled by Google Checkout.";
                            //$sent = $MailChimp->sendDonationCancellation($userInfo['Email'], $message, true);
                        }
                        // send notification to donor
                        $message = "Your donation of $DonationAmount to $recipient has been cancelled by Google Checkout. The transaction ID for this donation was $TransactionID. Please try making this donation again.";
                        $Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], $message, true);
                        break;
                    default:
                        break;
                }
                switch ($new_fulfillment_order) {
                    case 'NEW':
                    // do something here…
                        break;
                    case 'PROCESSING':
                    // do something here…
                        break;
                    case 'DELIVERED':
                    // do something here…
                        break;
                    case 'WILL_NOT_DELIVER':
                    // do something here…
                        break;
                    default:
                    // do something here…
                        break;
                }
                $Gresponse->SendAck();
                break;
            case 'chargeback-amount-notification':
                $transaction_id = $data[$root]['google-order-number']['VALUE'];
                $chargeback_amount = $data[$root]['total-chargeback-amount']['VALUE'];
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
                    'OrderStatusId' => 0,
                    'TransactionSource' => "Google Checkout",
                    'TransactionId' => $transaction_id,
                    'SupporterName' => $donation_info['SupporterName'],
                    'SupporterEmail' => $donation_info['SupporterEmail'],
                    'CreatedOn' => date('Y-m-d H:i:s')
                ));
                $Gresponse->SendAck();
                break;
            case 'refund-amount-notification':
                $transaction_id = $data[$root]['google-order-number']['VALUE'];
                $refund_amount = $data[$root]['latest-refund-amount']['VALUE'];
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
                    $recipient = $userInfo['FirstName']." ".$userInfo['LastName'];
                    $message = "A donation of $DonationAmount to you has been refunded by donor.";
                    //$sent = $MailChimp->sendDonationRefund($userInfo['Email'], $message);
                }
                $Gresponse->SendAck();
                break;
            default:
                // do something here…
                $Gresponse->SendBadRequestStatus('Invalid or not supported Message');
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

        return is_array ($var) && !is_numeric (implode('', array_keys($var)));

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
    echo $merchant_id."              ".$merchant_key;

        $Gresponse = new GoogleResponse($merchant_id, $merchant_key);
        $Grequest = new GoogleRequest($merchant_id, $merchant_key, $this->server_type, $currency);
        // retrieve the XML sent in the HTTP POST request to the ResponseHandler
        $xml_response = isset($HTTP_RAW_POST_DATA) ?
            $HTTP_RAW_POST_DATA:file_get_contents("php://input");
        if (get_magic_quotes_gpc()) {
            $xml_response = stripslashes($xml_response);
        }
        list($root, $data) = $Gresponse->GetParsedXML($xml_response);
        $Gresponse->SetMerchantAuthentication($merchant_id, $merchant_key);
        $headers = array();
        $headers[] = "Authorization: Basic ".base64_encode($merchant_id.':'.$merchant_key);
        $headers[] = "Content-Type: application/xml; charset=UTF-8";
        $headers[] = "Accept: application/xml; charset=UTF-8";
        $headers['Authorization'] = "Basic ".base64_encode($merchant_id.':'.$merchant_key);
        $status = $Gresponse->HttpAuthentication($headers);

        return array('status' => $status, 'root' => $root, 'data' => $data, 'xmlResponse' => $xml_response);
    }
}
