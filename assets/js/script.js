// JavaScript personalizado para el Sistema de Inventario

// Configuración global
const InventorySystem = {
    config: {
        apiUrl: '/api',
        alertDuration: 5000,
        tablePageSize: 25,
        searchDelay: 300
    },
    
    // Inicialización del sistema
    init: function() {
        this.setupEventListeners();
        this.initializeComponents();
        this.setupTableFeatures();
        this.setupFormValidation();
    },
    
    // Configurar event listeners globales
    setupEventListeners: function() {
        // Confirmar eliminaciones
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-delete') || e.target.hasAttribute('data-confirm')) {
                const message = e.target.getAttribute('data-confirm') || '¿Está seguro de que desea eliminar este elemento?';
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Validación en tiempo real de formularios
        document.addEventListener('input', function(e) {
            if (e.target.hasAttribute('required')) {
                InventorySystem.validateField(e.target);
            }
        });
        
        // Búsqueda en tiempo real
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('search-input')) {
                InventorySystem.debounce(function() {
                    InventorySystem.performSearch(e.target);
                }, InventorySystem.config.searchDelay)();
            }
        });
        
        // Auto-guardar formularios
        document.addEventListener('change', function(e) {
            if (e.target.hasAttribute('data-auto-save')) {
                InventorySystem.autoSave(e.target);
            }
        });
    },
    
    // Inicializar componentes de Bootstrap y otros
    initializeComponents: function() {
        // Tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
        
        // Modales
        const modalElements = document.querySelectorAll('.modal');
        modalElements.forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                const firstInput = modal.querySelector('input:not([type="hidden"]):not([readonly])');
                if (firstInput) {
                    firstInput.focus();
                }
            });
        });
    },
    
    // Configurar características de tablas
    setupTableFeatures: function() {
        // Ordenamiento de tablas
        const sortableHeaders = document.querySelectorAll('th[data-sortable]');
        sortableHeaders.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                InventorySystem.sortTable(this);
            });
        });
        
        // Filtros de tabla
        const filterInputs = document.querySelectorAll('.table-filter');
        filterInputs.forEach(input => {
            input.addEventListener('input', function() {
                InventorySystem.filterTable(this);
            });
        });
        
        // Selección múltiple
        const selectAllCheckbox = document.querySelector('#selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.row-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                InventorySystem.updateBulkActions();
            });
        }
        
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                InventorySystem.updateBulkActions();
            });
        });
    },
    
    // Configurar validación de formularios
    setupFormValidation: function() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!InventorySystem.validateForm(this)) {
                    e.preventDefault();
                    InventorySystem.showAlert('Por favor, complete todos los campos requeridos.', 'danger');
                    return false;
                }
            });
        });
    },
    
    // Validar un campo individual
    validateField: function(field) {
        const value = field.value.trim();
        const isValid = field.checkValidity();
        
        if (isValid && value) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else if (!isValid || (field.hasAttribute('required') && !value)) {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-valid', 'is-invalid');
        }
        
        return isValid;
    },
    
    // Validar formulario completo
    validateForm: function(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    },
    
    // Mostrar alertas
    showAlert: function(message, type = 'success', duration = null) {
        duration = duration || this.config.alertDuration;
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-hide
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 150);
            }
        }, duration);
        
        return alertDiv;
    },
    
    // Realizar búsqueda
    performSearch: function(input) {
        const searchTerm = input.value.toLowerCase().trim();
        const targetTable = document.querySelector(input.getAttribute('data-target') || 'table');
        
        if (!targetTable) return;
        
        const rows = targetTable.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const isVisible = !searchTerm || text.includes(searchTerm);
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });
        
        // Mostrar contador de resultados
        this.updateSearchResults(visibleCount, rows.length);
    },
    
    // Actualizar contador de resultados de búsqueda
    updateSearchResults: function(visible, total) {
        let counter = document.querySelector('.search-results-counter');
        if (!counter) {
            counter = document.createElement('small');
            counter.className = 'search-results-counter text-muted ms-2';
            const searchInput = document.querySelector('.search-input');
            if (searchInput && searchInput.parentNode) {
                searchInput.parentNode.appendChild(counter);
            }
        }
        
        if (visible === total) {
            counter.textContent = `${total} elementos`;
        } else {
            counter.textContent = `${visible} de ${total} elementos`;
        }
    },
    
    // Ordenar tabla
    sortTable: function(header) {
        const table = header.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(header.parentNode.children).indexOf(header);
        const isAscending = !header.classList.contains('sort-asc');
        
        // Limpiar clases de ordenamiento anteriores
        header.parentNode.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        
        // Agregar clase de ordenamiento actual
        header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
        
        // Ordenar filas
        rows.sort((a, b) => {
            const aValue = a.cells[columnIndex].textContent.trim();
            const bValue = b.cells[columnIndex].textContent.trim();
            
            // Detectar si son números
            const aNum = parseFloat(aValue.replace(/[^\d.-]/g, ''));
            const bNum = parseFloat(bValue.replace(/[^\d.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAscending ? aNum - bNum : bNum - aNum;
            } else {
                return isAscending ? 
                    aValue.localeCompare(bValue) : 
                    bValue.localeCompare(aValue);
            }
        });
        
        // Reordenar en el DOM
        rows.forEach(row => tbody.appendChild(row));
    },
    
    // Filtrar tabla
    filterTable: function(filterInput) {
        const filterValue = filterInput.value.toLowerCase();
        const column = filterInput.getAttribute('data-column');
        const table = document.querySelector(filterInput.getAttribute('data-target') || 'table');
        
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cell = row.cells[column];
            if (cell) {
                const cellText = cell.textContent.toLowerCase();
                row.style.display = cellText.includes(filterValue) ? '' : 'none';
            }
        });
    },
    
    // Actualizar acciones en lote
    updateBulkActions: function() {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const bulkActions = document.querySelector('.bulk-actions');
        
        if (bulkActions) {
            bulkActions.style.display = checkedBoxes.length > 0 ? 'block' : 'none';
        }
        
        const selectedCount = document.querySelector('.selected-count');
        if (selectedCount) {
            selectedCount.textContent = checkedBoxes.length;
        }
    },
    
    // Auto-guardar
    autoSave: function(field) {
        const form = field.closest('form');
        if (!form) return;
        
        const formData = new FormData(form);
        const url = form.getAttribute('data-auto-save-url') || form.action;
        
        // Mostrar indicador de guardado
        this.showSaveIndicator(field, 'saving');
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSaveIndicator(field, 'saved');
            } else {
                this.showSaveIndicator(field, 'error');
                this.showAlert(data.message || 'Error al guardar', 'danger');
            }
        })
        .catch(error => {
            this.showSaveIndicator(field, 'error');
            this.showAlert('Error de conexión', 'danger');
        });
    },
    
    // Mostrar indicador de guardado
    showSaveIndicator: function(field, status) {
        let indicator = field.parentNode.querySelector('.save-indicator');
        if (!indicator) {
            indicator = document.createElement('small');
            indicator.className = 'save-indicator ms-2';
            field.parentNode.appendChild(indicator);
        }
        
        switch (status) {
            case 'saving':
                indicator.textContent = 'Guardando...';
                indicator.className = 'save-indicator ms-2 text-info';
                break;
            case 'saved':
                indicator.textContent = 'Guardado';
                indicator.className = 'save-indicator ms-2 text-success';
                setTimeout(() => indicator.textContent = '', 2000);
                break;
            case 'error':
                indicator.textContent = 'Error';
                indicator.className = 'save-indicator ms-2 text-danger';
                setTimeout(() => indicator.textContent = '', 3000);
                break;
        }
    },
    
    // Debounce para optimizar búsquedas
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Formatear moneda
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN'
        }).format(amount);
    },
    
    // Formatear fecha
    formatDate: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-PE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    
    // Exportar tabla a CSV
    exportTableToCSV: function(tableId, filename = 'export.csv') {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tr:not([style*="display: none"])');
        const csv = [];
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            cols.forEach(col => {
                rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    },
    
    // Imprimir tabla
    printTable: function(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Reporte - ${document.title}</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <h2>Reporte - ${new Date().toLocaleDateString('es-PE')}</h2>
                    ${table.outerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    },
    
    // Actualizar contadores del dashboard
    updateDashboardCounters: function() {
        fetch('/api/dashboard-stats.php')
            .then(response => response.json())
            .then(data => {
                Object.keys(data).forEach(key => {
                    const element = document.getElementById(`counter-${key}`);
                    if (element) {
                        this.animateCounter(element, parseInt(element.textContent) || 0, data[key]);
                    }
                });
            })
            .catch(error => console.error('Error updating counters:', error));
    },
    
    // Animar contadores
    animateCounter: function(element, start, end, duration = 1000) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current);
        }, 16);
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    InventorySystem.init();
    
    // Actualizar contadores cada 30 segundos
    setInterval(() => {
        InventorySystem.updateDashboardCounters();
    }, 30000);
});

// Exportar para uso global
window.InventorySystem = InventorySystem;
