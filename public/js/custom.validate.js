$(function() {
    // date greater than current date
    $.validator.addMethod(
        "dateGT",
        function(dateVal, element, currDate) {
            dateVal = dateVal.split('/');
            if (typeof(currDate) != 'string') {
                var currDate = currDate.val().toString();
            }
            currDate = currDate.split('/');
            dateVal = new Date(dateVal[2], dateVal[0], dateVal[1]);
            currDate = new Date(currDate[2], currDate[0], currDate[1]);
            return this.optional(element) || (dateVal >= currDate);
        },
        "Start date must be greater than the current date."
    );

    // date and time greater than current date
    $.validator.addMethod(
        "dateTimeGT",
        function(dateVal, element, currDate) {
            dateVal = dateVal.split('/');

            timeVal = null;
            if ($("#EndTime").val()) {
                timeVal=getTime($("#EndTime").val());
            }
            initialTimeVal = null;
            if ($("#StartTime").val()) {
                initialTimeVal = getTime($("#StartTime").val());
            }
            if (typeof(currDate) != 'string') {
                var currDate = currDate.val().toString();
            }
            currDate = currDate.split('/');
            dateVal = new Date(dateVal[2], dateVal[0], dateVal[1]);
            currDateVal = new Date(dateVal.valueOf());
            currDateVal.setYear(currDate[2]);
            currDateVal.setMonth(currDate[0]);
            currDateVal.setDate(currDate[1]);

            isValid = dateVal > currDateVal ||
                           dateVal.valueOf()==currDateVal.valueOf()  && (initialTimeVal == null || timeVal == null ||
                                    initialTimeVal.getHours() < timeVal.getHours() ||
                                        (
                                            initialTimeVal.getHours() == timeVal.getHours() &&
                                            initialTimeVal.getMinutes() <= timeVal.getMinutes()
                                        )
                                );

            return this.optional(element) || isValid;
        },
        "End date must be greater than the start date."
    );

    //validator for dates formats
    $.validator.addMethod(
        "dateFormat",
        function(value,element) {
            return value.match(/^(0[1-9]|1[012])[- //.](0[1-9]|[12][0-9]|3[01])[- //.](19|20)\d\d$/);
        },
        "Please enter a date in the format mm/dd/yyyy"
    );

    //validate values differents of <val>
    $.validator.addMethod(
        "notEqualTo",
        function(value, element, valueParam) {
            return this.optional(element) || value != valueParam;
        },
        "Please check your input value."
    );

    jQuery.validator.addMethod("simpleUrl", function(value, element, param) {
        return this.optional(element) || /^((https?|ftp):\/\/)?(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(\#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i.test(value);
    }, jQuery.validator.messages.url);

    //Add a domain-check rule
    jQuery.validator.addMethod ("domainChk", function (value, element, params) {
            if (this.optional(element))
                return true;
            var regExp      = new RegExp ("^((https?|ftp)://|(www|ftp)\.)[a-z0-9-]+(\.[a-z0-9-]+)+([/?].*)?$", "i");
            return regExp.test(value);
        },
        function errmess (params, element) {
            return "This must be a valid URL for " + $(element).attr ("rel");
        }
    );
    jQuery.validator.addClassRules ( { domainChk: {domainChk: true} } );

    //credit card expiration date
    //params: month & year
    $.validator.addMethod(
        "creditCardExpDate",
        function(value, element, params) {
            var minMonth = new Date().getMonth() + 1;
            var minYear = new Date().getFullYear();
            var $month = $(params.cardExpMonth);
            var $year = $(params.cardExpYear);

            var month = parseInt($(params.month).val(), 10);
            var year = parseInt($(params.year).val(), 10);

            if ((year > minYear) || ((year === minYear) && (month >= minMonth))) {
                return true;
            } else {
                return false;
            }
        },
        "Your credit card expiration date is invalid."
    );

    //credit card not amex allowed
    $.validator.addMethod(
        "notAmex",
        function(value, element, params) {
            return !(creditCardCompany(value) == 'Amex');
        },
        "We currently don't accept Amex. Please use another credit card."
    );
});

function getTime(value) {
    var timeObj = new Date();
    var time = value.match(/(\d+)\s*[:\-\.,]\s*(\d+)\s*(am|pm)?/);
    timeObj.setHours( parseInt(time[1]) + (time[3] ? 12 : 0) );
    timeObj.setMinutes( parseInt(time[2]) || 0 );
    return timeObj;
}

function creditCardCompany(num) {
   num = num.replace(/[^\d]/g,'');
   if (num.match(/^5[1-5]\d{14}$/)) {
     return 'MasterCard';
   } else if (num.match(/^4\d{15}/) || num.match(/^4\d{12}/)) {
     return 'Visa';
   } else if (num.match(/^3[47]\d{13}/)) {
     return 'Amex';
   } else if (num.match(/^6011\d{12}/)) {
     return 'Discover';
   }
   return 'undefined';
 }
