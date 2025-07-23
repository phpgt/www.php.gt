location.pathname === "/" && (function() {
	let introTitle = document.querySelector("article blockquote+h2");
	let introTitleFirstElementChild = introTitle.firstElementChild;
	let introTitleTextNode = introTitleFirstElementChild.nextSibling;

	introTitleTextNode.textContent.split(" ").forEach(word => {
		const span = document.createElement("span");
		span.textContent = word + " ";
		introTitle.lastChild.before(span);
	});

	introTitleTextNode.remove();
})();
