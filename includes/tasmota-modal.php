<!-- Tasmota-Gerät Hinzufügen/Bearbeiten Modal -->
<div class="modal fade" id="tasmotaDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-energy text-white">
                <h5 class="modal-title">
                    <i class="bi bi-wifi"></i>
                    <span id="tasmotaModalTitle">Tasmota-Gerät hinzufügen</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="tasmotaDeviceForm" action="geraete.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add_tasmota_device">
                    <input type="hidden" name="device_id" id="tasmota_device_id">
                    
                    <!-- Basis-Informationen -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label required">Gerätename</label>
                            <input type="text" class="form-control" name="name" id="tasmota_name" 
                                   placeholder="z.B. Waschmaschine" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kategorie</label>
                            <select class="form-select" name="category" id="tasmota_category">
                                <option value="Smart Home">Smart Home</option>
                                <option value="Küche">Küche</option>
                                <option value="Haushaltsgerät">Haushaltsgerät</option>
                                <option value="Unterhaltung">Unterhaltung</option>
                                <option value="Büro">Büro</option>
                                <option value="Beleuchtung">Beleuchtung</option>
                                <option value="Sonstiges">Sonstiges</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Tasmota-spezifische Einstellungen -->
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="bi bi-router"></i> Tasmota-Konfiguration
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label class="form-label required">IP-Adresse</label>
                                    <input type="text" class="form-control" name="tasmota_ip" id="tasmota_ip" 
                                           placeholder="192.168.1.100" required pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                                    <div class="form-text">
                                        IP-Adresse Ihrer Tasmota-Steckdose im lokalen Netzwerk
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-primary w-100" 
                                            id="testTasmotaConnection">
                                        <i class="bi bi-wifi"></i> Testen
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tasmota-Name</label>
                                    <input type="text" class="form-control" name="tasmota_name" id="tasmota_device_name" 
                                           placeholder="sonoff-01">
                                    <div class="form-text">
                                        Gerätename in Tasmota (optional)
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Abfrage-Intervall</label>
                                    <select class="form-select" name="tasmota_interval" id="tasmota_interval">
                                        <option value="60">1 Minute</option>
                                        <option value="300" selected>5 Minuten</option>
                                        <option value="600">10 Minuten</option>
                                        <option value="1800">30 Minuten</option>
                                        <option value="3600">1 Stunde</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tasmota_enabled" 
                                       id="tasmota_enabled" value="1" checked>
                                <label class="form-check-label" for="tasmota_enabled">
                                    <strong>Automatische Datenerfassung aktivieren</strong>
                                </label>
                                <div class="form-text">
                                    Verbrauchsdaten werden automatisch von der Tasmota-Steckdose abgerufen
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Verbindungstest-Ergebnis -->
                    <div id="tasmotaTestResult" class="alert alert-info d-none">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                <span class="visually-hidden">Teste...</span>
                            </div>
                            <span>Teste Verbindung zur Tasmota-Steckdose...</span>
                        </div>
                    </div>
                    
                    <!-- Live-Daten Vorschau -->
                    <div id="tasmotaLiveData" class="card border-success d-none">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-lightning-charge"></i> 
                                Live-Daten von der Steckdose
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="tasmotaEnergyDisplay">
                                <!-- Wird dynamisch befüllt -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Erweiterte Einstellungen -->
                    <div class="accordion mt-3" id="advancedSettings">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#advancedOptions">
                                    <i class="bi bi-gear me-2"></i>
                                    Erweiterte Einstellungen
                                </button>
                            </h2>
                            <div id="advancedOptions" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Maximale Leistung (Watt)</label>
                                            <input type="number" class="form-control" name="wattage" 
                                                   id="tasmota_wattage" placeholder="Auto-Erkennung">
                                            <div class="form-text">
                                                Wird automatisch aus Tasmota ausgelesen, falls leer
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Standby-Verbrauch (Watt)</label>
                                            <input type="number" class="form-control" name="standby_power" 
                                                   step="0.1" placeholder="z.B. 0.5">
                                            <div class="form-text">
                                                Verbrauch im Standby-Modus
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Notizen</label>
                                        <textarea class="form-control" name="notes" rows="2" 
                                                  placeholder="Zusätzliche Informationen zum Gerät..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-energy" id="saveTasmotaDevice">
                        <i class="bi bi-check-lg"></i>
                        Gerät speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tasmota Geräte-Karten (Für Integration in bestehende Geräteliste) -->
<style>
.tasmota-device-card {
    border-left: 4px solid #28a745 !important;
    position: relative;
}

.tasmota-device-card::before {
    content: "SMART";
    position: absolute;
    top: 10px;
    right: 10px;
    background: #28a745;
    color: white;
    font-size: 0.7em;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: bold;
}

.tasmota-controls {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.energy-data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.energy-data-item {
    text-align: center;
    padding: 10px;
    background: rgba(40, 167, 69, 0.1);
    border-radius: 8px;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.energy-value {
    font-size: 1.2em;
    font-weight: bold;
    color: #28a745;
    display: block;
}

.energy-label {
    font-size: 0.8em;
    color: #6c757d;
    margin-top: 5px;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.status-online { background-color: #28a745; }
.status-offline { background-color: #dc3545; }
.status-unknown { background-color: #ffc107; }
</style>

<!-- JavaScript für Tasmota Modal -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Verbindungstest
    document.getElementById('testTasmotaConnection').addEventListener('click', async function() {
        const ipInput = document.getElementById('tasmota_ip');
        const ip = ipInput.value.trim();
        
        if (!ip) {
            alert('Bitte geben Sie eine IP-Adresse ein');
            return;
        }
        
        const resultDiv = document.getElementById('tasmotaTestResult');
        const liveDataDiv = document.getElementById('tasmotaLiveData');
        
        // Test-UI anzeigen
        resultDiv.classList.remove('d-none');
        resultDiv.className = 'alert alert-info';
        resultDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                <span>Teste Verbindung zu ${ip}...</span>
            </div>
        `;
        
        try {
            const response = await fetch(`/api/tasmota.php?action=test&ip=${ip}`);
            const data = await response.json();
            
            if (data.success && data.energy_data) {
                // Erfolg
                resultDiv.className = 'alert alert-success';
                resultDiv.innerHTML = `
                    <i class="bi bi-check-circle"></i>
                    <strong>Verbindung erfolgreich!</strong> Tasmota-Gerät gefunden.
                `;
                
                // Live-Daten anzeigen
                displayLiveData(data.energy_data);
                liveDataDiv.classList.remove('d-none');
                
                // Geräte-Name automatisch setzen falls vorhanden
                if (data.raw_data && data.raw_data.Status && data.raw_data.Status.FriendlyName) {
                    document.getElementById('tasmota_device_name').value = 
                        data.raw_data.Status.FriendlyName[0];
                }
                
            } else {
                // Fehler
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Verbindung fehlgeschlagen!</strong><br>
                    ${data.error || 'Unbekannter Fehler'}
                `;
                liveDataDiv.classList.add('d-none');
            }
            
        } catch (error) {
            resultDiv.className = 'alert alert-danger';
            resultDiv.innerHTML = `
                <i class="bi bi-wifi-off"></i>
                <strong>Netzwerk-Fehler!</strong><br>
                Gerät unter ${ip} nicht erreichbar.
            `;
            liveDataDiv.classList.add('d-none');
        }
    });
    
    // Live-Daten anzeigen
    function displayLiveData(data) {
        const display = document.getElementById('tasmotaEnergyDisplay');
        display.innerHTML = `
            <div class="energy-data-grid">
                <div class="energy-data-item">
                    <span class="energy-value">${data.power || 0}</span>
                    <div class="energy-label">Watt</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.voltage || 0}</span>
                    <div class="energy-label">Volt</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.current || 0}</span>
                    <div class="energy-label">Ampere</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.energy_today || 0}</span>
                    <div class="energy-label">kWh heute</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.energy_total || 0}</span>
                    <div class="energy-label">kWh gesamt</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.power_factor || 0}</span>
                    <div class="energy-label">Leistungsfaktor</div>
                </div>
            </div>
        `;
        
        // Wattage automatisch setzen falls leer
        const wattageInput = document.getElementById('tasmota_wattage');
        if (!wattageInput.value && data.power > 0) {
            wattageInput.value = Math.ceil(data.power);
        }
    }
    
    // Modal reset beim Schließen
    document.getElementById('tasmotaDeviceModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('tasmotaTestResult').classList.add('d-none');
        document.getElementById('tasmotaLiveData').classList.add('d-none');
        document.getElementById('tasmotaDeviceForm').reset();
    });
});
</script>