/*  
JAVASCRIPT IMAGE GALLERY W/ mootools
Description: A easy, non destructive javascript image gala.
Version: 1.1.3
Author: Devin Ross
Author URI: http://tutorialdog.com
*/

/*
Release notes:
	1.1 - Adds loading animation, and properly fades in images when fully loaded
	1.1.1 - Fixes displaying description, Fades out current image, Works with Mootools 1.2
	1.1.2 - Corrects IE Problem with Transitioning to Next Image (Note: this fix undo's release 1.1's to fade image in smoother)
	1.1.3 - Corrects First Image Text Problem
*/


window.addEvent('domready', function() {
		
		
		// CHANGE THIS !!
		var slides = 10;		// NUMBER OF SLIDES IN SLIDESHOW, CHANGE ACCORDINGLY
		var pos = 0;
		var offset = 330;	// HOW MUCH TO SLIDE WITH EACH CLICK
		var currentslide = 1;	// CURRENT SLIDE IS THE FIRST SLIDE
		var inspector = $('fullimg');	// WHERE THE LARGE IMAGES WILL BE PLACE	
		var fx = new Fx.Morph(inspector, {duration: 300, transition: Fx.Transitions.Sine.easeOut});
 		var fx2 = new Fx.Morph(inspector, {duration: 200, transition: Fx.Transitions.Sine.easeOut});

		
		/* THUMBNAIL IMAGE SCROLL */
		var imgscroll = new Fx.Scroll('wrapper', {
   			offset: {'x': 10, 'y': 10},
   			transition: Fx.Transitions.Cubic.easeOut	// HOW THE SCROLLER SCROLLS
		}).toLeft();

	
		/* EVENTS - WHEN AN ARROW IS CLICKED THE THUMBNAILS SCROLL */
		$('moveleft').addEvent('click', function(event) { event = new Event(event).stop();
			if(currentslide == 1) return;
			currentslide--;					// CURRENT SLIDE IS ONE LESS
			pos += -(offset);				// CHANGE SCROLL POSITION
			imgscroll.start(pos);			// SCROLL TO NEW POSITION
		});
		$('moveright').addEvent('click', function(event) { event = new Event(event).stop();
			if(currentslide >= slides) return;
			currentslide++;
			pos += offset;
			imgscroll.start(pos);
		});
		
		/* WHEN AN ITEM IS CLICKED, IT INSERTS THE IMAGE INTO THE FULL VIEW DIV */
		$$('.item').each(function(item){ 
			item.addEvent('click', function(e) { 
				e = new Event(e).stop();
				fx2.start({ 
					'opacity' : 0													
				}).chain(function(){
					
					inspector.empty();		// Empty Stage
					var loadimg = 'images/ajax-loader.gif';	   // Reference to load gif
					var load = new Element('img', { 'src': loadimg, 'class': 'loading' }).inject(inspector); 
					fx2.start({ 'opacity' : 1 });
					var largeImage = new Element('img', { 'src': item.href }); // create large image
					
					/* When the large image is loaded, fade out, fade in with new image */
					//largeImage.onload = function(){  // While this line of code causes the images to load/transition in smoothly, it cause IE to stop working
						fx.start({ 
							'opacity' : 0													
						}).chain(function(){
							inspector.empty();	           				// empty stage
							var description = item.getElement('span');	// see if there is a description
							
							if(description)					   
								var des = new Element('p').set('text', description.get('text')).inject(inspector);
									
							largeImage.inject(inspector); // insert new image
							fx.start({'opacity': 1});	 // then bring opacity of elements back to visible				
						});
					//};
					
				});
			});
		});

		// INSERT THE INITAL IMAGE - LIKE ABOVE
		inspector.empty();
		var description = $('first').getElement('span');
		//if(description) var desc = new Element('p').setHTML(description.get('html')).inject(inspector);
		if(description) var des = new Element('p').set('text', description.get('text')).inject(inspector);
		var largeImage = new Element('img', {'src': $('first').href}).inject(inspector);
		
		/*inspector.empty();
		var description = $('first').getElement('span');
		if(description) var desc = new Element('p').setHTML(description.get('html')).inject(inspector);
		var largeImage = new Element('img', {'src': $('first').href}).inject(inspector);*/
	
});