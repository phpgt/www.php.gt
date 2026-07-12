let input;
let easterEggCount = 0;

document.querySelectorAll("docs-search").forEach(element => {
	input = element.querySelector("input");
	input.addEventListener("focus", checkContent);
	input.addEventListener("focus", easterEgg);

	document.addEventListener("keydown", slashListener);
});

function checkContent() {
	if(input.value.length >= 2) {
		input.form.dispatchEvent(new Event("input"));
	}
}

function easterEgg() {
	if(input.value.length === 0) {
		easterEggCount = 0;
		return;
	}
	
	easterEggCount++;

	if(easterEggCount > 40) {
		if(input.dataset.originalPlaceholder) {
			input.placeholder = input.dataset.originalPlaceholder;
			delete input.dataset.originalPlaceholder;
		}
	}
	else if(easterEggCount > 35) {///////////////////////////
		input.placeholder = "I'm going to go away now..."
	}
	else if(easterEggCount > 30) {///////////////////////////
		input.placeholder = "Your clicking is insatiable!"
	}
	else if(easterEggCount > 25) {///////////////////////////
		input.placeholder = "You are something else! 🙀"
	}
	else if(easterEggCount > 20) {///////////////////////////
		input.placeholder = "Seriously... 😾";
	}
	else if(easterEggCount > 15) {///////////////////////////
		input.placeholder = "That's enough now... 😾";
	}
	else if(easterEggCount > 10) {///////////////////////////
		input.placeholder = "That's enough now...";
	}
	else if(easterEggCount > 5) {///////////////////////////
		input.placeholder = "Glad you're enjoying 😻";
	}
	else if(easterEggCount > 3) {
		if(!input.dataset.originalPlaceholder) {
			input.dataset.originalPlaceholder = input.placeholder;
		}

		input.placeholder = "Cool, isn't it???";
	}
}

function slashListener(event) {
	console.log(event.key);

	if(document.activeElement instanceof HTMLInputElement || document.activeElement instanceof HTMLTextAreaElement) {
		if(event.key === "Escape") {
			input.blur();
		}
	}
	else {
		if(event.key === "/") {
			event.preventDefault();
			input.focus();
		}
	}
}
