<?php
require_once 'Brigade/Db/Table/Stripe.php';
require_once 'Stripe/Stripe.php';

/**
 * Class Model Stripe.
 *
 * @author Matias Gonzalez
 */
class EmpoweredStripe extends Base {

    public $id;
    public $secretKey;
    public $publishableKey;

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
        if (property_exists('EmpoweredStripe', $attr)) {
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
        $Stripe = new Brigade_Db_Table_Stripe();
        $data   = $Stripe->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Stripe.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj                 = new self;
            $obj->id             = $data['id'];
            $obj->secretKey      = $data['secretKey'];
            $obj->publishableKey = $data['publishableKey'];
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
     * Set custom params for bluepay gateway payment object.
     *
     * @param String    $orderId   Order id of transaction
     * @param Float     $amount    Amount of transaction
     * @param array     $params    Array of params to set into transaction.
     * @param Frequency $frequency Frequency Interface
     *
     * @return BluePayment Object from bluepay library
     */
    public function createCharge($amount, $card, $description, $fee,
        $currency = "usd"
    ) {
        Stripe::setApiKey($this->key);

        $charge = Stripe_Charge::create(array(
                "amount"          => $amount,
                "currency"        => $currency,
                "card"            => $card,
                "description"     => $description,
                "application_fee" => $fee
            )
        );

        return json_decode($charge);
    }
}
