<?php
include 'common/header.php';

// Fetch stats for cards
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as count FROM users"))['count'];
$total_tournaments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as count FROM tournaments"))['count'];
$total_prize_query = "SELECT SUM(t.prize_pool) as total FROM tournaments t WHERE t.status = 'completed' AND t.winner_id IS NOT NULL";
$total_prize = mysqli_fetch_assoc(mysqli_query($conn, $total_prize_query))['total'] ?? 0;
$total_revenue_query = "SELECT SUM(t.entry_fee * (SELECT COUNT(id) FROM participants WHERE tournament_id = t.id) * (t.commission_percentage / 100)) as total_revenue FROM tournaments t WHERE t.status = 'completed'";
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, $total_revenue_query))['total_revenue'] ?? 0;

// Chart Data: Daily Signups (Last 7 Days)
$signup_data_query = "SELECT DATE(created_at) as signup_date, COUNT(id) as count FROM users WHERE created_at >= CURDATE() - INTERVAL 7 DAY GROUP BY DATE(created_at) ORDER BY signup_date ASC";
$signup_result = mysqli_query($conn, $signup_data_query);
$signup_labels = [];
$signup_counts = [];
while($row = mysqli_fetch_assoc($signup_result)) {
    $signup_labels[] = date('M d', strtotime($row['signup_date']));
    $signup_counts[] = $row['count'];
}

// Chart Data: Popular Games
$popular_games_query = "SELECT g.name, COUNT(p.id) as participant_count FROM participants p JOIN tournaments t ON p.tournament_id = t.id JOIN games g ON t.game_id = g.id GROUP BY g.name ORDER BY participant_count DESC LIMIT 5";
$popular_games_result = mysqli_query($conn, $popular_games_query);
$game_labels = [];
$game_counts = [];
while($row = mysqli_fetch_assoc($popular_games_result)) {
    $game_labels[] = $row['name'];
    $game_counts[] = $row['participant_count'];
}

?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-white">Dashboard</h1>
    <a href="tournament.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2">
        <i class="ph-fill ph-plus-circle"></i> Create Tournament
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 flex items-center gap-4"><div class="bg-blue-500/20 p-3 rounded-full"><i class="ph-fill ph-users text-3xl text-blue-300"></i></div><div><p class="text-sm text-gray-400">Total Users</p><p class="text-2xl font-bold"><?= $total_users ?></p></div></div>
    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 flex items-center gap-4"><div class="bg-yellow-500/20 p-3 rounded-full"><i class="ph-fill ph-trophy text-3xl text-yellow-300"></i></div><div><p class="text-sm text-gray-400">Tournaments</p><p class="text-2xl font-bold"><?= $total_tournaments ?></p></div></div>
    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 flex items-center gap-4"><div class="bg-green-500/20 p-3 rounded-full"><i class="ph-fill ph-gift text-3xl text-green-300"></i></div><div><p class="text-sm text-gray-400">Prize Distributed</p><p class="text-2xl font-bold">₹<?= number_format($total_prize) ?></p></div></div>
    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 flex items-center gap-4"><div class="bg-pink-500/20 p-3 rounded-full"><i class="ph-fill ph-chart-line text-3xl text-pink-300"></i></div><div><p class="text-sm text-gray-400">Total Revenue</p><p class="text-2xl font-bold">₹<?= number_format($total_revenue) ?></p></div></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
    <div class="lg:col-span-3 bg-gray-800 p-6 rounded-xl border border-gray-700">
        <h2 class="text-xl font-semibold mb-4">Daily Signups (Last 7 Days)</h2>
        <canvas id="signupsChart"></canvas>
    </div>
    <div class="lg:col-span-2 bg-gray-800 p-6 rounded-xl border border-gray-700">
        <h2 class="text-xl font-semibold mb-4">Most Popular Games</h2>
        <canvas id="gamesChart"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Signups Chart (Bar)
    const ctxSignups = document.getElementById('signupsChart').getContext('2d');
    new Chart(ctxSignups, {
        type: 'bar',
        data: {
            labels: <?= json_encode($signup_labels) ?>,
            datasets: [{
                label: 'New Users',
                data: <?= json_encode($signup_counts) ?>,
                backgroundColor: 'rgba(168, 85, 247, 0.5)',
                borderColor: 'rgba(168, 85, 247, 1)',
                borderWidth: 2,
                borderRadius: 5
            }]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });

    // Games Chart (Doughnut)
    const ctxGames = document.getElementById('gamesChart').getContext('2d');
    new Chart(ctxGames, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($game_labels) ?>,
            datasets: [{
                label: 'Participants',
                data: <?= json_encode($game_counts) ?>,
                backgroundColor: ['#a855f7', '#8b5cf6', '#6366f1', '#3b82f6', '#14b8a6'],
                hoverOffset: 4
            }]
        }
    });
});
</script>

<?php include 'common/bottom.php'; ?>