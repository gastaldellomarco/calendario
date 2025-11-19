<?php
/**
 * Widget notifiche per header
 */
function renderNotificationWidget() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return '';
    }
    
    // Conta notifiche non lette
    $sql_count = "SELECT COUNT(*) as count FROM notifiche WHERE utente_id = ? AND letta = 0";
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetchColumn();
    
    // Ottieni ultime 5 notifiche
    $sql_notifiche = "SELECT * FROM notifiche 
                     WHERE utente_id = ? 
                     ORDER BY created_at DESC 
                     LIMIT 5";
    $stmt = $pdo->prepare($sql_notifiche);
    $stmt->execute([$_SESSION['user_id']]);
    $notifiche = $stmt->fetchAll();
    
    ob_start();
    ?>
    <div class="relative dropdown-container" id="notification-widget">
        <!-- Icona Campana -->
        <button class="dropdown-toggle relative p-2 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 rounded-full">
            <i class="fas fa-bell text-xl"></i>
            <?php if ($count > 0): ?>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                    <?php echo $count > 9 ? '9+' : $count; ?>
                </span>
            <?php endif; ?>
        </button>

        <!-- Dropdown Notifiche -->
        <div class="dropdown-menu w-80 right-0 left-auto" id="notification-dropdown">
            <div class="px-4 py-3 border-b border-gray-200 bg-white rounded-t-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Notifiche</h3>
                    <?php if ($count > 0): ?>
                        <button id="segna-tutte-lette-widget" 
                                class="text-sm text-blue-600 hover:text-blue-500">
                            Segna tutte come lette
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="max-h-96 overflow-y-auto">
                <?php if (empty($notifiche)): ?>
                    <div class="px-4 py-6 text-center">
                        <i class="fas fa-bell-slash text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-500 text-sm">Nessuna notifica</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifiche as $notifica): ?>
                        <div class="border-b border-gray-200 last:border-b-0">
                            <a href="<?php echo $notifica['azione_url'] ? BASE_URL . $notifica['azione_url'] : '#'; ?>" 
                               class="block px-4 py-3 hover:bg-gray-50 notification-item <?php echo !$notifica['letta'] ? 'bg-blue-50' : ''; ?>" 
                               data-notifica-id="<?php echo $notifica['id']; ?>">
                                <div class="flex items-start">
                                    <!-- Icona Priorità -->
                                    <div class="flex-shrink-0 mr-3 mt-1">
                                        <?php
                                        $icon_class = [
                                            'urgente' => 'fas fa-exclamation-circle text-red-500',
                                            'alta' => 'fas fa-exclamation-triangle text-orange-500',
                                            'media' => 'fas fa-info-circle text-blue-500',
                                            'bassa' => 'fas fa-bell text-gray-400'
                                        ][$notifica['priorita']] ?? 'fas fa-bell text-gray-400';
                                        ?>
                                        <i class="<?php echo $icon_class; ?>"></i>
                                    </div>
                                    
                                    <!-- Contenuto -->
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate <?php echo !$notifica['letta'] ? 'font-bold' : ''; ?>">
                                            <?php echo htmlspecialchars($notifica['titolo']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500 mt-1 line-clamp-2">
                                            <?php echo htmlspecialchars($notifica['messaggio']); ?>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <?php echo time_elapsed_string($notifica['created_at']); ?>
                                        </p>
                                    </div>
                                    
                                    <?php if (!$notifica['letta']): ?>
                                        <div class="flex-shrink-0 ml-2">
                                            <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                <a href="<?php echo BASE_URL; ?>/pages/notifiche.php" 
                   class="block text-center text-sm font-medium text-blue-600 hover:text-blue-500">
                    Vedi tutte le notifiche
                </a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const widget = document.getElementById('notification-widget');
        const dropdown = document.getElementById('notification-dropdown');
        
        // Toggle dropdown
        widget.querySelector('.dropdown-toggle').addEventListener('click', function(e) {
            e.stopPropagation();
            widget.classList.toggle('active');
        });
        
        // Chiudi dropdown quando clicchi fuori
        document.addEventListener('click', function(e) {
            if (!widget.contains(e.target)) {
                widget.classList.remove('active');
            }
        });
        
        // Segna notifica come letta al click
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificaId = this.dataset.notificaId;
                if (notificaId) {
                    fetch('<?php echo BASE_URL; ?>/api/segna_notifica_letta.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ notifica_id: notificaId })
                    });
                }
            });
        });
        
        // Segna tutte come lette
        document.getElementById('segna-tutte-lette-widget')?.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            fetch('<?php echo BASE_URL; ?>/api/notifiche_api.php?action=mark_all_as_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
        
        // Auto-refresh ogni 30 secondi
        setInterval(() => {
            fetch('<?php echo BASE_URL; ?>/api/notifiche_api.php?action=get_notifiche&per_page=5')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Aggiorna badge
                        const unreadCount = data.data.filter(n => !n.letta).length;
                        const badge = widget.querySelector('.absolute');
                        if (badge) {
                            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                            if (unreadCount === 0) {
                                badge.remove();
                            }
                        } else if (unreadCount > 0) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center';
                            newBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                            widget.querySelector('.dropdown-toggle').appendChild(newBadge);
                        }
                    }
                });
        }, 30000);
    });
    
    function time_elapsed_string(datetime) {
        const now = new Date();
        const date = new Date(datetime);
        const diff = (now - date) / 1000; // differenza in secondi
        
        if (diff < 60) {
            return 'ora fa';
        } else if (diff < 3600) {
            return Math.floor(diff / 60) + ' minuti fa';
        } else if (diff < 86400) {
            return Math.floor(diff / 3600) + ' ore fa';
        } else if (diff < 2592000) {
            return Math.floor(diff / 86400) + ' giorni fa';
        } else {
            return date.toLocaleDateString('it-IT');
        }
    }
    </script>
    <?php
    return ob_get_clean();
}

// Helper function per formattare il tempo
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'anno',
        'm' => 'mese',
        'w' => 'settimana',
        'd' => 'giorno',
        'h' => 'ora',
        'i' => 'minuto',
        's' => 'secondo',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 'i' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' fa' : 'ora fa';
}