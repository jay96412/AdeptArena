<?php
include 'common/header.php';
$message = '';
$message_type = '';

// Logic to create a new contest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_contest'])) {
    $title = trim($_POST['title']);
    $entry_fee = (float)$_POST['entry_fee'];
    $match_start_time = $_POST['match_start_time'];

    if (!empty($title) && $entry_fee >= 0 && !empty($match_start_time)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO contests (title, entry_fee, match_start_time, status) VALUES (?, ?, ?, 'upcoming')");
        mysqli_stmt_bind_param($stmt, "sds", $title, $entry_fee, $match_start_time);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Contest created successfully! Now add questions to it.";
            $message_type = 'success';
        } else {
            $message = "Error creating contest.";
            $message_type = 'error';
        }
    } else {
        $message = "Please fill all fields correctly.";
        $message_type = 'error';
    }
}

// Fetch ACTIVE (upcoming & live) contests
$active_contests_result = mysqli_query($conn, "SELECT * FROM contests WHERE status IN ('upcoming', 'live') ORDER BY created_at DESC");

// Fetch COMPLETED contests
$completed_contests_result = mysqli_query($conn, "SELECT * FROM contests WHERE status = 'completed' ORDER BY created_at DESC");
?>

<h1 class="text-3xl font-bold text-white mb-6 flex items-center gap-3"><i class="ph-fill ph-question text-purple-400"></i>Prediction Contests</h1>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700">
            <h2 class="text-2xl font-semibold mb-6">Create New Contest</h2>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="create_contest">
                <div>
                    <label class="block mb-2 text-sm">Contest Title</label>
                    <input type="text" name="title" class="bg-gray-700 w-full p-3 rounded-lg" placeholder="e.g., T20 World Cup Final" required>
                </div>
                <div>
                    <label class="block mb-2 text-sm">Entry Fee (₹)</label>
                    <input type="number" name="entry_fee" step="1" class="bg-gray-700 w-full p-3 rounded-lg" placeholder="e.g., 25" required>
                </div>
                <div>
                    <label class="block mb-2 text-sm">Match Start Time</label>
                    <input type="datetime-local" name="match_start_time" class="bg-gray-700 w-full p-3 rounded-lg" required>
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg text-base">Create Contest</button>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2 space-y-8">
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700">
            <h2 class="text-2xl font-semibold mb-6">Live & Upcoming Contests</h2>
            <div class="space-y-4">
                <?php if(mysqli_num_rows($active_contests_result) > 0): while($contest = mysqli_fetch_assoc($active_contests_result)): ?>
                <div class="bg-gray-700/50 p-4 rounded-lg flex justify-between items-center">
                    <div>
                        <p class="font-bold text-lg text-white"><?= htmlspecialchars($contest['title']) ?></p>
                        <p class="text-sm text-gray-400">Fee: ₹<?= number_format($contest['entry_fee']) ?> | Status: <span class="font-semibold <?= $contest['status'] == 'live' ? 'text-red-400' : 'text-blue-400' ?>"><?= ucfirst($contest['status']) ?></span></p>
                    </div>
                    <a href="manage_questions.php?contest_id=<?= $contest['id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg">
                        Manage
                    </a>
                </div>
                <?php endwhile; else: ?>
                <p class="text-gray-400">No active contests found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700">
            <h2 class="text-2xl font-semibold mb-6">Completed Contests (History)</h2>
            <div class="space-y-4 max-h-96 overflow-y-auto">
                <?php if(mysqli_num_rows($completed_contests_result) > 0): while($contest = mysqli_fetch_assoc($completed_contests_result)): ?>
                <div class="bg-gray-700/50 p-4 rounded-lg flex justify-between items-center opacity-70">
                    <div>
                        <p class="font-bold text-lg text-gray-300"><?= htmlspecialchars($contest['title']) ?></p>
                        <p class="text-sm text-gray-500">Fee: ₹<?= number_format($contest['entry_fee']) ?> | Completed</p>
                    </div>
                    <a href="manage_questions.php?contest_id=<?= $contest['id'] ?>" class="bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg">
                        View Results
                    </a>
                </div>
                <?php endwhile; else: ?>
                <p class="text-gray-400">No completed contests found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'common/bottom.php'; ?>