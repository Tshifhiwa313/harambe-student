<?php
// PDF generation functions
require_once 'config.php';
require_once 'functions.php';

/**
 * Generate a PDF document
 * @param string $html HTML content for the PDF
 * @param string $filename Output filename
 * @param string $output_dir Output directory
 * @return string|false Path to the generated PDF or false on failure
 */
function generate_pdf($html, $filename, $output_dir = 'uploads/pdfs/') {
    // Create the output directory if it doesn't exist
    if (!file_exists($output_dir)) {
        mkdir($output_dir, 0755, true);
    }
    
    // Generate a unique filename if none provided
    if (empty($filename)) {
        $filename = generate_random_string() . '_' . time() . '.pdf';
    }
    
    // Full path to the output file
    $output_path = $output_dir . $filename;
    
    // Simple HTML to PDF conversion using PHP's output buffering
    // This is a basic implementation - in a real application, use a proper PDF library like TCPDF, FPDF, or mPDF
    
    // Start output buffering
    ob_start();
    
    // Set headers for PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Include a basic HTML to PDF conversion script
    // This is a placeholder - in a real application, use a proper PDF library
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>PDF Document</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12pt;
                color: #000;
                margin: 20mm 15mm;
            }
            h1 {
                font-size: 18pt;
                color: #333;
                margin-bottom: 15mm;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 5mm 0;
            }
            table, th, td {
                border: 1px solid #ccc;
            }
            th, td {
                padding: 2mm;
                text-align: left;
            }
            th {
                background-color: #f1f1f1;
            }
            .footer {
                position: fixed;
                bottom: 10mm;
                width: 100%;
                text-align: center;
                font-size: 10pt;
                color: #666;
            }
            @page {
                margin: 20mm 15mm;
            }
        </style>
    </head>
    <body>
        ' . $html . '
        <div class="footer">
            ' . SITE_NAME . ' &copy; ' . date('Y') . '
        </div>
    </body>
    </html>';
    
    // Get the content of the output buffer
    $pdf_content = ob_get_clean();
    
    // Write the PDF content to the file
    if (file_put_contents($output_path, $pdf_content)) {
        return $output_path;
    }
    
    return false;
}

/**
 * Generate a lease agreement PDF
 * @param int $lease_id Lease ID
 * @return string|false Path to the generated PDF or false on failure
 */
function generate_lease_pdf($lease_id) {
    $lease = get_lease($lease_id);
    if (!$lease) {
        return false;
    }
    
    $student = get_user($lease['student_id']);
    $accommodation = get_accommodation($lease['accommodation_id']);
    
    if (!$student || !$accommodation) {
        return false;
    }
    
    // Prepare filename
    $filename = 'lease_' . $lease_id . '_' . time() . '.pdf';
    
    // Prepare HTML content
    $html = '
    <h1>LEASE AGREEMENT</h1>
    
    <p><strong>THIS LEASE AGREEMENT</strong> (the "Agreement") is made and entered into on ' . date('d F Y') . ' by and between:</p>
    
    <p><strong>HARAMBEE STUDENT LIVING</strong>, hereinafter referred to as the "LANDLORD"</p>
    
    <p>and</p>
    
    <p><strong>' . $student['full_name'] . '</strong>, hereinafter referred to as the "TENANT".</p>
    
    <h2>1. PREMISES</h2>
    <p>The Landlord hereby leases to the Tenant and the Tenant hereby leases from the Landlord, for residential purposes only, the following premises (the "Premises"):</p>
    <p><strong>' . $accommodation['name'] . '</strong><br>' . $accommodation['location'] . '</p>
    
    <h2>2. TERM</h2>
    <p>The term of this Agreement begins on <strong>' . format_date($lease['start_date']) . '</strong> and ends on <strong>' . format_date($lease['end_date']) . '</strong>.</p>
    
    <h2>3. RENT</h2>
    <p>The Tenant agrees to pay the Landlord a monthly rent of <strong>' . format_currency($lease['monthly_rent']) . '</strong>, payable in advance on the first day of each month.</p>
    
    <h2>4. SECURITY DEPOSIT</h2>
    <p>Upon execution of this Agreement, Tenant shall deposit with Landlord the sum of <strong>' . format_currency($lease['monthly_rent']) . '</strong> as a security deposit.</p>
    
    <h2>5. UTILITIES</h2>
    <p>The Tenant shall be responsible for the payment of all utilities and services, except for the following which shall be paid by the Landlord: [water, electricity, internet].</p>
    
    <h2>6. MAINTENANCE</h2>
    <p>The Tenant shall maintain the Premises in a clean and sanitary condition and shall not damage or misuse the Premises. The Tenant shall be responsible for any damage caused to the Premises beyond normal wear and tear.</p>
    
    <h2>7. RULES AND REGULATIONS</h2>
    <p>The Tenant agrees to comply with all rules and regulations governing the Premises as established by the Landlord from time to time.</p>
    
    <h2>8. TERMINATION</h2>
    <p>This Agreement may be terminated by either party with one month\'s written notice. The Landlord may terminate this Agreement immediately for any breach of this Agreement by the Tenant.</p>
    
    <h2>9. SIGNATURES</h2>
    <p>By signing below, the parties acknowledge that they have read and understood this Agreement and agree to be bound by its terms.</p>
    
    <table style="border: none; margin-top: 30px;">
        <tr>
            <td style="border: none; width: 50%;">
                <p>LANDLORD:</p>
                <p>Harambee Student Living</p>
                <p>_______________________</p>
                <p>Date: ' . date('d/m/Y') . '</p>
            </td>
            <td style="border: none; width: 50%;">
                <p>TENANT:</p>
                <p>' . $student['full_name'] . '</p>
                <p>_______________________</p>
                <p>Date: ' . date('d/m/Y') . '</p>
            </td>
        </tr>
    </table>
    ';
    
    // Generate and return the PDF
    return generate_pdf($html, $filename, 'uploads/leases/');
}

/**
 * Generate an invoice PDF
 * @param int $invoice_id Invoice ID
 * @return string|false Path to the generated PDF or false on failure
 */
function generate_invoice_pdf($invoice_id) {
    $invoice = get_invoice($invoice_id);
    if (!$invoice) {
        return false;
    }
    
    $lease = get_lease($invoice['lease_id']);
    if (!$lease) {
        return false;
    }
    
    $student = get_user($lease['student_id']);
    $accommodation = get_accommodation($lease['accommodation_id']);
    
    if (!$student || !$accommodation) {
        return false;
    }
    
    // Prepare filename
    $filename = 'invoice_' . $invoice_id . '_' . time() . '.pdf';
    
    // Prepare HTML content
    $html = '
    <div style="text-align: center; margin-bottom: 20px;">
        <h1>INVOICE</h1>
        <p>' . SITE_NAME . '</p>
    </div>
    
    <table style="border: none; width: 100%;">
        <tr>
            <td style="border: none; width: 50%; vertical-align: top;">
                <p><strong>BILLED TO:</strong></p>
                <p>' . $student['full_name'] . '<br>
                ' . $student['email'] . '<br>
                ' . $student['phone_number'] . '</p>
            </td>
            <td style="border: none; width: 50%; vertical-align: top; text-align: right;">
                <p><strong>INVOICE #:</strong> ' . str_pad($invoice['id'], 5, '0', STR_PAD_LEFT) . '</p>
                <p><strong>DATE:</strong> ' . date('d/m/Y') . '</p>
                <p><strong>DUE DATE:</strong> ' . format_date($invoice['due_date'], 'd/m/Y') . '</p>
                <p><strong>STATUS:</strong> ' . strtoupper($invoice['status']) . '</p>
            </td>
        </tr>
    </table>
    
    <table style="width: 100%; margin-top: 30px;">
        <tr>
            <th style="width: 70%;">DESCRIPTION</th>
            <th style="width: 30%; text-align: right;">AMOUNT</th>
        </tr>
        <tr>
            <td>' . $invoice['description'] . '<br>
            <small>Property: ' . $accommodation['name'] . '</small><br>
            <small>Period: ' . format_date($lease['start_date']) . ' to ' . format_date($lease['end_date']) . '</small>
            </td>
            <td style="text-align: right;">' . format_currency($invoice['amount']) . '</td>
        </tr>
        <tr>
            <td style="text-align: right;"><strong>TOTAL</strong></td>
            <td style="text-align: right;"><strong>' . format_currency($invoice['amount']) . '</strong></td>
        </tr>
    </table>
    
    <div style="margin-top: 30px;">
        <h3>PAYMENT METHODS</h3>
        <p>Please include your invoice number when making payment.</p>
        
        <h4>Bank Transfer</h4>
        <p>Bank: Example Bank<br>
        Account Name: Harambee Student Living<br>
        Account Number: 1234567890<br>
        Branch Code: 12345<br>
        Reference: INV' . str_pad($invoice['id'], 5, '0', STR_PAD_LEFT) . '</p>
        
        <h4>Terms & Conditions</h4>
        <p>Payment is due by the date specified on this invoice. Late payments may incur additional fees.</p>
    </div>
    ';
    
    // Generate and return the PDF
    return generate_pdf($html, $filename, 'uploads/invoices/');
}
