<?php
require_once 'Brigade/Db/Table/SiteActivityComments.php';
require_once 'User.php';

/**
 * Class Model ActivityComment.
 * 
 * @author Matias Gonzalez
 */
class ActivityComment {
    
    public $id;
    public $siteActivityId;
    public $text;
    public $commentedById;
    public $date;

    protected $_user = null;

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     * 
     * @param String $name Name attr.
     */
    public function __get($name) {
        if ($name == 'user') {
            if (is_null($this->_user)) {
                $this->_getUser();
            } 
            return $this->_user;
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
     * Load information of the selected activity comment.
     * 
     * @param String $id ActivityComment Id.
     */
    public function load($id) {
        $Activity = new Brigade_Db_Table_SiteActivityComments();
        $data     = $Activity->loadInfo($id);

        return self::_populateObject($data);
    }
    
    /**
     * Create a object with the database array data.
     * 
     * @param Array $data Data in array format of the database
     * 
     * @return Object ActivityComment.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj                 = new self;
            $obj->id             = $data['ID'];
            $obj->siteActivityId = $data['SiteActivityId'];
            $obj->text           = $data['Comment'];
            $obj->commentedById  = $data['CommentedBy'];
            $obj->date           = $data['CommentedOn'];
        }
        return $obj;
    }

    /**
     * Return activities of an specific activity.
     * 
     */
    public static function getByActivity(Activity $Activity) {
        $SiteActivi = new Brigade_Db_Table_SiteActivityComments();
        $Comments   = $SiteActivi->getCommentsByActivity($Activity->id);
        $list       = array();
        foreach($Comments as $comm) {
            // create objects
            $list[] = self::_populateObject($comm);
        }
        return $list;
    }
    
    /**
     * Save object into database.
     * 
     * @return void
     */
    public function save() {
        $data = array(
            'SiteActivityId' => $this->siteActivityId,
            'Comment'        => $this->comment,
            'CommentedBy'    => $this->commentedById,
            'CommentedOn'    => $this->date,
        );
        
        $sa = new Brigade_Db_Table_SiteActivityComments();
        $sa->addSiteActivityComment($data);
    }
    
    /**
     * Get user object.
     * 
     * @return User $user Object
     */
    protected function _getUser() {
        $this->_user = User::get($this->commentedById);
    }
}