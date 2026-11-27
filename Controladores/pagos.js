const URL_PAGOS = "../Modelos/gestionarPagos.php";

const selectPrestamo = document.getElementById("selectPrestamo");
const formPago = document.getElementById("formPago");
const valorCuotaInput = document.getElementById("valorCuota");
const fechaPagoInput = document.getElementById("fechaPago");
const tablaPrestamos = document.getElementById("tablaPrestamos");
const tablaPagos = document.getElementById("tablaPagos");
const panelFormularioPago = document.getElementById("panelFormularioPago");

const resMonto = document.getElementById("resMonto");
const resPagado = document.getElementById("resPagado");
const resSaldo = document.getElementById("resSaldo");
const resCuota = document.getElementById("resCuota");
const resNumeroCuota = document.getElementById("resNumeroCuota");

let prestamosData = [];
let pagosData = [];

function formatoMoneda(valor) {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 2
  }).format(Number(valor || 0));
}

function actualizarResumen() {
  const idSel = Number(selectPrestamo.value);
  const prestamo = prestamosData.find((p) => Number(p.idPrestamo) === idSel);

  if (!prestamo) {
    resMonto.textContent = formatoMoneda(0);
    resPagado.textContent = formatoMoneda(0);
    resSaldo.textContent = formatoMoneda(0);
    resCuota.textContent = formatoMoneda(0);
    resNumeroCuota.textContent = "-";
    valorCuotaInput.value = "";
    return;
  }

  resMonto.textContent = formatoMoneda(prestamo.montoPrestamo);
  resPagado.textContent = formatoMoneda(prestamo.totalPagado);
  resSaldo.textContent = formatoMoneda(prestamo.saldoPendiente);
  resCuota.textContent = formatoMoneda(prestamo.valorCuotaEsperada);
  resNumeroCuota.textContent = prestamo.cuotasPagadas + 1;
  valorCuotaInput.value = prestamo.valorCuotaEsperada > 0
    ? Number(prestamo.valorCuotaEsperada).toFixed(2)
    : "";
  valorCuotaInput.max = prestamo.valorCuotaEsperada;
}

function renderPrestamos() {
  const valorAnterior = selectPrestamo.value;
  const prestamosDisponibles = prestamosData.filter(
    (prestamo) => prestamo.estado === "Aprobado" && prestamo.saldoPendiente > 0
  );
  selectPrestamo.innerHTML = `<option value="">${
    prestamosDisponibles.length ? "Seleccione un préstamo..." : "No hay préstamos aprobados con saldo pendiente"
  }</option>`;

  prestamosData.forEach((p) => {
    const opt = document.createElement("option");
    opt.value = p.idPrestamo;
    const puedePagar = p.estado === "Aprobado" && p.valorCuotaEsperada > 0;
    opt.textContent = `#${p.idPrestamo} - ${p.nombreAsociado} (${p.estado}, saldo: ${formatoMoneda(p.saldoPendiente)})`;
    opt.disabled = !puedePagar;
    selectPrestamo.appendChild(opt);
  });

  if (valorAnterior && prestamosDisponibles.some((p) => String(p.idPrestamo) === String(valorAnterior))) {
    selectPrestamo.value = valorAnterior;
  } else {
    selectPrestamo.value = "";
  }
  selectPrestamo.disabled = prestamosDisponibles.length === 0;
  formPago.querySelector('button[type="submit"]').disabled = prestamosDisponibles.length === 0;
  actualizarResumen();
}

function renderTablaPrestamos() {
  tablaPrestamos.replaceChildren();

  if (prestamosData.length === 0) {
    const fila = document.createElement("tr");
    const celda = document.createElement("td");
    celda.colSpan = 8;
    celda.className = "text-center text-muted py-3";
    celda.textContent = "No hay préstamos registrados.";
    fila.append(celda);
    tablaPrestamos.append(fila);
    return;
  }

  prestamosData.forEach((prestamo) => {
    const fila = document.createElement("tr");
    [
      `#${prestamo.idPrestamo}`,
      prestamo.nombreAsociado,
      formatoMoneda(prestamo.montoPrestamo),
      prestamo.numeroCuotas,
      prestamo.fechaPrestamo,
      prestamo.estado,
      formatoMoneda(prestamo.totalPagado),
      formatoMoneda(prestamo.saldoPendiente)
    ].forEach((valor) => {
      const celda = document.createElement("td");
      celda.textContent = valor ?? "";
      fila.append(celda);
    });
    tablaPrestamos.append(fila);
  });
}

function renderTablaPagos() {
  tablaPagos.replaceChildren();

  if (pagosData.length === 0) {
    const fila = document.createElement("tr");
    const celda = document.createElement("td");
    celda.colSpan = 5;
    celda.className = "text-center text-muted py-3";
    celda.textContent = "No hay pagos registrados.";
    fila.append(celda);
    tablaPagos.append(fila);
    return;
  }

  pagosData.forEach((pago) => {
    const fila = document.createElement("tr");
    [
      `#${pago.idPago}`,
      `#${pago.idPrestamo}`,
      pago.nombreAsociado,
      formatoMoneda(pago.valorCuota),
      pago.fechaPago
    ].forEach((valor) => {
      const celda = document.createElement("td");
      celda.textContent = valor ?? "";
      fila.append(celda);
    });
    tablaPagos.append(fila);
  });
}

async function cargarDatosPagos() {
  try {
    const res = await fetch(URL_PAGOS);
    const data = await res.json();

    if (res.status === 401) {
      window.location.href = "Login.html";
      return;
    }
    if (!res.ok || !data.success) {
      throw new Error(data.message || "No fue posible cargar los préstamos.");
    }

    prestamosData = data.prestamos || [];
    pagosData = data.pagos || [];

    const esAdmin = data.rol === "admin";
    panelFormularioPago.classList.toggle("d-none", esAdmin);
    document.getElementById("tituloPagos").textContent =
      esAdmin ? "Préstamos y abonos de asociados" : "Pagar mis cuotas";
    document.getElementById("tituloPrestamosPago").textContent =
      esAdmin ? "Préstamos y saldos de asociados" : "Mis préstamos y saldos";

    renderTablaPrestamos();
    renderTablaPagos();
    renderPrestamos();
  } catch (error) {
    const fila = document.createElement("tr");
    const celda = document.createElement("td");
    celda.colSpan = 8;
    celda.className = "text-center text-danger py-3";
    celda.textContent = `No se pudieron cargar los préstamos: ${error.message}`;
    fila.append(celda);
    tablaPrestamos.replaceChildren(fila);
  }
}

selectPrestamo?.addEventListener("change", actualizarResumen);

formPago?.addEventListener("submit", async (e) => {
  e.preventDefault();

  const payload = {
    idPrestamo: Number(selectPrestamo.value),
    valorCuota: Number(valorCuotaInput.value),
    fechaPago: fechaPagoInput.value
  };

  const res = await fetch(URL_PAGOS, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  });

  const data = await res.json();
  if (!res.ok || !data.success) {
    alert(data.message || "Error al registrar el pago");
    return;
  }

  alert(data.message || "Pago registrado");
  valorCuotaInput.value = "";
  await cargarDatosPagos();
});

document.addEventListener("DOMContentLoaded", () => {
  if (fechaPagoInput && !fechaPagoInput.value) {
    const hoy = new Date();
    const mes = String(hoy.getMonth() + 1).padStart(2, "0");
    const dia = String(hoy.getDate()).padStart(2, "0");
    fechaPagoInput.value = `${hoy.getFullYear()}-${mes}-${dia}`;
  }
  cargarDatosPagos();
});