// Variabili globali
let currentModal = null;

// Funzioni principali
function openDocenteForm(docenteId = 0) {
    const url = docenteId > 0 
        ? `../pages/docente_form.php?id=${docenteId}`
        : '../pages/docente_form.php';
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Errore nel caricamento del form');
            }
            return response.text();
        })
        .then(html => {
            showModal(html);
        })
        .catch(error => {
            console.error('Errore:', error);
            showToast('Errore nel caricamento del form', 'error');
        });
}

function openVincoli(docenteId) {
    fetch(`../pages/vincoli_docente.php?docente_id=${docenteId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Errore nel caricamento dei vincoli');
            }
            return response.text();
        })
        .then(html => {
            showModal(html);
            // Trigger event per inizializzare gli event listener dei vincoli
            setTimeout(initVincoliEvents, 100);
        })
        .catch(error => {
            console.error('Errore:', error);
            showToast('Errore nel caricamento dei vincoli', 'error');
        });
}

function openMaterie(docenteId) {
    fetch(`../pages/docente_materie.php?docente_id=${docenteId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Errore nel caricamento delle materie');
            }
            return response.text();
        })
        .then(html => {
            showModal(html);
        })
        .catch(error => {
            console.error('Errore:', error);
            showToast('Errore nel caricamento delle materie', 'error');
        });
}

function saveDocente() {
    const form = document.getElementById('docenteForm');
    if (!form) {
        showToast('Form non trovato', 'error');
        return;
    }

    const formData = new FormData(form);
    
    // Validazione frontend
    if (!validateDocenteForm(formData)) {
        return;
    }
    
    const isUpdate = formData.get('id') > 0;
    const url = `../api/docenti_api.php?action=${isUpdate ? 'update' : 'create'}`;
    
    // Mostra loading
    const submitBtn = form.querySelector('button[type="button"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Salvataggio...';
    submitBtn.disabled = true;
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Risposta API:', data);
        if (data.success) {
            showToast(data.message, 'success');
            closeModal();
            
            // Se è un nuovo docente, vai al form completo per aggiungere materie e vincoli
            const docenteId = data.id || formData.get('id');
            if (docenteId) {
                setTimeout(() => {
                    window.location.href = `../pages/docente_edit.php?id=${docenteId}`;
                }, 1000);
            } else {
                // Se è una modifica, ricarica solo la lista
                setTimeout(() => window.location.reload(), 1000);
            }
        } else {
            showToast(data.message, 'error');
            if (data.errors) {
                highlightErrors(data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Errore fetch:', error);
        showToast('Errore di connessione: ' + error.message, 'error');
    })
    .finally(() => {
        // Ripristina bottone
        if (submitBtn) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

function deleteDocente(docenteId, docenteNome) {
    if (!confirm(`Sei sicuro di voler eliminare il docente "${docenteNome}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('id', docenteId);
    
    fetch('../api/docenti_api.php?action=delete', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showToast('Errore di connessione', 'error');
    });
}

// Gestione Vincoli
function saveVincolo() {
    const form = document.getElementById('vincoloForm');
    if (!form) {
        showToast('Form vincoli non trovato', 'error');
        return;
    }

    const formData = new FormData(form);
    
    if (!validateVincoloForm(formData)) {
        return;
    }
    
    // Mostra loading
    const submitBtn = form.querySelector('button[type="button"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Salvataggio...';
    submitBtn.disabled = true;
    
    fetch('../api/vincoli_api.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Ricarica la pagina vincoli
            const docenteId = formData.get('docente_id');
            setTimeout(() => openVincoli(docenteId), 500);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showToast('Errore di connessione', 'error');
    })
    .finally(() => {
        // Ripristina bottone
        if (submitBtn) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

function deleteVincolo(vincoloId) {
    if (!confirm('Sei sicuro di voler eliminare questo vincolo?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('id', vincoloId);
    
    fetch('../api/vincoli_api.php?action=delete', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Ricarica la lista vincoli
            const docenteId = document.querySelector('input[name="docente_id"]').value;
            setTimeout(() => openVincoli(docenteId), 500);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showToast('Errore di connessione', 'error');
    });
}

// Gestione Materie
function saveMaterie() {
    const form = document.getElementById('materieForm');
    if (!form) {
        showToast('Form materie non trovato', 'error');
        return;
    }

    const formData = new FormData(form);
    
    // Raccoglie i dati delle materie in formato JSON
    const materieData = {};
    const checkboxes = form.querySelectorAll('input[type="checkbox"][name^="materie"]');
    
    checkboxes.forEach(checkbox => {
        const match = checkbox.name.match(/materie\[(\d+)\]\[abilitato\]/);
        if (match) {
            const materiaId = match[1];
            const preferenzaSelect = form.querySelector(`select[name="materie[${materiaId}][preferenza]"]`);
            
            materieData[materiaId] = {
                abilitato: checkbox.checked ? 1 : 0,
                preferenza: preferenzaSelect ? parseInt(preferenzaSelect.value) : 2
            };
        }
    });
    
    formData.append('materie_data', JSON.stringify(materieData));
    
    // Mostra loading
    const submitBtn = form.querySelector('button[type="button"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Salvataggio...';
    submitBtn.disabled = true;
    
    fetch('../api/materie_api.php?action=update_docente', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showToast('Errore di connessione', 'error');
    })
    .finally(() => {
        // Ripristina bottone
        if (submitBtn) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

// Import/Export
function openImportModal() {
    document.getElementById('importModal').classList.remove('hidden');
}

function closeImportModal() {
    document.getElementById('importModal').classList.add('hidden');
    // Reset form
    const form = document.getElementById('importForm');
    if (form) form.reset();
}

function importCSV() {
    const form = document.getElementById('importForm');
    if (!form) {
        showToast('Form import non trovato', 'error');
        return;
    }

    const fileInput = form.querySelector('input[type="file"]');
    if (!fileInput.files.length) {
        showToast('Seleziona un file CSV', 'error');
        return;
    }

    const formData = new FormData(form);
    
    // Mostra loading
    const submitBtn = form.querySelector('button[type="button"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Importazione...';
    submitBtn.disabled = true;
    
    fetch('../api/import_docenti.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeImportModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showToast('Errore di connessione', 'error');
    })
    .finally(() => {
        // Ripristina bottone
        if (submitBtn) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

function exportDocenti() {
    const searchParams = new URLSearchParams(window.location.search);
    window.open(`../api/export_docenti.php?${searchParams.toString()}`, '_blank');
}

// Validazioni
function validateDocenteForm(formData) {
    const cognome = formData.get('cognome')?.trim() || '';
    const nome = formData.get('nome')?.trim() || '';
    const email = formData.get('email')?.trim() || '';
    const codiceFiscale = formData.get('codice_fiscale')?.trim() || '';
    const sedeId = formData.get('sede_principale_id');
    
    // Reset errori precedenti
    clearErrors();
    
    let isValid = true;
    
    if (!cognome) {
        showFieldError('cognome', 'Il cognome è obbligatorio');
        isValid = false;
    }
    
    if (!nome) {
        showFieldError('nome', 'Il nome è obbligatorio');
        isValid = false;
    }
    
    if (email && !isValidEmail(email)) {
        showFieldError('email', 'Email non valida');
        isValid = false;
    }
    
    if (codiceFiscale && codiceFiscale.length !== 16) {
        showFieldError('codice_fiscale', 'Il codice fiscale deve essere di 16 caratteri');
        isValid = false;
    }
    
    if (!sedeId) {
        showFieldError('sede_principale_id', 'La sede principale è obbligatoria');
        isValid = false;
    }
    
    return isValid;
}

function validateVincoloForm(formData) {
    const tipo = formData.get('tipo');
    const giorno = formData.get('giorno_settimana');
    const oraInizio = formData.get('ora_inizio');
    const oraFine = formData.get('ora_fine');
    const sedeId = formData.get('sede_id');
    
    clearErrors();
    
    let isValid = true;
    
    if (!tipo) {
        showFieldError('tipo', 'Il tipo è obbligatorio');
        isValid = false;
    }
    
    if (!giorno) {
        showFieldError('giorno_settimana', 'Il giorno è obbligatorio');
        isValid = false;
    }
    
    if (oraInizio && oraFine && oraInizio >= oraFine) {
        showFieldError('ora_inizio', "L'ora di inizio deve essere precedente all'ora di fine");
        showFieldError('ora_fine', "L'ora di fine deve essere successiva all'ora di inizio");
        isValid = false;
    }
    
    if (tipo === 'doppia_sede' && !sedeId) {
        showFieldError('sede_id', 'La sede è obbligatoria per vincoli di doppia sede');
        isValid = false;
    }
    
    return isValid;
}

// Utility functions
function showModal(content) {
    // Rimuovi modale esistente
    if (currentModal) {
        document.body.removeChild(currentModal);
    }
    
    currentModal = document.createElement('div');
    currentModal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 z-50 flex items-center justify-center p-4';
    currentModal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            ${content}
        </div>
    `;
    
    document.body.appendChild(currentModal);
    
    // Chiudi modale cliccando fuori
    currentModal.addEventListener('click', (e) => {
        if (e.target === currentModal) {
            closeModal();
        }
    });
    
    // Chiudi con ESC
    document.addEventListener('keydown', handleEscKey);
}

function closeModal() {
    if (currentModal) {
        document.body.removeChild(currentModal);
        currentModal = null;
    }
    document.removeEventListener('keydown', handleEscKey);
}

function handleEscKey(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
}

function showToast(message, type = 'info') {
    // Rimuovi toast esistenti
    const existingToasts = document.querySelectorAll('.toast-message');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : 
                   type === 'error' ? 'bg-red-500' : 
                   type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
    
    toast.className = `toast-message fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-transform duration-300 translate-x-full`;
    toast.textContent = message;
    
    // Icona in base al tipo
    const icon = type === 'success' ? 'fa-check-circle' :
                 type === 'error' ? 'fa-exclamation-circle' :
                 type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icon} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animazione entrata
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    // Rimuovi dopo 5 secondi
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            if (toast.parentNode) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 5000);
}

function showFieldError(fieldName, message) {
    const field = document.getElementById(fieldName);
    if (field) {
        field.classList.add('border-red-500', 'focus:ring-red-500');
        
        let errorElement = field.parentNode.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('p');
            errorElement.className = 'field-error text-red-500 text-xs mt-1';
            field.parentNode.appendChild(errorElement);
        }
        errorElement.textContent = message;
    }
}

function clearErrors() {
    // Rimuovi highlight errori
    document.querySelectorAll('.border-red-500').forEach(el => {
        el.classList.remove('border-red-500', 'focus:ring-red-500');
    });
    
    // Rimuovi messaggi errore
    document.querySelectorAll('.field-error').forEach(el => {
        el.remove();
    });
}

function highlightErrors(errors) {
    clearErrors();
    if (Array.isArray(errors)) {
        errors.forEach(error => {
            // Cerca il campo corrispondente all'errore
            if (error.includes('cognome')) {
                showFieldError('cognome', error);
            } else if (error.includes('nome')) {
                showFieldError('nome', error);
            } else if (error.includes('email')) {
                showFieldError('email', error);
            } else if (error.includes('codice fiscale')) {
                showFieldError('codice_fiscale', error);
            } else if (error.includes('sede')) {
                showFieldError('sede_principale_id', error);
            }
        });
    }
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Gestione event listener per tipo vincolo
function initVincoliEvents() {
    const tipoSelect = document.getElementById('tipo');
    if (tipoSelect) {
        // Rimuovi event listener esistenti per evitare duplicati
        tipoSelect.replaceWith(tipoSelect.cloneNode(true));
        
        // Re-seleziona l'elemento dopo il clone
        const newTipoSelect = document.getElementById('tipo');
        newTipoSelect.addEventListener('change', function() {
            const sedeField = document.getElementById('sedeField');
            if (sedeField) {
                if (this.value === 'doppia_sede') {
                    sedeField.classList.remove('hidden');
                } else {
                    sedeField.classList.add('hidden');
                }
            }
        });
        
        // Inizializza lo stato iniziale
        const sedeField = document.getElementById('sedeField');
        if (sedeField) {
            if (newTipoSelect.value === 'doppia_sede') {
                sedeField.classList.remove('hidden');
            } else {
                sedeField.classList.add('hidden');
            }
        }
    }
}

// Gestione event listener per checkboxes materie
function initMaterieEvents() {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="materie"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const match = this.name.match(/materie\[(\d+)\]\[abilitato\]/);
            if (match) {
                const materiaId = match[1];
                const preferenzaSelect = document.querySelector(`select[name="materie[${materiaId}][preferenza]"]`);
                if (preferenzaSelect) {
                    preferenzaSelect.disabled = !this.checked;
                }
            }
        });
        
        // Inizializza stato iniziale
        const match = checkbox.name.match(/materie\[(\d+)\]\[abilitato\]/);
        if (match) {
            const materiaId = match[1];
            const preferenzaSelect = document.querySelector(`select[name="materie[${materiaId}][preferenza]"]`);
            if (preferenzaSelect) {
                preferenzaSelect.disabled = !checkbox.checked;
            }
        }
    });
}

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    console.log('Docenti JS caricato');
    
    // Gestione submit form ricerca
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const params = new URLSearchParams(formData);
            window.location.href = `?${params.toString()}`;
        });
    }
    
    // Gestione click sui pulsanti azione
    document.addEventListener('click', function(e) {
        // Gestione apertura modale docente
        if (e.target.closest('[onclick*="openDocenteForm"]')) {
            const button = e.target.closest('[onclick]');
            const match = button.getAttribute('onclick').match(/openDocenteForm\((\d+)\)/);
            if (match) {
                openDocenteForm(parseInt(match[1]));
            } else {
                openDocenteForm();
            }
        }
        
        // Gestione apertura vincoli
        if (e.target.closest('[onclick*="openVincoli"]')) {
            const button = e.target.closest('[onclick]');
            const match = button.getAttribute('onclick').match(/openVincoli\((\d+)\)/);
            if (match) {
                openVincoli(parseInt(match[1]));
            }
        }
        
        // Gestione apertura materie
        if (e.target.closest('[onclick*="openMaterie"]')) {
            const button = e.target.closest('[onclick]');
            const match = button.getAttribute('onclick').match(/openMaterie\((\d+)\)/);
            if (match) {
                openMaterie(parseInt(match[1]));
            }
        }
        
        // Gestione eliminazione docente
        if (e.target.closest('[onclick*="deleteDocente"]')) {
            const button = e.target.closest('[onclick]');
            const match = button.getAttribute('onclick').match(/deleteDocente\((\d+), '([^']+)'\)/);
            if (match) {
                deleteDocente(parseInt(match[1]), match[2]);
            }
        }
        
        // Gestione eliminazione vincolo
        if (e.target.closest('[onclick*="deleteVincolo"]')) {
            const button = e.target.closest('[onclick]');
            const match = button.getAttribute('onclick').match(/deleteVincolo\((\d+)\)/);
            if (match) {
                deleteVincolo(parseInt(match[1]));
            }
        }
    });
});

// Utility per debug
function debugFetch(url, options) {
    console.log('🔍 Fetch Debug:');
    console.log('URL:', url);
    console.log('Options:', options);
    
    return fetch(url, options)
        .then(response => {
            console.log('📡 Response:', {
                status: response.status,
                statusText: response.statusText,
                url: response.url,
                headers: Object.fromEntries(response.headers.entries())
            });
            return response;
        })
        .catch(error => {
            console.error('❌ Fetch Error:', error);
            throw error;
        });
}

// Export funzioni per uso globale
window.openDocenteForm = openDocenteForm;
window.openVincoli = openVincoli;
window.openMaterie = openMaterie;
window.saveDocente = saveDocente;
window.deleteDocente = deleteDocente;
window.saveVincolo = saveVincolo;
window.deleteVincolo = deleteVincolo;
window.saveMaterie = saveMaterie;
window.openImportModal = openImportModal;
window.closeImportModal = closeImportModal;
window.importCSV = importCSV;
window.exportDocenti = exportDocenti;
window.closeModal = closeModal;
window.initVincoliEvents = initVincoliEvents;
window.initMaterieEvents = initMaterieEvents;

console.log('✅ Docenti JS loaded successfully');