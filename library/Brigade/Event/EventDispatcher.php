<?php

/**
 *
 * This class is used to register Listeners to the different event that could be dispatched.
 * @author daniel
 *
 */
class EventDispatcher {

    public static $ORGANIZATION_CREATED          = "organization_created";
    public static $USER_CREATE_ACTION            = "user_create_action";
    public static $CHAPTER_CREATED_BY_USER       = "chapter_created_by_user";
    public static $FILE_SHARED                   = "file_shared";
    public static $USER_REGISTERED               = "user_registered";
    public static $FORGOT_PASSWORD               = "forgot_password";
    public static $REACTIVATE_USER               = "reactivate_user";
    public static $VOLUNTEER_REQUEST             = "volunteer_request";
    public static $VOLUNTEER_ACCEPTED            = "volunteer_accepted";
    public static $AWAITING_REQUEST              = "awaiting_request";
    public static $DONATION_RECEIPT              = "donation_receipt";
    public static $ADDED_SITE_ANNOUNCEMENT       = "added_site_announcement";
    public static $NONPROFIT_SIGNUP_NOTIFICATION = "nonprofit_signup_notification";
    public static $GROUP_EMAIL_VERIFICATION      = "group_email_verification";
    public static $GROUP_NOTIFICATION            = "group_notification";
    public static $GROUP_MEMBER_NOTIFICATION     = "group_member_notification";
    public static $SEND_UPLOADED_MEMBER          = "send_uploaded_member";
    public static $ADDED_NEW_ADMIN               = "added_new_admin";
    public static $ADDED_EXISTING_ADMIN          = "added_existing_admin";
    public static $UPLOAD_NEW_VOLUNTEER          = "upload_new_volunteer";
    public static $UPLOAD_EXISTING_VOLUNTEER     = "upload_existing_volunteer";
    public static $UPLOAD_NEW_FUNDRAISER         = "upload_new_fundraiser";
    public static $UPLOAD_EXISTING_FUNDRAISER    = "upload_existing_fundraiser";
    public static $DONATION_CANCELLED            = "donation_cancelled";
    public static $MESSAGE_TO_ADMIN              = "msg_to_admin";
    public static $VOLUNTEER_QUIT                = "volunteer_quit";
    public static $ORGANIZATION_NOMINATION       = "organization_nomination";
    public static $MEMBERSHIP_TURN_OFF           = "membership_turn_off";
    public static $PROJECT_DONATION_UPDATED      = "project_donation_updated";
    public static $CONTACT_US_GETSTARTED         = "contact_us_getstarted";
    public static $MEMBERSHIP_REMOVE             = "membership_remove";

    private $map;

    function addListener($eventName, $callback) {
        $this->map[$eventName][] = $callback;
    }

    function dispatchEvent($eventName, $data = null) {
        $result = false;
        foreach ($this->map[$eventName] as $callback) {
            $result = call_user_func_array($callback, $data);
            if (!$result) {
                 //LOG ERRROR!
            }
        }
        return $result;
    }

}
