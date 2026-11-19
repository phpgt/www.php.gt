// Pair with style/pattern/navigation-drawer.scss. The caller owns the selector
// and the desktop breakpoint; this module knows nothing about website routes.
export function navigationDrawer(details, { desktopQuery = "(min-width: 60rem)" } = {}) {
	const summary = details.querySelector(":scope > summary");
	const desktop = matchMedia(desktopQuery);
	let closeTimer;

	function cancelClose() {
		clearTimeout(closeTimer);
		details.classList.remove("closing");
	}

	function close(event) {
		if(desktop.matches || !details.open) return;
		event?.preventDefault();
		cancelClose();
		details.classList.add("closing");
		const duration = getComputedStyle(details).getPropertyValue("--drawer-duration").trim();
		const milliseconds = parseFloat(duration) * (duration.endsWith("ms") ? 1 : 1000);
		closeTimer = setTimeout(() => {
			details.open = false;
			details.classList.remove("closing");
		}, Number.isFinite(milliseconds) ? milliseconds : 0);
	}

	function resize() {
		cancelClose();
		details.open = desktop.matches;
	}

	summary.addEventListener("click", event => {
		if(details.open) close(event);
	});
	details.addEventListener("click", event => {
		if(event.target.closest("a[href]")) close();
	});
	details.addEventListener("keydown", event => {
		if(event.key === "Escape" && details.open && !desktop.matches) {
			close(event);
			summary.focus();
		}
	});
	desktop.addEventListener("change", resize);
	resize();
}
