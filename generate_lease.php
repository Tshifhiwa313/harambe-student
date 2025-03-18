<?php
function generateLeaseAgreement($type, $data) {
    if ($type == 'highlands') {
        // Generate Highlands lease agreement
        $agreement = "
        ACCOMMODATION AGREEMENT

        BETWEEN
        Highlands Lofts Residence 201001321807
        (“the Lessor”)

        AND
        {$data['full_name']} ({$data['id_passport']})
        (“the Lessee”)

        IN RELATION TO THE ACCOMMODATION ESTABLISHMENT KNOWN AS:
        Highlands Lofts Residence

        Key Information of Accommodation Agreement
        Full Name and Surname: {$data['full_name']}
        Gender: {$data['gender']}
        ID/Passport No: {$data['id_passport']}
        Student Number: {$data['student_number']}
        Email address: {$data['email']}
        Cell No: {$data['cell_no']}
        Alternative phone No: {$data['alt_phone_no']}
        Sponsor A: {$data['sponsor_name']}
        Sponsor ID/Registration No: {$data['sponsor_id']}
        Sponsor Email address: {$data['sponsor_email']}
        Sponsor Cell No: {$data['sponsor_cell_no']}
        Sponsor Alternative phone No: {$data['sponsor_alt_phone_no']}
        Physical Address: {$data['physical_address']}
        Check-in / Commencement date: {$data['check_in_date']}
        Anticipated check-out: {$data['check_out_date']}
        Non-refundable Administration Fee: {$data['admin_fee']}
        Security Deposit Amount Required (ZAR): {$data['security_deposit']}
        Accommodation Fee: {$data['accommodation_fee']}
        Total Monthly Amount Due: {$data['total_monthly_amount']}
        ";
    } elseif ($type == 'saratoga') {
        // Generate Saratoga lease agreement
        $agreement = "
        ACCOMMODATION AGREEMENT

        BETWEEN
        Saratoga Pty Ltd K2020142989
        (“the Lessor”)

        AND
        {$data['full_name']} ({$data['id_passport']})
        (“the Lessee”)

        IN RELATION TO THE ACCOMMODATION ESTABLISHMENT KNOWN AS:
        21 SARATOGA

        Key Information of Accommodation Agreement
        Full Name and Surname: {$data['full_name']}
        Gender: {$data['gender']}
        ID/Passport No: {$data['id_passport']}
        Student Number: {$data['student_number']}
        Email address: {$data['email']}
        Cell No: {$data['cell_no']}
        Alternative phone No: {$data['alt_phone_no']}
        Sponsor A: {$data['sponsor_name']}
        Sponsor ID/Registration No: {$data['sponsor_id']}
        Sponsor Email address: {$data['sponsor_email']}
        Sponsor Cell No: {$data['sponsor_cell_no']}
        Sponsor Alternative phone No: {$data['sponsor_alt_phone_no']}
        Physical Address: {$data['physical_address']}
        Check-in / Commencement date: {$data['check_in_date']}
        Anticipated check-out: {$data['check_out_date']}
        Non-refundable Administration Fee: {$data['admin_fee']}
        Security Deposit Amount Required (ZAR): {$data['security_deposit']}
        Accommodation Fee: {$data['accommodation_fee']}
        Total Monthly Amount Due: {$data['total_monthly_amount']}
        ";
    }
    return $agreement;
}
?>
