<?php
include 'common/header.php';
$message = '';
$message_type = '';

// Handle Deposit Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit'])) {
    $amount = $_POST['amount'];
    $transaction_id = trim($_POST['transaction_id']);
    
    if (!empty($amount) && !empty($transaction_id) && is_numeric($amount) && $amount > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO deposit_requests (user_id, amount, transaction_id, status) VALUES (?, ?, ?, 'pending')");
        mysqli_stmt_bind_param($stmt, "ids", $_SESSION['user_id'], $amount, $transaction_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Your request has been submitted! Please wait for approval.";
            $message_type = 'success';
        } else {
            $message = 'Something went wrong. Please try again.';
            $message_type = 'error';
        }
    } else {
        $message = 'Please enter a valid amount and transaction ID.';
        $message_type = 'error';
    }
}

$details = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payment_details WHERE id = 1"));
?>

<div class="py-6 max-w-lg mx-auto">
    <a href="wallet.php" class="text-purple-400 hover:underline mb-4 inline-block">&larr; Back to Wallet</a>
    <h1 class="text-3xl font-bold mb-4">Add Money</h1>

    <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 mb-6">
        <h2 class="text-xl font-semibold mb-4 text-center">Step 1: Make Payment</h2>
        <p class="text-center text-gray-400 mb-4">Scan the QR code or use the UPI ID to pay.</p>
        <div class="flex justify-center mb-4">
             
            <img src="<?= htmlspecialchars($details['qr_code_url']) ?>" alt="QR Code" class="w-48 h-48 rounded-lg border-2 border-gray-600">
        </div>
        <div class="text-center bg-gray-900/50 p-3 rounded-lg">
            <p class="text-gray-300">UPI ID:</p>
            <div class="flex items-center justify-center gap-2 mt-1">
                <span id="upiId" class="font-bold text-lg text-purple-300"><?= htmlspecialchars($details['upi_id']) ?></span>
                <button onclick="copyUpi()" class="bg-gray-700 hover:bg-gray-600 text-white px-2 py-1 rounded-md text-xs">Copy</button>
            </div>
        </div>
    </div>
    
    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
        <h2 class="text-xl font-semibold mb-4 text-center">Step 2: Submit Details</h2>
        <form method="POST">
            <input type="hidden" name="submit_deposit">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Amount Sent (₹)</label>
                    <input type="number" name="amount" step="0.01" class="bg-gray-700 w-full p-3 rounded-lg" placeholder="e.g., 500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Transaction ID (Ref ID)</label>
                    <input type="text" name="transaction_id" class="bg-gray-700 w-full p-3 rounded-lg" placeholder="e.g., 235489651245" required>
                </div>
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyUpi() {
    const upiId = document.getElementById('upiId').innerText;
    navigator.clipboard.writeText(upiId).then(() => {
        alert('UPI ID Copied!');
    }, () => {
        alert('Failed to copy.');
    });
}
</script>

<?php include 'common/bottom.php'; ?>