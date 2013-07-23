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
class SurveyGlobalStudentEmbassy extends Base {

    public $id;
    public $userId;
    public $groupId;
    public $projectId;
    public $firstName;
    public $middleName;
    public $lastName;
    public $preferredName;
    public $dateBirth;
    public $address;
    public $participantCellNum;
    public $participantEmail;
    public $gradeYearSchool;
    public $gender;
    public $parent1;
    public $parent2;
    public $parentAddress;
    public $emergencyName1;
    public $emergencyRelation1;
    public $emergencyEmail1;
    public $emergencyDayPhone1;
    public $emergencyEveningPhone1;
    public $emergencyCellPhone1;
    public $emergencyName2;
    public $emergencyRelation2;
    public $emergencyEmail2;
    public $emergencyDayPhone2;
    public $emergencyEveningPhone2;
    public $emergencyCellPhone2;
    public $bleedingClottingDisorders;
    public $asthma;
    public $diabetes;
    public $earInfections;
    public $heartDefectsHypertension;
    public $psychiatricTreatment;
    public $seizureDisorder;
    public $immunoCompromised;
    public $sleepWalking;
    public $bedWetting;
    public $hospitalizedLast5Years;
    public $chickenPox;
    public $measles;
    public $mumps;
    public $otherDiseases;
    public $dateLastTetanusShot;
    public $hayFever;
    public $iodine;
    public $mangos;
    public $poisonOak;
    public $penicillin;
    public $beesWaspsInsects;
    public $food;
    public $otherAllergies;
    public $epinephrinePen;
    public $inhaler;
    public $explanation;
    public $passport;
    public $passportCountry;
    public $passportName;
    public $passportExpirationDate;
    public $countryBirth;
    public $citizenship;
    public $grade;
    public $GPA;
    public $spanishListening;
    public $spanishReadingWriting;
    public $spanishSpeaking;
    public $traveledOutsideUS;
    public $traveledDevelopingWorld;
    public $experiences;
    public $signatureName;
    public $signatureParentName;
    public $fundraisingSupportMaterials;
    public $date;

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
            $obj            = new self;
            $obj->id        = $data['Id'];
            $obj->userId    = $data['UserId'];
            $obj->groupId   = $data['GroupId'];
            $obj->projectId = $data['ProjectId'];

            $obj->firstName                   = $data['FirstName'];
            $obj->middleName                  = $data['MiddleName'];
            $obj->lastName                    = $data['LastName'];
            $obj->preferredName               = $data['PreferredName'];
            $obj->dateBirth                   = $data['DateBirth'];
            $obj->address                     = $data['Address'];
            $obj->participantCellNum          = $data['ParticipantCellNum'];
            $obj->participantEmail            = $data['ParticipantEmail'];
            $obj->gradeYearSchool             = $data['GradeYearSchool'];
            $obj->gender                      = $data['Gender'];
            $obj->parent1                     = $data['Parent1'];
            $obj->parent2                     = $data['Parent2'];
            $obj->parentAddress               = $data['ParentAddress'];
            $obj->emergencyName1              = $data['EmergencyName1'];
            $obj->emergencyRelation1          = $data['EmergencyRelation1'];
            $obj->emergencyEmail1             = $data['EmergencyEmail1'];
            $obj->emergencyDayPhone1          = $data['EmergencyDayPhone1'];
            $obj->emergencyEveningPhone1      = $data['EmergencyEveningPhone1'];
            $obj->emergencyCellPhone1         = $data['EmergencyCellPhone1'];
            $obj->emergencyName2              = $data['EmergencyName2'];
            $obj->emergencyRelation2          = $data['EmergencyRelation2'];
            $obj->emergencyEmail2             = $data['EmergencyEmail2'];
            $obj->emergencyDayPhone2          = $data['EmergencyDayPhone2'];
            $obj->emergencyEveningPhone2      = $data['EmergencyEveningPhone2'];
            $obj->emergencyCellPhone2         = $data['EmergencyCellPhone2'];
            $obj->bleedingClottingDisorders   = $data['BleedingClottingDisorders'];
            $obj->asthma                      = $data['Asthma'];
            $obj->diabetes                    = $data['Diabetes'];
            $obj->earInfections               = $data['EarInfections'];
            $obj->heartDefectsHypertension    = $data['HeartDefectsHypertension'];
            $obj->psychiatricTreatment        = $data['PsychiatricTreatment'];
            $obj->seizureDisorder             = $data['SeizureDisorder'];
            $obj->immunoCompromised           = $data['ImmunoCompromised'];
            $obj->sleepWalking                = $data['SleepWalking'];
            $obj->bedWetting                  = $data['BedWetting'];
            $obj->hospitalizedLast5Years      = $data['HospitalizedLast5Years'];
            $obj->chickenPox                  = $data['ChickenPox'];
            $obj->measles                     = $data['Measles'];
            $obj->mumps                       = $data['Mumps'];
            $obj->otherDiseases               = $data['OtherDiseases'];
            $obj->dateLastTetanusShot         = $data['DateLastTetanusShot'];
            $obj->hayFever                    = $data['HayFever'];
            $obj->iodine                      = $data['Iodine'];
            $obj->mangos                      = $data['Mangos'];
            $obj->poisonOak                   = $data['PoisonOak'];
            $obj->penicillin                  = $data['Penicillin'];
            $obj->beesWaspsInsects            = $data['BeesWaspsInsects'];
            $obj->food                        = $data['Food'];
            $obj->otherAllergies              = $data['OtherAllergies'];
            $obj->epinephrinePen              = $data['EpinephrinePen'];
            $obj->inhaler                     = $data['Inhaler'];
            $obj->explanation                 = $data['Explanation'];
            $obj->passport                    = $data['Passport'];
            $obj->passportCountry             = $data['PassportCountry'];
            $obj->passportName                = $data['PassportName'];
            $obj->passportExpirationDate      = $data['PassportExpirationDate'];
            $obj->countryBirth                = $data['CountryBirth'];
            $obj->citizenship                 = $data['Citizenship'];
            $obj->grade                       = $data['Grade'];
            $obj->GPA                         = $data['GPA'];
            $obj->spanishListening            = $data['SpanishListening'];
            $obj->spanishReadingWriting       = $data['SpanishReadingWriting'];
            $obj->spanishSpeaking             = $data['SpanishSpeaking'];
            $obj->traveledOutsideUS           = $data['TraveledOutsideUS'];
            $obj->traveledDevelopingWorld     = $data['TraveledDevelopingWorld'];
            $obj->experiences                 = $data['Experiences'];
            $obj->signatureName               = $data['SignatureName'];
            $obj->signatureParentName         = $data['SignatureParentName'];
            $obj->fundraisingSupportMaterials = $data['FundraisingSupportMaterials'];
            $obj->date                        = $data['date'];
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
            'UserId'                      => $this->userId,
            'GroupId'                     => $this->groupId,
            'ProjectId'                   => $this->projectId,
            'FirstName'                   => $this->firstName,
            'MiddleName'                  => $this->middleName,
            'LastName'                    => $this->lastName,
            'PreferredName'               => $this->preferredName,
            'DateBirth'                   => $this->dateBirth,
            'Address'                     => $this->address,
            'ParticipantCellNum'          => $this->participantCellNum,
            'ParticipantEmail'            => $this->participantEmail,
            'GradeYearSchool'             => $this->gradeYearSchool,
            'Gender'                      => $this->gender,
            'Parent1'                     => $this->parent1,
            'Parent2'                     => $this->parent2,
            'ParentAddress'               => $this->parentAddress,
            'EmergencyName1'              => $this->emergencyName1,
            'EmergencyRelation1'          => $this->emergencyRelation1,
            'EmergencyEmail1'             => $this->emergencyEmail1,
            'EmergencyDayPhone1'          => $this->emergencyDayPhone1,
            'EmergencyEveningPhone1'      => $this->emergencyEveningPhone1,
            'EmergencyCellPhone1'         => $this->emergencyCellPhone1,
            'EmergencyName2'              => $this->emergencyName2,
            'EmergencyRelation2'          => $this->emergencyRelation2,
            'EmergencyEmail2'             => $this->emergencyEmail2,
            'EmergencyDayPhone2'          => $this->emergencyDayPhone2,
            'EmergencyEveningPhone2'      => $this->emergencyEveningPhone2,
            'EmergencyCellPhone2'         => $this->emergencyCellPhone2,
            'BleedingClottingDisorders'   => $this->bleedingClottingDisorders,
            'Asthma'                      => $this->asthma,
            'Diabetes'                    => $this->diabetes,
            'EarInfections'               => $this->earInfections,
            'HeartDefectsHypertension'    => $this->heartDefectsHypertension,
            'PsychiatricTreatment'        => $this->psychiatricTreatment,
            'SeizureDisorder'             => $this->seizureDisorder,
            'ImmunoCompromised'           => $this->immunoCompromised,
            'SleepWalking'                => $this->sleepWalking,
            'BedWetting'                  => $this->bedWetting,
            'HospitalizedLast5Years'      => $this->hospitalizedLast5Years,
            'ChickenPox'                  => $this->chickenPox,
            'Measles'                     => $this->measles,
            'Mumps'                       => $this->mumps,
            'OtherDiseases'               => $this->otherDiseases,
            'DateLastTetanusShot'         => $this->dateLastTetanusShot,
            'HayFever'                    => $this->hayFever,
            'Iodine'                      => $this->iodine,
            'Mangos'                      => $this->mangos,
            'PoisonOak'                   => $this->poisonOak,
            'Penicillin'                  => $this->penicillin,
            'BeesWaspsInsects'            => $this->beesWaspsInsects,
            'Food'                        => $this->food,
            'OtherAllergies'              => $this->otherAllergies,
            'EpinephrinePen'              => $this->epinephrinePen,
            'Inhaler'                     => $this->inhaler,
            'Explanation'                 => $this->explanation,
            'Passport'                    => $this->passport,
            'PassportCountry'             => $this->passportCountry,
            'PassportName'                => $this->passportName,
            'PassportExpirationDate'      => $this->passportExpirationDate,
            'CountryBirth'                => $this->countryBirth,
            'Citizenship'                 => $this->citizenship,
            'Grade'                       => $this->grade,
            'GPA'                         => $this->GPA,
            'SpanishListening'            => $this->spanishListening,
            'SpanishReadingWriting'       => $this->spanishReadingWriting,
            'SpanishSpeaking'             => $this->spanishSpeaking,
            'TraveledOutsideUS'           => $this->traveledOutsideUS,
            'TraveledDevelopingWorld'     => $this->traveledDevelopingWorld,
            'Experiences'                 => $this->experiences,
            'SignatureName'               => $this->signatureName,
            'SignatureParentName'         => $this->signatureParentName,
            'FundraisingSupportMaterials' => $this->fundraisingSupportMaterials,
            'date'                        => $this->date
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
        $sMan   = new Brigade_Db_Table_SurveyGlobalStudentEmbassy();
        $survey = $sMan->getByProjectAndUser($project->id, $user->id);
        $obj    = null;
        if ($survey) {
            $obj = self::_populateObject($survey[0]);
        }
        return $obj;
    }

}
