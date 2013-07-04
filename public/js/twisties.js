// Copyright 2009 Google Inc. All Rights Reserved.
var goog=goog||{};goog.ui=goog.ui||{};goog.ui.Twisties=function(a){this.classCollapse=a.classCollapse;this.classExpand=a.classExpand;this.intervalCollapse=a.intervalCollapse||15;this.intervalExpand=a.intervalExpand||20;this.intervalFade=a.intervalFade||15;this.enableFade=a.enableFade||false;var b,newLabel,i;var c=new RegExp("(^|\\s)"+a.classTwisty+"(\\s|$)");var d=a.parentId?document.getElementById(a.parentId):document;var e=(a.twistyTag)?d.getElementsByTagName(a.twistyTag):(d.all)?d.all:d.getElementsByTagName('*');for(i=e.length-1;i>=0;i--){if(c.test(e[i].className)){label=e[i];b=label.nextSibling;while(b&&(b.nodeType!=1)){b=b.nextSibling}if(b){if(label.tagName!='A'){newLabel=document.createElement('A');newLabel.innerHTML=label.innerHTML;label.innerHTML='';label.appendChild(newLabel);label=newLabel}b.currentHeight=b.offsetHeight;b.style.display='none';b.style.height='0';this.setOpacity(b,0);label.className+=' '+this.classCollapse;label.expandedFlag=false;label.onclick=this.createClickHandler(this,b,label);label.href='javascript:void 0'}}}};goog.ui.Twisties.prototype.createClickHandler=function(a,b,c){return function(){if(c.expandedFlag){c.className=c.className.replace(a.classExpand,a.classCollapse);b.currentHeight=b.offsetHeight;a.setOpacity(b,0);a.animate(b,6,false)}else{b.style.display='block';c.className=c.className.replace(a.classCollapse,a.classExpand);a.animate(b,7,true,b.currentHeight)}c.expandedFlag=!c.expandedFlag}};goog.ui.Twisties.prototype.animate=function(a,b,c,d){if(b>0){var e=(c)?(a.offsetHeight+(d-a.offsetHeight)/2):a.offsetHeight/2;a.style.height=Math.round(e)+'px';var f=this;window.setTimeout(function(){f.animate(a,b-1,c,d)},(c)?this.intervalExpand:this.intervalCollapse)}else{if(c){if(this.enableFade){this.setOpacity(a,0)}else{this.setOpacity(a,100)}a.style.height='';if(this.enableFade){this.fadeIn(a,4)}}else{a.style.display='none'}}};goog.ui.Twisties.prototype.fadeIn=function(a,b){if(b>0){this.setOpacity(a,100-(b/4*100));var c=this;window.setTimeout(function(){c.fadeIn(a,b-1)},this.intervalFade)}else{this.setOpacity(a,100)}};goog.ui.Twisties.prototype.setOpacity=function(a,b){if(typeof(document.body.style.opacity)!='undefined'){a.style.opacity=b/100}if(typeof(document.body.style.MozOpacity)!='undefined'){a.style.MozOpacity=b/100}if(typeof(document.body.style.filter)!='undefined'){a.style.filter='alpha(opacity='+b+')'}};