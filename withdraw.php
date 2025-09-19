<?php
include 'common/header.php';
$message = '';
$message_type = '';
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
    $amount = $_POST['amount'];
    $upi_id = trim($_POST['upi_id']);
    
    if (!empty($amount) && !empty($upi_id) && is_numeric($amount) && $amount > 0) {
        // Check if user has sufficient balance
        $stmt_bal = mysqli_prepare($conn, "SELECT wallet_balance FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt_bal, "i", $user_id);
        mysqli_stmt_execute($stmt_bal);
        $current_balance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bal))['wallet_balance'];

        if ($current_balance >= $amount) {
            mysqli_begin_transaction($conn);
            try {
                // Deduct amount from wallet immediately
                $stmt_deduct = mysqli_prepare($conn, "UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_deduct, "di", $amount, $user_id);
                mysqli_stmt_execute($stmt_deduct);

                // Create withdrawal request
                $stmt_req = mysqli_prepare($conn, "INSERT INTO withdrawal_requests (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'pending')");
                mysqli_stmt_bind_param($stmt_req, "ids", $user_id, $amount, $upi_id);
                mysqli_stmt_execute($stmt_req);
                
                mysqli_commit($conn);
                $message = "Withdrawal request submitted successfully!";
                $message_type = 'success';
                // Refresh wallet balance for header
                $wallet_balance -= $amount;

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = 'Something went wrong. Please try again.';
                $message_type = 'error';
            }
        } else {
            $message = 'Insufficient balance for this withdrawal.';
            $message_type = 'error';
        }
    } else {
        $message = 'Please enter a valid amount and UPI ID.';
        $message_type = 'error';
    }
}
?>

<div class="py-6 max-w-lg mx-auto">
    <a href="wallet.php" class="text-purple-400 hover:underline mb-4 inline-block">&larr; Back to Wallet</a>
    <h1 class="text-3xl font-bold mb-2">Withdraw Money</h1>
    <p class="text-gray-400 mb-6">Your current balance: <span class="font-bold text-white">₹<?= number_format($wallet_balance, 2) ?></span></p>

    <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
        <h2 class="text-xl font-semibold mb-1">Enter Withdrawal Details</h2>
        <p class="text-gray-400 text-sm mb-4">Amount will be processed within 24 hours.</p>
        <form method="POST">
            <input type="hidden" name="submit_withdrawal">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Amount to Withdraw (₹)</label>
                    <input type="number" name="amount" step="0.01" class="bg-gray-700 w-full p-3 rounded-lg" placeholder="Min. ₹100" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Your UPI ID</label>
                    <input type="text" name="upi_id" class="bg-gray-700 w-full p-3 rounded-lg" placeholder="e.g., yourname@okhdfcbank" required>
                </div>
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg transition-transform hover:scale-105">Request Withdrawal</button>
            </div>
        </form>
    </div>
</div>

<?php include 'common/bottom.php'; ?>