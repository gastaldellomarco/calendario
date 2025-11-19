<?php
// index.php - Dashboard semplificata
require_once __DIR__ . '/includes/auth_check.php';

$page_title = "Dashboard";
$current_page = "dashboard";
?>
<?php include_once __DIR__ . '/includes/header.php'; ?>

<div class="px-4 py-6 sm:px-0">
    <!-- Header pagina -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p class="mt-2 text-sm text-gray-600">
            Sistema di gestione calendario scolastico - Panoramica generale
        </p>
        <!-- Benvenuto utente -->
        <div class="mt-2 text-sm text-gray-500">
            Benvenuto, <span class="font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['nome_visualizzato'] ?? $_SESSION['username']); ?></span>
            (<span class="font-medium text-blue-600"><?php echo htmlspecialchars($_SESSION['ruolo']); ?></span>)
        </div>
    </div>

    <!-- Messaggio di benvenuto migliorato -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 mb-8">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-graduation-cap text-blue-500 text-2xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-medium text-blue-800">Benvenuto in <?php echo SITE_NAME; ?></h3>
                <p class="mt-1 text-blue-700">
                    Sistema completo per la gestione del calendario scolastico, orari delle lezioni, 
                    assegnazione docenti e monitoraggio delle attività didattiche.
                </p>
                <div class="mt-3">
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-150">
                        <i class="fas fa-tachometer-alt mr-2"></i>
                        Vai alla Dashboard Completa
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Card statistiche semplificate -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Card Docenti -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chalkboard-teacher text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Docenti totali
                            </dt>
                            <dd class="text-lg font-medium text-gray-900">
                                <?php
                                try {
                                    require_once __DIR__ . '/config/database.php';
                                    $pdo = getPDOConnection();
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM docenti WHERE stato = 'attivo'");
                                    $result = $stmt->fetch();
                                    echo $result['total'];
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <div class="text-sm">
                    <a href="<?php echo BASE_URL; ?>/pages/docenti.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Visualizza tutti
                    </a>
                </div>
            </div>
        </div>

        <!-- Card Classi -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Classi attive
                            </dt>
                            <dd class="text-lg font-medium text-gray-900">
                                <?php
                                try {
                                    require_once __DIR__ . '/config/database.php';
                                    $pdo = getPDOConnection();
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM classi WHERE stato = 'attiva'");
                                    $result = $stmt->fetch();
                                    echo $result['total'];
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <div class="text-sm">
                    <a href="<?php echo BASE_URL; ?>/pages/classi.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Visualizza tutte
                    </a>
                </div>
            </div>
        </div>

        <!-- Card Lezioni oggi -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar-day text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Lezioni oggi
                            </dt>
                            <dd class="text-lg font-medium text-gray-900">
                                <?php
                                try {
                                    require_once __DIR__ . '/config/database.php';
                                    $pdo = getPDOConnection();
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM calendario_lezioni WHERE data_lezione = CURDATE() AND stato != 'cancellata'");
                                    $result = $stmt->fetch();
                                    echo $result['total'];
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <div class="text-sm">
                    <a href="<?php echo BASE_URL; ?>/pages/orari_slot.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Vedi calendario
                    </a>
                </div>
            </div>
        </div>

        <!-- Card Conflitti -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Conflitti aperti
                            </dt>
                            <dd class="text-lg font-medium text-gray-900">
                                <?php
                                try {
                                    require_once __DIR__ . '/config/database.php';
                                    $pdo = getPDOConnection();
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM conflitti_orario WHERE risolto = 0");
                                    $result = $stmt->fetch();
                                    echo $result['total'];
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <div class="text-sm">
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Risolvi conflitti
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sezione azioni rapide -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Azioni Rapide</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="<?php echo BASE_URL; ?>/pages/orari_slot.php?action=new" 
               class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-150">
                <i class="fas fa-plus-circle text-blue-500 mr-3 text-xl"></i>
                <span class="text-gray-700 font-medium">Nuova Lezione</span>
            </a>
            
            <a href="<?php echo BASE_URL; ?>/pages/docente_form.php?action=new" 
               class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-150">
                <i class="fas fa-user-plus text-green-500 mr-3 text-xl"></i>
                <span class="text-gray-700 font-medium">Aggiungi Docente</span>
            </a>
            
            <a href="<?php echo BASE_URL; ?>/dashboard.php" 
               class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-150">
                <i class="fas fa-chart-bar text-purple-500 mr-3 text-xl"></i>
                <span class="text-gray-700 font-medium">Genera Report</span>
            </a>
        </div>
    </div>
</div>

<!-- Font Awesome per le icone -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<?php include_once __DIR__ . '/includes/footer.php'; ?>