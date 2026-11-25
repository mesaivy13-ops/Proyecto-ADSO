const listaPrestamos = document.getElementById("listaPrestamos");
let esAdministrador = false;
let prestamos = [];
let asociados = [];
let accionesConfiguradas = false;
const formularioPrestamo = document.getElementById("formPrestamo");
const modalPrestamo = new bootstrap.Modal(document.getElementById("modalPrestamo"));

async function solicitarApi(metodo, datos, endpoint = "../Modelos/gestionarPrestamos.php") {
    const opciones = {
        method: metodo,
        headers: { "Content-Type": "application/json" }
    };

    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

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

async function cargarAsociados() {
    try {
        const resultado = await solicitarApi("GET", null, "../Modelos/gestionarPrestamos.php?action=asociados");
        asociados = resultado.asociados || resultado.data || [];

        const selectAsociado = document.getElementById("idAsociado");
        if (!selectAsociado) return;

        selectAsociado.innerHTML = '<option value="" selected disabled>Seleccione un asociado...</option>';
        asociados.forEach((asociado) => {
            const id = asociado.idAsociado ?? asociado.id;
            const nombre = [asociado.nombre, asociado.nombres, asociado.apellido, asociado.apellidos]
                .filter(Boolean)
                .join(" ")
                .trim() || `Asociado #${id}`;

            const option = document.createElement("option");
            option.value = id;
            option.textContent = nombre;
            selectAsociado.append(option);
        });
    } catch (error) {
        console.error("Error al cargar asociados:", error);
    }
}

function obtenerNombreAsociado(idAsociado) {
    const asociado = asociados.find((a) => Number(a.idAsociado ?? a.id) === Number(idAsociado));
    if (!asociado) return `Asociado #${idAsociado}`;

    return [asociado.nombre, asociado.nombres, asociado.apellido, asociado.apellidos]
        .filter(Boolean)
        .join(" ")
        .trim() || `Asociado #${idAsociado}`;
}

async function cargarPrestamos() {
    try {
        await cargarAsociados();
        const resultado = await solicitarApi("GET");

        esAdministrador = resultado.rol === "admin";

        if (!esAdministrador) {
            document.getElementById("tituloPrestamos").textContent = "Mis préstamos";
            document.getElementById("descripcionPrestamos").textContent =
                "Solicita un préstamo y consulta únicamente los préstamos asociados a tu cuenta.";
        }

        configurarAcciones();

        prestamos = resultado.prestamos || resultado.data || [];
        mostrarPrestamos();
    } catch (error) {
        alert("Error al cargar los préstamos: " + error.message);
    }
}

function mostrarPrestamos() {
    listaPrestamos.replaceChildren();
    const columnas = esAdministrador ? 7 : 6;

    if (prestamos.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");
        celda.colSpan = columnas;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = "No hay préstamos disponibles.";
        fila.append(celda);
        listaPrestamos.append(fila);
        return;
    }

    prestamos.forEach((prestamo) => {
        const fila = document.createElement("tr");
        const valorFormateado = Number(prestamo.valorPrestamo).toLocaleString("es-CO", {
            style: "currency",
            currency: "COP"
        });

        fila.append(
            crearCelda(prestamo.nombreAsociado || obtenerNombreAsociado(prestamo.idAsociado)),
            crearCelda(valorFormateado),
            crearCelda(`${prestamo.tasaInteres}%`),
            crearCelda(prestamo.numeroCuotas),
            crearCelda(prestamo.fechaPrestamo),
            crearCelda(prestamo.estado)
        );

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap";

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn btn-sm btn-outline-primary me-2";
            botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
            botonEditar.addEventListener("click", () => abrirEdicion(prestamo));

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn btn-sm btn-outline-danger";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
            botonEliminar.addEventListener("click", () => eliminarPrestamo(prestamo));

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        }

        listaPrestamos.append(fila);
    });
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto;
    return celda;
}

async function eliminarPrestamo(prestamo) {
    const confirmar = window.confirm(`¿Desea eliminar el préstamo #${prestamo.idPrestamo}?`);

    if (!confirmar) {
        return;
    }

    try {
        await solicitarApi("DELETE", { idPrestamo: prestamo.idPrestamo });
        await cargarPrestamos();
        alert("Préstamo eliminado correctamente.");
    } catch (error) {
        alert(error.message);
    }
}

function configurarAcciones() {
    if (accionesConfiguradas) {
        return;
    }
    const botonNuevo = document.getElementById("nuevoPrestamo");
    const encabezadoAcciones = document.getElementById("encabezadoAcciones");

    if (esAdministrador) {
        botonNuevo?.classList.remove("d-none");
        botonNuevo?.addEventListener("click", abrirNuevoPrestamo);
        encabezadoAcciones?.classList.remove("d-none");
    } else {
        botonNuevo?.classList.remove("d-none");
        document.getElementById("textoBotonPrestamo").textContent = "Solicitar préstamo";
        botonNuevo?.addEventListener("click", abrirNuevoPrestamo);
    }
    accionesConfiguradas = true;
}

function abrirNuevoPrestamo() {
    formularioPrestamo.reset();
    document.getElementById("idPrestamo").value = "";
    document.getElementById("estado").value = "Pendiente";
    document.getElementById("campoAsociado").classList.toggle("d-none", !esAdministrador);
    document.getElementById("idAsociado").required = esAdministrador;
    document.getElementById("campoEstado").classList.toggle("d-none", !esAdministrador);
    document.getElementById("tituloModalPrestamo").textContent =
        esAdministrador ? "Nuevo préstamo" : "Solicitar préstamo";
    document.getElementById("guardarPrestamo").textContent =
        esAdministrador ? "Guardar préstamo" : "Enviar solicitud";
    modalPrestamo.show();
}

function abrirEdicion(prestamo) {
    formularioPrestamo.reset();
    document.getElementById("campoAsociado").classList.remove("d-none");
    document.getElementById("idAsociado").required = true;
    document.getElementById("campoEstado").classList.remove("d-none");
    document.getElementById("idPrestamo").value = prestamo.idPrestamo;
    document.getElementById("idAsociado").value = prestamo.idAsociado;
    document.getElementById("valorPrestamo").value = prestamo.valorPrestamo;
    document.getElementById("tasaInteres").value = prestamo.tasaInteres;
    document.getElementById("numeroCuotas").value = prestamo.numeroCuotas;
    document.getElementById("fechaPrestamo").value = prestamo.fechaPrestamo;
    document.getElementById("estado").value = prestamo.estado || "Pendiente";
    document.getElementById("tituloModalPrestamo").textContent = "Editar préstamo";
    document.getElementById("guardarPrestamo").textContent = "Guardar cambios";
    modalPrestamo.show();
}

formularioPrestamo.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    const idPrestamo = document.getElementById("idPrestamo").value;

    const datos = {
        valorPrestamo: Number(document.getElementById("valorPrestamo").value),
        tasaInteres: Number(document.getElementById("tasaInteres").value),
        numeroCuotas: Number(document.getElementById("numeroCuotas").value),
        fechaPrestamo: document.getElementById("fechaPrestamo").value
    };

    if (esAdministrador) {
        datos.idAsociado = Number(document.getElementById("idAsociado").value);
        datos.estado = document.getElementById("estado").value;
    }

    if (idPrestamo) {
        datos.idPrestamo = Number(idPrestamo);
    }

    try {
        await solicitarApi(idPrestamo ? "PUT" : "POST", datos);
        modalPrestamo.hide();
        alert(idPrestamo
            ? "Préstamo actualizado correctamente."
            : esAdministrador
                ? "Préstamo guardado correctamente."
                : "Solicitud enviada correctamente. Su estado es Pendiente.");
        await cargarPrestamos();
    } catch (error) {
        alert(error.message);
    }
});

cargarPrestamos();