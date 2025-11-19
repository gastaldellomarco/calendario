<?php
session_start();
// Redirect se già loggato
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

// Genera token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Gestione Scuola</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-lg">
        <!-- Logo e Titolo -->
        <div class="text-center">
            <div class="mx-auto h-16 w-16 bg-blue-600 rounded-full flex items-center justify-center">
                <i class="fas fa-graduation-cap text-white text-2xl"></i>
            </div>
            <h2 class="mt-6 text-3xl font-bold text-gray-900">Accedi al Sistema</h2>
            <p class="mt-2 text-sm text-gray-600">Inserisci le tue credenziali per accedere</p>
        </div>

        <!-- Form Login -->
        <form class="mt-8 space-y-6" id="loginForm" method="POST" action="process_login.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="space-y-4">
                <!-- Campo Username/Email -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">
                        Username o Email
                    </label>
                    <div class="mt-1 relative">
                        <input 
                            id="username" 
                            name="username" 
                            type="text" 
                            required 
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                            placeholder="Inserisci username o email"
                            value="" 
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                    </div>
                    <div id="username-error" class="text-red-600 text-sm mt-1 hidden"></div>
                </div>

                <!-- Campo Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <div class="mt-1 relative">
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            required 
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                            placeholder="Inserisci la password"
                            value="" 
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div id="password-error" class="text-red-600 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <!-- Opzioni Remember e Forgot Password -->
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input 
                        id="remember_me" 
                        name="remember_me" 
                        type="checkbox" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    >
                    <label for="remember_me" class="ml-2 block text-sm text-gray-900">
                        Ricordami
                    </label>
                </div>

                <div class="text-sm">
                    <a href="forgot_password.php" class="font-medium text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out">
                        Password dimenticata?
                    </a>
                </div>
            </div>

            <!-- Messaggi di Errore/Successo -->
            <div id="message-container" class="hidden p-4 rounded-md"></div>

            <!-- Bottone Submit -->
            <div>
                <button 
                    type="submit" 
                    id="submit-btn"
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span id="submit-text">Accedi</span>
                    <span id="submit-loading" class="hidden">
                        <i class="fas fa-spinner fa-spin ml-2"></i>
                    </span>
                </button>
            </div>

            <!-- Link Registrazione (solo per test) -->
            <div class="text-center">
                <span class="text-sm text-gray-600">Solo per testing:</span>
                <a href="register.php" class="text-sm font-medium text-blue-600 hover:text-blue-500 ml-1 transition duration-150 ease-in-out">
                    Registra nuovo utente
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const submitBtn = document.getElementById('submit-btn');
            const submitText = document.getElementById('submit-text');
            const submitLoading = document.getElementById('submit-loading');
            const messageContainer = document.getElementById('message-container');

            // Toggle visibilità password
            togglePasswordBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Validazione client-side
            function validateForm() {
                let isValid = true;
                const username = usernameInput.value.trim();
                const password = passwordInput.value.trim();

                // Reset errori
                document.getElementById('username-error').classList.add('hidden');
                document.getElementById('password-error').classList.add('hidden');

                // Validazione username
                if (!username) {
                    showError('username-error', 'Inserisci username o email');
                    isValid = false;
                }

                // Validazione password
                if (!password) {
                    showError('password-error', 'Inserisci la password');
                    isValid = false;
                } else if (password.length < 6) {
                    showError('password-error', 'La password deve essere di almeno 6 caratteri');
                    isValid = false;
                }

                return isValid;
            }

            function showError(elementId, message) {
                const element = document.getElementById(elementId);
                element.textContent = message;
                element.classList.remove('hidden');
            }

            function showMessage(message, type = 'error') {
                messageContainer.className = `p-4 rounded-md ${
                    type === 'error' ? 'bg-red-100 text-red-700 border border-red-300' : 
                    type === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 
                    'bg-blue-100 text-blue-700 border border-blue-300'
                }`;
                messageContainer.textContent = message;
                messageContainer.classList.remove('hidden');
            }

            // Submit form
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                if (!validateForm()) {
                    return;
                }

                // Disabilita bottone e mostra loading
                submitBtn.disabled = true;
                submitText.textContent = 'Accesso in corso...';
                submitLoading.classList.remove('hidden');

                try {
                    const formData = new FormData(form);
                    
                    const response = await fetch('process_login.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        showMessage('Accesso effettuato con successo! Redirect...', 'success');
                        setTimeout(() => {
                            window.location.href = result.redirect;
                        }, 1000);
                    } else {
                        showMessage(result.message || 'Errore durante il login');
                        submitBtn.disabled = false;
                        submitText.textContent = 'Accedi';
                        submitLoading.classList.add('hidden');
                    }
                } catch (error) {
                    showMessage('Errore di connessione. Riprova.');
                    submitBtn.disabled = false;
                    submitText.textContent = 'Accedi';
                    submitLoading.classList.add('hidden');
                }
            });

            // Validazione real-time
            usernameInput.addEventListener('blur', validateForm);
            passwordInput.addEventListener('blur', validateForm);
        });
    </script>
</body>
</html>