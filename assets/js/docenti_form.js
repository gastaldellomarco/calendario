// Variabile globale per il docente corrente
let currentDocenteId = 0;

// Salva docente da pagina docente_edit.php
function saveDocentePage() {
    const form = document.getElementById('docenteForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    const action = data.id ? 'update' : 'create';
    const url = `../api/docenti_api.php?action=${action}`;
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            currentDocenteId = result.id || data.id;
            console.log('Docente salvato con ID:', currentDocenteId);
            
            // Abilita i bottoni per materie e vincoli
            const btnMaterie = document.querySelector('[onclick="openMaterie()"]');
            const btnVincoli = document.querySelector('[onclick="openVincoli()"]');
            if (btnMaterie) btnMaterie.disabled = false;
            if (btnVincoli) btnVincoli.disabled = false;
            
            alert('✅ Docente salvato con successo!');
            // Reload pagina padre
            if (window.parent && window.parent !== window) {
                window.parent.location.reload();
            } else {
                location.reload();
            }
        } else {
            alert('❌ Errore: ' + (result.message || 'Errore sconosciuto'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('❌ Errore nel salvataggio');
    });
}

// Apri modale materie
function openMaterie() {
    if (!currentDocenteId && !document.getElementById('docenteForm').id.value) {
        alert('Salva prima il docente');
        return;
    }
    
    // Recupera ID dal form se non ancora salvato
    if (!currentDocenteId) {
        currentDocenteId = document.getElementById('docenteForm').querySelector('[name="id"]').value;
    }
    
    const modal = document.getElementById('materieModal');
    if (modal) modal.style.display = 'flex';
    
    loadMaterie();
}

// Apri modale vincoli
function openVincoli() {
    if (!currentDocenteId && !document.getElementById('docenteForm').querySelector('[name="id"]').value) {
        alert('Salva prima il docente');
        return;
    }
    
    // Recupera ID dal form se non ancora salvato
    if (!currentDocenteId) {
        currentDocenteId = document.getElementById('docenteForm').querySelector('[name="id"]').value;
    }
    
    const modal = document.getElementById('vincoliModal');
    if (modal) modal.style.display = 'flex';
    
    loadVincoli();
}

// Carica materie assegnate
function loadMaterie() {
    fetch(`../api/docenti_api.php?action=get_materie&docente_id=${currentDocenteId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const tbody = document.getElementById('materieTableBody');
                if (!tbody) return;
                
                tbody.innerHTML = '';
                if (result.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-3 text-center text-gray-500">Nessuna materia assegnata</td></tr>';
                    return;
                }
                
                result.data.forEach(materia => {
                    const preferenzaLabel = materia.preferenza == 1 ? 'Alta' : (materia.preferenza == 2 ? 'Media' : 'Bassa');
                    const preferenzaColor = materia.preferenza == 1 ? 'bg-red-100 text-red-800' : 
                                           (materia.preferenza == 2 ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800');
                    
                    tbody.innerHTML += `
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">${materia.percorso_nome}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">${materia.anno_corso}°</td>
                            <td class="px-4 py-3 text-sm text-gray-900">${materia.materia_nome}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${preferenzaColor}">
                                    ${preferenzaLabel}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <button onclick="removeMateria(${materia.id})" class="text-red-600 hover:text-red-900" title="Rimuovi">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
        })
        .catch(error => {
            console.error('Errore nel caricamento materie:', error);
            alert('Errore nel caricamento delle materie');
        });
}

// Aggiungi materia
function addMateria() {
    const materiaId = document.getElementById('materiaSelect')?.value;
    const preferenza = document.getElementById('preferenzaSelect')?.value || '2';
    
    if (!materiaId) {
        alert('Seleziona una materia');
        return;
    }
    
    fetch('../api/docenti_api.php?action=assign_materia', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            docente_id: currentDocenteId,
            materia_id: materiaId,
            preferenza: preferenza
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Materia assegnata con successo!');
            document.getElementById('materiaSelect').value = '';
            loadMaterie();
        } else {
            alert('Errore: ' + (result.message || 'Errore sconosciuto'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore nell\'assegnazione');
    });
}

// Rimuovi materia
function removeMateria(id) {
    if (!confirm('Sei sicuro di voler rimuovere questa materia?')) return;
    
    fetch('../api/docenti_api.php?action=remove_materia', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            loadMaterie();
        } else {
            alert('Errore: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore nella rimozione');
    });
}

// Carica vincoli
function loadVincoli() {
    fetch(`../api/docenti_api.php?action=get_vincoli&docente_id=${currentDocenteId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const tbody = document.getElementById('vincolitableBody');
                if (!tbody) return;
                
                const giorni = {1: 'Lun', 2: 'Mar', 3: 'Mer', 4: 'Gio', 5: 'Ven', 6: 'Sab', 7: 'Dom'};
                const tipi = {indisponibilita: 'Indisponibilità', preferenza: 'Preferenza', doppia_sede: 'Doppia Sede'};
                
                tbody.innerHTML = '';
                if (result.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-3 text-center text-gray-500">Nessun vincolo assegnato</td></tr>';
                    return;
                }
                
                result.data.forEach(vincolo => {
                    const statoColor = vincolo.attivo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                    const statoLabel = vincolo.attivo ? 'Attivo' : 'Inattivo';
                    const orari = vincolo.ora_inizio ? `${vincolo.ora_inizio.substring(0, 5)} - ${vincolo.ora_fine.substring(0, 5)}` : 'Tutto il giorno';
                    
                    tbody.innerHTML += `
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">${tipi[vincolo.tipo] || vincolo.tipo}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">${giorni[vincolo.giorno_settimana] || vincolo.giorno_settimana}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">${orari}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">${vincolo.sede_nome || '-'}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">${vincolo.motivo || '-'}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statoColor}">
                                    ${statoLabel}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <button onclick="deleteVincolo(${vincolo.id})" class="text-red-600 hover:text-red-900" title="Rimuovi">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
        })
        .catch(error => {
            console.error('Errore nel caricamento vincoli:', error);
            alert('Errore nel caricamento dei vincoli');
        });
}

// Aggiungi vincolo
function addVincolo() {
    const tipo = document.getElementById('tipoVincolo')?.value;
    const giorno = document.getElementById('giornoVincolo')?.value;
    const oraInizio = document.getElementById('oraInizioVincolo')?.value;
    const oraFine = document.getElementById('oraFineVincolo')?.value;
    const sedeId = document.getElementById('sedeVincolo')?.value;
    const motivo = document.getElementById('motivoVincolo')?.value;
    
    if (!tipo || !giorno) {
        alert('Compila i campi obbligatori (Tipo e Giorno)');
        return;
    }
    
    fetch('../api/docenti_api.php?action=add_vincolo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            docente_id: currentDocenteId,
            tipo: tipo,
            giorno_settimana: giorno,
            ora_inizio: oraInizio || null,
            ora_fine: oraFine || null,
            sede_id: sedeId || null,
            motivo: motivo || '',
            attivo: 1
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Vincolo aggiunto con successo!');
            // Resetta form
            document.getElementById('tipoVincolo').value = '';
            document.getElementById('giornoVincolo').value = '';
            document.getElementById('oraInizioVincolo').value = '';
            document.getElementById('oraFineVincolo').value = '';
            document.getElementById('sedeVincolo').value = '';
            document.getElementById('motivoVincolo').value = '';
            
            loadVincoli();
        } else {
            alert('Errore: ' + (result.message || 'Errore sconosciuto'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore nell\'aggiunta del vincolo');
    });
}

// Rimuovi vincolo
function deleteVincolo(id) {
    if (!confirm('Sei sicuro di voler rimuovere questo vincolo?')) return;
    
    fetch('../api/docenti_api.php?action=delete_vincolo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            loadVincoli();
        } else {
            alert('Errore: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore nella rimozione del vincolo');
    });
}

// Chiudi modale
function closeModal(modalId) {
    if (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
    }
}

// Chiudi modale premendo ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('materieModal');
        closeModal('vincoliModal');
    }
});

// Chiudi modale cliccando su overlay
document.addEventListener('DOMContentLoaded', function() {
    ['materieModal', 'vincoliModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(id);
                }
            });
        }
    });
});
