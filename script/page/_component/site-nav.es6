import { navigationDrawer } from "../../pattern/navigation-drawer.es6";

document.querySelectorAll("site-nav details").forEach(details => {
	navigationDrawer(details, { desktopQuery: "(min-width: 60rem)" });
});
