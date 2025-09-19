<?php
@ini_set('max_execution_time', 28);
set_time_limit(28);

require_once 'common/config.php';

// --- CONFIGURATIONS ---
$period_duration_seconds = 60; // 1 Minute Round

// --- PAYOUT RATES ---
$payout_rates = [
    'color' => 1.96, 'number' => 9, 'size' => 1.96,
    'violet' => 4.5, 'half_won' => 1.5
];

// --- Find the latest period ---
$latest_period_q = mysqli_query($conn, "SELECT * FROM color_game_periods ORDER BY id DESC LIMIT 1");
$latest_period = mysqli_fetch_assoc($latest_period_q);

$now = time();

// --- LOGIC 1: Create a new period if needed ---
if (!$latest_period || $latest_period['status'] === 'completed') {
    $period_serial = $latest_period ? ((int)substr($latest_period['period_id'], 8)) + 1 : 1;
    $new_period_id = date('Ymd') . str_pad($period_serial, 4, '0', STR_PAD_LEFT);
    
    $start_time = date('Y-m-d H:i:s', $now);
    $end_time = date('Y-m-d H:i:s', $now + $period_duration_seconds);

    $stmt = mysqli_prepare($conn, "INSERT INTO color_game_periods (period_id, start_time, end_time, status) VALUES (?, ?, ?, 'betting')");
    mysqli_stmt_bind_param($stmt, "sss", $new_period_id, $start_time, $end_time);
    mysqli_stmt_execute($stmt);
    echo "New period $new_period_id created.";
    exit();
}

// --- LOGIC 2: Process results if time is up ---
if ($latest_period['status'] === 'betting' && $now >= strtotime($latest_period['end_time'])) {
    $period_id = $latest_period['id'];
    
    // ## PROFIT MAXIMIZATION LOGIC STARTS HERE ##

    // 1. Fetch all bets for the current period
    $all_bets_q = mysqli_query($conn, "SELECT * FROM color_game_bets WHERE period_id = $period_id AND status = 'pending'");
    $all_bets = mysqli_fetch_all($all_bets_q, MYSQLI_ASSOC);

    $payouts_per_number = [];

    // 2. Simulate payout for each possible outcome (0-9)
    for ($num = 0; $num <= 9; $num++) {
        $current_payout = 0;
        $simulated_size = ($num >= 5) ? 'big' : 'small';
        $simulated_colors = [];
        if (in_array($num, [1, 3, 7, 9])) { $simulated_colors[] = 'green'; }
        if (in_array($num, [2, 4, 6, 8])) { $simulated_colors[] = 'red'; }
        if ($num == 0) { $simulated_colors = ['violet', 'red']; }
        if ($num == 5) { $simulated_colors = ['violet', 'green']; }

        foreach ($all_bets as $bet) {
            $bet_amount = (float)$bet['bet_amount'];
            if ($bet['bet_type'] === 'number' && $bet['bet_on'] == $num) {
                $current_payout += $bet_amount * $payout_rates['number'];
            } elseif ($bet['bet_type'] === 'size' && $bet['bet_on'] === $simulated_size) {
                $current_payout += $bet_amount * $payout_rates['size'];
            } elseif ($bet['bet_type'] === 'color' && in_array($bet['bet_on'], $simulated_colors)) {
                if ($bet['bet_on'] === 'violet') { $current_payout += $bet_amount * $payout_rates['violet']; }
                elseif (in_array('violet', $simulated_colors)) { $current_payout += $bet_amount * $payout_rates['half_won']; }
                else { $current_payout += $bet_amount * $payout_rates['color']; }
            }
        }
        $payouts_per_number[$num] = $current_payout;
    }

    // 3. Find the number that results in the minimum payout for the house
    asort($payouts_per_number); // Sort array by value (lowest payout first)
    $winning_number = key($payouts_per_number); // Get the key (the number) of the first element

    // ## PROFIT MAXIMIZATION LOGIC ENDS HERE ##

    // 4. Set final winning properties based on the chosen winning number
    $winning_size = ($winning_number >= 5) ? 'big' : 'small';
    $final_colors = [];
    if (in_array($winning_number, [1, 3, 7, 9])) { $final_colors[] = 'green'; }
    if (in_array($winning_number, [2, 4, 6, 8])) { $final_colors[] = 'red'; }
    if ($winning_number == 0) { $final_colors = ['violet', 'red']; }
    if ($winning_number == 5) { $final_colors = ['violet', 'green']; }
    $winning_color_str = implode(',', $final_colors);

    // 5. Update the period with the calculated (most profitable) result
    mysqli_query($conn, "UPDATE color_game_periods SET winning_number = $winning_number, winning_color = '$winning_color_str', winning_size = '$winning_size', status = 'completed' WHERE id = $period_id");

    // 6. Process final payouts based on the profitable result
    foreach ($all_bets as $bet) {
        $user_id = $bet['user_id'];
        $bet_amount = (float)$bet['bet_amount'];
        $winnings = 0;
        $new_status = 'lost';

        // Check for win condition against the calculated winning number
        if ($bet['bet_type'] === 'number' && $bet['bet_on'] == $winning_number) {
            $winnings = $bet_amount * $payout_rates['number']; $new_status = 'won';
        } elseif ($bet['bet_type'] === 'size' && $bet['bet_on'] === $winning_size) {
            $winnings = $bet_amount * $payout_rates['size']; $new_status = 'won';
        } elseif ($bet['bet_type'] === 'color' && in_array($bet['bet_on'], $final_colors)) {
            if ($bet['bet_on'] === 'violet') { $winnings = $bet_amount * $payout_rates['violet']; $new_status = 'won'; }
            elseif (in_array('violet', $final_colors)) { $winnings = $bet_amount * $payout_rates['half_won']; $new_status = 'half_won'; }
            else { $winnings = $bet_amount * $payout_rates['color']; $new_status = 'won'; }
        }
        
        mysqli_query($conn, "UPDATE color_game_bets SET status = '$new_status', winnings = $winnings WHERE id = {$bet['id']}");
        if ($winnings > 0) {
            mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $winnings WHERE id = $user_id");
            $desc = "Won in Color Game #" . $latest_period['period_id'];
            mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($user_id, $winnings, 'credit', '$desc')");
        }
    }
    
    echo "Period " . $latest_period['period_id'] . " processed. Profitable Winning Number: $winning_number";
    exit();
}

echo "No action needed for period " . $latest_period['period_id'];
?>