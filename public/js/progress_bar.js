// Administry object setup
if (!Administry) var Administry = {}

// progress() - animate a progress bar "el" to the value "val"
Administry.progress = function (el, val, max) {
    var duration = 400;
    var span = $(el).find("span");
    var b = $(el).find("b");
    var w;
    if (max == 0 && val == 0) {
        w = 0;
    } else if ((max == 0 && val > 0) || max == 0){
    	w = 100;
    } else {
    	w = Math.round((val / max) * 100);
    }
    if (w > 0) {
        $(b).fadeOut('fast');
        var wd = val>max? 100 : w
        $(span).animate({
            width: wd + '%'
        }, duration, function () {
            $(el).attr("value", val);
            if (val <= max && max > 0) {
                $(b).text(w + '%').fadeIn('fast');
            }
        });
    } else {
        $(b).text(w+'%');
    }
}
