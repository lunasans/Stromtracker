// js/main.js
// Minimale JavaScript-Datei für eigene Funktionen

// Debug-Modus
const DEBUG = true;

// Utility-Funktionen
const Utils = {
    log: function(message) {
        if (DEBUG) {
            console.log('[Stromtracker]', message);
        }
    },
    
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('de-DE', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    },
    
    formatKwh: function(kwh) {
        return new Intl.NumberFormat('de-DE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(kwh) + ' kWh';
    }
};

// Basis-Initialisierung
document.addEventListener('DOMContentLoaded', function() {
    Utils.log('Main.js loaded successfully');
    
    // Hier können Sie eigene Funktionen hinzufügen
});

// Export für andere Scripts
window.StromtrackerUtils = Utils;