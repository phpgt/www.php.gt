import("@phpgt/flux");
import("./page/_common.es6");
import("./page/index.es6");
import("./page/_component/docs-search.es6");
import("./page/_component/site-nav.es6");
import("./service-worker/runtime.es6");

let offline = !navigator.onLine;

function showOffline(value) {
	offline = value;
	for(const element of document.querySelectorAll("docs-search, docs-search-results")) {
		element.classList.toggle("offline", offline);
	}
	for(const input of document.querySelectorAll("docs-search input")) {
		if(offline && !input.hasAttribute("data-online-placeholder")) {
			input.dataset.onlinePlaceholder = input.placeholder;
			input.dataset.onlineValue = input.value;
			input.placeholder = "You are offline";
			input.value = "";
			input.readOnly = true;
		}
		else if(!offline && input.hasAttribute("data-online-placeholder")) {
			input.placeholder = input.dataset.onlinePlaceholder;
			input.value = input.dataset.onlineValue;
			input.readOnly = false;
			delete input.dataset.onlinePlaceholder;
			delete input.dataset.onlineValue;
		}
		input.closest("label").querySelector("span").textContent = offline ? "You are offline" : "Search this site";
	}
}

showOffline(!navigator.onLine);
window.addEventListener("offline", () => showOffline(true));
window.addEventListener("online", () => showOffline(false));
document.addEventListener("flux:after-render", () => showOffline(offline));
// Keep the offline control focusable, without submitting an unavailable search.
for(const type of ["input", "submit"]) {
	document.addEventListener(type, event => {
		if(offline && event.target.closest("docs-search")) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}, true);
}
navigator.serviceWorker?.addEventListener("message", event => {
	if(event.data?.type === "connection-status") {
		showOffline(!navigator.onLine || event.data.offline);
	}
});
