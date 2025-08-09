// js/enhanced-forms.js
// Enhanced Form Components JavaScript für Stromtracker

class EnhancedForms {
    constructor() {
        this.validators = new Map();
        this.formSteps = new Map();
        this.initialized = false;
        
        this.init();
    }
    
    // ========================================
    // Initialisierung
    // ========================================
    init() {
        if (this.initialized) return;
        
        try {
            this.setupFloatingLabels();
            this.setupRealTimeValidation();
            this.setupEnhancedSelects();
            this.setupFormSteps();
            this.setupKeyboardNavigation();
            this.setupAutoSave();
            
            this.initialized = true;
            console.log('[Enhanced Forms] Initialized successfully');
        } catch (error) {
            console.error('[Enhanced Forms] Initialization failed:', error);
        }
    }
    
    // ========================================
    // Floating Labels Enhancement
    // ========================================
    setupFloatingLabels() {
        const floatingGroups = document.querySelectorAll('.form-group-floating');
        
        floatingGroups.forEach(group => {
            const input = group.querySelector('.form-control-floating');
            const label = group.querySelector('.form-label-floating');
            
            if (input && label) {
                // Initial state check
                this.updateFloatingLabel(input, label);
                
                // Event listeners
                input.addEventListener('input', () => {
                    this.updateFloatingLabel(input, label);
                    this.validateField(input);
                });
                
                input.addEventListener('focus', () => {
                    group.classList.add('focused');
                    this.animateProgress(group, true);
                });
                
                input.addEventListener('blur', () => {
                    group.classList.remove('focused');
                    this.animateProgress(group, false);
                    this.validateField(input);
                });
                
                // Auto-format specific input types
                this.setupAutoFormat(input);
            }
        });
    }
    
    updateFloatingLabel(input, label) {
        const hasValue = input.value.length > 0;
        const isFocused = document.activeElement === input;
        
        if (hasValue || isFocused) {
            label.style.transform = 'translateY(-12px) scale(0.85)';
            label.style.color = isFocused ? 'var(--form-border-focus)' : 'var(--form-placeholder)';
        } else {
            label.style.transform = 'translateY(0) scale(1)';
            label.style.color = 'var(--form-placeholder)';
        }
    }
    
    animateProgress(group, show) {
        const progress = group.querySelector('.form-input-progress');
        if (progress) {
            progress.style.transform = show ? 'scaleX(1)' : 'scaleX(0)';
        }
    }
    
    // ========================================
    // Real-Time Validation
    // ========================================
    setupRealTimeValidation() {
        const validationGroups = document.querySelectorAll('[data-validation]');
        
        validationGroups.forEach(group => {
            const input = group.querySelector('.form-control-floating, input, select, textarea');
            const rules = this.parseValidationRules(group.getAttribute('data-validation'));
            
            if (input) {
                this.validators.set(input, rules);
                this.createValidationFeedback(group, rules);
                
                input.addEventListener('input', () => {
                    this.validateFieldRealTime(input, rules, group);
                });
                
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
            }
        });
    }
    
    parseValidationRules(rulesString) {
        const rules = {};
        const parts = rulesString.split('|');
        
        parts.forEach(part => {
            const [rule, param] = part.split(':');
            
            switch (rule) {
                case 'required':
                    rules.required = true;
                    break;
                case 'email':
                    rules.email = true;
                    break;
                case 'min':
                    rules.min = parseInt(param);
                    break;
                case 'max':
                    rules.max = parseInt(param);
                    break;
                case 'minlength':
                    rules.minLength = parseInt(param);
                    break;
                case 'maxlength':
                    rules.maxLength = parseInt(param);
                    break;
                case 'number':
                    rules.number = true;
                    break;
                case 'decimal':
                    rules.decimal = parseInt(param) || 2;
                    break;
                case 'date':
                    rules.date = true;
                    break;
            }
        });
        
        return rules;
    }
    
    createValidationFeedback(group, rules) {
        const feedback = document.createElement('div');
        feedback.className = 'validation-feedback-live';
        
        Object.keys(rules).forEach(rule => {
            const item = document.createElement('div');
            item.className = 'validation-item';
            item.setAttribute('data-rule', rule);
            
            const icon = document.createElement('i');
            icon.className = 'bi bi-circle';
            
            const text = document.createElement('span');
            text.textContent = this.getValidationMessage(rule, rules[rule]);
            
            item.appendChild(icon);
            item.appendChild(text);
            feedback.appendChild(item);
        });
        
        group.appendChild(feedback);
    }
    
    getValidationMessage(rule, param) {
        const messages = {
            required: 'Feld ist erforderlich',
            email: 'Gültige E-Mail-Adresse eingeben',
            min: `Minimum: ${param}`,
            max: `Maximum: ${param}`,
            minLength: `Mindestens ${param} Zeichen`,
            maxLength: `Höchstens ${param} Zeichen`,
            number: 'Nur Zahlen erlaubt',
            decimal: `Dezimalzahl mit ${param} Nachkommastellen`,
            date: 'Gültiges Datum eingeben'
        };
        
        return messages[rule] || 'Ungültige Eingabe';
    }
    
    validateFieldRealTime(input, rules, group) {
        const value = input.value;
        const feedbackItems = group.querySelectorAll('.validation-item');
        
        feedbackItems.forEach(item => {
            const rule = item.getAttribute('data-rule');
            const icon = item.querySelector('i');
            const isValid = this.validateRule(value, rule, rules[rule]);
            
            if (isValid) {
                item.classList.remove('invalid');
                item.classList.add('valid');
                icon.className = 'bi bi-check-circle';
            } else {
                item.classList.remove('valid');
                if (value.length > 0) {
                    item.classList.add('invalid');
                    icon.className = 'bi bi-x-circle';
                } else {
                    item.classList.remove('invalid');
                    icon.className = 'bi bi-circle';
                }
            }
        });
        
        // Update form group state
        this.updateValidationState(input, group);
    }
    
    validateRule(value, rule, param) {
        switch (rule) {
            case 'required':
                return value.length > 0;
            case 'email':
                return !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            case 'min':
                return !value || parseFloat(value) >= param;
            case 'max':
                return !value || parseFloat(value) <= param;
            case 'minLength':
                return !value || value.length >= param;
            case 'maxLength':
                return !value || value.length <= param;
            case 'number':
                return !value || /^\d+$/.test(value);
            case 'decimal':
                const regex = new RegExp(`^\\d+(\\.\\d{1,${param}})?$`);
                return !value || regex.test(value);
            case 'date':
                return !value || !isNaN(Date.parse(value));
            default:
                return true;
        }
    }
    
    validateField(input) {
        const rules = this.validators.get(input);
        if (!rules) return true;
        
        const value = input.value;
        const group = input.closest('.form-group-floating, .form-group');
        let isValid = true;
        
        for (const [rule, param] of Object.entries(rules)) {
            if (!this.validateRule(value, rule, param)) {
                isValid = false;
                break;
            }
        }
        
        this.updateValidationState(input, group, isValid);
        return isValid;
    }
    
    updateValidationState(input, group, isValid = null) {
        if (isValid === null) {
            const rules = this.validators.get(input);
            isValid = rules ? Object.entries(rules).every(([rule, param]) => 
                this.validateRule(input.value, rule, param)) : true;
        }
        
        if (group) {
            group.classList.remove('is-valid', 'is-invalid');
            if (input.value.length > 0) {
                group.classList.add(isValid ? 'is-valid' : 'is-invalid');
            }
        }
    }
    
    // ========================================
    // Auto-Format Inputs
    // ========================================
    setupAutoFormat(input) {
        const type = input.getAttribute('data-format');
        
        switch (type) {
            case 'currency':
                this.setupCurrencyFormat(input);
                break;
            case 'number':
                this.setupNumberFormat(input);
                break;
            case 'decimal':
                this.setupDecimalFormat(input);
                break;
            case 'date':
                this.setupDateFormat(input);
                break;
        }
    }
    
    setupCurrencyFormat(input) {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^\d,]/g, '');
            value = value.replace(',', '.');
            const number = parseFloat(value);
            
            if (!isNaN(number)) {
                e.target.value = number.toLocaleString('de-DE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        });
    }
    
    setupNumberFormat(input) {
        input.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^\d]/g, '');
        });
    }
    
    setupDecimalFormat(input) {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^\d,]/g, '');
            const commaIndex = value.indexOf(',');
            
            if (commaIndex !== -1) {
                const beforeComma = value.substring(0, commaIndex);
                const afterComma = value.substring(commaIndex + 1).substring(0, 2);
                value = beforeComma + ',' + afterComma;
            }
            
            e.target.value = value;
        });
    }
    
    setupDateFormat(input) {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^\d]/g, '');
            
            if (value.length >= 2) {
                value = value.substring(0, 2) + '.' + value.substring(2);
            }
            if (value.length >= 5) {
                value = value.substring(0, 5) + '.' + value.substring(5, 9);
            }
            
            e.target.value = value;
        });
    }
    
    // ========================================
    // Enhanced Selects
    // ========================================
    setupEnhancedSelects() {
        const selects = document.querySelectorAll('.select-enhanced select');
        
        selects.forEach(select => {
            select.addEventListener('change', () => {
                const group = select.closest('.form-group-floating');
                if (group) {
                    const label = group.querySelector('.form-label-floating');
                    if (label) {
                        this.updateFloatingLabel(select, label);
                    }
                }
            });
        });
    }
    
    // ========================================
    // Form Steps/Wizard
    // ========================================
    setupFormSteps() {
        const stepForms = document.querySelectorAll('.form-steps-container');
        
        stepForms.forEach(container => {
            const steps = container.querySelectorAll('.form-step');
            const sections = container.querySelectorAll('.form-step-section');
            
            if (steps.length > 0 && sections.length > 0) {
                this.formSteps.set(container, {
                    steps: Array.from(steps),
                    sections: Array.from(sections),
                    currentStep: 0
                });
                
                this.initializeSteps(container);
                this.setupStepNavigation(container);
            }
        });
    }
    
    initializeSteps(container) {
        const stepData = this.formSteps.get(container);
        const { steps, sections } = stepData;
        
        // Hide all sections except first
        sections.forEach((section, index) => {
            section.style.display = index === 0 ? 'block' : 'none';
        });
        
        // Mark first step as active
        if (steps[0]) {
            steps[0].classList.add('active');
        }
    }
    
    setupStepNavigation(container) {
        const nextBtns = container.querySelectorAll('[data-step="next"]');
        const prevBtns = container.querySelectorAll('[data-step="prev"]');
        
        nextBtns.forEach(btn => {
            btn.addEventListener('click', () => this.nextStep(container));
        });
        
        prevBtns.forEach(btn => {
            btn.addEventListener('click', () => this.prevStep(container));
        });
    }
    
    nextStep(container) {
        const stepData = this.formSteps.get(container);
        const { steps, sections, currentStep } = stepData;
        
        // Validate current step
        if (!this.validateStep(sections[currentStep])) {
            return;
        }
        
        if (currentStep < steps.length - 1) {
            // Hide current section
            sections[currentStep].style.display = 'none';
            steps[currentStep].classList.remove('active');
            steps[currentStep].classList.add('completed');
            
            // Show next section
            const nextStep = currentStep + 1;
            sections[nextStep].style.display = 'block';
            steps[nextStep].classList.add('active');
            
            stepData.currentStep = nextStep;
            this.animateStepTransition(sections[currentStep], sections[nextStep]);
        }
    }
    
    prevStep(container) {
        const stepData = this.formSteps.get(container);
        const { steps, sections, currentStep } = stepData;
        
        if (currentStep > 0) {
            // Hide current section
            sections[currentStep].style.display = 'none';
            steps[currentStep].classList.remove('active');
            
            // Show previous section
            const prevStep = currentStep - 1;
            sections[prevStep].style.display = 'block';
            steps[prevStep].classList.remove('completed');
            steps[prevStep].classList.add('active');
            
            stepData.currentStep = prevStep;
            this.animateStepTransition(sections[currentStep], sections[prevStep]);
        }
    }
    
    validateStep(section) {
        const inputs = section.querySelectorAll('.form-control-floating, input, select, textarea');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    animateStepTransition(fromSection, toSection) {
        fromSection.style.opacity = '0';
        fromSection.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            toSection.style.opacity = '0';
            toSection.style.transform = 'translateX(20px)';
            
            requestAnimationFrame(() => {
                toSection.style.transition = 'all 0.3s ease';
                toSection.style.opacity = '1';
                toSection.style.transform = 'translateX(0)';
                
                setTimeout(() => {
                    toSection.style.transition = '';
                }, 300);
            });
        }, 50);
    }
    
    // ========================================
    // Keyboard Navigation
    // ========================================
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Enter to submit forms
            if (e.key === 'Enter' && e.target.matches('.form-control-floating')) {
                const form = e.target.closest('form');
                if (form) {
                    const submitBtn = form.querySelector('[type="submit"]');
                    if (submitBtn && !e.shiftKey) {
                        e.preventDefault();
                        submitBtn.click();
                    }
                }
            }
            
            // Tab navigation enhancement
            if (e.key === 'Tab') {
                const activeElement = document.activeElement;
                if (activeElement.matches('.form-control-floating')) {
                    const group = activeElement.closest('.form-group-floating');
                    if (group) {
                        group.classList.add('keyboard-focus');
                        
                        setTimeout(() => {
                            group.classList.remove('keyboard-focus');
                        }, 200);
                    }
                }
            }
        });
    }
    
    // ========================================
    // Auto-Save
    // ========================================
    setupAutoSave() {
        const autoSaveForms = document.querySelectorAll('[data-autosave]');
        
        autoSaveForms.forEach(form => {
            const inputs = form.querySelectorAll('.form-control-floating, input, select, textarea');
            const saveKey = form.getAttribute('data-autosave') || 'autosave-' + Date.now();
            
            // Load saved data
            this.loadAutoSaveData(form, saveKey);
            
            // Save on input
            inputs.forEach(input => {
                input.addEventListener('input', this.debounce(() => {
                    this.saveAutoSaveData(form, saveKey);
                }, 1000));
            });
            
            // Clear on submit
            form.addEventListener('submit', () => {
                this.clearAutoSaveData(saveKey);
            });
        });
    }
    
    loadAutoSaveData(form, saveKey) {
        try {
            const savedData = localStorage.getItem(saveKey);
            if (savedData) {
                const data = JSON.parse(savedData);
                
                Object.entries(data).forEach(([name, value]) => {
                    const input = form.querySelector(`[name="${name}"]`);
                    if (input) {
                        input.value = value;
                        
                        // Trigger events for floating labels
                        input.dispatchEvent(new Event('input'));
                        input.dispatchEvent(new Event('change'));
                    }
                });
            }
        } catch (error) {
            console.error('[Enhanced Forms] Auto-save load error:', error);
        }
    }
    
    saveAutoSaveData(form, saveKey) {
        try {
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            localStorage.setItem(saveKey, JSON.stringify(data));
        } catch (error) {
            console.error('[Enhanced Forms] Auto-save error:', error);
        }
    }
    
    clearAutoSaveData(saveKey) {
        try {
            localStorage.removeItem(saveKey);
        } catch (error) {
            console.error('[Enhanced Forms] Auto-save clear error:', error);
        }
    }
    
    // ========================================
    // Utility Methods
    // ========================================
    
    // Enhanced form submission
    submitFormEnhanced(form, options = {}) {
        const submitBtn = form.querySelector('[type="submit"]');
        
        // Add loading state
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }
        
        // Validate all fields
        const inputs = form.querySelectorAll('.form-control-floating, input, select, textarea');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
            return false;
        }
        
        // Custom success/error handling
        const originalAction = form.action;
        
        if (options.ajax) {
            this.submitFormAjax(form, options);
            return false;
        }
        
        return true;
    }
    
    async submitFormAjax(form, options) {
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            });
            
            if (response.ok) {
                if (options.onSuccess) {
                    options.onSuccess(response);
                } else {
                    this.showFormMessage('Erfolgreich gespeichert!', 'success');
                }
            } else {
                throw new Error('Server error');
            }
        } catch (error) {
            if (options.onError) {
                options.onError(error);
            } else {
                this.showFormMessage('Ein Fehler ist aufgetreten.', 'error');
            }
        } finally {
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        }
    }
    
    showFormMessage(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.insertAdjacentElement('afterbegin', alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
    
    // Debounce utility
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
    
    // Public API
    validateForm(form) {
        const inputs = form.querySelectorAll('.form-control-floating, input, select, textarea');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    resetForm(form) {
        form.reset();
        
        // Reset enhanced states
        const groups = form.querySelectorAll('.form-group-floating');
        groups.forEach(group => {
            group.classList.remove('is-valid', 'is-invalid', 'focused');
            
            const input = group.querySelector('.form-control-floating');
            const label = group.querySelector('.form-label-floating');
            
            if (input && label) {
                this.updateFloatingLabel(input, label);
            }
        });
    }
}

// ========================================
// Global Functions & Initialization
// ========================================

let enhancedFormsInstance = null;

function initEnhancedForms() {
    try {
        if (!enhancedFormsInstance) {
            enhancedFormsInstance = new EnhancedForms();
            window.enhancedForms = enhancedFormsInstance;
            console.log('[Enhanced Forms] System initialized successfully');
        }
    } catch (error) {
        console.error('[Enhanced Forms] Failed to initialize:', error);
    }
}

// DOM Ready Detection
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEnhancedForms);
} else {
    initEnhancedForms();
}

// Export für Module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { EnhancedForms, initEnhancedForms };
}