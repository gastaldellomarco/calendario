<?php
class Logger {
    
    /**
     * Log generico
     */
    public static function log($tipo, $azione, $descrizione, $dati = []) {
        $utente = $_SESSION['user']['username'] ?? 'sistema';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $dati_prima = null;
        $dati_dopo = null;
        $tabella = $dati['tabella'] ?? null;
        $record_id = $dati['record_id'] ?? null;
        
        if (isset($dati['prima'])) {
            $dati_prima = json_encode($dati['prima']);
        }
        if (isset($dati['dopo'])) {
            $dati_dopo = json_encode($dati['dopo']);
        }
        
        $params = [
            $tipo, $azione, $descrizione, $tabella, $record_id,
            $utente, $dati_prima, $dati_dopo, $ip_address
        ];
        
        Database::query("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, dati_prima, dati_dopo, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", $params);
    }
    
    /**
     * Log inserimento record
     */
    public static function logInsert($tabella, $record_id, $dati) {
        self::log(
            'insert', 
            'creazione', 
            "Nuovo record creato in $tabella", 
            [
                'tabella' => $tabella,
                'record_id' => $record_id,
                'dopo' => $dati
            ]
        );
    }
    
    /**
     * Log aggiornamento record
     */
    public static function logUpdate($tabella, $record_id, $prima, $dopo) {
        // Calcola differenze
        $differenze = [];
        foreach ($dopo as $campo => $valore) {
            if (!isset($prima[$campo]) || $prima[$campo] != $valore) {
                $differenze[$campo] = [
                    'prima' => $prima[$campo] ?? null,
                    'dopo' => $valore
                ];
            }
        }
        
        if (!empty($differenze)) {
            self::log(
                'update', 
                'modifica', 
                "Record aggiornato in $tabella", 
                [
                    'tabella' => $tabella,
                    'record_id' => $record_id,
                    'prima' => $prima,
                    'dopo' => $dopo
                ]
            );
        }
    }
    
    /**
     * Log eliminazione record
     */
    public static function logDelete($tabella, $record_id, $dati) {
        self::log(
            'delete', 
            'eliminazione', 
            "Record eliminato da $tabella", 
            [
                'tabella' => $tabella,
                'record_id' => $record_id,
                'prima' => $dati
            ]
        );
    }
    
    /**
     * Log accesso utente
     */
    public static function logLogin($utente_id, $successo) {
        $azione = $successo ? 'login_successo' : 'login_fallito';
        $descrizione = $successo ? 
            "Accesso utente riuscito" : 
            "Tentativo di accesso fallito";
            
        self::log(
            'login', 
            $azione, 
            $descrizione,
            [
                'tabella' => 'utenti',
                'record_id' => $utente_id
            ]
        );
    }
    
    /**
     * Log errore
     */
    public static function logError($messaggio, $trace = null) {
        $dati = [];
        if ($trace) {
            $dati['trace'] = $trace;
        }
        
        self::log(
            'error', 
            'errore_sistema', 
            $messaggio,
            $dati
        );
    }
    
    /**
     * Log backup
     */
    public static function logBackup($tipo, $dettagli) {
        self::log(
            'backup', 
            $tipo, 
            "Operazione backup: $dettagli"
        );
    }
    
    /**
     * Log manutenzione
     */
    public static function logMaintenance($azione, $dettagli) {
        self::log(
            'maintenance', 
            $azione, 
            "Manutenzione sistema: $dettagli"
        );
    }
}

// Funzioni helper globali per backward compatibility
function logActivity($tipo, $tabella, $record_id, $descrizione, $dati = []) {
    $dati['tabella'] = $tabella;
    $dati['record_id'] = $record_id;
    Logger::log($tipo, $tipo, $descrizione, $dati);
}
?>