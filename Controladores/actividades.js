const listaActividades = document.getElementById("listaActividades")
let esAdministrador = false;
let actividades = [];
let accionesConfiguradas = false;
const formularioActividad = document.getElementById("formActividad");
const modalActividad = new bootstrap.Modal(document.getElementById("modalActividad"));

async function solicitarApi(metodo, datos, accion = null) {
    const opciones = {
        method: metodo,
        headers: { "Content-Type": "application/json" }
    };

    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const endpoint = accion
        ? `../Modelos/gestionarActividades.php?action=${encodeURIComponent(accion)}`
        : "../Modelos/gestionarActividades.php";
    const respuesta = await fetch(endpoint, opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = "login.html";
    }
    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || "Error en la solicitud");
    }

    return resultado;
}
async function cargarActividades() {
    try {
        const resultado = await solicitarApi("GET");

        esAdministrador = resultado.rol === "admin";

        configurarAcciones();

        actividades = resultado.actividades;
        mostrarActividades();
    } catch (error) {
        alert("Error al cargar las actividades:" + error.message);
    }
}
function mostrarActividades() {
    listaActividades.replaceChildren();

    if (actividades.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td")
        celda.colSpan = 9;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = "No hay actividades disponibles.";
        fila.append(celda);
        listaActividades.append(fila);
        return;
    }
    actividades.forEach((actividad) => {
        const fila = document.createElement("tr");
        fila.append(
            crearCelda(actividad.nombreActividad),
            crearCelda(actividad.fechaEvento),
            crearCelda(actividad.municipio),
            crearCelda(actividad.totalRecaudado === null ? "Pendiente" : formatearPesos(actividad.totalRecaudado)),
            crearCelda(actividad.fondoClub === null ? "Se define al cierre" : formatearPesos(actividad.fondoClub)),
            crearCelda(actividad.fondoParticipantes === null ? "Se define al cierre" : formatearPesos(actividad.fondoParticipantes)),
            crearCelda(actividad.cantidadParticipantes),
            crearCelda(obtenerValorParticipante(actividad)),
        );
        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap";

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn btn-sm btn-outline-primary me-2";
            botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
            botonEditar.addEventListener("click", () => abrirEdicion(actividad));

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn btn-sm btn-outline-danger";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
            botonEliminar.addEventListener("click", () => eliminarActividad(actividad));

            

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        } else {
            const participacion = document.createElement("td");
            const botonParticipacion = document.createElement("button");
            botonParticipacion.type = "button";
            botonParticipacion.className = actividad.participa
                ? "btn btn-sm btn-outline-secondary"
                : "btn btn-sm btn-outline-success";
            botonParticipacion.textContent = actividad.participa
                ? "Retirar inscripción"
                : "Inscribirme";
            botonParticipacion.disabled = actividad.totalRecaudado !== null;
            botonParticipacion.addEventListener("click", () => cambiarParticipacion(actividad));
            participacion.append(botonParticipacion);
            fila.append(participacion);
        }
        listaActividades.append(fila);
    });
}

function formatearPesos(valor) {
    return Number(valor).toLocaleString("es-CO", {
        style: "currency",
        currency: "COP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function obtenerValorParticipante(actividad) {
    if (actividad.totalRecaudado === null) {
        return "Se calcula al cierre";
    }
    if (Number(actividad.cantidadParticipantes) === 0) {
        return "Sin participantes";
    }
    if (esAdministrador) {
        const minimo = actividad.valorParticipanteMin;
        const maximo = actividad.valorParticipanteMax;
        return minimo === maximo
            ? formatearPesos(minimo)
            : `${formatearPesos(minimo)}–${formatearPesos(maximo)}`;
    }
    if (!actividad.participa) {
        return "—";
    }
    return formatearPesos(actividad.valorParticipante);
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto;
    return celda;
}

async function cambiarParticipacion(actividad) {
    try {
        await solicitarApi(
            actividad.participa ? "DELETE" : "POST",
            { idActividad: actividad.idActividad },
            "participar"
        );
        await cargarActividades();
    } catch (error) {
        alert(error.message);
    }
}

async function eliminarActividad(actividad) {
    const confirmar = window.confirm(`¿Desea eliminar la actividad "${actividad.nombreActividad}"?`);

    if (!confirmar) {
        return;
    }

    try {
        await solicitarApi("DELETE", { idActividad: actividad.idActividad });
        await cargarActividades();
        alert("Actividad eliminada correctamente.");
    } catch (error) {
        alert(error.message);
    }
}
function configurarAcciones() {
    if (accionesConfiguradas) {
        return;
    }
    const botonNueva = document.getElementById("nuevaActividad");
    const encabezadoAcciones = document.getElementById("encabezadoAcciones");

    if (esAdministrador) {
        botonNueva.classList.remove("d-none");
        botonNueva.addEventListener("click", abrirNuevaActividad);//Le da funcionalidad al boton
        encabezadoAcciones.classList.remove("d-none");
        encabezadoAcciones.textContent = "Acciones";
        accionesConfiguradas = true;
        return;
    }
    encabezadoAcciones.classList.remove("d-none");
    encabezadoAcciones.textContent = "Participación";
    accionesConfiguradas = true;
}
function abrirNuevaActividad() {
    formularioActividad.reset();
    document.getElementById("campoTotalRecaudado").classList.add("d-none");
    document.getElementById("tituloModalActividad").textContent =
        "Nueva actividad";
    document.getElementById("guardarActividad").textContent =
        "Guardar actividad";
    modalActividad.show();
}
function abrirEdicion(actividad) {
    formularioActividad.reset();
    document.getElementById("idActividad").value = actividad.idActividad;
    document.getElementById("nombreActividad").value = actividad.nombreActividad;
    document.getElementById("fechaEvento").value = actividad.fechaEvento;
    document.getElementById("municipio").value = actividad.municipio;
    document.getElementById("totalRecaudado").value =
        actividad.totalRecaudado ?? "";
    document.getElementById("campoTotalRecaudado").classList.remove("d-none");
    document.getElementById("tituloModalActividad").textContent =
        "Editar actividad";
    document.getElementById("guardarActividad").textContent = "Guardar cambios";
    modalActividad.show();
}
formularioActividad.addEventListener("submit", async (evento)=>{
    evento.preventDefault();
    const idActividad=document.getElementById("idActividad").value;

    const datos ={
        nombreActividad: document.getElementById("nombreActividad").value.trim(),
        fechaEvento: document.getElementById("fechaEvento").value,
        municipio:document.getElementById("municipio").value.trim(),
    }
    if (idActividad) {
        datos.idActividad = Number(idActividad);
        const total = document.getElementById("totalRecaudado").value;
        datos.totalRecaudado = total === "" ? null : Number(total);
    }

    try {
        const resultado = await solicitarApi(idActividad ? "PUT" : "POST",datos);
        modalActividad.hide();
        alert(resultado.message);
        await cargarActividades();
    } catch (error) {
        alert(error.message)
    }
});
cargarActividades();