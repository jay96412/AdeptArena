<?php
include 'common/header.php';
$user_id = $_SESSION['user_id'];

// --- UPDATED STATS CALCULATION (LOSS IS REMOVED) ---

// 1. Core Stats
$matches_played_q = mysqli_query($conn, "SELECT COUNT(id) as count FROM participants WHERE user_id = $user_id");
$matches_played = mysqli_fetch_assoc($matches_played_q)['count'];

$wins_q = mysqli_query($conn, "SELECT COUNT(id) as count FROM winners WHERE user_id = $user_id");
$total_wins = mysqli_fetch_assoc($wins_q)['count'];

$winnings_q = mysqli_query($conn, "SELECT SUM(prize_amount) as total FROM winners WHERE user_id = $user_id");
$total_winnings = (float)mysqli_fetch_assoc($winnings_q)['total'];

$win_rate = ($matches_played > 0) ? ($total_wins / $matches_played) * 100 : 0;

// 2. Data for Charts
// Chart 1: Winnings per Game (NEW)
$winnings_by_game_q = mysqli_query($conn, "
    SELECT g.name, SUM(w.prize_amount) as total_prize
    FROM winners w
    JOIN tournaments t ON w.tournament_id = t.id
    JOIN games g ON t.game_id = g.id
    WHERE w.user_id = $user_id
    GROUP BY g.name
");
$winnings_game_labels = [];
$winnings_game_amounts = [];
while($row = mysqli_fetch_assoc($winnings_by_game_q)){
    $winnings_game_labels[] = $row['name'];
    $winnings_game_amounts[] = $row['total_prize'];
}

// Chart 2: Games Played Distribution
$games_dist_q = mysqli_query($conn, "
    SELECT g.name, COUNT(p.id) as play_count 
    FROM participants p 
    JOIN tournaments t ON p.tournament_id = t.id 
    JOIN games g ON t.game_id = g.id 
    WHERE p.user_id = $user_id 
    GROUP BY g.name
");
$game_dist_labels = [];
$game_play_counts = [];
while($row = mysqli_fetch_assoc($games_dist_q)){
    $game_dist_labels[] = $row['name'];
    $game_play_counts[] = $row['play_count'];
}
?>

<div class="py-6">
    <a href="profile.php" class="text-purple-400 hover:underline mb-4 inline-block">&larr; Back to Profile</a>
    <h1 class="text-3xl font-bold text-white mb-6">Your Performance Stats</h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-800 p-4 rounded-xl text-center">
            <p class="text-2xl font-bold"><?= $total_wins ?></p>
            <p class="text-sm text-gray-400">Total Wins</p>
        </div>
        <div class="bg-gray-800 p-4 rounded-xl text-center">
            <p class="text-2xl font-bold text-green-400">₹<?= number_format($total_winnings) ?></p>
            <p class="text-sm text-gray-400">Total Winnings</p>
        </div>
        <div class="bg-gray-800 p-4 rounded-xl text-center">
            <p class="text-2xl font-bold"><?= $matches_played ?></p>
            <p class="text-sm text-gray-400">Matches Played</p>
        </div>
        <div class="bg-gray-800 p-4 rounded-xl text-center">
            <p class="text-2xl font-bold text-purple-400"><?= number_format($win_rate, 1) ?>%</p>
            <p class="text-sm text-gray-400">Win Rate</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 bg-gray-800 p-6 rounded-xl border border-gray-700">
            <h2 class="text-xl font-semibold mb-4">Winnings per Game</h2>
            <canvas id="winningsByGameChart"></canvas>
        </div>
        <div class="lg:col-span-2 bg-gray-800 p-6 rounded-xl border border-gray-700">
            <h2 class="text-xl font-semibold mb-4">Games Played Distribution</h2>
            <canvas id="gamesChart"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.color = '#9ca3af';

    // ## NEW BAR CHART: Winnings per Game ##
    const ctxWinnings = document.getElementById('winningsByGameChart').getContext('2d');
    new Chart(ctxWinnings, {
        type: 'bar',
        data: {
            labels: <?= json_encode($winnings_game_labels) ?>,
            datasets: [{
                label: 'Winnings in ₹',
                data: <?= json_encode($winnings_game_amounts) ?>,
                backgroundColor: 'rgba(52, 211, 153, 0.5)',
                borderColor: 'rgba(52, 211, 153, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
    });

    // Doughnut Chart: Games Distribution
    const ctxGames = document.getElementById('gamesChart').getContext('2d');
    new Chart(ctxGames, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($game_dist_labels) ?>,
            datasets: [{
                data: <?= json_encode($game_play_counts) ?>,
                backgroundColor: ['#a855f7', '#8b5cf6', '#6366f1', '#3b82f6', '#14b8a6'],
                hoverOffset: 4,
                borderColor: '#1f2937'
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
});
</script>

<?php include 'common/bottom.php'; ?>