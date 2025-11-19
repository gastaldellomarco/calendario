<?php
require_once '../../config/config.php';
requireAuth('preside');

$pageTitle = "Gestione Utenti";
include '../../includes/header.php';

// Gestione azioni
if ($_POST['action'] ?? '' === 'save_user') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    requireCsrfToken($csrf_token);
    
    $id = $_POST['id'] ?? 0;
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $ruolo = sanitizeInput($_POST['ruolo']);
    $docente_id = $_POST['docente_id'] ?? null;
    $attivo = isset($_POST['attivo']) ? 1 : 0;
    
    if ($id) {
        // Update
        $params = [$username, $email, $ruolo, $docente_id, $attivo, $id];
        Database::query("UPDATE utenti SET username=?, email=?, ruolo=?, docente_id=?, attivo=? WHERE id=?", $params);
        logActivity('update', 'utenti', $id, "Utente aggiornato: $username");
    } else {
        // Create
        $password = $_POST['password'];
        if (strlen($password) < 8) {
            $error = "La password deve essere di almeno 8 caratteri";
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $params = [$username, $email, $password_hash, $ruolo, $docente_id, $attivo];
            Database::query("INSERT INTO utenti (username, email, password_hash, ruolo, docente_id, attivo) VALUES (?, ?, ?, ?, ?, ?)", $params);
            $newId = Database::lastInsertId();
            logActivity('insert', 'utenti', $newId, "Nuovo utente creato: $username");
        }
    }
}

if ($_GET['action'] ?? '' === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user = Database::queryOne("SELECT username FROM utenti WHERE id = ?", [$id]);
    if ($user && $user['username'] !== 'admin') {
        Database::query("DELETE FROM utenti WHERE id = ?", [$id]);
        logActivity('delete', 'utenti', $id, "Utente eliminato: {$user['username']}");
    }
}

if ($_GET['action'] ?? '' === 'reset_password' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $temp_password = bin2hex(random_bytes(4));
    $password_hash = password_hash($temp_password, PASSWORD_BCRYPT);
    
    Database::query("UPDATE utenti SET password_hash = ? WHERE id = ?", [$password_hash, $id]);
    logActivity('update', 'utenti', $id, "Password resettata per utente ID: $id");
    
    $success = "Password temporanea generata: <strong>$temp_password</strong>. Comunicala all'utente.";
}

// Lista utenti
$users = Database::queryAll("
    SELECT u.*, d.nome as docente_nome, d.cognome as docente_cognome 
    FROM utenti u 
    LEFT JOIN docenti d ON u.docente_id = d.id 
    ORDER BY u.created_at DESC
");

// Lista docenti per dropdown
$docenti = Database::queryAll("SELECT id, nome, cognome FROM docenti WHERE stato = 'attivo' ORDER BY cognome, nome");
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Gestione Utenti</h1>
            <p class="text-gray-600">Gestisci gli utenti del sistema</p>
        </div>
        <button onclick="openUserModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Nuovo Utente
        </button>
    </div>

    <!-- Messaggi -->
    <?php if (isset($error)): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <!-- Tabella Utenti -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ruolo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Docente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ultimo Accesso</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($user['email']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                            <?php echo htmlspecialchars($user['ruolo']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php if ($user['docente_nome']): ?>
                            <?php echo htmlspecialchars($user['docente_cognome'] . ' ' . $user['docente_nome']); ?>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo $user['ultimo_accesso'] ? date('d/m/Y H:i', strtotime($user['ultimo_accesso'])) : 'Mai'; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 py-1 <?php echo $user['attivo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> rounded-full text-xs">
                            <?php echo $user['attivo'] ? 'Attivo' : 'Disattivo'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <button onclick="editUser(<?php echo $user['id']; ?>)" class="text-blue-600 hover:text-blue-900">Modifica</button>
                        <button onclick="resetPassword(<?php echo $user['id']; ?>)" class="text-yellow-600 hover:text-yellow-900">Reset Password</button>
                        <?php if ($user['username'] !== 'admin'): ?>
                        <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="text-red-600 hover:text-red-900">Elimina</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Utente -->
<div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Nuovo Utente</h3>
            
            <form id="userForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" id="userId" value="0">
                
                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username *</label>
                        <input type="text" id="username" name="username" required class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                        <input type="email" id="email" name="email" required class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div id="passwordField">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password *</label>
                        <input type="password" id="password" name="password" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Minimo 8 caratteri</p>
                    </div>
                    
                    <div>
                        <label for="ruolo" class="block text-sm font-medium text-gray-700">Ruolo *</label>
                        <select id="ruolo" name="ruolo" required class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="docente">Docente</option>
                            <option value="segreteria">Segreteria</option>
                            <option value="vice_preside">Vice Preside</option>
                            <option value="preside">Preside</option>
                            <option value="amministratore">Amministratore</option>
                        </select>
                    </div>
                    
                    <div id="docenteField">
                        <label for="docente_id" class="block text-sm font-medium text-gray-700">Collega a Docente</label>
                        <select id="docente_id" name="docente_id" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Nessun docente</option>
                            <?php foreach ($docenti as $docente): ?>
                            <option value="<?php echo $docente['id']; ?>">
                                <?php echo htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="attivo" name="attivo" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="attivo" class="ml-2 block text-sm text-gray-900">Utente attivo</label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeUserModal()" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                        Annulla
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                        Salva
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUserModal() {
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Nuovo Utente';
    document.getElementById('userId').value = '0';
    document.getElementById('userForm').reset();
    document.getElementById('passwordField').style.display = 'block';
    document.getElementById('password').required = true;
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
}

function editUser(id) {
    fetch(`../../api/admin_api.php?action=get_user&id=${id}`)
        .then(response => response.json())
        .then(user => {
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Modifica Utente';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('ruolo').value = user.ruolo;
            document.getElementById('docente_id').value = user.docente_id || '';
            document.getElementById('attivo').checked = user.attivo;
            
            // Nascondi campo password per modifica
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('password').required = false;
        });
}

function resetPassword(id) {
    if (confirm('Vuoi resettare la password per questo utente?')) {
        window.location.href = `?action=reset_password&id=${id}`;
    }
}

function deleteUser(id) {
    if (confirm('Sei sicuro di voler eliminare questo utente?')) {
        window.location.href = `?action=delete&id=${id}`;
    }
}

// Mostra/nascondi campo docente in base al ruolo
document.getElementById('ruolo').addEventListener('change', function() {
    const docenteField = document.getElementById('docenteField');
    docenteField.style.display = this.value === 'docente' ? 'block' : 'none';
});
</script>

<?php include '../../includes/footer.php'; ?>