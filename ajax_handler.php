<?php
require_once 'common/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first.']);
    exit();
}

$response = [];
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Get current wallet balance for immediate response updates
$user_query = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $user_id");
$wallet_balance = mysqli_fetch_assoc($user_query)['wallet_balance'];

switch ($action) {
    case 'join_tournament':
        $tournament_id = (int)$_POST['tournament_id'];
        $promo_code = strtoupper(trim($_POST['promo_code']));
        $referrer_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT referred_by FROM users WHERE id = $user_id"))['referred_by'];

        mysqli_begin_transaction($conn);
        try {
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM participants WHERE user_id = ? AND tournament_id = ?");
            mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $tournament_id);
            mysqli_stmt_execute($stmt_check);
            if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) { throw new Exception("You have already joined this tournament."); }
            
            $stmt_tourney = mysqli_prepare($conn, "SELECT entry_fee FROM tournaments WHERE id = ?");
            mysqli_stmt_bind_param($stmt_tourney, "i", $tournament_id);
            mysqli_stmt_execute($stmt_tourney);
            $entry_fee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tourney))['entry_fee'];
            
            $final_fee = $entry_fee;
            if(!empty($promo_code)) {
                // Promo code logic here...
            }
            
            if ($wallet_balance < $final_fee) { throw new Exception("Insufficient wallet balance."); }

            mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $final_fee WHERE id = $user_id");
            mysqli_query($conn, "INSERT INTO participants (user_id, tournament_id) VALUES ($user_id, $tournament_id)");
            $desc = "Joined tournament #" . $tournament_id;
            mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($user_id, $final_fee, 'debit', '$desc')");
            
            if ($referrer_id) {
                // Commission logic here...
            }
            
            mysqli_commit($conn);
            $new_balance = $wallet_balance - $final_fee;
            $response = ['status' => 'success', 'message' => 'Successfully joined!', 'new_balance' => number_format($new_balance, 2)];
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $response = ['status' => 'error', 'message' => $e->getMessage()];
        }
        break;

    case 'cancel_join':
        $tournament_id = (int)$_POST['tournament_id'];

        mysqli_begin_transaction($conn);
        try {
            $tourney_q = mysqli_query($conn, "SELECT entry_fee, status FROM tournaments WHERE id = $tournament_id FOR UPDATE");
            $tournament = mysqli_fetch_assoc($tourney_q);

            if (!$tournament) { throw new Exception("Tournament not found."); }
            if ($tournament['status'] !== 'upcoming') { throw new Exception("You can only cancel entry for 'upcoming' tournaments."); }
            
            $delete_stmt = mysqli_prepare($conn, "DELETE FROM participants WHERE user_id = ? AND tournament_id = ?");
            mysqli_stmt_bind_param($delete_stmt, "ii", $user_id, $tournament_id);
            mysqli_stmt_execute($delete_stmt);
            
            if (mysqli_stmt_affected_rows($delete_stmt) === 0) {
                throw new Exception("You are not a participant of this tournament.");
            }

            $entry_fee = (float)$tournament['entry_fee'];
            mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $entry_fee WHERE id = $user_id");

            $refund_desc = "Refund for cancelled entry in tournament #" . $tournament_id;
            mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($user_id, $entry_fee, 'credit', '$refund_desc')");
            
            mysqli_commit($conn);
            $new_balance = $wallet_balance + $entry_fee;
            $response = ['status' => 'success', 'message' => 'Your entry has been cancelled and fee refunded!', 'new_balance' => number_format($new_balance, 2)];

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $response = ['status' => 'error', 'message' => $e->getMessage()];
        }
        break;

    default:
        $response = ['status' => 'error', 'message' => 'Invalid action specified.'];
        break;
}

echo json_encode($response);
exit();
?>