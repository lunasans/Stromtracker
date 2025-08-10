// js/enhanced-animations.js
// Safe Loading Version - verhindert doppelte Deklaration

// Prüfen ob bereits geladen
if (typeof window.EnhancedAnimations !== 'undefined') {
    console.log('[Enhanced Animations] Already loaded, skipping redeclaration');
} else {

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
     */
    safeClosest(target, selector) {
        const element = target?.target || target;
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
    
    enhanceStatsCardHover(card) {
        if (!card) return;
        
        // Subtle glow effect
        card.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(251, 191, 36, 0.3)';
        
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
        
        const statsValue = card.querySelector('.stats-value');
        if (statsValue) {
            statsValue.style.transform = '';
        }
    }
    
    enhanceButtonHover(button) {
        if (!button) return;
        
        // Einfacher Hover-Effekt ohne Ripple für bessere Performance
        button.style.transform = 'translateY(-2px)';
    }
    
    resetButtonHover(button) {
        if (!button) return;
        button.style.transform = '';
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
    
    // ========================================
    // Loading States
    // ========================================
    setupLoadingStates() {
        // Add CSS for animations if not present
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
            `;
            document.head.appendChild(style);
        }
    }
    
    // ========================================
    // Public API Methods
    // ========================================
    
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
    
    revealElements(elements, staggerDelay = 100) {
        if (!elements || !elements.length) return;
        
        elements.forEach((element, index) => {
            setTimeout(() => {
                this.revealElement(element);
            }, index * staggerDelay);
        });
    }
    
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
// Safe Loading & Error Handling
// ========================================

// Global Error Handler für unerwartete Fehler
window.addEventListener('error', (e) => {
    if (e.message && e.message.includes('closest')) {
        console.warn('[EnhancedAnimations] Caught closest() error:', e.message);
        e.preventDefault();
    }
});

// Sichere Initialisierung - nur wenn noch nicht vorhanden
let enhancedAnimationsInstance = null;

try {
    // Prüfen ob bereits eine Instanz existiert
    if (window.enhancedAnimations) {
        console.log('[Enhanced Animations] Instance already exists, using existing one');
        enhancedAnimationsInstance = window.enhancedAnimations;
    } else {
        // DOM bereit prüfen
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                enhancedAnimationsInstance = new EnhancedAnimations();
                window.enhancedAnimations = enhancedAnimationsInstance;
            });
        } else {
            // DOM bereits geladen
            enhancedAnimationsInstance = new EnhancedAnimations();
            window.enhancedAnimations = enhancedAnimationsInstance;
        }
    }
} catch (error) {
    console.error('[EnhancedAnimations] Failed to initialize:', error);
}

// Global verfügbar machen für manuelle Kontrolle
window.EnhancedAnimations = EnhancedAnimations;
if (!window.enhancedAnimations) {
    window.enhancedAnimations = enhancedAnimationsInstance;
}

} // Ende der if-Überprüfung für doppelte Deklaration