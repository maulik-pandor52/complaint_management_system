// main.js - Global Javascript Utilities

document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Sidebar Toggle Logic
    const toggleBtn = document.getElementById('menu-toggle');
    const wrapper = document.getElementById('wrapper');
    const sidebar = document.getElementById('sidebar-wrapper');
    
    if (toggleBtn && wrapper && sidebar) {
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // Toggle sidebar hiding by adjusting margin or width via a class
            // Since we set max-width:260px, we can toggle a class that sets it to 0
            wrapper.classList.toggle('toggled');
            if (wrapper.classList.contains('toggled')) {
                sidebar.style.marginLeft = '-260px';
            } else {
                sidebar.style.marginLeft = '0';
            }
        });
        
        // Auto-close sidebar on mobile devices on load
        if (window.innerWidth <= 992) {
            wrapper.classList.add('toggled');
            sidebar.style.marginLeft = '-260px';
        }
    }

    // 2. Dark/Light Theme Toggle Logic
    const themeToggleBtn = document.getElementById('theme-toggle');
    const body = document.body;
    const icon = themeToggleBtn ? themeToggleBtn.querySelector('i') : null;

    // Check for saved theme preference in localStorage
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        body.setAttribute('data-bs-theme', 'dark');
        if (icon) { icon.classList.replace('fa-moon', 'fa-sun'); }
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            if (body.getAttribute('data-bs-theme') === 'dark') {
                body.removeAttribute('data-bs-theme');
                localStorage.setItem('theme', 'light');
                if (icon) { icon.classList.replace('fa-sun', 'fa-moon'); }
            } else {
                body.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                if (icon) { icon.classList.replace('fa-moon', 'fa-sun'); }
            }
        });
    }

    // 3. Flash Message Auto-Hide
    const flashMessages = document.querySelectorAll('.alert-auto-dismiss');
    flashMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 300);
        }, 5000);
    });

    // 4. Global File Upload Validation (Ensuring max 2MB size)
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileSize = this.files[0].size / 1024 / 1024; // in MB
                if (fileSize > 2) {
                    alert('File is too large! Maximum allowed size is 2MB.');
                    this.value = ''; // Clear the invalid file
                }
            }
        });
    });

    // 5. Confirm Delete / Actions Prompt
    const dangerButtons = document.querySelectorAll('.btn-danger, .btn-logout');
    dangerButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if(this.textContent.toLowerCase().includes('delete') || this.textContent.toLowerCase().includes('deactivate') || this.hasAttribute('data-confirm')) {
                if(!confirm('Are you sure you want to perform this action?')) {
                    e.preventDefault();
                }
            }
        });
    });

    // 6. Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
