/**
 * CSS Deduplicator - Удаление дублирующихся CSS правил
 * Анализирует и оптимизирует загруженные стили
 */

(function() {
    'use strict';
    
    class CSSDeduplicator {
        constructor() {
            this.ruleMap = new Map();
            this.duplicates = [];
            this.init();
        }
        
        init() {
            // Запускаем анализ после загрузки всех стилей
            if (document.readyState === 'complete') {
                this.analyze();
            } else {
                window.addEventListener('load', () => {
                    setTimeout(() => this.analyze(), 1000);
                });
            }
        }
        
        // Анализ всех стилей
        analyze() {
            const styleSheets = Array.from(document.styleSheets);
            
            styleSheets.forEach(sheet => {
                this.analyzeSheet(sheet);
            });
            
            this.reportDuplicates();
        }
        
        // Анализ одного stylesheet
        analyzeSheet(sheet) {
            try {
                const rules = Array.from(sheet.cssRules || sheet.rules || []);
                
                rules.forEach((rule, index) => {
                    if (rule.type === CSSRule.STYLE_RULE) {
                        this.checkRule(rule, sheet, index);
                    }
                });
            } catch (e) {
                // Cross-origin stylesheet - пропускаем
            }
        }
        
        // Проверка правила на дубликаты
        checkRule(rule, sheet, index) {
            const selector = rule.selectorText;
            const cssText = rule.style.cssText;
            
            // Создаем уникальный ключ
            const key = `${selector}::${cssText}`;
            
            if (this.ruleMap.has(key)) {
                // Найден дубликат
                const original = this.ruleMap.get(key);
                this.duplicates.push({
                    selector,
                    cssText,
                    original: {
                        sheet: original.sheet.href || 'inline',
                        index: original.index
                    },
                    duplicate: {
                        sheet: sheet.href || 'inline',
                        index
                    }
                });
            } else {
                // Сохраняем первое вхождение
                this.ruleMap.set(key, { sheet, index, rule });
            }
        }
        
        // Отчет о дубликатах
        reportDuplicates() {
            if (this.duplicates.length === 0) {
                console.log('✓ No duplicate CSS rules found');
                return;
            }
            
            console.group(`⚠ Found ${this.duplicates.length} duplicate CSS rules`);
            
            // Группируем по файлам
            const byFile = new Map();
            this.duplicates.forEach(dup => {
                const file = dup.duplicate.sheet;
                if (!byFile.has(file)) {
                    byFile.set(file, []);
                }
                byFile.get(file).push(dup);
            });
            
            byFile.forEach((dups, file) => {
                console.group(`${file} (${dups.length} duplicates)`);
                dups.slice(0, 5).forEach(dup => {
                    console.log(`Selector: ${dup.selector}`);
                });
                if (dups.length > 5) {
                    console.log(`... and ${dups.length - 5} more`);
                }
                console.groupEnd();
            });
            
            console.groupEnd();
            
            // Сохраняем в StateManager если доступен
            if (window.StateManager) {
                window.StateManager.set('css.duplicates', {
                    count: this.duplicates.length,
                    byFile: Array.from(byFile.entries()).map(([file, dups]) => ({
                        file,
                        count: dups.length
                    }))
                }, false);
            }
        }
        
        // Удаление дубликатов (экспериментально)
        removeDuplicates() {
            let removed = 0;
            
            this.duplicates.forEach(dup => {
                try {
                    const sheet = Array.from(document.styleSheets).find(s => 
                        (s.href || 'inline') === dup.duplicate.sheet
                    );
                    
                    if (sheet && sheet.cssRules) {
                        // Удаляем правило
                        sheet.deleteRule(dup.duplicate.index - removed);
                        removed++;
                    }
                } catch (e) {
                    console.warn('Failed to remove duplicate rule:', e);
                }
            });
            
            console.log(`Removed ${removed} duplicate rules`);
            return removed;
        }
        
        // Поиск неиспользуемых правил
        findUnusedRules() {
            const unused = [];
            const styleSheets = Array.from(document.styleSheets);
            
            styleSheets.forEach(sheet => {
                try {
                    const rules = Array.from(sheet.cssRules || []);
                    
                    rules.forEach(rule => {
                        if (rule.type === CSSRule.STYLE_RULE) {
                            const selector = rule.selectorText;
                            
                            // Пропускаем псевдо-селекторы и медиа-запросы
                            if (selector.includes(':') || selector.includes('@')) {
                                return;
                            }
                            
                            try {
                                const elements = document.querySelectorAll(selector);
                                if (elements.length === 0) {
                                    unused.push({
                                        selector,
                                        sheet: sheet.href || 'inline'
                                    });
                                }
                            } catch (e) {
                                // Невалидный селектор
                            }
                        }
                    });
                } catch (e) {
                    // Cross-origin
                }
            });
            
            return unused;
        }
        
        // Оптимизация специфичности
        analyzeSpecificity() {
            const specificity = [];
            
            this.ruleMap.forEach((value, key) => {
                const selector = value.rule.selectorText;
                const score = this.calculateSpecificity(selector);
                
                specificity.push({
                    selector,
                    score,
                    sheet: value.sheet.href || 'inline'
                });
            });
            
            // Сортируем по специфичности
            specificity.sort((a, b) => b.score - a.score);
            
            return specificity;
        }
        
        // Расчет специфичности селектора
        calculateSpecificity(selector) {
            let score = 0;
            
            // ID селекторы
            score += (selector.match(/#/g) || []).length * 100;
            
            // Классы, атрибуты, псевдоклассы
            score += (selector.match(/\.|:(?!:)|\[/g) || []).length * 10;
            
            // Элементы и псевдоэлементы
            score += (selector.match(/\w+|::/g) || []).length;
            
            return score;
        }
        
        // Получение статистики
        getStats() {
            const unused = this.findUnusedRules();
            
            return {
                totalRules: this.ruleMap.size,
                duplicates: this.duplicates.length,
                unused: unused.length,
                potentialSavings: this.calculateSavings()
            };
        }
        
        // Расчет потенциальной экономии
        calculateSavings() {
            let bytes = 0;
            
            this.duplicates.forEach(dup => {
                // Примерный размер правила
                bytes += dup.selector.length + dup.cssText.length + 10;
            });
            
            return {
                bytes,
                kb: (bytes / 1024).toFixed(2)
            };
        }
        
        // Генерация оптимизированного CSS
        generateOptimizedCSS() {
            const optimized = [];
            
            this.ruleMap.forEach((value) => {
                const rule = value.rule;
                optimized.push(`${rule.selectorText}{${rule.style.cssText}}`);
            });
            
            return optimized.join('');
        }
    }
    
    // Инициализация
    window.addEventListener('load', () => {
        setTimeout(() => {
            window.CSSDeduplicator = new CSSDeduplicator();
        }, 2000);
    });
    
    // API
    window.cssOptimize = {
        analyze: () => window.CSSDeduplicator && window.CSSDeduplicator.analyze(),
        removeDuplicates: () => window.CSSDeduplicator && window.CSSDeduplicator.removeDuplicates(),
        findUnused: () => window.CSSDeduplicator && window.CSSDeduplicator.findUnusedRules(),
        stats: () => window.CSSDeduplicator && window.CSSDeduplicator.getStats(),
        generate: () => window.CSSDeduplicator && window.CSSDeduplicator.generateOptimizedCSS()
    };
    
})();
