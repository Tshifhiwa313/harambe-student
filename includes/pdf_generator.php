<?php
/**
 * PDF generation functions
 * 
 * This file contains functions for generating PDF documents like leases and invoices.
 */

/**
 * Generate a lease agreement PDF
 *
 * @param PDO $conn Database connection
 * @param int $leaseId Lease ID
 * @return string|false Path to the generated PDF file, or false on failure
 */
function generateLeasePDF($conn, $leaseId) {
    // Get lease details
    $query = "SELECT l.*, 
              u.first_name, u.last_name, u.email, u.phone,
              a.name as accommodation_name, a.address
              FROM leases l
              JOIN users u ON l.user_id = u.id
              JOIN accommodations a ON l.accommodation_id = a.id
              WHERE l.id = :leaseId";
    
    $lease = fetchRow($conn, $query, ['leaseId' => $leaseId]);
    
    if (!$lease) {
        return false;
    }
    
    // Create PDF content using HTML
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Lease Agreement</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                font-size: 12pt;
                line-height: 1.6;
            }
            h1 {
                text-align: center;
                font-size: 18pt;
                color: #333;
                margin-bottom: 20px;
            }
            h2 {
                font-size: 14pt;
                color: #333;
                margin-top: 20px;
                margin-bottom: 10px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .section {
                margin-bottom: 20px;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                font-size: 10pt;
                color: #666;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            table, th, td {
                border: 1px solid #ddd;
            }
            th, td {
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
            .signature {
                margin-top: 40px;
                border-top: 1px solid #000;
                width: 200px;
                text-align: center;
                padding-top: 5px;
            }
            .date {
                margin-top: 10px;
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>RESIDENTIAL LEASE AGREEMENT</h1>
            <p>' . APP_NAME . '</p>
        </div>
        
        <div class="section">
            <h2>1. PARTIES</h2>
            <p>This Lease Agreement (hereinafter referred to as the "Agreement") is made and entered into on 
            ' . formatDate($lease['created_at']) . ' by and between:</p>
            <p><strong>LANDLORD:</strong> ' . APP_NAME . ', (hereinafter referred to as "Landlord")</p>
            <p><strong>TENANT:</strong> ' . $lease['first_name'] . ' ' . $lease['last_name'] . ', (hereinafter referred to as "Tenant")</p>
        </div>
        
        <div class="section">
            <h2>2. PROPERTY</h2>
            <p>Landlord hereby leases to Tenant, and Tenant hereby leases from Landlord, the residential property located at:</p>
            <p><strong>' . $lease['accommodation_name'] . '</strong><br>' . $lease['address'] . '</p>
        </div>
        
        <div class="section">
            <h2>3. TERM</h2>
            <p>The term of this Agreement shall begin on ' . formatDate($lease['start_date']) . ' and end on 
            ' . formatDate($lease['end_date']) . ', unless otherwise terminated as provided in this Agreement.</p>
        </div>
        
        <div class="section">
            <h2>4. RENT</h2>
            <p>Tenant agrees to pay Landlord the monthly rent of ' . formatCurrency($lease['monthly_rent']) . ' payable in advance 
            on the 1st day of each month during the term of this Agreement.</p>
            <p>A security deposit of ' . formatCurrency($lease['security_deposit']) . ' is required and shall be refundable 
            subject to the terms of this Agreement and applicable law.</p>
        </div>
        
        <div class="section">
            <h2>5. UTILITIES</h2>
            <p>Tenant shall be responsible for payment of all utilities and services to the property, except for the following 
            which will be provided by the Landlord: water, electricity (up to a reasonable amount), and basic internet service.</p>
        </div>
        
        <div class="section">
            <h2>6. MAINTENANCE</h2>
            <p>Tenant shall maintain the property in good, clean condition and shall notify Landlord promptly of any defects or 
            maintenance issues. Tenant shall be responsible for any damage caused by Tenant\'s negligence or misuse.</p>
        </div>
        
        <div class="section">
            <h2>7. RULES AND REGULATIONS</h2>
            <p>Tenant agrees to comply with all rules and regulations governing the property, including but not limited to 
            noise restrictions, guest policies, and proper use of common areas.</p>
        </div>
        
        <div class="section">
            <h2>8. TERMINATION</h2>
            <p>Either party may terminate this Agreement at the end of the initial term by giving at least 30 days\' written notice. 
            If no notice is given, the Agreement will continue on a month-to-month basis until terminated by either party with 
            30 days\' written notice.</p>
        </div>
        
        <div class="section">
            <h2>9. SIGNATURES</h2>
            <p>By signing below, the parties acknowledge that they have read, understood, and agree to be bound by the terms 
            of this Agreement.</p>
            
            <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                <div>
                    <div class="signature">Landlord Signature</div>
                    <div class="date">Date: ' . formatDate(date('Y-m-d')) . '</div>
                </div>
                
                <div>
                    <div class="signature">Tenant Signature: ' . $lease['first_name'] . ' ' . $lease['last_name'] . '</div>
                    <div class="date">Date: ' . ($lease['signed'] ? formatDate($lease['signed_date']) : '_____________') . '</div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an official lease agreement document of ' . APP_NAME . '.</p>
            <p>Generated on: ' . formatDate(date('Y-m-d')) . '</p>
        </div>
    </body>
    </html>
    ';
    
    // Create directory if it doesn't exist
    if (!file_exists(LEASE_UPLOADS)) {
        mkdir(LEASE_UPLOADS, 0755, true);
    }
    
    // Generate unique filename
    $filename = 'lease_' . $leaseId . '_' . uniqid() . '.pdf';
    $filepath = LEASE_UPLOADS . '/' . $filename;
    
    // Create the PDF using a PHP library like TCPDF, FPDF, or Dompdf
    // For simplicity, we'll just save the HTML to a file in this example
    // In a real implementation, you'd use a PDF library
    file_put_contents($filepath, $html);
    
    // Update the lease record with the PDF path
    $data = ['pdf_path' => $filename];
    updateRow($conn, 'leases', $data, 'id', $leaseId);
    
    return $filename;
}

/**
 * Generate an invoice PDF
 *
 * @param PDO $conn Database connection
 * @param int $invoiceId Invoice ID
 * @return string|false Path to the generated PDF file, or false on failure
 */
function generateInvoicePDF($conn, $invoiceId) {
    // Get invoice details
    $query = "SELECT i.*, 
              l.monthly_rent, l.start_date, l.end_date, 
              u.first_name, u.last_name, u.email, u.phone,
              a.name as accommodation_name, a.address
              FROM invoices i
              JOIN leases l ON i.lease_id = l.id
              JOIN users u ON l.user_id = u.id
              JOIN accommodations a ON l.accommodation_id = a.id
              WHERE i.id = :invoiceId";
    
    $invoice = fetchRow($conn, $query, ['invoiceId' => $invoiceId]);
    
    if (!$invoice) {
        return false;
    }
    
    // Create invoice number
    $invoiceNumber = 'INV-' . str_pad($invoice['id'], 6, '0', STR_PAD_LEFT);
    
    // Create PDF content using HTML
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoice #' . $invoiceNumber . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                font-size: 12pt;
                line-height: 1.6;
            }
            h1 {
                text-align: center;
                font-size: 18pt;
                color: #333;
                margin-bottom: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .invoice-details {
                margin-bottom: 30px;
            }
            .invoice-details table {
                width: 100%;
                border-collapse: collapse;
            }
            .invoice-details table td {
                padding: 5px;
                vertical-align: top;
            }
            .invoice-details .label {
                font-weight: bold;
                width: 150px;
            }
            .items {
                margin-bottom: 30px;
            }
            .items table {
                width: 100%;
                border-collapse: collapse;
            }
            .items table th,
            .items table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .items table th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            .total {
                text-align: right;
                margin-top: 20px;
                font-weight: bold;
                font-size: 14pt;
            }
            .notes {
                margin-top: 30px;
                font-style: italic;
            }
            .footer {
                text-align: center;
                margin-top: 50px;
                font-size: 10pt;
                color: #666;
            }
            .status {
                font-weight: bold;
                padding: 5px 10px;
                border-radius: 4px;
                display: inline-block;
            }
            .status-unpaid {
                color: #dc3545;
                border: 1px solid #dc3545;
            }
            .status-paid {
                color: #28a745;
                border: 1px solid #28a745;
            }
            .status-overdue {
                color: #fd7e14;
                border: 1px solid #fd7e14;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>INVOICE</h1>
            <p>' . APP_NAME . '</p>
        </div>
        
        <div class="invoice-details">
            <table>
                <tr>
                    <td class="label">Invoice #:</td>
                    <td>' . $invoiceNumber . '</td>
                    <td class="label">Status:</td>
                    <td>
                        <div class="status status-' . $invoice['status'] . '">
                            ' . strtoupper($invoice['status']) . '
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="label">Date Issued:</td>
                    <td>' . formatDate($invoice['created_at']) . '</td>
                    <td class="label">Due Date:</td>
                    <td>' . formatDate($invoice['due_date']) . '</td>
                </tr>
            </table>
        </div>
        
        <div class="invoice-details">
            <table>
                <tr>
                    <td class="label">Landlord:</td>
                    <td>
                        ' . APP_NAME . '<br>
                        admin@harambee.com
                    </td>
                    <td class="label">Tenant:</td>
                    <td>
                        ' . $invoice['first_name'] . ' ' . $invoice['last_name'] . '<br>
                        ' . $invoice['email'] . '<br>
                        ' . ($invoice['phone'] ? $invoice['phone'] . '<br>' : '') . '
                    </td>
                </tr>
                <tr>
                    <td class="label">Property:</td>
                    <td colspan="3">
                        ' . $invoice['accommodation_name'] . '<br>
                        ' . $invoice['address'] . '
                    </td>
                </tr>
                <tr>
                    <td class="label">Lease Period:</td>
                    <td colspan="3">
                        ' . formatDate($invoice['start_date']) . ' to ' . formatDate($invoice['end_date']) . '
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="items">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Monthly Rent for ' . date('F Y', strtotime($invoice['due_date'])) . '</td>
                        <td>' . formatCurrency($invoice['monthly_rent']) . '</td>
                    </tr>
                    <!-- Add additional items if needed -->
                </tbody>
            </table>
            
            <div class="total">
                Total Due: ' . formatCurrency($invoice['amount']) . '
            </div>
        </div>
        
        <div class="notes">
            <p>Payment Methods:</p>
            <p>1. Direct Deposit to Bank Account: [Bank Account Details]</p>
            <p>2. Online Payment through Student Portal</p>
            <p>3. Cash or Card Payment at the Administration Office</p>
            <p>Please include your Invoice Number as reference for all payments.</p>
        </div>
        
        <div class="footer">
            <p>This is an official invoice of ' . APP_NAME . '.</p>
            <p>For any queries, please contact us at admin@harambee.com</p>
            <p>Generated on: ' . formatDate(date('Y-m-d')) . '</p>
        </div>
    </body>
    </html>
    ';
    
    // Create directory if it doesn't exist
    if (!file_exists(INVOICE_UPLOADS)) {
        mkdir(INVOICE_UPLOADS, 0755, true);
    }
    
    // Generate unique filename
    $filename = 'invoice_' . $invoiceId . '_' . uniqid() . '.pdf';
    $filepath = INVOICE_UPLOADS . '/' . $filename;
    
    // Create the PDF using a PHP library like TCPDF, FPDF, or Dompdf
    // For simplicity, we'll just save the HTML to a file in this example
    // In a real implementation, you'd use a PDF library
    file_put_contents($filepath, $html);
    
    // Update the invoice record with the PDF path
    $data = ['pdf_path' => $filename];
    updateRow($conn, 'invoices', $data, 'id', $invoiceId);
    
    return $filename;
}
?>
