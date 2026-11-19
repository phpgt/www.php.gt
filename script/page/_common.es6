let threshold = null;

window.addEventListener("resize", () => threshold = null);

window.addEventListener("scroll", e => {
	if(!threshold) {
		let desktop = matchMedia("(min-width: 60rem)").matches;
		let fixedHeadingElement;

		if(!desktop) {
			fixedHeadingElement = document.querySelector("site-nav");
		}
		else {
			fixedHeadingElement = document.querySelector("docs-search");
		}

		if(!matchMedia("(min-width: 32rem)").matches) {
			threshold = fixedHeadingElement.offsetTop;
		}
		else {
			threshold = fixedHeadingElement.offsetHeight;
		}

		document.body.style.setProperty('--site-fixed-heading', `${fixedHeadingElement.offsetHeight}px`);
	}

	if(threshold && scrollY > threshold) {
		document.body.classList.add("scrolled");
	}
	else {
		document.body.classList.remove("scrolled");
	}
})
