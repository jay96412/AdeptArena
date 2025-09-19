<?php 
include 'common/header.php'; 

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>

<div class="bg-gray-800 text-white min-h-screen font-sans">
    <div class="bg-gray-900 p-4 shadow-md sticky top-0 z-20">
        <div class="text-center mb-4">
            <p class="text-xs text-gray-400">Wallet balance</p>
            <p class="text-2xl font-bold" id="wallet-balance-display">₹<?= number_format($wallet_balance, 2) ?></p>
        </div>
        <div class="flex justify-around">
            <a href="withdraw.php" class="bg-red-500 text-white font-bold py-2 px-8 rounded-full transition-transform hover:scale-105">Withdraw</a>
            <a href="add_money.php" class="bg-green-500 text-white font-bold py-2 px-8 rounded-full transition-transform hover:scale-105">Deposit</a>
        </div>
    </div>

    <div class="p-4">
        <button class="w-full bg-yellow-400/20 text-yellow-300 py-4 rounded-lg border-2 border-yellow-500 font-bold shadow-lg">
            Win Go 1Min
        </button>
    </div>

    <div class="mx-4 p-4 bg-gray-700/50 rounded-lg">
        <div class="flex justify-between items-center mb-3">
            <div class="text-sm"><i class="ph-fill ph-trophy text-yellow-400"></i><span class="text-gray-300 ml-1">Win Go 1 Min</span></div>
            <a href="#" class="text-xs text-gray-400 underline">How to play</a>
        </div>
        <div class="flex justify-between items-center">
            <div>
                <p class="text-gray-400 text-lg">Period</p>
                <p class="font-bold text-xl" id="period-id">Loading...</p>
            </div>
            <div class="text-right">
                <p class="text-gray-400 text-lg">Time remaining</p>
                <div class="flex gap-1 font-bold text-3xl bg-gray-900/50 p-2 rounded-md" id="countdown-timer">
                    <span class="bg-gray-800 text-white px-2 rounded">0</span><span class="bg-gray-800 text-white px-2 rounded">0</span>
                    <span>:</span>
                    <span class="bg-gray-800 text-white px-2 rounded">0</span><span class="bg-gray-800 text-white px-2 rounded">0</span>
                </div>
            </div>
        </div>
    </div>

    <div class="p-4">
        <div class="flex items-center gap-2 mb-3 overflow-x-auto pb-2" id="results-history">
            </div>
        <div class="grid grid-cols-3 gap-3">
            <button data-bet-type="color" data-bet-on="green" class="bet-btn bg-gradient-to-br from-green-500 to-green-700 text-white font-bold py-3 rounded-lg shadow-lg">Green</button>
            <button data-bet-type="color" data-bet-on="violet" class="bet-btn bg-gradient-to-br from-purple-500 to-purple-700 text-white font-bold py-3 rounded-lg shadow-lg">Violet</button>
            <button data-bet-type="color" data-bet-on="red" class="bet-btn bg-gradient-to-br from-red-500 to-red-700 text-white font-bold py-3 rounded-lg shadow-lg">Red</button>
        </div>
        
        <div class="grid grid-cols-5 gap-3 mt-3">
            <?php for($i=0; $i<10; $i++): 
                $colors = ['violet-red','green','red','green','red','violet-green','red','green','red','green'];
                $bg_color = 'bg-gray-600';
                if ($colors[$i] == 'green') $bg_color = 'bg-green-600';
                if ($colors[$i] == 'red') $bg_color = 'bg-red-600';
                if ($colors[$i] == 'violet-red') $bg_color = 'bg-gradient-to-br from-purple-600 to-red-600';
                if ($colors[$i] == 'violet-green') $bg_color = 'bg-gradient-to-br from-purple-600 to-green-600';
            ?>
            <button data-bet-type="number" data-bet-on="<?= $i ?>" class="bet-btn h-14 <?= $bg_color ?> rounded-full text-white font-bold text-2xl shadow-md flex items-center justify-center"><?= $i ?></button>
            <?php endfor; ?>
        </div>
        
        <div class="grid grid-cols-2 gap-3 mt-3">
            <button data-bet-type="size" data-bet-on="big" class="bet-btn bg-yellow-500 text-black font-bold py-3 rounded-lg shadow-lg">Big</button>
            <button data-bet-type="size" data-bet-on="small" class="bet-btn bg-blue-500 text-white font-bold py-3 rounded-lg shadow-lg">Small</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentPeriodId = 0;
    let currentPeriodDbId = 0; // To send with bet request
    let bettingAllowed = false;
    let timerInterval;
    const gameMode = '1m'; // We are only playing 1 minute game

    function updateGameState() {
        $.ajax({
            url: 'ajax_color_game_handler.php',
            type: 'GET',
            data: { action: 'get_state', game_mode: gameMode },
            dataType: 'json',
            success: function(response) {
                if(response.period_id !== currentPeriodId) {
                    currentPeriodId = response.period_id;
                    currentPeriodDbId = response.id;
                    
                    $('#period-id').text(currentPeriodId);
                    
                    const endTime = new Date(response.end_time.replace(' ', 'T')).getTime();
                    
                    if(timerInterval) clearInterval(timerInterval);

                    timerInterval = setInterval(function() {
                        const now = new Date().getTime();
                        const distance = endTime - now;
                        const bettingEndsAfter = 15 * 1000; // Betting closes 15 seconds before the end

                        bettingAllowed = (distance > bettingEndsAfter);

                        if (distance < 0) {
                            clearInterval(timerInterval);
                            $('#countdown-timer').html(`<span class="text-yellow-400 text-lg">Waiting...</span>`);
                            setTimeout(updateGameState, 5000);
                        } else {
                            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                             $('#countdown-timer').html(
                                `<span class="bg-gray-800 text-white px-2 rounded">0</span><span class="bg-gray-800 text-white px-2 rounded">${Math.floor(minutes/10)}</span>
                                <span>:</span>
                                <span class="bg-gray-800 text-white px-2 rounded">${minutes%10}</span><span class="bg-gray-800 text-white px-2 rounded">${Math.floor(seconds/10)}</span><span class="bg-gray-800 text-white px-2 rounded">${seconds%10}</span>`
                            );
                        }
                    }, 1000);
                }

                // History
                const historyContainer = $('#results-history');
                historyContainer.empty();
                if(response.history && Array.isArray(response.history)){
                    response.history.forEach(item => {
                        let colorClass = '';
                        if (item.winning_color.includes('green')) colorClass += 'bg-green-500 ';
                        if (item.winning_color.includes('red')) colorClass += 'bg-red-500 ';
                        if (item.winning_color.includes('violet')) colorClass += 'bg-purple-500 ';
                        const historyHtml = `<div class="h-8 w-8 rounded-full ${colorClass} flex-shrink-0 flex items-center justify-center font-bold text-white text-sm">${item.winning_number}</div>`;
                        historyContainer.append(historyHtml);
                    });
                }
            }
        });
    }

    // Handle bet button clicks
    $('.bet-btn').on('click', function() {
        if (!bettingAllowed) {
            Swal.fire({ title: 'Time Up!', text: 'Betting for this period is closed.', icon: 'error', background: '#1f2937', color: '#f9fafb' });
            return;
        }
        const betType = $(this).data('bet-type');
        const betOn = $(this).data('bet-on');
        
        Swal.fire({
            title: `Bet on "${betOn.toString().toUpperCase()}"`,
            html: `<input type="number" id="bet-amount" class="swal2-input" placeholder="Enter Amount" required>`,
            showCancelButton: true,
            confirmButtonText: 'Confirm Bet',
            background: '#1f2937', color: '#f9fafb',
            preConfirm: () => {
                const amount = Swal.getPopup().querySelector('#bet-amount').value;
                if (!amount || amount <= 0) { Swal.showValidationMessage(`Please enter a valid amount`); }
                return { amount: amount };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const betAmount = result.value.amount;
                $.ajax({
                    url: 'ajax_color_game_handler.php',
                    type: 'POST',
                    data: { action: 'place_bet', period_id: currentPeriodDbId, game_mode: gameMode, bet_type: betType, bet_on: betOn, bet_amount: betAmount },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({title: 'Success!', text: response.message, icon: 'success', background: '#1f2937', color: '#f9fafb'});
                            $('#wallet-balance-display').text('₹' + response.new_balance);
                        } else {
                            Swal.fire({title: 'Error!', text: response.message, icon: 'error', background: '#1f2937', color: '#f9fafb'});
                        }
                    }
                });
            }
        });
    });

    updateGameState();
});
</script>

<?php include 'common/bottom.php'; ?>