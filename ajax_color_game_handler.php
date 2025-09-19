<?php
require_once 'common/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first.']);
    exit();
}

switch ($action) {
    case 'get_state':
        $latest_period_q = mysqli_query($conn, "SELECT * FROM color_game_periods ORDER BY id DESC LIMIT 1");
        $latest_period = mysqli_fetch_assoc($latest_period_q);
        
        $history_q = mysqli_query($conn, "SELECT winning_number, winning_color FROM color_game_periods WHERE status = 'completed' ORDER BY id DESC LIMIT 10");
        $history = mysqli_fetch_all($history_q, MYSQLI_ASSOC);

        echo json_encode([
            'period_id' => $latest_period['period_id'],
            'end_time' => $latest_period['end_time'],
            'status' => $latest_period['status'],
            'history' => $history
        ]);
        break;

    case 'place_bet':
        $period_id = (int)$_POST['period_id'];
        $bet_type = $_POST['bet_type']; // 'color', 'size', or 'number'
        $bet_on = $_POST['bet_on'];
        $bet_amount = (float)$_POST['bet_amount'];

        mysqli_begin_transaction($conn);
        try {
            $period_q = mysqli_query($conn, "SELECT end_time, status FROM color_game_periods WHERE id = $period_id FOR UPDATE");
            $period = mysqli_fetch_assoc($period_q);
            if (!$period || $period['status'] !== 'betting' || time() > (strtotime($period['end_time']) - 5) ) {
                throw new Exception("Betting for this period has closed.");
            }

            $user_q = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $user_id FOR UPDATE");
            $wallet_balance = (float)mysqli_fetch_assoc($user_q)['wallet_balance'];
            
            if ($wallet_balance < $bet_amount) { throw new Exception("Insufficient wallet balance."); }
            
            // Payout rates
            $rates = ['color' => 1.96, 'size' => 1.96, 'number' => 9, 'violet' => 4.5];
            $payout_rate = $rates[$bet_on] ?? $rates[$bet_type];

            mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $bet_amount WHERE id = $user_id");
            
            $stmt = mysqli_prepare($conn, "INSERT INTO color_game_bets (user_id, period_id, bet_type, bet_on, bet_amount, payout_rate) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisssd", $user_id, $period_id, $bet_type, $bet_on, $bet_amount, $payout_rate);
            mysqli_stmt_execute($stmt);

            $desc = "Bet on Color Game #" . $period_id;
            mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($user_id, $bet_amount, 'debit', '$desc')");
            
            mysqli_commit($conn);
            $new_balance = $wallet_balance - $bet_amount;
            echo json_encode(['status' => 'success', 'message' => 'Bet placed successfully!', 'new_balance' => number_format($new_balance, 2)]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
}
exit();
?>