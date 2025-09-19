<?php
include 'common/header.php';
$message = '';
$message_type = '';
$tournament_id = $_GET['id'] ?? 0;

if (!$tournament_id) {
    header('Location: tournament.php');
    exit();
}

// Fetch tournament details first
$stmt_t = mysqli_prepare($conn, "SELECT * FROM tournaments WHERE id = ?");
mysqli_stmt_bind_param($stmt_t, "i", $tournament_id);
mysqli_stmt_execute($stmt_t);
$tournament = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t));


// LOGIC 1: Handle Room Details & Status Update (including REFUNDS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room'])) {
    $room_id = $_POST['room_id'];
    $room_password = $_POST['room_password'];
    $status = $_POST['status'];
    
    // Check if status is being changed to 'cancelled' to process refunds
    if ($status === 'cancelled' && $tournament['status'] !== 'cancelled') {
        $entry_fee = $tournament['entry_fee'];
        if ($entry_fee > 0) {
            $participants_q = mysqli_query($conn, "SELECT user_id FROM participants WHERE tournament_id = $tournament_id");
            mysqli_begin_transaction($conn);
            try {
                while ($participant = mysqli_fetch_assoc($participants_q)) {
                    $p_user_id = $participant['user_id'];
                    // 1. Refund amount to user's wallet
                    mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $entry_fee WHERE id = $p_user_id");
                    // 2. Log transaction for the user
                    $refund_desc = "Refund for cancelled tournament #" . $tournament_id;
                    mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($p_user_id, $entry_fee, 'credit', '$refund_desc')");
                }
                mysqli_commit($conn);
                $message = "Tournament cancelled and entry fees refunded to all participants!";
                $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Error during refund process.";
                $message_type = 'error';
            }
        }
    }

    $stmt = mysqli_prepare($conn, "UPDATE tournaments SET room_id = ?, room_password = ?, status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "sssi", $room_id, $room_password, $status, $tournament_id);
    if (mysqli_stmt_execute($stmt)) {
        if(empty($message)) { // Show this message only if refund message is not already set
            $message = "Room details updated successfully!";
            $message_type = 'success';
        }
        // Refresh tournament data
        $stmt_t_refresh = mysqli_prepare($conn, "SELECT * FROM tournaments WHERE id = ?");
        mysqli_stmt_bind_param($stmt_t_refresh, "i", $tournament_id);
        mysqli_stmt_execute($stmt_t_refresh);
        $tournament = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t_refresh));
    } else {
        $message = "Failed to update details."; $message_type = 'error';
    }
}

// LOGIC 2: Handle Multi-Winner Declaration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['declare_winners'])) {
    $winners_data = $_POST['winners'];
    $prize_pool = (float)$tournament['prize_pool'];
    $total_prize_distributed = 0;

    foreach ($winners_data as $winner) { $total_prize_distributed += (float)$winner['prize']; }

    if ($total_prize_distributed > $prize_pool) {
        $message = "Error: Total prize distributed (₹$total_prize_distributed) cannot be more than the prize pool (₹$prize_pool).";
        $message_type = 'error';
    } else {
        mysqli_begin_transaction($conn);
        try {
            foreach ($winners_data as $winner) {
                if (!empty($winner['user_id']) && (float)$winner['prize'] > 0) {
                    $w_user_id = $winner['user_id'];
                    $w_prize = (float)$winner['prize'];
                    $w_rank = $winner['rank'];

                    mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $w_prize WHERE id = $w_user_id");
                    $win_desc = "Prize for Rank #$w_rank in tournament #" . $tournament_id;
                    mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($w_user_id, $w_prize, 'credit', '$win_desc')");
                    mysqli_query($conn, "INSERT INTO winners (tournament_id, user_id, `rank`, prize_amount) VALUES ($tournament_id, $w_user_id, $w_rank, $w_prize)");
                }
            }
            mysqli_query($conn, "UPDATE tournaments SET status = 'completed', winner_id = {$winners_data[0]['user_id']} WHERE id = $tournament_id"); // Set 1st prize winner as main winner
            mysqli_commit($conn);
            $message = "Winners declared & prizes distributed!"; $message_type = 'success';
             // Refresh tournament data to show results
            $stmt_t_refresh = mysqli_prepare($conn, "SELECT * FROM tournaments WHERE id = ?");
            mysqli_stmt_bind_param($stmt_t_refresh, "i", $tournament_id);
            mysqli_stmt_execute($stmt_t_refresh);
            $tournament = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t_refresh));
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error: " . $e->getMessage(); $message_type = 'error';
        }
    }
}

// Fetch participants for dropdowns
$stmt_p = mysqli_prepare($conn, "SELECT u.id, u.username FROM users u JOIN participants p ON u.id = p.user_id WHERE p.tournament_id = ?");
mysqli_stmt_bind_param($stmt_p, "i", $tournament_id);
mysqli_stmt_execute($stmt_p);
$participants_result = mysqli_stmt_get_result($stmt_p);
$participants = mysqli_fetch_all($participants_result, MYSQLI_ASSOC);

// Fetch winners if tournament is completed
$winners = [];
if ($tournament['status'] === 'completed') {
    $winners_q = mysqli_query($conn, "SELECT w.*, u.username FROM winners w JOIN users u ON w.user_id = u.id WHERE w.tournament_id = $tournament_id ORDER BY w.rank ASC");
    $winners = mysqli_fetch_all($winners_q, MYSQLI_ASSOC);
}
?>

<a href="tournament.php" class="text-purple-400 hover:underline mb-6 block">&larr; Back to Tournaments</a>
<h1 class="text-3xl font-bold text-white mb-2">Manage: <?= htmlspecialchars($tournament['title']) ?></h1>
<p class="text-gray-400 mb-6">Game: <?= htmlspecialchars($tournament['game_name']) ?> | Prize Pool: <span class="font-bold">₹<?= number_format($tournament['prize_pool']) ?></span></p>

<?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
            <h2 class="text-xl font-semibold mb-4">Room & Status</h2>
            <form method="POST">
                <input type="hidden" name="update_room">
                <div class="space-y-4">
                    <input type="text" name="room_id" placeholder="Room ID" class="bg-gray-700 w-full p-2.5 rounded-lg" value="<?= htmlspecialchars($tournament['room_id'] ?? '') ?>">
                    <input type="text" name="room_password" placeholder="Room Password" class="bg-gray-700 w-full p-2.5 rounded-lg" value="<?= htmlspecialchars($tournament['room_password'] ?? '') ?>">
                    <select name="status" class="bg-gray-700 w-full p-2.5 rounded-lg">
                        <option value="upcoming" <?= $tournament['status'] == 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="live" <?= $tournament['status'] == 'live' ? 'selected' : '' ?>>Live</option>
                        <option value="completed" <?= $tournament['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $tournament['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg">Update Details</button>
                </div>
            </form>
        </div>

        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
            <h2 class="text-xl font-semibold mb-4">Participants (<?= count($participants) ?>)</h2>
            <ul class="space-y-2 max-h-96 overflow-y-auto">
                <?php foreach($participants as $p): ?>
                <li class="bg-gray-700 p-2 rounded-md"><?= htmlspecialchars($p['username']) ?></li>
                <?php endforeach; if(empty($participants)): ?>
                <li class="text-gray-400">No participants yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="lg:col-span-2">
        <?php if ($tournament['status'] !== 'completed' && $tournament['status'] !== 'cancelled'): ?>
        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
            <h2 class="text-xl font-semibold mb-4">Declare Winners</h2>
            <form method="POST">
                <input type="hidden" name="declare_winners">
                <div class="space-y-4">
                    <?php for ($i = 0; $i < 3; $i++): $rank = $i + 1; ?>
                    <div>
                        <label class="block text-sm mb-1 text-gray-300"><?= $rank . ($rank == 1 ? 'st' : ($rank == 2 ? 'nd' : 'rd')) ?> Place Winner</label>
                        <div class="flex gap-2">
                            <select name="winners[<?= $i ?>][user_id]" class="bg-gray-700 w-2/3 p-2.5 rounded-lg">
                                <option value="">-- Select Participant --</option>
                                <?php foreach($participants as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['username']) ?></option><?php endforeach; ?>
                            </select>
                            <input type="number" step="0.01" name="winners[<?= $i ?>][prize]" class="bg-gray-700 w-1/3 p-2.5 rounded-lg" placeholder="Prize (₹)">
                            <input type="hidden" name="winners[<?= $i ?>][rank]" value="<?= $rank ?>">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                <button type="submit" class="w-full mt-6 bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-4 rounded-lg">Declare Winners & Distribute</button>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
            <h2 class="text-xl font-semibold mb-4">Match Results</h2>
            <?php if ($tournament['status'] === 'cancelled'): ?>
                <p class="text-center text-red-400 bg-red-500/10 p-4 rounded-lg">This tournament was cancelled.</p>
            <?php elseif (!empty($winners)): ?>
            <div class="space-y-3">
                <?php foreach($winners as $winner): ?>
                <div class="flex items-center justify-between bg-gray-700/50 p-3 rounded-lg">
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-lg text-yellow-300">#<?= $winner['rank'] ?></span>
                        <span class="font-medium"><?= htmlspecialchars($winner['username']) ?></span>
                    </div>
                    <span class="font-bold text-green-400">+ ₹<?= number_format($winner['prize_amount']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                 <p class="text-center text-gray-400 p-4">Results have not been declared yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'common/bottom.php'; ?>