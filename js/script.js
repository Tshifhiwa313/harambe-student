/**
 * Main JavaScript file for Harambee Student Living Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Image upload preview
    const imageUpload = document.getElementById('imageUpload');
    const imagePreview = document.getElementById('imagePreview');
    
    if (imageUpload && imagePreview) {
        imageUpload.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                
                reader.addEventListener('load', function() {
                    imagePreview.innerHTML = `<img src="${this.result}" alt="Preview">`;
                });
                
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Mark all notifications as read
    const markAllReadBtn = document.querySelector('.mark-all-read');
    
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            fetch('/includes/mark_all_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI to reflect that all notifications are read
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    
                    // Remove the notification badge
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        badge.remove();
                    }
                }
            })
            .catch(error => console.error('Error marking notifications as read:', error));
        });
    }
    
    // Room availability counter in accommodation details
    const decrementRoomBtn = document.getElementById('decrementRoom');
    const incrementRoomBtn = document.getElementById('incrementRoom');
    const roomsAvailableInput = document.getElementById('roomsAvailable');
    
    if (decrementRoomBtn && incrementRoomBtn && roomsAvailableInput) {
        decrementRoomBtn.addEventListener('click', function() {
            const currentValue = parseInt(roomsAvailableInput.value);
            if (currentValue > 0) {
                roomsAvailableInput.value = currentValue - 1;
            }
        });
        
        incrementRoomBtn.addEventListener('click', function() {
            const currentValue = parseInt(roomsAvailableInput.value);
            roomsAvailableInput.value = currentValue + 1;
        });
    }
    
    // Form validation for required fields
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Password visibility toggle
    const togglePasswordBtns = document.querySelectorAll('.toggle-password');
    
    togglePasswordBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const passwordInput = document.querySelector(this.getAttribute('data-target'));
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
    
    // Date picker initialization
    const datePickers = document.querySelectorAll('.datepicker');
    
    datePickers.forEach(function(picker) {
        picker.addEventListener('focus', function(e) {
            e.target.type = 'date';
        });
        
        picker.addEventListener('blur', function(e) {
            if (!e.target.value) {
                e.target.type = 'text';
            }
        });
    });
    
    // Confirmation dialogs
    const confirmBtns = document.querySelectorAll('[data-confirm]');
    
    confirmBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
    
    // Print buttons
    const printBtns = document.querySelectorAll('.btn-print');
    
    printBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
    });
    
    // Dynamic form fields for maintenance requests
    const addFieldBtn = document.getElementById('addField');
    const additionalFieldsContainer = document.getElementById('additionalFields');
    
    if (addFieldBtn && additionalFieldsContainer) {
        let fieldCounter = 0;
        
        addFieldBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fieldCounter++;
            
            const newField = document.createElement('div');
            newField.className = 'mb-3 additional-field';
            newField.innerHTML = `
                <div class="input-group">
                    <input type="text" class="form-control" name="additional_fields[${fieldCounter}][key]" placeholder="Field Name">
                    <input type="text" class="form-control" name="additional_fields[${fieldCounter}][value]" placeholder="Field Value">
                    <button class="btn btn-outline-danger remove-field" type="button">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            additionalFieldsContainer.appendChild(newField);
            
            // Add event listener to the remove button
            newField.querySelector('.remove-field').addEventListener('click', function() {
                additionalFieldsContainer.removeChild(newField);
            });
        });
    }
    
    // Accommodation search functionality
    const searchInput = document.getElementById('accommodationSearch');
    const accommodationCards = document.querySelectorAll('.accommodation-card');
    
    if (searchInput && accommodationCards.length > 0) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            accommodationCards.forEach(function(card) {
                const accommodationName = card.querySelector('.card-title').textContent.toLowerCase();
                const accommodationAddress = card.querySelector('.accommodation-address').textContent.toLowerCase();
                
                if (accommodationName.includes(searchTerm) || accommodationAddress.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
    
    // File upload validation
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const file = this.files[0];
            const maxSize = this.getAttribute('data-max-size') || 5242880; // 5MB default
            const allowedTypes = this.getAttribute('data-allowed-types') ? this.getAttribute('data-allowed-types').split(',') : ['image/jpeg', 'image/png', 'image/gif'];
            
            if (file) {
                // Check file size
                if (file.size > maxSize) {
                    alert('File is too large. Maximum size is ' + (maxSize / 1048576) + 'MB.');
                    this.value = '';
                    return;
                }
                
                // Check file type
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Allowed types are: ' + allowedTypes.join(', '));
                    this.value = '';
                    return;
                }
            }
        });
    });
});

/**
 * Format a number as currency
 * 
 * @param {number} amount - The amount to format
 * @param {string} currencySymbol - The currency symbol to use
 * @returns {string} The formatted currency string
 */
function formatCurrency(amount, currencySymbol = 'R') {
    return currencySymbol + ' ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format a date string
 * 
 * @param {string} dateString - The date string to format
 * @param {string} format - The format to use (default: 'long')
 * @returns {string} The formatted date string
 */
function formatDate(dateString, format = 'long') {
    const date = new Date(dateString);
    
    if (isNaN(date.getTime())) {
        return dateString;
    }
    
    if (format === 'long') {
        return date.toLocaleDateString('en-ZA', { day: 'numeric', month: 'long', year: 'numeric' });
    } else if (format === 'short') {
        return date.toLocaleDateString('en-ZA');
    } else if (format === 'datetime') {
        return date.toLocaleDateString('en-ZA') + ' ' + date.toLocaleTimeString('en-ZA');
    }
    
    return dateString;
}

/**
 * Validate an email address
 * 
 * @param {string} email - The email address to validate
 * @returns {boolean} True if valid, false otherwise
 */
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validate a phone number
 * 
 * @param {string} phone - The phone number to validate
 * @returns {boolean} True if valid, false otherwise
 */
function isValidPhone(phone) {
    // Remove non-digit characters
    const phoneDigits = phone.replace(/\D/g, '');
    
    // Check if it has 10-15 digits
    return /^[0-9]{10,15}$/.test(phoneDigits);
}
