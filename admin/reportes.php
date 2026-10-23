
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function fechaValida($fecha) {
    if ($fecha === "") {
        return true;
    }

    $d = DateTimeImmutable::createFromFormat("!Y-m-d", $fecha);

    return $d !== false && $d->format("Y-m-d") === $fecha;
}

function formatoHoras($segundos) {
    $minutos = intdiv(max(0, (int)$segundos), 60);

    return sprintf(
        "%02d:%02d",
        intdiv($minutos, 60),
        $minutos % 60
    );
}

function celdaSegura($valor) {
    $valor = (string)$valor;

    if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $valor)) {
        return "'" . $valor;
    }

    return $valor;
}

/*
 * Referencias administrativas.
 * No representan automáticamente horas extraordinarias.
 */
$horasHabituales = 8;
$horasRevision = 12;

$segundosHabituales = $horasHabituales * 3600;
$segundosRevision = $horasRevision * 3600;

$buscar = trim((string)($_GET["buscar"] ?? ""));
$desde = trim((string)($_GET["desde"] ?? ""));
$hasta = trim((string)($_GET["hasta"] ?? ""));

$error = "";

if (!fechaValida($desde) || !fechaValida($hasta)) {
    $error = "Las fechas ingresadas no son válidas.";
} elseif (
    $desde !== "" &&
    $hasta !== "" &&
    $desde > $hasta
) {
    $error = "La fecha inicial no puede superar la fecha final.";
} elseif (mb_strlen($buscar) > 150) {
    $error = "El texto de búsqueda es demasiado largo.";
}

/*
 * Condiciones de búsqueda.
 */
$condiciones = [
    "a.hora_salida IS NOT NULL"
];

$valores = [];
$tipos = "";

if ($buscar !== "") {
    $condiciones[] = "(e.nombre LIKE ? OR e.cedula LIKE ?)";

    $termino = "%" . $buscar . "%";

    $valores[] = $termino;
    $valores[] = $termino;

    $tipos .= "ss";
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

/*
 * Primero calculamos la duración de cada jornada.
 * Después agrupamos los resultados por empleado.
 */
$sql = "
    SELECT
        r.id,
        r.cedula,
        r.nombre,

        COUNT(*) AS jornadas,

        COALESCE(
            SUM(GREATEST(0, r.duracion)),
            0
        ) AS segundos_trabajados,

        SUM(
            CASE
                WHEN r.duracion >= 0
                     AND r.duracion <= ?
                THEN 1
                ELSE 0
            END
        ) AS jornadas_habituales,

        SUM(
            CASE
                WHEN r.duracion > ?
                     AND r.duracion <= ?
                THEN 1
                ELSE 0
            END
        ) AS jornadas_adicionales,

        SUM(
            CASE
                WHEN r.duracion > ?
                     OR r.duracion < 0
                THEN 1
                ELSE 0
            END
        ) AS jornadas_revision,

        MIN(r.fecha) AS primera_fecha,
        MAX(r.fecha) AS ultima_fecha

    FROM (
        SELECT
            e.id,
            e.cedula,
            e.nombre,
            a.fecha,

            TIME_TO_SEC(
                TIMEDIFF(
                    a.hora_salida,
                    a.hora_entrada
                )
            ) AS duracion

        FROM asistencias a

        INNER JOIN empleados e
            ON e.id = a.empleado_id

        WHERE " . implode(" AND ", $condiciones) . "
    ) AS r

    GROUP BY
        r.id,
        r.cedula,
        r.nombre

    ORDER BY
        jornadas_revision DESC,
        jornadas_adicionales DESC,
        segundos_trabajados DESC,
        r.nombre ASC
";

$reportes = [];

if ($error === "") {
    $stmt = $conn->prepare($sql);

    /*
     * Los primeros cuatro parámetros son
     * los límites utilizados en la clasificación.
     */
    $tiposConsulta = "iiii" . $tipos;

    $valoresConsulta = array_merge(
        [
            $segundosHabituales,
            $segundosHabituales,
            $segundosRevision,
            $segundosRevision
        ],
        $valores
    );

    $stmt->bind_param(
        $tiposConsulta,
        ...$valoresConsulta
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $reportes[] = $fila;
    }

    $stmt->close();
}

/*
 * Totales del periodo consultado.
 */
$totalEmpleados = count($reportes);

$totalJornadas = 0;
$totalSegundos = 0;

$totalHabituales = 0;
$totalAdicionales = 0;
$totalRevision = 0;

foreach ($reportes as $fila) {
    $totalJornadas += (int)$fila["jornadas"];

    $totalSegundos += (int)$fila["segundos_trabajados"];

    $totalHabituales += (int)$fila["jornadas_habituales"];

    $totalAdicionales += (int)$fila["jornadas_adicionales"];

    $totalRevision += (int)$fila["jornadas_revision"];
}

$totalHoras = formatoHoras($totalSegundos);

/*
 * Exportación CSV compatible con Excel.
 */
if (
    isset($_GET["exportar"]) &&
    $_GET["exportar"] === "1"
) {
    if ($error !== "") {
        http_response_code(400);
        exit($error);
    }

    header("Content-Type: text/csv; charset=UTF-8");

    header(
        'Content-Disposition: attachment; filename="reporte_horas_' .
        date("Y-m-d") . '.csv"'
    );

    header("Cache-Control: no-store");

    $archivo = fopen("php://output", "w");


    $escribir = function($datos) use ($archivo) {
        fputcsv(
            $archivo,
            array_map("celdaSegura", $datos),
            ";",
            '"',
            ""
        );
    };

    $escribir([
        "Cedula",
        "Empleado",
        "Jornadas",
        "Horas registradas",
        "Jornadas habituales",
        "Tiempo adicional",
        "Jornadas para revision",
        "Primera fecha",
        "Ultima fecha"
    ]);

    foreach ($reportes as $fila) {
        $escribir([
            $fila["cedula"],
            $fila["nombre"],
            $fila["jornadas"],
            formatoHoras($fila["segundos_trabajados"]),
            $fila["jornadas_habituales"],
            $fila["jornadas_adicionales"],
            $fila["jornadas_revision"],
            date("d/m/Y", strtotime($fila["primera_fecha"])),
            date("d/m/Y", strtotime($fila["ultima_fecha"]))
        ]);
    }

    $escribir([
        "TOTAL",
        "",
        $totalJornadas,
        $totalHoras,
        $totalHabituales,
        $totalAdicionales,
        $totalRevision,
        "",
        ""
    ]);

    fclose($archivo);
    exit;
}

$parametros = http_build_query([
    "buscar" => $buscar,
    "desde" => $desde,
    "hasta" => $hasta,
    "exportar" => 1
]);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Reportes | PUNTO. Control de Asistencia</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../css/estilos.css">

    <style>
        .reporte-clasificaciones {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 20px;
            margin-bottom: 24px;
        }

        .reporte-clasificacion {
            background: #ffffff;
            border: 1px solid #e9e6e1;
            border-radius: 12px;
            padding: 20px;
        }

        .reporte-clasificacion span {
            display: block;
            color: #777d78;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .reporte-clasificacion strong {
            display: block;
            font-size: 27px;
            color: #303733;
            line-height: 1.2;
        }

        .reporte-clasificacion small {
            display: block;
            color: #888d88;
            margin-top: 7px;
            font-size: 12px;
        }

        .reporte-clasificacion.adicional {
            border-left: 4px solid #d6a45d;
        }

        .reporte-clasificacion.revision {
            border-left: 4px solid #c97949;
        }

        .reporte-etiquetas {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }

        .reporte-etiqueta {
            display: inline-block;
            border-radius: 7px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .reporte-etiqueta.habitual {
            background: #edf6ef;
            color: #287047;
        }

        .reporte-etiqueta.adicional {
            background: #fff5e6;
            color: #93631d;
        }

        .reporte-etiqueta.revision {
            background: #fff0e7;
            color: #a64d23;
        }

        .reporte-alerta {
            margin-bottom: 24px;
            padding: 18px 20px;
            background: #fff8f2;
            border: 1px solid #f0d6c5;
            border-left: 4px solid #c97949;
            border-radius: 9px;
            color: #624535;
        }

        .reporte-alerta strong {
            display: block;
            margin-bottom: 6px;
        }

        .reporte-alerta p {
            margin: 0;
            font-size: 13px;
            line-height: 1.7;
        }

        @media (max-width: 768px) {
            .reporte-clasificaciones {
                grid-template-columns: 1fr;
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
                ADMINISTRACIÓN / REPORTES
            </span>

            <h1>
                Horas trabajadas<span>.</span>
            </h1>

            <p>
                Consulta las jornadas completadas,
                las horas acumuladas y su clasificación
                para revisión administrativa.
            </p>
        </div>

        <a
            href="reportes.php?<?= escapar($parametros) ?>"
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

    <!-- Indicadores principales -->

    <section class="reporte-indicadores">

        <div class="reporte-indicador">
            <span>Empleados con registros</span>

            <strong>
                <?= $totalEmpleados ?>
            </strong>

            <small>Personal con jornadas finalizadas</small>
        </div>

        <div class="reporte-indicador">
            <span>Jornadas completadas</span>

            <strong>
                <?= $totalJornadas ?>
            </strong>

            <small>Registros con entrada y salida</small>
        </div>

        <div class="reporte-indicador reporte-principal">
            <span>Horas acumuladas</span>

            <strong>
                <?= escapar($totalHoras) ?>
            </strong>

            <small>Total del periodo consultado</small>
        </div>

    </section>

    <!-- Clasificación de jornadas -->

    <section class="reporte-clasificaciones">

        <div class="reporte-clasificacion">

            <span>Jornadas habituales</span>

            <strong>
                <?= $totalHabituales ?>
            </strong>

            <small>
                Hasta <?= $horasHabituales ?> horas
            </small>

        </div>

        <div class="reporte-clasificacion adicional">

            <span>Jornadas con tiempo adicional</span>

            <strong>
                <?= $totalAdicionales ?>
            </strong>

            <small>
                Más de <?= $horasHabituales ?>
                y hasta <?= $horasRevision ?> horas
            </small>

        </div>

        <div class="reporte-clasificacion revision">

            <span>Jornadas para revisión</span>

            <strong>
                <?= $totalRevision ?>
            </strong>

            <small>
                Más de <?= $horasRevision ?> horas
                o duración inconsistente
            </small>

        </div>

    </section>

    <?php if ($totalRevision > 0): ?>

        <div class="reporte-alerta">

            <strong>
                ⚠ <?= $totalRevision ?>
                jornada(s) requieren revisión
            </strong>

            <p>
                Se encontraron registros superiores a
                <?= $horasRevision ?> horas o con horarios
                inconsistentes. Consulta el historial
                para verificar los datos y agregar
                observaciones cuando corresponda.
            </p>

            <a
                href="asistencias.php"
                class="btn-limpiar mt-3 d-inline-block"
            >
                Consultar asistencias →
            </a>

        </div>

    <?php endif; ?>

    <!-- Filtros -->

    <section class="panel-bloque">

        <div class="bloque-encabezado">

            <div>
                <span class="seccion-etiqueta">
                    CONSULTA DE PERSONAL
                </span>

                <h2>Filtrar reporte</h2>
            </div>

        </div>

        <form method="GET" class="reporte-filtros">

            <div>
                <label for="buscar" class="form-label">
                    Empleado o cédula
                </label>

                <input
                    type="search"
                    id="buscar"
                    name="buscar"
                    class="form-control"
                    placeholder="Buscar empleado"
                    maxlength="150"
                    value="<?= escapar($buscar) ?>"
                >
            </div>

            <div>
                <label for="desde" class="form-label">
                    Desde
                </label>

                <input
                    type="date"
                    id="desde"
                    name="desde"
                    class="form-control"
                    value="<?= escapar($desde) ?>"
                >
            </div>

            <div>
                <label for="hasta" class="form-label">
                    Hasta
                </label>

                <input
                    type="date"
                    id="hasta"
                    name="hasta"
                    class="form-control"
                    value="<?= escapar($hasta) ?>"
                >
            </div>

            <div class="reporte-acciones">

                <button
                    type="submit"
                    class="btn btn-acceso"
                >
                    Consultar
                </button>

                <a
                    href="reportes.php"
                    class="btn-limpiar"
                >
                    Limpiar
                </a>

            </div>

        </form>

        <!-- Resultados -->

        <div class="reporte-tabla-titulo">

            <div>
                <span class="seccion-etiqueta">
                    RESULTADOS
                </span>

                <h2>Resumen por empleado</h2>
            </div>

            <span class="contador-empleados">
                <?= $totalEmpleados ?> resultado(s)
            </span>

        </div>

        <div class="table-responsive">

            <table class="table tabla-panel">

                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Empleado</th>
                        <th>Jornadas</th>
                        <th>Horas registradas</th>
                        <th>Clasificación</th>
                        <th>Primera fecha</th>
                        <th>Última fecha</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($totalEmpleados > 0): ?>

                    <?php foreach ($reportes as $fila): ?>

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
                                <?= (int)$fila["jornadas"] ?>
                            </td>

                            <td>
                                <strong class="reporte-horas">
                                    <?= formatoHoras(
                                        $fila["segundos_trabajados"]
                                    ) ?>
                                </strong>
                            </td>

                            <td>

                                <div class="reporte-etiquetas">

                                    <?php if (
                                        (int)$fila["jornadas_habituales"] > 0
                                    ): ?>

                                        <span class="reporte-etiqueta habitual">
                                            <?= (int)$fila["jornadas_habituales"] ?>
                                            habitual(es)
                                        </span>

                                    <?php endif; ?>

                                    <?php if (
                                        (int)$fila["jornadas_adicionales"] > 0
                                    ): ?>

                                        <span class="reporte-etiqueta adicional">
                                            <?= (int)$fila["jornadas_adicionales"] ?>
                                            con tiempo adicional
                                        </span>

                                    <?php endif; ?>

                                    <?php if (
                                        (int)$fila["jornadas_revision"] > 0
                                    ): ?>

                                        <span class="reporte-etiqueta revision">
                                            ⚠
                                            <?= (int)$fila["jornadas_revision"] ?>
                                            para revisión
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>

                            <td>
                                <?= date(
                                    "d/m/Y",
                                    strtotime($fila["primera_fecha"])
                                ) ?>
                            </td>

                            <td>
                                <?= date(
                                    "d/m/Y",
                                    strtotime($fila["ultima_fecha"])
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="tabla-vacia">
                            No hay jornadas completadas
                            para los filtros seleccionados.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

                <?php if ($totalEmpleados > 0): ?>

                    <tfoot>

                        <tr class="reporte-total">

                            <td colspan="2">
                                <strong>TOTAL GENERAL</strong>
                            </td>

                            <td>
                                <strong>
                                    <?= $totalJornadas ?>
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    <?= escapar($totalHoras) ?>
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    <?= $totalHabituales ?> habituales ·
                                    <?= $totalAdicionales ?> adicionales ·
                                    <?= $totalRevision ?> revisión
                                </strong>
                            </td>

                            <td colspan="2"></td>

                        </tr>

                    </tfoot>

                <?php endif; ?>

            </table>

        </div>

        <p class="reporte-nota">
            Las horas representan el tiempo transcurrido
            entre la entrada y la salida, sin descontar
            descansos. Solo se incluyen jornadas finalizadas.
            La clasificación utiliza referencias de
            <?= $horasHabituales ?> y <?= $horasRevision ?> horas,
            y no determina automáticamente horas extraordinarias
            ni incumplimientos laborales.
        </p>

    </section>

    <footer class="panel-footer">
        <span>PUNTO. / Control de Asistencia</span>

        <span>
            <?= date("Y") ?> · Reportes administrativos
        </span>
    </footer>

</main>

</body>
</html>
