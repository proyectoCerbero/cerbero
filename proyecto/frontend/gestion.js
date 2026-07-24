document.addEventListener('DOMContentLoaded', () => {
    const USUARIOS_URL = new URL('../backend/api/usuarios.php', window.location.href).toString();
    const CUADRILLAS_URL = new URL('../backend/api/cuadrillas.php', window.location.href).toString();

    const ROLES = ['vecino', 'cuadrilla', 'operario', 'admin'];
    const ESTADOS_USUARIO = ['activo', 'inactivo', 'suspendido'];

    const usuariosStatus = document.getElementById('usuariosStatus');
    const usuariosBody = document.getElementById('usuariosTablaBody');
    const cuadrillasStatus = document.getElementById('cuadrillasStatus');
    const cuadrillasBody = document.getElementById('cuadrillasTablaBody');
    const crearCuadrillaForm = document.getElementById('crearCuadrillaForm');
    const crearCuadrillaBtn = document.getElementById('crearCuadrillaBtn');

    const showStatus = (el, message, isError = false) => {
        el.textContent = message;
        el.style.color = isError ? 'crimson' : 'green';
    };

    const fetchJson = async (url, init) => {
        const response = await fetch(url, init);
        const text = await response.text();

        let payload = null;
        try {
            payload = text ? JSON.parse(text) : null;
        } catch (error) {
            throw new Error(`Respuesta no válida del servidor (${response.status}): ${text}`);
        }

        if (!payload) {
            throw new Error(`El servidor respondió ${response.status} sin contenido.`);
        }

        return payload;
    };

    // ---------- USUARIOS ----------

    const renderUsuarios = (usuarios) => {
        if (!usuarios.length) {
            usuariosBody.innerHTML = '<tr><td colspan="6" class="tabla-vacia">No hay usuarios registrados todavía.</td></tr>';
            return;
        }

        usuariosBody.innerHTML = usuarios.map((usuario) => {
            const rolActual = usuario.role || 'vecino';
            const estadoActual = usuario.estado || 'activo';
            const badgeClass = estadoActual === 'activo' ? 'badge-activo' : (estadoActual === 'suspendido' ? 'badge-suspendido' : 'badge-inactivo');
            const proximoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';
            const textoBoton = estadoActual === 'activo' ? 'Desactivar' : 'Activar';
            const claseBoton = estadoActual === 'activo' ? 'btn-accion peligro' : 'btn-accion';

            const opcionesRol = ROLES.map((rol) => (
                `<option value="${rol}" ${rol === rolActual ? 'selected' : ''}>${rol}</option>`
            )).join('');

            return `
                <tr data-ci="${usuario.ci}">
                    <td>${usuario.ci}</td>
                    <td>${usuario.nombre} ${usuario.apellido}</td>
                    <td>${usuario.email}</td>
                    <td>
                        <select class="rol-select">${opcionesRol}</select>
                    </td>
                    <td><span class="badge ${badgeClass}">${estadoActual}</span></td>
                    <td>
                        <div class="acciones-cell">
                            <button type="button" class="btn-accion btn-guardar-rol">Guardar rol</button>
                            <button type="button" class="${claseBoton} btn-toggle-estado" data-proximo-estado="${proximoEstado}">${textoBoton}</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const cargarUsuarios = async () => {
        try {
            const result = await fetchJson(USUARIOS_URL);
            if (!result.success) throw new Error(result.message);
            renderUsuarios(result.data.usuarios || []);
        } catch (error) {
            usuariosBody.innerHTML = '<tr><td colspan="6" class="tabla-vacia">No se pudieron cargar los usuarios.</td></tr>';
            showStatus(usuariosStatus, error.message || 'Error al cargar usuarios.', true);
        }
    };

    usuariosBody.addEventListener('click', async (event) => {
        const fila = event.target.closest('tr[data-ci]');
        if (!fila) return;
        const ci = fila.dataset.ci;

        // Botón: guardar rol seleccionado
        if (event.target.classList.contains('btn-guardar-rol')) {
            const rolSeleccionado = fila.querySelector('.rol-select').value;
            const boton = event.target;
            boton.disabled = true;
            showStatus(usuariosStatus, 'Actualizando rol...');

            try {
                const result = await fetchJson(`${USUARIOS_URL}?action=rol`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ci, rol: rolSeleccionado })
                });

                showStatus(usuariosStatus, result.message, !result.success);
                if (result.success) await cargarUsuarios();
            } catch (error) {
                showStatus(usuariosStatus, error.message || 'No se pudo actualizar el rol.', true);
            } finally {
                boton.disabled = false;
            }
        }

        // Botón: activar/desactivar
        if (event.target.classList.contains('btn-toggle-estado')) {
            const boton = event.target;
            const proximoEstado = boton.dataset.proximoEstado;
            boton.disabled = true;
            showStatus(usuariosStatus, 'Actualizando estado...');

            try {
                const result = await fetchJson(`${USUARIOS_URL}?action=estado`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ci, estado: proximoEstado })
                });

                showStatus(usuariosStatus, result.message, !result.success);
                if (result.success) await cargarUsuarios();
            } catch (error) {
                showStatus(usuariosStatus, error.message || 'No se pudo actualizar el estado.', true);
            } finally {
                boton.disabled = false;
            }
        }
    });

    // ---------- CUADRILLAS ----------

    const renderCuadrillas = (cuadrillas) => {
        if (!cuadrillas.length) {
            cuadrillasBody.innerHTML = '<tr><td colspan="4" class="tabla-vacia">No hay cuadrillas creadas todavía.</td></tr>';
            return;
        }

        cuadrillasBody.innerHTML = cuadrillas.map((cuadrilla) => `
            <tr>
                <td>${cuadrilla.id_cuadrilla}</td>
                <td>${cuadrilla.nombre}</td>
                <td>${cuadrilla.turno ?? '-'}</td>
                <td>${cuadrilla.estado ?? '-'}</td>
            </tr>
        `).join('');
    };

    const cargarCuadrillas = async () => {
        try {
            const result = await fetchJson(CUADRILLAS_URL);
            if (!result.success) throw new Error(result.message);
            renderCuadrillas(result.data.cuadrillas || []);
        } catch (error) {
            cuadrillasBody.innerHTML = '<tr><td colspan="4" class="tabla-vacia">No se pudieron cargar las cuadrillas.</td></tr>';
            showStatus(cuadrillasStatus, error.message || 'Error al cargar cuadrillas.', true);
        }
    };

    crearCuadrillaForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const nombre = document.getElementById('cuadrillaNombre').value.trim();
        const turno = document.getElementById('cuadrillaTurno').value;
        const estado = document.getElementById('cuadrillaEstado').value;

        if (!nombre) {
            showStatus(cuadrillasStatus, 'El nombre de la cuadrilla es obligatorio.', true);
            return;
        }

        crearCuadrillaBtn.disabled = true;
        showStatus(cuadrillasStatus, 'Creando cuadrilla...');

        try {
            const result = await fetchJson(CUADRILLAS_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre, turno, estado })
            });

            showStatus(cuadrillasStatus, result.message, !result.success);

            if (result.success) {
                crearCuadrillaForm.reset();
                await cargarCuadrillas();
            }
        } catch (error) {
            showStatus(cuadrillasStatus, error.message || 'No se pudo crear la cuadrilla.', true);
        } finally {
            crearCuadrillaBtn.disabled = false;
        }
    });

    cargarUsuarios();
    cargarCuadrillas();
});
