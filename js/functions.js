/*! outline.js v1.2.0 - https://github.com/lindsayevans/outline.js/ */
(function(d){

	var style_element = d.createElement('STYLE'),
	    dom_events = 'addEventListener' in d,
	    add_event_listener = function(type, callback){
			// Basic cross-browser event handling
			if(dom_events){
				d.addEventListener(type, callback);
			}else{
				d.attachEvent('on' + type, callback);
			}
		},
	    set_css = function(css_text){
			// Handle setting of <style> element contents in IE8
			!!style_element.styleSheet ? style_element.styleSheet.cssText = css_text : style_element.innerHTML = css_text;
		}
	;

	d.getElementsByTagName('HEAD')[0].appendChild(style_element);

	// Using mousedown instead of mouseover, so that previously focused elements don't lose focus ring on mouse move
	add_event_listener('mousedown', function(){
		set_css(':focus{outline:0}::-moz-focus-inner{border:0;}');
	});

	add_event_listener('keydown', function(){
		set_css('');
	});

})(document);

window.onload = function() {
	var screen = 767,
		duration = 500,
		opener = document.querySelector('.toggle-nav'),
		menu = document.querySelector('#menu-main-menu'),
		sub = document.querySelectorAll('.sub-menu');
	let slideUp = (el, duration = 500) => {
		el.style.transitionProperty = 'height, margin, padding';
		el.style.transitionDuration = duration + 'ms';
		el.style.boxSizing = 'border-box';
		el.style.height = el.offsetHeight + 'px';
		el.offsetHeight;
		el.style.overflow = 'hidden';
		el.style.height = 0;
		el.style.paddingTop = 0;
		el.style.paddingBottom = 0;
		el.style.marginTop = 0;
		el.style.marginBottom = 0;
		el.classList.remove('active');
		window.setTimeout(() => {
			el.style.display = null;
			el.style.removeProperty('height');
			el.style.removeProperty('padding-top');
			el.style.removeProperty('padding-bottom');
			el.style.removeProperty('margin-top');
			el.style.removeProperty('margin-bottom');
			el.style.removeProperty('overflow');
			el.style.removeProperty('transition-duration');
			el.style.removeProperty('transition-property');
		}, duration);
	}
	let slideDown = (el, duration = 500) => {
		el.style.removeProperty('display');
		let display = window.getComputedStyle(el).display;
		if(display === 'none') display = 'block';
		el.style.display = display;
		let height = el.offsetHeight;
		el.style.overflow = 'hidden';
		el.style.height = 0;
		el.style.paddingTop = 0;
		el.style.paddingBottom = 0;
		el.style.marginTop = 0;
		el.style.marginBottom = 0;
		el.offsetHeight;
		el.style.boxSizing = 'border-box';
		el.style.transitionProperty = "height, margin, padding";
		el.style.transitionDuration = duration + 'ms';
		el.style.height = height + 'px';
		el.style.removeProperty('padding-top');
		el.style.removeProperty('padding-bottom');
		el.style.removeProperty('margin-top');
		el.style.removeProperty('margin-bottom');
		el.classList.add('active');
		window.setTimeout(() => {
			el.style.removeProperty('height');
			el.style.removeProperty('overflow');
			el.style.removeProperty('transition-duration');
			el.style.removeProperty('transition-property');
		}, duration);
	}
	var slideToggle = (el, duration = 500) => {
			if(window.getComputedStyle(el).display === 'none') {
				return slideDown(el, duration);
			} else {
				return slideUp(el, duration);
			}
		},
		submenus = function() {
			document.querySelectorAll('.sub-menu').forEach((el) => {
				var handler = el.parentElement.getElementsByTagName('a')[0],
					parent = el.parentElement;
				handler.setAttribute('aria-expanded', 'false');
				
					handler.addEventListener('click', function(e) {
						e.preventDefault();
            if(window.innerWidth < screen) {
						var x = handler.getAttribute('aria-expanded');
						if(x == 'true') {
							x = 'false';
							parent.classList.remove('active');
						} else {
							x = 'true';
							parent.classList.add('active');
						}
						handler.setAttribute('aria-expanded', x);
						slideToggle(el, duration);
            }
					}, false);
				
			});
		},
		close = function() {
			[].forEach.call(sub, function(el) {
				el.parentElement.getElementsByTagName('a')[0].setAttribute('aria-expanded', 'false');
				el.parentElement.classList.remove('active');
				if(window.innerWidth < screen) {
					slideUp(el, duration);
				} else {
					menu.style.display = null;
					el.style.display = null;
				}
			});
		},
		cross = function() {
			if(menu.classList.contains('active')) {
				opener.classList.add('cross');
        opener.setAttribute('aria-expanded', 'true');
			} else {
				opener.classList.remove('cross');
        opener.setAttribute('aria-expanded', 'false');
				close();
			}
		};
	opener.addEventListener('click', function() {
		slideToggle(menu, duration);
		cross();
	});
	submenus();
	window.addEventListener('resize', close, false);
}
/*Infinite Load */
document.addEventListener("DOMContentLoaded", function() {
	function getPage(from, to) {
		var button = document.querySelector('.load-more'),
			url = button.href,
			spinner = document.querySelector('.spinner');
		spinner.style.display = 'block';
		if(!from) {
			from = "body";
		}
		if(to && to.split) {
			to = document.querySelector(to);
		}
		if(!to) {
			to = document.querySelector(from);
		}
		var XHRt = new XMLHttpRequest;
		XHRt.responseType = 'document';
		XHRt.onload = function() {
			if(this.readyState == 4 && this.status == 200) {
				to.insertAdjacentHTML('beforeend', XHRt.response.querySelector(from).innerHTML);
				var max = document.querySelector('.load-more').getAttribute('data-pages'),
					current = url.match(/\/page\/(\d*)/)[1],
					next = url.replace(/(\d+)\/$/, function(x) {
						return parseInt(x, 10) + 1 + '/'
					});
				if(current < max) {
					button.setAttribute('href', next);
				} else {
					//button.classList.add('disabled');
					//button.style.display = 'none';
					//button.setAttribute('href', '#');
					button.remove();
				}
				spinner.style.display = 'none';
			}
		};
		XHRt.open("GET", url, true);
		XHRt.send();
		return XHRt;
	}
	document.querySelector('.load-more').addEventListener('click', function(e) {
		e.preventDefault();
		getPage('.post_container', '.post_container');
	}, false);
}); 