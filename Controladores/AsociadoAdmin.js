const listaAsociados = document.getElementById("listaActividades");
const formularioAsociado = document.getElementById("formAsociado");
const modalAsociado = new bootstrap.Modal(document.getElementById("modalActividad"));

let asociados = [];
let esAdministrador = false;

async function solicitarApi(metodo, datos = null) {
    const opciones = {
        method: metodo,
        headers: { "Content-Type": "application/json" }
    };

    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../Modelos/gestionarAsociados.php", opciones);
    const texto = await respuesta.text();
    let resultado;

    try {
        resultado = JSON.parse(texto);
    } catch (error) {
        throw new Error("El servidor respondió con datos inválidos.");
    }

    if (respuesta.status === 401) {
        window.location.href = "Login.html";
        return;
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || "Error en la solicitud");
    }

    return resultado;
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto ?? "";
    return celda;
}

function configurarAcciones() {
    const encabezadoAcciones = document.getElementById("encabezadoAcciones");

    if (esAdministrador) {
        encabezadoAcciones.classList.remove("d-none");
    } else {
        encabezadoAcciones.classList.add("d-none");
    }
}

function mostrarAsociados() {
    listaAsociados.replaceChildren();

    if (asociados.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");
        celda.colSpan = esAdministrador ? 10 : 9;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = "No hay asociados disponibles.";
        fila.append(celda);
        listaAsociados.append(fila);
        return;
    }

    asociados.forEach((asociado) => {
        const fila = document.createElement("tr");
        fila.append(
            crearCelda(asociado.documento),
            crearCelda(`${asociado.nombres} ${asociado.apellidos}`),
            crearCelda(asociado.fechaNacimiento),
            crearCelda(asociado.direccionResidencia),
            crearCelda(asociado.genero),
            crearCelda(asociado.ciudad),
            crearCelda(asociado.telefono),
            crearCelda(asociado.email),
            crearCelda(asociado.rol)
        );

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap";

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn btn-sm btn-outline-primary me-2";
            botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
            botonEditar.addEventListener("click", () => abrirEdicion(asociado));

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn btn-sm btn-outline-danger";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
            botonEliminar.addEventListener("click", () => eliminarAsociado(asociado));

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        }

        listaAsociados.append(fila);
    });
}

async function cargarAsociados() {
    try {
        const resultado = await solicitarApi("GET");
        esAdministrador = resultado.rol === "admin";
        asociados = resultado.asociados;
        configurarAcciones();
        mostrarAsociados();
    } catch (error) {
        alert("Error al cargar los asociados: " + error.message);
    }
}
async function eliminarAsociado(asociado) {
    const confirmar = window.confirm(
        `¿Desea eliminar al asociado "${asociado.nombres} ${asociado.apellidos}"?`
    );

    if (!confirmar) {
        return;
    }

    try {
        await solicitarApi("DELETE", { idAsociado: asociado.idAsociado });
        await cargarAsociados();
        alert("Asociado eliminado correctamente.");
    } catch (error) {
        alert(error.message);
    }
}
function abrirEdicion(asociado) {
    formularioAsociado.reset();

    document.getElementById("idAsociado").value = asociado.documento;
    document.getElementById("documento").value = asociado.documento;
    document.getElementById("nombreAsociado").value = asociado.nombres;
    document.getElementById("apellidoAsociado").value = asociado.apellidos;
    document.getElementById("fechaNacimiento").value = asociado.fechaNacimiento;
    document.getElementById("direccionResidencia").value = asociado.direccionResidencia;
    document.getElementById("ciudad").value = asociado.ciudad;
    document.getElementById("telefono").value = asociado.telefono;
    document.getElementById("email").value = asociado.email;
    document.getElementById("rol").value = asociado.rol;

    const genero = document.querySelector(
        `input[name="genero"][value="${CSS.escape(asociado.genero)}"]`
    );
    if (genero) {
        genero.checked = true;
    }

    document.getElementById("tituloModalAsociado").textContent = "Editar asociado";
    document.getElementById("guardarAsociado").textContent = "Guardar cambios";
    modalAsociado.show();
}

formularioAsociado.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    const documentoAnterior = document.getElementById("idAsociado").value;
    if (!documentoAnterior) {
        return;
    }

    const genero = formularioAsociado.querySelector('input[name="genero"]:checked');
    const datos = {
        documentoAnterior: documentoAnterior,
        documento: document.getElementById("documento").value.trim(),
        nombres: document.getElementById("nombreAsociado").value.trim(),
        apellidos: document.getElementById("apellidoAsociado").value.trim(),
        fechaNacimiento: document.getElementById("fechaNacimiento").value,
        genero: genero ? genero.value : "",
        direccionResidencia: document.getElementById("direccionResidencia").value.trim(),
        ciudad: document.getElementById("ciudad").value.trim(),
        telefono: document.getElementById("telefono").value.trim(),
        email: document.getElementById("email").value.trim(),
        rol: document.getElementById("rol").value
    };

    try {
        await solicitarApi("PUT", datos);
        modalAsociado.hide();
        await cargarAsociados();
        alert("Asociado actualizado correctamente");
    } catch (error) {
        alert(error.message);
    }
});

cargarAsociados();