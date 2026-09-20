document.querySelectorAll("site-nav").forEach(element => {
	let details = element.querySelector("details");
	let summary = details.querySelector("summary");
	let desktopMedia = matchMedia("(min-width: 60rem)");

	summary.addEventListener("click", clickSummary);
	desktopSidebar(details, desktopMedia);
	autoCloseOnNav(summary, desktopMedia);
});

function clickSummary(e) {
	let summary = this;
	let details = summary.closest("details");
	details.classList.add("clicked");
	let detailsDiv = details.querySelector(":scope > div");

	if(details.open) {
		if(e) { e.preventDefault(); }

		details.classList.add("closing");
		setTimeout(() => {
			details.classList.remove("closing");
			details.open = false;
		}, getTransitionTime(detailsDiv));
	}
}

function getTransitionTime(element) {
	let transitionTime = getComputedStyle(element)
		.getPropertyValue("--site-nav-duration");

	return cssTimeToMilliseconds(transitionTime);
}

function cssTimeToMilliseconds(value) {
	value = value.trim();

	if(value.endsWith("ms")) {
		return Number.parseFloat(value);
	}

	if(value.endsWith("s")) {
		return Number.parseFloat(value) * 1000;
	}

	return 0;
}

function desktopSidebar(details, desktopMedia) {
	let update = () => details.open = desktopMedia.matches;
	desktopMedia.addEventListener("change", update);
	update();
}

function autoCloseOnNav(summary, desktopMedia) {
	summary.parentElement.querySelectorAll("li a").forEach(link => {
		link.addEventListener("click", () => {
			if(!desktopMedia.matches) {
				clickSummary.apply(summary);
			}
		});
	});
}
