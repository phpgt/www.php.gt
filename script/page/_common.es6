let threshold = null;

window.addEventListener("scroll", e => {
	if(!threshold) {
		let mediaBreak = getComputedStyle(document.documentElement).getPropertyValue("--break");
		let fixedHeadingElement = document.querySelector("site-nav");

		if(mediaBreak === "none") {
			threshold = fixedHeadingElement.offsetTop;
		}
		else {
			threshold = null;
		}

		document.body.style.setProperty('--size-fixed-heading', `${fixedHeadingElement.offsetHeight}px`);
	}

	if(threshold && scrollY > threshold) {
		document.body.classList.add("scrolled");
	}
	else {
		document.body.classList.remove("scrolled");
	}
})
