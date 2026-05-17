// Background slider (only runs when .slide elements exist)
(function () {
    var slides = document.querySelectorAll('.slide');
    if (!slides.length) return;
    var index = 0;
    function showNextSlide() {
        slides[index].classList.remove('active');
        index = (index + 1) % slides.length;
        slides[index].classList.add('active');
    }
    setInterval(showNextSlide, 6000);
})();

// Hero rotating headline + subtitle
const heroSlides = [
    {
        title: "African Think Tank\nPlatform Administration",
        subtitle: "ATTP is the central hub connecting African Union think tanks, research institutions, and policy bodies to drive evidence-based governance across the continent."
    },
    {
        title: "Where African Ideas\nShape African Policy",
        subtitle: "The African Think Tank Platform brings together Africa's brightest research minds to co-create solutions for the continent's most pressing development challenges."
    },
    {
        title: "Strengthening Africa's\nPolicy Ecosystem",
        subtitle: "ATTP empowers AU member states with coordinated think tank insights, institutional research, and strategic policy support for sustainable continental growth."
    },
    {
        title: "Knowledge. Research.\nImpact.",
        subtitle: "The African Think Tank Platform Administration bridges the gap between policy research and real-world implementation across 55 African Union member states."
    },
    {
        title: "One Continent.\nOne Think Tank Platform.",
        subtitle: "Uniting Africa's leading think tanks under a single platform to advance governance, transparency, and evidence-driven decision-making for the African Union."
    }
];

const typeEl     = document.getElementById("typewriter");
const subtitleEl = document.getElementById("hero-subtitle");

let heroIndex    = 0;
let charIndex    = 0;
let isDeleting   = false;
let typeSpeed    = 65;
let holdTimer    = null;

function runTypewriter() {
    if (!typeEl) return;

    const current = heroSlides[heroIndex];
    const raw     = current.title;

    if (!isDeleting) {
        // Type next character (treat \n as <br>)
        charIndex++;
        typeEl.innerHTML = raw.substring(0, charIndex).replace(/\n/g, "<br>");

        if (charIndex === raw.length) {
            // Finished typing — show subtitle, hold, then delete
            if (subtitleEl) {
                subtitleEl.textContent = current.subtitle;
                subtitleEl.classList.add("sub-visible");
            }
            holdTimer = setTimeout(function () {
                isDeleting = true;
                if (subtitleEl) subtitleEl.classList.remove("sub-visible");
                setTimeout(runTypewriter, 400);
            }, 3800);
            return;
        }
    } else {
        // Erase one character at a time from the raw string (not from innerHTML)
        charIndex--;
        typeEl.innerHTML = raw.substring(0, charIndex).replace(/\n/g, "<br>");

        if (charIndex === 0) {
            isDeleting = false;
            heroIndex  = (heroIndex + 1) % heroSlides.length;
            typeSpeed  = 65;
            setTimeout(runTypewriter, 300);
            return;
        }
    }

    typeSpeed = isDeleting ? 35 : 65;
    setTimeout(runTypewriter, typeSpeed);
}

window.addEventListener("load", function () {
    if (typeEl) {
        setTimeout(runTypewriter, 400);
    }
});



