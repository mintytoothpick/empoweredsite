<?php
require_once 'Brigade/Db/Table/Survey.php';
require_once 'Base.php';
require_once 'Group.php';
require_once 'User.php';

/**
 * Class Model Survey.
 *
 * @author Matias Gonzalez
 */
class Survey extends Base {

    public $id;
    public $userId;
    public $groupId;
    public $projectId;
    public $firstName;
    public $middleName;
    public $lastName;
    public $nickName;
    public $gender;
    public $birthday;
    public $email;
    public $phone;
    public $citizenship;
    public $passportType;
    public $passportNumber;
    public $passportExpirationDate;
    public $emergencyContactName;
    public $emergencyContactNumber;
    public $emergencyContactRelationship;
    public $emergencyContactEmail;
    public $skills;
    public $degree;
    public $dietaryRestrictions;
    public $medicalConditions;
    public $otherInformation;
    public $contactAmerica;
    public $spanishLevel;
    public $discipline;
    public $brigadeMonth;
    public $leadershipPosition;
    public $date;
    public $question1;
    public $question2;
    public $question3;
    public $question4;
    public $question5;
    public $question6;

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
        if (property_exists('Survey', $attr)) {
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
     * Get instance object.
     * TODO: Implement cache layer.
     *
     * @param String $id Survey Id.
     *
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Load information of the selected project.
     *
     * @param String $id Survey Id.
     */
    public function load($id) {
        $Survey = new Brigade_Db_Table_Survey();
        $data   = $Survey->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Survey.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj                               = new self;
            $obj->id                           = $data['SurveyId'];
            $obj->userId                       = $data['UserId'];
            $obj->groupId                      = $data['GroupId'];
            $obj->projectId                    = $data['ProjectId'];
            $obj->firstName                    = $data['firstname'];
            $obj->middleName                   = $data['middlename'];
            $obj->lastName                     = $data['lastname'];
            $obj->nickName                     = $data['nickname'];
            $obj->gender                       = $data['gender'];
            $obj->birthday                     = $data['birthday'];
            $obj->email                        = $data['email'];
            $obj->phone                        = $data['phone'];
            $obj->citizenship                  = $data['citizenship'];
            $obj->passportType                 = $data['passport_type'];
            $obj->passportNumber               = $data['passport_number'];
            $obj->passportExpirationDate       = $data['passport_expirationdate'];
            $obj->emergencyContactName         = $data['emergency_contactname'];
            $obj->emergencyContactNumber       = $data['emergency_contactnumber'];
            $obj->emergencyContactRelationship = $data['emergency_contactrelationship'];
            $obj->emergencyContactEmail        = $data['emergency_contactemail'];
            $obj->skills                       = $data['skills'];
            $obj->degree                       = $data['degree'];
            $obj->dietaryRestrictions          = $data['dietary_restrictions'];
            $obj->medicalConditions            = $data['medical_conditions'];
            $obj->otherInformation             = $data['other_information'];
            $obj->contactAmerica               = $data['contact_america'];
            $obj->spanishLevel                 = $data['spanish_level'];
            $obj->discipline                   = $data['discipline'];
            $obj->brigadeMonth                 = $data['brigade_month'];
            $obj->leadershipPosition           = $data['leadership_position'];
            $obj->date                         = $data['date'];

            $obj->question1 = $data['question1'];
            $obj->question2 = $data['question2'];
            $obj->question3 = $data['question3'];
            $obj->question4 = $data['question4'];
            $obj->question5 = $data['question5'];
            $obj->question6 = $data['question6'];
        }
        return $obj;
    }

    /**
     * Save object into database.
     *
     * @return void
     */
    public function save() {
        $data = array(
            'UserId'                        => $this->userId,
            'GroupId'                       => $this->groupId,
            'ProjectId'                     => $this->projectId,
            'firstname'                     => $this->firstName,
            'middlename'                    => $this->middleName,
            'lastname'                      => $this->lastName,
            'nickname'                      => $this->nickName,
            'gender'                        => $this->gender,
            'birthday'                      => $this->birthday,
            'email'                         => $this->email,
            'phone'                         => $this->phone,
            'citizenship'                   => $this->citizenship,
            'passport_type'                 => $this->passportType,
            'passport_number'               => $this->passportNumber,
            'passport_expirationdate'       => $this->passportExpirationDate,
            'emergency_contactname'         => $this->emergencyContactName,
            'emergency_contactnumber'       => $this->emergencyContactNumber,
            'emergency_contactrelationship' => $this->emergencyContactRelationship,
            'emergency_contactemail'        => $this->emergencyContactEmail,
            'skills'                        => $this->skills,
            'degree'                        => $this->degree,
            'dietary_restrictions'          => $this->dietaryRestrictions,
            'medical_conditions'            => $this->medicalConditions,
            'other_information'             => $this->otherInformation,
            'contact_america'               => $this->contactAmerica,
            'spanish_level'                 => $this->spanishLevel,
            'discipline'                    => $this->discipline,
            'brigade_month'                 => $this->brigadeMonth,
            'leadership_position'           => $this->leadershipPosition,
            'date'                          => $this->date,
            'question1'                     => $this->question1,
            'question2'                     => $this->question2,
            'question3'                     => $this->question3,
            'question4'                     => $this->question4,
            'question5'                     => $this->question5,
            'question6'                     => $this->question6
        );

        $sa = new Brigade_Db_Table_Survey();
        if (!empty($this->id) && $this->id > 0) {
            $sa->updateInfo($data, $this->id);
        } else {
            $sa->addSurvey($data);
        }
    }

    /**
     * Get survey for a project
     *
     * @param Project $project Project instance
     *
     * @return Survey
     */
    static public function getByProjectAndUser($project, $user) {
        $sMan   = new Brigade_Db_Table_Survey();
        $survey = $sMan->getByProjectAndUser($project->id, $user->id);
        $obj    = null;
        if ($survey) {
            $obj = self::_populateObject($survey[0]);
        }
        return $obj;
    }

    public function isDegree($val) {
        if (!empty($this->degree)) {
            return (strpos($this->degree, $val) !== false);
        } else {
            return false;
        }
    }
}
