let threshold = null;

window.addEventListener("scroll", e => {
	if(!threshold) {
		let desktop = matchMedia("(min-width: 60rem)").matches;
		let fixedHeadingElement;

		fixedHeadingElement = document.querySelector(desktop ? "docs-search" : "site-nav");

		if(innerWidth < 32 * parseFloat(getComputedStyle(document.documentElement).fontSize)) {
			threshold = fixedHeadingElement.offsetTop;
		}
		else {
			threshold = fixedHeadingElement.offsetHeight;
		}

		document.body.style.setProperty('--site-fixed-heading-size', `${fixedHeadingElement.offsetHeight}px`);
	}

	if(threshold && scrollY > threshold) {
		document.body.classList.add("scrolled");
	}
	else {
		document.body.classList.remove("scrolled");
	}
})
