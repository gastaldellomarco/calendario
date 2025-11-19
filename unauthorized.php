<?php
/**
 * Pagina di errore per accesso non autorizzato
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = "Accesso Non Autorizzato";
include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-16">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="mb-6">
            <i class="fas fa-lock text-red-500 text-6xl"></i>
        </div>
        
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Accesso Non Autorizzato</h1>
        
        <p class="text-gray-600 mb-6">
            Non hai i permessi necessari per accedere a questa pagina.
        </p>
        
        <?php if (isset($_SESSION['ruolo'])): ?>
            <p class="text-sm text-gray-500 mb-6">
                Il tuo ruolo attuale: <span class="font-semibold"><?php echo htmlspecialchars(getRoleDisplayName($_SESSION['ruolo'])); ?></span>
            </p>
        <?php endif; ?>
        
        <div class="space-y-3">
            <a href="<?php echo BASE_URL; ?>/index.php" 
               class="block w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-150">
                <i class="fas fa-home mr-2"></i>
                Torna alla Dashboard
            </a>
            
            <a href="<?php echo BASE_URL; ?>/dashboard.php" 
               class="block w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-150">
                <i class="fas fa-arrow-left mr-2"></i>
                Indietro
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

