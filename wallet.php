<?php
include 'common/header.php';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Redeem Gift Card Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_code'])) {
    $code = trim($_POST['code']);
    
    mysqli_begin_transaction($conn);
    try {
        $stmt_find = mysqli_prepare($conn, "SELECT id, amount, status FROM gift_cards WHERE code = ? AND status = 'available' FOR UPDATE");
        mysqli_stmt_bind_param($stmt_find, "s", $code);
        mysqli_stmt_execute($stmt_find);
        $result = mysqli_stmt_get_result($stmt_find);

        if ($card = mysqli_fetch_assoc($result)) {
            $card_id = $card['id'];
            $card_amount = $card['amount'];

            mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $card_amount WHERE id = $user_id");
            $redeemed_at = date("Y-m-d H:i:s");
            mysqli_query($conn, "UPDATE gift_cards SET status = 'redeemed', redeemed_by_user_id = $user_id, redeemed_at = '$redeemed_at' WHERE id = $card_id");
            
            $desc = "Redeemed Gift Card " . htmlspecialchars($code);
            mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($user_id, $card_amount, 'credit', '$desc')");
            
            mysqli_commit($conn);
            $message = "₹" . number_format($card_amount) . " added to your wallet successfully!";
            $message_type = 'success';
            $wallet_balance += $card_amount;
        } else {
            throw new Exception("This gift code is invalid or has already been used.");
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch transactions and commission history
$trans_result = mysqli_query($conn, "SELECT * FROM transactions WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 20");
$comm_result = mysqli_query($conn, "SELECT ce.*, u.username as referred_username, t.title as tournament_title FROM commission_earnings ce JOIN users u ON ce.referred_user_id = u.id JOIN tournaments t ON ce.tournament_id = t.id WHERE ce.referrer_id = $user_id ORDER BY ce.created_at DESC LIMIT 20");
?>

<div class="py-6">
    <h1 class="text-3xl font-bold mb-4">My Wallet</h1>

    <div class="bg-gradient-to-br from-purple-600 to-blue-600 p-8 rounded-xl shadow-lg mb-6 text-center">
        <p class="text-sm text-purple-200 uppercase tracking-wider">Current Balance</p>
        <p class="text-5xl font-bold mt-2">₹<?= number_format($wallet_balance, 2) ?></p>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
            <h2 class="text-lg font-semibold mb-3">Redeem a Gift Card</h2>
            <form method="POST"><input type="hidden" name="redeem_code"><div class="flex gap-2"><input type="text" name="code" class="bg-gray-700 flex-1 p-2.5 rounded-lg text-sm uppercase tracking-widest" placeholder="ENTER CODE" required><button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-5 rounded-lg font-semibold transition-all duration-300">Redeem</button></div></form>
        </div>
        <a href="buy_gift_card.php" class="bg-yellow-500/20 p-6 rounded-xl border border-yellow-400/50 flex flex-col items-center justify-center text-center hover:bg-yellow-500/30 transition-all duration-300">
            <i class="ph-fill ph-gift text-4xl text-yellow-300 mb-2"></i>
            <p class="font-bold text-yellow-200">Buy a Gift Card</p><p class="text-xs text-yellow-400">Share a gift with your friends!</p>
        </a>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-8">
        <a href="add_money.php" class="bg-green-500/20 text-green-300 font-semibold py-3 rounded-lg flex items-center justify-center gap-2 hover:bg-green-500/30 transition-all duration-300"><i class="ph-fill ph-plus-circle text-lg"></i> <span>Add Money</span></a>
        <a href="withdraw.php" class="bg-blue-500/20 text-blue-300 font-semibold py-3 rounded-lg flex items-center justify-center gap-2 hover:bg-blue-500/30 transition-all duration-300"><i class="ph-fill ph-arrow-circle-up text-lg"></i> <span>Withdraw</span></a>
    </div>

    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
        <div class="mb-4 border-b border-gray-700"><ul class="flex -mb-px text-sm font-medium text-center"><li class="mr-2"><button class="tab-btn inline-block p-4 border-b-2 rounded-t-lg" id="transactions-tab">Wallet History</button></li><li><button class="tab-btn inline-block p-4 border-b-2 rounded-t-lg" id="commission-tab">Commission History</button></li></ul></div>
        
        <div id="transactions-content" class="tab-content space-y-3">
            <?php if (mysqli_num_rows($trans_result) > 0): while ($row = mysqli_fetch_assoc($trans_result)): ?>
            <div class="bg-gray-700/50 p-4 rounded-lg flex justify-between items-center hover:bg-gray-700 transition-colors">
                <div><p class="font-semibold capitalize text-white"><?= htmlspecialchars($row['description']) ?></p><p class="text-xs text-gray-400"><?= date('d M, Y h:i A', strtotime($row['created_at'])) ?></p></div>
                <div><?php if ($row['type'] == 'credit'): ?><p class="font-bold text-green-400">+ ₹<?= number_format($row['amount'], 2) ?></p><?php else: ?><p class="font-bold text-red-400">- ₹<?= number_format($row['amount'], 2) ?></p><?php endif; ?></div>
            </div>
            <?php endwhile; else: ?><p class="text-gray-400 text-center py-8">No transactions yet.</p><?php endif; ?>
        </div>

        <div id="commission-content" class="tab-content hidden space-y-3">
             <?php if (mysqli_num_rows($comm_result) > 0): while ($row = mysqli_fetch_assoc($comm_result)): ?>
                <div class="bg-gray-700/50 p-4 rounded-lg hover:bg-gray-700 transition-colors">
                    <div class="flex justify-between items-center">
                        <div><p class="font-semibold text-white">2% commission from <span class="text-purple-300"><?= htmlspecialchars($row['referred_username']) ?></span></p><p class="text-xs text-gray-400">Tournament: <?= htmlspecialchars($row['tournament_title']) ?></p></div>
                        <p class="font-bold text-green-400">+ ₹<?= number_format($row['commission_amount'], 2) ?></p>
                    </div>
                </div>
            <?php endwhile; else: ?><p class="text-gray-400 text-center py-8">No commission earnings yet.</p><?php endif; ?>
        </div>
    </div>
</div>

<script>
    const tabs = document.querySelectorAll('.tab-btn');
    const contents = document.querySelectorAll('.tab-content');
    function showTab(tabId) {
        tabs.forEach(tab => {
            const isActive = tab.id === tabId;
            tab.classList.toggle('border-purple-500', isActive); tab.classList.toggle('text-purple-500', isActive);
            tab.classList.toggle('border-transparent', !isActive); tab.classList.toggle('text-gray-400', !isActive);
        });
        contents.forEach(content => {
            content.classList.toggle('hidden', content.id !== tabId.replace('-tab', '-content'));
        });
    }
    tabs.forEach(tab => { tab.addEventListener('click', () => showTab(tab.id)); });
    showTab('transactions-tab');
</script>

<?php include 'common/bottom.php'; ?>