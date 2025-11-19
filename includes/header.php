<?php
// Includi configurazione
require_once __DIR__ . '/../config/config.php';

// ✅ Imposta current_page
if (!isset($current_page)) {
    $current_page = basename($_SERVER['SCRIPT_NAME'], '.php');
}
if (!isset($page_title)) {
    $page_title = ucfirst(str_replace('_', ' ', $current_page));
}

// 📋 MENU STRUTTURATO PER CATEGORIE
$menu_items = [
    [
        'categoria' => 'Dashboard',
        'icon' => 'fa-tachometer-alt',
        'items' => [
            ['label' => 'Home', 'page' => 'dashboard', 'url' => '/dashboard.php', 'icon' => 'fa-home']
        ]
    ],
    [
        'categoria' => '👥 Gestione Personale',
        'icon' => 'fa-users',
        'items' => [
            ['label' => 'Docenti', 'page' => 'docenti', 'url' => '/pages/docenti.php', 'icon' => 'fa-chalkboard-teacher'],
            ['label' => 'Docente Edit', 'page' => 'docente_edit', 'url' => '/pages/docente_edit.php', 'icon' => 'fa-user-edit'],
        ]
    ],
    [
        'categoria' => '🎓 Gestione Didattica',
        'icon' => 'fa-book',
        'items' => [
            ['label' => 'Classi', 'page' => 'classi', 'url' => '/pages/classi.php', 'icon' => 'fa-users-class'],
            ['label' => 'Materie', 'page' => 'materie', 'url' => '/pages/materie.php', 'icon' => 'fa-book'],
            ['label' => 'Percorsi Formativi', 'page' => 'percorsi', 'url' => '/pages/percorsi.php', 'icon' => 'fa-graduation-cap'],
        ]
    ],
    [
        'categoria' => '📅 Calendario',
        'icon' => 'fa-calendar',
        'items' => [
            ['label' => 'Lezioni', 'page' => 'calendario', 'url' => '/pages/calendario.php', 'icon' => 'fa-clock'],
            ['label' => 'Slot Orari', 'page' => 'orari_slot', 'url' => '/pages/orari_slot.php', 'icon' => 'fa-calendar-alt'],
            ['label' => 'Genera Calendario', 'page' => 'genera_calendario', 'url' => '/pages/genera_calendario.php', 'icon' => 'fa-cog'],
        ]
    ],
    [
        'categoria' => '🏢 Struttura Scuola',
        'icon' => 'fa-building',
        'items' => [
            ['label' => 'Sedi', 'page' => 'sedi', 'url' => '/pages/sedi.php', 'icon' => 'fa-building'],
            ['label' => 'Aule', 'page' => 'aule', 'url' => '/pages/aule.php', 'icon' => 'fa-door-open'],
            ['label' => 'Anni Scolastici', 'page' => 'anni_scolastici', 'url' => '/pages/anni_scolastici.php', 'icon' => 'fa-calendar-check'],
            ['label' => 'Giorni di Chiusura', 'page' => 'giorni_chiusura', 'url' => '/pages/giorni_chiusura.php', 'icon' => 'fa-x'],
        ]
    ],
    [
        'categoria' => '⚙️ Amministrazione',
        'icon' => 'fa-cog',
        'items' => [
            ['label' => 'Vincoli Docente', 'page' => 'vincoli_docente', 'url' => '/pages/vincoli_docente.php', 'icon' => 'fa-ban'],
            ['label' => 'Docente/Materie', 'page' => 'docente_materie', 'url' => '/pages/docente_materie.php', 'icon' => 'fa-link'],
            ['label' => 'Assegna Materie', 'page' => 'assegna_materie_classe', 'url' => '/pages/assegna_materie_classe.php', 'icon' => 'fa-tasks'],
        ]
    ]
];

// Filtra menu per ruolo (solo admin vede amministrazione)
if (!isLoggedIn() || !in_array(getLoggedUserRole(), ['amministratore', 'preside'])) {
    $menu_items = array_filter($menu_items, function($item) {
        return $item['categoria'] !== '⚙️ Amministrazione' && $item['categoria'] !== '🏢 Struttura Scuola';
    });
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . (isset($page_title) ? " - $page_title" : ''); ?></title>
    <meta name="description" content="Sistema di gestione calendario scolastico per istituti">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome per le icone - CDN di jsdelivr (più affidabile) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@6.4.0/css/all.min.css">
    
    <!-- Fallback Font Awesome da unpkg se jsdelivr fallisce -->
    <link rel="stylesheet" href="https://unpkg.com/font-awesome@6.4.0/css/all.min.css" onerror="this.onerror=null">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    
    <style>
        .nav-active {
            background-color: #3b82f6;
            color: white;
        }
        .nav-active:hover {
            background-color: #2563eb;
        }
        
        /* Dropdown Menu Styles */
        .dropdown-container {
            position: relative;
        }
        
        .dropdown-toggle {
            position: relative;
            cursor: pointer;
        }
        
        .dropdown-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease-in-out;
            position: absolute;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            z-index: 50;
            min-width: 220px;
            top: 100%;
            left: 0;
            margin-top: 8px;
        }
        
        .dropdown-container.active .dropdown-menu,
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: block;
            width: 100%;
            text-align: left;
            padding: 10px 16px;
            color: #374151;
            text-decoration: none;
            transition: all 0.15s;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        
        .dropdown-item:first-of-type {
            border-top: 1px solid #f3f4f6;
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
            border-radius: 0 0 6px 6px;
        }
        
        .dropdown-item:hover {
            background: #f3f4f6;
            color: #1f2937;
            padding-left: 20px;
        }
        
        .dropdown-item.active {
            background: #dbeafe;
            color: #1e40af;
            font-weight: 600;
        }
        
        .category-label {
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .dropdown-toggle .fa-chevron-down {
            transition: transform 0.2s;
        }
        
        .dropdown-container.active .fa-chevron-down {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header Navigation -->
    <header class="bg-white shadow-sm border-b sticky top-0 z-40">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo e nome sito -->
                <div class="flex items-center">
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="text-lg font-bold text-blue-600 whitespace-nowrap mr-8">
                        <i class="fas fa-graduation-cap mr-2"></i><?php echo SITE_NAME; ?>
                    </a>
                    
                    <!-- Menu navigazione desktop -->
                    <nav class="hidden lg:flex space-x-1">
                        <a href="<?php echo BASE_URL; ?>/dashboard.php" 
                           class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 transition duration-150 <?php echo ($current_page == 'dashboard' || $current_page == 'index') ? 'nav-active' : ''; ?>">
                            <i class="fas fa-home mr-1"></i>Home
                        </a>
                        
                        <!-- Menu dropdown per categorie -->
                        <?php foreach ($menu_items as $index => $category): ?>
                            <div class="dropdown-container">
                                <button class="dropdown-toggle px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 transition duration-150 flex items-center" data-dropdown="dropdown-<?php echo $index; ?>">
                                    <i class="fas <?php echo $category['icon']; ?> mr-1"></i>
                                    <?php 
                                    // Rimuovi emoji dalla categoria per il display
                                    $categoria_text = str_replace(['👥 ', '🎓 ', '📅 ', '🏢 ', '⚙️ '], '', $category['categoria']);
                                    echo $categoria_text;
                                    ?>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                
                                <div class="dropdown-menu" id="dropdown-<?php echo $index; ?>">
                                    <div class="category-label"><?php echo $category['categoria']; ?></div>
                                    <?php foreach ($category['items'] as $item): ?>
                                        <a href="<?php echo BASE_URL . $item['url']; ?>" 
                                           class="dropdown-item <?php echo ($current_page == $item['page']) ? 'active' : ''; ?>">
                                            <i class="fas <?php echo $item['icon']; ?> mr-2 w-4"></i><?php echo $item['label']; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Area utente -->
                <div class="flex items-center space-x-4">
                    <?php if (isLoggedIn()): ?>
                        <div class="hidden sm:flex items-center space-x-3">
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(getLoggedUserName()); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars(getLoggedUserRole()); ?></p>
                            </div>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode(getLoggedUserName()); ?>&background=3b82f6&color=fff" 
                                 alt="Avatar" class="w-8 h-8 rounded-full">
                        </div>
                        <a href="<?php echo BASE_URL; ?>/auth/logout.php" 
                           class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-md text-sm font-medium transition duration-150">
                            <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline ml-1">Logout</span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/auth/login.php" 
                           class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                            <i class="fas fa-sign-in-alt mr-1"></i>Login
                        </a>
                    <?php endif; ?>
                    
                    <!-- Hamburger menu mobile -->
                    <button class="lg:hidden text-gray-700 hover:text-gray-900" id="mobile-menu-btn">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Menu mobile -->
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t max-h-96 overflow-y-auto">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="<?php echo BASE_URL; ?>/dashboard.php" 
                   class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 <?php echo ($current_page == 'dashboard' || $current_page == 'index') ? 'nav-active' : ''; ?>">
                    <i class="fas fa-home mr-2"></i>Home
                </a>
                
                <!-- Menu mobile per categorie -->
                <?php foreach ($menu_items as $category): ?>
                    <div class="space-y-1 py-1">
                        <div class="px-3 py-2 text-xs font-bold text-gray-500 uppercase tracking-wider">
                            <?php 
                            // Rimuovi emoji dalla categoria per il display
                            $categoria_text = str_replace(['👥 ', '🎓 ', '📅 ', '🏢 ', '⚙️ '], '', $category['categoria']);
                            echo $categoria_text;
                            ?>
                        </div>
                        <?php foreach ($category['items'] as $item): ?>
                            <a href="<?php echo BASE_URL . $item['url']; ?>" 
                               class="block px-5 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 <?php echo ($current_page == $item['page']) ? 'nav-active' : ''; ?>">
                                <i class="fas <?php echo $item['icon']; ?> mr-2 w-4"></i><?php echo $item['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-full mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Qui verrà incluso il contenuto specifico di ogni pagina -->

    <script>
        // Gestione dropdown menu
        document.querySelectorAll('.dropdown-toggle').forEach(button => {
            const dropdownId = button.getAttribute('data-dropdown');
            const dropdown = document.getElementById(dropdownId);
            const container = button.closest('.dropdown-container');
            
            // Apri dropdown al click
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Chiudi tutti gli altri dropdown
                document.querySelectorAll('.dropdown-container.active').forEach(other => {
                    if (other !== container) {
                        other.classList.remove('active');
                    }
                });
                
                // Toggle il dropdown corrente
                container.classList.toggle('active');
            });
            
            // Tieni il dropdown aperto quando il mouse è sopra
            container.addEventListener('mouseenter', function() {
                container.classList.add('active');
            });
            
            // Chiudi quando il mouse esce
            container.addEventListener('mouseleave', function() {
                container.classList.remove('active');
            });
        });
        
        // Chiudi dropdown quando clicchi su un link
        document.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-container.active').forEach(container => {
                    container.classList.remove('active');
                });
            });
        });
        
        // Chiudi dropdown quando clicchi altrove
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-container')) {
                document.querySelectorAll('.dropdown-container.active').forEach(container => {
                    container.classList.remove('active');
                });
            }
        });
        
        // Toggle menu mobile
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
    </script>