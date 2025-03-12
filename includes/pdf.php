<?php
require_once 'config.php';
require_once 'functions.php';

/**
 * Generate a lease agreement PDF
 * @param array $lease Lease data
 * @return string Generated PDF content
 */
function generateLeasePDF($lease) {
    // Get user and accommodation details
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [$lease['user_id']]);
    $accommodation = fetchOne("SELECT * FROM accommodations WHERE id = ?", [$lease['accommodation_id']]);
    
    // Start output buffering to capture HTML content
    ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Lease Agreement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        h1 {
            color: #1a5276;
            text-align: center;
            margin-bottom: 20px;
        }
        h2 {
            color: #2874a6;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .container {
            width: 90%;
            margin: 0 auto;
        }
        .section {
            margin-bottom: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table th, .info-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .info-table th {
            background-color: #f2f2f2;
            text-align: left;
        }
        .signature {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Lease Agreement</h1>
            <p>Harambee Student Living Management System</p>
            <p>Agreement Date: <?= formatDate(date('Y-m-d')) ?></p>
        </div>
        
        <div class="section">
            <h2>Parties</h2>
            <p><strong>Landlord/Property Manager:</strong> Harambee Student Living</p>
            <p><strong>Tenant:</strong> <?= $user['username'] ?></p>
        </div>
        
        <div class="section">
            <h2>Property Information</h2>
            <table class="info-table">
                <tr>
                    <th>Property Name</th>
                    <td><?= $accommodation['name'] ?></td>
                </tr>
                <tr>
                    <th>Address</th>
                    <td><?= $accommodation['location'] ?></td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td><?= $accommodation['description'] ?></td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <h2>Lease Terms</h2>
            <table class="info-table">
                <tr>
                    <th>Lease Start Date</th>
                    <td><?= formatDate($lease['start_date']) ?></td>
                </tr>
                <tr>
                    <th>Lease End Date</th>
                    <td><?= formatDate($lease['end_date']) ?></td>
                </tr>
                <tr>
                    <th>Monthly Rent</th>
                    <td><?= formatCurrency($lease['monthly_rent']) ?></td>
                </tr>
                <tr>
                    <th>Security Deposit</th>
                    <td><?= formatCurrency($lease['security_deposit']) ?></td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <h2>Terms and Conditions</h2>
            <ol>
                <li>The Tenant agrees to pay the monthly rent on or before the 1st day of each month.</li>
                <li>The Tenant shall keep the premises in a clean and sanitary condition.</li>
                <li>The Tenant shall not sublet the property without prior written consent from the Landlord.</li>
                <li>The Tenant shall comply with all building, housing, and health codes.</li>
                <li>The Tenant shall not make alterations or additions to the property without prior written consent.</li>
                <li>The Landlord may enter the premises with reasonable notice to inspect, make repairs, or show the property.</li>
                <li>The security deposit will be returned within 30 days after the end of the lease term, less any deductions for damages.</li>
                <li>Failure to pay rent or violating any terms of this agreement may result in termination of the lease.</li>
            </ol>
        </div>
        
        <div class="signature">
            <h2>Signatures</h2>
            <p><strong>Tenant Signature:</strong> _______________________________ Date: <?= formatDate($lease['signed_at'] ?? date('Y-m-d')) ?></p>
            <p><strong>Landlord/Manager Signature:</strong> _______________________________ Date: <?= formatDate(date('Y-m-d')) ?></p>
        </div>
        
        <div class="footer">
            <p>This is an official lease agreement document generated by <?= APP_NAME ?>.</p>
            <p>Document ID: <?= $lease['id'] ?>-<?= time() ?></p>
        </div>
    </div>
</body>
</html>
<?php
    // Get the HTML content
    $html = ob_get_clean();
    
    // Return the HTML content as we don't have an actual PDF library in this implementation
    return $html;
}

/**
 * Generate an invoice PDF
 * @param array $invoice Invoice data
 * @return string Generated PDF content
 */
function generateInvoicePDF($invoice) {
    // Get user and accommodation details
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [$invoice['user_id']]);
    $accommodation = fetchOne("SELECT * FROM accommodations WHERE id = ?", [$invoice['accommodation_id']]);
    
    // Start output buffering to capture HTML content
    ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        h1 {
            color: #1a5276;
            text-align: center;
            margin-bottom: 20px;
        }
        h2 {
            color: #2874a6;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .container {
            width: 90%;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .invoice-info {
            margin-bottom: 20px;
        }
        .invoice-info table {
            width: 100%;
        }
        .invoice-info td {
            padding: 5px;
        }
        .invoice-info .label {
            font-weight: bold;
            width: 150px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .invoice-table th, .invoice-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .invoice-table th {
            background-color: #f2f2f2;
        }
        .invoice-table .amount {
            text-align: right;
        }
        .totals {
            width: 100%;
            margin-bottom: 30px;
        }
        .totals table {
            width: 350px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            padding: 5px 10px;
        }
        .totals .label {
            font-weight: bold;
            text-align: left;
        }
        .totals .amount {
            text-align: right;
        }
        .totals .total {
            border-top: 2px solid #333;
            font-weight: bold;
        }
        .notes {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice</h1>
            <p>Harambee Student Living Management System</p>
        </div>
        
        <div class="invoice-info">
            <table>
                <tr>
                    <td class="label">Invoice Number:</td>
                    <td><?= $invoice['id'] ?></td>
                    <td class="label">Date Issued:</td>
                    <td><?= formatDate($invoice['created_at']) ?></td>
                </tr>
                <tr>
                    <td class="label">Due Date:</td>
                    <td><?= formatDate($invoice['due_date']) ?></td>
                    <td class="label">Status:</td>
                    <td><?= $invoice['paid'] ? 'Paid' : 'Unpaid' ?></td>
                </tr>
            </table>
        </div>
        
        <div class="billing-info">
            <h2>Bill To</h2>
            <p><?= $user['username'] ?><br>
            <?= $user['email'] ?></p>
            
            <h2>Property</h2>
            <p><?= $accommodation['name'] ?><br>
            <?= $accommodation['location'] ?></p>
        </div>
        
        <h2>Invoice Details</h2>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Monthly Rent (<?= formatDate($invoice['period_start']) ?> to <?= formatDate($invoice['period_end']) ?>)</td>
                    <td class="amount"><?= formatCurrency($invoice['amount']) ?></td>
                </tr>
                <?php if (!empty($invoice['late_fee']) && $invoice['late_fee'] > 0): ?>
                <tr>
                    <td>Late Fee</td>
                    <td class="amount"><?= formatCurrency($invoice['late_fee']) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <table>
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="amount"><?= formatCurrency($invoice['amount']) ?></td>
                </tr>
                <?php if (!empty($invoice['late_fee']) && $invoice['late_fee'] > 0): ?>
                <tr>
                    <td class="label">Late Fee:</td>
                    <td class="amount"><?= formatCurrency($invoice['late_fee']) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total">
                    <td class="label">Total Due:</td>
                    <td class="amount"><?= formatCurrency($invoice['amount'] + ($invoice['late_fee'] ?? 0)) ?></td>
                </tr>
            </table>
        </div>
        
        <div class="notes">
            <h2>Payment Information</h2>
            <p>Please make payments using one of the following methods:</p>
            <ol>
                <li>Online through your student portal</li>
                <li>Bank transfer to Account #: 123456789</li>
                <li>In-person at the management office</li>
            </ol>
            <p><strong>Note:</strong> Late payments may incur additional fees.</p>
        </div>
        
        <div class="footer">
            <p>This is an official invoice generated by <?= APP_NAME ?>.</p>
            <p>Document ID: INV-<?= $invoice['id'] ?>-<?= time() ?></p>
        </div>
    </div>
</body>
</html>
<?php
    // Get the HTML content
    $html = ob_get_clean();
    
    // Return the HTML content as we don't have an actual PDF library in this implementation
    return $html;
}
?>
