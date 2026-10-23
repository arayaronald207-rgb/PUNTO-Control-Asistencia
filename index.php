
<?php
require_once __DIR__ . "/config/conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set("America/Costa_Rica");

if (empty($_SESSION["csrf_marcacion"])) {
    $_SESSION["csrf_marcacion"] = bin2hex(random_bytes(32));
}

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store");

    $respuesta = [
        "tipo" => "error",
        "mensaje" => "",
        "nombre" => "",
        "fecha" => "",
        "hora" => "",
        "csrf_token" => ""
    ];

    $cedula = trim((string)($_POST["cedula"] ?? ""));
    $codigo = strtoupper(trim((string)($_POST["codigo"] ?? "")));
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_marcacion"], $token)
    ) {
        $respuesta["mensaje"] =
            "Solicitud no válida. Actualiza la página.";

    } elseif ($cedula === "" || $codigo === "") {

        $respuesta["mensaje"] =
            "Debes completar ambos campos.";

    } elseif (
        strlen($cedula) > 25 ||
        strlen($codigo) > 20
    ) {

        $respuesta["mensaje"] =
            "Cédula o código de acceso incorrecto.";

    } else {

        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "SELECT id, nombre, estado, codigo_acceso
                 FROM empleados
                 WHERE cedula = ?
                 LIMIT 1 FOR UPDATE"
            );

            $stmt->bind_param("s", $cedula);
            $stmt->execute();

            $empleado = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                !$empleado ||
                !hash_equals(
                    (string)$empleado["codigo_acceso"],
                    $codigo
                )
            ) {

                $respuesta["mensaje"] =
                    "Cédula o código de acceso incorrecto.";

            } elseif ($empleado["estado"] !== "Activo") {

                $respuesta["mensaje"] =
                    "Este empleado se encuentra inactivo.";

            } else {

                $empleadoId = (int)$empleado["id"];

                $hoy = date("Y-m-d");
                $ahora = date("H:i:s");

                $stmt = $conn->prepare(
                    "SELECT id, hora_entrada, hora_salida
                     FROM asistencias
                     WHERE empleado_id = ?
                     AND fecha = ?
                     LIMIT 1 FOR UPDATE"
                );

                $stmt->bind_param("is", $empleadoId, $hoy);
                $stmt->execute();

                $asistencia = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$asistencia) {

                    $stmt = $conn->prepare(
                        "INSERT INTO asistencias
                         (empleado_id, fecha, hora_entrada)
                         VALUES (?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "iss",
                        $empleadoId,
                        $hoy,
                        $ahora
                    );

                    $stmt->execute();
                    $stmt->close();

                    $respuesta["tipo"] = "entrada";
                    $respuesta["mensaje"] =
                        "Entrada registrada correctamente.";

                } elseif ($asistencia["hora_salida"] === null) {

                    $stmt = $conn->prepare(
                        "UPDATE asistencias
                         SET hora_salida = ?
                         WHERE id = ?
                         AND hora_salida IS NULL"
                    );

                    $stmt->bind_param(
                        "si",
                        $ahora,
                        $asistencia["id"]
                    );

                    $stmt->execute();
                    $stmt->close();

                    $respuesta["tipo"] = "salida";
                    $respuesta["mensaje"] =
                        "Salida registrada correctamente.";

                } else {

                    $respuesta["mensaje"] =
                        "Ya completaste tu jornada de hoy.";
                }

                if (
                    $respuesta["tipo"] === "entrada" ||
                    $respuesta["tipo"] === "salida"
                ) {
                    $respuesta["nombre"] = $empleado["nombre"];
                    $respuesta["fecha"] = date("d/m/Y");
                    $respuesta["hora"] = date(
                        "h:i A",
                        strtotime($ahora)
                    );
                }
            }

            $conn->commit();

        } catch (Throwable $e) {

            $conn->rollback();
            error_log($e->getMessage());

            $respuesta["tipo"] = "error";
            $respuesta["mensaje"] =
                "No se pudo registrar la asistencia. Intenta nuevamente.";
            $respuesta["nombre"] = "";
        }
    }

    $_SESSION["csrf_marcacion"] = bin2hex(random_bytes(32));
    $respuesta["csrf_token"] = $_SESSION["csrf_marcacion"];

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE
    );
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>PUNTO. | Control de Asistencia</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="css/estilos.css">
</head>

<body class="marcacion-body">

<header class="marcacion-header">

    <a href="index.php" class="panel-logo">
        <span class="logo-simbolo">◷</span>

        <span class="marca-texto">
            <strong>
                PUNTO<span class="logo-acento">.</span>
            </strong>
            <small>CONTROL DE ASISTENCIA</small>
        </span>
    </a>

    <a href="login.php" class="marcacion-admin">
        Acceso administrativo →
    </a>

</header>

<main class="marcacion-main">

    <div class="marcacion-intro">

        <span class="seccion-etiqueta">
            CONTROL DE ASISTENCIA
        </span>

        <h1>
            Registro de jornada<span>.</span>
        </h1>

        <p>
            Ingresa tus credenciales para registrar
            tu entrada o salida de la empresa.
        </p>

    </div>

    <section class="marcacion-card">

        <div class="marcacion-card-header">

            <div>
                <span class="seccion-etiqueta">
                    MARCACIÓN DE PERSONAL
                </span>

                <h2>Identificación</h2>
            </div>

            <div class="marcacion-reloj">
                <span id="reloj">--:--:--</span>
                <small id="fecha-actual">
                    <?= date("d/m/Y") ?>
                </small>
            </div>

        </div>

        <div id="resultado"
             class="resultado-marcacion"
             role="status"
             aria-live="polite"
             hidden>
        </div>

        <form id="formMarcacion"
              method="POST"
              autocomplete="off">

            <input
                type="hidden"
                name="csrf_token"
                id="csrf_token"
                value="<?= escapar($_SESSION["csrf_marcacion"]) ?>">

            <div class="mb-4">

                <label for="cedula" class="form-label">
                    Número de cédula
                </label>

                <input
                    type="text"
                    id="cedula"
                    name="cedula"
                    class="form-control"
                    placeholder="Ingresa tu cédula"
                    maxlength="25"
                    required
                    autofocus>

            </div>

            <div class="mb-4">

                <label for="codigo" class="form-label">
                    Código de acceso
                </label>

                <input
                    type="password"
                    id="codigo"
                    name="codigo"
                    class="form-control"
                    placeholder="Código de seis caracteres"
                    maxlength="20"
                    required>

            </div>

            <button
                type="submit"
                id="btnRegistrar"
                class="btn btn-acceso w-100">

                <span id="textoBoton">
                    Registrar asistencia
                </span>

                <span id="flechaBoton">→</span>

            </button>

        </form>

        <div class="marcacion-ayuda">
            El sistema identificará automáticamente
            si corresponde una entrada o una salida.
        </div>

    </section>

    <div class="marcacion-footer">
        PUNTO. · Control de Asistencia
    </div>

</main>

<script>
const formulario = document.getElementById("formMarcacion");
const resultado = document.getElementById("resultado");
const boton = document.getElementById("btnRegistrar");
const textoBoton = document.getElementById("textoBoton");
const flechaBoton = document.getElementById("flechaBoton");
const campoCedula = document.getElementById("cedula");
const campoCodigo = document.getElementById("codigo");
const campoToken = document.getElementById("csrf_token");

function actualizarReloj() {
    const ahora = new Date();

    document.getElementById("reloj").textContent =
        ahora.toLocaleTimeString("es-CR", {
            hour12: false
        });

    document.getElementById("fecha-actual").textContent =
        ahora.toLocaleDateString("es-CR");
}

actualizarReloj();
setInterval(actualizarReloj, 1000);

function agregarDetalle(contenedor, etiqueta, valor) {
    const parrafo = document.createElement("p");
    const destacado = document.createElement("b");

    parrafo.appendChild(
        document.createTextNode(etiqueta + ": ")
    );

    destacado.textContent = valor;
    parrafo.appendChild(destacado);

    contenedor.appendChild(parrafo);
}

function mostrarResultado(datos) {
    resultado.replaceChildren();
    resultado.hidden = false;

    const tipo = ["entrada", "salida"].includes(datos.tipo)
        ? datos.tipo
        : "error";

    resultado.className = "resultado-marcacion " + tipo;

    const titulo = document.createElement("strong");
    titulo.textContent = datos.mensaje;
    resultado.appendChild(titulo);

    if (tipo === "entrada" || tipo === "salida") {
        agregarDetalle(resultado, "Empleado", datos.nombre);
        agregarDetalle(resultado, "Fecha", datos.fecha);

        agregarDetalle(
            resultado,
            tipo === "entrada" ? "Hora de entrada" : "Hora de salida",
            datos.hora
        );
    }
}

formulario.addEventListener("submit", async function(evento) {
    evento.preventDefault();

    if (!formulario.reportValidity()) {
        return;
    }

    boton.disabled = true;
    textoBoton.textContent = "Registrando...";
    flechaBoton.textContent = "◷";

    resultado.hidden = true;

    try {
        const datosFormulario = new FormData(formulario);

        const respuesta = await fetch("index.php", {
            method: "POST",
            body: datosFormulario,
            credentials: "same-origin",
            cache: "no-store"
        });

        if (!respuesta.ok) {
            throw new Error("Error del servidor");
        }

        const datos = await respuesta.json();

        if (datos.csrf_token) {
            campoToken.value = datos.csrf_token;
        }

        mostrarResultado(datos);

        if (datos.tipo === "entrada" || datos.tipo === "salida") {
            campoCedula.value = "";
            campoCodigo.value = "";
            campoCedula.focus();
        }

    } catch (error) {
        mostrarResultado({
            tipo: "error",
            mensaje: "No se pudo confirmar la marcación. Consulta el historial antes de volver a intentarlo."
        });
    } finally {
        boton.disabled = false;
        textoBoton.textContent = "Registrar asistencia";
        flechaBoton.textContent = "→";
    }
});
</script>

</body>
</html>

