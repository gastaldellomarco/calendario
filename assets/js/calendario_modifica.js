class CalendarioModifica {
    constructor() {
        this.modifiche = [];
        this.history = [];
        this.historyIndex = -1;
        this.currentSede = document.getElementById('sedeFilter')?.value || '';
        this.currentSettimana = new URLSearchParams(window.location.search).get('settimana') || '';
        
        this.init();
    }
    
    init() {
        this.caricaLezioni();
        this.initSortable();
        this.initEventListeners();
        this.initModale();
    }
    
    /**
     * Carica le lezioni del calendario per la settimana attualmente selezionata
     *
     * @param {Date} [dataInizio] - Data inizio settimana (opzionale, se omesso viene calcolato da currentSettimana)
     * @param {number} [classeId] - ID della classe (opzionale)
     * @param {Object} [options] - Opzioni aggiuntive
     * @param {boolean} [options.includiSostituzioni=false] - Se includere lezioni sostituite
     * @returns {Promise<Array<Object>>} Promise che risolve con l'array di lezioni caricate
     * @throws {Error} Se la richiesta API fallisce
     *
     * @example
     * await calendario.caricaLezioni();
     */
    caricaLezioni(dataInizio = null, classeId = null, options = { includiSostituzioni: false }) {
        const startDate = dataInizio ? new Date(dataInizio) : this.getDataInizioSettimana();
        const dataFine = new Date(startDate);
        dataFine.setDate(startDate.getDate() + 6);
        
        const params = new URLSearchParams({
            action: 'get_lezioni',
            data_inizio: startDate.toISOString().split('T')[0],
            data_fine: dataFine.toISOString().split('T')[0],
            sede_id: this.currentSede
        });
        
    return fetch(`../api/calendario_api.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderLezioni(data.data);
                } else {
                    this.mostraErrore('Errore nel caricamento delle lezioni: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                this.mostraErrore('Errore di connessione');
            });
    }
    
    renderLezioni(lezioni) {
        // Pulisci tutti i container
        document.querySelectorAll('.lezione-container').forEach(container => {
            container.innerHTML = '';
        });
        
        // Raggruppa lezioni per data e slot
        const lezioniPerSlot = {};
        lezioni.forEach(lezione => {
            const key = `slot-${lezione.data_lezione}-${lezione.slot_id}`;
            if (!lezioniPerSlot[key]) {
                lezioniPerSlot[key] = [];
            }
            lezioniPerSlot[key].push(lezione);
        });
        
        // Renderizza lezioni
        Object.keys(lezioniPerSlot).forEach(key => {
            const container = document.getElementById(key);
            if (container) {
                container.innerHTML = '';
                lezioniPerSlot[key].forEach(lezione => {
                    container.appendChild(this.creaCardLezione(lezione));
                });
            }
        });
    }
    
    /**
     * Crea una card DOM per rappresentare una lezione nel calendario
     *
     * @param {Object} lezione - Oggetto lezione
     * @param {number} lezione.id
     * @param {number} lezione.classe_id
     * @param {number} lezione.materia_id
     * @param {number} lezione.docente_id
     * @param {string} lezione.materia_nome
     * @param {string} lezione.classe_nome
     * @param {string} [lezione.aula_nome]
     * @param {string} lezione.ora_inizio
     * @param {string} lezione.ora_fine
     * @returns {HTMLElement} Elemento DOM della card
     */
    creaCardLezione(lezione) {
        const card = document.createElement('div');
        card.className = 'lezione-card draggable-lezione mb-2 p-3 rounded-lg shadow-sm cursor-move hover:shadow-md transition-all duration-200';
        card.setAttribute('draggable', 'true');
        card.setAttribute('data-lezione-id', lezione.id);
        card.setAttribute('data-classe-id', lezione.classe_id);
        card.setAttribute('data-materia-id', lezione.materia_id);
        card.setAttribute('data-docente-id', lezione.docente_id);
        card.setAttribute('data-aula-id', lezione.aula_id);
        
        // Colore basato sulla materia
        const colore = this.stringToColor(lezione.materia_nome);
        const isLight = this.isLightColor(colore);
        const textColor = isLight ? 'text-gray-800' : 'text-white';
        
        card.style.backgroundColor = colore;
        card.style.borderLeft = `4px solid ${this.stringToColor(lezione.classe_nome)}`;
        
        card.innerHTML = `
            <div class="flex justify-between items-start mb-2">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold ${textColor} truncate">
                        ${this.escapeHtml(lezione.classe_nome)}
                    </div>
                </div>
                <div class="flex space-x-1 ml-2">
                    <button class="edit-lezione text-xs ${textColor} opacity-70 hover:opacity-100 transition-opacity"
                            data-lezione-id="${lezione.id}"
                            title="Modifica">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="delete-lezione text-xs ${textColor} opacity-70 hover:opacity-100 transition-opacity"
                            data-lezione-id="${lezione.id}"
                            title="Elimina">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="text-xs ${textColor} opacity-90 truncate">
                ${this.escapeHtml(lezione.materia_nome)}
            </div>
            <div class="text-xs ${textColor} opacity-80 truncate">
                ${this.escapeHtml(lezione.docente_nome)}
            </div>
            ${lezione.aula_nome ? `
                <div class="text-xs ${textColor} opacity-70 truncate">
                    <i class="fas fa-door-open mr-1"></i>${this.escapeHtml(lezione.aula_nome)}
                </div>
            ` : ''}
            <div class="mt-1 text-xs ${textColor} opacity-60">
                ${lezione.ora_inizio.substring(0, 5)} - ${lezione.ora_fine.substring(0, 5)}
            </div>
        `;
        
        // Aggiungi event listeners
        card.querySelector('.edit-lezione').addEventListener('click', (e) => {
            e.stopPropagation();
            this.apriModaleModifica(lezione.id);
        });
        
        card.querySelector('.delete-lezione').addEventListener('click', (e) => {
            e.stopPropagation();
            this.eliminaLezione(lezione.id);
        });
        
        card.addEventListener('click', (e) => {
            if (!e.target.closest('button')) {
                this.mostraDettaglioLezione(lezione.id);
            }
        });
        
        return card;
    }
    
    initSortable() {
        const containers = document.querySelectorAll('.droppable-slot');
        
        containers.forEach(container => {
            new Sortable(container.querySelector('.lezione-container'), {
                group: 'lezioni',
                animation: 150,
                ghostClass: 'lezione-ghost',
                chosenClass: 'lezione-chosen',
                dragClass: 'lezione-drag',
                
                onEnd: (evt) => {
                    const lezioneId = evt.item.getAttribute('data-lezione-id');
                    const nuovoSlot = evt.to.closest('.droppable-slot');
                    
                    if (nuovoSlot) {
                        const nuovaData = nuovoSlot.getAttribute('data-date');
                        const nuovoSlotId = nuovoSlot.getAttribute('data-slot');
                        
                        this.spostaLezione(lezioneId, nuovaData, nuovoSlotId);
                    }
                }
            });
        });
    }
    
    initEventListeners() {
        // Filtro sede
        document.getElementById('sedeFilter')?.addEventListener('change', (e) => {
            this.currentSede = e.target.value;
            this.caricaLezioni();
        });
        
        // Pulsanti azione
        document.getElementById('saveChanges')?.addEventListener('click', () => this.salvaModifiche());
        document.getElementById('undoBtn')?.addEventListener('click', () => this.undo());
        document.getElementById('redoBtn')?.addEventListener('click', () => this.redo());
        
        // Aggiungi lezione
        document.querySelectorAll('.add-lezione-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const date = e.target.closest('.add-lezione-btn').getAttribute('data-date');
                const slot = e.target.closest('.add-lezione-btn').getAttribute('data-slot');
                this.apriModaleNuovaLezione(date, slot);
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 's':
                        e.preventDefault();
                        this.salvaModifiche();
                        break;
                    case 'z':
                        e.preventDefault();
                        if (e.shiftKey) {
                            this.redo();
                        } else {
                            this.undo();
                        }
                        break;
                }
            }
        });
    }
    
    initModale() {
        this.modale = document.getElementById('lezioneModal');
        this.lezioneForm = document.getElementById('lezioneForm');
        
        // Chiusura modale
        document.getElementById('closeModal')?.addEventListener('click', () => this.chiudiModale());
        document.getElementById('cancelLezione')?.addEventListener('click', () => this.chiudiModale());
        
        // Salva lezione
        document.getElementById('saveLezione')?.addEventListener('click', () => this.salvaLezione());
        
        // Verifica conflitti
        document.getElementById('checkConflitti')?.addEventListener('click', () => this.verificaConflitti());
        
        // Change events per caricamento dati correlati
        document.getElementById('classe_id')?.addEventListener('change', (e) => this.caricaMateriePerClasse(e.target.value));
        document.getElementById('materia_id')?.addEventListener('change', (e) => this.caricaDocentiPerMateria(e.target.value));
    }
    
    apriModaleNuovaLezione(date, slot) {
        document.getElementById('modalTitle').textContent = 'Nuova Lezione';
        document.getElementById('lezione_id').value = '';
        document.getElementById('modal_date').value = date;
        document.getElementById('modal_slot').value = slot;
        
        // Reset form
        this.lezioneForm.reset();
        document.getElementById('conflittiAlert').classList.add('hidden');
        
        // Carica dati iniziali
        this.caricaClassi();
        this.caricaAule();
        
        this.modale.classList.remove('hidden');
    }
    
    apriModaleModifica(lezioneId) {
        fetch(`../api/calendario_api.php?action=get_lezione&lezione_id=${lezioneId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const lezione = data.data;
                    
                    document.getElementById('modalTitle').textContent = 'Modifica Lezione';
                    document.getElementById('lezione_id').value = lezione.id;
                    document.getElementById('modal_date').value = lezione.data_lezione;
                    document.getElementById('modal_slot').value = lezione.slot_id;
                    
                    // Compila form
                    document.getElementById('classe_id').value = lezione.classe_id;
                    document.getElementById('materia_id').value = lezione.materia_id;
                    document.getElementById('docente_id').value = lezione.docente_id;
                    document.getElementById('aula_id').value = lezione.aula_id || '';
                    document.getElementById('stato').value = lezione.stato;
                    document.getElementById('modalita').value = lezione.modalita;
                    document.getElementById('argomento').value = lezione.argomento || '';
                    document.getElementById('note').value = lezione.note || '';
                    
                    // Carica dati correlati
                    this.caricaClassi();
                    this.caricaMateriePerClasse(lezione.classe_id, lezione.materia_id);
                    this.caricaDocentiPerMateria(lezione.materia_id, lezione.docente_id);
                    this.caricaAule(lezione.aula_id);
                    
                    document.getElementById('conflittiAlert').classList.add('hidden');
                    this.modale.classList.remove('hidden');
                } else {
                    this.mostraErrore('Errore nel caricamento della lezione: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                this.mostraErrore('Errore di connessione');
            });
    }
    
    caricaClassi() {
        // Implementa caricamento classi via AJAX
        // Per semplicità, assumiamo che le classi siano già nel select
    }
    
    caricaMateriePerClasse(classeId, materiaSelezionata = '') {
        if (!classeId) return;
        
        fetch(`../api/materie_api.php?action=get_by_classe&classe_id=${classeId}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('materia_id');
                select.innerHTML = '<option value="">Seleziona materia</option>';
                
                if (data.success) {
                    data.data.forEach(materia => {
                        const option = document.createElement('option');
                        option.value = materia.id;
                        option.textContent = materia.nome;
                        option.selected = materia.id == materiaSelezionata;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Errore:', error);
            });
    }
    
    caricaDocentiPerMateria(materiaId, docenteSelezionato = '') {
        if (!materiaId) return;
        
        fetch(`../api/docenti_api.php?action=get_by_materia&materia_id=${materiaId}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('docente_id');
                select.innerHTML = '<option value="">Seleziona docente</option>';
                
                if (data.success) {
                    data.data.forEach(docente => {
                        const option = document.createElement('option');
                        option.value = docente.id;
                        option.textContent = docente.nome;
                        option.selected = docente.id == docenteSelezionato;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Errore:', error);
            });
    }
    
    caricaAule(aulaSelezionata = '') {
        if (!this.currentSede) return;
        
        fetch(`../api/aule_api.php?action=get_by_sede&sede_id=${this.currentSede}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('aula_id');
                select.innerHTML = '<option value="">Nessuna aula</option>';
                
                if (data.success) {
                    data.data.forEach(aula => {
                        const option = document.createElement('option');
                        option.value = aula.id;
                        option.textContent = aula.nome;
                        option.selected = aula.id == aulaSelezionata;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Errore:', error);
            });
    }
    
    verificaConflitti() {
        const formData = new FormData(this.lezioneForm);
        const data = Object.fromEntries(formData);
        
        // Aggiungi sede corrente
        data.sede_id = this.currentSede;
        
        fetch('../api/calendario_api.php?action=check_disponibilita', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            const alert = document.getElementById('conflittiAlert');
            const list = document.getElementById('conflittiList');
            
            if (!result.data.disponibile && result.data.conflitti.length > 0) {
                list.innerHTML = result.data.conflitti.map(conflitto => 
                    `<div class="mb-1">• ${conflitto.messaggio}</div>`
                ).join('');
                alert.classList.remove('hidden');
            } else {
                alert.classList.add('hidden');
                this.mostraSuccesso('Nessun conflitto rilevato');
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            this.mostraErrore('Errore nella verifica conflitti');
        });
    }
    
    salvaLezione() {
        const formData = new FormData(this.lezioneForm);
        const data = Object.fromEntries(formData);
        
        // Aggiungi sede corrente
        data.sede_id = this.currentSede;
        
        const action = data.lezione_id ? 'update_lezione' : 'create_lezione';
        
        fetch(`../api/calendario_api.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                this.mostraSuccesso(result.message);
                this.chiudiModale();
                this.caricaLezioni();
                this.aggiungiModifica(action, data);
            } else {
                if (result.data.conflitti) {
                    this.mostraConflitti(result.data.conflitti);
                } else {
                    this.mostraErrore(result.message);
                }
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            this.mostraErrore('Errore di connessione');
        });
    }
    
    spostaLezione(lezioneId, nuovaData, nuovoSlotId) {
        const modifica = {
            type: 'move',
            lezioneId: lezioneId,
            nuovaData: nuovaData,
            nuovoSlotId: nuovoSlotId,
            timestamp: new Date().toISOString()
        };
        
        fetch('../api/calendario_api.php?action=move_lezione', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lezione_id: lezioneId,
                nuova_data: nuovaData,
                nuovo_slot: nuovoSlotId
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                this.mostraSuccesso('Lezione spostata con successo');
                this.aggiungiModifica('move', modifica);
            } else {
                this.mostraErrore('Errore nello spostamento: ' + result.message);
                // Ricarica per ripristinare posizione originale
                this.caricaLezioni();
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            this.mostraErrore('Errore di connessione');
            this.caricaLezioni();
        });
    }
    
    eliminaLezione(lezioneId) {
        if (!confirm('Sei sicuro di voler eliminare questa lezione?')) {
            return;
        }
        
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
                this.mostraSuccesso('Lezione eliminata con successo');
                this.caricaLezioni();
                this.aggiungiModifica('delete', { lezioneId: lezioneId });
            } else {
                this.mostraErrore('Errore nell\'eliminazione: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            this.mostraErrore('Errore di connessione');
        });
    }
    
    // Gestione History (Undo/Redo)
    aggiungiModifica(tipo, dati) {
        this.modifiche.push({ tipo, dati, timestamp: new Date() });
        this.aggiornaContatoreModifiche();
    }
    
    undo() {
        if (this.modifiche.length === 0) return;
        
        const modifica = this.modifiche.pop();
        this.history.push(modifica);
        this.historyIndex++;
        
        // Implementa logica undo specifica per tipo modifica
        switch(modifica.tipo) {
            case 'create_lezione':
                this.eliminaLezione(modifica.dati.lezione_id);
                break;
            case 'delete_lezione':
                // Ricrea lezione eliminata
                break;
            case 'move_lezione':
                // Ripristina posizione originale
                break;
        }
        
        this.aggiornaContatoreModifiche();
        this.aggiornaPulsantiHistory();
    }
    
    redo() {
        if (this.history.length === 0 || this.historyIndex < 0) return;
        
        const modifica = this.history[this.historyIndex];
        this.historyIndex--;
        
        // Re-applica modifica
        // Implementa logica redo
        
        this.aggiornaPulsantiHistory();
    }
    
    salvaModifiche() {
        if (this.modifiche.length === 0) {
            this.mostraInfo('Nessuna modifica da salvare');
            return;
        }
        
        // In un'implementazione reale, qui salveresti tutte le modifiche in batch
        this.mostraSuccesso('Modifiche salvate con successo');
        this.modifiche = [];
        this.aggiornaContatoreModifiche();
    }
    
    // Utility functions
    getDataInizioSettimana() {
        const [year, week] = this.currentSettimana.split('-').map(Number);
        const date = new Date(year, 0, 1 + (week - 1) * 7);
        const day = date.getDay();
        const diff = date.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(date.setDate(diff));
    }
    
    stringToColor(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        let color = '#';
        for (let i = 0; i < 3; i++) {
            const value = (hash >> (i * 8)) & 0xFF;
            color += ('00' + value.toString(16)).substr(-2);
        }
        return color;
    }
    
    isLightColor(color) {
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        const brightness = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return brightness > 128;
    }
    
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    aggiornaContatoreModifiche() {
        const counter = document.getElementById('unsavedCount');
        if (counter) {
            counter.textContent = this.modifiche.length;
        }
    }
    
    aggiornaPulsantiHistory() {
        const undoBtn = document.getElementById('undoBtn');
        const redoBtn = document.getElementById('redoBtn');
        
        if (undoBtn) undoBtn.disabled = this.modifiche.length === 0;
        if (redoBtn) redoBtn.disabled = this.history.length === 0 || this.historyIndex < 0;
    }
    
    chiudiModale() {
        this.modale.classList.add('hidden');
    }
    
    mostraDettaglioLezione(lezioneId) {
        // Implementa visualizzazione dettaglio lezione
        console.log('Mostra dettaglio lezione:', lezioneId);
    }
    
    // Messaggi
    mostraSuccesso(messaggio) {
        this.mostraMessaggio(messaggio, 'success');
    }
    
    mostraErrore(messaggio) {
        this.mostraMessaggio(messaggio, 'error');
    }
    
    mostraInfo(messaggio) {
        this.mostraMessaggio(messaggio, 'info');
    }
    
    mostraConflitti(conflitti) {
        const messaggio = 'Conflitti rilevati:\n' + conflitti.map(c => c.messaggio).join('\n');
        this.mostraMessaggio(messaggio, 'warning');
    }
    
    mostraMessaggio(messaggio, tipo) {
        // Implementa sistema di notifiche
        const tipi = {
            success: { bg: 'bg-green-500', icon: 'fa-check-circle' },
            error: { bg: 'bg-red-500', icon: 'fa-exclamation-circle' },
            warning: { bg: 'bg-yellow-500', icon: 'fa-exclamation-triangle' },
            info: { bg: 'bg-blue-500', icon: 'fa-info-circle' }
        };
        
        const config = tipi[tipo] || tipi.info;
        
        // Crea e mostra notifica
        const notifica = document.createElement('div');
        notifica.className = `fixed top-4 right-4 ${config.bg} text-white p-4 rounded-lg shadow-lg z-50 max-w-sm`;
        notifica.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${config.icon} mr-2"></i>
                <span>${messaggio}</span>
            </div>
        `;
        
        document.body.appendChild(notifica);
        
        // Rimuovi dopo 5 secondi
        setTimeout(() => {
            notifica.remove();
        }, 5000);
    }
}

// Inizializzazione quando il DOM è pronto
document.addEventListener('DOMContentLoaded', () => {
    new CalendarioModifica();
});