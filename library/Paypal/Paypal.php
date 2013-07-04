<?php

class Paypal_API {
        /********************************************
        PayPal Adaptive Payments API Module
         
        Defines all the global variables and the wrapper functions 
        ********************************************/
        protected $API_UserName = "";
        protected $API_Password = "";
        protected $API_Signature = "";
        protected $API_AppID = "";
        protected $API_Endpoint = "";
        protected $PROXY_HOST = '';
        protected $PROXY_PORT = '';
        protected $Env = "";
        protected $USE_PROXY = false;

        public function __construct($Env = "Production") {
            if (session_id() == "") {
                session_start();
            }

            if($Env == "sandbox"){
                $this->API_UserName = "oconno_1301865577_biz_api1.gmail.com";
                $this->API_Password = "KEXLND3JC37UEYA6";
                $this->API_Signature = "Ak0heJA2xOdn-VaMZQ75PumtUo3xAWykby5Cg2zIE44EuKDqFGhzX2fg";
                $this->API_AppID = "APP-80W284485P519543T";
                $this->API_Endpoint = "https://svcs.sandbox.paypal.com/AdaptivePayments";
            } else {
                $this->API_UserName = "oconnoroisin_api1.gmail.com";
                $this->API_Password = "3D8DG2F78EGR9ZH6";
                $this->API_Signature = "ATkgsxix5LZczjh7T.jcysTlEmizAZlgi9ExYWl.6gVRo-P-Bf3DjfKv";
                $this->API_AppID = "APP-7XA45342AG019270M";
                $this->API_Endpoint = "https://svcs.paypal.com/AdaptivePayments";
            }
        }
        public function generateCharacter () {
                $possible = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
                $char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
                return $char;
        }
        public function generateTrackingID () {
                $GUID = $this->generateCharacter().$this->generateCharacter().$this->generateCharacter().$this->generateCharacter().$this->generateCharacter();
                $GUID .= $this->generateCharacter().$this->generateCharacter().$this->generateCharacter().$this->generateCharacter();
                return $GUID;
        }
        /*
        '-------------------------------------------------------------------------------------------------------------------------------------------
        ' Purpose:      Prepares the parameters for the Refund API Call.
        '                       The API credentials used in a Pay call can make the Refund call
        '                       against a payKey, or a tracking id, or to specific receivers of a payKey or a tracking id
        '                       that resulted from the Pay call
        '
        '                       A receiver itself with its own API credentials can make a Refund call against the transactionId corresponding to their transaction.
        '                       The API credentials used in a Pay call cannot use transactionId to issue a refund
        '                       for a transaction for which they themselves were not the receiver
        '
        '                       If you do specify specific receivers, keep in mind that you must provide the amounts as well
        '                       If you specify a transactionId, then only the receiver of that transactionId is affected therefore
        '                       the receiverEmailArray and receiverAmountArray should have 1 entry each if you do want to give a partial refund
        ' Inputs:
        '
        ' Conditionally Required:
        '               One of the following:  payKey or trackingId or trasactionId or
        '                              (payKey and receiverEmailArray and receiverAmountArray) or
        '                              (trackingId and receiverEmailArray and receiverAmountArray) or
        '                              (transactionId and receiverEmailArray and receiverAmountArray)
        ' Returns: 
        '               The NVP Collection object of the Refund call response.
        '--------------------------------------------------------------------------------------------------------------------------------------------   
        */
        public function CallRefund( $payKey, $transactionId, $trackingId, $receiverEmailArray, $receiverAmountArray )
        {
                /* Gather the information to make the Refund call.
                        The variable nvpstr holds the name value pairs
                */
                
                $nvpstr = "";
                
                // conditionally required fields
                if ("" != $payKey)
                {
                        $nvpstr = "payKey=" . urlencode($payKey);
                        if (0 != count($receiverEmailArray))
                        {
                                reset($receiverEmailArray);
                                while (list($key, $value) = each($receiverEmailArray))
                                {
                                        if ("" != $value)
                                        {
                                                $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
                                        }
                                }
                        }
                        if (0 != count($receiverAmountArray))
                        {
                                reset($receiverAmountArray);
                                while (list($key, $value) = each($receiverAmountArray))
                                {
                                        if ("" != $value)
                                        {
                                                $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
                                        }
                                }
                        }
                }
                elseif ("" != $trackingId)
                {
                        $nvpstr = "trackingId=" . urlencode($trackingId);
                        if (0 != count($receiverEmailArray))
                        {
                                reset($receiverEmailArray);
                                while (list($key, $value) = each($receiverEmailArray))
                                {
                                        if ("" != $value)
                                        {
                                                $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
                                        }
                                }
                        }
                        if (0 != count($receiverAmountArray))
                        {
                                reset($receiverAmountArray);
                                while (list($key, $value) = each($receiverAmountArray))
                                {
                                        if ("" != $value)
                                        {
                                                $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
                                        }
                                }
                        }
                }
                elseif ("" != $transactionId)
                {
                        $nvpstr = "transactionId=" . urlencode($transactionId);
                        // the caller should only have 1 entry in the email and amount arrays
                        if (0 != count($receiverEmailArray))
                        {
                                reset($receiverEmailArray);
                                while (list($key, $value) = each($receiverEmailArray))
                                {
                                        if ("" != $value)
                                        {
                                                $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
                                        }
                                }
                        }
                        if (0 != count($receiverAmountArray))
                        {
                                reset($receiverAmountArray);
                                while (list($key, $value) = each($receiverAmountArray))
                                {
                                        if ("" != $value)
                                        {
                                                $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
                                        }
                                }
                        }
                }
                /* Make the Refund call to PayPal */
                $resArray = $this->hash_call("Refund", $nvpstr);
                /* Return the response array */
                return $resArray;
        }
        
        /*
        '-------------------------------------------------------------------------------------------------------------------------------------------
        ' Purpose:      Prepares the parameters for the PaymentDetails API Call.
        '                       The PaymentDetails call can be made with either
        '                       a payKey, a trackingId, or a transactionId of a previously successful Pay call.
        ' Inputs:
        '
        ' Conditionally Required:
        '               One of the following:  payKey or transactionId or trackingId
        ' Returns: 
        '               The NVP Collection object of the PaymentDetails call response.
        '--------------------------------------------------------------------------------------------------------------------------------------------   
        */
        public function CallPaymentDetails( $payKey, $transactionId, $trackingId )
        {
                /* Gather the information to make the PaymentDetails call.
                        The variable nvpstr holds the name value pairs
                */
                
                $nvpstr = "";
                
                // conditionally required fields
                if ("" != $payKey)
                {
                        $nvpstr = "payKey=" . urlencode($payKey);
                }
                elseif ("" != $transactionId)
                {
                        $nvpstr = "transactionId=" . urlencode($transactionId);
                }
                elseif ("" != $trackingId)
                {
                        $nvpstr = "trackingId=" . urlencode($trackingId);
                }
                /* Make the PaymentDetails call to PayPal */
                $resArray = $this->hash_call("PaymentDetails", $nvpstr);
                /* Return the response array */
                return $resArray;
        }
        /*
        '-------------------------------------------------------------------------------------------------------------------------------------------
        ' Purpose:      Prepares the parameters for the Pay API Call.
        ' Inputs:
        '
        ' Required:
        '
        ' Optional:
        '
        '               
        ' Returns: 
        '               The NVP Collection object of the Pay call response.
        '--------------------------------------------------------------------------------------------------------------------------------------------   
        */
        public function CallPay( $actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray,
                                                $receiverPrimaryArray, $receiverInvoiceIdArray, $feesPayer, $ipnNotificationUrl,
                                                $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $senderEmail, $trackingId )
        {
                /* Gather the information to make the Pay call.
                        The variable nvpstr holds the name value pairs
                */
                
                // required fields
                $nvpstr = "currencyCode=" . urlencode($currencyCode) . "&actionType=" . $actionType;
                $nvpstr .= "&returnUrl=" . urlencode($returnUrl) . "&cancelUrl=" . urlencode($cancelUrl);
                if (0 != count($receiverAmountArray))
                {
                        reset($receiverAmountArray);
                        while (list($key, $value) = each($receiverAmountArray))
                        {
                                if ("" != $value)
                                {
                                        $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
                                }
                        }
                }
                if (0 != count($receiverEmailArray))
                {
                        reset($receiverEmailArray);
                        while (list($key, $value) = each($receiverEmailArray))
                        {
                                if ("" != $value)
                                {
                                        $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
                                }
                        }
                }
                if (0 != count($receiverPrimaryArray))
                {
                        reset($receiverPrimaryArray);
                        while (list($key, $value) = each($receiverPrimaryArray))
                        {
                                if ("" != $value)
                                {
                                        $nvpstr = $nvpstr . "&receiverList.receiver(" . $key . ").primary=" . urlencode($value);
                                }
                        }
                }
                if (0 != count($receiverInvoiceIdArray))
                {
                        reset($receiverInvoiceIdArray);
                        while (list($key, $value) = each($receiverInvoiceIdArray))
                        {
                                if ("" != $value)
                                {
                                        $nvpstr = $nvpstr . "&receiverList.receiver(" . $key . ").invoiceId=" . urlencode($value);
                                }
                        }
                }
        
                // optional fields
                if ("" != $feesPayer)
                {
                        $nvpstr .= "&feesPayer=" . urlencode($feesPayer);
                }
                if ("" != $ipnNotificationUrl)
                {
                        $nvpstr .= "&ipnNotificationUrl=" . urlencode($ipnNotificationUrl);
                }
                if ("" != $memo)
                {
                        $nvpstr .= "&memo=" . urlencode($memo);
                }
                if ("" != $pin)
                {
                        $nvpstr .= "&pin=" . urlencode($pin);
                }
                if ("" != $preapprovalKey)
                {
                        $nvpstr .= "&preapprovalKey=" . urlencode($preapprovalKey);
                }
                if ("" != $reverseAllParallelPaymentsOnError)
                {
                        $nvpstr .= "&reverseAllParallelPaymentsOnError=" . urlencode($reverseAllParallelPaymentsOnError);
                }
                if ("" != $senderEmail)
                {
                        $nvpstr .= "&senderEmail=" . urlencode($senderEmail);
                }
                if ("" != $trackingId)
                {
                        $nvpstr .= "&trackingId=" . urlencode($trackingId);
                }
                /* Make the Pay call to PayPal */
                $resArray = $this->hash_call("Pay", $nvpstr);
                /* Return the response array */
                return $resArray;
        }
        /*
        '-------------------------------------------------------------------------------------------------------------------------------------------
        ' Purpose:      Prepares the parameters for the PreapprovalDetails API Call.
        ' Inputs:
        '
        ' Required:
        '               preapprovalKey:         A preapproval key that identifies the agreement resulting from a previously successful Preapproval call.
        ' Returns: 
        '               The NVP Collection object of the PreapprovalDetails call response.
        '--------------------------------------------------------------------------------------------------------------------------------------------   
        */
        public function CallPreapprovalDetails( $preapprovalKey )
        {
                /* Gather the information to make the PreapprovalDetails call.
                        The variable nvpstr holds the name value pairs
                */
                
                // required fields
                $nvpstr = "preapprovalKey=" . urlencode($preapprovalKey);
                /* Make the PreapprovalDetails call to PayPal */
                $resArray = $this->hash_call("PreapprovalDetails", $nvpstr);
                /* Return the response array */
                return $resArray;
        }
        
        /*
        '-------------------------------------------------------------------------------------------------------------------------------------------
        ' Purpose:      Prepares the parameters for the Preapproval API Call.
        ' Inputs:
        '
        ' Required:
        '
        ' Optional:
        '
        '               
        ' Returns: 
        '               The NVP Collection object of the Preapproval call response.
        '--------------------------------------------------------------------------------------------------------------------------------------------   
        */
        public function CallPreapproval( $returnUrl, $cancelUrl, $currencyCode, $startingDate, $endingDate, $maxTotalAmountOfAllPayments,
                                                                $senderEmail, $maxNumberOfPayments, $paymentPeriod, $dateOfMonth, $dayOfWeek,
                                                                $maxAmountPerPayment, $maxNumberOfPaymentsPerPeriod, $pinType )
        {
                /* Gather the information to make the Preapproval call.
                        The variable nvpstr holds the name value pairs
                */
                
                // required fields
                $nvpstr = "returnUrl=" . urlencode($returnUrl) . "&cancelUrl=" . urlencode($cancelUrl);
                $nvpstr .= "&currencyCode=" . urlencode($currencyCode) . "&startingDate=" . urlencode($startingDate);
                $nvpstr .= "&endingDate=" . urlencode($endingDate);
                $nvpstr .= "&maxTotalAmountOfAllPayments=" . urlencode($maxTotalAmountOfAllPayments);
                
                // optional fields
                if ("" != $senderEmail)
                {
                        $nvpstr .= "&senderEmail=" . urlencode($senderEmail);
                }
                if ("" != $maxNumberOfPayments)
                {
                        $nvpstr .= "&maxNumberOfPayments=" . urlencode($maxNumberOfPayments);
                }
                
                if ("" != $paymentPeriod)
                {
                        $nvpstr .= "&paymentPeriod=" . urlencode($paymentPeriod);
                }
                if ("" != $dateOfMonth)
                {
                        $nvpstr .= "&dateOfMonth=" . urlencode($dateOfMonth);
                }
                if ("" != $dayOfWeek)
                {
                        $nvpstr .= "&dayOfWeek=" . urlencode($dayOfWeek);
                }
                if ("" != $maxAmountPerPayment)
                {
                        $nvpstr .= "&maxAmountPerPayment=" . urlencode($maxAmountPerPayment);
                }
                if ("" != $maxNumberOfPaymentsPerPeriod)
                {
                        $nvpstr .= "&maxNumberOfPaymentsPerPeriod=" . urlencode($maxNumberOfPaymentsPerPeriod);
                }
                if ("" != $pinType)
                {
                        $nvpstr .= "&pinType=" . urlencode($pinType);
                }
                /* Make the Preapproval call to PayPal */
                $resArray = $this->hash_call("Preapproval", $nvpstr);
                /* Return the response array */
                return $resArray;
        }
        /*
          '-------------------------------------------------------------------------------------------------------------------------------------------
          * hash_call: Function to perform the API call to PayPal using API signature
          * @methodName is name of API method.
          * @nvpStr is nvp string.
          * returns an associative array containing the response from the server.
          '-------------------------------------------------------------------------------------------------------------------------------------------
        */
        private function hash_call($methodName, $nvpStr)
        {
                //declaring of global variables
                global $API_Endpoint, $API_UserName, $API_Password, $API_Signature, $API_AppID;
                global $USE_PROXY, $PROXY_HOST, $PROXY_PORT;
                $this->API_Endpoint .= "/" . $methodName;
                //setting the curl parameters.
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL,$this->API_Endpoint);
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                //turning off the server and peer verification(TrustManager Concept).
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
                curl_setopt($ch, CURLOPT_POST, 1);
                
                // Set the HTTP Headers
                curl_setopt($ch, CURLOPT_HTTPHEADER,  array(
                'X-PAYPAL-REQUEST-DATA-FORMAT: NV',
                'X-PAYPAL-RESPONSE-DATA-FORMAT: NV',
                'X-PAYPAL-SECURITY-USERID: ' . $this->API_UserName,
                'X-PAYPAL-SECURITY-PASSWORD: ' .$this->API_Password,
                'X-PAYPAL-SECURITY-SIGNATURE: ' . $this->API_Signature,
                'X-PAYPAL-SERVICE-VERSION: 1.3.0',
                'X-PAYPAL-APPLICATION-ID: ' . $this->API_AppID
                ));
        
            //if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
                //Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php 
                if($USE_PROXY)
                        curl_setopt ($ch, CURLOPT_PROXY, $PROXY_HOST. ":" . $PROXY_PORT); 
                // RequestEnvelope fields
                $detailLevel    = urlencode("ReturnAll");       // See DetailLevelCode in the WSDL for valid enumerations
                $errorLanguage  = urlencode("en_US");           // This should be the standard RFC 3066 language identification tag, e.g., en_US
                // NVPRequest for submitting to server
                $nvpreq = "requestEnvelope.errorLanguage=$errorLanguage&requestEnvelope.detailLevel=$detailLevel";
                $nvpreq .= "&$nvpStr";
                //setting the nvpreq as POST FIELD to curl
                curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
                //getting response from server
                $response = curl_exec($ch);
                if($response === false) { echo "<br>Curl error: ". curl_error($ch) . "<br />"; }
                //converting NVPResponse to an Associative Array
                $nvpResArray = $this->deformatNVP($response);
                $nvpReqArray = $this->deformatNVP($nvpreq);
                //print("RequestArray<br>");
                //print_r($nvpReqArray);
                //print("<br><br>ResponseArray:<br>");
                //print_r($nvpResArray);
                //print("<br><br>");
                $_SESSION['nvpReqArray']=$nvpReqArray;
                if (curl_errno($ch)) 
                {
                        // moving to display page to display curl errors
                          $_SESSION['curl_error_no']=curl_errno($ch) ;
                          $_SESSION['curl_error_msg']=curl_error($ch);
                          //Execute the Error handling module to display errors. 
                } 
                else 
                {
                         //closing the curl
                        curl_close($ch);
                }
                return $nvpResArray;
        }
        /*'----------------------------------------------------------------------------------
         Purpose: Redirects to PayPal.com site.
         Inputs:  $cmd is the querystring
         Returns: 
        ----------------------------------------------------------------------------------
        */
        public function RedirectToPayPal ( $cmd )
        {
                // Redirect to paypal.com here
                global $Env;
                $payPalURL = "";
                
                if ($Env == "sandbox") 
                {
                        $payPalURL = "https://www.sandbox.paypal.com/webscr?" . $cmd;
                }
                else
                {
                        $payPalURL = "https://www.paypal.com/webscr?" . $cmd;
                }
                header("Location: ".$payPalURL);
        }
        
        /*'----------------------------------------------------------------------------------
         * This function will take NVPString and convert it to an Associative Array and it will decode the response.
          * It is usefull to search for a particular key and displaying arrays.
          * @nvpstr is NVPString.
          * @nvpArray is Associative Array.
           ----------------------------------------------------------------------------------
          */
        public function deformatNVP($nvpstr)
        {
                $intial=0;
                $nvpArray = array();
                while(strlen($nvpstr))
                {
                        //postion of Key
                        $keypos= strpos($nvpstr,'=');
                        //position of value
                        $valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);
                        /*getting the Key and Value values and storing in a Associative Array*/
                        $keyval=substr($nvpstr,$intial,$keypos);
                        $valval=substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
                        //decoding the respose
                        $nvpArray[urldecode($keyval)] =urldecode( $valval);
                        $nvpstr=substr($nvpstr,$valuepos+1,strlen($nvpstr));
             }
                return $nvpArray;
        }
    }
?>
