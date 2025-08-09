// js/enhanced-loading.js
// Enhanced Loading States & Performance Management

class EnhancedLoading {
    constructor() {
        this.loadingStates = new Map();
        this.observers = new Map();
        this.performanceMetrics = {
            loadStart: performance.now(),
            criticalResources: [],
            lazyResources: []
        };
        
        this.init();
    }
    
    // ========================================
    // Initialisierung
    // ========================================
    init() {
        try {
            this.setupIntersectionObserver();
            this.setupPerformanceObserver();
            this.setupCriticalResourceLoading();
            this.setupLazyLoading();
            this.setupProgressiveEnhancement();
            
            console.log('[Enhanced Loading] Initialized successfully');
        } catch (error) {
            console.error('[Enhanced Loading] Initialization failed:', error);
        }
    }
    
    // ========================================
    // Intersection Observer für Lazy Loading
    // ========================================
    setupIntersectionObserver() {
        if (!('IntersectionObserver' in window)) {
            console.warn('[Enhanced Loading] IntersectionObserver not supported');
            return;
        }
        
        // Lazy Loading Observer
        const lazyObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadLazyContent(entry.target);
                    lazyObserver.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.1
        });
        
        // Chart Loading Observer
        const chartObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadChart(entry.target);
                    chartObserver.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '100px 0px',
            threshold: 0.1
        });
        
        this.observers.set('lazy', lazyObserver);
        this.observers.set('chart', chartObserver);
        
        // Register elements
        this.registerLazyElements();
    }
    
    registerLazyElements() {
        // Lazy Loading Elements
        document.querySelectorAll('[data-lazy]').forEach(el => {
            this.observers.get('lazy').observe(el);
        });
        
        // Chart Elements
        document.querySelectorAll('.chart-loading, [data-chart-lazy]').forEach(el => {
            this.observers.get('chart').observe(el);
        });
    }
    
    // ========================================
    // Performance Observer
    // ========================================
    setupPerformanceObserver() {
        if (!('PerformanceObserver' in window)) {
            console.warn('[Enhanced Loading] PerformanceObserver not supported');
            return;
        }
        
        try {
            // Largest Contentful Paint
            const lcpObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                const lastEntry = entries[entries.length - 1];
                this.performanceMetrics.lcp = lastEntry.startTime;
                this.updatePerformanceUI();
            });
            lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
            
            // First Input Delay
            const fidObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                entries.forEach(entry => {
                    this.performanceMetrics.fid = entry.processingStart - entry.startTime;
                    this.updatePerformanceUI();
                });
            });
            fidObserver.observe({ entryTypes: ['first-input'] });
            
            // Cumulative Layout Shift
            const clsObserver = new PerformanceObserver((list) => {
                let clsValue = 0;
                const entries = list.getEntries();
                entries.forEach(entry => {
                    if (!entry.hadRecentInput) {
                        clsValue += entry.value;
                    }
                });
                this.performanceMetrics.cls = clsValue;
                this.updatePerformanceUI();
            });
            clsObserver.observe({ entryTypes: ['layout-shift'] });
            
        } catch (error) {
            console.warn('[Enhanced Loading] Performance Observer setup failed:', error);
        }
    }
    
    updatePerformanceUI() {
        const performanceElements = document.querySelectorAll('[data-performance]');
        
        performanceElements.forEach(el => {
            const metric = el.getAttribute('data-performance');
            const value = this.performanceMetrics[metric];
            
            if (value !== undefined) {
                el.textContent = this.formatPerformanceMetric(metric, value);
                el.className = this.getPerformanceClass(metric, value);
            }
        });
    }
    
    formatPerformanceMetric(metric, value) {
        switch (metric) {
            case 'lcp':
                return `${Math.round(value)}ms`;
            case 'fid':
                return `${Math.round(value)}ms`;
            case 'cls':
                return value.toFixed(3);
            default:
                return value.toString();
        }
    }
    
    getPerformanceClass(metric, value) {
        const thresholds = {
            lcp: { good: 2500, poor: 4000 },
            fid: { good: 100, poor: 300 },
            cls: { good: 0.1, poor: 0.25 }
        };
        
        const threshold = thresholds[metric];
        if (!threshold) return '';
        
        if (value <= threshold.good) return 'text-success';
        if (value <= threshold.poor) return 'text-warning';
        return 'text-danger';
    }
    
    // ========================================
    // Critical Resource Loading
    // ========================================
    setupCriticalResourceLoading() {
        // Critical CSS Loading
        this.loadCriticalCSS();
        
        // Critical JavaScript Loading
        this.loadCriticalJS();
        
        // Font Loading
        this.optimizeFontLoading();
    }
    
    loadCriticalCSS() {
        const criticalCSS = [
            'css/enhanced-design-system.css',
            'css/enhanced-utilities.css'
        ];
        
        criticalCSS.forEach(href => {
            this.loadCSS(href, true);
        });
    }
    
    loadCriticalJS() {
        const criticalJS = [
            'js/enhanced-animations.js',
            'js/enhanced-forms.js'
        ];
        
        criticalJS.forEach(src => {
            this.loadJS(src, true);
        });
    }
    
    optimizeFontLoading() {
        if ('fonts' in document) {
            // Preload critical fonts
            const criticalFonts = [
                { family: 'Inter', weight: '400' },
                { family: 'Inter', weight: '600' }
            ];
            
            criticalFonts.forEach(font => {
                document.fonts.load(`${font.weight} 16px ${font.family}`);
            });
            
            document.fonts.ready.then(() => {
                document.body.classList.add('fonts-loaded');
                this.performanceMetrics.fontsLoaded = performance.now();
            });
        }
    }
    
    // ========================================
    // Lazy Loading Implementation
    // ========================================
    loadLazyContent(element) {
        const type = element.getAttribute('data-lazy');
        const src = element.getAttribute('data-src');
        
        // Show loading state
        this.showLoadingState(element);
        
        switch (type) {
            case 'image':
                this.loadLazyImage(element, src);
                break;
            case 'component':
                this.loadLazyComponent(element, src);
                break;
            case 'iframe':
                this.loadLazyIframe(element, src);
                break;
            case 'data':
                this.loadLazyData(element, src);
                break;
            default:
                this.loadGenericContent(element, src);
        }
    }
    
    loadLazyImage(element, src) {
        const img = new Image();
        img.onload = () => {
            element.src = src;
            element.classList.add('loaded');
            this.hideLoadingState(element);
        };
        img.onerror = () => {
            element.classList.add('error');
            this.hideLoadingState(element);
        };
        img.src = src;
    }
    
    loadLazyComponent(element, componentName) {
        import(`./components/${componentName}.js`)
            .then(module => {
                const component = new module.default();
                component.render(element);
                this.hideLoadingState(element);
            })
            .catch(error => {
                console.error(`Failed to load component ${componentName}:`, error);
                this.hideLoadingState(element);
            });
    }
    
    loadLazyIframe(element, src) {
        element.src = src;
        element.onload = () => {
            this.hideLoadingState(element);
        };
    }
    
    async loadLazyData(element, endpoint) {
        try {
            const response = await fetch(endpoint);
            const data = await response.json();
            
            // Trigger custom event with data
            element.dispatchEvent(new CustomEvent('dataLoaded', {
                detail: data
            }));
            
            this.hideLoadingState(element);
        } catch (error) {
            console.error(`Failed to load data from ${endpoint}:`, error);
            this.hideLoadingState(element);
        }
    }
    
    // ========================================
    // Chart Loading
    // ========================================
    loadChart(element) {
        const chartType = element.getAttribute('data-chart-type') || 'line';
        const dataSource = element.getAttribute('data-chart-data');
        
        // Show skeleton loading
        this.showChartSkeleton(element);
        
        if (dataSource) {
            this.loadChartData(element, dataSource, chartType);
        } else {
            // Use static data
            setTimeout(() => {
                this.renderChart(element, chartType, this.getDefaultChartData());
            }, 500);
        }
    }
    
    async loadChartData(element, dataSource, chartType) {
        try {
            const response = await fetch(dataSource);
            const data = await response.json();
            
            setTimeout(() => {
                this.renderChart(element, chartType, data);
            }, 300); // Minimum loading time for UX
            
        } catch (error) {
            console.error(`Failed to load chart data:`, error);
            this.showChartError(element);
        }
    }
    
    renderChart(element, type, data) {
        // Replace skeleton with canvas
        const canvas = document.createElement('canvas');
        canvas.id = element.id || 'chart-' + Date.now();
        
        element.innerHTML = '';
        element.appendChild(canvas);
        element.classList.remove('chart-loading');
        element.classList.add('chart-loaded');
        
        // Initialize Chart.js (if available)
        if (typeof Chart !== 'undefined') {
            new Chart(canvas, {
                type: type,
                data: data,
                options: this.getChartOptions(type)
            });
        }
    }
    
    showChartSkeleton(element) {
        element.innerHTML = `
            <div class="chart-skeleton">
                <div class="chart-skeleton-bar"></div>
                <div class="chart-skeleton-bar"></div>
                <div class="chart-skeleton-bar"></div>
                <div class="chart-skeleton-bar"></div>
                <div class="chart-skeleton-bar"></div>
                <div class="chart-skeleton-bar"></div>
            </div>
        `;
    }
    
    showChartError(element) {
        element.innerHTML = `
            <div class="chart-error text-center py-4">
                <i class="bi bi-exclamation-triangle text-warning display-4"></i>
                <p class="text-muted mt-2">Chart konnte nicht geladen werden</p>
            </div>
        `;
    }
    
    getDefaultChartData() {
        return {
            labels: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun'],
            datasets: [{
                label: 'Verbrauch',
                data: [420, 435, 445, 410, 465, 450],
                borderColor: 'var(--energy-500)',
                backgroundColor: 'rgba(251, 191, 36, 0.1)',
                tension: 0.4
            }]
        };
    }
    
    getChartOptions(type) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: type !== 'doughnut'
                }
            },
            scales: type !== 'doughnut' ? {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            } : {}
        };
    }
    
    // ========================================
    // Loading State Management
    // ========================================
    showLoadingState(element, options = {}) {
        const loadingId = this.generateLoadingId();
        const loadingType = options.type || 'skeleton';
        
        this.loadingStates.set(element, loadingId);
        element.classList.add('loading-state', 'is-loading');
        element.setAttribute('aria-busy', 'true');
        
        // Add loading announcement for screen readers
        this.announceLoading(element, 'Inhalt wird geladen...');
        
        switch (loadingType) {
            case 'skeleton':
                this.showSkeletonLoading(element, options);
                break;
            case 'spinner':
                this.showSpinnerLoading(element, options);
                break;
            case 'progress':
                this.showProgressLoading(element, options);
                break;
            default:
                this.showDefaultLoading(element);
        }
    }
    
    hideLoadingState(element) {
        const loadingId = this.loadingStates.get(element);
        if (!loadingId) return;
        
        element.classList.remove('loading-state', 'is-loading');
        element.removeAttribute('aria-busy');
        
        // Remove loading content
        const loadingElements = element.querySelectorAll('.skeleton, .spinner, .loading-content');
        loadingElements.forEach(el => el.remove());
        
        // Announce completion
        this.announceLoading(element, 'Inhalt geladen');
        
        this.loadingStates.delete(element);
        
        // Trigger loaded event
        element.dispatchEvent(new CustomEvent('contentLoaded'));
    }
    
    showSkeletonLoading(element, options) {
        const skeletonType = options.skeletonType || 'default';
        let skeletonHTML = '';
        
        switch (skeletonType) {
            case 'card':
                skeletonHTML = this.getCardSkeleton();
                break;
            case 'table':
                skeletonHTML = this.getTableSkeleton(options.rows || 5);
                break;
            case 'stats':
                skeletonHTML = this.getStatsSkeleton();
                break;
            default:
                skeletonHTML = this.getDefaultSkeleton();
        }
        
        const skeletonContainer = document.createElement('div');
        skeletonContainer.className = 'skeleton-container';
        skeletonContainer.innerHTML = skeletonHTML;
        
        element.appendChild(skeletonContainer);
    }
    
    showSpinnerLoading(element, options) {
        const spinnerType = options.spinnerType || 'default';
        const spinnerSize = options.size || 'medium';
        
        const spinnerContainer = document.createElement('div');
        spinnerContainer.className = 'loading-content d-flex align-items-center justify-content-center';
        
        let spinnerHTML = '';
        switch (spinnerType) {
            case 'dots':
                spinnerHTML = `
                    <div class="spinner-dots">
                        <div class="spinner-dot"></div>
                        <div class="spinner-dot"></div>
                        <div class="spinner-dot"></div>
                    </div>
                `;
                break;
            case 'pulse':
                spinnerHTML = `<div class="spinner-pulse"></div>`;
                break;
            case 'ring':
                spinnerHTML = `<div class="spinner-ring"></div>`;
                break;
            default:
                spinnerHTML = `<div class="spinner ${spinnerSize}"></div>`;
        }
        
        spinnerContainer.innerHTML = spinnerHTML;
        element.appendChild(spinnerContainer);
    }
    
    showProgressLoading(element, options) {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'loading-content';
        
        const progressHTML = `
            <div class="progress-bar mb-2">
                <div class="progress-fill" style="width: 0%"></div>
            </div>
            <div class="text-center text-sm text-neutral-600">
                ${options.message || 'Wird geladen...'}
            </div>
        `;
        
        progressContainer.innerHTML = progressHTML;
        element.appendChild(progressContainer);
        
        // Simulate progress
        this.simulateProgress(progressContainer.querySelector('.progress-fill'));
    }
    
    simulateProgress(progressElement) {
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress >= 100) {
                progress = 100;
                clearInterval(interval);
            }
            progressElement.style.width = progress + '%';
        }, 200);
    }
    
    // ========================================
    // Skeleton Templates
    // ========================================
    getCardSkeleton() {
        return `
            <div class="skeleton-card">
                <div class="skeleton-title"></div>
                <div class="skeleton-paragraph">
                    <div class="skeleton-text"></div>
                    <div class="skeleton-text medium"></div>
                    <div class="skeleton-text short"></div>
                </div>
            </div>
        `;
    }
    
    getTableSkeleton(rows) {
        let skeletonRows = '';
        for (let i = 0; i < rows; i++) {
            skeletonRows += `
                <div class="table-skeleton-row">
                    <div class="skeleton table-skeleton-cell"></div>
                    <div class="skeleton table-skeleton-cell"></div>
                    <div class="skeleton table-skeleton-cell"></div>
                    <div class="skeleton table-skeleton-cell"></div>
                    <div class="skeleton table-skeleton-cell"></div>
                </div>
            `;
        }
        return `<div class="table-skeleton">${skeletonRows}</div>`;
    }
    
    getStatsSkeleton() {
        return `
            <div class="skeleton-stats">
                <div class="skeleton skeleton-value"></div>
                <div class="skeleton skeleton-label"></div>
            </div>
        `;
    }
    
    getDefaultSkeleton() {
        return `
            <div class="skeleton-paragraph">
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text medium"></div>
                <div class="skeleton skeleton-text long"></div>
            </div>
        `;
    }
    
    // ========================================
    // Progressive Enhancement
    // ========================================
    setupProgressiveEnhancement() {
        // Feature Detection
        this.detectFeatures();
        
        // Progressive Form Enhancement
        this.enhanceFormsProgressively();
        
        // Progressive Chart Enhancement
        this.enhanceChartsProgressively();
    }
    
    detectFeatures() {
        const features = {
            intersectionObserver: 'IntersectionObserver' in window,
            performanceObserver: 'PerformanceObserver' in window,
            webAnimations: 'animate' in document.body,
            customElements: 'customElements' in window,
            modules: 'noModule' in HTMLScriptElement.prototype
        };
        
        Object.entries(features).forEach(([feature, supported]) => {
            document.documentElement.classList.toggle(`no-${feature}`, !supported);
            document.documentElement.classList.toggle(`has-${feature}`, supported);
        });
        
        this.performanceMetrics.features = features;
    }
    
    enhanceFormsProgressively() {
        // Only enhance if JavaScript is available
        document.querySelectorAll('form[data-enhance]').forEach(form => {
            // Add progressive enhancement classes
            form.classList.add('form-enhanced');
            
            // Add loading states to submit buttons
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                form.addEventListener('submit', () => {
                    this.showButtonLoading(submitBtn);
                });
            }
        });
    }
    
    enhanceChartsProgressively() {
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            this.loadChartLibrary().then(() => {
                this.processChartQueue();
            });
        } else {
            this.processChartQueue();
        }
    }
    
    async loadChartLibrary() {
        try {
            await this.loadJS('https://cdn.jsdelivr.net/npm/chart.js');
            console.log('[Enhanced Loading] Chart.js loaded successfully');
        } catch (error) {
            console.error('[Enhanced Loading] Failed to load Chart.js:', error);
        }
    }
    
    processChartQueue() {
        document.querySelectorAll('.chart-container:not(.processed)').forEach(chart => {
            chart.classList.add('processed');
            if (this.isInViewport(chart)) {
                this.loadChart(chart);
            }
        });
    }
    
    // ========================================
    // Button Loading States
    // ========================================
    showButtonLoading(button) {
        button.classList.add('btn-loading');
        button.disabled = true;
        
        // Store original text
        const originalText = button.innerHTML;
        button.setAttribute('data-original-text', originalText);
        
        button.innerHTML = '<span class="btn-text">' + originalText + '</span>';
    }
    
    hideButtonLoading(button) {
        button.classList.remove('btn-loading');
        button.disabled = false;
        
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.innerHTML = originalText;
            button.removeAttribute('data-original-text');
        }
    }
    
    // ========================================
    // Utility Methods
    // ========================================
    loadCSS(href, critical = false) {
        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            
            if (critical) {
                link.media = 'all';
            }
            
            link.onload = () => {
                this.performanceMetrics.criticalResources.push({
                    type: 'css',
                    href: href,
                    loadTime: performance.now()
                });
                resolve();
            };
            
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }
    
    loadJS(src, critical = false) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.defer = !critical;
            
            script.onload = () => {
                this.performanceMetrics.criticalResources.push({
                    type: 'js',
                    src: src,
                    loadTime: performance.now()
                });
                resolve();
            };
            
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
    
    generateLoadingId() {
        return 'loading-' + Math.random().toString(36).substr(2, 9);
    }
    
    announceLoading(element, message) {
        const announcement = element.querySelector('.loading-announce') || 
                           document.createElement('div');
        
        announcement.className = 'loading-announce';
        announcement.setAttribute('aria-live', 'polite');
        announcement.textContent = message;
        
        if (!element.contains(announcement)) {
            element.appendChild(announcement);
        }
    }
    
    // ========================================
    // Public API
    // ========================================
    
    // Manual loading control
    setLoading(element, loading = true, options = {}) {
        if (loading) {
            this.showLoadingState(element, options);
        } else {
            this.hideLoadingState(element);
        }
    }
    
    // Get performance metrics
    getPerformanceMetrics() {
        return {
            ...this.performanceMetrics,
            totalLoadTime: performance.now() - this.performanceMetrics.loadStart
        };
    }
    
    // Force lazy load element
    forceLazyLoad(element) {
        this.loadLazyContent(element);
    }
    
    // Clear all loading states
    clearAllLoadingStates() {
        this.loadingStates.forEach((id, element) => {
            this.hideLoadingState(element);
        });
    }
}

// ========================================
// Initialization & Global Access
// ========================================

let enhancedLoadingInstance = null;

function initEnhancedLoading() {
    try {
        if (!enhancedLoadingInstance) {
            enhancedLoadingInstance = new EnhancedLoading();
            window.enhancedLoading = enhancedLoadingInstance;
            console.log('[Enhanced Loading] System initialized successfully');
        }
    } catch (error) {
        console.error('[Enhanced Loading] Failed to initialize:', error);
    }
}

// Global functions for compatibility
window.showLoading = function(element, options = {}) {
    window.enhancedLoading?.setLoading(element, true, options);
};

window.hideLoading = function(element) {
    window.enhancedLoading?.setLoading(element, false);
};

// DOM Ready Detection
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEnhancedLoading);
} else {
    initEnhancedLoading();
}

// Performance monitoring
window.addEventListener('load', () => {
    setTimeout(() => {
        if (window.enhancedLoading) {
            const metrics = window.enhancedLoading.getPerformanceMetrics();
            console.log('[Enhanced Loading] Performance Metrics:', metrics);
        }
    }, 1000);
});

// Export für Module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { EnhancedLoading, initEnhancedLoading };
}