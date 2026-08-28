document.addEventListener('DOMContentLoaded', () => {
    const API_BASE = '../backend/api';
    const USUARIOS_URL = new URL(`${API_BASE}/usuarios.php`, window.location.href).toString();
    const CUADRILLAS_URL = new URL(`${API_BASE}/cuadrillas.php`, window.location.href).toString();
    const CAMIONES_URL = new URL(`${API_BASE}/camiones.php`, window.location.href).toString();
    const CONTENEDORES_URL = new URL(`${API_BASE}/contenedores.php`, window.location.href).toString();
    const INSTALACIONES_URL = new URL(`${API_BASE}/instalaciones.php`, window.location.href).toString();
    const MAQUINARIA_URL = new URL(`${API_BASE}/maquinaria.php`, window.location.href).toString();

    const ROLE_OPTIONS = [
        { value: 'vecino', label: 'Vecino' },
        { value: 'cuadrilla de recolección', label: 'Cuadrilla de recolección' },
        { value: 'operario de centro', label: 'Operario de centro' },
        { value: 'administrador municipal', label: 'Administrador municipal' }
    ];

    const currentUser = JSON.parse(localStorage.getItem('cerberoUser') || 'null');
    const viewSelector = document.getElementById('gestionViewSelector');
    const isCurrentUserAdmin = () => viewSelector?.value === 'admin' || String(currentUser?.role || '').trim().toLowerCase().includes('admin');

    const showStatus = (el, message, isError = false) => {
        el.textContent = message;
        el.style.color = isError ? 'crimson' : 'green';
        setTimeout(() => el.textContent = '', 4000);
    };

    const fetchJson = async (url, init) => {
        const response = await fetch(url, init);
        const text = await response.text();
        try {
            return text ? JSON.parse(text) : null;
        } catch (error) {
            throw new Error(`Error del servidor (${response.status})`);
        }
    };

    const genericDelete = async (url, id, idField, statusElement, refreshCallback) => {
        if (!confirm('¿Estás seguro de eliminar este registro?')) return;
        try {
            const result = await fetchJson(`${url}?action=delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ [idField]: id })
            });
            showStatus(statusElement, result.message || 'Eliminado con éxito', !result.success);
            if (result.success) await refreshCallback();
        } catch (error) {
            showStatus(statusElement, 'Error al eliminar', true);
        }
    };

    // ---------- CARGA DE DATOS (CRUD READ) ----------

    const cargarDatos = async () => {
        try {
            const [users, cuadrillas, camiones, contenedores, instalaciones, maquinaria] = await Promise.all([
                fetchJson(USUARIOS_URL).catch(() => ({ success: false, data: { usuarios: [] } })),
                fetchJson(CUADRILLAS_URL).catch(() => ({ success: false, data: { cuadrillas: [] } })),
                fetchJson(CAMIONES_URL).catch(() => ({ success: false, data: { camiones: [] } })),
                fetchJson(CONTENEDORES_URL).catch(() => ({ success: false, data: { contenedores: [] } })),
                fetchJson(INSTALACIONES_URL).catch(() => ({ success: false, data: { instalaciones: [] } })),
                fetchJson(MAQUINARIA_URL).catch(() => ({ success: false, data: { maquinaria: [] } }))
            ]);

            renderUsuarios(users.data?.usuarios || []);
            renderCuadrillas(cuadrillas.data?.cuadrillas || []);
            renderCamiones(camiones.data?.camiones || []);
            renderContenedores(contenedores.data?.contenedores || []);
            renderInstalaciones(instalaciones.data?.instalaciones || []);
            renderMaquinaria(maquinaria.data?.maquinaria || []);

            // Llenar select de cuadrillas en formulario de camiones
            const camionCuadrillaSelect = document.getElementById('camionCuadrilla');
            if (camionCuadrillaSelect) {
                camionCuadrillaSelect.innerHTML = '<option value="">Sin asignar</option>' + 
                    (cuadrillas.data?.cuadrillas || []).map(c => `<option value="${c.id_cuadrilla}">${c.nombre}</option>`).join('');
            }
        } catch (error) {
            console.error('Error al cargar datos generales', error);
        }
    };

    // ---------- RENDERIZADOS DE TABLAS ----------

    const renderUsuarios = (usuarios) => {
        const body = document.getElementById('usuariosTablaBody');
        body.innerHTML = usuarios.length ? usuarios.map(u => `
            <tr data-ci="${u.ci}">
                <td>${u.ci}</td>
                <td>${u.nombre} ${u.apellido}</td>
                <td>${u.email}</td>
                <td>${isCurrentUserAdmin() ? `<select class="rol-select">${ROLE_OPTIONS.map(r => `<option value="${r.value}" ${r.value === u.role ? 'selected' : ''}>${r.label}</option>`).join('')}</select>` : u.role}</td>
                <td><span class="badge ${u.estado === 'activo' ? 'badge-activo' : 'badge-inactivo'}">${u.estado}</span></td>
                <td>
                    ${isCurrentUserAdmin() ? `
                        <button class="btn-accion btn-guardar-rol">Guardar Rol</button>
                        <button class="btn-accion peligro btn-toggle-estado" data-estado="${u.estado === 'activo' ? 'inactivo' : 'activo'}">${u.estado === 'activo' ? 'Desactivar' : 'Activar'}</button>
                    ` : '-'}
                </td>
            </tr>
        `).join('') : '<tr><td colspan="6">No hay usuarios.</td></tr>';
    };

    const renderCuadrillas = (cuadrillas) => {
        document.getElementById('cuadrillasTablaBody').innerHTML = cuadrillas.length ? cuadrillas.map(c => `
            <tr>
                <td>${c.id_cuadrilla}</td><td>${c.nombre}</td><td>${c.turno}</td><td>${c.estado}</td>
                <td>${isCurrentUserAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${CUADRILLAS_URL}', ${c.id_cuadrilla}, 'id_cuadrilla', document.getElementById('cuadrillasStatus'), cargarDatos)">Eliminar</button>` : '-'}</td>
            </tr>
        `).join('') : '<tr><td colspan="5">No hay cuadrillas.</td></tr>';
        document.getElementById('crearCuadrillaForm').style.display = isCurrentUserAdmin() ? 'grid' : 'none';
    };

    const renderCamiones = (camiones) => {
        document.getElementById('camionesTablaBody').innerHTML = camiones.length ? camiones.map(c => `
            <tr>
                <td>${c.matricula}</td><td>${c.marca} ${c.modelo}</td><td>${c.anio}</td><td>${c.capacidad}</td><td>${c.estado}</td><td>${c.cuadrilla_nombre || '-'}</td>
                <td>${isCurrentUserAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${CAMIONES_URL}', ${c.id_camion}, 'id_camion', document.getElementById('camionesStatus'), cargarDatos)">Eliminar</button>` : '-'}</td>
            </tr>
        `).join('') : '<tr><td colspan="7">No hay camiones.</td></tr>';
        document.getElementById('crearCamionForm').style.display = isCurrentUserAdmin() ? 'grid' : 'none';
    };

    const renderContenedores = (contenedores) => {
        document.getElementById('contenedoresTablaBody').innerHTML = contenedores.length ? contenedores.map(c => `
            <tr>
                <td>${c.id_contenedor}</td><td>${c.capacidad} L</td><td>${c.nivel_llenado}%</td><td>${c.estado}</td><td>Ubic: ${c.id_ubicacion}</td>
                <td>${isCurrentUserAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${CONTENEDORES_URL}', ${c.id_contenedor}, 'id_contenedor', document.getElementById('contenedoresStatus'), cargarDatos)">Eliminar</button>` : '-'}</td>
            </tr>
        `).join('') : '<tr><td colspan="6">No hay contenedores.</td></tr>';
        document.getElementById('crearContenedorForm').style.display = isCurrentUserAdmin() ? 'grid' : 'none';
    };

    const renderInstalaciones = (instalaciones) => {
        document.getElementById('instalacionesTablaBody').innerHTML = instalaciones.length ? instalaciones.map(i => `
            <tr>
                <td>${i.nombre}</td><td>${i.calle} ${i.numero}</td><td>${i.horario}</td><td>${i.capacidad} T</td><td>${i.estado}</td>
                <td>${isCurrentUserAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${INSTALACIONES_URL}', ${i.id_instalacion}, 'id_instalacion', document.getElementById('instalacionesStatus'), cargarDatos)">Eliminar</button>` : '-'}</td>
            </tr>
        `).join('') : '<tr><td colspan="6">No hay instalaciones.</td></tr>';
        document.getElementById('crearInstalacionForm').style.display = isCurrentUserAdmin() ? 'grid' : 'none';
    };

    const renderMaquinaria = (maquinaria) => {
        document.getElementById('maquinariaTablaBody').innerHTML = maquinaria.length ? maquinaria.map(m => `
            <tr>
                <td>${m.id_maquinaria}</td><td>${m.tipo}</td><td>${m.id_instalacion}</td><td>${m.estado}</td>
                <td>${isCurrentUserAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${MAQUINARIA_URL}', ${m.id_maquinaria}, 'id_maquinaria', document.getElementById('maquinariaStatus'), cargarDatos)">Eliminar</button>` : '-'}</td>
            </tr>
        `).join('') : '<tr><td colspan="5">No hay maquinaria registrada.</td></tr>';
        document.getElementById('crearMaquinariaForm').style.display = isCurrentUserAdmin() ? 'grid' : 'none';
    };

    // ---------- EVENTOS FORMULARIOS (CRUD CREATE & UPDATE) ----------

    // Eventos para la tabla de Usuarios (Editar Rol y Estado)
    document.getElementById('usuariosTablaBody')?.addEventListener('click', async (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        const ci = tr.dataset.ci;

        if (e.target.classList.contains('btn-guardar-rol')) {
            const nuevoRol = tr.querySelector('.rol-select').value;
            try {
                await fetchJson(`${USUARIOS_URL}?action=updateRole`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ci, role: nuevoRol }) });
                showStatus(document.getElementById('usuariosStatus'), 'Rol actualizado');
                cargarDatos();
            } catch (error) { showStatus(document.getElementById('usuariosStatus'), 'Error al actualizar rol', true); }
        }

        if (e.target.classList.contains('btn-toggle-estado')) {
            const nuevoEstado = e.target.dataset.estado;
            try {
                await fetchJson(`${USUARIOS_URL}?action=updateEstado`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ci, estado: nuevoEstado }) });
                showStatus(document.getElementById('usuariosStatus'), 'Estado actualizado');
                cargarDatos();
            } catch (error) { showStatus(document.getElementById('usuariosStatus'), 'Error al actualizar estado', true); }
        }
    });

    // Evento Crear Cuadrilla
    document.getElementById('crearCuadrillaForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            nombre: document.getElementById('cuadrillaNombre').value,
            turno: document.getElementById('cuadrillaTurno').value,
            estado: document.getElementById('cuadrillaEstado').value
        };
        try {
            await fetchJson(CUADRILLAS_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            e.target.reset();
            cargarDatos();
        } catch (error) { showStatus(document.getElementById('cuadrillasStatus'), 'Error al crear cuadrilla', true); }
    });

    // Evento Crear Camión
    document.getElementById('crearCamionForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            matricula: document.getElementById('camionMatricula').value,
            marca: document.getElementById('camionMarca').value,
            modelo: document.getElementById('camionModelo').value,
            anio: document.getElementById('camionAnio').value,
            kilometraje: document.getElementById('camionKilometraje').value,
            capacidad: document.getElementById('camionCapacidad').value,
            estado: document.getElementById('camionEstado').value,
            id_cuadrilla: document.getElementById('camionCuadrilla').value
        };
        try {
            await fetchJson(CAMIONES_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            e.target.reset();
            cargarDatos();
        } catch (error) { showStatus(document.getElementById('camionesStatus'), 'Error al crear camión', true); }
    });

    // Evento Crear Contenedor
    document.getElementById('crearContenedorForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            capacidad: document.getElementById('contCapacidad').value,
            nivel_llenado: document.getElementById('contNivel').value || 0,
            id_ubicacion: document.getElementById('contUbicacion').value,
            id_tipo_residuo: document.getElementById('contTipoResiduo').value,
            estado: document.getElementById('contEstado').value
        };
        try {
            await fetchJson(CONTENEDORES_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            e.target.reset();
            cargarDatos();
        } catch (error) { showStatus(document.getElementById('contenedoresStatus'), 'Error al crear contenedor', true); }
    });

    // Evento Crear Instalación
    document.getElementById('crearInstalacionForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            nombre: document.getElementById('instNombre').value,
            calle: document.getElementById('instCalle').value,
            numero: document.getElementById('instNumero').value,
            telefono: document.getElementById('instTelefono').value,
            horario: document.getElementById('instHorario').value,
            capacidad: document.getElementById('instCapacidad').value || 0,
            estado: document.getElementById('instEstado').value
        };
        try {
            await fetchJson(INSTALACIONES_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            e.target.reset();
            cargarDatos();
        } catch (error) { showStatus(document.getElementById('instalacionesStatus'), 'Error al crear centro', true); }
    });

    // Evento Crear Maquinaria
    document.getElementById('crearMaquinariaForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            tipo: document.getElementById('maqTipo').value,
            id_instalacion: document.getElementById('maqInstalacion').value,
            estado: document.getElementById('maqEstado').value
        };
        try {
            await fetchJson(MAQUINARIA_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            e.target.reset();
            cargarDatos();
        } catch (error) { showStatus(document.getElementById('maquinariaStatus'), 'Error al registrar maquinaria', true); }
    });

    if (viewSelector) viewSelector.addEventListener('change', cargarDatos);

    // Adjuntar la función de eliminación global a la ventana para el uso inline de los botones
    window.genericDelete = genericDelete;

    cargarDatos();
});