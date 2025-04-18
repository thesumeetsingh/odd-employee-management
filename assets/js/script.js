document.addEventListener('DOMContentLoaded', function() {
    // Console confirmation
    console.log('Organised Design Desk - Architecture Studio');
    
    // Smooth scroll for any anchor links (can be used later)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
});