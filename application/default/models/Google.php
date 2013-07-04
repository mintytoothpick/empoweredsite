<?php
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';

/**
 * Class Google Checkout Account.
 *
 * @author Matias Gonzalez
 */
class Google {

    public $id;
    public $name;
    public $merchantId;
    public $merchantKey;
    public $currency;
    public $createdBy;
    public $createdOn;
    public $currencyType;
    public $template;

    /**
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($siteId) {
        $obj = new self;
        return $obj->load($siteId);
    }

    /**
     * Load information of the selected google checkout account.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $Google = new Brigade_Db_Table_GoogleCheckoutAccounts();
        $data   = $Google->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Google.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj               = new self;
            $obj->id           = $data['GoogleCheckoutAccountId'];
            $obj->name         = $data['GoogleCheckoutAccountName'];
            $obj->merchantId   = $data['GoogleMerchantID'];
            $obj->merchantKey  = $data['GoogleMerchantKey'];
            $obj->currency     = $data['Currency'];
            $obj->createdBy    = $data['CreatedBy'];
            $obj->createdOn    = $data['CreatedOn'];
            $obj->currencyType = $data['CurrencyType'];
            $obj->template     = $data['DonationProcessedTemplate'];
        }
        return $obj;
    }
}
