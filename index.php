<?php
include 'common/header.php';

// Query 1: Fetch upcoming tournaments
$tournaments_result = mysqli_query($conn, "SELECT t.*, g.name as game_name_from_table, g.icon_url FROM tournaments t LEFT JOIN games g ON t.game_id = g.id WHERE t.status = 'upcoming' ORDER BY t.match_time ASC");

// Query 2: Fetch open opinion events
$opinions_result = mysqli_query($conn, "SELECT * FROM opinion_events WHERE status = 'open' ORDER BY created_at DESC");

// Query 3: Fetch upcoming prediction contests
$contests_result = mysqli_query($conn, "SELECT * FROM contests WHERE status = 'upcoming' ORDER BY match_start_time ASC");
?>

<div class="py-6">
    <h1 class="text-3xl font-bold mb-6">Events Lobby</h1>

    <div class="mb-6 border-b border-gray-700">
        <ul class="flex -mb-px text-sm font-medium text-center">
            <li class="flex-1"><button class="tab-btn w-full p-4 border-b-2" id="tournaments-tab-btn" type="button">Tournaments</button></li>
            <li class="flex-1"><button class="tab-btn w-full p-4 border-b-2" id="opinions-tab-btn" type="button">Opinion Events</button></li>
            <li class="flex-1"><button class="tab-btn w-full p-4 border-b-2" id="contests-tab-btn" type="button">Prediction Contests</button></li>
        </ul>
    </div>

    <div id="tournaments-content" class="tab-content">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if (mysqli_num_rows($tournaments_result) > 0): while ($row = mysqli_fetch_assoc($tournaments_result)): ?>
                <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700 flex flex-col group animate__animated animate__fadeInUp">
                    <div class="relative">
                        <img src="<?= htmlspecialchars($row['icon_url'] ?? 'https://via.placeholder.com/400x200') ?>" class="w-full h-40 object-cover">
                    </div>
                    <div class="p-5 flex flex-col flex-grow">
                        <p class="text-sm text-purple-400 font-semibold"><?= htmlspecialchars($row['game_name_from_table']) ?></p>
                        <h2 class="text-lg font-bold mt-1 text-white flex-grow"><?= htmlspecialchars($row['title']) ?></h2>
                        <div class="mt-4 border-t border-gray-700 pt-4">
                            <div class="flex justify-between items-center text-center">
                                <div><p class="text-xs text-gray-400">PRIZE POOL</p><p class="font-bold text-base text-green-400">₹<?= number_format($row['prize_pool']) ?></p></div>
                                <div><p class="text-xs text-gray-400">ENTRY FEE</p><p class="font-bold text-base text-white">₹<?= number_format($row['entry_fee']) ?></p></div>
                            </div>
                        </div>
                        <div class="mt-4 bg-gray-900/50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-400">STARTS IN</p>
                            <div class="countdown-timer font-bold text-lg text-yellow-300" data-time="<?= $row['match_time'] ?>">Loading...</div>
                        </div>
                    </div>
                    <div class="bg-gray-700/50 px-5 py-4 mt-auto">
                        <form class="join-form space-y-3">
                            <input type="hidden" name="tournament_id" value="<?= $row['id'] ?>">
                            <input type="text" name="promo_code" class="bg-gray-800 border border-gray-600 text-white sm:text-sm rounded-lg block w-full p-2.5 uppercase" placeholder="PROMO CODE (OPTIONAL)">
                            <button type="submit" class="join-btn w-full text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg text-sm px-5 py-3 text-center">Join Now</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="bg-gray-800 rounded-lg p-8 text-center sm:col-span-full"><p class="text-gray-400">No upcoming tournaments available.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div id="opinions-content" class="tab-content hidden">
        <div class="space-y-6">
        <?php if(mysqli_num_rows($opinions_result) > 0): while($event = mysqli_fetch_assoc($opinions_result)): 
            $options = json_decode($event['options'], true);
        ?>
            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 event-card" data-event-id="<?= $event['id'] ?>">
                <h3 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($event['title']) ?></h3>
                <p class="text-sm text-gray-400 mb-4">Total Pool: <span class="font-bold text-yellow-300 total-pool">Calculating...</span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <?php foreach($options as $index => $option): ?>
                    <div class="bg-gray-700/50 p-4 rounded-lg">
                        <div class="flex justify-between items-center"><span class="font-semibold"><?= htmlspecialchars($option) ?></span><span class="font-bold text-lg text-green-400 odds-display" data-option-index="<?= $index ?>">--x</span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <form class="bet-form flex flex-col sm:flex-row gap-3">
                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                    <input type="number" name="bet_amount" class="bg-gray-700 w-full sm:w-1/3 p-2.5 rounded-lg" placeholder="₹ Amount" required>
                    <select name="option_index" class="bg-gray-700 flex-1 p-2.5 rounded-lg" required>
                        <option value="">-- Select an Option --</option>
                        <?php foreach($options as $index => $option): ?><option value="<?= $index ?>"><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select>
                    <button type="submit" class="bet-btn bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-5 rounded-lg">Bet</button>
                </form>
            </div>
        <?php endwhile; else: ?>
            <div class="bg-gray-800 rounded-lg p-8 text-center"><p class="text-gray-400">No open opinion events available.</p></div>
        <?php endif; ?>
        </div>
    </div>
    
    <div id="contests-content" class="tab-content hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php if(mysqli_num_rows($contests_result) > 0): while($contest = mysqli_fetch_assoc($contests_result)): ?>
            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 flex flex-col justify-between">
                <div>
                    <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($contest['title']) ?></h3>
                    <p class="text-sm text-gray-400 mt-1">Starts at: <?= date('d M, h:i A', strtotime($contest['match_start_time'])) ?></p>
                </div>
                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-600">
                    <div><p class="text-xs text-gray-400">ENTRY FEE</p><p class="font-bold text-lg text-white">₹<?= number_format($contest['entry_fee']) ?></p></div>
                    <a href="play_contest.php?id=<?= $contest['id'] ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">Play Now</a>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="bg-gray-800 rounded-lg p-8 text-center md:col-span-2"><p class="text-gray-400">No upcoming contests available right now.</p></div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Tab Logic
    const tabs = $('.tab-btn');
    const contents = $('.tab-content');
    function showTab(tabName) {
        tabs.removeClass('border-purple-500 text-purple-500').addClass('border-transparent text-gray-400');
        contents.addClass('hidden');
        $('#' + tabName + '-tab-btn').removeClass('border-transparent text-gray-400').addClass('border-purple-500 text-purple-500');
        $('#' + tabName + '-content').removeClass('hidden');
    }
    tabs.on('click', function() {
        const tabName = $(this).attr('id').replace('-tab-btn', '');
        showTab(tabName);
    });
    showTab('tournaments');

    // AJAX Logic for joining tournaments
    $('.join-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const button = form.find('.join-btn');
        let formData = form.serialize() + '&action=join_tournament';
        $.ajax({
            url: 'ajax_handler.php',
            type: 'POST', data: formData, dataType: 'json',
            beforeSend: function() { button.html('<i class="ph-fill ph-spinner animate-spin"></i> Joining...').prop('disabled', true); },
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({ title: 'Joined!', text: response.message, icon: 'success', background: '#1f2937', color: '#f9fafb' });
                    $('#wallet-balance').text('₹' + response.new_balance);
                } else {
                    Swal.fire({ title: 'Oops...', text: response.message, icon: 'error', background: '#1f2937', color: '#f9fafb' });
                }
            },
            error: function() { Swal.fire({ title: 'Error!', text: 'An unknown error occurred.', icon: 'error', background: '#1f2937', color: '#f9fafb' }); },
            complete: function() { button.text('Join Now').prop('disabled', false); }
        });
    });

    // AJAX Logic for opinion events
    function updateAllEventData() {
        $('.event-card').each(function() {
            const card = $(this);
            const eventId = card.data('event-id');
            if (!eventId) return;
            $.ajax({
                url: 'ajax_opinion_handler.php',
                type: 'GET', data: { action: 'get_event_data', event_id: eventId }, dataType: 'json',
                success: function(data) {
                    card.find('.total-pool').text('₹' + data.total_pool);
                    for (const [index, odds_info] of Object.entries(data.odds_data)) {
                        const odds_span = card.find(`.odds-display[data-option-index="${index}"]`);
                        if(odds_info.odds > 0) { odds_span.text(odds_info.odds + 'x'); } else { odds_span.text('--x'); }
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
        let formData = form.serialize() + '&action=place_bet';
        $.ajax({
            url: 'ajax_opinion_handler.php',
            type: 'POST', data: formData, dataType: 'json',
            beforeSend: function() { button.text('...').prop('disabled', true); },
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({ title: 'Bet Placed!', text: response.message, icon: 'success', background: '#1f2937', color: '#f9fafb' });
                    updateAllEventData();
                    if(response.new_balance) { $('#wallet-balance').text('₹' + response.new_balance); }
                } else {
                    Swal.fire({ title: 'Oops...', text: response.message, icon: 'error', background: '#1f2937', color: '#f9fafb' });
                }
            },
            error: function() { Swal.fire({ title: 'Error!', text: 'An unknown error occurred.', icon: 'error', background: '#1f2937', color: '#f9fafb' }); },
            complete: function() { button.text('Bet').prop('disabled', false); }
        });
    });
});
</script>

<?php include 'common/bottom.php'; ?>