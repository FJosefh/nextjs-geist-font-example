</div> <!-- Cierre del container-fluid main-content -->
    
    <footer class="bg-dark text-light py-3 mt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        Sistema de Gestión de Inventario v1.0
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/script.js"></script>
    
    <!-- Scripts adicionales específicos de página -->
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>
    
    <script>
        // Funciones globales para la aplicación
        
        // Confirmar acciones de eliminación
        function confirmDelete(message = '¿Está seguro de que desea eliminar este elemento?') {
            return confirm(message);
        }
        
        // Mostrar alertas con auto-hide
        function showAlert(message, type = 'success', duration = 5000) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto-hide después del tiempo especificado
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, duration);
        }
        
        // Formatear números como moneda
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-PE', {
                style: 'currency',
                currency: 'PEN'
            }).format(amount);
        }
        
        // Formatear fechas
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-PE', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Validar formularios
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;
            
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
        
        // Búsqueda en tiempo real para tablas
        function setupTableSearch(searchInputId, tableId) {
            const searchInput = document.getElementById(searchInputId);
            const table = document.getElementById(tableId);
            
            if (!searchInput || !table) return;
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
        
        // Resaltar columna del almacén del usuario
        function highlightUserWarehouse() {
            const userWarehouse = '<?php echo $userWarehouse ?? ''; ?>';
            if (!userWarehouse) return;
            
            // Buscar columnas que contengan el nombre del almacén
            const headers = document.querySelectorAll('th');
            headers.forEach((header, index) => {
                const headerText = header.textContent.toLowerCase();
                if (headerText.includes(userWarehouse.replace('_', ' '))) {
                    header.classList.add('warehouse-highlight');
                    
                    // Resaltar también las celdas de esa columna
                    const rows = document.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const cell = row.cells[index];
                        if (cell) {
                            cell.classList.add('warehouse-highlight');
                        }
                    });
                }
            });
        }
        
        // Ejecutar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Resaltar almacén del usuario
            highlightUserWarehouse();
            
            // Inicializar tooltips de Bootstrap
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Inicializar popovers de Bootstrap
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            // Auto-focus en el primer campo de formularios
            const firstInput = document.querySelector('form input:not([type="hidden"]):not([readonly])');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Confirmar formularios de eliminación
            const deleteButtons = document.querySelectorAll('.btn-delete, [data-action="delete"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirmDelete()) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
            
            // Validación en tiempo real de formularios
            const forms = document.querySelectorAll('form[data-validate="true"]');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!validateForm(this.id)) {
                        e.preventDefault();
                        showAlert('Por favor, complete todos los campos requeridos.', 'danger');
                        return false;
                    }
                });
            });
        });
        
        // Función para actualizar contadores en tiempo real
        function updateCounters() {
            fetch('<?php echo SITE_URL; ?>/api/counters.php')
                .then(response => response.json())
                .then(data => {
                    // Actualizar contadores en el dashboard
                    Object.keys(data).forEach(key => {
                        const element = document.getElementById(`counter-${key}`);
                        if (element) {
                            element.textContent = data[key];
                        }
                    });
                })
                .catch(error => console.error('Error updating counters:', error));
        }
        
        // Actualizar contadores cada 30 segundos
        setInterval(updateCounters, 30000);
    </script>
</body>
</html>
