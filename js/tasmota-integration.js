// js/tasmota-integration.js
// Tasmota Frontend-Integration für Stromtracker

class TasmotaManager {
    constructor() {
        this.apiUrl = '/api/tasmota.php';
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.startAutoRefresh();
    }
    
    bindEvents() {
        // Test-Button für Tasmota-Verbindung
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-tasmota-test]')) {
                this.testDevice(e.target.dataset.tasmotaTest);
            }
            
            if (e.target.matches('[data-tasmota-power]')) {
                const [ip, state] = e.target.dataset.tasmotaPower.split(',');
                this.togglePower(ip, state);
            }
            
            if (e.target.matches('[data-tasmota-collect]')) {
                this.collectAllDevices();
            }
        });
        
        // IP-Feld Validierung
        document.addEventListener('input', (e) => {
            if (e.target.matches('[name="tasmota_ip"]')) {
                this.validateIP(e.target);
            }
        });
    }
    
    /**
     * Tasmota-Gerät testen
     */
    async testDevice(ip) {
        if (!ip) return;
        
        const button = document.querySelector(`[data-tasmota-test="${ip}"]`);
        const statusDiv = document.getElementById(`tasmota-status-${ip.replace(/\./g, '_')}`);
        
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Teste...';
        }
        
        try {
            const response = await fetch(`${this.apiUrl}?action=test&ip=${ip}`);
            const data = await response.json();
            
            if (data.success && data.energy_data) {
                this.displayEnergyData(ip, data.energy_data);
                if (statusDiv) {
                    statusDiv.innerHTML = '<span class="badge bg-success">✓ Verbunden</span>';
                }
            } else {
                if (statusDiv) {
                    statusDiv.innerHTML = '<span class="badge bg-danger">✗ Fehler</span>';
                }
                console.error('Tasmota Fehler:', data);
            }
            
        } catch (error) {
            console.error('Verbindungsfehler:', error);
            if (statusDiv) {
                statusDiv.innerHTML = '<span class="badge bg-warning">⚠ Offline</span>';
            }
        }
        
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-wifi"></i> Testen';
        }
    }
    
    /**
     * Energiedaten anzeigen
     */
    displayEnergyData(ip, data) {
        const containerId = `energy-data-${ip.replace(/\./g, '_')}`;
        let container = document.getElementById(containerId);
        
        if (!container) {
            // Container erstellen
            const deviceCard = document.querySelector(`[data-device-ip="${ip}"]`);
            if (deviceCard) {
                container = document.createElement('div');
                container.id = containerId;
                container.className = 'mt-3';
                deviceCard.appendChild(container);
            } else {
                return;
            }
        }
        
        const html = `
            <div class="card border-success">
                <div class="card-body p-3">
                    <h6 class="card-title text-success mb-2">
                        <i class="bi bi-lightning-charge"></i> Live-Daten
                    </h6>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <small class="text-muted">Leistung</small><br>
                            <strong class="text-energy">${data.power || 0} W</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Spannung</small><br>
                            <strong>${data.voltage || 0} V</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Stromstärke</small><br>
                            <strong>${data.current || 0} A</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Heute</small><br>
                            <strong class="text-primary">${data.energy_today || 0} kWh</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Gestern</small><br>
                            <strong>${data.energy_yesterday || 0} kWh</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Gesamt</small><br>
                            <strong>${data.energy_total || 0} kWh</strong>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-clock"></i> ${new Date().toLocaleString('de-DE')}
                        </small>
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    }
    
    /**
     * Gerät ein/ausschalten
     */
    async togglePower(ip, currentState) {
        const newState = currentState === '1' ? '0' : '1';
        
        try {
            const response = await fetch(`${this.apiUrl}?action=power&ip=${ip}&state=${newState}`);
            const data = await response.json();
            
            if (data.POWER) {
                // Button-Status aktualisieren
                const button = document.querySelector(`[data-tasmota-power="${ip},${currentState}"]`);
                if (button) {
                    button.dataset.tasmotaPower = `${ip},${newState}`;
                    if (newState === '1') {
                        button.innerHTML = '<i class="bi bi-power text-success"></i> Ein';
                        button.className = button.className.replace('btn-outline-success', 'btn-success');
                    } else {
                        button.innerHTML = '<i class="bi bi-power text-danger"></i> Aus';
                        button.className = button.className.replace('btn-success', 'btn-outline-success');
                    }
                }
                
                // Toast anzeigen
                this.showToast(`Gerät ${newState === '1' ? 'eingeschaltet' : 'ausgeschaltet'}`, 'success');
            }
            
        } catch (error) {
            console.error('Power Toggle Fehler:', error);
            this.showToast('Fehler beim Schalten des Geräts', 'error');
        }
    }
    
    /**
     * Alle Tasmota-Geräte abfragen
     */
    async collectAllDevices() {
        const button = document.querySelector('[data-tasmota-collect]');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Sammle Daten...';
        }
        
        try {
            const response = await fetch(`${this.apiUrl}?action=collect`);
            const data = await response.json();
            
            if (data.success) {
                let successCount = 0;
                data.results.forEach(result => {
                    if (result.data && !result.error) {
                        this.displayEnergyData(result.device.tasmota_ip, result.data);
                        successCount++;
                    }
                });
                
                this.showToast(
                    `${successCount} von ${data.results.length} Geräten erfolgreich abgefragt`,
                    'success'
                );
                
                // Seite nach 2 Sekunden neu laden für aktualisierte Daten
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
            
        } catch (error) {
            console.error('Collect Error:', error);
            this.showToast('Fehler beim Sammeln der Daten', 'error');
        }
        
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-download"></i> Alle Daten sammeln';
        }
    }
    
    /**
     * IP-Adresse validieren
     */
    validateIP(input) {
        const ipPattern = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        
        if (input.value && !ipPattern.test(input.value)) {
            input.classList.add('is-invalid');
            
            let feedback = input.nextElementSibling;
            if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                input.parentNode.appendChild(feedback);
            }
            feedback.textContent = 'Bitte geben Sie eine gültige IP-Adresse ein (z.B. 192.168.1.100)';
        } else {
            input.classList.remove('is-invalid');
            const feedback = input.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.remove();
            }
        }
    }
    
    /**
     * Toast-Nachricht anzeigen
     */
    showToast(message, type = 'info') {
        // Bootstrap Toast oder einfache Alert-Alternative
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            // Bootstrap Toast Implementation
            const toastHtml = `
                <div class="toast align-items-center text-bg-${type === 'error' ? 'danger' : type}" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            // Toast Container erstellen falls nicht vorhanden
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                document.body.appendChild(container);
            }
            
            container.insertAdjacentHTML('beforeend', toastHtml);
            const toastElement = container.lastElementChild;
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // Toast nach dem Ausblenden entfernen
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        } else {
            // Fallback: Einfaches Alert
            alert(message);
        }
    }
    
    /**
     * Auto-Refresh für Live-Daten
     */
    startAutoRefresh() {
        // Alle 30 Sekunden Live-Daten aktualisieren
        setInterval(() => {
            const activeDevices = document.querySelectorAll('[data-tasmota-test]');
            activeDevices.forEach(button => {
                const ip = button.dataset.tasmotaTest;
                // Stille Aktualisierung ohne Button-Animation
                this.testDeviceSilent(ip);
            });
        }, 30000);
    }
    
    /**
     * Stille Geräteabfrage (ohne UI-Feedback)
     */
    async testDeviceSilent(ip) {
        try {
            const response = await fetch(`${this.apiUrl}?action=test&ip=${ip}`);
            const data = await response.json();
            
            if (data.success && data.energy_data) {
                this.displayEnergyData(ip, data.energy_data);
            }
        } catch (error) {
            // Fehler stillschweigend ignorieren
            console.debug('Silent refresh failed for', ip);
        }
    }
}

// CSS für Spinning Animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin { 
        animation: spin 1s linear infinite; 
    }
    
    .tasmota-device-card {
        border-left: 4px solid #28a745;
        transition: all 0.3s ease;
    }
    
    .tasmota-device-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
`;
document.head.appendChild(style);

// Automatisch initialisieren wenn DOM bereit ist
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.tasmotaManager = new TasmotaManager();
    });
} else {
    window.tasmotaManager = new TasmotaManager();
}