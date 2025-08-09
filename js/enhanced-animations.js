// js/enhanced-animations.js
// Fixed Version - Robuste Event Handling ohne closest() Fehler

class EnhancedAnimations {
    constructor() {
        this.initialized = false;
        this.observers = new Map();
        this.counters = new Map();
        
        this.init();
    }
    
    // ========================================
    // UTILITY - Sichere Element-Prüfung
    // ========================================
    
    /**
     * Sichere Implementierung von closest() mit Null-Checks
     * @param {Event|Element} target - Event target oder DOM Element
     * @param {string} selector - CSS Selector
     * @returns {Element|null}
     */
    safeClosest(target, selector) {
        // Event-Objekt extrahieren falls nötig
        const element = target?.target || target;
        
        // Prüfen ob element existiert und closest-Methode hat
        if (!element || typeof element.closest !== 'function') {
            return null;
        }
        
        try {
            return element.closest(selector);
        } catch (error) {
            console.warn('[EnhancedAnimations] Safe closest error:', error);
            return null;
        }
    }
    
    /**
     * Prüft ob ein Event-Target ein gültiges Element ist
     * @param {Event} event - Das Event-Objekt
     * @returns {boolean}
     */
    isValidEventTarget(event) {
        return event && 
               event.target && 
               event.target.nodeType === Node.ELEMENT_NODE &&
               typeof event.target.closest === 'function';
    }
    
    // ========================================
    // Initialisierung
    // ========================================
    init() {
        if (this.initialized) return;
        
        try {
            // DOM bereit prüfen
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupAnimations());
            } else {
                this.setupAnimations();
            }
            
            this.initialized = true;
            console.log('[EnhancedAnimations] Initialized successfully');
        } catch (error) {
            console.error('[EnhancedAnimations] Initialization failed:', error);
        }
    }
    
    setupAnimations() {
        this.setupIntersectionObserver();
        this.setupCountUpAnimations();
        this.setupStaggeredAnimations();
        this.setupHoverEffects();
        this.setupClickFeedback();
        this.setupLoadingStates();
    }
    
    // ========================================
    // Intersection Observer für Scroll-Animationen
    // ========================================
    setupIntersectionObserver() {
        if (!('IntersectionObserver' in window)) {
            console.warn('[EnhancedAnimations] IntersectionObserver not supported');
            return;
        }
        
        const observerOptions = {
            threshold: [0.1, 0.3, 0.5, 0.7],
            rootMargin: '50px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.animateOnScroll(entry.target, entry.intersectionRatio);
                }
            });
        }, observerOptions);
        
        // Elemente für Scroll-Animation registrieren
        const animateElements = document.querySelectorAll([
            '.stats-card-enhanced',
            '.card',
            '[data-animate]',
            '.animate-on-scroll'
        ].join(', '));
        
        animateElements.forEach(el => {
            observer.observe(el);
        });
        
        this.observers.set('scroll', observer);
    }
    
    animateOnScroll(element, ratio) {
        try {
            // Basis-Animation je nach Attribut oder Klasse
            const animationType = element.getAttribute('data-animate') || 'fade-up';
            
            switch (animationType) {
                case 'fade-up':
                    this.animateFadeUp(element);
                    break;
                case 'fade-in':
                    this.animateFadeIn(element);
                    break;
                case 'slide-left':
                    this.animateSlideLeft(element);
                    break;
                case 'scale':
                    this.animateScale(element);
                    break;
                default:
                    this.animateFadeUp(element);
            }
        } catch (error) {
            console.error('[EnhancedAnimations] Scroll animation error:', error);
        }
    }
    
    // ========================================
    // Animation Methoden
    // ========================================
    animateFadeUp(element) {
        element.style.cssText = `
            opacity: 1 !important;
            transform: translateY(0) !important;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1) !important;
        `;
    }
    
    animateFadeIn(element) {
        element.style.cssText = `
            opacity: 1 !important;
            transition: opacity 0.6s ease !important;
        `;
    }
    
    animateSlideLeft(element) {
        element.style.cssText = `
            opacity: 1 !important;
            transform: translateX(0) !important;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1) !important;
        `;
    }
    
    animateScale(element) {
        element.style.cssText = `
            opacity: 1 !important;
            transform: scale(1) !important;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1) !important;
        `;
    }
    
    // ========================================
    // Count-Up Animationen
    // ========================================
    setupCountUpAnimations() {
        const countElements = document.querySelectorAll('.count-up, [data-count-up]');
        
        countElements.forEach(el => {
            const target = parseFloat(el.getAttribute('data-target') || el.textContent);
            const duration = parseInt(el.getAttribute('data-duration')) || 2000;
            const decimals = el.hasAttribute('data-decimals') ? 
                parseInt(el.getAttribute('data-decimals')) : 0;
            
            if (!isNaN(target)) {
                this.setupCountUpObserver(el, target, duration, decimals);
            }
        });
    }
    
    setupCountUpObserver(element, target, duration, decimals) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !element.hasAttribute('data-counted')) {
                    this.animateCountUp(element, target, duration, decimals);
                    element.setAttribute('data-counted', 'true');
                }
            });
        }, { threshold: 0.5 });
        
        observer.observe(element);
    }
    
    animateCountUp(element, target, duration, decimals) {
        try {
            const start = 0;
            const range = target - start;
            const startTime = performance.now();
            
            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing-Funktion (ease-out)
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const currentValue = start + (range * easeOut);
                
                // Wert formatieren und anzeigen
                const displayValue = this.formatCountUpValue(currentValue, decimals);
                element.textContent = displayValue;
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    // Final value sicherstellen
                    element.textContent = this.formatCountUpValue(target, decimals);
                }
            };
            
            requestAnimationFrame(animate);
            
        } catch (error) {
            console.error('[EnhancedAnimations] Count-up animation error:', error);
            element.textContent = this.formatCountUpValue(target, decimals);
        }
    }
    
    formatCountUpValue(value, decimals) {
        if (decimals === 0) {
            return Math.floor(value).toLocaleString('de-DE');
        }
        return value.toLocaleString('de-DE', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }
    
    // ========================================
    // Staggered Animationen
    // ========================================
    setupStaggeredAnimations() {
        const staggerContainers = document.querySelectorAll('.animate-stagger');
        
        staggerContainers.forEach(container => {
            const children = Array.from(container.children);
            const staggerDelay = parseInt(container.getAttribute('data-stagger-delay')) || 100;
            
            children.forEach((child, index) => {
                child.style.opacity = '0';
                child.style.transform = 'translateY(20px)';
                child.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                child.style.transitionDelay = (index * staggerDelay) + 'ms';
            });
            
            // Observer für Container
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        children.forEach(child => {
                            child.style.opacity = '1';
                            child.style.transform = 'translateY(0)';
                        });
                        observer.unobserve(container);
                    }
                });
            }, { threshold: 0.2 });
            
            observer.observe(container);
        });
    }
    
    // ========================================
    // Enhanced Hover Effects - FIXED VERSION
    // ========================================
    setupHoverEffects() {
        try {
            // Mouseenter Event - mit sicherer closest() Verwendung
            document.addEventListener('mouseenter', (e) => {
                if (!this.isValidEventTarget(e)) return;
                
                // Energy Indicator Hover
                const energyIndicator = this.safeClosest(e, '.energy-indicator');
                if (energyIndicator) {
                    this.enhanceEnergyIndicator(energyIndicator);
                }
                
                // Stats Card Hover
                const statsCard = this.safeClosest(e, '.stats-card-enhanced');
                if (statsCard) {
                    this.enhanceStatsCardHover(statsCard);
                }
                
                // Button Hover
                const button = this.safeClosest(e, '.btn-enhanced');
                if (button) {
                    this.enhanceButtonHover(button);
                }
            }, { passive: true, capture: true });
            
            // Mouseleave Event - mit sicherer closest() Verwendung
            document.addEventListener('mouseleave', (e) => {
                if (!this.isValidEventTarget(e)) return;
                
                // Energy Indicator Reset
                const energyIndicator = this.safeClosest(e, '.energy-indicator');
                if (energyIndicator) {
                    this.resetEnergyIndicator(energyIndicator);
                }
                
                // Stats Card Reset
                const statsCard = this.safeClosest(e, '.stats-card-enhanced');
                if (statsCard) {
                    this.resetStatsCardHover(statsCard);
                }
                
                // Button Reset
                const button = this.safeClosest(e, '.btn-enhanced');
                if (button) {
                    this.resetButtonHover(button);
                }
            }, { passive: true, capture: true });
            
        } catch (error) {
            console.error('[EnhancedAnimations] Hover effects setup error:', error);
        }
    }
    
    enhanceEnergyIndicator(indicator) {
        if (!indicator) return;
        
        indicator.style.transform = 'scale(1.3)';
        indicator.style.filter = 'brightness(1.2)';
        indicator.style.animationDuration = '1s';
    }
    
    resetEnergyIndicator(indicator) {
        if (!indicator) return;
        
        indicator.style.transform = '';
        indicator.style.filter = '';
        indicator.style.animationDuration = '';
    }
    
    enhanceStatsCardHover(card) {
        if (!card) return;
        
        // Subtle glow effect
        card.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(251, 191, 36, 0.3)';
        
        // Enhance energy indicator if present
        const indicator = card.querySelector('.energy-indicator');
        if (indicator) {
            this.enhanceEnergyIndicator(indicator);
        }
        
        // Animate stats value
        const statsValue = card.querySelector('.stats-value');
        if (statsValue) {
            statsValue.style.transform = 'scale(1.05)';
            statsValue.style.transition = 'transform 0.3s ease';
        }
    }
    
    resetStatsCardHover(card) {
        if (!card) return;
        
        card.style.boxShadow = '';
        
        const indicator = card.querySelector('.energy-indicator');
        if (indicator) {
            this.resetEnergyIndicator(indicator);
        }
        
        const statsValue = card.querySelector('.stats-value');
        if (statsValue) {
            statsValue.style.transform = '';
        }
    }
    
    enhanceButtonHover(button) {
        if (!button) return;
        
        // Ripple effect
        const existingRipple = button.querySelector('.button-ripple');
        if (existingRipple) return; // Prevent multiple ripples
        
        const ripple = document.createElement('span');
        ripple.className = 'button-ripple';
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
            z-index: 0;
        `;
        
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (rect.width / 2 - size / 2) + 'px';
        ripple.style.top = (rect.height / 2 - size / 2) + 'px';
        
        button.style.position = 'relative';
        button.appendChild(ripple);
        
        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 600);
    }
    
    resetButtonHover(button) {
        if (!button) return;
        // Cleanup wird durch timeout gehandelt
    }
    
    // ========================================
    // Click Feedback - FIXED VERSION
    // ========================================
    setupClickFeedback() {
        try {
            document.addEventListener('click', (e) => {
                if (!this.isValidEventTarget(e)) return;
                
                // Stats Card Click
                const statsCard = this.safeClosest(e, '.stats-card-enhanced');
                if (statsCard) {
                    this.animateStatsCardClick(statsCard);
                }
                
                // Button Click
                const button = this.safeClosest(e, '.btn-enhanced');
                if (button) {
                    this.animateButtonClick(button, e);
                }
                
                // General interactive elements
                const feedbackElement = this.safeClosest(e, '[data-click-feedback]');
                if (feedbackElement) {
                    this.animateClickFeedback(feedbackElement);
                }
            }, { passive: true });
            
        } catch (error) {
            console.error('[EnhancedAnimations] Click feedback setup error:', error);
        }
    }
    
    animateStatsCardClick(card) {
        if (!card) return;
        
        card.style.transform = 'scale(0.98)';
        card.style.transition = 'transform 0.1s ease';
        
        setTimeout(() => {
            card.style.transform = '';
            card.style.transition = '';
        }, 150);
        
        // Value pulse animation
        const statsValue = card.querySelector('.stats-value');
        if (statsValue) {
            statsValue.classList.add('pulse-animation');
            setTimeout(() => {
                statsValue.classList.remove('pulse-animation');
            }, 600);
        }
    }
    
    animateButtonClick(button, event) {
        if (!button) return;
        
        // Scale animation
        button.style.transform = 'scale(0.95)';
        button.style.transition = 'transform 0.1s ease';
        
        setTimeout(() => {
            button.style.transform = '';
            button.style.transition = '';
        }, 150);
    }
    
    animateClickFeedback(element) {
        if (!element) return;
        
        element.style.transform = 'scale(0.97)';
        element.style.transition = 'transform 0.15s ease';
        
        setTimeout(() => {
            element.style.transform = '';
            element.style.transition = '';
        }, 150);
    }
    
    // ========================================
    // Loading States
    // ========================================
    setupLoadingStates() {
        // Add CSS for pulse animation if not present
        if (!document.getElementById('enhanced-animations-styles')) {
            const style = document.createElement('style');
            style.id = 'enhanced-animations-styles';
            style.textContent = `
                .pulse-animation {
                    animation: pulse-scale 0.6s ease-in-out;
                }
                
                @keyframes pulse-scale {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                    100% { transform: scale(1); }
                }
                
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // ========================================
    // Public API Methods
    // ========================================
    
    /**
     * Element mit Animation einblenden
     */
    revealElement(element, direction = 'up') {
        if (!element) return;
        
        const directions = {
            up: 'translateY(30px)',
            down: 'translateY(-30px)',
            left: 'translateX(30px)',
            right: 'translateX(-30px)',
            scale: 'scale(0.8)'
        };
        
        element.style.opacity = '0';
        element.style.transform = directions[direction] || directions.up;
        element.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        
        requestAnimationFrame(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0) translateX(0) scale(1)';
        });
    }
    
    /**
     * Mehrere Elemente mit Verzögerung einblenden
     */
    revealElements(elements, staggerDelay = 100) {
        if (!elements || !elements.length) return;
        
        elements.forEach((element, index) => {
            setTimeout(() => {
                this.revealElement(element);
            }, index * staggerDelay);
        });
    }
    
    /**
     * Performance-friendly debounce
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Cleanup-Methode
     */
    destroy() {
        try {
            this.observers.forEach(observer => observer.disconnect());
            this.observers.clear();
            this.counters.clear();
            this.initialized = false;
            
            console.log('[EnhancedAnimations] Destroyed successfully');
        } catch (error) {
            console.error('[EnhancedAnimations] Destroy error:', error);
        }
    }
}

// ========================================
// Auto-Initialisierung & Error Handling
// ========================================

// Global Error Handler für unerwartete Fehler
window.addEventListener('error', (e) => {
    if (e.message && e.message.includes('closest')) {
        console.warn('[EnhancedAnimations] Caught closest() error:', e.message);
        // Event wird nicht weiter propagiert um andere Scripts nicht zu beeinträchtigen
        e.preventDefault();
    }
});

// Sichere Initialisierung
let enhancedAnimationsInstance = null;

try {
    // DOM bereit prüfen
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            enhancedAnimationsInstance = new EnhancedAnimations();
        });
    } else {
        // DOM bereits geladen
        enhancedAnimationsInstance = new EnhancedAnimations();
    }
} catch (error) {
    console.error('[EnhancedAnimations] Failed to initialize:', error);
}

// Global verfügbar machen für manuelle Kontrolle
window.EnhancedAnimations = EnhancedAnimations;
window.enhancedAnimations = enhancedAnimationsInstance;