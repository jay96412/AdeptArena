<?php
include 'common/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
// All PHP queries to fetch data remain the same as before
$upcoming_result = mysqli_query($conn, "SELECT t.*, g.name as game_name_from_table, g.icon_url FROM tournaments t JOIN participants p ON t.id = p.tournament_id LEFT JOIN games g ON t.game_id = g.id WHERE p.user_id = $user_id AND (t.status = 'upcoming' OR t.status = 'live') ORDER BY t.match_time ASC");
$completed_result = mysqli_query($conn, "SELECT t.*, g.name as game_name_from_table, g.icon_url, w.prize_amount, w.rank FROM tournaments t JOIN participants p ON t.id = p.tournament_id LEFT JOIN games g ON t.game_id = g.id LEFT JOIN winners w ON t.id = w.tournament_id AND w.user_id = p.user_id WHERE p.user_id = $user_id AND t.status = 'completed' ORDER BY t.match_time DESC");
$opinions_result = mysqli_query($conn, "SELECT ob.*, oe.title as event_title, oe.options FROM opinion_bets ob JOIN opinion_events oe ON ob.event_id = oe.id WHERE ob.user_id = $user_id ORDER BY ob.created_at DESC");
?>

<div class="py-6">
    <h1 class="text-3xl font-bold mb-6">My Activity</h1>

    <div class="mb-6 border-b border-gray-700">
        <ul class="flex -mb-px text-sm font-medium text-center">
            <li class="flex-1"><button class="tab-btn w-full p-4 border-b-2" id="upcoming-tab-btn" type="button">Tournaments</button></li>
            <li class="flex-1"><button class="tab-btn w-full p-4 border-b-2" id="opinions-tab-btn" type="button">My Opinions</button></li>
            <li class="flex-1"><button class="tab-btn w-full p-4 border-b-2" id="completed-tab-btn" type="button">Completed</button></li>
        </ul>
    </div>

    <div id="upcoming-content" class="tab-content">
        <div class="space-y-6">
        <?php if (mysqli_num_rows($upcoming_result) > 0): while ($row = mysqli_fetch_assoc($upcoming_result)): ?>
            <div class="bg-gray-800 rounded-xl shadow-lg p-6 border border-gray-700 space-y-4">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4"><img src="<?= htmlspecialchars($row['icon_url'] ?? 'https://via.placeholder.com/40') ?>" class="w-12 h-12 rounded-lg object-cover"><div><p class="text-base text-purple-400 font-semibold"><?= htmlspecialchars($row['game_name_from_table']) ?></p><h2 class="text-xl font-bold mt-1 text-white"><?= htmlspecialchars($row['title']) ?></h2></div></div>
                    <?php if ($row['status'] == 'live'): ?><span class="bg-red-500/80 text-white text-xs font-bold px-3 py-1 rounded-full animate-pulse">LIVE</span><?php else: ?><span class="bg-blue-500/80 text-white text-xs font-bold px-3 py-1 rounded-full">UPCOMING</span><?php endif; ?>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center border-y border-gray-700 py-4">
                    <div><p class="text-xs text-gray-400 mb-1">PRIZE POOL</p><p class="font-bold text-lg text-green-400">₹<?= number_format($row['prize_pool']) ?></p></div>
                    <div><p class="text-xs text-gray-400 mb-1">ENTRY FEE</p><p class="font-bold text-lg text-white">₹<?= number_format($row['entry_fee']) ?></p></div>
                    <div><p class="text-xs text-gray-400 mb-1">MATCH TIME</p><p class="font-bold text-lg text-white"><?= date('h:i A', strtotime($row['match_time'])) ?></p></div>
                </div>
                <?php if ($row['status'] == 'live' && !empty($row['room_id'])): ?>
                <div class="bg-gray-900/50 p-3 rounded-lg border border-gray-700"><h3 class="text-sm text-center font-semibold text-gray-300 mb-2">ROOM DETAILS</h3><div class="flex justify-around items-center"><p>Room ID: <span class="font-bold text-yellow-300 tracking-wider"><?= htmlspecialchars($row['room_id']) ?></span></p><p>Password: <span class="font-bold text-yellow-300 tracking-wider"><?= htmlspecialchars($row['room_password']) ?></span></p></div></div>
                <?php endif; ?>
                
                <?php if ($row['status'] == 'upcoming'): ?>
                <div class="border-t border-gray-700 pt-4">
                    <button class="cancel-btn w-full bg-red-500/20 hover:bg-red-500/30 text-red-300 font-semibold py-2 rounded-lg transition-colors" data-tournament-id="<?= $row['id'] ?>">
                        Cancel Entry & Get Refund
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php endwhile; else: ?>
            <div class="bg-gray-800 rounded-lg p-8 text-center"><p class="text-gray-400">Aapne abhi tak koi Upcoming ya Live tournament join nahi kiya hai.</p></div>
        <?php endif; ?>
        </div>
    </div>

    <div id="opinions-content" class="tab-content hidden">
        </div>

    <div id="completed-content" class="tab-content hidden">
        </div>
</div>

<script>
$(document).ready(function() {
    // Tab Logic remains the same
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
    showTab('upcoming');

    // Cancel Entry Logic
    $('.cancel-btn').on('click', function() {
        const button = $(this);
        const tournamentId = button.data('tournament-id');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "Your entry will be cancelled and the fee will be refunded.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, cancel it!',
            cancelButtonText: 'No, keep it',
            background: '#1f2937', color: '#f9fafb'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax_handler.php',
                    type: 'POST',
                    data: { action: 'cancel_join', tournament_id: tournamentId },
                    dataType: 'json',
                    beforeSend: function() { button.text('Cancelling...').prop('disabled', true); },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Cancelled!', response.message, 'success');
                            $('#wallet-balance').text('₹' + response.new_balance);
                            button.closest('.bg-gray-800').fadeOut(500, function() { $(this).remove(); });
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() { Swal.fire('Error!', 'An unknown error occurred.', 'error'); },
                    complete: function() { button.text('Cancel Entry & Get Refund').prop('disabled', false); }
                });
            }
        });
    });
});
</script>

<?php include 'common/bottom.php'; ?>