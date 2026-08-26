document.addEventListener('DOMContentLoaded', function () {
    // Inicializar Select2
    $('.select2-multiple').select2({
        width: '100%'
    });

    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const headerStep1 = document.getElementById('header-step-1');
    const headerStep2 = document.getElementById('header-step-2');
    
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');

    // Inputs
    const skuInput = document.getElementById('sku');
    const categorySelect = document.getElementById('category_id');
    const colorsSelect = document.getElementById('colors');
    const sizesSelect = document.getElementById('sizes');

    btnNext.addEventListener('click', function () {
        // Validaciones del paso 1
        const sku = skuInput.value.trim();
        if (!sku) {
            Swal.fire('Faltan Datos', 'El SKU del producto es obligatorio.', 'warning');
            return;
        }

        if (categorySelect && categorySelect.value === '') {
            Swal.fire('Faltan Datos', 'Debes seleccionar una categoría.', 'warning');
            return;
        }

        const selectedColors = Array.from(colorsSelect.selectedOptions).map(opt => ({ id: opt.value, name: opt.text }));
        const selectedSizes = Array.from(sizesSelect.selectedOptions).map(opt => ({ id: opt.value, name: opt.text }));

        if (selectedColors.length === 0 || selectedSizes.length === 0) {
            Swal.fire('Faltan Datos', 'Debes seleccionar al menos un color y una talla para poder configurar el stock.', 'warning');
            return;
        }

        // Construir la tabla tipo Excel
        buildExcelTable(selectedColors, selectedSizes);

        // Cambiar de paso
        step1.classList.remove('active');
        headerStep1.classList.remove('active');
        
        step2.classList.add('active');
        headerStep2.classList.add('active');

        btnPrev.style.display = 'inline-block';
        btnNext.style.display = 'none';
        btnSubmit.style.display = 'inline-block';
    });

    btnPrev.addEventListener('click', function () {
        // Cambiar de paso
        step2.classList.remove('active');
        headerStep2.classList.remove('active');
        
        step1.classList.add('active');
        headerStep1.classList.add('active');

        btnPrev.style.display = 'none';
        btnNext.style.display = 'inline-block';
        btnSubmit.style.display = 'none';
    });

    function buildExcelTable(colors, sizes) {
        const headerRow = document.getElementById('excel-header-row');
        const tbody = document.getElementById('excel-body');

        // Limpiar la tabla
        headerRow.innerHTML = '';
        tbody.innerHTML = '';

        // Construir el encabezado de tallas (Columnas)
        headerRow.innerHTML = '<th>Color \\ Talla</th>';
        sizes.forEach(size => {
            headerRow.innerHTML += `<th>${size.name}</th>`;
        });

        // Construir las filas (Colores)
        colors.forEach(color => {
            let tr = document.createElement('tr');
            tr.innerHTML = `<td style="font-weight:600; text-align:left;">${color.name}</td>`;
            
            sizes.forEach(size => {
                tr.innerHTML += `
                    <td>
                        <input type="number" 
                               name="inventory[${color.id}][${size.id}]" 
                               class="excel-input" 
                               value="0" 
                               min="0" style="text-align:center;">
                    </td>
                `;
            });
            
            tbody.appendChild(tr);
        });
    }

    // Validación antes de submit (Asegurar que haya inventario)
    btnSubmit.addEventListener('click', function (e) {
        let hasQuantity = false;
        const inputs = document.querySelectorAll('.excel-input');
        inputs.forEach(input => {
            if (parseInt(input.value) > 0) {
                hasQuantity = true;
            }
        });

        if (!hasQuantity) {
            e.preventDefault();
            Swal.fire('Faltan Datos', 'Debes ingresar una cantidad mayor a 0 en al menos una combinación de talla y color.', 'warning');
            return;
        }
    });
});
