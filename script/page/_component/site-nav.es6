document.querySelectorAll("site-nav").forEach(element => {
	let details = element.querySelector("details");
	let summary = details.querySelector("summary");

	summaryAnim(summary);
	desktopSidebar(details);
});

function summaryAnim(summary) {
	summary.addEventListener("click", clickSummary);
	function clickSummary(e) {
		let summary = e.currentTarget;
		let details = summary.closest("details");
		let detailsDiv = details.querySelector(":scope > div");

		if(details.open) {
			e.preventDefault();
			detailsDiv.addEventListener("transitionend", transitionEnd);
			details.classList.add("closing");
		}
	}

	function transitionEnd(e) {
		let detailsDiv = e.currentTarget;
		let details = detailsDiv.closest("details");

		detailsDiv.removeEventListener("transitionend", transitionEnd);
		if(!details.classList.contains("closing")) {
			return;
		}

		details.classList.remove("closing");
		details.open = false;
	}
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
