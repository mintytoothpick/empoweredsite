<?php
require_once 'Brigade/Db/Table/FlyForGood.php';

/**
 * Class Fly For Good.
 *
 * @author Matias Gonzalez
 */
class FlyForGood extends Base {

    public $id;
    public $userId;
    public $organizationId;
    public $amount;
    public $fee;
    public $currency;
    public $flyForGoodId;
    public $description = '';

    /**
     * Return total user spent in fly for good site.
     *
     * @param User         $user         User instance to get total spent
     * @param Organization $organization Organization to get funds
     *
     * return float Total amount spent.
     */
    static public function getUserSpent($user, $organization) {
        $FFG  = new Brigade_Db_Table_FlyForGood();
        $res = $FFG->getMoneySpent($user->id, $organization->id);
        if (!$res['TotalSpent']) {
            return 0;
        } else {
            return $res['TotalSpent']+$res['TotalFee'];
        }

    }

    /**
     * Add payment into database
     *
     * return void
     */
    public function payTicket() {
        $data                   = array();
        $data['UserId']         = $this->userId;
        $data['OrganizationId'] = $this->organizationId;
        $data['Amount']         = $this->amount;
        $data['Fee']            = $this->fee;
        $data['Currency']       = $this->currency;
        $data['FlyForGoodId']   = $this->flyForGoodId;
        $data['Description']    = $this->description;

        $ffg = new Brigade_Db_Table_FlyForGood();
        $ffg->insert($data);
    }
}
