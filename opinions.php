<?php
include 'common/header.php';

// Fetch all open events
$events = mysqli_query($conn, "SELECT * FROM opinion_events WHERE status = 'open' ORDER BY created_at DESC");
?>

<div class="py-6">
    <h1 class="text-3xl font-bold mb-6">Opinion Events</h1>
    
    <div class="space-y-6">
    <?php if(mysqli_num_rows($events) > 0): while($event = mysqli_fetch_assoc($events)): 
        $options = json_decode($event['options'], true);
    ?>
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 event-card" data-event-id="<?= $event['id'] ?>">
            <h2 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($event['title']) ?></h2>
            <p class="text-sm text-gray-400 mb-4">Total Pool: <span class="font-bold text-yellow-300 total-pool">Calculating...</span></p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <?php foreach($options as $index => $option): ?>
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold"><?= htmlspecialchars($option) ?></span>
                        <span class="font-bold text-lg text-green-400 odds-display" data-option-index="<?= $index ?>">--x</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <form class="bet-form flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                <input type="number" name="bet_amount" class="bg-gray-700 w-full sm:w-1/3 p-2.5 rounded-lg" placeholder="₹ Amount" required>
                <select name="option_index" class="bg-gray-700 flex-1 p-2.5 rounded-lg" required>
                    <option value="">-- Select an Option --</option>
                    <?php foreach($options as $index => $option): ?>
                    <option value="<?= $index ?>"><?= htmlspecialchars($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bet-btn bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-5 rounded-lg">Bet</button>
            </form>
        </div>
    <?php endwhile; else: ?>
        <p class="text-gray-400 text-center">No open events available right now.</p>
    <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    function updateAllEventData() {
        $('.event-card').each(function() {
            const card = $(this);
            const eventId = card.data('event-id');

            $.ajax({
                url: 'ajax_opinion_handler.php',
                type: 'GET',
                data: { action: 'get_event_data', event_id: eventId },
                dataType: 'json',
                success: function(data) {
                    card.find('.total-pool').text('₹' + data.total_pool);
                    for (const [index, odds_info] of Object.entries(data.odds_data)) {
                        const odds_span = card.find(`.odds-display[data-option-index="${index}"]`);
                        if(odds_info.odds > 0) {
                            odds_span.text(odds_info.odds + 'x');
                        } else {
                            odds_span.text('--x');
                        }
                    }
                }
            });
        });
    }

    setInterval(updateAllEventData, 10000);
    updateAllEventData();

    $('.bet-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const button = form.find('.bet-btn');
        const originalButtonText = button.text();
        let formData = form.serialize() + '&action=place_bet';

        $.ajax({
            url: 'ajax_opinion_handler.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() { button.text('...').prop('disabled', true); },
            success: function(response) {
                if (response.status === 'success') {
                    // ## THIS IS THE NEW POPUP ANIMATION ##
                    Swal.fire({
                        title: 'Bet Placed!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonText: 'Great!',
                        background: '#1f2937', // Dark background for the popup
                        color: '#f9fafb'      // Light text for the popup
                    });
                    
                    updateAllEventData(); // Refresh odds
                    if(response.new_balance) {
                        $('#wallet-balance').text('₹' + response.new_balance);
                    }
                } else {
                    // Error popup
                    Swal.fire({
                        title: 'Oops...',
                        text: response.message,
                        icon: 'error',
                        confirmButtonText: 'Okay',
                        background: '#1f2937',
                        color: '#f9fafb'
                    });
                }
            },
            error: function(xhr) { 
                Swal.fire({
                        title: 'Error!',
                        text: 'An unknown error occurred. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'Okay',
                        background: '#1f2937',
                        color: '#f9fafb'
                    });
                console.error("Betting Error:", xhr.responseText);
            },
            complete: function() { button.text(originalButtonText).prop('disabled', false); }
        });
    });

    // We no longer need the showToast function
    // function showToast(message, type) { /* ... */ }
});
</script>

<?php include 'common/bottom.php'; ?>