<?php
include 'common/header.php';
$message = '';
$message_type = '';

// ## LOGIC 1: UPDATE PAYMENT DETAILS (UPI & QR) ##
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_details'])) {
    $upi_id = trim($_POST['upi_id']);
    
    $current_details = mysqli_fetch_assoc(mysqli_query($conn, "SELECT qr_code_url FROM payment_details WHERE id = 1"));
    $qr_code_path_to_save = $current_details['qr_code_url'];

    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == 0) {
        $target_dir = "../uploads/";
        $new_filename = "qr_" . time() . "_" . basename($_FILES["qr_code"]["name"]);
        $target_file = $target_dir . $new_filename;
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

        if(in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) {
            if (move_uploaded_file($_FILES["qr_code"]["tmp_name"], $target_file)) {
                $qr_code_path_to_save = "uploads/" . $new_filename;
            } else { $message = 'Error: QR code upload nahi ho paya.'; $message_type = 'error'; }
        } else { $message = 'Error: Sirf JPG, JPEG, PNG & GIF files hi allowed hain.'; $message_type = 'error'; }
    }
    
    if (empty($message)) {
        $stmt = mysqli_prepare($conn, "UPDATE payment_details SET upi_id = ?, qr_code_url = ? WHERE id = 1");
        mysqli_stmt_bind_param($stmt, "ss", $upi_id, $qr_code_path_to_save);
        if (mysqli_stmt_execute($stmt)) { $message = 'Payment details aab update ho gayi hain!'; $message_type = 'success';
        } else { $message = 'Error: Details update nahi ho payi.'; $message_type = 'error'; }
    }
}

// ## LOGIC 2: APPROVE/REJECT DEPOSIT REQUESTS ##
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = $_GET['id'];
    $action = $_GET['action'];
    $stmt_req = mysqli_prepare($conn, "SELECT user_id, amount, status FROM deposit_requests WHERE id = ?");
    mysqli_stmt_bind_param($stmt_req, "i", $request_id);
    mysqli_stmt_execute($stmt_req);
    $request = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_req));
    if ($request && $request['status'] == 'pending') {
        if ($action == 'approve') {
            mysqli_begin_transaction($conn);
            try {
                mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + {$request['amount']} WHERE id = {$request['user_id']}");
                mysqli_query($conn, "UPDATE deposit_requests SET status = 'approved' WHERE id = {$request_id}");
                mysqli_commit($conn);
                $message = 'Deposit approved!'; $message_type = 'success';
            } catch (Exception $e) { mysqli_rollback($conn); $message = 'Error during approval.'; $message_type = 'error'; }
        } elseif ($action == 'reject') {
            mysqli_query($conn, "UPDATE deposit_requests SET status = 'rejected' WHERE id = {$request_id}");
            $message = 'Deposit rejected.'; $message_type = 'success';
        }
    }
}

// Fetch data for the page
$details = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payment_details WHERE id = 1"));
$pending_requests = mysqli_query($conn, "SELECT dr.*, u.username FROM deposit_requests dr JOIN users u ON dr.user_id = u.id WHERE dr.status = 'pending' ORDER BY dr.created_at ASC");
$processed_requests = mysqli_query($conn, "SELECT dr.*, u.username FROM deposit_requests dr JOIN users u ON dr.user_id = u.id WHERE dr.status != 'pending' ORDER BY dr.created_at DESC LIMIT 20");
?>

<h1 class="text-3xl font-bold text-white mb-6 flex items-center gap-3"><i class="ph-fill ph-bank text-purple-400"></i>Deposits & Payment Settings</h1>

<?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 bg-gray-800 p-6 rounded-xl border border-gray-700 h-fit">
        <h2 class="text-xl font-semibold mb-4">Update Payment Details</h2>
        <form method="POST" enctype="multipart/form-data" action="payments.php">
            <input type="hidden" name="update_details">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm mb-1 font-medium text-gray-300">New UPI ID</label>
                    <input type="text" name="upi_id" class="bg-gray-700 w-full p-2.5 rounded-lg border border-gray-600 focus:ring-2 focus:ring-purple-500 transition" value="<?= htmlspecialchars($details['upi_id']) ?>">
                </div>
                <div>
                    <label class="block text-sm mb-1 font-medium text-gray-300">Upload New QR Code (Optional)</label>
                    <input type="file" name="qr_code" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-700 cursor-pointer">
                    <p class="text-xs text-gray-500 mt-1">Agar aap QR code nahi badalna chahte toh isse khaali chhod dein.</p>
                </div>
                <div class="mt-4">
                    <label class="block text-xs mb-1 font-medium text-gray-400">Current QR Code:</label>
                    <img src="../<?= htmlspecialchars($details['qr_code_url']) ?>?t=<?= time() ?>" class="rounded-lg border border-gray-600 w-32 h-32 object-cover">
                </div>
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 rounded-lg transition-transform hover:scale-105">Save Settings</button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-gray-800 p-6 rounded-xl border border-gray-700">
        <div class="mb-4 border-b border-gray-700">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                <li class="mr-2"><button onclick="showTab('pending')" class="tab-btn inline-block p-4 border-b-2 rounded-t-lg" id="pending-tab">Pending</button></li>
                <li><button onclick="showTab('history')" class="tab-btn inline-block p-4 border-b-2 rounded-t-lg" id="history-tab">History</button></li>
            </ul>
        </div>
        <div id="pending-content" class="tab-content">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-gray-700/50"><tr><th class="px-4 py-2">User</th><th class="px-4 py-2">Amount</th><th class="px-4 py-2">ID</th><th class="px-4 py-2 text-center">Actions</th></tr></thead>
                    <tbody>
                        <?php if(mysqli_num_rows($pending_requests) > 0): while($req = mysqli_fetch_assoc($pending_requests)): ?>
                        <tr class="border-b border-gray-700">
                            <td class="px-4 py-3"><?= htmlspecialchars($req['username']) ?></td>
                            <td class="px-4 py-3 font-bold text-green-400">₹<?= htmlspecialchars($req['amount']) ?></td>
                            <td class="px-4 py-3 font-mono"><?= htmlspecialchars($req['transaction_id']) ?></td>
                            <td class="px-4 py-3 flex gap-3 justify-center"><a href="payments.php?action=approve&id=<?= $req['id'] ?>" class="font-medium text-green-400">Approve</a><a href="payments.php?action=reject&id=<?= $req['id'] ?>" class="font-medium text-red-400">Reject</a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="text-center py-8 text-gray-400">No pending deposit requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="history-content" class="tab-content hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-gray-700/50"><tr><th class="px-4 py-2">User</th><th class="px-4 py-2">Amount</th><th class="px-4 py-2">Status</th></tr></thead>
                    <tbody>
                        <?php if(mysqli_num_rows($processed_requests) > 0): while($req = mysqli_fetch_assoc($processed_requests)): ?>
                        <tr class="border-b border-gray-700">
                            <td class="px-4 py-3"><?= htmlspecialchars($req['username']) ?></td>
                            <td class="px-4 py-3 font-bold">₹<?= htmlspecialchars($req['amount']) ?></td>
                            <td class="px-4 py-3"><?php $status_color = $req['status'] == 'approved' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'; ?><span class="px-2 py-1 font-semibold text-xs rounded-full <?= $status_color ?>"><?= ucfirst($req['status']) ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" class="text-center py-8 text-gray-400">No history found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
    showTab('pending'); // Show pending by default
</script>

<?php include 'common/bottom.php'; ?>