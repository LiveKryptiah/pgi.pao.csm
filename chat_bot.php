<?php
session_start();
require 'db.php';

// Security: Only logged-in admins can use the bot
if (!isset($_SESSION['admin_logged_in'])) {
    echo "Access denied.";
    exit;
}

$user_message = strtolower($_POST['message'] ?? '');

// 1. Query real-time data from your database
$total_responses = $pdo->query("SELECT COUNT(*) FROM responses")->fetchColumn();
$latest_date = $pdo->query("SELECT MAX(survey_date) FROM responses")->fetchColumn();

// 2. Simple Keyword-Based Logic
if (strpos($user_message, 'hello') !== false || strpos($user_message, 'hi') !== false) {
    echo "Hello! How can I help you analyze the dashboard today?";
} 
elseif (strpos($user_message, 'total') !== false || strpos($user_message, 'how many') !== false) {
    echo "Currently, you have <strong>$total_responses</strong> total client responses in the system. The latest submission was on $latest_date.";
}
elseif (strpos($user_message, 'sqd') !== false) {
    echo "SQD stands for <strong>Service Quality Dimensions</strong>. It measures things like Responsiveness, Reliability, Facilities, Communication, Costs, Integrity (Walang Palakasan), Professionalism, and Rules.";
}
elseif (strpos($user_message, 'export') !== false || strpos($user_message, 'print') !== false) {
    echo "To export data, scroll down to Section 6. You can use the 'Global Filter' at the top of the dashboard to pick a date, and the Export table will automatically update for you to print!";
}
elseif (strpos($user_message, 'cc') !== false || strpos($user_message, 'charter') !== false) {
    echo "CC stands for Citizen's Charter. The system tracks 3 things: <strong>CC1</strong> (Knowledge/Awareness), <strong>CC2</strong> (Visibility), and <strong>CC3</strong> (Helpfulness).";
}
elseif (strpos($user_message, 'suggestion') !== false || strpos($user_message, 'feedback') !== false) {
    $total_suggestions = $pdo->query("SELECT COUNT(*) FROM responses WHERE suggestions IS NOT NULL AND suggestions != '' AND suggestions != 'N/A'")->fetchColumn();
    echo "There are currently <strong>$total_suggestions</strong> actual written suggestions. You can view them in Section 7 of the dashboard.";
}
else {
    echo "I am a local system assistant. I can answer questions about your <strong>total records</strong>, <strong>suggestions</strong>, <strong>SQD meanings</strong>, or <strong>how to use features</strong> like export!";
}
?>