/**
 * ResolveX - Front-end Logic
 * Main JavaScript file for UI interactions and responsiveness.
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('ResolveX Frontend Initialized');

    // Sidebar & Overlay Elements
    const menuToggle = document.getElementById('menu-toggle');
    const wrapper = document.getElementById('wrapper');
    const overlay = document.getElementById('sidebar-overlay');
    
    function toggleSidebar(forceClose = false) {
        if (forceClose) {
            wrapper.classList.remove('toggled');
        } else {
            wrapper.classList.toggle('toggled');
        }
        
        // Handle body scroll locking on mobile
        if (window.innerWidth <= 992) {
            if (wrapper.classList.contains('toggled')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        }
    }

    if (menuToggle && wrapper) {
        menuToggle.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            toggleSidebar(true);
        });
    }

    // Auto-close sidebar on mobile after clicking a link
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                toggleSidebar(true);
            }
        });
    });

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Custom File Upload display logic
    const fileInputs = document.querySelectorAll('.file-upload-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', (e) => {
            const fileName = e.target.files[0] ? e.target.files[0].name : '';
            const wrapper = input.closest('.file-upload-wrapper');
            const display = wrapper.querySelector('.file-name-info');
            const placeholder = wrapper.querySelector('.file-upload-text');
            
            if (fileName) {
                wrapper.classList.add('has-file');
                display.classList.remove('d-none');
                display.innerHTML = `<i class="fas fa-file-circle-check me-1"></i> Selected: ${fileName}`;
                placeholder.classList.add('d-none');
            } else {
                wrapper.classList.remove('has-file');
                display.classList.add('d-none');
                placeholder.classList.remove('d-none');
            }
        });
    });

    // Global Form Validation (Step 7)
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Generic Action Confirmation (Step 7)
    const confirmButtons = document.querySelectorAll('.confirm-action');
    confirmButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const message = btn.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Refined Alert auto-dismissal
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 600);
        }, 6000);
    });

    // Add "hover-translate" class effect via JS if needed for older browsers
    const interactiveElements = document.querySelectorAll('.hover-translate');
    interactiveElements.forEach(el => {
        el.addEventListener('mouseenter', () => {
             el.style.transform = 'translateY(-5px)';
        });
        el.addEventListener('mouseleave', () => {
             el.style.transform = 'translateY(0)';
        });
    });
});
