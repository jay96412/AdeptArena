<?php
require_once 'common/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Function to calculate odds
function calculateOdds($conn, $event_id) {
    $house_edge = 0.04; // 4% commission for the house
    
    // Get total pool for the event
    $total_pool_q = mysqli_query($conn, "SELECT SUM(bet_amount) as total FROM opinion_bets WHERE event_id = $event_id");
    $total_pool = (float)mysqli_fetch_assoc($total_pool_q)['total'];

    // Get pools for each option
    $options_pool_q = mysqli_query($conn, "SELECT option_index, SUM(bet_amount) as total FROM opinion_bets WHERE event_id = $event_id GROUP BY option_index");
    $options_pool = [];
    while ($row = mysqli_fetch_assoc($options_pool_q)) {
        $options_pool[$row['option_index']] = (float)$row['total'];
    }

    // Get the options array from the event itself
    $event_q = mysqli_query($conn, "SELECT options FROM opinion_events WHERE id = $event_id");
    $options_json = mysqli_fetch_assoc($event_q)['options'];
    $options_array = json_decode($options_json, true);

    $odds_data = [];
    foreach ($options_array as $index => $option_name) {
        $pool_for_this_option = $options_pool[$index] ?? 0;
        $odds = 0;
        if ($pool_for_this_option > 0) {
            $odds = ($total_pool / $pool_for_this_option) * (1 - $house_edge);
        }
        $odds_data[$index] = [
            'odds' => round($odds, 2),
            'pool' => round($pool_for_this_option, 2)
        ];
    }
    return ['total_pool' => round($total_pool, 2), 'odds_data' => $odds_data];
}


switch($action) {
    case 'get_event_data':
        $event_id = (int)$_GET['event_id'];
        $data = calculateOdds($conn, $event_id);
        echo json_encode($data);
        break;

    case 'place_bet':
        if ($user_id == 0) { exit(json_encode(['status' => 'error', 'message' => 'Please login to place a bet.'])); }

        $event_id = (int)$_POST['event_id'];
        $option_index = (int)$_POST['option_index'];
        $bet_amount = (float)$_POST['bet_amount'];

        if ($bet_amount <= 0) { exit(json_encode(['status' => 'error', 'message' => 'Invalid bet amount.'])); }
        
        $current_odds_data = calculateOdds($conn, $event_id);
        $odds_at_bet_time = $current_odds_data['odds_data'][$option_index]['odds'];
        if ($odds_at_bet_time == 0) { $odds_at_bet_time = (1 / 0.5) * (1-0.04); } // Default odds for first bet

        mysqli_begin_transaction($conn);
        try {
            $user_q = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $user_id FOR UPDATE");
            $wallet_balance = (float)mysqli_fetch_assoc($user_q)['wallet_balance'];
            
            if ($wallet_balance < $bet_amount) { throw new Exception("Insufficient wallet balance."); }

            mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $bet_amount WHERE id = $user_id");
            
            $stmt = mysqli_prepare($conn, "INSERT INTO opinion_bets (user_id, event_id, option_index, bet_amount, odds_at_bet_time) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiidd", $user_id, $event_id, $option_index, $bet_amount, $odds_at_bet_time);
            mysqli_stmt_execute($stmt);

            $desc = "Bet on opinion event #" . $event_id;
            mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($user_id, $bet_amount, 'debit', '$desc')");
            
            mysqli_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Bet placed successfully!']);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
}
exit();
?>