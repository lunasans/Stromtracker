// js/enhanced-animations.js
// Phase 1: Basic Micro-Animations & Enhanced Interactions

class EnhancedAnimations {
    constructor() {
        this.initialized = false;
        this.observers = new Map();
        this.counters = new Map();
        
        this.init();
    }
    
    // ========================================
    // Initialisierung
    // ========================================
    init() {
        if (this.initialized) return;
        
        try {
            this.setupIntersectionObserver();
            this.setupCountUpAnimations();
            this.setupStaggeredAnimations();
            this.setupHoverEffects();
            this.setupClickFeedback();
            this.setupLoadingStates();
            
            this.initialized = true;
            console.log('[EnhancedAnimations] Initialized successfully');
        } catch (error) {
            console.error('[EnhancedAnimations] Initialization failed:', error);
        }
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
            // Basis-Animation hinzufügen
            if (!element.classList.contains('animated')) {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                element.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                
                // Staggered Delay basierend auf Position
                const delay = this.calculateStaggerDelay(element);
                element.style.transitionDelay = delay + 'ms';
                
                requestAnimationFrame(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                    element.classList.add('animated');
                });
            }
            
            // Parallax-Effekt für bestimmte Elemente
            if (element.hasAttribute('data-parallax')) {
                const speed = parseFloat(element.getAttribute('data-parallax')) || 0.5;
                const yPos = -(ratio * speed * 50);
                element.style.transform = `translateY(${yPos}px)`;
            }
        } catch (error) {
            console.error('[EnhancedAnimations] Scroll animation error:', error);
        }
    }
    
    calculateStaggerDelay(element) {
        // Berechne Verzögerung basierend auf Element-Position
        const rect = element.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const distanceFromTop = rect.top;
        
        // Je näher zur Mitte, desto weniger Verzögerung
        const normalizedDistance = Math.abs(distanceFromTop - viewportHeight / 2) / viewportHeight;
        return Math.min(normalizedDistance * 200, 500);
    }
    
    // ========================================
    // Count-Up Animationen
    // ========================================
    setupCountUpAnimations() {
        const countElements = document.querySelectorAll('[data-countup]');
        
        countElements.forEach(el => {
            const target = parseFloat(el.getAttribute('data-countup'));
            const duration = parseInt(el.getAttribute('data-duration')) || 2000;
            const decimals = (el.getAttribute('data-decimals')) ? parseInt(el.getAttribute('data-decimals')) : 0;
            
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
            
            const counter = {
                element,
                target,
                current: start,
                startTime,
                duration
            };
            
            this.counters.set(element, counter);
            
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
                    this.counters.delete(element);
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
    // Enhanced Hover Effects
    // ========================================
    setupHoverEffects() {
        // Energy Indicator Hover
        document.addEventListener('mouseenter', (e) => {
            if (e.target.closest('.energy-indicator')) {
                const indicator = e.target.closest('.energy-indicator');
                this.enhanceEnergyIndicator(indicator);
            }
            
            // Stats Card Hover
            if (e.target.closest('.stats-card-enhanced')) {
                const card = e.target.closest('.stats-card-enhanced');
                this.enhanceStatsCardHover(card);
            }
            
            // Button Hover
            if (e.target.closest('.btn-enhanced')) {
                const button = e.target.closest('.btn-enhanced');
                this.enhanceButtonHover(button);
            }
        }, true);
        
        document.addEventListener('mouseleave', (e) => {
            if (e.target.closest('.energy-indicator')) {
                const indicator = e.target.closest('.energy-indicator');
                this.resetEnergyIndicator(indicator);
            }
            
            if (e.target.closest('.stats-card-enhanced')) {
                const card = e.target.closest('.stats-card-enhanced');
                this.resetStatsCardHover(card);
            }
            
            if (e.target.closest('.btn-enhanced')) {
                const button = e.target.closest('.btn-enhanced');
                this.resetButtonHover(button);
            }
        }, true);
    }
    
    enhanceEnergyIndicator(indicator) {
        indicator.style.transform = 'scale(1.3)';
        indicator.style.filter = 'brightness(1.2)';
        indicator.style.animationDuration = '1s';
    }
    
    resetEnergyIndicator(indicator) {
        indicator.style.transform = '';
        indicator.style.filter = '';
        indicator.style.animationDuration = '';
    }
    
    enhanceStatsCardHover(card) {
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
        // Ripple effect
        const ripple = document.createElement('span');
        ripple.className = 'button-ripple';
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (rect.width / 2 - size / 2) + 'px';
        ripple.style.top = (rect.height / 2 - size / 2) + 'px';
        
        button.appendChild(ripple);
        
        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 600);
    }
    
    resetButtonHover(button) {
        // Cleanup wird durch timeout gehandelt
    }
    
    // ========================================
    // Click Feedback
    // ========================================
    setupClickFeedback() {
        document.addEventListener('click', (e) => {
            // Stats Card Click
            if (e.target.closest('.stats-card-enhanced')) {
                this.animateStatsCardClick(e.target.closest('.stats-card-enhanced'));
            }
            
            // Button Click
            if (e.target.closest('.btn-enhanced')) {
                this.animateButtonClick(e.target.closest('.btn-enhanced'), e);
            }
            
            // General interactive elements
            if (e.target.closest('[data-click-feedback]')) {
                this.animateClickFeedback(e.target.closest('[data-click-feedback]'));
            }
        });
    }
    
    animateStatsCardClick(card) {
        card.style.transform = 'scale(0.98)';
        card.style.transition = 'transform 0.1s ease';
        
        setTimeout(() => {
            card.style.transform = '';
            card.style.transition = '';
        }, 150);
        
        // Value pulse animation
        const statsValue = card.querySelector('.stats-value');
        if (statsValue) {
            statsValue.classList.add('animate-pulse-once');
            setTimeout(() => {
                statsValue.classList.remove('animate-pulse-once');
            }, 600);
        }
    }
    
    animateButtonClick(button, event) {
        // Create click ripple
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: clickRipple 0.4s ease-out;
            pointer-events: none;
            z-index: 1;
        `;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
        
        button.appendChild(ripple);
        
        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 400);
    }
    
    animateClickFeedback(element) {
        element.style.transform = 'scale(0.95)';
        setTimeout(() => {
            element.style.transform = '';
        }, 100);
    }
    
    // ========================================
    // Loading States
    // ========================================
    setupLoadingStates() {
        // Auto-setup für loading skeletons
        document.querySelectorAll('.loading-skeleton').forEach(skeleton => {
            this.animateLoadingSkeleton(skeleton);
        });
    }
    
    animateLoadingSkeleton(skeleton) {
        skeleton.style.background = `
            linear-gradient(90deg, 
                var(--neutral-200) 25%, 
                var(--neutral-100) 50%, 
                var(--neutral-200) 75%
            )
        `;
        skeleton.style.backgroundSize = '200% 100%';
        skeleton.style.animation = 'loading 1.5s infinite';
    }
    
    // ========================================
    // Utility Methods
    // ========================================
    
    // Smooth reveal animation
    revealElement(element, direction = 'up') {
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
    
    // Batch reveal für mehrere Elemente
    revealElements(elements, staggerDelay = 100) {
        elements.forEach((element, index) => {
            setTimeout(() => {
                this.revealElement(element);
            }, index * staggerDelay);
        });
    }
    
    // Theme-aware color animation
    animateColorChange(element, property, fromColor, toColor, duration = 300) {
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Simple color interpolation (works with CSS custom properties)
            element.style.setProperty(property, toColor);
            element.style.transition = `${property} ${duration}ms ease`;
            
            if (progress >= 1) {
                element.style.transition = '';
            }
        };
        
        requestAnimationFrame(animate);
    }
    
    // Performance-friendly debounce
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
    
    // Cleanup-Methode
    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers.clear();
        this.counters.clear();
        this.initialized = false;
    }
}

// ========================================
// Additional Animation Keyframes (via JavaScript)
// ========================================
function injectAnimationKeyframes() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        @keyframes clickRipple {
            to {
                transform: scale(3);
                opacity: 0;
            }
        }
        
        @keyframes animate-pulse-once {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .animate-pulse-once {
            animation: animate-pulse-once 0.6s ease-in-out;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes bounce-subtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .animate-bounce-subtle {
            animation: bounce-subtle 2s ease-in-out infinite;
        }
    `;
    document.head.appendChild(style);
}

// ========================================
// Initialisierung
// ========================================
let enhancedAnimationsInstance = null;

function initEnhancedAnimations() {
    try {
        if (!enhancedAnimationsInstance) {
            // Keyframes injizieren
            injectAnimationKeyframes();
            
            // Animations-System initialisieren
            enhancedAnimationsInstance = new EnhancedAnimations();
            
            // Global verfügbar machen
            window.enhancedAnimations = enhancedAnimationsInstance;
            
            console.log('[EnhancedAnimations] System initialized successfully');
        }
    } catch (error) {
        console.error('[EnhancedAnimations] Failed to initialize:', error);
    }
}

// DOM Ready Detection
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEnhancedAnimations);
} else {
    initEnhancedAnimations();
}

// Export für Module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { EnhancedAnimations, initEnhancedAnimations };
}