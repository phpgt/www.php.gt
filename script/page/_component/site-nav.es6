document.querySelectorAll("site-nav").forEach(element => {
	let details = element.querySelector("details");
	let summary = details.querySelector("summary");

	summary.addEventListener("click", clickSummary);
	desktopSidebar(details);
	autoCloseOnNav(summary);
});

function clickSummary(e) {
	let screenBreak = getComputedStyle(document.documentElement).getPropertyValue("--break");

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
		.getPropertyValue("--transition-time");

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

function desktopSidebar(details, skipResizeEvent = false) {
	if(!skipResizeEvent) {
		window.addEventListener("resize", function() {
			desktopSidebar(details, true);
		});
	}

	let screenBreak = getComputedStyle(document.documentElement).getPropertyValue("--break");

	if(screenBreak === "none" || screenBreak === "small") {
		details.open = false;
		return;
	}

	details.open = true;
}

function autoCloseOnNav(summary) {
	let screenBreak = getComputedStyle(document.documentElement).getPropertyValue("--break");
	if(screenBreak === "none" || screenBreak === "small") {
		summary.parentElement.querySelectorAll("li a").forEach(link => {
			link.addEventListener("click", e => {
				clickSummary.apply(summary);
			});
		});
	}
}
