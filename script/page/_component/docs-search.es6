let input;
let easterEggCount = 0;

document.querySelectorAll("docs-search").forEach(element => {
	input = element.querySelector("input");
	input.addEventListener("focus", easterEgg);
});

function easterEgg() {
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
