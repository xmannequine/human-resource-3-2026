// Sticky Header and Navbar Toggle
const header = document.querySelector("header");
window.addEventListener("scroll", function(){
    header.classList.toggle("sticky", window.scrollY > 0 );
});

let menu = document.querySelector('#menu-icon');
let navbar = document.querySelector('.navbar');
menu.onclick = () => {
    menu.classList.toggle('bx-x');
    navbar.classList.toggle('open');
};
window.onscroll = () => {
    menu.classList.remove('bx-x');
    navbar.classList.remove('open');
};

// Scroll Reveal
const sr = ScrollReveal({
     distance:'60px',
     duration:2500,
     delay:400,
     reset:true
});
sr.reveal('.home-text', {delay:200, origin:'top'});
sr.reveal('.home-img', {delay:300, origin:'top'});
sr.reveal('.feature,.product,.cta-content, .contact', {delay:200, origin:'top'});

// Dropdown Menu
document.addEventListener("DOMContentLoaded", function() {
    const dropdown = document.querySelector(".dropdown > a");
    const dropdownMenu = document.querySelector(".dropdown-menu");

    dropdown.addEventListener("click", function(e) {
        e.preventDefault();
        dropdownMenu.classList.toggle("show");
    });

    document.addEventListener("click", function(e) {
        if (!dropdown.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove("show");
        }
    });
});

// ✅ Hero Slideshow with Dots
let slides = document.querySelectorAll(".hero-slides .slide");
let dotsContainer = document.querySelector(".hero-dots");
let currentSlide = 0;
let slideInterval = setInterval(nextSlide, 3000);

function showSlide(index) {
  slides.forEach(slide => slide.classList.remove("active"));
  const dots = document.querySelectorAll(".hero-dots .dot");
  dots.forEach(dot => dot.classList.remove("active"));

  currentSlide = (index + slides.length) % slides.length;
  slides[currentSlide].classList.add("active");
  dots[currentSlide].classList.add("active");
}

function nextSlide() {
  showSlide(currentSlide + 1);
}

// Click dots
dotsContainer.querySelectorAll(".dot").forEach(dot => {
  dot.addEventListener("click", () => {
    const index = parseInt(dot.dataset.index);
    showSlide(index);
    resetInterval();
  });
});

function resetInterval() {
  clearInterval(slideInterval);
  slideInterval = setInterval(nextSlide, 3000);
}

// ✅ Login Modal
const userIcon = document.querySelector('.ri-user-line').parentElement;
const loginModal = document.getElementById('loginModal');
const closeLogin = document.getElementById('closeLogin');

userIcon.addEventListener('click', e => {
    e.preventDefault();
    loginModal.style.display = 'flex';
    setTimeout(() => loginModal.classList.add('show'), 10);
});

closeLogin.addEventListener('click', () => {
    loginModal.classList.remove('show');
    setTimeout(() => loginModal.style.display = 'none', 400);
});

loginModal.addEventListener('click', e => {
    if (e.target === loginModal) {
        loginModal.classList.remove('show');
        setTimeout(() => loginModal.style.display = 'none', 400);
    }
});

// Password Toggle
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    togglePassword.className = isPassword ? 'ri-eye-off-line' : 'ri-eye-line';
});

// ✅ Signup Modal
const signupModal = document.getElementById('signupModal');
const closeSignup = document.getElementById('closeSignup');
const openSignup = document.getElementById('openSignup');
const openLoginFromSignup = document.getElementById('openLoginFromSignup');

openSignup.addEventListener('click', e => {
    e.preventDefault();
    loginModal.classList.remove('show');
    setTimeout(() => {
        loginModal.style.display = 'none';
        signupModal.style.display = 'flex';
        setTimeout(() => signupModal.classList.add('show'), 10);
    }, 400);
});

closeSignup.addEventListener('click', () => {
    signupModal.classList.remove('show');
    setTimeout(() => signupModal.style.display = 'none', 400);
});

openLoginFromSignup.addEventListener('click', e => {
    e.preventDefault();
    signupModal.classList.remove('show');
    setTimeout(() => {
        signupModal.style.display = 'none';
        loginModal.style.display = 'flex';
        setTimeout(() => loginModal.classList.add('show'), 10);
    }, 400);
});

signupModal.addEventListener('click', e => {
    if (e.target === signupModal) {
        signupModal.classList.remove('show');
        setTimeout(() => signupModal.style.display = 'none', 400);
    }
});

// Toggle Signup Password
document.querySelector('.toggleSignupPassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('newPassword');
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    this.className = isPassword ? 'ri-eye-off-line toggleSignupPassword' : 'ri-eye-line toggleSignupPassword';
});

// ✅ Search Toggle
const searchToggle = document.getElementById('search-toggle');
const searchBar = document.getElementById('search-bar');

searchToggle.addEventListener('click', e => {
  e.preventDefault();
  searchBar.classList.toggle('show');
});