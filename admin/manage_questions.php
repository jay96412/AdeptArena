<?php
include 'common/header.php';
$contest_id = (int)($_GET['contest_id'] ?? 0);
$message = '';
$message_type = '';

if ($contest_id === 0) {
    header('Location: contests.php');
    exit();
}

// Fetch contest details initially
$contest = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM contests WHERE id = $contest_id"));

// Logic to add a new question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    if ($contest['status'] === 'upcoming') {
        $question_text = trim($_POST['question_text']);
        $options = array_filter($_POST['options']);
        if (!empty($question_text) && count($options) >= 2) {
            $options_json = json_encode(array_values($options));
            $stmt = mysqli_prepare($conn, "INSERT INTO contest_questions (contest_id, question_text, options) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iss", $contest_id, $question_text, $options_json);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Question added successfully!"; $message_type = 'success';
            } else { $message = "Error adding question."; $message_type = 'error'; }
        } else { $message = "Please provide question text and at least two options."; $message_type = 'error'; }
    } else { $message = "Cannot add questions to a live or completed contest."; $message_type = 'error'; }
}

// Logic to save correct answers and set status to 'live'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_answers'])) {
    $correct_answers = $_POST['answers'];
    $all_questions_answered_query = mysqli_query($conn, "SELECT id FROM contest_questions WHERE contest_id = $contest_id");
    $all_answers_marked = (count($correct_answers) == mysqli_num_rows($all_questions_answered_query));

    foreach ($correct_answers as $question_id => $correct_option_index) {
        if ($correct_option_index === '') { $all_answers_marked = false; break; }
        $q_id = (int)$question_id;
        $c_opt_idx = (int)$correct_option_index;
        mysqli_query($conn, "UPDATE contest_questions SET correct_option_index = $c_opt_idx WHERE id = $q_id");
    }
    
    if ($all_answers_marked) {
        mysqli_query($conn, "UPDATE contests SET status = 'live' WHERE id = $contest_id");
        $message = "All correct answers have been saved. The contest is now ready for payout processing."; $message_type = 'success';
    } else { $message = "Please mark all correct answers before finalizing."; $message_type = 'error'; }
    $contest = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM contests WHERE id = $contest_id"));
}

// Logic to process payouts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payouts'])) {
    mysqli_begin_transaction($conn);
    try {
        $participants_q = mysqli_query($conn, "SELECT COUNT(id) as count FROM contest_participants WHERE contest_id = $contest_id");
        $num_participants = mysqli_fetch_assoc($participants_q)['count'];
        $total_pool = $contest['entry_fee'] * $num_participants;
        $prize_pool = $total_pool * (1 - (COMMISSION_RATE / 100));

        $participants = mysqli_query($conn, "SELECT id, user_id FROM contest_participants WHERE contest_id = $contest_id");
        $scores = [];
        $points_per_correct_answer = 10;
        
        while ($p = mysqli_fetch_assoc($participants)) {
            $participant_id = $p['id'];
            $user_id = $p['user_id'];
            $score_q = mysqli_query($conn, "SELECT COUNT(ca.id) as score FROM contest_answers ca JOIN contest_questions cq ON ca.question_id = cq.id WHERE ca.participant_id = $participant_id AND ca.selected_option_index = cq.correct_option_index");
            $score = (int)mysqli_fetch_assoc($score_q)['score'] * $points_per_correct_answer;
            $scores[$user_id] = $score;
        }

        $prize_per_winner = 0;
        $winner_count = 0;
        if (!empty($scores)) {
            $top_score = max($scores);
            $winners = [];
            if ($top_score > 0) {
                foreach ($scores as $user_id => $score) {
                    if ($score == $top_score) { $winners[] = $user_id; }
                }
            }

            if (count($winners) > 0) {
                $winner_count = count($winners);
                $prize_per_winner = $prize_pool / $winner_count;
                foreach ($winners as $rank => $winner_id) {
                    mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $prize_per_winner WHERE id = $winner_id");
                    $win_desc = "Prize for winning contest #" . $contest_id;
                    mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) VALUES ($winner_id, $prize_per_winner, 'credit', '$win_desc')");
                    mysqli_query($conn, "INSERT INTO contest_winners (contest_id, user_id, score, `rank`, prize_amount) VALUES ($contest_id, $winner_id, $top_score, 1, $prize_per_winner)");
                }
            }
        }
        
        mysqli_query($conn, "UPDATE contests SET status = 'completed' WHERE id = $contest_id");
        mysqli_commit($conn);
        $message = "Payouts processed! " . $winner_count . " users won ₹" . number_format($prize_per_winner, 2) . " each.";
        $message_type = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Error during payout: " . $e->getMessage();
        $message_type = 'error';
    }
    $contest = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM contests WHERE id = $contest_id"));
}

// Fetch questions for this contest
$questions = mysqli_query($conn, "SELECT * FROM contest_questions WHERE contest_id = $contest_id");

// Fetch winners list if the contest is completed
$winners_list = [];
if ($contest['status'] === 'completed') {
    $winners_q = mysqli_query($conn, "SELECT w.*, u.username FROM contest_winners w JOIN users u ON w.user_id = u.id WHERE w.contest_id = $contest_id ORDER BY w.rank ASC, w.score DESC");
    while($row = mysqli_fetch_assoc($winners_q)) { $winners_list[] = $row; }
}
?>

<a href="contests.php" class="text-purple-400 hover:underline mb-6 block">&larr; Back to Contests</a>
<h1 class="text-3xl font-bold text-white mb-2">Manage: <span class="text-purple-400"><?= htmlspecialchars($contest['title']) ?></span></h1>
<p class="text-gray-400 mb-6">Status: <span class="font-semibold"><?= ucfirst($contest['status']) ?></span></p>

<?php if ($message): ?>
    <div class="my-6 p-4 rounded-lg text-center font-semibold <?= $message_type === 'success' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <?php if ($contest['status'] === 'upcoming'): ?>
    <div class="lg:col-span-1">
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700">
            <h2 class="text-2xl font-semibold mb-6">Add New Question</h2>
            <form method="POST" action="manage_questions.php?contest_id=<?= $contest_id ?>" class="space-y-6">
                <input type="hidden" name="add_question">
                <div><label class="block mb-2 text-sm">Question Text</label><input type="text" name="question_text" class="bg-gray-700 w-full p-3 rounded-lg" required></div>
                <div id="options-container"><label class="block mb-2 text-sm">Options</label><input type="text" name="options[]" class="bg-gray-700 w-full p-3 rounded-lg mb-2" placeholder="Option 1" required><input type="text" name="options[]" class="bg-gray-700 w-full p-3 rounded-lg mb-2" placeholder="Option 2" required></div>
                <button type="button" id="add-option-btn" class="text-sm text-purple-400 hover:underline">+ Add Option</button>
                <div class="pt-2"><button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg">Add Question</button></div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= ($contest['status'] === 'upcoming') ? 'lg:col-span-2' : 'lg:col-span-3' ?>">
        <div class="bg-gray-800 p-8 rounded-xl border border-gray-700">
            <h2 class="text-2xl font-semibold mb-6">Results</h2>
            <?php if ($contest['status'] === 'upcoming' || $contest['status'] === 'live'): ?>
            <form method="POST" action="manage_questions.php?contest_id=<?= $contest_id ?>">
                <div class="space-y-6">
                    <?php if(mysqli_num_rows($questions) > 0): mysqli_data_seek($questions, 0); while($q = mysqli_fetch_assoc($questions)): $options = json_decode($q['options'], true); ?>
                    <div class="bg-gray-700/50 p-4 rounded-lg">
                        <p class="font-semibold text-white mb-3"><?= htmlspecialchars($q['question_text']) ?></p>
                        <select name="answers[<?= $q['id'] ?>]" class="bg-gray-800 w-full p-2.5 rounded-lg text-sm" <?= $contest['status'] !== 'upcoming' ? 'disabled' : '' ?> required>
                            <option value="">-- Select Correct Option --</option>
                            <?php foreach($options as $index => $option): ?><option value="<?= $index ?>" <?= ($q['correct_option_index'] !== null && $q['correct_option_index'] == $index) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php endwhile; ?>
                        <?php if ($contest['status'] === 'upcoming'): ?>
                        <div class="pt-2"><button type="submit" name="save_answers" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg">Save Answers & Make Live</button></div>
                        <?php elseif ($contest['status'] === 'live'): ?>
                        <div class="pt-2"><button type="submit" name="process_payouts" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg">Finalize & Process Payouts</button></div>
                        <?php endif; ?>
                    <?php else: ?><p class="text-gray-400">No questions added yet. Add a question from the form.</p><?php endif; ?>
                </div>
            </form>
            <?php else: // Completed State ?>
            <div class="text-center text-green-300 bg-green-500/10 p-4 rounded-lg mb-6"><p class="font-bold">This contest is completed.</p></div>
            <h3 class="text-xl font-semibold mb-4">Final Leaderboard</h3>
            <div class="space-y-3">
            <?php if (!empty($winners_list)): foreach($winners_list as $winner): ?>
                <div class="flex items-center justify-between bg-gray-700/50 p-3 rounded-lg">
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-lg text-yellow-300">#<?= $winner['rank'] ?></span>
                        <span class="font-medium text-white"><?= htmlspecialchars($winner['username']) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-300 mr-4">Score: <span class="font-bold"><?= $winner['score'] ?></span></span>
                        <span class="font-bold text-green-400">+ ₹<?= number_format($winner['prize_amount']) ?></span>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <p class="text-gray-400 text-center">No winners were recorded for this contest.</p>
            <?php endif; ?>
            </div>
            <?php endif; ?>
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