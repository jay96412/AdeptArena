<?php
include 'common/header.php';
$message = '';
$message_type = '';

// Block/Unblock Logic
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $action = $_GET['action'];
    $new_status = ($action == 'block') ? 'blocked' : 'active';
    
    if ($user_id != 1) { 
        $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_status, $user_id);
        if(mysqli_stmt_execute($stmt)){
            $message = "User status updated!";
            $message_type = 'success';
        }
    } else {
        $message = "Cannot change status of this primary user.";
        $message_type = 'error';
    }
}

// Manual Wallet Adjustment Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_wallet'])) {
    $adj_user_id = $_POST['user_id'];
    $adj_amount = (float)$_POST['amount'];
    $adj_type = $_POST['type']; // 'credit' or 'debit'
    $adj_reason = trim($_POST['reason']);

    if ($adj_amount > 0 && !empty($adj_reason)) {
        mysqli_begin_transaction($conn);
        try {
            if ($adj_type == 'credit') {
                mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $adj_amount WHERE id = $adj_user_id");
            } else {
                $user_bal_q = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $adj_user_id");
                $user_bal = mysqli_fetch_assoc($user_bal_q)['wallet_balance'];
                if ($user_bal < $adj_amount) {
                    throw new Exception("User does not have enough balance to debit.");
                }
                mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $adj_amount WHERE id = $adj_user_id");
            }

            $stmt_trans = mysqli_prepare($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_trans, "idss", $adj_user_id, $adj_amount, $adj_type, $adj_reason);
            mysqli_stmt_execute($stmt_trans);
            
            mysqli_commit($conn);
            $message = "Wallet adjusted successfully!"; $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error: " . $e->getMessage(); $message_type = 'error';
        }
    } else {
        $message = "Please provide a valid amount and reason."; $message_type = 'error';
    }
}

// Fetch all users
$users_result = mysqli_query($conn, "SELECT id, username, email, wallet_balance, status, avatar, created_at FROM users ORDER BY created_at DESC");
?>

<h1 class="text-3xl font-bold text-white mb-6 flex items-center gap-3"><i class="ph-fill ph-users text-purple-400"></i>User Management</h1>

<?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>


<div class="bg-gray-800 p-6 rounded-xl border border-gray-700 overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="text-xs uppercase bg-gray-700/50">
            <tr>
                <th class="px-4 py-3">Username</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Wallet Balance</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($users_result)): ?>
            <tr class="border-b border-gray-700 hover:bg-gray-700/40">
                <td class="px-4 py-3 font-medium flex items-center gap-3">
                    <img src="../uploads/avatars/<?= htmlspecialchars($row['avatar'] ?? 'default.png') ?>" class="w-8 h-8 rounded-full object-cover">
                    <?= htmlspecialchars($row['username']) ?>
                </td>
                <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($row['email']) ?></td>
                <td class="px-4 py-3 font-semibold text-green-400">₹<?= number_format($row['wallet_balance'], 2) ?></td>
                <td class="px-4 py-3">
                    <?php if($row['status'] == 'active'): ?>
                        <span class="px-2 py-1 font-semibold text-xs rounded-full bg-green-500/20 text-green-300">Active</span>
                    <?php else: ?>
                        <span class="px-2 py-1 font-semibold text-xs rounded-full bg-red-500/20 text-red-300">Blocked</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <?php if($row['status'] == 'active'): ?>
                        <a href="user.php?action=block&user_id=<?= $row['id'] ?>" class="font-medium text-red-400 hover:underline" onclick="return confirm('Are you sure you want to block this user?')">Block</a>
                    <?php else: ?>
                        <a href="user.php?action=unblock&user_id=<?= $row['id'] ?>" class="font-medium text-green-400 hover:underline" onclick="return confirm('Are you sure you want to unblock this user?')">Unblock</a>
                    <?php endif; ?>
                    
                    <button onclick="openModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['username'])) ?>')" class="font-medium text-blue-400 hover:underline ml-3">Adjust Wallet</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div id="walletModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center hidden z-50">
    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Adjust Wallet for <span id="modalUsername" class="text-purple-400"></span></h2>
        <form method="POST" action="user.php">
            <input type="hidden" name="adjust_wallet">
            <input type="hidden" name="user_id" id="modalUserId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" class="bg-gray-700 w-full p-2.5 rounded-lg border border-gray-600" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Type</label>
                    <select name="type" class="bg-gray-700 w-full p-2.5 rounded-lg border border-gray-600">
                        <option value="credit">Credit (Add Money)</option>
                        <option value="debit">Debit (Remove Money)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Reason</label>
                    <textarea name="reason" rows="2" class="bg-gray-700 w-full p-2.5 rounded-lg border border-gray-600" placeholder="e.g., Refund for Match #123" required></textarea>
                </div>
                <div class="flex gap-4 pt-2">
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg">Confirm Adjustment</button>
                    <button type="button" onclick="closeModal()" class="w-full bg-gray-600 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-lg">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('walletModal');
    function openModal(userId, username) {
        document.getElementById('modalUserId').value = userId;
        document.getElementById('modalUsername').innerText = username;
        modal.classList.remove('hidden');
    }
    function closeModal() {
        modal.classList.add('hidden');
    }
</script>

<?php include 'common/bottom.php'; ?>