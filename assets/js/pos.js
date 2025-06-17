// Variables globales
let productosVenta = [];
let metodoPago = 'efectivo';
let ventaEnProceso = false;

// usuarioId ya está definido en pos.php como const usuarioId

// Función para agregar producto a la venta
function agregarProducto(elemento) {
    if (ventaEnProceso) return;

    const id = elemento.dataset.id;
    const nombre = elemento.dataset.nombre;
    const precio = parseFloat(elemento.dataset.precio);

    // Buscar si el producto ya está en la venta
    const productoExistente = productosVenta.find(p => p.id === id);

    if (productoExistente) {
        productoExistente.cantidad++;
    } else {
        productosVenta.push({
            id,
            nombre,
            precio,
            cantidad: 1
        });
    }

    actualizarVenta();
    reproducirSonido('click');
}

// Función para actualizar la vista de la venta
function actualizarVenta() {
    const ventaItems = document.getElementById('venta-items');
    const ventaTotal = document.getElementById('venta-total');

    ventaItems.innerHTML = '';
    let total = 0;

    productosVenta.forEach((producto, index) => {
        const subtotal = producto.precio * producto.cantidad;
        total += subtotal;

        const itemHTML = `
            <div class="venta-item" data-index="${index}">
                <div class="venta-item-info">
                    <span class="venta-item-nombre">${producto.nombre}</span>
                    <span class="venta-item-precio">$${producto.precio.toFixed(2)}</span>
                </div>
                <div class="venta-item-cantidad">
                    <button onclick="modificarCantidad(${index}, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span>${producto.cantidad}</span>
                    <button onclick="modificarCantidad(${index}, 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="eliminar-btn" onclick="eliminarProducto(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="venta-item-subtotal">$${subtotal.toFixed(2)}</div>
            </div>
        `;

        ventaItems.innerHTML += itemHTML;
    });

    ventaTotal.textContent = `$${total.toFixed(2)}`;
}

// Función para modificar cantidad
function modificarCantidad(index, cambio) {
    if (ventaEnProceso) return;

    const producto = productosVenta[index];
    producto.cantidad += cambio;

    if (producto.cantidad <= 0) {
        productosVenta.splice(index, 1);
    }

    actualizarVenta();
    reproducirSonido('click');
}

// Función para eliminar producto
function eliminarProducto(index) {
    if (ventaEnProceso) return;

    productosVenta.splice(index, 1);
    actualizarVenta();
    reproducirSonido('delete');
}

// Función para limpiar la venta
function limpiarVenta() {
    if (ventaEnProceso) return;

    if (productosVenta.length > 0 && !confirm('¿Estás seguro de limpiar la venta actual?')) {
        return;
    }

    productosVenta = [];
    actualizarVenta();
    reproducirSonido('clear');
}

// Función para cancelar venta
function cancelarVenta() {
    if (ventaEnProceso) return;

    if (productosVenta.length > 0 && !confirm('¿Cancelar esta venta?')) {
        return;
    }

    window.location.href = 'dashboard.php';
}

// Función para finalizar la venta
async function finalizarVenta() {
    if (ventaEnProceso || productosVenta.length === 0) {
        alert('No hay productos en la venta para procesar.');
        return;
    }

    ventaEnProceso = true;
    const cobrarBtn = document.querySelector('.cobrar-btn');
    cobrarBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    cobrarBtn.disabled = true;

    try {
        const total = productosVenta.reduce((sum, p) => sum + (p.precio * p.cantidad), 0);

        const response = await fetch('procesar_venta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                productos: productosVenta,
                total: total.toFixed(2),
                metodo_pago: metodoPago,
                usuario_id: usuarioId
            })
        });

        const data = await response.json();

        if (data.success) {
            alert(`Venta #${data.venta_id} registrada con éxito!`);

            if (confirm('¿Deseas imprimir el ticket?')) {
                window.open(`ticket.php?venta_id=${data.venta_id}`, '_blank');
            }

            productosVenta = [];
            actualizarVenta();
        } else {
            throw new Error(data.message || 'No se pudo completar la venta.');
        }
    } catch (error) {
        console.error('Error al procesar la venta:', error);
        alert(`Error al procesar la venta: ${error.message}`);
    } finally {
        ventaEnProceso = false;
        cobrarBtn.innerHTML = '<i class="fas fa-cash-register"></i> Cobrar';
        cobrarBtn.disabled = false;
    }
}

// Función para reproducir sonidos
function reproducirSonido(tipo) {
    try {
        const audio = new Audio(`assets/sounds/${tipo}.mp3`);
        audio.volume = 0.3;
        audio.play().catch(e => console.log('No se pudo reproducir sonido:', e));
    } catch (e) {
        console.log('Error con sonidos:', e);
    }
}

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    // Configurar métodos de pago
    document.querySelectorAll('.metodo-btn').forEach(btn => {
        btn.removeEventListener('click', handleMetodoPago); // Evitar duplicados
        btn.addEventListener('click', handleMetodoPago);
    });

    // Configurar filtrado por categoría
    document.querySelectorAll('.categoria-btn').forEach(btn => {
        btn.removeEventListener('click', handleCategoria); // Evitar duplicados
        btn.addEventListener('click', handleCategoria);
    });

    // Hacer productos interactivos
    document.querySelectorAll('.producto-card').forEach(card => {
        card.removeEventListener('click', handleProductoClick); // Evitar duplicados
        card.removeEventListener('keypress', handleProductoKeypress); // Evitar duplicados
        card.addEventListener('click', handleProductoClick);
        card.addEventListener('keypress', handleProductoKeypress);
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');
        card.setAttribute('aria-label', `Agregar ${card.dataset.nombre} a la venta`);
    });
});

// Funciones manejadoras de eventos
function handleMetodoPago() {
    if (ventaEnProceso) return;

    document.querySelectorAll('.metodo-btn').forEach(b => {
        b.classList.remove('active');
        b.innerHTML = b.innerHTML.replace(' ✓', '');
    });

    this.classList.add('active');
    this.innerHTML += ' ✓';
    metodoPago = this.dataset.metodo;
    reproducirSonido('click');
}

function handleCategoria() {
    if (ventaEnProceso) return;

    document.querySelectorAll('.categoria-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');

    const categoria = this.dataset.categoria;
    document.querySelectorAll('.producto-card').forEach(producto => {
        producto.style.display = (categoria === 'all' || producto.dataset.categoria === categoria)
            ? 'block'
            : 'none';
    });

    reproducirSonido('click');
}

function handleProductoClick() {
    agregarProducto(this);
}

function handleProductoKeypress(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        agregarProducto(this);
    }
}