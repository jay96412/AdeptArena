<?php
include 'common/header.php';
$message = '';
$message_type = '';

// Logic for processing requests remains the same
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = $_GET['id'];
    $action = $_GET['action'];
    $stmt_req = mysqli_prepare($conn, "SELECT user_id, amount, status FROM withdrawal_requests WHERE id = ?");
    mysqli_stmt_bind_param($stmt_req, "i", $request_id);
    mysqli_stmt_execute($stmt_req);
    $request = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_req));

    if ($request && $request['status'] == 'pending') {
        $new_status = ($action == 'complete') ? 'completed' : 'rejected';
        if ($new_status == 'rejected') {
            mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + {$request['amount']} WHERE id = {$request['user_id']}");
        }
        $stmt_status = mysqli_prepare($conn, "UPDATE withdrawal_requests SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_status, "si", $new_status, $request_id);
        if(mysqli_stmt_execute($stmt_status)){
            $message = "Request marked as " . ucfirst($new_status); $message_type = 'success';
        }
    }
}


// Fetch PENDING Requests
$pending_withdrawals = mysqli_query($conn, "SELECT wr.*, u.username FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id WHERE wr.status = 'pending' ORDER BY wr.created_at ASC");
// Fetch PROCESSED Requests
$processed_withdrawals = mysqli_query($conn, "SELECT wr.*, u.username FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id WHERE wr.status != 'pending' ORDER BY wr.created_at DESC LIMIT 20");
?>

<h1 class="text-3xl font-bold text-white mb-6 flex items-center gap-3"><i class="ph-fill ph-arrow-fat-lines-up text-purple-400"></i>Withdrawal Requests</h1>

<?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
    <div class="mb-4 border-b border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
            <li class="mr-2"><button onclick="showTab('pending')" class="tab-btn inline-block p-4 border-b-2 rounded-t-lg" id="pending-tab">Pending</button></li>
            <li><button onclick="showTab('history')" class="tab-btn inline-block p-4 border-b-2 rounded-t-lg" id="history-tab">History</button></li>
        </ul>
    </div>
    <div id="pending-content" class="tab-content">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase bg-gray-700/50"><tr><th class="px-4 py-2">User</th><th class="px-4 py-2">Amount</th><th class="px-4 py-2">UPI ID</th><th class="px-4 py-2 text-center">Actions</th></tr></thead>
                <tbody>
                    <?php if(mysqli_num_rows($pending_withdrawals) > 0): while($req = mysqli_fetch_assoc($pending_withdrawals)): ?>
                    <tr class="border-b border-gray-700">
                        <td class="px-4 py-3"><?= htmlspecialchars($req['username']) ?></td>
                        <td class="px-4 py-3 font-bold text-red-400">₹<?= htmlspecialchars($req['amount']) ?></td>
                        <td class="px-4 py-3 font-mono"><?= htmlspecialchars($req['upi_id']) ?></td>
                        <td class="px-4 py-3 flex gap-3 justify-center"><a href="withdrawals.php?action=complete&id=<?= $req['id'] ?>" class="font-medium text-green-400">Complete</a><a href="withdrawals.php?action=reject&id=<?= $req['id'] ?>" class="font-medium text-red-400">Reject</a></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" class="text-center py-8 text-gray-400">No pending withdrawal requests.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div id="history-content" class="tab-content hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                 <thead class="text-xs uppercase bg-gray-700/50"><tr><th class="px-4 py-2">User</th><th class="px-4 py-2">Amount</th><th class="px-4 py-2">UPI ID</th><th class="px-4 py-2">Status</th></tr></thead>
                 <tbody>
                    <?php if(mysqli_num_rows($processed_withdrawals) > 0): while($req = mysqli_fetch_assoc($processed_withdrawals)): ?>
                    <tr class="border-b border-gray-700">
                        <td class="px-4 py-3"><?= htmlspecialchars($req['username']) ?></td>
                        <td class="px-4 py-3 font-bold">₹<?= htmlspecialchars($req['amount']) ?></td>
                        <td class="px-4 py-3 font-mono"><?= htmlspecialchars($req['upi_id']) ?></td>
                        <td class="px-4 py-3"><?php $status_color = $req['status'] == 'completed' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'; ?><span class="px-2 py-1 font-semibold text-xs rounded-full <?= $status_color ?>"><?= ucfirst($req['status']) ?></span></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" class="text-center py-8 text-gray-400">No history found.</td></tr>
                    <?php endif; ?>
                 </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const tabs = document.querySelectorAll('.tab-btn');
    const contents = document.querySelectorAll('.tab-content');
    function showTab(tabName) {
        tabs.forEach(tab => {
            const isActive = tab.id === tabName + '-tab';
            tab.classList.toggle('border-purple-500', isActive);
            tab.classList.toggle('text-purple-500', isActive);
            tab.classList.toggle('border-transparent', !isActive);
        });
        contents.forEach(content => {
            content.classList.toggle('hidden', content.id !== tabName + '-content');
        });
    }
    showTab('pending');
</script>

<?php include 'common/bottom.php'; ?>