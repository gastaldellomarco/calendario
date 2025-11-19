    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-auto">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-center md:text-left mb-4 md:mb-0">
                    <p class="text-sm text-gray-600">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tutti i diritti riservati.
                    </p>
                </div>
                
                <div class="flex items-center space-x-6">
                    <span class="text-xs text-gray-500">
                        Versione: <?php echo VERSION; ?>
                    </span>
                    
                    <div class="flex space-x-4">
                        <a href="<?php echo BASE_URL; ?>/privacy.php" class="text-xs text-gray-500 hover:text-gray-700">
                            Privacy
                        </a>
                        <a href="<?php echo BASE_URL; ?>/terms.php" class="text-xs text-gray-500 hover:text-gray-700">
                            Termini
                        </a>
                        <a href="<?php echo BASE_URL; ?>/help.php" class="text-xs text-gray-500 hover:text-gray-700">
                            Aiuto
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Gestione notifiche
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                type === 'warning' ? 'bg-yellow-500 text-black' :
                'bg-blue-500 text-white'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Conferma azioni distruttive
        function confirmAction(message) {
            return confirm(message || 'Sei sicuro di voler procedere?');
        }

        // Gestione caricamento
        function showLoading() {
            const loader = document.createElement('div');
            loader.id = 'global-loader';
            loader.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            loader.innerHTML = `
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                    <p class="mt-2 text-gray-600">Caricamento...</p>
                </div>
            `;
            document.body.appendChild(loader);
        }

        function hideLoading() {
            const loader = document.getElementById('global-loader');
            if (loader) {
                loader.remove();
            }
        }

        // Inizializzazione
        document.addEventListener('DOMContentLoaded', function() {
            // Aggiungi qui eventuali inizializzazioni JavaScript
            console.log('<?php echo SITE_NAME; ?> v<?php echo VERSION; ?> caricato');
        });
    </script>
</body>
</html>