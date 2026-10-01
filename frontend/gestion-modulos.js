(function () {
    const MODULOS = {
        usuarios: {
            titulo: 'Usuarios',
            descripcion: 'Administrá las cuentas, roles y estados de acceso al sistema.',
            contenido: `
                <section class="gestion-seccion" id="seccionUsuarios">
                    <h2>Usuarios</h2>
                    <div id="usuariosStatus" class="gestion-status"></div>
                    <form id="crearUsuarioForm" class="form-cuadrilla" autocomplete="off">
                        <div><label for="usuarioCi">Cédula</label><input type="text" id="usuarioCi" inputmode="numeric" pattern="[0-9]{7,8}" minlength="7" maxlength="8" title="Ingresá únicamente 7 u 8 números" required></div>
                        <div><label for="usuarioNombre">Nombre</label><input type="text" id="usuarioNombre" minlength="2" maxlength="50" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]{2,50}" title="Usá únicamente letras, espacios, apóstrofes o guiones" required></div>
                        <div><label for="usuarioApellido">Apellido</label><input type="text" id="usuarioApellido" minlength="2" maxlength="50" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]{2,50}" title="Usá únicamente letras, espacios, apóstrofes o guiones" required></div>
                        <div><label for="usuarioEmail">Correo</label><input type="email" id="usuarioEmail" maxlength="100" required></div>
                        <div><label for="usuarioPassword">Contraseña inicial</label><input type="password" id="usuarioPassword" minlength="8" maxlength="72" required></div>
                        <div><label for="usuarioRol">Rol</label><select id="usuarioRol" required><option value="vecino">Vecino</option><option value="cuadrilla de recolección">Miembro de cuadrilla</option><option value="operario de centro">Operario de instalación</option><option value="administrador municipal">Administrador municipal</option></select></div>
                        <div><label for="usuarioCuadrilla">Cuadrilla (opcional)</label><select id="usuarioCuadrilla" disabled><option value="">Sin asignar</option></select></div>
                        <div><label for="usuarioEstado">Estado</label><select id="usuarioEstado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
                        <button type="submit" id="crearUsuarioBtn">Registrar usuario</button>
                    </form>
                    <table>
                        <thead><tr><th>CI</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Cuadrilla</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody id="usuariosTablaBody"><tr><td colspan="7" class="tabla-vacia">Cargando usuarios...</td></tr></tbody>
                    </table>
                </section>`
        },
        cuadrillas: {
            titulo: 'Cuadrillas',
            descripcion: 'Gestioná las cuadrillas, sus rutas, turnos y estados operativos.',
            contenido: `
                <section class="gestion-seccion" id="seccionCuadrillas">
                    <h2>Cuadrillas</h2>
                    <div id="cuadrillasStatus" class="gestion-status"></div>
                    <form id="crearCuadrillaForm" class="form-cuadrilla">
                        <input type="hidden" id="cuadrillaEditId" value="">
                        <div><label>Nombre</label><input type="text" id="cuadrillaNombre" minlength="2" maxlength="50" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9 .#'()_-]{2,50}" title="Ingresá entre 2 y 50 caracteres válidos" required></div>
                        <div><label>Ruta</label><select id="cuadrillaRuta" required><option value="">Cargando rutas...</option></select></div>
                        <div><label>Turno</label><select id="cuadrillaTurno" required><option value="00:00-08:00">00:00-08:00</option><option value="08:00-16:00">08:00-16:00</option><option value="16:00-00:00">16:00-00:00</option></select></div>
                        <div><label>Estado</label><select id="cuadrillaEstado"><option value="activa">Activa</option><option value="inactiva">Inactiva</option></select></div>
                        <button type="submit" id="crearCuadrillaBtn">Crear cuadrilla</button>
                        <button type="button" id="cancelarCuadrillaBtn" style="display:none;">Cancelar edición</button>
                    </form>
                    <table>
                        <thead><tr><th>ID</th><th>Nombre</th><th>Ruta</th><th>Turno</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody id="cuadrillasTablaBody"><tr><td colspan="6" class="tabla-vacia">Cargando...</td></tr></tbody>
                    </table>
                </section>`
        },
        camiones: {
            titulo: 'Camiones',
            descripcion: 'Administrá la flota, sus estados y las cuadrillas asignadas.',
            contenido: `
                <section class="gestion-seccion" id="seccionCamiones">
                    <h2>Camiones (Flota)</h2>
                    <div id="camionesStatus" class="gestion-status"></div>
                    <form id="crearCamionForm" class="form-cuadrilla">
                        <input type="hidden" id="camionEditId" value="">
                        <div><label>Matrícula</label><input type="text" id="camionMatricula" minlength="5" maxlength="10" pattern="[A-Za-z0-9]{2,4}-?[A-Za-z0-9]{3,4}" title="Usá un formato como ABC1234 o ABC-1234" required></div>
                        <div><label>Marca</label><input type="text" id="camionMarca" minlength="2" maxlength="50" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9 .#'()_-]{2,50}" required></div>
                        <div><label>Modelo</label><input type="text" id="camionModelo" minlength="1" maxlength="50" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9 .#'()_-]{1,50}" required></div>
                        <div><label>Año</label><input type="number" id="camionAnio" min="1950" max="2027" step="1" required></div>
                        <div><label>Kilometraje</label><input type="number" id="camionKilometraje" min="0" max="10000000" step="1"></div>
                        <div><label>Capacidad (m³)</label><input type="number" id="camionCapacidad" min="0.1" max="1000" step="0.1" required></div>
                        <div><label>Estado</label><select id="camionEstado"><option value="Operativo">Operativo</option><option value="Respaldo">Respaldo</option><option value="En reparación">En reparación</option><option value="Inactivo">Inactivo</option></select></div>
                        <div><label>Cuadrilla asignada</label><select id="camionCuadrilla"><option value="">Sin asignar</option></select></div>
                        <button type="submit" id="crearCamionBtn">Registrar camión</button>
                        <button type="button" id="cancelarCamionBtn" style="display:none;">Cancelar edición</button>
                    </form>
                    <table>
                        <thead><tr><th>Matrícula</th><th>Vehículo</th><th>Año</th><th>Capacidad</th><th>Estado</th><th>Cuadrilla</th><th>Ruta</th><th>Turno</th><th>Acciones</th></tr></thead>
                        <tbody id="camionesTablaBody"><tr><td colspan="9" class="tabla-vacia">Cargando...</td></tr></tbody>
                    </table>
                </section>`
        },
        contenedores: {
            titulo: 'Contenedores',
            descripcion: 'Consultá y actualizá capacidad, llenado, ubicación, estado e historial.',
            contenido: `
                <section class="gestion-seccion" id="seccionContenedores">
                    <h2>Contenedores</h2>
                    <div id="contenedoresStatus" class="gestion-status"></div>
                    <form id="crearContenedorForm" class="form-cuadrilla">
                        <input type="hidden" id="contenedorEditId" value="">
                        <div><label>Capacidad (L)</label><input type="number" id="contCapacidad" min="1" max="100000" step="0.1" required></div>
                        <div><label>Porcentaje de llenado (%)</label><input type="number" id="contNivel" max="100" min="0" step="1" required></div>
                        <div><label>ID Ubicación</label><input type="number" id="contUbicacion" min="1" step="1" placeholder="Ej: 1" required></div>
                        <div><label>ID Tipo Residuo</label><input type="number" id="contTipoResiduo" min="1" step="1" placeholder="Ej: 1" required></div>
                        <div><label>Estado</label><select id="contEstado" required><option value="en buen estado">En buen estado</option><option value="lleno">Lleno</option><option value="roto">Roto</option></select></div>
                        <button type="submit" id="crearContenedorBtn">Registrar contenedor</button>
                        <button type="button" id="cancelarContenedorBtn" style="display:none;">Cancelar edición</button>
                    </form>
                    <table>
                        <thead><tr><th>ID</th><th>Capacidad</th><th>Llenado</th><th>Estado</th><th>Ubicación</th><th>Acciones</th></tr></thead>
                        <tbody id="contenedoresTablaBody"><tr><td colspan="6" class="tabla-vacia">Cargando...</td></tr></tbody>
                    </table>
                </section>`
        },
        instalaciones: {
            titulo: 'Centros de Acopio',
            descripcion: 'Gestioná los centros, direcciones, horarios, capacidades y estados.',
            contenido: `
                <section class="gestion-seccion" id="seccionInstalaciones">
                    <h2>Centros de Acopio</h2>
                    <div id="instalacionesStatus" class="gestion-status"></div>
                    <form id="crearInstalacionForm" class="form-cuadrilla">
                        <input type="hidden" id="instalacionEditId" value="">
                        <div><label>Nombre del Centro</label><input type="text" id="instNombre" minlength="2" maxlength="80" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9 .,#'()_-]{2,80}" required></div>
                        <div><label>Calle</label><input type="text" id="instCalle" minlength="2" maxlength="100" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9 .,'_-]{2,100}" required></div>
                        <div><label>Número</label><input type="text" id="instNumero" maxlength="12" pattern="[A-Za-z0-9+/-]{1,12}" required></div>
                        <div><label>Teléfono</label><input type="tel" id="instTelefono" maxlength="25" pattern="[+0-9 ()-]{7,25}" title="Ingresá un teléfono válido"></div>
                        <div><label>Horario</label><input type="text" id="instHorario" maxlength="25" pattern="[0-2][0-9]:[0-5][0-9]( ?- ?[0-2][0-9]:[0-5][0-9])?" placeholder="Ej: 08:00 - 18:00"></div>
                        <div><label>Capacidad (T)</label><input type="number" id="instCapacidad" min="0" max="100000" step="0.1"></div>
                        <div><label>Estado</label><select id="instEstado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
                        <button type="submit" id="crearInstalacionBtn">Registrar Centro</button>
                        <button type="button" id="cancelarInstalacionBtn" style="display:none;">Cancelar edición</button>
                    </form>
                    <table>
                        <thead><tr><th>Nombre</th><th>Dirección</th><th>Horario</th><th>Capacidad</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody id="instalacionesTablaBody"><tr><td colspan="6" class="tabla-vacia">Cargando...</td></tr></tbody>
                    </table>
                </section>`
        },
        maquinaria: {
            titulo: 'Maquinaria',
            descripcion: 'Administrá la maquinaria operativa de los centros de acopio.',
            contenido: `
                <section class="gestion-seccion" id="seccionMaquinaria">
                    <h2>Maquinaria</h2>
                    <div id="maquinariaStatus" class="gestion-status"></div>
                    <form id="crearMaquinariaForm" class="form-cuadrilla">
                        <input type="hidden" id="maquinariaEditId" value="">
                        <div><label>Nombre</label><input type="text" id="maqNombre" minlength="2" maxlength="80" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9 .,#'()_-]{2,80}" placeholder="Ej: Compactadora #3" required></div>
                        <div><label>Tipo</label><input type="text" id="maqTipo" minlength="2" maxlength="60" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9 .,#'()_-]{2,60}" placeholder="Ej: Compactadora" required></div>
                        <div><label>ID Centro de Acopio</label><input type="number" id="maqInstalacion" min="1" step="1" placeholder="Ej: 1" required></div>
                        <div><label>Estado</label><select id="maqEstado"><option value="operativa">Operativa</option><option value="mantenimiento">Mantenimiento</option><option value="inactiva">Inactiva</option></select></div>
                        <button type="submit" id="crearMaquinariaBtn">Registrar Maquinaria</button>
                        <button type="button" id="cancelarMaquinariaBtn" style="display:none;">Cancelar edición</button>
                    </form>
                    <table>
                        <thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>ID Centro</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody id="maquinariaTablaBody"><tr><td colspan="6" class="tabla-vacia">Cargando...</td></tr></tbody>
                    </table>
                </section>`
        }
    };

    const clave = document.body.dataset.gestionSection || 'usuarios';
    const modulo = MODULOS[clave] || MODULOS.usuarios;
    const root = document.getElementById('gestionPageRoot');
    if (!root) return;

    root.innerHTML = `
        <div class="contenedor">
            <aside class="sidebar">
                <div>
                    <div class="sidebar-brand"><h1>Cerbero</h1><img src="img/Logo SiGeRu Cerbero.png" alt="Logo de Cerbero" class="sidebar-brand-logo" width="48" height="48"></div>
                    <p>Sistema de Gestión de Residuos Urbanos</p>
                    <nav id="sidebarNav" data-current-page="${clave}" aria-label="Menú principal"><a href="index.html">Principal</a><a href="usuarios.html">Usuarios</a></nav>
                </div>
                <div class="empresa"><p>Empresa</p><h2>Cerbero S.A.S.</h2></div>
            </aside>
            <main class="contenido">
                <header class="gestion-header">
                    <div class="gestion-heading"><span class="pill pill-gestion">Administración</span><h1>${modulo.titulo}</h1><p>${modulo.descripcion}</p></div>
                    <div class="gestion-actions"><div id="gestionUserPanel" class="user-panel"></div><a class="auth-btn secondary" href="index.html">Volver al inicio</a></div>
                </header>
                <div id="gestionAccesoDenegado" class="gestion-status" style="display:none;"></div>
                <div id="gestionGrid" class="gestion-grid gestion-grid-unica">${modulo.contenido}</div>
            </main>
        </div>
        ${clave === 'contenedores' ? `
            <div id="historialContenedorModal" class="gestion-modal" hidden aria-hidden="true">
                <div class="gestion-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="historialContenedorTitulo">
                    <div class="gestion-modal-header"><div><span class="pill pill-gestion">Registro</span><h2 id="historialContenedorTitulo">Historial de llenado</h2></div><button id="cerrarHistorialContenedorBtn" class="gestion-modal-close" type="button" aria-label="Cerrar">×</button></div>
                    <p class="gestion-modal-help">Cada limpieza conserva el porcentaje observado antes de vaciar el contenedor.</p>
                    <div class="incidencias-table-wrap"><table class="historial-table"><thead><tr><th>Fecha y hora</th><th>Antes</th><th>Después</th><th>Acción</th><th>Registrado por</th></tr></thead><tbody id="historialContenedorBody"><tr><td colspan="5" class="tabla-vacia">Cargando historial...</td></tr></tbody></table></div>
                </div>
            </div>` : ''}
    `;
})();
