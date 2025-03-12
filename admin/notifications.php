<?php
/**
 * Admin Notifications Management
 * 
 * This page allows administrators to send bulk notifications to students.
 */

require_once '../includes/header.php';
require_once '../includes/twilio.php';

// Require admin authentication
requireAuth([ROLE_MASTER_ADMIN, ROLE_ADMIN]);

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Send bulk notification
    if (isset($_POST['send_notification'])) {
        $subject = sanitize($_POST['subject'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        $smsMessage = sanitize($_POST['sms_message'] ?? '');
        $recipientType = $_POST['recipient_type'] ?? 'all';
        $accommodationId = isset($_POST['accommodation_id']) ? (int)$_POST['accommodation_id'] : 0;
        $sendEmail = isset($_POST['send_email']);
        $sendSMS = isset($_POST['send_sms']);
        
        // Validation
        if (empty($subject) && $sendEmail) {
            $errors[] = 'Email subject is required';
        }
        
        if (empty($message) && $sendEmail) {
            $errors[] = 'Email message is required';
        }
        
        if (empty($smsMessage) && $sendSMS) {
            $errors[] = 'SMS message is required';
        }
        
        if (!$sendEmail && !$sendSMS) {
            $errors[] = 'You must select at least one notification method (Email or SMS)';
        }
        
        // Get recipients based on selection
        $recipients = [];
        
        if ($recipientType === 'all') {
            // All students
            $recipients = fetchAll($conn, "SELECT id, email, phone FROM users WHERE role = :role", ['role' => ROLE_STUDENT]);
        } elseif ($recipientType === 'accommodation' && $accommodationId > 0) {
            // Students in a specific accommodation
            // Check permission for regular admin
            if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $accommodationId)) {
                $errors[] = 'You do not have permission to send notifications to students in this accommodation';
            } else {
                $recipients = fetchAll($conn, 
                    "SELECT DISTINCT u.id, u.email, u.phone FROM users u 
                    JOIN leases l ON u.id = l.user_id 
                    WHERE l.accommodation_id = :accommodation_id AND l.end_date >= CURRENT_DATE", 
                    ['accommodation_id' => $accommodationId]
                );
            }
        } elseif ($recipientType === 'specific' && isset($_POST['user_ids']) && !empty($_POST['user_ids'])) {
            // Specific students
            $userIds = array_map('intval', $_POST['user_ids']);
            
            // For regular admin, check if they have permission to send to these students
            if ($userRole !== ROLE_MASTER_ADMIN) {
                $adminAccommodations = getAdminAccommodations($conn, $userId);
                $accommodationIds = array_map(function($acc) { return $acc['id']; }, $adminAccommodations);
                
                if (empty($accommodationIds)) {
                    $errors[] = 'You do not have any assigned accommodations';
                } else {
                    $placeholders = implode(',', array_fill(0, count($accommodationIds), '?'));
                    $params = array_merge($accommodationIds, $userIds);
                    
                    $stmt = $conn->prepare(
                        "SELECT COUNT(*) FROM leases 
                        WHERE accommodation_id IN ($placeholders) 
                        AND user_id IN (" . implode(',', array_fill(0, count($userIds), '?')) . ") 
                        AND end_date >= CURRENT_DATE"
                    );
                    $stmt->execute($params);
                    $count = $stmt->fetchColumn();
                    
                    if ($count != count($userIds)) {
                        $errors[] = 'You do not have permission to send notifications to some of the selected students';
                    }
                }
            }
            
            // Get recipient information
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $conn->prepare("SELECT id, email, phone FROM users WHERE id IN ($placeholders) AND role = :role");
            $params = array_merge($userIds, [ROLE_STUDENT]);
            $stmt->execute($params);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if (empty($recipients)) {
            $errors[] = 'No recipients found for the selected criteria';
        }
        
        // Send notifications if no errors
        if (empty($errors)) {
            $successCount = 0;
            $failCount = 0;
            
            foreach ($recipients as $recipient) {
                $success = false;
                
                // Send email
                if ($sendEmail && !empty($recipient['email'])) {
                    $emailSuccess = sendEmail($recipient['email'], $subject, $message);
                    $success = $success || $emailSuccess;
                }
                
                // Send SMS
                if ($sendSMS && !empty($recipient['phone'])) {
                    $smsSuccess = sendTwilioSMS($recipient['phone'], $smsMessage);
                    $success = $success || $smsSuccess;
                }
                
                // Create database notification
                $notificationData = [
                    'user_id' => $recipient['id'],
                    'message' => $subject,
                    'type' => 'admin',
                    'is_read' => 0
                ];
                insertRow($conn, 'notifications', $notificationData);
                
                if ($success) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            
            if ($successCount > 0) {
                setFlashMessage("Notifications sent successfully to $successCount recipients" . ($failCount > 0 ? " ($failCount failed)" : ""), 'success');
                redirect('notifications.php');
            } else {
                $errors[] = 'Failed to send notifications to any recipients';
            }
        }
    }
}

// Get accommodations based on user role
if ($userRole === ROLE_MASTER_ADMIN) {
    // Master admin can see all accommodations
    $accommodations = fetchAll($conn, "SELECT * FROM accommodations ORDER BY name");
} else {
    // Regular admin can only see assigned accommodations
    $accommodations = getAdminAccommodations($conn, $userId);
}

// Get all students for specific selection
$students = [];
if ($userRole === ROLE_MASTER_ADMIN) {
    // Master admin can see all students
    $students = fetchAll($conn, "SELECT id, first_name, last_name, email FROM users WHERE role = :role ORDER BY first_name, last_name", ['role' => ROLE_STUDENT]);
} else {
    // Regular admin can only see students in their assigned accommodations
    $adminAccommodations = getAdminAccommodations($conn, $userId);
    
    if (!empty($adminAccommodations)) {
        $accommodationIds = array_map(function($acc) { return $acc['id']; }, $adminAccommodations);
        $placeholders = implode(',', array_fill(0, count($accommodationIds), '?'));
        
        $stmt = $conn->prepare(
            "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email 
            FROM users u 
            JOIN leases l ON u.id = l.user_id 
            WHERE l.accommodation_id IN ($placeholders) 
            AND l.end_date >= CURRENT_DATE 
            ORDER BY u.first_name, u.last_name"
        );
        $stmt->execute($accommodationIds);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get recent notifications sent by this admin
$recentNotifications = fetchAll($conn, 
    "SELECT n.*, u.first_name, u.last_name, u.email 
    FROM notifications n 
    JOIN users u ON n.user_id = u.id 
    WHERE n.type = 'admin' 
    ORDER BY n.created_at DESC 
    LIMIT 10"
);
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Notifications Management</h2>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h4 class="mb-0">
                        <i class="fas fa-paper-plane me-2"></i>Send Notifications
                    </h4>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label class="form-label">Recipients</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_type" id="all_students" value="all" checked>
                                <label class="form-check-label" for="all_students">
                                    All Students
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_type" id="accommodation_students" value="accommodation">
                                <label class="form-check-label" for="accommodation_students">
                                    Students in a Specific Accommodation
                                </label>
                            </div>
                            <div class="ms-4 mb-3 accommodation-select" style="display: none;">
                                <select class="form-select" name="accommodation_id">
                                    <option value="">-- Select Accommodation --</option>
                                    <?php foreach ($accommodations as $accommodation): ?>
                                        <option value="<?php echo $accommodation['id']; ?>"><?php echo $accommodation['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_type" id="specific_students" value="specific">
                                <label class="form-check-label" for="specific_students">
                                    Specific Students
                                </label>
                            </div>
                            <div class="ms-4 mb-3 students-select" style="display: none;">
                                <select class="form-select" name="user_ids[]" multiple size="5">
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo $student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Hold Ctrl (or Cmd on Mac) to select multiple students</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notification Methods</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_email" id="send_email" checked>
                                <label class="form-check-label" for="send_email">
                                    Email
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_sms" id="send_sms">
                                <label class="form-check-label" for="send_sms">
                                    SMS (will only be sent to students with phone numbers)
                                </label>
                            </div>
                        </div>
                        
                        <div class="email-fields">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Email Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject">
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Email Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5"></textarea>
                                <div class="form-text">You can use HTML formatting in your email message.</div>
                            </div>
                        </div>
                        
                        <div class="sms-fields" style="display: none;">
                            <div class="mb-3">
                                <label for="sms_message" class="form-label">SMS Message</label>
                                <textarea class="form-control" id="sms_message" name="sms_message" rows="3" maxlength="160"></textarea>
                                <div class="form-text">Maximum 160 characters for SMS messages.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Preview</label>
                            <div class="card">
                                <div class="card-body">
                                    <div class="email-preview">
                                        <h6>Email Preview:</h6>
                                        <div class="border p-3 mb-3">
                                            <div id="email-subject-preview" class="fw-bold mb-2">Subject: </div>
                                            <div id="email-message-preview">Message content will appear here...</div>
                                        </div>
                                    </div>
                                    <div class="sms-preview" style="display: none;">
                                        <h6>SMS Preview:</h6>
                                        <div class="border p-3 mb-3">
                                            <div id="sms-message-preview">SMS content will appear here...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="send_notification" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Notification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h4 class="mb-0">
                        <i class="fas fa-history me-2"></i>Recent Notifications
                    </h4>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentNotifications)): ?>
                        <div class="p-3 text-center">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <p>No recent notifications found.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentNotifications as $notification): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><?php echo formatDate($notification['created_at']); ?></small>
                                    </div>
                                    <div class="mt-1 mb-1"><?php echo $notification['message']; ?></div>
                                    <small class="text-muted">To: <?php echo $notification['first_name'] . ' ' . $notification['last_name']; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle accommodation select
    const accommodationRadio = document.getElementById('accommodation_students');
    const accommodationSelect = document.querySelector('.accommodation-select');
    
    accommodationRadio.addEventListener('change', function() {
        if (this.checked) {
            accommodationSelect.style.display = 'block';
        } else {
            accommodationSelect.style.display = 'none';
        }
    });
    
    // Toggle students select
    const specificRadio = document.getElementById('specific_students');
    const studentsSelect = document.querySelector('.students-select');
    
    specificRadio.addEventListener('change', function() {
        if (this.checked) {
            studentsSelect.style.display = 'block';
        } else {
            studentsSelect.style.display = 'none';
        }
    });
    
    // Toggle all radios
    const allRadio = document.getElementById('all_students');
    allRadio.addEventListener('change', function() {
        if (this.checked) {
            accommodationSelect.style.display = 'none';
            studentsSelect.style.display = 'none';
        }
    });
    
    // Toggle SMS fields
    const smsCheckbox = document.getElementById('send_sms');
    const smsFields = document.querySelector('.sms-fields');
    const smsPreview = document.querySelector('.sms-preview');
    
    smsCheckbox.addEventListener('change', function() {
        if (this.checked) {
            smsFields.style.display = 'block';
            smsPreview.style.display = 'block';
        } else {
            smsFields.style.display = 'none';
            smsPreview.style.display = 'none';
        }
    });
    
    // Toggle email fields
    const emailCheckbox = document.getElementById('send_email');
    const emailFields = document.querySelector('.email-fields');
    const emailPreview = document.querySelector('.email-preview');
    
    emailCheckbox.addEventListener('change', function() {
        if (this.checked) {
            emailFields.style.display = 'block';
            emailPreview.style.display = 'block';
        } else {
            emailFields.style.display = 'none';
            emailPreview.style.display = 'none';
        }
    });
    
    // Update preview
    const subjectInput = document.getElementById('subject');
    const messageInput = document.getElementById('message');
    const smsMessageInput = document.getElementById('sms_message');
    
    const subjectPreview = document.getElementById('email-subject-preview');
    const messagePreview = document.getElementById('email-message-preview');
    const smsMessagePreview = document.getElementById('sms-message-preview');
    
    subjectInput.addEventListener('input', function() {
        subjectPreview.textContent = 'Subject: ' + this.value;
    });
    
    messageInput.addEventListener('input', function() {
        messagePreview.innerHTML = this.value;
    });
    
    smsMessageInput.addEventListener('input', function() {
        smsMessagePreview.textContent = this.value;
        
        // Update character count
        const maxLength = 160;
        const currentLength = this.value.length;
        const remaining = maxLength - currentLength;
        
        if (remaining < 20) {
            this.nextElementSibling.textContent = `${remaining} characters remaining`;
            if (remaining < 0) {
                this.nextElementSibling.classList.add('text-danger');
            } else {
                this.nextElementSibling.classList.remove('text-danger');
            }
        } else {
            this.nextElementSibling.textContent = 'Maximum 160 characters for SMS messages.';
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
