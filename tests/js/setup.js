// Test setup file for JavaScript tests

// Mock Bootstrap global objects
global.bootstrap = {
    Modal: class {
        constructor(element) {
            this.element = element;
        }
        
        show() {
            // Mock implementation
        }
        
        hide() {
            // Mock implementation
        }
        
        static getInstance() {
            return new this();
        }
    },
    
    Toast: class {
        constructor(element, options) {
            this.element = element;
            this.options = options;
        }
        
        show() {
            // Mock implementation
        }
        
        static getInstance() {
            return new this();
        }
    }
};

// Mock Stimulus controller base class
global.StimulusController = class {
    constructor() {
        this.element = null;
        this.targets = [];
    }
};

// Mock console methods to reduce noise
global.console = {
    ...console,
    log: jest.fn(),
    warn: jest.fn(),
    error: jest.fn()
};

// Mock window and document features
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: jest.fn().mockImplementation(query => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: jest.fn(),
        removeListener: jest.fn(),
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
        dispatchEvent: jest.fn(),
    })),
});

// Mock fetch
global.fetch = jest.fn();

// Mock DOM methods that might not be available in jsdom
Document.prototype.createRange = function() {
    return {
        setStart: function() {},
        setEnd: function() {},
        commonAncestorContainer: {
            nodeName: 'BODY',
            ownerDocument: document
        }
    };
};

// Mock requestAnimationFrame
global.requestAnimationFrame = callback => setTimeout(callback, 0);
global.cancelAnimationFrame = id => clearTimeout(id);

// Mock localStorage
Storage.prototype.setItem = jest.fn();
Storage.prototype.getItem = jest.fn();
Storage.prototype.removeItem = jest.fn();

// Mock IntersectionObserver
global.IntersectionObserver = class IntersectionObserver {
    constructor(callback, options) {
        this.callback = callback;
        this.options = options;
    }
    
    observe() {}
    unobserve() {}
    disconnect() {}
};

// Add custom matchers
expect.extend({
    toHaveClass(received, className) {
        const pass = received.classList.contains(className);
        return {
            message: () => `expected ${received} ${pass ? 'not ' : ''}to have class "${className}"`,
            pass
        };
    }
});