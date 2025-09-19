<?php
include 'common/header.php';
$message = '';
$message_type = '';

// Add/Edit Promo Code Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_promo'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount = (int)$_POST['discount_percentage'];
    $max_uses = (int)$_POST['max_uses'];

    if(!empty($code) && $discount > 0 && $max_uses > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO promo_codes (code, discount_percentage, max_uses) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sii", $code, $discount, $max_uses);
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Promo code created successfully!'; $message_type = 'success';
        } else {
            $message = 'Error: This code might already exist.'; $message_type = 'error';
        }
    } else {
        $message = 'Please fill all fields correctly.'; $message_type = 'error';
    }
}

// Toggle Active Status Logic
if (isset($_GET['toggle_id'])) {
    $id = $_GET['toggle_id'];
    mysqli_query($conn, "UPDATE promo_codes SET is_active = !is_active WHERE id = $id");
    header('Location: promos.php'); // Redirect to avoid re-toggling on refresh
    exit();
}


$promos = mysqli_query($conn, "SELECT * FROM promo_codes ORDER BY created_at DESC");
?>

<h1 class="text-3xl font-bold text-white mb-6 flex items-center gap-3"><i class="ph-fill ph-ticket text-purple-400"></i>Promo Code Management</h1>

<?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 bg-gray-800 p-6 rounded-xl border border-gray-700">
        <h2 class="text-xl font-semibold mb-4">Create New Code</h2>
        <form method="POST">
            <input type="hidden" name="add_promo">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm mb-1">Promo Code</label>
                    <input type="text" name="code" class="bg-gray-700 w-full p-2.5 rounded-lg uppercase" placeholder="e.g., NEWYEAR50" required>
                </div>
                <div>
                    <label class="block text-sm mb-1">Discount (%)</label>
                    <input type="number" name="discount_percentage" class="bg-gray-700 w-full p-2.5 rounded-lg" placeholder="e.g., 20" required>
                </div>
                <div>
                    <label class="block text-sm mb-1">Max Uses</label>
                    <input type="number" name="max_uses" class="bg-gray-700 w-full p-2.5 rounded-lg" placeholder="e.g., 100" required>
                </div>
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 rounded-lg">Create Code</button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-gray-800 p-6 rounded-xl border border-gray-700">
        <h2 class="text-xl font-semibold mb-4">Existing Codes</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-700/50">
                    <tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Usage</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody>
                    <?php while($promo = mysqli_fetch_assoc($promos)): ?>
                    <tr class="border-b border-gray-700">
                        <td class="px-4 py-3 font-mono font-bold"><?= htmlspecialchars($promo['code']) ?></td>
                        <td class="px-4 py-3"><?= $promo['discount_percentage'] ?>%</td>
                        <td class="px-4 py-3"><?= $promo['uses'] ?> / <?= $promo['max_uses'] ?></td>
                        <td class="px-4 py-3">
                            <a href="?toggle_id=<?= $promo['id'] ?>" class="px-2 py-1 font-semibold text-xs rounded-full <?= $promo['is_active'] ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
                                <?= $promo['is_active'] ? 'Active' : 'Inactive' ?>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'common/bottom.php'; ?>