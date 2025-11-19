// assets/js/materie.js

class GestioneMaterie {
    constructor() {
        this.initEventListeners();
    }

    initEventListeners() {
        // Filtri
        document.addEventListener('change', this.handleFilterChanges.bind(this));
        
        // Gestione modali
        document.addEventListener('click', this.handleModalActions.bind(this));
        
        // Validazione form
        document.addEventListener('submit', this.handleFormSubmit.bind(this));
    }

    handleFilterChanges(e) {
        const target = e.target;
        
        // Filtro percorso -> aggiorna anni disponibili
        if (target.name === 'percorso_formativo_id') {
            this.aggiornaFiltroAnni(target.value);
        }
    }

    async aggiornaFiltroAnni(percorsoId) {
        if (!percorsoId) return;

        try {
            const response = await fetch(`api/classi_api.php?action=get_percorso&id=${percorsoId}`);
            const percorso = await response.json();

            const selectAnni = document.querySelector('select[name="anno_corso"]');
            if (selectAnni && percorso.durata_anni) {
                selectAnni.innerHTML = '<option value="">Tutti gli anni</option>';
                
                for (let i = 1; i <= percorso.durata_anni; i++) {
                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = `${i}° Anno`;
                    selectAnni.appendChild(option);
                }
            }
        } catch (error) {
            console.error('Errore nel caricamento durata percorso:', error);
        }
    }

    handleModalActions(e) {
        if (e.target.classList.contains('btn-elimina-materia')) {
            this.confermaEliminazioneMateria(e);
        }
        
        if (e.target.classList.contains('btn-modifica-materia')) {
            this.modificaMateria(e);
        }
    }

    confermaEliminazioneMateria(e) {
        const materiaId = e.target.dataset.id;
        const materiaNome = e.target.dataset.nome;

        if (confirm(`Sei sicuro di voler eliminare la materia "${materiaNome}"?`)) {
            this.eliminaMateria(materiaId);
        }
    }

    async eliminaMateria(materiaId) {
        try {
            const response = await fetch('api/materie_api.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: materiaId })
            });

            const result = await response.json();

            if (result.success) {
                this.mostraMessaggio('Materia eliminata con successo', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                this.mostraMessaggio(result.message, 'error');
            }
        } catch (error) {
            console.error('Errore:', error);
            this.mostraMessaggio('Errore durante l\'eliminazione', 'error');
        }
    }

    async modificaMateria(e) {
        const materiaId = e.target.dataset.id;
        
        try {
            const response = await fetch(`api/materie_api.php?action=get&id=${materiaId}`);
            const materia = await response.json();

            this.popolaFormModifica(materia);
            this.apriModalModifica();
        } catch (error) {
            console.error('Errore:', error);
            this.mostraMessaggio('Errore nel caricamento materia', 'error');
        }
    }

    popolaFormModifica(materia) {
        const form = document.getElementById('form-modifica-materia');
        if (!form) return;

        Object.keys(materia).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = Boolean(materia[key]);
                } else {
                    input.value = materia[key] || '';
                }
            }
        });
    }

    apriModalModifica() {
        const modal = document.getElementById('modal-modifica-materia');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    chiudiModalModifica() {
        const modal = document.getElementById('modal-modifica-materia');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    handleFormSubmit(e) {
        if (e.target.classList.contains('materia-form')) {
            e.preventDefault();
            this.salvaMateria(e.target);
        }
    }

    async salvaMateria(form) {
        const formData = new FormData(form);
        const dati = Object.fromEntries(formData);

        // Validazione
        if (!this.validaDatiMateria(dati)) {
            return;
        }

        try {
            const isEdit = !!dati.id;
            const url = 'api/materie_api.php';
            const method = 'POST';

            // Calcola ore annuali (33 settimane * ore settimanali)
            dati.ore_annuali = 33 * (dati.ore_settimanali || 2);

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: isEdit ? 'update' : 'create',
                    ...dati
                })
            });

            const result = await response.json();

            if (result.success) {
                this.mostraMessaggio(
                    isEdit ? 'Materia aggiornata con successo' : 'Materia creata con successo', 
                    'success'
                );
                
                if (isEdit) {
                    this.chiudiModalModifica();
                }
                
                setTimeout(() => {
                    window.location.href = 'materie.php';
                }, 1000);
            } else {
                this.mostraMessaggio(result.message, 'error');
            }
        } catch (error) {
            console.error('Errore:', error);
            this.mostraMessaggio('Errore durante il salvataggio', 'error');
        }
    }

    validaDatiMateria(dati) {
        const errori = [];

        if (!dati.nome?.trim()) {
            errori.push('Il nome della materia è obbligatorio');
        }

        if (!dati.codice?.trim()) {
            errori.push('Il codice della materia è obbligatorio');
        }

        if (!dati.percorso_formativo_id) {
            errori.push('Il percorso formativo è obbligatorio');
        }

        if (!dati.anno_corso) {
            errori.push('L\'anno corso è obbligatorio');
        }

        if (!dati.tipo) {
            errori.push('Il tipo di materia è obbligatorio');
        }

        if (errori.length > 0) {
            this.mostraMessaggio(errori.join('<br>'), 'error');
            return false;
        }

        return true;
    }

    mostraMessaggio(messaggio, tipo) {
        // Rimuovi messaggi esistenti
        const messaggiEsistenti = document.querySelectorAll('.alert-message');
        messaggiEsistenti.forEach(msg => msg.remove());

        // Crea nuovo messaggio
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert-message fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 ${
            tipo === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
        }`;
        alertDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                <span>${messaggio}</span>
            </div>
        `;

        document.body.appendChild(alertDiv);

        // Rimuovi dopo 5 secondi
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    // Gestione ricerca live
    initRicercaLive() {
        const inputRicerca = document.querySelector('input[name="ricerca"]');
        if (inputRicerca) {
            let timeout;
            inputRicerca.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.filtraMaterieLive(e.target.value);
                }, 300);
            });
        }
    }

    async filtraMaterieLive(testo) {
        try {
            const response = await fetch(`api/materie_api.php?action=search&q=${encodeURIComponent(testo)}`);
            const materie = await response.json();
            
            this.aggiornaListaMaterie(materie);
        } catch (error) {
            console.error('Errore nella ricerca:', error);
        }
    }

    aggiornaListaMaterie(materie) {
        const tbody = document.querySelector('table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (materie.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                        Nessuna materia trovata
                    </td>
                </tr>
            `;
            return;
        }

        materie.forEach(materia => {
            const row = this.creaRigaMateria(materia);
            tbody.appendChild(row);
        });
    }

    creaRigaMateria(materia) {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="font-medium text-gray-900">${materia.nome}</div>
                ${materia.descrizione ? `<div class="text-sm text-gray-500 truncate max-w-xs">${materia.descrizione}</div>` : ''}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${materia.codice}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${materia.percorso_nome || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${materia.anno_corso}° anno</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getColoreTipo(materia.tipo)}">
                    ${this.formattaTipo(materia.tipo)}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${materia.ore_settimanali}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${materia.richiede_laboratorio ? 'Sì' : 'No'}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${materia.attiva ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                    ${materia.attiva ? 'Attiva' : 'Inattiva'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                    <button class="btn-modifica-materia text-blue-600 hover:text-blue-900" 
                            data-id="${materia.id}" title="Modifica">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-elimina-materia text-red-600 hover:text-red-900" 
                            data-id="${materia.id}" data-nome="${materia.nome}" title="Elimina">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;

        return row;
    }

    getColoreTipo(tipo) {
        const colori = {
            'culturale': 'bg-blue-100 text-blue-800',
            'professionale': 'bg-green-100 text-green-800',
            'laboratoriale': 'bg-purple-100 text-purple-800',
            'stage': 'bg-yellow-100 text-yellow-800',
            'sostegno': 'bg-red-100 text-red-800'
        };
        
        return colori[tipo] || 'bg-gray-100 text-gray-800';
    }

    formattaTipo(tipo) {
        const tipi = {
            'culturale': 'Culturale',
            'professionale': 'Professionale',
            'laboratoriale': 'Laboratoriale',
            'stage': 'Stage',
            'sostegno': 'Sostegno'
        };
        
        return tipi[tipo] || tipo;
    }
}

// Inizializzazione quando il DOM è pronto
document.addEventListener('DOMContentLoaded', function() {
    window.gestioneMaterie = new GestioneMaterie();
    window.gestioneMaterie.initRicercaLive();
});

// Utility functions per materie
function calcolaOreAnnuali(oreSettimanali) {
    return oreSettimanali * 33; // 33 settimane scolastiche
}

function toggleCampiLaboratorio() {
    const tipoSelect = document.querySelector('select[name="tipo"]');
    const labContainer = document.getElementById('laboratorio-container');
    
    if (tipoSelect && labContainer) {
        const mostraLab = tipoSelect.value === 'laboratoriale';
        labContainer.style.display = mostraLab ? 'block' : 'none';
    }
}