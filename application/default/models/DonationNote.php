<?php
require_once 'Brigade/Db/Table/ProjectDonationNotes.php';

/**
 * Class Model DonationNote (project_donation_notes).
 *
 * @author Matias Gonzalez
 */
class DonationNote extends Base {

    public $id;
    public $projectDonationId;
    public $note;
    public $createdById;
    public $createdOn;
    public $modifiedBy;
    public $modifiedOn;
    public $donorEmail = '';
    public $isPrivate;

    // Lazy
    protected $_user = null;

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
        if (property_exists('DonationNote', $attr)) {
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
        $PDonation = new Brigade_Db_Table_ProjectDonations();
        $data      = $PDonation->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Return list of donations by Project
     *
     * @param Project $Project to get all donations.
     *
     * @return Array List of donations
     */
    public function getListByDonation(Donation $Donation) {
        $DNotes = new Brigade_Db_Table_ProjectDonationNotes();
        $Notes  = $DNotes->getListByDonation($Donation->id);

        $list = array();
        foreach($Notes as $note) {
            // create objects project
            $list[] = self::_populateObject($note);
        }
        return $list;
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Donation.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['DonationNoteId'];

            $obj->projectDonationId = $data['ProjectDonationId'];
            $obj->note              = $data['Notes'];
            $obj->isPrivate         = $data['isPrivate'];
            $obj->createdById       = $data['CreatedBy'];
            $obj->createdOn         = $data['CreatedOn'];
            $obj->modifiedBy        = $data['ModifiedBy'];
            $obj->modifiedOn        = $data['ModifiedOn'];
            $obj->donorEmail        = $data['DonorEmail'];
        }
        return $obj;
    }

    /**
     * Create
     */
    public function save() {
        $data = array(
            'ProjectDonationId' => $this->projectDonationId,
            'Notes'             => $this->note,
            'isPrivate'         => $this->isPrivate,
            'CreatedBy'         => $this->createdById,
            'CreatedOn'         => $this->createdOn,
            'ModifiedBy'        => $this->modifiedBy,
            'ModifiedOn'        => $this->modifiedOn,
            'DonorEmail'        => $this->donorEmail
        );
        $notes = new Brigade_Db_Table_ProjectDonationNotes();
        if (!empty($this->id)) {
            //TODO: Update
        } else {
            $notes->addDonationNote($data);
        }
    }

    /**
     * Gets user volunteer. Donation behalf
     *
     * @return void
     */
    protected function _getUser() {
        $this->_user = User::get($this->createdById);
    }
}
