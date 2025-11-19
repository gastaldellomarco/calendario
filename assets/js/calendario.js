// Gestione base del calendario - versione semplificata
document.addEventListener('DOMContentLoaded', function() {
    // Gestione click sulle card lezione
    document.addEventListener('click', function(e) {
        const lezioneCard = e.target.closest('.lezione-card');
        if (lezioneCard) {
            const lezioneId = lezioneCard.getAttribute('data-lezione-id');
            if (lezioneId) {
                mostraDettaglioLezione(lezioneId);
            }
        }
        
        // Gestione aggiunta lezione
        const addBtn = e.target.closest('.add-lezione-btn');
        if (addBtn) {
            const date = addBtn.getAttribute('data-date');
            const slot = addBtn.getAttribute('data-slot');
            apriModaleNuovaLezione(date, slot);
        }
    });
});

function mostraDettaglioLezione(lezioneId) {
    fetch(`../api/calendario_api.php?action=get_lezione&lezione_id=${lezioneId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lezione = data.data;
                const modalContent = document.getElementById('modalContent');
                
                modalContent.innerHTML = `
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="font-semibold text-gray-700">Classe:</label>
                                <p>${escapeHtml(lezione.classe_nome)}</p>
                            </div>
                            <div>
                                <label class="font-semibold text-gray-700">Materia:</label>
                                <p>${escapeHtml(lezione.materia_nome)}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="font-semibold text-gray-700">Docente:</label>
                                <p>${escapeHtml(lezione.docente_nome)}</p>
                            </div>
                            <div>
                                <label class="font-semibold text-gray-700">Aula:</label>
                                <p>${lezione.aula_nome ? escapeHtml(lezione.aula_nome) : 'Nessuna'}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="font-semibold text-gray-700">Data e Ora:</label>
                                <p>${formatDate(lezione.data_lezione)} ${lezione.ora_inizio.substring(0, 5)} - ${lezione.ora_fine.substring(0, 5)}</p>
                            </div>
                            <div>
                                <label class="font-semibold text-gray-700">Stato:</label>
                                <span class="px-2 py-1 rounded-full text-xs ${getStatoColor(lezione.stato)}">
                                    ${lezione.stato}
                                </span>
                            </div>
                        </div>
                        ${lezione.argomento ? `
                            <div>
                                <label class="font-semibold text-gray-700">Argomento:</label>
                                <p class="mt-1">${escapeHtml(lezione.argomento)}</p>
                            </div>
                        ` : ''}
                        ${lezione.note ? `
                            <div>
                                <label class="font-semibold text-gray-700">Note:</label>
                                <p class="mt-1">${escapeHtml(lezione.note)}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
                
                // Imposta i pulsanti di azione
                document.getElementById('editLezione').onclick = () => {
                    window.location.href = `calendario_modifica.php?lezione_id=${lezioneId}`;
                };
                
                document.getElementById('deleteLezione').onclick = () => {
                    if (confirm('Sei sicuro di voler eliminare questa lezione?')) {
                        eliminaLezione(lezioneId);
                    }
                };
                
                // Mostra modale
                document.getElementById('lezioneModal').classList.remove('hidden');
            } else {
                alert('Errore nel caricamento della lezione: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Errore di connessione');
        });
}

function apriModaleNuovaLezione(date, slot) {
    // Reindirizza alla pagina di modifica
    window.location.href = `calendario_modifica.php?date=${date}&slot=${slot}`;
}

function eliminaLezione(lezioneId) {
    fetch('../api/calendario_api.php?action=delete_lezione', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ lezione_id: lezioneId })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Lezione eliminata con successo');
            document.getElementById('lezioneModal').classList.add('hidden');
            location.reload(); // Ricarica la pagina
        } else {
            alert('Errore nell\'eliminazione: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione');
    });
}

// Funzioni utility
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('it-IT');
}

function getStatoColor(stato) {
    const colors = {
        'pianificata': 'bg-blue-100 text-blue-800',
        'confermata': 'bg-green-100 text-green-800',
        'svolta': 'bg-purple-100 text-purple-800',
        'cancellata': 'bg-red-100 text-red-800',
        'sostituita': 'bg-orange-100 text-orange-800'
    };
    return colors[stato] || 'bg-gray-100 text-gray-800';
}