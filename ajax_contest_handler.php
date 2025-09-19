<?php
require_once 'common/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login to participate.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'submit_contest_entry') {
    $contest_id = (int)$_POST['contest_id'];
    $answers = $_POST['answers'] ?? [];

    mysqli_begin_transaction($conn);
    try {
        // Step 1: Fetch contest details and lock the row
        $contest_q = mysqli_query($conn, "SELECT entry_fee, status FROM contests WHERE id = $contest_id FOR UPDATE");
        $contest = mysqli_fetch_assoc($contest_q);

        if (!$contest) { throw new Exception("Contest not found."); }
        if ($contest['status'] !== 'upcoming') { throw new Exception("This contest is no longer open for entries."); }

        // Step 2: Check if user has already participated
        $participant_q = mysqli_query($conn, "SELECT id FROM contest_participants WHERE user_id = $user_id AND contest_id = $contest_id");
        if (mysqli_num_rows($participant_q) > 0) { throw new Exception("You have already joined this contest."); }

        // Step 3: Check user's wallet balance
        $user_q = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $user_id FOR UPDATE");
        $wallet_balance = (float)mysqli_fetch_assoc($user_q)['wallet_balance'];
        $entry_fee = (float)$contest['entry_fee'];
        if ($wallet_balance < $entry_fee) { throw new Exception("Insufficient wallet balance."); }

        // Step 4: Deduct entry fee and log transaction
        mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $entry_fee WHERE id = $user_id");
        $desc = "Entry for contest #" . $contest_id;
        mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($user_id, $entry_fee, 'debit', '$desc')");
        
        // Step 5: Add user to participants
        mysqli_query($conn, "INSERT INTO contest_participants (user_id, contest_id) VALUES ($user_id, $contest_id)");
        $participant_id = mysqli_insert_id($conn);

        // Step 6: Save all answers
        foreach ($answers as $question_id => $selected_option_index) {
            $q_id = (int)$question_id;
            $opt_idx = (int)$selected_option_index;
            $stmt_ans = mysqli_prepare($conn, "INSERT INTO contest_answers (participant_id, question_id, selected_option_index) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt_ans, "iii", $participant_id, $q_id, $opt_idx);
            mysqli_stmt_execute($stmt_ans);
        }

        mysqli_commit($conn);
        $new_balance = $wallet_balance - $entry_fee;
        echo json_encode(['status' => 'success', 'message' => 'Your entry is confirmed. Good luck!', 'new_balance' => number_format($new_balance, 2)]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
?>