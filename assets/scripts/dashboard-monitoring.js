/**
 * Classe simple pour l'affichage temps réel des métriques système
 * Sans complexité, juste lecture et affichage
 */
class SimpleDashboardMonitoring {
    constructor() {
        this.refreshInterval = 3000; // 3 secondes
        this.intervalId = null;
        this.isRunning = false;
        this.errorCount = 0;
        this.maxErrors = 5;

        // Démarrage automatique
        this.init();
    }

    init() {
        console.log('🚀 Initialisation du monitoring dashboard');

        // Vérifier que les éléments DOM existent
        if (!this.checkRequiredElements()) {
            console.error('❌ Éléments DOM requis manquants');
            return;
        }

        // Première récupération immédiate
        this.fetchAndUpdateData();

        // Puis démarrage du timer
        this.startMonitoring();

        // Configuration des événements
        this.setupEventListeners();
    }

    /**
     * Vérification des éléments DOM nécessaires
     */
    checkRequiredElements() {
        const required = [
            'cpu-widget',
            'memory-widget',
            'disk-widget',
            'network-widget'
        ];

        return required.every(id => {
            const element = document.getElementById(id);
            if (!element) {
                console.warn(`⚠️ Élément manquant: ${id}`);
                return false;
            }
            return true;
        });
    }

    /**
     * Démarrage du monitoring périodique
     */
    startMonitoring() {
        if (this.isRunning) return;

        this.intervalId = setInterval(() => {
            this.fetchAndUpdateData();
        }, this.refreshInterval);

        this.isRunning = true;
        console.log(`✅ Monitoring démarré (interval: ${this.refreshInterval}ms)`);
    }

    /**
     * Arrêt du monitoring
     */
    stopMonitoring() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        this.isRunning = false;
        console.log('⏹️ Monitoring arrêté');
    }

    /**
     * Récupération des données via l'API
     */
    async fetchAndUpdateData() {
        try {
            console.log('📡 Récupération des métriques...');

            const response = await fetch(`${API_BASE_PATH}/dashboard/api/system-stats`);

            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            // Vérifier si c'est une réponse d'erreur
            if (data.error) {
                throw new Error(data.message || 'Erreur serveur');
            }

            // Mise à jour de l'interface
            this.updateAllWidgets(data);

            // Reset du compteur d'erreurs en cas de succès
            this.errorCount = 0;
            this.hideErrorMessage();

            console.log('✅ Métriques mises à jour');

        } catch (error) {
            console.error('❌ Erreur récupération données:', error);
            this.handleError(error);
        }
    }

    /**
     * Mise à jour de tous les widgets
     */
    updateAllWidgets(data) {
        if (data.cpu) this.updateCpuWidget(data.cpu, data.load);
        if (data.memory) this.updateMemoryWidget(data.memory);
        if (data.disk) this.updateDiskWidget(data.disk);
        if (data.network) this.updateNetworkWidget(data.network);
        if (data.system) this.updateSystemInfo(data.system);

        // Mise à jour du timestamp
        this.updateLastRefresh(data.timestamp);
    }

    /**
     * Widget CPU - Affichage détaillé de tous les coeurs
     */
    updateCpuWidget(cpuData, loadData) {
        const widget = document.getElementById('cpu-widget');
        if (!widget) return;

        // Informations générales
        const coresElement = widget.querySelector('.cpu-cores');
        const loadElement = widget.querySelector('.cpu-load');
        const globalElement = widget.querySelector('.cpu-global');
        const coresDetailElement = widget.querySelector('.cpu-cores-detail');

        if (coresElement) {
            coresElement.textContent = `${cpuData.cores_count} cœur${cpuData.cores_count > 1 ? 's' : ''}`;
        }

        if (loadElement && loadData) {
            loadElement.innerHTML = `
                <div><strong>Load Average:</strong> ${loadData['1min']} / ${loadData['5min']} / ${loadData['15min']}</div>
                <div><strong>Processus:</strong> ${loadData.running_processes}/${loadData.total_processes}</div>
            `;
        }

        // Stats globales (somme de tous les CPU)
        if (globalElement && cpuData.global) {
            const global = cpuData.global;
            const globalPercentage = ((global.active / global.total) * 100).toFixed(1);

            globalElement.innerHTML = `
                <div><strong>CPU Global:</strong> ~${globalPercentage}% actif</div>
                <div>
                    <small>
                        User: ${global.user} | System: ${global.system} | 
                        Idle: ${global.idle} | I/O Wait: ${global.iowait}
                    </small>
                </div>
            `;
        }

        // Détail par cœur individuel
        if (coresDetailElement && cpuData.individual_cpus) {
            let html = '<div><strong>Détail par cœur:</strong></div>';

            Object.entries(cpuData.individual_cpus).forEach(([coreId, stats]) => {
                const percentage = ((stats.active / stats.total) * 100).toFixed(1);
                const barWidth = Math.min(percentage, 100);

                html += `
                    <div class="cpu-core" style="margin: 2px 0;">
                        <div style="display: flex; align-items: center;">
                            <span style="width: 60px;">CPU${coreId}:</span>
                            <div style="width: 60%; height: 12px; background: #e0e0e0; border-radius: 6px; margin: 0 8px;">
                                <div style="width: ${barWidth}%; height: 100%; background: ${this.getCpuColor(percentage)}; border-radius: 6px;"></div>
                            </div>
                            <span style="width: 50px; font-size: 11px;">${percentage}%</span>
                        </div>
                    </div>
                `;
            });

            coresDetailElement.innerHTML = html;
        }
    }

    /**
     * Couleur selon l'utilisation CPU
     */
    getCpuColor(percentage) {
        if (percentage > 80) return '#dc3545'; // Rouge
        if (percentage > 60) return '#ffc107'; // Orange
        if (percentage > 40) return '#17a2b8'; // Bleu
        return '#28a745'; // Vert
    }

    /**
     * Widget Mémoire
     */
    updateMemoryWidget(memoryData) {
        const widget = document.getElementById('memory-widget');
        if (!widget) return;

        const percentage = memoryData.percentage;

        // Barre de progression
        const progressBar = widget.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = `${percentage}%`;

            // Couleur selon le niveau
            progressBar.className = 'progress-bar';
            if (percentage > 85) {
                progressBar.classList.add('bg-danger');
            } else if (percentage > 70) {
                progressBar.classList.add('bg-warning');
            } else {
                progressBar.classList.add('bg-success');
            }
        }

        // Texte des informations
        const percentageText = widget.querySelector('.memory-percentage');
        const usageText = widget.querySelector('.memory-usage');

        if (percentageText) {
            percentageText.textContent = `${percentage}%`;
        }

        if (usageText) {
            usageText.innerHTML = `
                <div>${memoryData.formatted.used} / ${memoryData.formatted.total}</div>
                <div>Disponible: ${memoryData.formatted.available}</div>
            `;
        }
    }

    /**
     * Widget Disque
     */
    updateDiskWidget(diskData) {
        const widget = document.getElementById('disk-widget');
        if (!widget) return;

        const percentage = diskData.percentage;

        // Barre de progression
        const progressBar = widget.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = `${percentage}%`;

            progressBar.className = 'progress-bar';
            if (percentage > 90) {
                progressBar.classList.add('bg-danger');
            } else if (percentage > 75) {
                progressBar.classList.add('bg-warning');
            } else {
                progressBar.classList.add('bg-info');
            }
        }

        const percentageText = widget.querySelector('.disk-percentage');
        const usageText = widget.querySelector('.disk-usage');

        if (percentageText) {
            percentageText.textContent = `${percentage}%`;
        }

        if (usageText) {
            usageText.innerHTML = `
                <div>Utilisé: ${diskData.formatted.used}</div>
                <div>Libre: ${diskData.formatted.free}</div>
                <div>Total: ${diskData.formatted.total}</div>
            `;
        }
    }

    /**
     * Widget Réseau
     */
    updateNetworkWidget(networkData) {
        const widget = document.getElementById('network-widget');
        if (!widget) return;

        const interfacesList = widget.querySelector('.network-interfaces');
        if (!interfacesList) return;

        let html = '';

        Object.entries(networkData).forEach(([name, stats]) => {
            html += `
                <div class="network-interface">
                    <strong>${name}</strong>
                    <div>↓ ${stats.formatted.rx} | ↑ ${stats.formatted.tx}</div>
                    <div>Paquets: ${stats.rx_packets.toLocaleString()} / ${stats.tx_packets.toLocaleString()}</div>
                </div>
            `;
        });

        interfacesList.innerHTML = html;
    }

    /**
     * Informations système
     */
    updateSystemInfo(systemData) {
        const infoElement = document.getElementById('system-info');
        if (!infoElement) return;

        infoElement.innerHTML = `
            <div><strong>Serveur:</strong> ${systemData.hostname}</div>
            <div><strong>OS:</strong> ${systemData.os} (${systemData.architecture})</div>
            <div><strong>Uptime:</strong> ${systemData.uptime}</div>
            <div><strong>Heure:</strong> ${systemData.server_time}</div>
        `;
    }

    /**
     * Mise à jour du timestamp
     */
    updateLastRefresh(timestamp) {
        const element = document.getElementById('last-refresh');
        if (element) {
            const date = new Date(timestamp * 1000);
            element.textContent = date.toLocaleTimeString('fr-FR');
        }
    }

    /**
     * Gestion des erreurs
     */
    handleError(error) {
        this.errorCount++;

        this.showErrorMessage(`Erreur: ${error.message}`);

        // Arrêter le monitoring après trop d'erreurs consécutives
        if (this.errorCount >= this.maxErrors) {
            this.stopMonitoring();
            this.showErrorMessage(`Trop d'erreurs (${this.maxErrors}). Monitoring arrêté.`);
        }
    }

    /**
     * Affichage d'un message d'erreur
     */
    showErrorMessage(message) {
        const errorContainer = document.getElementById('error-messages');
        if (errorContainer) {
            errorContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            `;
        }
        console.error('💥', message);
    }

    /**
     * Masquer les messages d'erreur
     */
    hideErrorMessage() {
        const errorContainer = document.getElementById('error-messages');
        if (errorContainer) {
            errorContainer.innerHTML = '';
        }
    }

    /**
     * Configuration des événements
     */
    setupEventListeners() {
        // Bouton pause/play
        const toggleBtn = document.getElementById('toggle-monitoring');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                if (this.isRunning) {
                    this.stopMonitoring();
                    toggleBtn.innerHTML = '<i class="fas fa-play"></i> Reprendre';
                    toggleBtn.classList.replace('btn-warning', 'btn-success');
                } else {
                    this.startMonitoring();
                    toggleBtn.innerHTML = '<i class="fas fa-pause"></i> Pause';
                    toggleBtn.classList.replace('btn-success', 'btn-warning');
                }
            });
        }

        // Bouton refresh manuel
        const refreshBtn = document.getElementById('manual-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.fetchAndUpdateData();
            });
        }

        // Changement d'intervalle
        const intervalSelect = document.getElementById('refresh-interval');
        if (intervalSelect) {
            intervalSelect.addEventListener('change', (e) => {
                this.refreshInterval = parseInt(e.target.value) * 1000;

                if (this.isRunning) {
                    this.stopMonitoring();
                    this.startMonitoring();
                }

                console.log(`⏱️ Intervalle changé: ${this.refreshInterval}ms`);
            });
        }
    }
}

// Démarrage automatique au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    console.log('🌐 DOM chargé, démarrage du monitoring');
    window.dashboardMonitoring = new SimpleDashboardMonitoring();
});

// Nettoyage avant fermeture de la page
window.addEventListener('beforeunload', () => {
    if (window.dashboardMonitoring) {
        window.dashboardMonitoring.stopMonitoring();
    }
});