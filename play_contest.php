<?php
include 'common/header.php';
$contest_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch contest and questions details
$contest_q = mysqli_query($conn, "SELECT * FROM contests WHERE id = $contest_id AND status = 'upcoming'");
$contest = mysqli_fetch_assoc($contest_q);
$questions = [];
if($contest) {
    $questions_q = mysqli_query($conn, "SELECT * FROM contest_questions WHERE contest_id = $contest_id");
    while($row = mysqli_fetch_assoc($questions_q)) { $questions[] = $row; }
}

// Check if user has already participated
$participant_q = mysqli_query($conn, "SELECT id FROM contest_participants WHERE user_id = $user_id AND contest_id = $contest_id");
$has_participated = mysqli_num_rows($participant_q) > 0;
?>

<div class="py-6 max-w-2xl mx-auto">
    <a href="index.php" class="text-purple-400 hover:underline mb-4 inline-block">&larr; Back to Lobby</a>
    
    <?php if($contest && !$has_participated): ?>
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($contest['title']) ?></h1>
            <p class="text-gray-400 mt-2">Entry Fee: <span class="font-bold text-lg text-white">₹<?= number_format($contest['entry_fee']) ?></span></p>
        </div>

        <form id="contest-form">
            <input type="hidden" name="contest_id" value="<?= $contest_id ?>">
            <div class="space-y-6">
                <?php foreach($questions as $index => $q): 
                    $options = json_decode($q['options'], true);
                ?>
                <div class="bg-gray-800 p-5 rounded-lg border border-gray-700">
                    <p class="font-semibold text-lg mb-3"><?= ($index + 1) ?>. <?= htmlspecialchars($q['question_text']) ?></p>
                    <div class="space-y-2">
                        <?php foreach($options as $opt_index => $opt): ?>
                        <label class="flex items-center bg-gray-700/50 p-3 rounded-md hover:bg-gray-700 cursor-pointer transition-colors">
                            <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt_index ?>" class="w-4 h-4 mr-4 text-purple-600 bg-gray-900 border-gray-600 focus:ring-purple-600 ring-offset-gray-800 focus:ring-2">
                            <span><?= htmlspecialchars($opt) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" id="submit-btn" class="w-full mt-8 bg-green-600 hover:bg-green-700 text-white font-bold text-lg py-3 rounded-lg transition-transform hover:scale-105">Submit Answers & Pay ₹<?= number_format($contest['entry_fee']) ?></button>
        </form>

    <?php elseif($has_participated): ?>
        <div class="bg-gray-800 text-center p-8 rounded-lg"><p class="text-green-400 text-lg">You have already participated in this contest. Good luck!</p><a href="my_tournaments.php" class="text-purple-400 underline mt-2 block">Check My Activity</a></div>
    <?php else: ?>
        <div class="bg-gray-800 text-center p-8 rounded-lg"><p class="text-red-400 text-lg">This contest is not available or has already started.</p></div>
    <?php endif; ?>
</div>

<script>
$('#contest-form').on('submit', function(e){
    e.preventDefault();
    const form = $(this);
    const button = form.find('#submit-btn');
    const originalButtonText = button.text();
    let formData = form.serialize() + '&action=submit_contest_entry';

    Swal.fire({
        title: 'Confirm Entry?',
        text: "The entry fee will be deducted from your wallet. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Join Contest!',
        cancelButtonText: 'Cancel',
        background: '#1f2937', color: '#f9fafb'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax_contest_handler.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                beforeSend: function() { button.html('<i class="ph-fill ph-spinner animate-spin"></i> Processing...').prop('disabled', true); },
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Success!', text: response.message, icon: 'success',
                            background: '#1f2937', color: '#f9fafb'
                        }).then(() => {
                            window.location.href = 'my_tournaments.php'; // Redirect after success
                        });
                        if(response.new_balance) { $('#wallet-balance').text('₹' + response.new_balance); }
                    } else {
                        Swal.fire({ title: 'Oops...', text: response.message, icon: 'error', background: '#1f2937', color: '#f9fafb' });
                    }
                },
                error: function() { Swal.fire('Error!', 'An unknown error occurred.', 'error'); },
                complete: function() { button.text(originalButtonText).prop('disabled', false); }
            });
        }
    });
});
</script>
<?php include 'common/bottom.php'; ?>