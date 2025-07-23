document.querySelectorAll("site-nav").forEach(element => {
	let details = element.querySelector("details");
	let summary = details.querySelector("summary");
	let detailsDiv = details.querySelector("div");

	summaryAnim(details, summary, detailsDiv);
	desktopSidebar(details);
});

function summaryAnim(details, summary, detailsDiv) {
	summary.addEventListener("click", clickSummary);
	function clickSummary(e) {
		if(details.open) {
			e.preventDefault();
			detailsDiv.addEventListener("transitionend", transitionEnd);
			details.classList.add("closing");
		}
	}

	function transitionEnd() {
		detailsDiv.removeEventListener("transitionend", transitionEnd);
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
		// details.open = false;
		return;
	}

	details.open = true;
}
