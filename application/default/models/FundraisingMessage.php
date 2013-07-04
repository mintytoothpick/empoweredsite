<?php
require_once 'Brigade/Db/Table/VolunteerFundraisingMessage.php';

/**
 * Class Model Volunteer Fundraising Message.
 * 
 * @author Matias Gonzalez
 */
class FundraisingMessage {
    
    public $id;
    public $projectId;
    public $volunteerId;
    public $text;
        
    /**
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }
    
    /**
     * Load information of the selected message.
     * 
     * @param String $id FundMessage Id.
     */
    public function load($id) {
        $Photo  = new Brigade_Db_Table_VolunteerFundraisingMessage();
        $data = $Photo->loadInfo($id);

        return self::_populateObject($data);
    }
    
    static public function getByProjectVolunteer(Project $project, Volunteer $volunteer) {
        $VFgMessage = new Brigade_Db_Table_VolunteerFundraisingMessage();
        $msg        = $VFgMessage->getFundraisingMessage($project->id, $volunteer->id);
        
        return  self::_populateObject($msg);
    }
        
    /**
     * Create a object with the database array data.
     * 
     * @param Array $data Data in array format of the database
     * 
     * @return Object FundraisingMessage.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj              = new self;
            $obj->id          = $data['FundraisingMessageId'];
            $obj->projectId   = $data['BrigadeId'];
            $obj->volunteerId = $data['VolunteerId'];
            $obj->text        = $data['FundraisingMessage'];
        }
        return $obj;
    }
    
    /**
     * Save object activity into database.
     * 
     * @return void
     */
    public function save() {
        $data = array(
            'FundraisingMessageId' => $this->id,
            'BrigadeId'            => $this->projectId,
            'VolunteerId'          => $this->volunteerId,
            'FundraisingMessage'   => $this->text
        );
        
        $vfm = new Brigade_Db_Table_VolunteerFundraisingMessage();
        
        if ($this->id != '') {
            $vfm->updateFundraisingMessage($this->id, $data);
        } else {
            $vfm->addFundRaisingMessage($data);
        }
    }
}