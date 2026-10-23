
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION["csrf_observaciones"])) {
    $_SESSION["csrf_observaciones"] = bin2hex(random_bytes(32));
}

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function horaFormato($hora) {
    return $hora === null ? "—" : date("h:i A", strtotime($hora));
}

function fechaValida($valor) {
    if ($valor === "") {
        return true;
    }

    $fecha = DateTimeImmutable::createFromFormat("!Y-m-d", $valor);

    return $fecha !== false && $fecha->format("Y-m-d") === $valor;
}

function duracionSegundos($entrada, $salida) {
    if ($entrada === null || $salida === null) {
        return null;
    }

    return strtotime($salida) - strtotime($entrada);
}

function horasTrabajadas($segundos) {
    if ($segundos === null || $segundos < 0) {
        return "—";
    }

    $minutos = intdiv($segundos, 60);

    return sprintf(
        "%02d:%02d",
        intdiv($minutos, 60),
        $minutos % 60
    );
}

/*
 * Referencias administrativas.
 * No calculan automáticamente horas extraordinarias.
 */
$horasHabituales = 8;
$horasRevision = 12;

$segundosHabituales = $horasHabituales * 3600;
$segundosRevision = $horasRevision * 3600;

$buscar = trim((string)($_GET["buscar"] ?? ""));
$fecha = trim((string)($_GET["fecha"] ?? ""));
$desde = trim((string)($_GET["desde"] ?? ""));
$hasta = trim((string)($_GET["hasta"] ?? ""));

$error = "";

if (
    !fechaValida($fecha) ||
    !fechaValida($desde) ||
    !fechaValida($hasta)
) {
    $error = "Ingresa fechas válidas.";
} elseif (
    $desde !== "" &&
    $hasta !== "" &&
    $desde > $hasta
) {
    $error = "La fecha inicial no puede ser posterior a la final.";
} elseif (mb_strlen($buscar) > 150) {
    $error = "El texto de búsqueda es demasiado largo.";
}

$condiciones = [];
$valores = [];
$tipos = "";

if ($error === "") {
    if ($buscar !== "") {
        $condiciones[] = "(e.nombre LIKE ? OR e.cedula LIKE ?)";

        $termino = "%" . $buscar . "%";

        $valores[] = $termino;
        $valores[] = $termino;
        $tipos .= "ss";
    }

    if ($fecha !== "") {
        $condiciones[] = "a.fecha = ?";
        $valores[] = $fecha;
        $tipos .= "s";
    }

    if ($desde !== "") {
        $condiciones[] = "a.fecha >= ?";
        $valores[] = $desde;
        $tipos .= "s";
    }

    if ($hasta !== "") {
        $condiciones[] = "a.fecha <= ?";
        $valores[] = $hasta;
        $tipos .= "s";
    }
}

$sql = "
    SELECT
        a.id,
        e.nombre,
        e.cedula,
        a.fecha,
        a.hora_entrada,
        a.hora_salida,
        a.observacion
    FROM asistencias a
    INNER JOIN empleados e
        ON e.id = a.empleado_id
";

if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= " ORDER BY a.fecha DESC, a.hora_entrada DESC, a.id DESC";

$registros = [];

$totalMinutos = 0;
$completadas = 0;
$pendientes = 0;

$totalHabituales = 0;
$totalAdicionales = 0;
$totalRevision = 0;

if ($error === "") {
    $stmt = $conn->prepare($sql);

    if ($tipos !== "") {
        $stmt->bind_param($tipos, ...$valores);
    }

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $segundos = duracionSegundos(
            $fila["hora_entrada"],
            $fila["hora_salida"]
        );

        $fila["duracion_segundos"] = $segundos;

        if ($segundos === null) {
            $fila["clasificacion"] = "abierta";
            $pendientes++;
        } else {
            $completadas++;

            if ($segundos < 0) {
                $fila["clasificacion"] = "inconsistente";
                $totalRevision++;
            } elseif ($segundos > $segundosRevision) {
                $fila["clasificacion"] = "revision";
                $totalRevision++;
            } elseif ($segundos > $segundosHabituales) {
                $fila["clasificacion"] = "adicional";
                $totalAdicionales++;
                $totalMinutos += intdiv($segundos, 60);
            } else {
                $fila["clasificacion"] = "habitual";
                $totalHabituales++;
                $totalMinutos += intdiv($segundos, 60);
            }

            if ($segundos > $segundosRevision) {
                $totalMinutos += intdiv($segundos, 60);
            }
        }

        $registros[] = $fila;
    }

    $stmt->close();
}

$total = count($registros);

$horasAcumuladas = sprintf(
    "%02d:%02d",
    intdiv($totalMinutos, 60),
    $totalMinutos % 60
);

$parametros = http_build_query([
    "buscar" => $buscar,
    "fecha" => $fecha,
    "desde" => $desde,
    "hasta" => $hasta
]);

$mensaje = $_GET["mensaje"] ?? "";
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Historial de asistencia | PUNTO.</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../css/estilos.css">

    <style>
        .observacion-acciones {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .btn-observacion {
            border: 1px solid #deded9;
            background: #ffffff;
            color: #343b38;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-observacion:hover {
            background: #f7f0ea;
            border-color: #c97949;
            color: #b76536;
        }

        .btn-observacion-principal {
            background: #f8eee7;
            border-color: #e9c7af;
            color: #b76536;
        }

        .btn-observacion-principal:hover {
            background: #f2dfd1;
            border-color: #c97949;
        }

        .observacion-modal .modal-content {
            border: 1px solid #e9e6e1;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(25, 31, 28, 0.12);
        }

        .observacion-modal .modal-header {
            background: #faf9f7;
            border-bottom: 1px solid #eeeae5;
            padding: 24px;
        }

        .observacion-modal .modal-body {
            padding: 24px;
        }

        .observacion-modal .modal-footer {
            padding: 18px 24px;
            border-top: 1px solid #eeeae5;
        }

        .observacion-modal .modal-title {
            font-weight: 700;
            color: #303733;
        }

        .observacion-detalle {
            color: #777d78;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .observacion-contenido {
            background: #f8f7f5;
            border: 1px solid #e9e6e1;
            border-radius: 10px;
            padding: 20px;
            color: #343b38;
            font-size: 14px;
            line-height: 1.8;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            max-height: 55vh;
            overflow-y: auto;
        }

        .observacion-modal textarea {
            border: 1px solid #deded9;
            border-radius: 9px;
            padding: 14px;
            font-size: 14px;
            resize: vertical;
            overflow-wrap: anywhere;
        }

        .observacion-modal textarea:focus {
            border-color: #c97949;
            box-shadow: 0 0 0 3px rgba(201, 121, 73, 0.12);
        }

        .clasificacion-jornada {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .clasificacion-jornada.habitual {
            background: #edf6ef;
            color: #287047;
        }

        .clasificacion-jornada.adicional {
            background: #fff5e6;
            color: #93631d;
        }

        .clasificacion-jornada.revision {
            background: #fff0e7;
            color: #a64d23;
        }

        .clasificacion-jornada.abierta {
            background: #f0f1f0;
            color: #69716c;
        }

        .clasificacion-resumen {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0 24px;
        }

        .clasificacion-resumen-item {
            background: #ffffff;
            border: 1px solid #e9e6e1;
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 13px;
            color: #59605b;
        }

        .clasificacion-resumen-item strong {
            color: #303733;
            margin-right: 6px;
        }

        .clasificacion-resumen-item.revision {
            border-left: 4px solid #c97949;
        }

        .historial-nota-clasificacion {
            margin-top: 18px;
            font-size: 12px;
            color: #777d78;
            line-height: 1.7;
        }

        @media (max-width: 576px) {
            .observacion-acciones {
                flex-direction: column;
                align-items: stretch;
            }

            .observacion-modal .modal-header,
            .observacion-modal .modal-body,
            .observacion-modal .modal-footer {
                padding: 18px;
            }
        }
    </style>
</head>

<body class="panel-body">

<?php require __DIR__ . "/includes/header.php"; ?>

<main class="panel-main container-fluid">

    <div class="panel-titulo">
        <div>
            <span class="seccion-etiqueta">
                ADMINISTRACIÓN / ASISTENCIA
            </span>

            <h1>
                Historial de asistencia<span>.</span>
            </h1>

            <p>
                Consulta las jornadas, las horas trabajadas,
                su clasificación y las observaciones del personal.
            </p>
        </div>

        <a
            href="exportar_excel.php?<?= escapar($parametros) ?>"
            class="btn btn-acceso"
        >
            Exportar a Excel ↗
        </a>
    </div>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <?= escapar($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje === "guardado"): ?>
        <div class="alert alert-success">
            La observación se guardó correctamente.
        </div>
    <?php elseif ($mensaje === "error"): ?>
        <div class="alert alert-danger">
            No fue posible guardar la observación.
        </div>
    <?php endif; ?>

    <!-- Indicadores principales -->

    <section class="historial-indicadores">
        <div>
            <span>Total de registros</span>
            <strong><?= $total ?></strong>
        </div>

        <div>
            <span>Jornadas completadas</span>
            <strong><?= $completadas ?></strong>
        </div>

        <div>
            <span>Jornadas abiertas</span>
            <strong><?= $pendientes ?></strong>
        </div>

        <div class="historial-destacado">
            <span>Horas acumuladas</span>
            <strong><?= escapar($horasAcumuladas) ?></strong>
        </div>
    </section>

    <!-- Resumen de clasificación -->

    <div class="clasificacion-resumen">
        <div class="clasificacion-resumen-item">
            <strong><?= $totalHabituales ?></strong>
            Habituales
        </div>

        <div class="clasificacion-resumen-item">
            <strong><?= $totalAdicionales ?></strong>
            Con tiempo adicional
        </div>

        <div class="clasificacion-resumen-item revision">
            <strong><?= $totalRevision ?></strong>
            Para revisión
        </div>
    </div>

    <!-- Historial -->

    <section class="panel-bloque">

        <div class="bloque-encabezado">
            <div>
                <span class="seccion-etiqueta">
                    CONSULTA DE REGISTROS
                </span>

                <h2>Buscar asistencias</h2>
            </div>
        </div>

        <form method="GET" class="filtros-asistencia">

            <div class="filtro-empleado">
                <label class="form-label">
                    Empleado o cédula
                </label>

                <input
                    type="search"
                    name="buscar"
                    class="form-control"
                    placeholder="Nombre o identificación"
                    maxlength="150"
                    value="<?= escapar($buscar) ?>"
                >
            </div>

            <div>
                <label class="form-label">
                    Fecha específica
                </label>

                <input
                    type="date"
                    name="fecha"
                    class="form-control"
                    value="<?= escapar($fecha) ?>"
                >
            </div>

            <div>
                <label class="form-label">
                    Desde
                </label>

                <input
                    type="date"
                    name="desde"
                    class="form-control"
                    value="<?= escapar($desde) ?>"
                >
            </div>

            <div>
                <label class="form-label">
                    Hasta
                </label>

                <input
                    type="date"
                    name="hasta"
                    class="form-control"
                    value="<?= escapar($hasta) ?>"
                >
            </div>

            <div class="filtro-acciones">
                <button type="submit" class="btn btn-acceso">
                    Filtrar
                </button>

                <a href="asistencias.php" class="btn-limpiar">
                    Limpiar
                </a>
            </div>

        </form>

        <div class="historial-tabla-titulo">
            <h2>Registros encontrados</h2>

            <span class="contador-empleados">
                <?= $total ?> resultado(s)
            </span>
        </div>

        <div class="table-responsive">

            <table class="table tabla-panel align-middle">

                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Empleado</th>
                        <th>Fecha</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Horas trabajadas</th>
                        <th>Estado</th>
                        <th>Clasificación</th>
                        <th>Observación</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total > 0): ?>

                    <?php foreach ($registros as $fila): ?>

                        <?php
                        $tieneObservacion = trim(
                            (string)($fila["observacion"] ?? "")
                        ) !== "";

                        $clasificacion = $fila["clasificacion"];
                        ?>

                        <tr>

                            <td>
                                <?= escapar($fila["cedula"]) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= escapar($fila["nombre"]) ?>
                                </strong>
                            </td>

                            <td>
                                <?= date(
                                    "d/m/Y",
                                    strtotime($fila["fecha"])
                                ) ?>
                            </td>

                            <td>
                                <?= horaFormato($fila["hora_entrada"]) ?>
                            </td>

                            <td>
                                <?= horaFormato($fila["hora_salida"]) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= horasTrabajadas(
                                        $fila["duracion_segundos"]
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?php if (
                                    $fila["hora_salida"] === null
                                ): ?>

                                    <span class="estado estado-abierto">
                                        En jornada
                                    </span>

                                <?php else: ?>

                                    <span class="estado estado-cerrado">
                                        Finalizada
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td>

                                <?php if (
                                    $clasificacion === "habitual"
                                ): ?>

                                    <span class="clasificacion-jornada habitual">
                                        Habitual
                                    </span>

                                <?php elseif (
                                    $clasificacion === "adicional"
                                ): ?>

                                    <span class="clasificacion-jornada adicional">
                                        Tiempo adicional
                                    </span>

                                <?php elseif (
                                    $clasificacion === "revision"
                                ): ?>

                                    <span class="clasificacion-jornada revision">
                                        ⚠ Revisar jornada
                                    </span>

                                <?php elseif (
                                    $clasificacion === "inconsistente"
                                ): ?>

                                    <span class="clasificacion-jornada revision">
                                        ⚠ Revisar horario
                                    </span>

                                <?php else: ?>

                                    <span class="clasificacion-jornada abierta">
                                        Pendiente
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <div class="observacion-acciones">

                                    <?php if ($tieneObservacion): ?>

                                        <button
                                            type="button"
                                            class="btn-observacion btn-observacion-principal"
                                            data-bs-toggle="modal"
                                            data-bs-target="#verObservacionModal"
                                            data-empleado="<?= escapar($fila["nombre"]) ?>"
                                            data-fecha="<?= escapar($fila["fecha"]) ?>"
                                            data-observacion="<?= escapar($fila["observacion"]) ?>"
                                        >
                                            Ver observación
                                        </button>

                                    <?php endif; ?>

                                    <button
                                        type="button"
                                        class="btn-observacion"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editarObservacionModal"
                                        data-id="<?= (int)$fila["id"] ?>"
                                        data-empleado="<?= escapar($fila["nombre"]) ?>"
                                        data-fecha="<?= escapar($fila["fecha"]) ?>"
                                        data-observacion="<?= escapar($fila["observacion"] ?? "") ?>"
                                    >
                                        <?= $tieneObservacion
                                            ? "Editar"
                                            : "Agregar" ?>
                                    </button>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="tabla-vacia">
                            No hay registros para los filtros seleccionados.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p class="historial-nota-clasificacion">
            Clasificación administrativa: hasta
            <?= $horasHabituales ?> horas, jornada habitual;
            más de <?= $horasHabituales ?> y hasta
            <?= $horasRevision ?> horas, tiempo adicional;
            más de <?= $horasRevision ?> horas o duración
            inconsistente, registro para revisión.
            No se descuentan descansos ni se calculan
            automáticamente horas extraordinarias.
        </p>

    </section>

    <footer class="panel-footer">
        <span>PUNTO. / Control de asistencia</span>
        <span><?= date("Y") ?> · Historial de personal</span>
    </footer>

</main>

<!-- Modal: Ver observación -->

<div
    class="modal fade observacion-modal"
    id="verObservacionModal"
    tabindex="-1"
    aria-labelledby="verObservacionTitulo"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <span class="seccion-etiqueta">
                        DETALLE DE ASISTENCIA
                    </span>

                    <h2
                        class="modal-title fs-5"
                        id="verObservacionTitulo"
                    >
                        Observación registrada
                    </h2>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                ></button>
            </div>

            <div class="modal-body">
                <p
                    class="observacion-detalle"
                    id="verObservacionDetalle"
                ></p>

                <div
                    class="observacion-contenido"
                    id="verObservacionTexto"
                ></div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-limpiar"
                    data-bs-dismiss="modal"
                >
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Modal: Agregar o editar observación -->

<div
    class="modal fade observacion-modal"
    id="editarObservacionModal"
    tabindex="-1"
    aria-labelledby="editarObservacionTitulo"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="guardar_observacion.php">

                <div class="modal-header">
                    <div>
                        <span class="seccion-etiqueta">
                            ADMINISTRACIÓN
                        </span>

                        <h2
                            class="modal-title fs-5"
                            id="editarObservacionTitulo"
                        >
                            Observación de asistencia
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"
                    ></button>
                </div>

                <div class="modal-body">

                    <p
                        class="observacion-detalle"
                        id="editarObservacionDetalle"
                    ></p>

                    <input
                        type="hidden"
                        name="asistencia_id"
                        id="editarObservacionId"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapar(
                            $_SESSION["csrf_observaciones"]
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="retorno"
                        value="<?= escapar($parametros) ?>"
                    >

                    <label
                        for="editarObservacionTexto"
                        class="form-label fw-semibold"
                    >
                        Observación
                    </label>

                    <textarea
                        name="observacion"
                        id="editarObservacionTexto"
                        class="form-control"
                        rows="5"
                        maxlength="500"
                        placeholder="Escribe una observación sobre esta jornada..."
                    ></textarea>

                    <div class="form-text mt-2">
                        Máximo 500 caracteres. Puedes dejar el campo
                        vacío para eliminar una observación existente.
                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn-limpiar"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-acceso"
                    >
                        Guardar observación
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const modalVer = document.getElementById("verObservacionModal");

    if (modalVer) {
        modalVer.addEventListener("show.bs.modal", function (evento) {
            const boton = evento.relatedTarget;

            if (!boton) return;

            const empleado = boton.getAttribute("data-empleado") || "";
            const fecha = boton.getAttribute("data-fecha") || "";
            const observacion = boton.getAttribute("data-observacion") || "";

            document.getElementById("verObservacionDetalle").textContent =
                empleado + " · " + fecha;

            document.getElementById("verObservacionTexto").textContent =
                observacion;
        });
    }

    const modalEditar = document.getElementById("editarObservacionModal");

    if (modalEditar) {
        modalEditar.addEventListener("show.bs.modal", function (evento) {
            const boton = evento.relatedTarget;

            if (!boton) return;

            const id = boton.getAttribute("data-id") || "";
            const empleado = boton.getAttribute("data-empleado") || "";
            const fecha = boton.getAttribute("data-fecha") || "";
            const observacion = boton.getAttribute("data-observacion") || "";

            document.getElementById("editarObservacionId").value = id;

            document.getElementById("editarObservacionTexto").value =
                observacion;

            document.getElementById("editarObservacionDetalle").textContent =
                empleado + " · " + fecha;
        });
    }

});
</script>

</body>
</html>
