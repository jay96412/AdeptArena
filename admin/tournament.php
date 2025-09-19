<?php
include 'common/header.php';
$message = '';
$message_type = '';

// Create Tournament Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tournament'])) {
    $title = trim($_POST['title']);
    $game_id = $_POST['game_id'];
    $entry_fee = $_POST['entry_fee'];
    $prize_pool = $_POST['prize_pool'];
    $match_time = $_POST['match_time'];
    $commission = $_POST['commission_percentage'];

    if (empty($title) || empty($game_id) || empty($entry_fee) || empty($prize_pool) || empty($match_time)) {
        $message = "All fields are required.";
        $message_type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO tournaments (title, game_id, entry_fee, prize_pool, match_time, commission_percentage) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "siddsi", $title, $game_id, $entry_fee, $prize_pool, $match_time, $commission);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Tournament created successfully!";
            $message_type = 'success';
        } else {
            $message = "Failed to create tournament. Please try again.";
            $message_type = 'error';
        }
    }
}

// Delete Tournament Logic
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE FROM participants WHERE tournament_id = $delete_id");
        mysqli_query($conn, "DELETE FROM winners WHERE tournament_id = $delete_id");
        mysqli_query($conn, "DELETE FROM tournament_chat WHERE tournament_id = $delete_id");
        mysqli_query($conn, "DELETE FROM tournaments WHERE id = $delete_id");
        mysqli_commit($conn);
        $message = "Tournament and all related data deleted successfully.";
        $message_type = "success";
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $message = "Failed to delete tournament due to a database error.";
        $message_type = "error";
    }
}

// Fetch all tournaments for the list
$tournaments_result = mysqli_query($conn, "SELECT t.*, g.name as game_name FROM tournaments t LEFT JOIN games g ON t.game_id = g.id ORDER BY t.created_at DESC");
?>
<style>
    .game-selector input[type="radio"] { display: none; }
    .game-selector img {
        border: 3px solid transparent;
        border-radius: 0.5rem;
        transition: all 0.2s ease-in-out;
        cursor: pointer;
        opacity: 0.7;
    }
    .game-selector input[type="radio"]:hover + label img { opacity: 1; }
    .game-selector input[type="radio"]:checked + label img {
        border-color: #a855f7;
        transform: scale(1.05);
        opacity: 1;
        box-shadow: 0 0 15px rgba(168, 85, 247, 0.5);
    }
</style>

<h1 class="text-3xl font-bold text-white mb-6 flex items-center gap-3"><i class="ph-fill ph-trophy text-purple-400"></i>Tournaments</h1>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
    <div class="lg:col-span-2">
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700 shadow-lg">
            <h2 class="text-2xl font-semibold mb-6 flex items-center gap-3"><i class="ph-fill ph-plus-circle text-green-400"></i>Create New Tournament</h2>
            <form action="tournament.php" method="POST" class="space-y-6">
                <input type="hidden" name="create_tournament">
                
                <div>
                    <label class="block mb-3 text-sm font-medium text-gray-300">Select Game</label>
                    <div class="game-selector grid grid-cols-3 sm:grid-cols-4 gap-4">
                        <?php 
                            $games_result = mysqli_query($conn, "SELECT id, name, icon_url FROM games ORDER BY name ASC");
                            if(mysqli_num_rows($games_result) > 0):
                            while($game = mysqli_fetch_assoc($games_result)):
                        ?>
                        <div>
                            <input type="radio" name="game_id" value="<?= $game['id'] ?>" id="game_<?= $game['id'] ?>" required>
                            <label for="game_<?= $game['id'] ?>">
                                <img src="../<?= htmlspecialchars($game['icon_url']) ?>" alt="<?= htmlspecialchars($game['name']) ?>" class="w-full h-auto object-cover aspect-square" title="<?= htmlspecialchars($game['name']) ?>">
                                <p class="text-center text-xs mt-1 text-gray-400 truncate"><?= htmlspecialchars($game['name']) ?></p>
                            </label>
                        </div>
                        <?php endwhile; else: ?>
                        <p class="text-gray-400 col-span-full">No games found. Please <a href="games.php" class="text-purple-400 underline">add a game</a> first.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div><label class="block mb-2 text-sm">Tournament Title</label><input type="text" name="title" class="bg-gray-700 border border-gray-600 rounded-lg w-full p-3" placeholder="e.g., Weekend Bonanza" required></div>
                <div><label class="block mb-2 text-sm">Match Time</label><input type="datetime-local" name="match_time" class="bg-gray-700 border border-gray-600 rounded-lg w-full p-3" required></div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block mb-2 text-sm">Entry Fee (₹)</label><input type="number" name="entry_fee" step="0.01" class="bg-gray-700 border border-gray-600 rounded-lg w-full p-3" placeholder="e.g., 50" required></div>
                    <div><label class="block mb-2 text-sm">Prize Pool (₹)</label><input type="number" name="prize_pool" step="0.01" class="bg-gray-700 border border-gray-600 rounded-lg w-full p-3" placeholder="e.g., 1000" required></div>
                </div>

                <div><label class="block mb-2 text-sm">Commission (%)</label><input type="number" name="commission_percentage" value="20" class="bg-gray-700 border border-gray-600 rounded-lg w-full p-3" required></div>
                
                <div class="pt-2"><button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg text-base">Create Tournament</button></div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-3">
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700">
            <h2 class="text-2xl font-semibold mb-6 flex items-center gap-3"><i class="ph-fill ph-list-bullets text-blue-400"></i>All Tournaments</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-700/50"><tr><th class="px-4 py-3">Title</th><th class="px-4 py-3">Game</th><th class="px-4 py-3">Time</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
                    <tbody>
                        <?php if(mysqli_num_rows($tournaments_result) > 0): while ($row = mysqli_fetch_assoc($tournaments_result)): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700/40 transition-colors">
                            <td class="px-4 py-4 font-medium"><p class="font-semibold"><?= htmlspecialchars($row['title']) ?></p><p class="text-xs text-gray-400">Prize: ₹<?= number_format($row['prize_pool']) ?> | Fee: ₹<?= number_format($row['entry_fee']) ?></p></td>
                            <td class="px-4 py-4 text-gray-300"><?= htmlspecialchars($row['game_name']) ?></td>
                            <td class="px-4 py-4 text-gray-300"><?= date('d M, h:i A', strtotime($row['match_time'])) ?></td>
                            <td class="px-4 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-500/20 text-blue-300"><?= ucfirst($row['status']) ?></span></td>
                            <td class="px-4 py-4 flex gap-3"><a href="manage_tournament.php?id=<?= $row['id'] ?>" class="font-medium text-blue-400 hover:underline">Manage</a><a href="tournament.php?delete_id=<?= $row['id'] ?>" class="font-medium text-red-400 hover:underline" onclick="return confirm('Are you sure? This will delete all related data.')">Delete</a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-8 text-gray-400">No tournaments created yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'common/bottom.php'; ?>