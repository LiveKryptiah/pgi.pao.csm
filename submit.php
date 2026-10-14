<?php
require 'db.php'; // Ensure your database connection is correct

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Helper to ensure N/A is always saved perfectly ---
    function clean_sqd($val) {
        if ($val === '6' || $val === 'N/A') return 'N/A';
        return $val ?? 'N/A';
    }

    // Collect all variables
    $survey_date = $_POST['survey_date'];
    $client_type = $_POST['client_type'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $region = $_POST['region'] ?? 'Region II';
    $province = $_POST['province'] ?? '';
    $municipality = $_POST['municipality'] ?? '';
    $age_group = $_POST['age_group'] ?? '';
    $transaction_type = $_POST['transaction_type'] ?? '';
    
    // Normalize location values if outside Region II or outside Isabela
    if ($region !== 'Region II') {
        if (empty($province)) $province = 'Outside Region 2';
        if (empty($municipality)) $municipality = 'Outside Isabela';
    } elseif ($province === 'Outside Isabela') {
        if (empty($municipality)) $municipality = 'Outside Isabela';
    }
    
    // CC Ratings
    $cc1 = $_POST['cc1'] ?? '';
    $cc2 = $_POST['cc2'] ?? '';
    $cc3 = $_POST['cc3'] ?? '';
    
    // SQD Ratings (Automatically catches 6 and converts to N/A)
    $sqd0 = clean_sqd($_POST['sqd0'] ?? null);
    $sqd1 = clean_sqd($_POST['sqd1'] ?? null);
    $sqd2 = clean_sqd($_POST['sqd2'] ?? null);
    $sqd3 = clean_sqd($_POST['sqd3'] ?? null);
    $sqd4 = clean_sqd($_POST['sqd4'] ?? null);
    $sqd5 = clean_sqd($_POST['sqd5'] ?? null);
    $sqd6 = clean_sqd($_POST['sqd6'] ?? null); 
    $sqd7 = clean_sqd($_POST['sqd7'] ?? null);
    $sqd8 = clean_sqd($_POST['sqd8'] ?? null);

    $suggestions = $_POST['suggestions'] ?? '';
    $email = $_POST['email'] ?? '';

    // ==========================================
    // STEP 1: INSERT RECORD WITH A PLACEHOLDER
    // ==========================================
    $sql = "INSERT INTO responses (
                control_number, survey_date, client_type, gender, region, province, municipality, 
                age_group, transaction_type, cc1, cc2, cc3, 
                sqd0, sqd1, sqd2, sqd3, sqd4, sqd5, sqd6, sqd7, sqd8, 
                suggestions, email
            ) 
            VALUES (
                'PENDING', :sd, :ct, :gen, :reg, :pro, :mun, 
                :age, :trans, :cc1, :cc2, :cc3, 
                :sq0, :sq1, :sq2, :sq3, :sq4, :sq5, :sq6, :sq7, :sq8, 
                :sug, :email
            )";

    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':sd' => $survey_date, ':ct' => $client_type, ':gen' => $gender, 
        ':reg' => $region, ':pro' => $province, ':mun' => $municipality, ':age' => $age_group, ':trans' => $transaction_type, 
        ':cc1' => $cc1, ':cc2' => $cc2, ':cc3' => $cc3,
        ':sq0' => $sqd0, ':sq1' => $sqd1, ':sq2' => $sqd2, 
        ':sq3' => $sqd3, ':sq4' => $sqd4, ':sq5' => $sqd5,
        ':sq6' => $sqd6, ':sq7' => $sqd7, ':sq8' => $sqd8,
        ':sug' => $suggestions, ':email' => $email
    ]);

    // Get the ID of the record we just inserted
    $new_record_id = $pdo->lastInsertId();

    // ==========================================
    // STEP 2: RE-CALCULATE ALL CONTROL NUMBERS CHRONOLOGICALLY
    // ==========================================
    // Fetch ALL records, strictly sorted by Date (Oldest to Newest)
    // If two records share the exact same date, it uses the ID as a tie-breaker
    $fetch_stmt = $pdo->query("SELECT id, survey_date FROM responses ORDER BY survey_date ASC, id ASC");
    $all_records = $fetch_stmt->fetchAll(PDO::FETCH_ASSOC);

    $counter = 1;
    $final_control_number = '';

    // Prepare the update statement
    $update_stmt = $pdo->prepare("UPDATE responses SET control_number = :cn WHERE id = :id");

    foreach ($all_records as $row) {
        // 1. Get the 2-digit month from the row's specific date
        $month = date('m', strtotime($row['survey_date']));
        
        // 2. Create the 3-digit running sequence based on chronological order
        $padded_sequence = sprintf("%03d", $counter);
        
        // 3. Combine them (e.g., E06-017)
        $new_cn = "E" . $month . "-" . $padded_sequence;

        // 4. Update this specific row in the database with the corrected number
        $update_stmt->execute([
            ':cn' => $new_cn,
            ':id' => $row['id']
        ]);

        // If this is the record the user JUST inserted, save it so we can show it in the alert
        if ($row['id'] == $new_record_id) {
            $final_control_number = $new_cn;
        }

        $counter++; // Move to the next sequence number
    }

    // ==========================================
    // STEP 3: SHOW SUCCESS MESSAGE
    // ==========================================
    echo "<script>
            alert('Survey submitted successfully! Control No: " . $final_control_number . "');
            window.location.href = 'index.php';
          </script>";
}
?>