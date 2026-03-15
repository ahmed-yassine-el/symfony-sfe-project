// public/js/app.js
// Basic JavaScript functionality for ENCG Events

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuCheckbox = document.getElementById('menu');
    const navbar = document.querySelector('.navbar');

    if (menuCheckbox && navbar) {
        menuCheckbox.addEventListener('change', function() {
            if (this.checked) {
                navbar.style.display = 'flex';
            } else {
                navbar.style.display = 'none';
            }
        });
    }

    // Smooth scrolling for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Auto-hide mobile menu when clicking a link
    const navLinks = document.querySelectorAll('.navbar a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (menuCheckbox) {
                menuCheckbox.checked = false;
                navbar.style.display = 'none';
            }
        });
    });

    // Add loading animation for event boxes
    const eventBoxes = document.querySelectorAll('.box');
    eventBoxes.forEach((box, index) => {
        box.style.opacity = '0';
        box.style.transform = 'translateY(20px)';
        setTimeout(() => {
            box.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            box.style.opacity = '1';
            box.style.transform = 'translateY(0)';
        }, index * 100);
    });

    console.log('ENCG Events - JavaScript loaded successfully');
});