<?php
include 'common/header.php';
$message = '';
$message_type = '';
$generated_code = null;
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_card'])) {
    $amount = (float)$_POST['amount'];
    
    if ($amount > 0) {
        // Check if user has sufficient balance
        if ($wallet_balance >= $amount) {
            mysqli_begin_transaction($conn);
            try {
                // 1. Deduct amount from buyer's wallet
                mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $amount WHERE id = $user_id");
                
                // 2. Generate a unique gift code
                $code = 'GIFT-' . strtoupper(bin2hex(random_bytes(6)));

                // 3. Insert the new gift card into the table
                $stmt = mysqli_prepare($conn, "INSERT INTO gift_cards (code, amount, created_by_user_id) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sdi", $code, $amount, $user_id);
                mysqli_stmt_execute($stmt);
                
                mysqli_commit($conn);
                $message = "Gift Card purchased successfully! Share this code with your friend.";
                $message_type = 'success';
                $generated_code = $code; // To display the code
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = 'An error occurred. Please try again.';
                $message_type = 'error';
            }
        } else {
            $message = 'Insufficient wallet balance.';
            $message_type = 'error';
        }
    } else {
        $message = 'Please enter a valid amount.';
        $message_type = 'error';
    }
}
?>

<div class="py-6 max-w-lg mx-auto">
    <a href="wallet.php" class="text-purple-400 hover:underline mb-4 inline-block">&larr; Back to Wallet</a>
    <h1 class="text-3xl font-bold mb-2">Buy a Gift Card</h1>
    <p class="text-gray-400 mb-6">Purchase a gift card using your wallet balance and share the code with a friend!</p>

    <?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <?php if ($generated_code): ?>
    <div class="bg-gray-900/50 p-4 rounded-lg text-center border-2 border-dashed border-purple-500">
        <p class="text-gray-300">Your Gift Code is:</p>
        <p class="font-bold text-2xl text-yellow-300 tracking-widest my-2"><?= htmlspecialchars($generated_code) ?></p>
        <p class="text-xs text-gray-500">Share this code safely. It can only be used once.</p>
    </div>
    <a href="buy_gift_card.php" class="block text-center mt-4 text-purple-400 hover:underline">Buy another card</a>
    <?php else: ?>
    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
        <form method="POST">
            <input type="hidden" name="buy_card">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Enter Amount (₹)</label>
                    <input type="number" name="amount" step="10" class="bg-gray-700 w-full p-3 rounded-lg" placeholder="e.g., 100" required>
                </div>
                <div class="text-xs text-gray-400">
                    Amount will be deducted from your wallet balance of <span class="font-bold text-white">₹<?= number_format($wallet_balance, 2) ?></span>
                </div>
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg">Buy Now</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include 'common/bottom.php'; ?>