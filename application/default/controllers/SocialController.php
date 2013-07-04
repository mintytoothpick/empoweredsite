<?php
require_once 'BaseController.php';
require_once 'Activity.php';

/**
 * Controller used to handle social actions of the website.
 *
 * @author Leonel Quinteros
 *
 */
class SocialController extends BaseController
{
    function init() {
        parent::init();
    }

    /**
     * Connects Facebook users with registered users.
     * Updates existent users with FB information
     * or creates new users usinf FB information.
     *
     * @author Leonel Quinteros
     */
    public function facebookconnectAction()
    {
        $this->_helper->layout()->disableLayout();

        // Check for Facebook user authenticated.
        if( !empty($this->fbUserInfo) ) {
            // Check for logged in user to integrate.
            if(!empty($this->sessionUser)) {
                $this->updateFacebookData($this->sessionUser);
            } else {
                // Check for existent email account from Facebook in DB. If so, integrate it.
                $checkUser = User::getByEmail($this->fbUserInfo['email']);

                if( !empty($checkUser) )
                {
                    // Activates account if needed.
                    if ($checkUser->isActive != 1) {
                        $checkUser->isActive = 1;
                    }
                    $this->updateFacebookData($checkUser);

                    $this->doLogin($checkUser);
                }
                else
                {
                    // Register new user using Facebook data
                    $newUser = new User();

                    $newUser->email                   = $this->fbUserInfo['email'];
                    $newUser->urlName                 = '';
                    $newUser->password                = md5(time());
                    $newUser->isActive                = 1;
                    $newUser->promptDetails           = 0; // WTF is this?
                    $newUser->currency                = '$';
                    $newUser->lastLogin               = '0000-00-00 00:00:00';
                    $newUser->firstLogin              = 0;
                    $newUser->percentageFee           = '';
                    $newUser->allowPercentageFee      = 'optional';
                    $newUser->paypalAccountId         = '0';
                    $newUser->googleCheckoutAccountId = '0';

                    $this->updateFacebookData($newUser);

                    // log the site activity
                    $activity              = new Activity();
                    $activity->siteId      = $newUser->id;
                    $activity->type        = 'User Joined';
                    $activity->createdById = $newUser->id;
                    $activity->date        = date('Y-m-d H:i:s');
                    $activity->save();

                    $this->doLogin($newUser);
                }
            }
        }
    }


    /**
     * Attempts to do login with Facebook account
     *
     * @author Leonel Quinteros
     */
    public function facebookloginAction() {
        $this->_helper->layout()->disableLayout();

        $response = array('success' => false);

        // Check for Facebook user authenticated.
        if( !empty($this->fbUserInfo) ) {
            $checkUser = User::getByFaceBookId($this->fbUserInfo['id']);
            
            if(empty($checkUser)) {
                // Check for existent email account from Facebook in DB. If so, integrate it.
                $checkUser = User::getByEmail($this->fbUserInfo['email']);
            }
            
            if( !empty($checkUser) ) {
                $this->updateFacebookData($checkUser);
                
                if($this->doLogin($checkUser)) {
                    $response['success'] = true;
                }
            }
        }

        echo json_encode($response);
    }

    /**
     * Takes a User model object and does login
     * TODO: Put repeated login code into a single method to reuse.
     *
     * @param User $userData
     */
    private function doLogin($userData) {
        $auth = Zend_Auth::getInstance();
        $authAdapter = new Brigade_Util_Auth();
        $authAdapter->setIdentity($userData->email)->setCredential($userData->password);
        $authResult = $auth->authenticate($authAdapter);

        if ($authResult->isValid()) {
            $userInfo = $authAdapter->_resultRow;

            if ($userInfo->Active == 1) {
                $_SESSION['FullName'] = $userInfo->FirstName . " " . $userInfo->LastName;
                $_SESSION['UserId']   = $userInfo->UserId;

                $cookie_name = 'siteAuth';
                $cookie_time = time() + 3600*24*30; // 30 days
                setcookie($cookie_name, 'user=' . $userData->email . '&hash=' . $userData->password, $cookie_time, '/');

                return true;
            }
        }

        return false;
    }


    /**
     * Takes a User model object and updates the user information using
     * Facebook connect data.
     *
     * @author Leonel Quinteros
     *
     * @param User $userData by reference
     */
    private function updateFacebookData(& $userData) {
        if($userData->faceBookId != $this->fbUserInfo['id'])
        {
            $userData->faceBookId = $this->fbUserInfo['id'];
        }
        if(empty($userData->webAddress))
        {
            $userData->webAddress = $this->fbUserInfo['link'];
        }
        if(is_null($userData->gender) || $userData->gender == 0)
        {
            if($this->fbUserInfo['gender'] == 'male')
            {
                $userData->gender = 1;
            }
            elseif($this->fbUserInfo['gender'] == 'female')
            {
                $userData->gender = 2;
            }
        }
        if(empty($userData->firstName) || preg_match('/@/', $userData->firstName))
        {
            $userData->firstName = $this->fbUserInfo['first_name'];
        }
        if(empty($userData->lastName)|| preg_match('/@/', $userData->lastName))
        {
            $userData->lastName = $this->fbUserInfo['last_name'];
        }
        if(empty($userData->fullName) || preg_match('/@/', $userData->fullName))
        {
            $userData->fullName = $this->fbUserInfo['name'];
        }
        if(empty($userData->location) || $userData->location == 'Not Entered')
        {
            if(!empty($this->fbUserInfo['location']) && !empty($this->fbUserInfo['location']['name'])) {
                $userData->location = $this->fbUserInfo['location']['name']; // Will not allways exist
            }
        }
        if(empty($userData->profleImage)) {
            @$image = file_get_contents('https://graph.facebook.com/' . $this->fbUserInfo['id'] . '/picture?type=large');
            if(!empty($image)) {
                $userData->profileImage = $image;
            }
        }
        
        $userData->isActive = 1;

        $userData->save();
    }

}
