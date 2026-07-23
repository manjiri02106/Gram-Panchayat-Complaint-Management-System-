/* assets/js/main.js */
/* Gram Panchayat Complaint Management System - Core Client Side Scripts */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Initialize Real-Time Clock in Header
    const timeElement = document.getElementById('live-time');
    if (timeElement) {
        function updateTime() {
            const now = new Date();
            
            // Format options
            const dateOptions = { day: '2-digit', month: 'short', year: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
            
            const dateStr = now.toLocaleDateString('en-IN', dateOptions);
            const timeStr = now.toLocaleTimeString('en-IN', timeOptions);
            
            timeElement.innerHTML = `<i class="far fa-calendar-alt"></i> ${dateStr} &nbsp; <i class="far fa-clock"></i> ${timeStr}`;
        }
        updateTime();
        setInterval(updateTime, 1000);
    }

    // 2. Sidebar Toggle for Mobile Devices
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside of it on mobile
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('active');
            }
        });
    }

    // 3. Header Dropdowns Toggle (Notifications & Profile)
    setupDropdown('notif-trigger', 'notif-dropdown');
    setupDropdown('profile-trigger', 'profile-dropdown');
    
    function setupDropdown(triggerId, dropdownId) {
        const trigger = document.getElementById(triggerId);
        const dropdown = document.getElementById(dropdownId);
        
        if (trigger && dropdown) {
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                // Close other dropdowns first
                closeAllDropdowns(dropdownId);
                dropdown.classList.toggle('active');
            });
        }
    }
    
    function closeAllDropdowns(exceptId = '') {
        const dropdowns = ['notif-dropdown', 'profile-dropdown'];
        dropdowns.forEach(id => {
            if (id !== exceptId) {
                const element = document.getElementById(id);
                if (element) {
                    element.classList.remove('active');
                }
            }
        });
    }
    
    // Close dropdowns on clicking anywhere on the document
    document.addEventListener('click', function() {
        closeAllDropdowns();
    });

    // 4. Modal Dialog Operations
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Disable scroll under modal
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto'; // Restore scroll
        }
    };
    
    // Attach close handlers to all modal close buttons and overlays
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
        
        const closeBtn = modal.querySelector('.modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeModal(modal.id);
            });
        }
    });

    // 5. Dynamic Client Side Table Filtering
    const searchInput = document.getElementById('table-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const filterValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.table tbody tr');
            
            tableRows.forEach(row => {
                let match = false;
                const cells = row.querySelectorAll('td');
                
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(filterValue)) {
                        match = true;
                    }
                });
                
                if (match) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 6. Citizen Complaint Image Upload Preview
    const imageInput = document.getElementById('complaint-image');
    const imagePreview = document.getElementById('image-preview-container');
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = `
                        <p class="form-text">Image Preview:</p>
                        <img src="${e.target.result}" class="complaint-img-preview" alt="Complaint photo preview">
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.innerHTML = '';
            }
        });
    }
});
