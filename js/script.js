/**
 * Harambee Student Living Management System
 * Main JavaScript file
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // File input display filename
    const fileInputs = document.querySelectorAll('.custom-file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const fileName = this.files[0].name;
            const nextSibling = this.nextElementSibling;
            nextSibling.innerText = fileName;
        });
    });
    
    // Image preview for accommodation uploads
    const imageUpload = document.getElementById('accommodation_image');
    const imagePreview = document.getElementById('image_preview');
    
    if (imageUpload && imagePreview) {
        imageUpload.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.addEventListener('load', function() {
                    imagePreview.src = this.result;
                    imagePreview.style.display = 'block';
                });
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    
    if (togglePassword && passwordField) {
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
    
    // Maintenance request form validation
    const maintenanceForm = document.getElementById('maintenance_form');
    
    if (maintenanceForm) {
        maintenanceForm.addEventListener('submit', function(event) {
            const issueField = document.getElementById('issue');
            const descriptionField = document.getElementById('description');
            
            if (issueField.value.trim() === '') {
                event.preventDefault();
                alert('Please enter the issue title');
                issueField.focus();
                return false;
            }
            
            if (descriptionField.value.trim() === '') {
                event.preventDefault();
                alert('Please enter a description of the issue');
                descriptionField.focus();
                return false;
            }
        });
    }
    
    // Application form validation
    const applicationForm = document.getElementById('application_form');
    
    if (applicationForm) {
        applicationForm.addEventListener('submit', function(event) {
            const firstNameField = document.getElementById('first_name');
            const lastNameField = document.getElementById('last_name');
            const phoneField = document.getElementById('phone');
            
            if (firstNameField && firstNameField.value.trim() === '') {
                event.preventDefault();
                alert('Please enter your first name');
                firstNameField.focus();
                return false;
            }
            
            if (lastNameField && lastNameField.value.trim() === '') {
                event.preventDefault();
                alert('Please enter your last name');
                lastNameField.focus();
                return false;
            }
            
            if (phoneField && phoneField.value.trim() === '') {
                event.preventDefault();
                alert('Please enter your phone number');
                phoneField.focus();
                return false;
            }
        });
    }
    
    // Print functionality for invoices and leases
    const printButtons = document.querySelectorAll('.btn-print');
    
    printButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
    });
    
    // Date picker initialization for forms
    const datePickers = document.querySelectorAll('.datepicker');
    
    datePickers.forEach(input => {
        // We're using the browser's built-in date picker
        // This is just to ensure proper formatting
        input.addEventListener('change', function() {
            const date = new Date(this.value);
            if (!isNaN(date.getTime())) {
                const formattedDate = date.toISOString().split('T')[0];
                this.value = formattedDate;
            }
        });
    });
    
    // Bulk notification selection
    const selectAllCheckbox = document.getElementById('select_all');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.recipient-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Confirmation dialogs
    const confirmActions = document.querySelectorAll('[data-confirm]');
    
    confirmActions.forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Auto calculate invoice amounts based on rent and duration
    const rentField = document.getElementById('monthly_rent');
    const durationField = document.getElementById('duration');
    const totalField = document.getElementById('total_amount');
    
    if (rentField && durationField && totalField) {
        const calculateTotal = function() {
            const rent = parseFloat(rentField.value) || 0;
            const duration = parseInt(durationField.value) || 0;
            const total = rent * duration;
            totalField.value = total.toFixed(2);
        };
        
        rentField.addEventListener('input', calculateTotal);
        durationField.addEventListener('input', calculateTotal);
    }
});
