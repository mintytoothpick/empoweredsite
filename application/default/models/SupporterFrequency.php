<?php
require_once 'Brigade/Db/Table/ProgramSupporterFrequency.php';

/**
 * Class Model SupporterFrequency.
 * For different ways of support.
 * All static because we don't know if is going to be changed in future
 *
 * @author Matias Gonzalez
 */
class SupporterFrequency extends Base {

    public $id;
    public $programId;
    public $frequencyId;
    public $amount;
    public $description = '';
    public $frequency   = '';
    public $paidUntil   = null;
    public $bluePayFreq = '';

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
            $obj              = new self;
            $obj->id          = $data['id'];
            $obj->frequencyId = $data['FrequencyId'];
            $obj->programId   = $data['ProgramId'];
            $obj->amount      = $data['Amount'];
            $obj->description = $data['Description'];
            $obj->bluePayFreq = BluePay::getFrequency($obj->frequencyId);
            $obj->frequency   = Payment::getFrequency($obj->frequencyId);
            $obj->paidUntil   = Payment::getPaidUntil($obj->frequencyId);
        }
        return $obj;
    }

    static public function getList($program) {
        $gmf  = new Brigade_Db_Table_ProgramSupporterFrequency();
        $freq = $gmf->loadList($program->id);
        $list = array();
        foreach($freq as $data) {
            // create objects project
            $list[] = self::_populateObject($data);
        }

        return $list;
    }

    /**
     * Create/Update frequency data
     */
    public function save() {
        $GMemF = new Brigade_Db_Table_ProgramSupporterFrequency();
        $data  = array(
            'ProgramId'   => $this->programId,
            'FrequencyId' => $this->frequencyId,
            'Amount'      => $this->amount,
            'Description' => $this->description
        );
        $GMemF->addMembershipFrequency($data);
    }

    static public function clean($program) {
        $GMemF = new Brigade_Db_Table_ProgramSupporterFrequency();
        $GMemF->cleanSupporterFrequency($program->id);
    }

    /**
     * Get particullar frequency for group.
     */
    static public function getByProgram($frequencyId, $programId) {
        $gmf  = new Brigade_Db_Table_ProgramSupporterFrequency();
        $data = $gmf->getByFrequencyAndProgram($frequencyId, $programId);
        $obj  = self::_populateObject($data);

        return $obj;
    }
}
