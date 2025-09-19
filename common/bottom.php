</main>

<nav class="bg-gray-800/80 backdrop-blur-sm fixed bottom-0 left-0 right-0 z-50 border-t border-gray-700">
    <div class="container mx-auto grid grid-cols-5">
        <?php
            $active_page = basename($_SERVER['PHP_SELF']);
            $nav_items = [
                ['page' => 'index.php', 'icon' => 'ph-house', 'label' => 'Home'],
                ['page' => 'color_game.php', 'icon' => 'ph-game-controller', 'label' => 'Win Go'],
                ['page' => 'my_tournaments.php', 'icon' => 'ph-trophy', 'label' => 'My Activity'],
                ['page' => 'wallet.php', 'icon' => 'ph-wallet', 'label' => 'Wallet'],
                ['page' => 'profile.php', 'icon' => 'ph-user', 'label' => 'Profile']

            ];

            foreach ($nav_items as $item) {
                $is_active = ($active_page == $item['page']);
                $text_color = $is_active ? 'text-purple-400' : 'text-gray-400';
                $icon_weight = $is_active ? 'ph-fill' : 'ph';
                echo "<a href='{$item['page']}' class='text-center py-3 {$text_color} hover:text-purple-300 transition-colors'>";
                echo "<i class='{$icon_weight} {$item['icon']} text-2xl'></i>";
                echo "<span class='block text-xs'>{$item['label']}</span>";
                echo "</a>";
            }
        ?>
    </div>
</nav>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Live Countdown Timer Logic
    $(document).ready(function() {
        setInterval(function() {
            $('.countdown-timer').each(function() {
                const timerElement = $(this);
                const matchTimeStr = timerElement.data('time');
                const matchTime = new Date(matchTimeStr.replace(' ', 'T')).getTime();
                const now = new Date().getTime();
                const distance = matchTime - now;
                
                if (distance < 0) {
                    timerElement.html('<span class="text-red-400 animate-pulse">Match Live!</span>');
                } else {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    let countdownText = '';
                    if (days > 0) countdownText += days + "d ";
                    countdownText += hours + "h " + minutes + "m " + seconds + "s ";
                    timerElement.text(countdownText);
                }
            });
        }, 1000);
    });

    // Disable right-click and zoom
    document.addEventListener('contextmenu', event => event.preventDefault());
    document.addEventListener('keydown', function (event) {
      if (event.ctrlKey === true && (event.key === '+' || event.key === '-' || event.key === '0')) {
        event.preventDefault();
      }
    });
    window.addEventListener('wheel', function (event) {
      if (event.ctrlKey === true) { event.preventDefault(); }
    }, { passive: false });
</script>
</body>
</html>