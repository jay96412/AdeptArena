<?php
include 'common/header.php';
$message = '';
$message_type = '';

// ## LOGIC 1: CREATE NEW OPINION EVENT ##
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title = trim($_POST['title']);
    $options = array_filter($_POST['options']); // Remove empty options

    if (!empty($title) && count($options) >= 2) {
        $options_json = json_encode($options);
        $stmt = mysqli_prepare($conn, "INSERT INTO opinion_events (title, options) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $title, $options_json);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Opinion event created successfully!";
            $message_type = 'success';
        } else {
            $message = "Error creating event.";
            $message_type = 'error';
        }
    } else {
        $message = "Please provide a title and at least two options.";
        $message_type = 'error';
    }
}

// ## LOGIC 2: DECLARE WINNER & DISTRIBUTE PAYOUTS ##
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['declare_winner'])) {
    $event_id = (int)$_POST['event_id'];
    $correct_option_index = (int)$_POST['correct_option'];

    mysqli_begin_transaction($conn);
    try {
        // Mark the event as completed
        $stmt_event = mysqli_prepare($conn, "UPDATE opinion_events SET status = 'completed', correct_option = ? WHERE id = ? AND status = 'open'");
        mysqli_stmt_bind_param($stmt_event, "ii", $correct_option_index, $event_id);
        mysqli_stmt_execute($stmt_event);

        // Fetch all bets for this event
        $bets_q = mysqli_query($conn, "SELECT id, user_id, bet_amount, odds_at_bet_time, option_index FROM opinion_bets WHERE event_id = $event_id AND status = 'pending'");
        
        while ($bet = mysqli_fetch_assoc($bets_q)) {
            $bet_id = $bet['id'];
            $user_id = $bet['user_id'];
            
            if ($bet['option_index'] == $correct_option_index) {
                // This user won
                $payout = $bet['bet_amount'] * $bet['odds_at_bet_time'];
                mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $payout WHERE id = $user_id");
                mysqli_query($conn, "UPDATE opinion_bets SET status = 'won' WHERE id = $bet_id");
                $desc = "Won opinion event #" . $event_id;
                mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($user_id, $payout, 'credit', '$desc')");
            } else {
                // This user lost
                mysqli_query($conn, "UPDATE opinion_bets SET status = 'lost' WHERE id = $bet_id");
            }
        }
        
        mysqli_commit($conn);
        $message = "Winner declared and payouts processed successfully!";
        $message_type = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "An error occurred: " . $e->getMessage();
        $message_type = 'error';
    }
}


// Fetch all events to display
$events = mysqli_query($conn, "SELECT * FROM opinion_events ORDER BY created_at DESC");
?>

<h1 class="text-3xl font-bold text-white mb-6 flex items-center gap-3"><i class="ph-fill ph-chats-circle text-purple-400"></i>Opinion Events</h1>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700">
            <h2 class="text-2xl font-semibold mb-6">Create New Event</h2>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="create_event">
                <div>
                    <label class="block mb-2 text-sm">Event Title / Question</label>
                    <input type="text" name="title" class="bg-gray-700 w-full p-3 rounded-lg" placeholder="e.g., Who will win the match?" required>
                </div>
                <div id="options-container">
                    <label class="block mb-2 text-sm">Options</label>
                    <input type="text" name="options[]" class="bg-gray-700 w-full p-3 rounded-lg mb-2" placeholder="Option 1" required>
                    <input type="text" name="options[]" class="bg-gray-700 w-full p-3 rounded-lg mb-2" placeholder="Option 2" required>
                </div>
                <button type="button" id="add-option-btn" class="text-sm text-purple-400 hover:underline">+ Add Another Option</button>
                <div class="pt-2"><button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg">Create Event</button></div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700">
            <h2 class="text-2xl font-semibold mb-6">Manage Events</h2>
            <div class="space-y-6">
                <?php while($event = mysqli_fetch_assoc($events)): 
                    $options = json_decode($event['options']);
                ?>
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <p class="font-bold text-lg"><?= htmlspecialchars($event['title']) ?></p>
                    <div class="text-sm text-gray-400 my-2">
                        Options: <?= implode(', ', array_map('htmlspecialchars', $options)) ?>
                    </div>
                    <?php if($event['status'] == 'open'): ?>
                        <form method="POST">
                            <input type="hidden" name="declare_winner">
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                            <div class="flex gap-2 items-center mt-3">
                                <select name="correct_option" class="bg-gray-800 flex-1 p-2.5 rounded-lg text-sm" required>
                                    <option value="">-- Declare Winning Option --</option>
                                    <?php foreach($options as $index => $option): ?>
                                    <option value="<?= $index ?>"><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg">Declare</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-sm font-semibold mt-3 p-2 rounded-md bg-green-500/20 text-green-300">
                            Result Declared: <span class="font-bold"><?= htmlspecialchars($options[$event['correct_option']]) ?></span>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('add-option-btn').addEventListener('click', function() {
    const container = document.getElementById('options-container');
    const optionCount = container.querySelectorAll('input').length;
    const newOption = document.createElement('input');
    newOption.type = 'text';
    newOption.name = 'options[]';
    newOption.className = 'bg-gray-700 w-full p-3 rounded-lg mb-2';
    newOption.placeholder = 'Option ' + (optionCount + 1);
    container.appendChild(newOption);
});
</script>

<?php include 'common/bottom.php'; ?>