import("@phpgt/flux");
import("./page/_common.es6");
import("./page/_component/docs-search.es6");
import("./page/_component/site-nav.es6");

if(location.hostname === "localhost") {
	document.querySelectorAll("article a").forEach(link => {
		setTimeout(() => {
			fetch(link.href)
				.then(response => {
					if(!response.ok) {
						link.classList.add("broken-link");
					}
				});
		})
	});
}
