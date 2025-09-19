<?php
include 'common/header.php';
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    $ann_message = trim($_POST['message']);
    if (!empty($ann_message)) {
        // Deactivate all other announcements first
        mysqli_query($conn, "UPDATE announcements SET is_active = 0");
        // Insert the new one as active
        $stmt = mysqli_prepare($conn, "INSERT INTO announcements (message, is_active) VALUES (?, 1)");
        mysqli_stmt_bind_param($stmt, "s", $ann_message);
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Announcement posted successfully!'; $message_type = 'success';
        } else {
            $message = 'Error posting announcement.'; $message_type = 'error';
        }
    }
}

// Deactivate logic
if (isset($_GET['deactivate_id'])) {
    $id = $_GET['deactivate_id'];
    mysqli_query($conn, "UPDATE announcements SET is_active = 0 WHERE id = $id");
    header('Location: announcements.php');
    exit();
}

$announcements = mysqli_query($conn, "SELECT * FROM announcements ORDER BY created_at DESC");
?>

<h1 class="text-3xl font-bold text-white mb-6 flex items-center gap-3"><i class="ph-fill ph-megaphone text-purple-400"></i>Announcements</h1>

<?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 bg-gray-800 p-6 rounded-xl border border-gray-700">
        <h2 class="text-xl font-semibold mb-4">Post New Announcement</h2>
        <p class="text-sm text-gray-400 mb-4">Note: Posting a new announcement will automatically deactivate the old one.</p>
        <form method="POST">
            <input type="hidden" name="add_announcement">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm mb-1">Message</label>
                    <textarea name="message" rows="4" class="bg-gray-700 w-full p-2.5 rounded-lg" placeholder="Enter your message here..." required></textarea>
                </div>
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 rounded-lg">Post to Homepage</button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-gray-800 p-6 rounded-xl border border-gray-700">
        <h2 class="text-xl font-semibold mb-4">History</h2>
        <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
            <?php while($ann = mysqli_fetch_assoc($announcements)): ?>
            <div class="bg-gray-700/50 p-4 rounded-lg">
                <p class="text-gray-200"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>
                <div class="flex justify-between items-center mt-2 text-xs text-gray-400">
                    <span><?= date('d M, Y', strtotime($ann['created_at'])) ?></span>
                    <?php if($ann['is_active']): ?>
                        <a href="?deactivate_id=<?= $ann['id'] ?>" class="px-2 py-1 font-semibold rounded-full bg-green-500/20 text-green-300">Active (Click to Deactivate)</a>
                    <?php else: ?>
                        <span class="px-2 py-1 font-semibold rounded-full bg-gray-600 text-gray-300">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php include 'common/bottom.php'; ?>