
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function contar($conn, $sql) {
    $resultado = $conn->query($sql);
    return (int)$resultado->fetch_row()[0];
}

function horaBonita($hora) {
    return $hora === null
        ? "—"
        : date("h:i A", strtotime($hora));
}

$totalEmpleados = contar($conn,
    "SELECT COUNT(*) FROM empleados");

$empleadosActivos = contar($conn,
    "SELECT COUNT(*) FROM empleados WHERE estado = 'Activo'");

$ingresaronHoy = contar($conn,
    "SELECT COUNT(DISTINCT empleado_id)
     FROM asistencias WHERE fecha = CURDATE()");

$actualmenteDentro = contar($conn,
    "SELECT COUNT(*) FROM asistencias
     WHERE fecha = CURDATE()
     AND hora_salida IS NULL");

$registrosHoy = contar($conn,
    "SELECT COUNT(*) FROM asistencias
     WHERE fecha = CURDATE()");

$salidasHoy = contar($conn,
    "SELECT COUNT(*) FROM asistencias
     WHERE fecha = CURDATE()
     AND hora_salida IS NOT NULL");

$ultimos = $conn->query(
    "SELECT a.id, e.nombre, e.cedula,
            a.fecha, a.hora_entrada, a.hora_salida
     FROM asistencias a
     INNER JOIN empleados e ON e.id = a.empleado_id
     ORDER BY a.fecha DESC, a.hora_entrada DESC, a.id DESC
     LIMIT 6"
);

$dentro = $conn->query(
    "SELECT e.nombre, e.cedula, a.hora_entrada
     FROM asistencias a
     INNER JOIN empleados e ON e.id = a.empleado_id
     WHERE a.fecha = CURDATE()
     AND a.hora_salida IS NULL
     ORDER BY a.hora_entrada DESC
     LIMIT 6"
);

$nombreAdmin = $_SESSION["admin_nombre"] ?? "Administrador";
$inicialAdmin = mb_strtoupper(
    mb_substr($nombreAdmin, 0, 1, "UTF-8"),
    "UTF-8"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Panel administrativo | PUNTO. Control de Asistencia</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="../css/estilos.css">
</head>

<body class="panel-body">

<?php require __DIR__ . "/includes/header.php"; ?>

<main class="panel-main container-fluid">

    <div class="panel-titulo">
        <div>
            <span class="seccion-etiqueta">
                PANEL DE CONTROL / VISTA GENERAL
            </span>

            <h1>Resumen de asistencia<span>.</span></h1>

            <p>
                Información actualizada del personal y
                sus movimientos durante la jornada.
            </p>
        </div>

        <div class="panel-fecha">
            <span class="fecha-indicador"></span>
            <?= date("d/m/Y") ?>
        </div>
    </div>

    <section class="indicadores">

        <div class="indicador">
            <span class="indicador-nombre">
                Total de empleados
            </span>
            <strong><?= $totalEmpleados ?></strong>
            <span class="indicador-pie">
                Personal registrado
            </span>
        </div>

        <div class="indicador">
            <span class="indicador-nombre">
                Empleados activos
            </span>
            <strong><?= $empleadosActivos ?></strong>
            <span class="indicador-pie">
                Habilitados para marcar
            </span>
        </div>

        <div class="indicador">
            <span class="indicador-nombre">
                Ingresaron hoy
            </span>
            <strong><?= $ingresaronHoy ?></strong>
            <span class="indicador-pie">
                Entradas registradas
            </span>
        </div>

        <div class="indicador indicador-destacado">
            <span class="indicador-nombre">
                Actualmente dentro
            </span>
            <strong><?= $actualmenteDentro ?></strong>
            <span class="indicador-pie">
                Jornadas abiertas de hoy
            </span>
        </div>

    </section>

    <section class="resumen-jornada">
        <div>
            <span class="seccion-etiqueta">
                ACTIVIDAD DEL DÍA
            </span>
            <h2>Movimientos registrados</h2>
            <p>
                Consulta las entradas y salidas
                realizadas durante el día.
            </p>
        </div>

        <div class="movimientos-resumen">
            <div>
                <strong><?= $registrosHoy ?></strong>
                <span>Entradas</span>
            </div>

            <div>
                <strong><?= $salidasHoy ?></strong>
                <span>Salidas</span>
            </div>

            <div>
                <strong><?= $registrosHoy + $salidasHoy ?></strong>
                <span>Marcaciones</span>
            </div>
        </div>
    </section>

    <section class="accesos-rapidos">
        <div class="accesos-encabezado">
            <span class="seccion-etiqueta">GESTIÓN DEL SISTEMA</span>
            <h2>Accesos rápidos</h2>
        </div>

        <div class="accesos-grid">
            <a href="empleados.php" class="acceso-tarjeta">
                <span class="acceso-icono">＋</span>
                <strong>Administrar empleados</strong>
                <small>Consultar y gestionar el personal</small>
                <span class="acceso-flecha">↗</span>
            </a>

            <a href="asistencias.php" class="acceso-tarjeta">
                <span class="acceso-icono">◷</span>
                <strong>Historial de asistencia</strong>
                <small>Consultar jornadas y horas</small>
                <span class="acceso-flecha">↗</span>
            </a>

            <a href="../index.php" target="_blank"
               rel="noopener" class="acceso-tarjeta">
                <span class="acceso-icono">✓</span>
                <strong>Registrar marcación</strong>
                <small>Acceder al registro público</small>
                <span class="acceso-flecha">↗</span>
            </a>
        </div>
    </section>

    <div class="row g-4 panel-secciones">

        <div class="col-lg-8">
            <section class="panel-bloque">

                <div class="bloque-encabezado">
                    <div>
                        <span class="seccion-etiqueta">
                            SEGUIMIENTO
                        </span>
                        <h2>Últimos registros</h2>
                    </div>

                    <a href="asistencias.php" class="enlace-accion">
                        Ver historial →
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table tabla-panel">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Fecha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php if ($ultimos->num_rows > 0): ?>
                            <?php while ($fila = $ultimos->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= escapar($fila["nombre"]) ?>
                                        </strong>
                                        <small>
                                            <?= escapar($fila["cedula"]) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= date("d/m/Y", strtotime($fila["fecha"])) ?>
                                    </td>

                                    <td>
                                        <?= horaBonita($fila["hora_entrada"]) ?>
                                    </td>

                                    <td>
                                        <?= horaBonita($fila["hora_salida"]) ?>
                                    </td>

                                    <td>
                                        <?php if ($fila["hora_salida"] === null): ?>
                                            <span class="estado estado-abierto">
                                                En jornada
                                            </span>
                                        <?php else: ?>
                                            <span class="estado estado-cerrado">
                                                Finalizada
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="tabla-vacia">
                                    Todavía no hay registros de asistencia.
                                </td>
                            </tr>
                        <?php endif; ?>

                        </tbody>
                    </table>
                </div>

            </section>
        </div>

        <div class="col-lg-4">
            <section class="panel-bloque">

                <div class="bloque-encabezado">
                    <div>
                        <span class="seccion-etiqueta">
                            PERSONAL PRESENTE
                        </span>
                        <h2>Actualmente dentro</h2>
                    </div>
                </div>

                <div class="personal-lista">

                    <?php if ($dentro->num_rows > 0): ?>

                        <?php while ($persona = $dentro->fetch_assoc()): ?>
                            <div class="personal-item">

                                <div class="personal-avatar">
                                    <?= escapar(mb_strtoupper(
                                        mb_substr(
                                            $persona["nombre"],
                                            0,
                                            1,
                                            "UTF-8"
                                        ),
                                        "UTF-8"
                                    )) ?>
                                </div>

                                <div class="personal-info">
                                    <strong>
                                        <?= escapar($persona["nombre"]) ?>
                                    </strong>
                                    <small>
                                        Entrada:
                                        <?= horaBonita($persona["hora_entrada"]) ?>
                                    </small>
                                </div>

                                <span class="personal-punto"></span>
                            </div>
                        <?php endwhile; ?>

                    <?php else: ?>

                        <div class="personal-vacio">
                            <div class="vacio-icono">◷</div>
                            <strong>Sin personal dentro</strong>
                            <p>
                                No hay jornadas abiertas
                                registradas para hoy.
                            </p>
                        </div>

                    <?php endif; ?>

                </div>
            </section>
        </div>

    </div>

    <footer class="panel-footer">
        <span>PUNTO. / Control de Asistencia</span>
        <span><?= date("Y") ?> · Panel administrativo</span>
    </footer>

</main>

</body>
</html>
