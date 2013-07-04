<?php
require_once 'Brigade/Db/Table/BluePay.php';

/**
 * Class Model BluePay.
 *
 * @author Matias Gonzalez
 */
class BluePay extends Base {

    public $id;
    public $accountId;
    public $secretKey;
    public $mode;

    const isActive = true; //switch to enable or disable payment gateway

    //status of result bluepay transaction
    const PROCESSED = 1;
    const DECLINED  = 'Declined';
    const ERROR     = 'Error';

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     *
     * @param String $name Name attr.
     */
    public function __get($name) {
        $data  = $this->_getLimits($name);
        $attr  = '_'.$data[0];
        $param = $data[1];
        if (property_exists('BluePay', $attr)) {
            if (is_null($this->$attr)) {
                $method = '_get'.ucfirst($data[0]);
                if ($param != '') {
                    $this->$method($param);
                } else {
                    $this->$method();
                }
            }
            return $this->$attr;
        }
    }

    /**
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $BluePay = new Brigade_Db_Table_BluePay();
        $data    = $BluePay->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object BluePay.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['id'];

            $obj->accountId = $data['AccountId'];
            $obj->secretKey = $data['SecretKey'];
            $obj->mode      = $data['Mode'];
        }
        return $obj;
    }

    /**
     * Create/Update data
     */
    public function save() {
        //TODO
    }

    /**
     * Validate array of parameters for blue pay, before sending information to
     * gateway.
     *
     * @param Array $params Array of parameters
     *
     * @return String Error Message
     */
    static public function validateParams($params) {
        $error = '';
        if (empty($params['typePayment']) ||
            ($params['typePayment'] != 'card' && $params['typePayment'] != 'check')
        ) {
            $error .= "Invalid payment type. <br />";
        }
        if (empty($params['firstName']) || empty($params['lastName'])) {
            $error .= "Invalid name. <br />";
        }
        if (empty($params['street'])) {
            $error .= "Invalid street address. <br />";
        }
        if (empty($params['city'])) {
            $error .= "Invalid city. <br />";
        }
        if (empty($params['state'])) {
            $error .= "Invalid state. <br />";
        }
        if (empty($params['zipcode'])) {
            $error .= "Invalid zip code. <br />";
        }
        if (empty($params['country'])) {
            $error .= "Invalid country. <br />";
        }
        if (empty($params['phone'])) {
            $error .= "Invalid phone number. <br />";
        }
        if (empty($params['email'])) {
            $error .= "Invalid email. <br />";
        }
        if (!empty($params['typePayment']) && $params['typePayment'] == 'card') {
            if (empty($params['cardNumber'])) {
                $error .= "Invalid credit card number. <br />";
            }
            if (empty($params['validationCode'])) {
                $error .= "Invalid credit card validation code. <br />";
            }
            if (!(($params['expirationDateYY'] > date('Y')) ||
                (($params['expirationDateYY'] === date('Y')) &&
                ($params['expirationDateMM'] >= date('m')))
            )) {
                $error .= "Invalid expiration date credit card. <br />";
            }
        }
        if (!empty($params['typePayment']) && $params['typePayment'] == 'check') {
            if (empty($params['checkBankRoutingNumber'])) {
                $error .= "Invalid bank routing number. <br />";
            }
            if (empty($params['checkAccountNumber'])) {
                $error .= "Invalid account number. <br />";
            }
        }

        return $error;
    }

    /**
     * Get frequency string constant for bluepay gateway.
     *
     * @param Payment constant id
     *
     * @return String BluePay Frequency value
     */
    static public function getFrequency($paymentId) {
        switch ($paymentId) {
            case Payment::ONETIME:
                $val = '';
                break;
            case Payment::TWICE_YEAR:
                $val = "6 MONTH";
                break;
            case Payment::ANNUAL:
                $val = "1 YEAR";
                break;
            case Payment::MONTHLY:
                $val = "1 MONTH";
                break;
            case Payment::ONEDAY:
                $val = "1 DAY";
                break;
        }
        return $val;
    }

    /**
     * Set custom params for bluepay gateway payment object.
     *
     * @param String    $orderId   Order id of transaction
     * @param Float     $amount    Amount of transaction
     * @param array     $params    Array of params to set into transaction.
     * @param Frequency $frequency Frequency Interface
     *
     * @return BluePayment Object from bluepay library
     */
    public function createInstanceGateway($orderId, $amount, $params,
        $frequency = null
    ) {
        $bpay = new BluePayment(
            $this->accountId,
            $this->secretKey,
            $this->mode
        );
        $bpay->setOrderId($orderId);
        $bpay->sale($amount);

        if ($params['typePayment'] != 'check') {
            //credit card payment
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
                $params['email'], //The customer's email address.
                null,
                null,
                null,
                (!empty($params['comment'])) ? $params['comment'] : null
            );
        } else {
            //echeck
            $bpay->setCustACHInfo(
                $params['checkBankRoutingNumber'], //the bank's routing number
                $params['checkAccountNumber'], //the customer's account number
                'C', //'C' for checking, or 'S' for savings
                $params['firstName'], //The customer's first name (32 characters)
                $params['lastName'], //The customer's last name (32 characters)
                $params['street'], //The customer's street address, necessary for AVS. (64 Characters)
                $params['city'], //The customer's city (32 Characters)
                $params['state'], //The customers' state, province, or equivalent.(16 Characters max)
                $params['zipcode'], //The customer's zipcode or equivalent. (16 Characters)
                $params['country'],//The customer's country (64 Characters)
                $params['phone'], //The cusotmer's phone number.
                $params['email'], //The customer's email address.
                null,
                null,
                null,
                (!empty($params['comment'])) ? $params['comment'] : null
            );
        }

        //for rebill option
        if ($frequency) {
            if ($frequency->bluePayFreq != '') {
                $bpay->rebAdd(
                    $frequency->amount,
                    $frequency->paidUntil,
                    $frequency->bluePayFreq,
                    null
                );
            }
        }
        return $bpay;
    }
}
