const formularioMisDatos = document.getElementById("formMisDatos");
const mensajePerfil = document.getElementById("mensajePerfil");

function mostrarMensajePerfil(texto, tipo) {
    mensajePerfil.textContent = texto;
    mensajePerfil.className = `alert alert-${tipo}`;
}

async function solicitarPerfil(metodo, datos = null) {
    const opciones = {
        method: metodo,
        headers: { "Content-Type": "application/json" }
    };

    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../Modelos/gestionarMisDatos.php", opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = "Login.html";
        return null;
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || "No fue posible consultar tus datos.");
    }

    return resultado;
}

function cargarFormulario(datos) {
    Object.entries(datos).forEach(([campo, valor]) => {
        const entrada = formularioMisDatos.elements.namedItem(campo);
        if (entrada) {
            entrada.value = valor ?? "";
        }
    });
}

async function cargarMisDatos() {
    try {
        const resultado = await solicitarPerfil("GET");
        if (resultado) {
            cargarFormulario(resultado.datos);
        }
    } catch (error) {
        mostrarMensajePerfil(error.message, "danger");
    }
}

formularioMisDatos.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    const boton = document.getElementById("guardarMisDatos");
    boton.disabled = true;
    mensajePerfil.classList.add("d-none");

    try {
        const datos = Object.fromEntries(new FormData(formularioMisDatos).entries());
        const resultado = await solicitarPerfil("PUT", datos);
        if (resultado) {
            cargarFormulario(resultado.datos);
            localStorage.setItem(
                "nombre",
                `${resultado.datos.nombres} ${resultado.datos.apellidos}`
            );
            mostrarMensajePerfil("Tus datos se actualizaron correctamente.", "success");
        }
    } catch (error) {
        mostrarMensajePerfil(error.message, "danger");
    } finally {
        boton.disabled = false;
    }
});

cargarMisDatos();
