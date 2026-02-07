class SimpleDashboardMonitoring {
    constructor() {
        this.refreshInterval = 1000;
        this.intervalId = null;
        this.isRunning = false;
        this.errorCount = 0;
        this.maxErrors = 5;

        this.init();
    }

    init() {
        this.fetchAndUpdateData();
        this.startMonitoring();
        this.setupEventListeners();
    }

    startMonitoring() {
        if (this.isRunning) return;

        this.intervalId = setInterval(() => {
            this.fetchAndUpdateData(false); // ← Passer false, pas d'animation
        }, this.refreshInterval);

        this.isRunning = true;
    }

    stopMonitoring() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        this.isRunning = false;
    }

    async fetchAndUpdateData(isManual = false) {
        try {
            const refreshIcon = document.querySelector('#manual-refresh i');

            // Animer SEULEMENT si refresh manuel
            if (refreshIcon && isManual) {
                refreshIcon.classList.add('fa-spin');
            }

            const url = window.API_BASE_URL || '/dashboard/api/system-stats';
            const response = await fetch(url);

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            if (data.error) throw new Error(data.message || 'Erreur serveur');

            this.updateAllWidgets(data);
            this.errorCount = 0;
            this.hideErrorMessage();

            // Retirer l'animation
            if (refreshIcon && isManual) {
                setTimeout(() => {
                    refreshIcon.classList.remove('fa-spin');
                }, 500);
            }
        } catch (error) {
            console.error('❌ Erreur:', error);
            this.handleError(error);

            const refreshIcon = document.querySelector('#manual-refresh i');
            if (refreshIcon) {
                refreshIcon.classList.remove('fa-spin');
            }
        }
    }

    updateAllWidgets(data) {
        if (data.cpu) this.updateCpuWidget(data.cpu);
        if (data.memory) this.updateMemoryWidget(data.memory);
        if (data.disk) this.updateDiskWidget(data.disk);
        if (data.network) this.updateNetworkWidget(data.network);
        if (data.load) this.updateLoadWidget(data.load);

        this.updateLastRefresh();
    }

    updateCpuWidget(cpuData) {
        // CPU Global
        if (cpuData.cpu) {
            const globalPercentage = cpuData.cpu.percentage;
            const globalDiv = document.querySelector('#cpu-widget .cpu-global strong:last-child');
            if (globalDiv) globalDiv.textContent = `${globalPercentage}%`;

            const globalBar = document.querySelector('#cpu-widget .cpu-global .progress-bar');
            if (globalBar) {
                globalBar.style.width = `${globalPercentage}%`;
                globalBar.textContent = `${globalPercentage}%`;
                globalBar.className = 'progress-bar';
                if (globalPercentage > 90) globalBar.classList.add('bg-danger');
                else if (globalPercentage > 70) globalBar.classList.add('bg-warning');
                else globalBar.classList.add('bg-primary');
            }
        }

        // Cœurs individuels
        Object.entries(cpuData).forEach(([core, data]) => {
            if (core === 'cpu') return;

            const percentage = data.percentage;
            const coreElements = document.querySelectorAll('#cpu-widget .cpu-core');

            coreElements.forEach(el => {
                const coreLabel = el.querySelector('small:first-child');
                if (coreLabel && coreLabel.textContent.toUpperCase() === core.toUpperCase()) {
                    const percentageLabel = el.querySelector('small:last-child');
                    if (percentageLabel) percentageLabel.textContent = `${percentage}%`;

                    const bar = el.querySelector('.progress-bar');
                    if (bar) {
                        bar.style.width = `${percentage}%`;
                        bar.className = 'progress-bar';
                        if (percentage > 90) bar.classList.add('bg-danger');
                        else if (percentage > 70) bar.classList.add('bg-warning');
                        else bar.classList.add('bg-info');
                    }
                }
            });
        });
    }

    updateMemoryWidget(memoryData) {
        const percentage = memoryData.percentage;

        const percentageEl = document.querySelector('#memory-widget strong:last-child');
        if (percentageEl) percentageEl.textContent = `${percentage}%`;

        const bar = document.querySelector('#memory-widget .progress-bar');
        if (bar) {
            bar.style.width = `${percentage}%`;
            bar.textContent = `${percentage}%`;
            bar.className = 'progress-bar';
            if (percentage > 90) bar.classList.add('bg-danger');
            else if (percentage > 70) bar.classList.add('bg-warning');
            else bar.classList.add('bg-success');
        }

        const usageDiv = document.querySelector('#memory-widget .memory-usage');
        if (usageDiv) {
            usageDiv.innerHTML = `
                <div>${memoryData.formatted.used} / ${memoryData.formatted.total}</div>
                <div><small>Disponible: ${memoryData.formatted.available}</small></div>
            `;
        }
    }

    updateDiskWidget(diskData) {
        const percentage = diskData.percentage;

        const percentageEl = document.querySelector('#disk-widget strong:last-child');
        if (percentageEl) percentageEl.textContent = `${percentage}%`;

        const bar = document.querySelector('#disk-widget .progress-bar');
        if (bar) {
            bar.style.width = `${percentage}%`;
            bar.textContent = `${percentage}%`;
            bar.className = 'progress-bar';
            if (percentage > 90) bar.classList.add('bg-danger');
            else if (percentage > 70) bar.classList.add('bg-warning');
            else bar.classList.add('bg-info');
        }

        const usageDiv = document.querySelector('#disk-widget .disk-usage');
        if (usageDiv) {
            usageDiv.innerHTML = `
                <div>Utilisé: ${diskData.formatted.used}</div>
                <div>Libre: ${diskData.formatted.free}</div>
                <div><small>Total: ${diskData.formatted.total}</small></div>
            `;
        }
    }

    updateNetworkWidget(networkData) {
        const widget = document.getElementById('network-widget');
        if (!widget) return;

        const content = widget.querySelector('.widget-content');
        if (!content) return;

        let html = '';
        Object.entries(networkData).forEach(([interfaceName, stats]) => {
            html += `
                <div class="network-interface mb-3">
                    <strong>${interfaceName}</strong>
                    <div class="d-flex justify-content-between mt-1">
                        <span>↓ ${stats.formatted.rx}</span>
                        <span>↑ ${stats.formatted.tx}</span>
                    </div>
                </div>
            `;
        });

        content.innerHTML = html || '<div>Aucune interface réseau</div>';
    }

    updateLoadWidget(loadData) {
        const widget = document.getElementById('load-widget');
        if (!widget) return;

        const load1Text = widget.querySelector('.load-1min');
        if (load1Text) load1Text.textContent = `${loadData.load1} (${loadData.load1_percent}%)`;

        const load5Text = widget.querySelector('.load-5min');
        if (load5Text) load5Text.textContent = `${loadData.load5} (${loadData.load5_percent}%)`;

        const load15Text = widget.querySelector('.load-15min');
        if (load15Text) load15Text.textContent = `${loadData.load15} (${loadData.load15_percent}%)`;
    }

    updateLastRefresh() {
        const el = document.getElementById('last-refresh');
        if (el) {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('fr-FR');
        }
    }

    handleError(error) {
        this.errorCount++;
        this.showErrorMessage(`Erreur: ${error.message}`);

        if (this.errorCount >= this.maxErrors) {
            this.stopMonitoring();
            this.showErrorMessage(`Trop d'erreurs (${this.maxErrors}). Monitoring arrêté.`);
        }
    }

    showErrorMessage(message) {
        const container = document.getElementById('error-messages');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    }

    hideErrorMessage() {
        const container = document.getElementById('error-messages');
        if (container) container.innerHTML = '';
    }

    setupEventListeners() {
        // Pause/Play
        const toggleBtn = document.getElementById('toggle-cpu-details');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const details = document.getElementById('cpu-details');
                const icon = toggleBtn.querySelector('i');

                if (details.style.display === 'none') {
                    details.style.display = 'block';
                    toggleBtn.classList.add('active');
                } else {
                    details.style.display = 'none';
                    toggleBtn.classList.remove('active');
                }
            });
        }
        const toggleBtnMonito = document.getElementById('toggle-monitoring');
        if (toggleBtnMonito) {
            toggleBtnMonito.addEventListener('click', () => {
                if (this.isRunning) {
                    this.stopMonitoring();
                    toggleBtnMonito.innerHTML = '<i class="fas fa-play"></i> Reprendre';
                    toggleBtnMonito.classList.replace('btn-warning', 'btn-success');
                } else {
                    this.startMonitoring();
                    toggleBtnMonito.innerHTML = '<i class="fas fa-pause"></i> Pause';
                    toggleBtnMonito.classList.replace('btn-success', 'btn-warning');
                }
            });
        }

        // Refresh manuel
        const refreshBtn = document.getElementById('manual-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.fetchAndUpdateData(true); // ← Passer true pour animer
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
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.dashboardMonitoring = new SimpleDashboardMonitoring();
});

window.addEventListener('beforeunload', () => {
    if (window.dashboardMonitoring) {
        window.dashboardMonitoring.stopMonitoring();
    }
});