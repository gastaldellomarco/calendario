# ANALISI COMPLETA ERRORI SISTEMA SCUOLA

## 🔴 PROBLEMI CRITICI TROVATI

### 1. **Menu di Navigazione Incompleto**
**File:** `/includes/header.php`
- ❌ Menu manca pagine per:
  - `anni_scolastici.php` (non in menu)
  - `classe_form.php` (non in menu)
  - `aula_form.php` (non esiste ma è richiesta da aule.php)
  - `disponibilita_aula.php` (non esiste ma è richiesta da aule.php)
  
**Soluzione:** Aggiungere menu per tutte le pagine mancanti

---

### 2. **Pagine Morte (Link che Puntano a Pagine Inesistenti)**
**File:** `pages/aule.php` (linee 167-170)
```php
onclick="modificaAula(<?= $aula['id'] ?>)" // → aula_form.php (NON ESISTE)
onclick="visualizzaDisponibilita(<?= $aula['id'] ?>)" // → disponibilita_aula.php (NON ESISTE)
```

**File:** `pages/docenti.php` (linee 195-201)
```php
onclick="openDocenteForm(<?= $docente['id'] ?>)" // → docente_form.php
onclick="openVincoli(<?= $docente['id'] ?>)" // FUNZIONE NON DEFINITA
onclick="openMaterie(<?= $docente['id'] ?>)" // FUNZIONE NON DEFINITA
```

---

### 3. **Funzioni JavaScript Non Definite**
**File:** `pages/docenti.php`
- `openDocenteForm()` - Non definita
- `openVincoli()` - Non definita  
- `openMaterie()` - Non definita
- `deleteDocente()` - Non definita
- `exportDocenti()` - Non definita
- `openImportModal()` - Non definita
- `closeImportModal()` - Non definita
- `importCSV()` - Non definita

**File:** `pages/aule.php`
- `modificaAula()` - Non definita (ma richiesta)
- `visualizzaDisponibilita()` - Non definita (ma richiesta)

**File:** `pages/materie.php`
- `apriModalMateria()` - ✅ Definita
- `chiudiModalMateria()` - ✅ Definita
- `modificaMateria()` - ✅ Definita
- `eliminaMateria()` - ✅ Definita

---

### 4. **Errori di Logica Database**
**File:** `pages/aule.php` (linea 25)
```php
// ❌ ERRORE: $pdo non è definito
try {
    $stmt = $pdo->prepare(...) // 🔴 Undefined variable
}
```

**Fix:** Aggiungere `$pdo = getPDOConnection();`

---

### 5. **Pagine Mancanti Completamente**
- ❌ `pages/aula_form.php` - Form per modifica aule
- ❌ `pages/disponibilita_aula.php` - Disponibilità aula
- ❌ `pages/anni_scolastici.php` - Gestione anni scolastici
- ❌ `pages/classe_form.php` - Form per creazione classi
- ❌ `api/disponibilita_api.php` - API per disponibilità (richiesta da disponibilita_docenti.php)

---

### 6. **API Non Gestiscono Correttamente DELETE**
**File:** `api/materie_api.php` (linee 7-42)
```php
// ❌ DELETE gestito ma switch non lo supporta correttamente
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    deleteMateria($pdo, $input);
} else {
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
}
```

Il problema: Se DELETE non è supportato dal switch, viene restituito errore

---

### 7. **Manca Gestione Modifiche Aule, Docenti**
**File:** `pages/aule.php`
- ❌ Nessun pulsante "Modifica" funzionante
- ❌ Nessun pulsante "Elimina" visibile

**File:** `pages/docenti.php`
- ✅ Pulsanti presenti ma JavaScript non implementato

---

## ✅ CORREZIONI DA IMPLEMENTARE

### Priorità 1 (CRITICA)
1. Creare file `pages/aula_form.php`
2. Creare file `pages/disponibilita_aula.php`
3. Implementare funzioni JavaScript in `pages/docenti.php`
4. Implementare funzioni JavaScript in `pages/aule.php`
5. Aggiornare menu in `includes/header.php`

### Priorità 2 (ALTA)
1. Creare file `pages/anni_scolastici.php`
2. Creare file `pages/classe_form.php`
3. Creare API `api/disponibilita_api.php`
4. Aggiungere pulsanti Modifica/Elimina in tabelle

### Priorità 3 (MEDIA)
1. Aggiungere validazioni form lato client
2. Aggiungere messaggi di conferma prima di eliminare
3. Aggiungere feedback visuale durante caricamenti

---

## 📋 ARCHITETTURA CONSIGLIATA

### File Form (CRUD)
```
pages/
├── docente_form.php       ✅ Esiste
├── classe_form.php        ❌ Manca
├── aula_form.php          ❌ Manca
├── materia_form.php       ⚠️ Integrato in modal
├── anni_scolastici.php    ❌ Manca
└── percorsi_form.php      ❌ Manca
```

### File Dettagli/Disponibilità
```
pages/
├── disponibilita_docenti.php    ✅ Esiste
├── disponibilita_aula.php       ❌ Manca
└── disponibilita_classe.php     ❌ Manca
```

### Menu Navigazione
```
Dashboard
  ├── 👥 Gestione Personale
  │   ├── Docenti          ✅
  │   └── Docente Edit     ✅
  ├── 🎓 Gestione Didattica
  │   ├── Classi           ✅
  │   ├── Materie          ✅
  │   └── Percorsi         ✅
  ├── 📅 Calendario
  │   ├── Lezioni          ✅
  │   ├── Slot Orari       ✅
  │   └── Genera Calendario ✅
  ├── 🏢 Struttura Scuola
  │   ├── Sedi             ✅
  │   ├── Aule             ✅
  │   └── Anni Scolastici  ❌ Manca dal menu
  └── ⚙️ Amministrazione
      ├── Vincoli Docente      ✅
      ├── Docente/Materie      ✅
      └── Assegna Materie      ✅
```
