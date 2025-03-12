<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';
require_once 'includes/email.php';
require_once 'includes/sms.php';

// Require admin or master admin role
requireRole([ROLE_MASTER_ADMIN, ROLE_ADMIN], 'index.php');

$error = '';
$success = '';

$isMasterAdmin = hasRole(ROLE_MASTER_ADMIN);

// Process notification sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $recipients = $_POST['recipients'] ?? [];
    $notificationType = $_POST['notification_type'] ?? '';
    $accommodationId = intval($_POST['accommodation_id'] ?? 0);
    
    if (empty($subject) || empty($message) || empty($notificationType)) {
        $error = 'Please fill all required fields.';
    } elseif (empty($recipients) && $notificationType == 'selected') {
        $error = 'Please select at least one recipient.';
    } else {
        $recipientEmails = [];
        $recipientPhones = [];
        
        // Get recipients based on selection type
        if ($notificationType == 'all_students') {
            // Get all student emails
            if ($isMasterAdmin) {
                $students = fetchAll("SELECT email, phone FROM users WHERE role = ?", [ROLE_STUDENT]);
            } else {
                // Get students for this admin's accommodations
                $adminAccommodations = getAdminAccommodations($_SESSION['user_id']);
                $adminAccommodationIds = array_column($adminAccommodations, 'id');
                
                if (!empty($adminAccommodationIds)) {
                    $placeholders = implode(',', array_fill(0, count($adminAccommodationIds), '?'));
                    $students = fetchAll(
                        "SELECT DISTINCT u.email, u.phone 
                        FROM users u
                        JOIN leases l ON u.id = l.user_id
                        WHERE u.role = ? AND l.accommodation_id IN ($placeholders)", 
                        array_merge([ROLE_STUDENT], $adminAccommodationIds)
                    );
                } else {
                    $students = [];
                }
            }
            
            foreach ($students as $student) {
                $recipientEmails[] = $student['email'];
                if (!empty($student['phone'])) {
                    $recipientPhones[] = $student['phone'];
                }
            }
        } elseif ($notificationType == 'accommodation' && $accommodationId > 0) {
            // Get students for specific accommodation
            $students = fetchAll(
                "SELECT DISTINCT u.email, u.phone 
                FROM users u
                JOIN leases l ON u.id = l.user_id
                WHERE u.role = ? AND l.accommodation_id = ?", 
                [ROLE_STUDENT, $accommodationId]
            );
            
            foreach ($students as $student) {
                $recipientEmails[] = $student['email'];
                if (!empty($student['phone'])) {
                    $recipientPhones[] = $student['phone'];
                }
            }
        } elseif ($notificationType == 'selected') {
            // Get selected students
            foreach ($recipients as $userId) {
                $student = fetchOne("SELECT email, phone FROM users WHERE id = ? AND role = ?", [intval($userId), ROLE_STUDENT]);
                if ($student) {
                    $recipientEmails[] = $student['email'];
                    if (!empty($student['phone'])) {
                        $recipientPhones[] = $student['phone'];
                    }
                }
            }
        }
        
        if (empty($recipientEmails)) {
            $error = 'No recipients found matching your criteria.';
        } else {
            // Send email notifications
            $emailResults = sendBulkEmail($recipientEmails, $subject, $message);
            
            // Send SMS if requested and phone numbers available
            $smsResults = ['success' => 0, 'failure' => 0];
            if (isset($_POST['send_sms']) && !empty($recipientPhones)) {
                $smsResults = sendBulkSMS($recipientPhones, substr($subject . ': ' . $message, 0, 160));
            }
            
            $success = "Notifications sent successfully! " .
                       "Emails sent: {$emailResults['success']} successful, {$emailResults['failure']} failed. " .
                       "SMS sent: {$smsResults['success']} successful, {$smsResults['failure']} failed.";
        }
    }
}

// Get students for selected recipients option
if ($isMasterAdmin) {
    $students = fetchAll("SELECT * FROM users WHERE role = ? ORDER BY username ASC", [ROLE_STUDENT]);
} else {
    // Get students for this admin's accommodations
    $adminAccommodations = getAdminAccommodations($_SESSION['user_id']);
    $adminAccommodationIds = array_column($adminAccommodations, 'id');
    
    if (!empty($adminAccommodationIds)) {
        $placeholders = implode(',', array_fill(0, count($adminAccommodationIds), '?'));
        $students = fetchAll(
            "SELECT DISTINCT u.* 
            FROM users u
            JOIN leases l ON u.id = l.user_id
            WHERE u.role = ? AND l.accommodation_id IN ($placeholders)
            ORDER BY u.username ASC", 
            array_merge([ROLE_STUDENT], $adminAccommodationIds)
        );
    } else {
        $students = [];
    }
}

// Get accommodations for accommodation-specific notifications
if ($isMasterAdmin) {
    $accommodations = getAccommodations();
} else {
    $accommodations = getAdminAccommodations($_SESSION['user_id']);
}

include 'includes/header.php';
?>

<div class="container">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Send Notifications</h1>
            <p class="lead">Send email or SMS notifications to students.</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Compose Notification</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="notifications.php">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notification Type *</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notification_type" id="type_all" value="all_students" checked>
                                <label class="form-check-label" for="type_all">
                                    All Students
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notification_type" id="type_accommodation" value="accommodation">
                                <label class="form-check-label" for="type_accommodation">
                                    Students in Specific Accommodation
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notification_type" id="type_selected" value="selected">
                                <label class="form-check-label" for="type_selected">
                                    Selected Students
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3 accommodation-selector" style="display: none;">
                            <label for="accommodation_id" class="form-label">Select Accommodation</label>
                            <select class="form-select" id="accommodation_id" name="accommodation_id">
                                <option value="">Select Accommodation</option>
                                <?php foreach ($accommodations as $accommodation): ?>
                                    <option value="<?= $accommodation['id'] ?>"><?= $accommodation['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3 student-selector" style="display: none;">
                            <label class="form-label">Select Recipients</label>
                            <div class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="select_all">
                                    <label class="form-check-label" for="select_all">
                                        Select All
                                    </label>
                                </div>
                            </div>
                            <div class="card" style="max-height: 300px; overflow-y: auto;">
                                <div class="card-body">
                                    <?php if (empty($students)): ?>
                                        <p class="text-muted">No students available.</p>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): ?>
                                            <div class="form-check">
                                                <input class="form-check-input recipient-checkbox" type="checkbox" name="recipients[]" id="student_<?= $student['id'] ?>" value="<?= $student['id'] ?>">
                                                <label class="form-check-label" for="student_<?= $student['id'] ?>">
                                                    <?= $student['username'] ?> (<?= $student['email'] ?>)
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="send_sms" name="send_sms" value="1">
                                <label class="form-check-label" for="send_sms">
                                    Also send as SMS (if phone numbers available)
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="send_notification" class="btn btn-primary btn-lg">Send Notification</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Notification Tips</h5>
                </div>
                <div class="card-body">
                    <h6>Email Best Practices</h6>
                    <ul>
                        <li>Use clear and concise subject lines</li>
                        <li>Keep messages brief and to the point</li>
                        <li>Include contact information for follow-up questions</li>
                        <li>Avoid sending late at night unless urgent</li>
                    </ul>
                    
                    <h6>SMS Considerations</h6>
                    <ul>
                        <li>SMS messages are limited to 160 characters</li>
                        <li>Use SMS for time-sensitive notifications</li>
                        <li>Avoid sending SMS too frequently</li>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Common Templates</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <button class="btn btn-outline-primary d-block w-100 text-start template-btn" data-subject="Maintenance Notice" data-message="Dear Student,

We will be conducting maintenance work at your accommodation on [DATE] from [TIME] to [TIME]. 

This may affect water/electricity supply during this period. Please plan accordingly.

We apologize for any inconvenience caused.

Regards,
Harambee Student Living">Maintenance Notice</button>
                    </div>
                    
                    <div class="mb-3">
                        <button class="btn btn-outline-primary d-block w-100 text-start template-btn" data-subject="Payment Reminder" data-message="Dear Student,

This is a friendly reminder that your monthly rent payment is due on [DATE].

Please ensure that your payment is made on time to avoid late fees.

Regards,
Harambee Student Living">Payment Reminder</button>
                    </div>
                    
                    <div class="mb-3">
                        <button class="btn btn-outline-primary d-block w-100 text-start template-btn" data-subject="Important Announcement" data-message="Dear Student,

We would like to inform you about an important announcement:

[ANNOUNCEMENT DETAILS]

If you have any questions, please contact our office.

Regards,
Harambee Student Living">Important Announcement</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Notification type selector
        const typeAll = document.getElementById('type_all');
        const typeAccommodation = document.getElementById('type_accommodation');
        const typeSelected = document.getElementById('type_selected');
        const accommodationSelector = document.querySelector('.accommodation-selector');
        const studentSelector = document.querySelector('.student-selector');
        
        function updateSelectors() {
            if (typeAll.checked) {
                accommodationSelector.style.display = 'none';
                studentSelector.style.display = 'none';
            } else if (typeAccommodation.checked) {
                accommodationSelector.style.display = 'block';
                studentSelector.style.display = 'none';
            } else if (typeSelected.checked) {
                accommodationSelector.style.display = 'none';
                studentSelector.style.display = 'block';
            }
        }
        
        typeAll.addEventListener('change', updateSelectors);
        typeAccommodation.addEventListener('change', updateSelectors);
        typeSelected.addEventListener('change', updateSelectors);
        
        // Initialize on page load
        updateSelectors();
        
        // Select All functionality
        const selectAllCheckbox = document.getElementById('select_all');
        const recipientCheckboxes = document.querySelectorAll('.recipient-checkbox');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                recipientCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
        
        // Template buttons
        const templateButtons = document.querySelectorAll('.template-btn');
        const subjectField = document.getElementById('subject');
        const messageField = document.getElementById('message');
        
        templateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const subject = this.getAttribute('data-subject');
                const message = this.getAttribute('data-message');
                
                subjectField.value = subject;
                messageField.value = message;
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
