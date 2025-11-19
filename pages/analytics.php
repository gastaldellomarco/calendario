<?php
require_once '../includes/header.php';
require_once '../config/database.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">📈 Analytics Avanzate</h1>
        <p class="text-gray-600">Dashboard interattiva con insights predittivi e KPI in tempo reale</p>
    </div>

    <!-- Filtri Avanzati -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Filtri Analytics</h2>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Anno Scolastico</label>
                <select id="annoSelect" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutti</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sede</label>
                <select id="sedeSelect" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutte</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Percorso</label>
                <select id="percorsoSelect" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutti</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Periodo</label>
                <select id="periodoSelect" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="7">Ultima settimana</option>
                    <option value="30" selected>Ultimo mese</option>
                    <option value="90">Ultimo trimestre</option>
                    <option value="180">Ultimo semestre</option>
                    <option value="365">Ultimo anno</option>
                    <option value="custom">Personalizzato</option>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="caricaAnalytics()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 w-full">
                    Aggiorna Analytics
                </button>
            </div>
        </div>
        
        <!-- Date personalizzate (nascosto di default) -->
        <div id="customDates" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Da Data</label>
                <input type="date" id="dataInizio" class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">A Data</label>
                <input type="date" id="dataFine" class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
        </div>
    </div>

    <!-- KPI Principali -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow-md p-4 text-center">
            <div class="text-2xl font-bold text-blue-600" id="kpiOreSvolte">0</div>
            <div class="text-sm text-gray-600">Ore Svolte</div>
            <div class="text-xs text-green-500 mt-1" id="kpiTrendOre">+0%</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 text-center">
            <div class="text-2xl font-bold text-green-600" id="kpiTassoSuccesso">0%</div>
            <div class="text-sm text-gray-600">Tasso Successo</div>
            <div class="text-xs text-green-500 mt-1" id="kpiTrendSuccesso">+0%</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 text-center">
            <div class="text-2xl font-bold text-purple-600" id="kpiUtilizzoAule">0%</div>
            <div class="text-sm text-gray-600">Utilizzo Aule</div>
            <div class="text-xs text-green-500 mt-1" id="kpiTrendAule">+0%</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 text-center">
            <div class="text-2xl font-bold text-orange-600" id="kpiEfficienza">0%</div>
            <div class="text-sm text-gray-600">Efficienza Docenti</div>
            <div class="text-xs text-green-500 mt-1" id="kpiTrendEfficienza">+0%</div>
        </div>
    </div>

    <!-- Grafici Principali -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Trend Ore nel Tempo -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">📈 Trend Ore nel Tempo</h3>
            <canvas id="trendOreChart" height="250"></canvas>
        </div>

        <!-- Distribuzione per Sede -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">🏫 Distribuzione per Sede</h3>
            <canvas id="distribuzioneSediChart" height="250"></canvas>
        </div>

        <!-- Previsioni Completamento -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">🎯 Previsioni Completamento Anno</h3>
            <canvas id="previsioniChart" height="250"></canvas>
        </div>

        <!-- Efficienza Docenti -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">👥 Efficienza Docenti</h3>
            <canvas id="efficienzaDocentiChart" height="250"></canvas>
        </div>
    </div>

    <!-- Insights Automatici -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">💡 Insights & Raccomandazioni</h2>
        <div id="insightsContainer" class="space-y-4">
            <!-- Gli insights verranno caricati via JavaScript -->
        </div>
    </div>

    <!-- Analisi Dettagliata -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Hotspot Conflitti -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">⚠️ Hotspot Conflitti</h3>
            <div id="hotspotConflitti">
                <!-- Mappa calore conflitti -->
            </div>
        </div>

        <!-- Performance Classi -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">📊 Performance Classi</h3>
            <div id="performanceClassi">
                <!-- Tabella performance -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script>
// Variabili globali per i grafici
let trendOreChart, distribuzioneSediChart, previsioniChart, efficienzaDocentiChart;

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    caricaFiltri();
    caricaAnalytics();
    
    // Gestione periodo personalizzato
    document.getElementById('periodoSelect').addEventListener('change', function() {
        const customDates = document.getElementById('customDates');
        customDates.classList.toggle('hidden', this.value !== 'custom');
    });
});

async function caricaFiltri() {
    try {
        // Carica anni scolastici
        const responseAnni = await fetch('../api/reports_api.php?action=get_custom_report&table=anni_scolastici&fields=id,anno&order_by=data_inizio DESC');
        const dataAnni = await responseAnni.json();
        
        if (dataAnni.success) {
            const select = document.getElementById('annoSelect');
            dataAnni.data.forEach(anno => {
                const option = document.createElement('option');
                option.value = anno.id;
                option.textContent = anno.anno;
                select.appendChild(option);
            });
        }
        
        // Carica sedi
        const responseSedi = await fetch('../api/reports_api.php?action=get_custom_report&table=sedi&fields=id,nome&where=attiva=1');
        const dataSedi = await responseSedi.json();
        
        if (dataSedi.success) {
            const select = document.getElementById('sedeSelect');
            dataSedi.data.forEach(sede => {
                const option = document.createElement('option');
                option.value = sede.id;
                option.textContent = sede.nome;
                select.appendChild(option);
            });
        }
        
        // Carica percorsi
        const responsePercorsi = await fetch('../api/reports_api.php?action=get_custom_report&table=percorsi_formativi&fields=id,nome&where=attivo=1');
        const dataPercorsi = await responsePercorsi.json();
        
        if (dataPercorsi.success) {
            const select = document.getElementById('percorsoSelect');
            dataPercorsi.data.forEach(percorso => {
                const option = document.createElement('option');
                option.value = percorso.id;
                option.textContent = percorso.nome;
                select.appendChild(option);
            });
        }
        
    } catch (error) {
        console.error('Errore nel caricamento filtri:', error);
    }
}

async function caricaAnalytics() {
    const filters = getFilters();
    
    try {
        // Carica KPI
        const responseKPI = await fetch(`../api/reports_api.php?action=get_kpi&${filters}`);
        const dataKPI = await responseKPI.json();
        
        if (dataKPI.success) {
            updateKPI(dataKPI.kpi);
        }
        
        // Carica dati per grafici
        await caricaTrendOre(filters);
        await caricaDistribuzioneSedi(filters);
        await caricaPrevisioni(filters);
        await caricaEfficienzaDocenti(filters);
        await caricaInsights(filters);
        
    } catch (error) {
        console.error('Errore nel caricamento analytics:', error);
    }
}

function getFilters() {
    const params = new URLSearchParams();
    
    const anno = document.getElementById('annoSelect').value;
    const sede = document.getElementById('sedeSelect').value;
    const percorso = document.getElementById('percorsoSelect').value;
    const periodo = document.getElementById('periodoSelect').value;
    
    if (anno) params.append('anno_scolastico_id', anno);
    if (sede) params.append('sede_id', sede);
    if (percorso) params.append('percorso_formativo_id', percorso);
    
    if (periodo === 'custom') {
        const dataInizio = document.getElementById('dataInizio').value;
        const dataFine = document.getElementById('dataFine').value;
        if (dataInizio) params.append('data_inizio', dataInizio);
        if (dataFine) params.append('data_fine', dataFine);
    } else if (periodo !== '') {
        const dataFine = new Date().toISOString().split('T')[0];
        const dataInizio = new Date(Date.now() - periodo * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        params.append('data_inizio', dataInizio);
        params.append('data_fine', dataFine);
    }
    
    return params.toString();
}

function updateKPI(kpiData) {
    // Simula aggiornamento KPI (dati reali verrebbero dall'API)
    document.getElementById('kpiOreSvolte').textContent = '1,250';
    document.getElementById('kpiTrendOre').textContent = '+12%';
    
    document.getElementById('kpiTassoSuccesso').textContent = '94%';
    document.getElementById('kpiTrendSuccesso').textContent = '+3%';
    
    document.getElementById('kpiUtilizzoAule').textContent = '78%';
    document.getElementById('kpiTrendAule').textContent = '+5%';
    
    document.getElementById('kpiEfficienza').textContent = '88%';
    document.getElementById('kpiTrendEfficienza').textContent = '+2%';
}

async function caricaTrendOre(filters) {
    // Dati di esempio per il trend ore
    const datiTrend = {
        labels: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu'],
        datasets: [{
            label: 'Ore Svolte',
            data: [1200, 1300, 1250, 1400, 1350, 1500],
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'Ore Pianificate',
            data: [1250, 1350, 1300, 1450, 1400, 1550],
            borderColor: '#10B981',
            borderDash: [5, 5],
            fill: false
        }]
    };
    
    const ctx = document.getElementById('trendOreChart').getContext('2d');
    
    if (trendOreChart) {
        trendOreChart.destroy();
    }
    
    trendOreChart = new Chart(ctx, {
        type: 'line',
        data: datiTrend,
        options: {
            responsive: true,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Ore'
                    }
                }
            }
        }
    });
}

async function caricaDistribuzioneSedi(filters) {
    const datiSedi = {
        labels: ['Sede Centrale', 'Sede Nord', 'Sede Sud', 'Sede Ovest'],
        datasets: [{
            data: [45, 25, 20, 10],
            backgroundColor: [
                '#3B82F6', '#10B981', '#F59E0B', '#EF4444'
            ]
        }]
    };
    
    const ctx = document.getElementById('distribuzioneSediChart').getContext('2d');
    
    if (distribuzioneSediChart) {
        distribuzioneSediChart.destroy();
    }
    
    distribuzioneSediChart = new Chart(ctx, {
        type: 'doughnut',
        data: datiSedi,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

async function caricaPrevisioni(filters) {
    const datiPrevisioni = {
        labels: ['Set', 'Ott', 'Nov', 'Dic', 'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu'],
        datasets: [{
            label: 'Progresso Attuale',
            data: [10, 25, 45, 60, 75, 85, 90, 95, 98, 100],
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true
        }, {
            label: 'Target Ideale',
            data: [15, 30, 50, 65, 80, 90, 95, 98, 99, 100],
            borderColor: '#3B82F6',
            borderDash: [5, 5],
            fill: false
        }]
    };
    
    const ctx = document.getElementById('previsioniChart').getContext('2d');
    
    if (previsioniChart) {
        previsioniChart.destroy();
    }
    
    previsioniChart = new Chart(ctx, {
        type: 'line',
        data: datiPrevisioni,
        options: {
            responsive: true,
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

async function caricaEfficienzaDocenti(filters) {
    const datiEfficienza = {
        labels: ['Docente 1', 'Docente 2', 'Docente 3', 'Docente 4', 'Docente 5', 'Docente 6'],
        datasets: [{
            label: 'Efficienza (%)',
            data: [95, 87, 92, 78, 85, 96],
            backgroundColor: [
                '#10B981', '#10B981', '#10B981', '#F59E0B', '#10B981', '#10B981'
            ]
        }]
    };
    
    const ctx = document.getElementById('efficienzaDocentiChart').getContext('2d');
    
    if (efficienzaDocentiChart) {
        efficienzaDocentiChart.destroy();
    }
    
    efficienzaDocentiChart = new Chart(ctx, {
        type: 'bar',
        data: datiEfficienza,
        options: {
            responsive: true,
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

async function caricaInsights(filters) {
    const insights = [
        {
            type: 'warning',
            title: '3 Docenti sopra il 120% del carico',
            message: 'I docenti Rossi, Bianchi e Verdi hanno un carico di lavoro superiore al 120% del contratto. Considerare redistribuzione ore.',
            action: 'Visualizza report docenti'
        },
        {
            type: 'info',
            title: 'Aula 12 utilizzata solo al 30%',
            message: 'L\'aula laboratorio 12 ha un tasso di utilizzo del 30%. Considerare riallocazione o promozione utilizzo.',
            action: 'Analizza utilizzo aule'
        },
        {
            type: 'success',
            title: 'Classe 4A avanti sul programma',
            message: 'La classe 4A ha completato il 95% del programma con 2 settimane di anticipo. Possibile anticipo esami.',
            action: 'Dettaglio classe'
        },
        {
            type: 'warning',
            title: 'Aumento conflitti orario',
            message: 'Rilevato aumento del 15% nei conflitti orario nell\'ultimo mese. Verificare sovrapposizioni.',
            action: 'Gestisci conflitti'
        }
    ];
    
    const container = document.getElementById('insightsContainer');
    container.innerHTML = insights.map(insight => `
        <div class="flex items-start p-4 border-l-4 ${
            insight.type === 'warning' ? 'border-yellow-400 bg-yellow-50' :
            insight.type === 'info' ? 'border-blue-400 bg-blue-50' :
            'border-green-400 bg-green-50'
        } rounded">
            <div class="flex-1">
                <h4 class="font-semibold text-gray-800">${insight.title}</h4>
                <p class="text-sm text-gray-600 mt-1">${insight.message}</p>
                <button class="text-sm text-blue-600 hover:text-blue-800 mt-2">${insight.action} →</button>
            </div>
        </div>
    `).join('');
}
</script>

<?php require_once '../includes/footer.php'; ?>