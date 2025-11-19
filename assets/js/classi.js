// assets/js/classi.js

class GestioneClassi {
    constructor() {
        this.initEventListeners();
    }

    initEventListeners() {
        // Filtri dinamici
        document.addEventListener('change', this.handleFilterChanges.bind(this));
        
        // Gestione modali
        document.addEventListener('click', this.handleModalActions.bind(this));
        
        // Validazione form
        document.addEventListener('submit', this.handleFormSubmit.bind(this));
    }

    handleFilterChanges(e) {
        const target = e.target;
        
        // Filtro sede -> aggiorna percorsi
        if (target.name === 'sede_id') {
            this.aggiornaPercorsi(target.value);
        }
        
        // Filtro percorso -> aggiorna materie
        if (target.name === 'percorso_formativo_id') {
            this.aggiornaMaterie(target.value);
        }
    }

    async aggiornaPercorsi(sedeId) {
        if (!sedeId) return;

        try {
            const response = await fetch(`api/classi_api.php?action=get_percorsi&sede_id=${sedeId}`);
            const percorsi = await response.json();

            const select = document.querySelector('select[name="percorso_formativo_id"]');
            if (select) {
                select.innerHTML = '<option value="">Seleziona percorso</option>';
                percorsi.forEach(percorso => {
                    const option = document.createElement('option');
                    option.value = percorso.id;
                    option.textContent = percorso.nome;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Errore nel caricamento percorsi:', error);
        }
    }

    async aggiornaMaterie(percorsoId) {
        if (!percorsoId) return;

        try {
            const annoCorso = document.querySelector('select[name="anno_corso"]').value;
            if (!annoCorso) return;

            const response = await fetch(`api/materie_api.php?action=get_by_percorso_anno&percorso_id=${percorsoId}&anno_corso=${annoCorso}`);
            const materie = await response.json();

            // Aggiorna interfaccia materie disponibili
            this.aggiornaListaMaterie(materie);
        } catch (error) {
            console.error('Errore nel caricamento materie:', error);
        }
    }

    aggiornaListaMaterie(materie) {
        const container = document.getElementById('materie-container');
        if (!container) return;

        container.innerHTML = '';

        materie.forEach(materia => {
            const card = this.creaCardMateria(materia);
            container.appendChild(card);
        });
    }

    creaCardMateria(materia) {
        const div = document.createElement('div');
        div.className = 'materia-card bg-white border rounded-lg p-4 mb-3';
        div.innerHTML = `
            <div class="flex justify-between items-center">
                <div>
                    <h4 class="font-medium">${materia.nome}</h4>
                    <span class="text-sm text-gray-600">${materia.tipo} - ${materia.ore_settimanali} ore/sett.</span>
                </div>
                <div class="flex items-center space-x-2">
                    <select name="docente_${materia.id}" class="docente-select border rounded px-2 py-1 text-sm">
                        <option value="">Seleziona docente</option>
                    </select>
                    <input type="number" name="ore_${materia.id}" value="${materia.ore_settimanali}" 
                           min="1" max="10" class="w-16 border rounded px-2 py-1 text-sm">
                    <button type="button" class="btn-rimuovi text-red-600 hover:text-red-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;

        this.caricaDocentiMateria(materia.id, div.querySelector('.docente-select'));
        return div;
    }

    async caricaDocentiMateria(materiaId, selectElement) {
        try {
            const response = await fetch(`api/materie_api.php?action=get_docenti_abilitati&materia_id=${materiaId}`);
            const docenti = await response.json();

            docenti.forEach(docente => {
                const option = document.createElement('option');
                option.value = docente.id;
                option.textContent = `${docente.cognome} ${docente.nome}`;
                selectElement.appendChild(option);
            });
        } catch (error) {
            console.error('Errore nel caricamento docenti:', error);
        }
    }

    handleModalActions(e) {
        if (e.target.classList.contains('btn-elimina-classe')) {
            this.confermaEliminazioneClasse(e);
        }
        
        if (e.target.classList.contains('btn-duplica-classe')) {
            this.duplicaClasse(e);
        }
    }

    confermaEliminazioneClasse(e) {
        const classeId = e.target.dataset.id;
        const classeNome = e.target.dataset.nome;

        if (confirm(`Sei sicuro di voler eliminare la classe "${classeNome}"?`)) {
            this.eliminaClasse(classeId);
        }
    }

    async eliminaClasse(classeId) {
        try {
            const response = await fetch('api/classi_api.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: classeId })
            });

            const result = await response.json();

            if (result.success) {
                this.mostraMessaggio('Classe eliminata con successo', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                this.mostraMessaggio(result.message, 'error');
            }
        } catch (error) {
            console.error('Errore:', error);
            this.mostraMessaggio('Errore durante l\'eliminazione', 'error');
        }
    }

    async duplicaClasse(e) {
        const classeId = e.target.dataset.id;
        
        try {
            const response = await fetch(`api/classi_api.php?action=duplica&id=${classeId}`);
            const result = await response.json();

            if (result.success) {
                this.mostraMessaggio('Classe duplicata con successo', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                this.mostraMessaggio(result.message, 'error');
            }
        } catch (error) {
            console.error('Errore:', error);
            this.mostraMessaggio('Errore durante la duplicazione', 'error');
        }
    }

    handleFormSubmit(e) {
        if (e.target.classList.contains('classe-form')) {
            e.preventDefault();
            this.salvaClasse(e.target);
        }
    }

    async salvaClasse(form) {
        const formData = new FormData(form);
        const dati = Object.fromEntries(formData);

        // Validazione
        if (!this.validaDatiClasse(dati)) {
            return;
        }

        try {
            const isEdit = !!dati.id;
            const url = 'api/classi_api.php';
            const method = 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: isEdit ? 'update_classe' : 'create_classe',
                    ...dati
                })
            });

            const result = await response.json();

            if (result.success) {
                this.mostraMessaggio(
                    isEdit ? 'Classe aggiornata con successo' : 'Classe creata con successo', 
                    'success'
                );
                setTimeout(() => {
                    window.location.href = 'classi.php';
                }, 1000);
            } else {
                this.mostraMessaggio(result.message, 'error');
            }
        } catch (error) {
            console.error('Errore:', error);
            this.mostraMessaggio('Errore durante il salvataggio', 'error');
        }
    }

    validaDatiClasse(dati) {
        const errori = [];

        if (!dati.nome?.trim()) {
            errori.push('Il nome della classe è obbligatorio');
        }

        if (!dati.anno_scolastico_id) {
            errori.push('L\'anno scolastico è obbligatorio');
        }

        if (!dati.percorso_formativo_id) {
            errori.push('Il percorso formativo è obbligatorio');
        }

        if (!dati.sede_id) {
            errori.push('La sede è obbligatoria');
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

    // Gestione calcolo ore totali
    calcolaOreTotali() {
        const inputsOre = document.querySelectorAll('input[name^="ore_"]');
        let totale = 0;

        inputsOre.forEach(input => {
            if (input.value) {
                totale += parseInt(input.value);
            }
        });

        const orePreviste = document.getElementById('ore-previste').value || 0;
        const differenza = orePreviste - totale;

        document.getElementById('totale-ore').textContent = totale;
        document.getElementById('differenza-ore').textContent = differenza;

        // Colore in base alla differenza
        const diffElement = document.getElementById('differenza-ore');
        if (differenza < 0) {
            diffElement.className = 'text-red-600 font-bold';
        } else if (differenza > 0) {
            diffElement.className = 'text-yellow-600 font-bold';
        } else {
            diffElement.className = 'text-green-600 font-bold';
        }
    }
}

// Inizializzazione quando il DOM è pronto
document.addEventListener('DOMContentLoaded', function() {
    window.gestioneClassi = new GestioneClassi();
});

// Utility functions
function formattaData(data) {
    return new Date(data).toLocaleDateString('it-IT');
}

function formattaOre(ore) {
    return `${ore}h`;
}

function mostraLoader() {
    document.body.classList.add('loading');
}

function nascondiLoader() {
    document.body.classList.remove('loading');
}