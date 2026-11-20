let hexHue = Math.floor(Math.random() * 360);
let hexResetTimer;

function colourHex() {
	// Keep successive colours visibly different, even across the hue boundary.
	hexHue = (hexHue + 60 + Math.floor(Math.random() * 240)) % 360;
	// The root survives Flux replacing the demo with the greeting response.
	document.documentElement.style.setProperty(
		"--example-hex-color", `hsl(${hexHue} 90% 50%)`
	);
	clearTimeout(hexResetTimer);
	hexResetTimer = setTimeout(() => {
		document.documentElement.style.removeProperty("--example-hex-color");
	}, 650);
}

document.addEventListener("submit", event => {
	if(!(event.target instanceof HTMLFormElement)
		|| !event.target.matches("#example-output form")) {
		return;
	}

	colourHex();
});
