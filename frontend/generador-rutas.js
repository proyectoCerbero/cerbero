document.addEventListener('DOMContentLoaded', () => {
    const AUTH_URL = new URL('../backend/api/auth.php?action=me', window.location.href).toString();
    const RUTAS_URL = new URL('../backend/api/rutas.php', window.location.href).toString();
    const OSRM_URL = 'https://router.project-osrm.org/trip/v1/driving';
    const acceso = document.getElementById('generadorAcceso');
    const modulo = document.getElementById('generadorModulo');
    const userPanel = document.getElementById('generadorUserPanel');
    const status = document.getElementById('generadorStatus');
    const generarBtn = document.getElementById('generarRutaBtn');
    const confirmarBtn = document.getElementById('confirmarRutaBtn');
    const cancelarBtn = document.getElementById('cancelarRutaBtn');
    const preview = document.getElementById('rutaPreview');
    const seleccionCantidad = document.getElementById('seleccionCantidad');
    const marcadores = new Map();
    const seleccionados = new Map();
    let mapa = null;
    let capaRecorrido = null;
    let centros = [];
    let rutaPendiente = null;
    let rutasGuardadas = new Map();
    let nombreSugerido = 'Ruta generada 01';
    const ROUTE_NAME_RE = /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9 .,#'()_+/-]{2,80}$/;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    const fetchJson = async (url, init = {}) => {
        const csrfToken = localStorage.getItem('cerberoCsrfToken');
        const isMutation = String(init.method || 'GET').toUpperCase() !== 'GET';
        const response = await fetch(url, {
            credentials: 'include',
            ...init,
            headers: {
                'Content-Type': 'application/json',
                ...(isMutation && csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
                ...(init.headers || {})
            }
        });
        const payload = await response.json();
        if (!response.ok || payload?.success === false) {
            throw new Error(payload?.message || `Error del servidor (${response.status}).`);
        }
        if (payload?.data?.csrf_token) localStorage.setItem('cerberoCsrfToken', payload.data.csrf_token);
        return payload;
    };

    const esAdmin = (role) => ['admin', 'administrador', 'administrador municipal']
        .includes(String(role || '').trim().toLowerCase());

    const distanciaKm = (a, b) => {
        const radio = 6371;
        const rad = (grados) => grados * Math.PI / 180;
        const dLat = rad(Number(b.latitud) - Number(a.latitud));
        const dLng = rad(Number(b.longitud) - Number(a.longitud));
        const lat1 = rad(Number(a.latitud));
        const lat2 = rad(Number(b.latitud));
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
        return 2 * radio * Math.asin(Math.sqrt(h));
    };

    const longitudRuta = (ruta, centro) => {
        if (!ruta.length) return 0;
        let total = distanciaKm(centro, ruta[0]) + distanciaKm(ruta[ruta.length - 1], centro);
        for (let i = 0; i < ruta.length - 1; i += 1) total += distanciaKm(ruta[i], ruta[i + 1]);
        return total;
    };

    const vecinoMasCercano = (puntos, centro) => {
        const pendientes = [...puntos];
        const ruta = [];
        let actual = centro;
        while (pendientes.length) {
            let mejor = 0;
            for (let i = 1; i < pendientes.length; i += 1) {
                if (distanciaKm(actual, pendientes[i]) < distanciaKm(actual, pendientes[mejor])) mejor = i;
            }
            actual = pendientes.splice(mejor, 1)[0];
            ruta.push(actual);
        }
        return ruta;
    };

    const optimizarDosOpt = (rutaInicial, centro) => {
        const ruta = [...rutaInicial];
        if (ruta.length < 4) return ruta;
        let mejoro = true;
        let pasadas = 0;
        while (mejoro && pasadas < 30) {
            mejoro = false;
            pasadas += 1;
            for (let i = 0; i < ruta.length - 1; i += 1) {
                for (let k = i + 1; k < ruta.length; k += 1) {
                    const anterior = i === 0 ? centro : ruta[i - 1];
                    const siguiente = k === ruta.length - 1 ? centro : ruta[k + 1];
                    const actual = distanciaKm(anterior, ruta[i]) + distanciaKm(ruta[k], siguiente);
                    const alternativo = distanciaKm(anterior, ruta[k]) + distanciaKm(ruta[i], siguiente);
                    if (alternativo + 0.001 < actual) {
                        const invertido = ruta.slice(i, k + 1).reverse();
                        ruta.splice(i, invertido.length, ...invertido);
                        mejoro = true;
                    }
                }
            }
        }
        return ruta;
    };

    const calcularCentroSugerido = (puntos) => {
        const candidatas = centros.map((centro) => {
            const orden = optimizarDosOpt(vecinoMasCercano(puntos, centro), centro);
            return { centro, orden, distancia: longitudRuta(orden, centro) };
        });
        candidatas.sort((a, b) => a.distancia - b.distancia);
        return candidatas[0];
    };

    const solicitarRutaVial = async (puntos) => {
        const aproximacion = calcularCentroSugerido(puntos);
        const entradas = [aproximacion.centro, ...puntos];
        const coordenadas = entradas
            .map(punto => `${Number(punto.longitud)},${Number(punto.latitud)}`)
            .join(';');
        const parametros = new URLSearchParams({
            roundtrip: 'true',
            source: 'first',
            steps: 'false',
            geometries: 'geojson',
            overview: 'full'
        });
        let response;
        try {
            response = await fetch(`${OSRM_URL}/${coordenadas}?${parametros.toString()}`);
        } catch (error) {
            throw new Error('No se pudo conectar con el servicio de rutas viales. Verificá la conexión a Internet e intentá nuevamente.');
        }
        const payload = await response.json().catch(() => null);
        if (!response.ok || payload?.code !== 'Ok' || !payload?.trips?.[0]) {
            throw new Error(payload?.message || 'El servicio de rutas viales no pudo calcular el recorrido. Verificá la conexión a Internet e intentá nuevamente.');
        }

        const viaje = payload.trips[0];
        const geometria = viaje?.geometry?.coordinates;
        if (!Array.isArray(geometria) || geometria.length < 2 || !Array.isArray(payload.waypoints)) {
            throw new Error('El servicio vial devolvió una ruta incompleta.');
        }

        const orden = puntos.map((punto, indice) => ({
            punto,
            posicion: Number(payload.waypoints[indice + 1]?.waypoint_index ?? indice + 1)
        })).sort((a, b) => a.posicion - b.posicion).map(item => item.punto);

        return {
            centro: aproximacion.centro,
            orden,
            distancia: Number(viaje.distance) / 1000,
            duracionMinutos: Number(viaje.duration) / 60,
            geometria
        };
    };

    const actualizarSeleccion = () => {
        seleccionCantidad.textContent = String(seleccionados.size);
        generarBtn.disabled = seleccionados.size < 2;
    };

    const alternarContenedor = (contenedor) => {
        const id = Number(contenedor.id);
        const marker = marcadores.get(id);
        if (seleccionados.has(id)) {
            seleccionados.delete(id);
            marker?.setStyle({ color: '#47705d', fillColor: '#ffffff', fillOpacity: 1, radius: 7 });
        } else {
            seleccionados.set(id, contenedor);
            marker?.setStyle({ color: '#15803d', fillColor: '#22c55e', fillOpacity: 1, radius: 9 });
        }
        cancelarPreview(false);
        actualizarSeleccion();
    };

    const dibujarContenedores = (contenedores) => {
        const bounds = [];
        contenedores.forEach((contenedor) => {
            const punto = [Number(contenedor.latitud), Number(contenedor.longitud)];
            bounds.push(punto);
            const marker = L.circleMarker(punto, {
                radius: 7, color: '#47705d', weight: 3, fillColor: '#ffffff', fillOpacity: 1
            }).addTo(mapa);
            marker.bindTooltip(
                `<strong>Contenedor #${Number(contenedor.id)}</strong><br>${escapeHtml(contenedor.barrio || 'Montevideo')}<br>Llenado: ${Math.round(Number(contenedor.nivel_llenado || 0))} %<br><small>Clic para seleccionar</small>`,
                { direction: 'top', opacity: 0.98 }
            );
            marker.on('click', () => alternarContenedor(contenedor));
            marcadores.set(Number(contenedor.id), marker);
        });

        centros.forEach((centro) => {
            const punto = [Number(centro.latitud), Number(centro.longitud)];
            bounds.push(punto);
            const icono = L.divIcon({
                className: 'centro-reciclaje-marker centro-generador-marker',
                html: `<span aria-label="Centro de reciclaje">♻</span><small>${escapeHtml(centro.nombre)}</small>`,
                iconSize: [170, 48], iconAnchor: [20, 24]
            });
            L.marker(punto, { icon: icono, title: centro.nombre }).addTo(mapa)
                .bindTooltip(`<strong>${escapeHtml(centro.nombre)}</strong><br><span class="centro-tooltip-secondary">Centro candidato de salida y regreso</span>`);
        });
        if (bounds.length) mapa.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
    };

    const dibujarRecorrido = (ruta, color = '#7c3aed') => {
        if (capaRecorrido) mapa.removeLayer(capaRecorrido);
        const coordenadas = ruta.geometria.map(coordenada => [Number(coordenada[1]), Number(coordenada[0])]);
        capaRecorrido = L.polyline(coordenadas, { color, weight: 5, opacity: 0.9 }).addTo(mapa);
        mapa.fitBounds(coordenadas, { padding: [35, 35], maxZoom: 14 });
    };

    const generarRuta = async () => {
        if (seleccionados.size < 2 || !centros.length) return;
        generarBtn.disabled = true;
        generarBtn.textContent = 'Calculando por calles...';
        status.textContent = 'Buscando el recorrido vial más conveniente. Esto puede demorar unos segundos.';
        status.style.color = '#52665c';
        try {
            rutaPendiente = await solicitarRutaVial([...seleccionados.values()]);
            dibujarRecorrido(rutaPendiente);
            document.getElementById('rutaNombre').value = nombreSugerido;
            document.getElementById('rutaPreviewResumen').innerHTML =
                `<strong>${rutaPendiente.orden.length} contenedores</strong> · Base: <strong>${escapeHtml(rutaPendiente.centro.nombre)}</strong> · Distancia vial: <strong>${rutaPendiente.distancia.toFixed(2)} km</strong> · Duración estimada: <strong>${Math.round(rutaPendiente.duracionMinutos)} min</strong>`;
            preview.hidden = false;
            status.textContent = 'Ruta calculada sobre calles reales de Montevideo.';
            status.style.color = '#15803d';
            preview.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (error) {
            rutaPendiente = null;
            preview.hidden = true;
            status.textContent = error.message;
            status.style.color = '#b91c1c';
        } finally {
            generarBtn.textContent = 'Generar ruta';
            generarBtn.disabled = seleccionados.size < 2;
        }
    };

    function cancelarPreview(recentrar = true) {
        rutaPendiente = null;
        preview.hidden = true;
        if (capaRecorrido) {
            mapa.removeLayer(capaRecorrido);
            capaRecorrido = null;
        }
        if (recentrar && marcadores.size) {
            const puntos = [...seleccionados.values()].map(p => [Number(p.latitud), Number(p.longitud)]);
            if (puntos.length) mapa.fitBounds(puntos, { padding: [35, 35], maxZoom: 14 });
        }
    }

    const renderRutas = (rutas) => {
        const body = document.getElementById('rutasGeneradasBody');
        const lista = Array.isArray(rutas) ? rutas : [];
        rutasGuardadas = new Map(lista.map(ruta => [Number(ruta.id_ruta), ruta]));
        document.getElementById('rutasGeneradasCantidad').textContent = `${lista.length} ${lista.length === 1 ? 'ruta' : 'rutas'}`;
        body.innerHTML = lista.length ? lista.map(ruta => `
            <tr><td>#${Number(ruta.id_ruta)}</td><td>${escapeHtml(ruta.nombre)}</td><td>${escapeHtml(ruta.centro_nombre || '-')}</td><td>${Number(ruta.cantidad_puntos || 0)}</td><td>${Number(ruta.distancia || 0).toFixed(2)} km</td><td>${ruta.duracion_minutos ? `${Math.round(Number(ruta.duracion_minutos))} min` : '-'}</td><td><span class="badge badge-activo">${escapeHtml(ruta.estado || 'generada')}</span></td><td><button class="ruta-ver-btn" type="button" data-ver-ruta="${Number(ruta.id_ruta)}" ${Array.isArray(ruta.geometria) && ruta.geometria.length > 1 ? '' : 'disabled'}>Ver en mapa</button></td></tr>
        `).join('') : '<tr><td colspan="8" class="tabla-vacia">Todavía no hay rutas generadas.</td></tr>';
    };

    const verRutaGuardada = (idRuta) => {
        const ruta = rutasGuardadas.get(Number(idRuta));
        if (!ruta || !Array.isArray(ruta.geometria) || ruta.geometria.length < 2) return;
        dibujarRecorrido(ruta, '#2563eb');
        status.textContent = `${ruta.nombre}: ${Number(ruta.distancia || 0).toFixed(2)} km por calles · ${Math.round(Number(ruta.duracion_minutos || 0))} min estimados.`;
        status.style.color = '#1d4ed8';
        document.getElementById('generadorMapa').scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const cargarDatos = async () => {
        const payload = await fetchJson(RUTAS_URL);
        const data = payload.data || {};
        centros = Array.isArray(data.centros) ? data.centros : [];
        nombreSugerido = data.nombre_sugerido || 'Ruta generada 01';
        renderRutas(data.rutas || []);
        if (!mapa) {
            mapa = L.map('generadorMapa', { scrollWheelZoom: true }).setView([-34.9011, -56.1645], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap'
            }).addTo(mapa);
            dibujarContenedores(data.contenedores || []);
        }
    };

    const confirmarRuta = async () => {
        if (!rutaPendiente) return;
        const nombreInput = document.getElementById('rutaNombre');
        const nombre = nombreInput.value.replace(/\s+/g, ' ').trim();
        nombreInput.value = nombre;
        if (!ROUTE_NAME_RE.test(nombre)) {
            status.textContent = 'Ingresá un nombre de ruta válido, de 2 a 80 caracteres.';
            status.style.color = '#b91c1c';
            nombreInput.focus();
            return;
        }
        confirmarBtn.disabled = true;
        try {
            const payload = await fetchJson(RUTAS_URL, {
                method: 'POST',
                body: JSON.stringify({
                    nombre,
                    id_instalacion_base: Number(rutaPendiente.centro.id),
                    distancia: rutaPendiente.distancia,
                    duracion_minutos: rutaPendiente.duracionMinutos,
                    geometria: rutaPendiente.geometria,
                    contenedores: rutaPendiente.orden.map(punto => Number(punto.id))
                })
            });
            status.innerHTML = `${escapeHtml(payload.message || 'Ruta confirmada.')} <a class="ruta-listado-link" href="rutas.html">Verla en el apartado Rutas</a>`;
            status.style.color = '#15803d';
            seleccionados.clear();
            marcadores.forEach(marker => marker.setStyle({ color: '#47705d', fillColor: '#ffffff', fillOpacity: 1, radius: 7 }));
            actualizarSeleccion();
            cancelarPreview(false);
            const nuevosDatos = await fetchJson(RUTAS_URL);
            nombreSugerido = nuevosDatos?.data?.nombre_sugerido || nombreSugerido;
            renderRutas(nuevosDatos?.data?.rutas || []);
            document.querySelector('.generador-listado')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            status.textContent = error.message;
            status.style.color = '#b91c1c';
        } finally {
            confirmarBtn.disabled = false;
        }
    };

    generarBtn.addEventListener('click', generarRuta);
    confirmarBtn.addEventListener('click', confirmarRuta);
    cancelarBtn.addEventListener('click', () => cancelarPreview(true));
    document.getElementById('rutasGeneradasBody').addEventListener('click', (event) => {
        const boton = event.target.closest('[data-ver-ruta]');
        if (boton && !boton.disabled) verRutaGuardada(boton.dataset.verRuta);
    });

    const iniciar = async () => {
        try {
            const sesion = await fetchJson(AUTH_URL);
            const user = sesion?.data?.user || null;
            const role = sesion?.data?.role || user?.role || 'visitante';
            if (!user || !esAdmin(role)) {
                userPanel.innerHTML = '<span class="pill">Acceso restringido</span>';
                acceso.innerHTML = '<h2>Acceso exclusivo para administradores</h2><p>Solo un administrador municipal puede generar y confirmar nuevas rutas.</p><div class="incidencias-access-actions"><a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a><a class="auth-btn secondary" href="index.html">Volver a Principal</a></div>';
                return;
            }
            userPanel.innerHTML = `<div class="user-details"><strong>${escapeHtml(`${user.nombre || ''} ${user.apellido || ''}`.trim())}</strong><p>Administrador municipal</p></div>`;
            acceso.hidden = true;
            modulo.hidden = false;
            await cargarDatos();
            setTimeout(() => mapa?.invalidateSize(), 0);
        } catch (error) {
            acceso.innerHTML = `<h2>No se pudo abrir el generador</h2><p>${escapeHtml(error.message)}</p>`;
        }
    };

    iniciar();
});
