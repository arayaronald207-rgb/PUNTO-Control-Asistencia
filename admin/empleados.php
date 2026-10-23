
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$buscar = trim($_GET["buscar"] ?? "");

if ($buscar !== "") {

    $termino = "%" . $buscar . "%";

    $stmt = $conn->prepare(
        "SELECT * FROM empleados
         WHERE nombre LIKE ? OR cedula LIKE ?
         ORDER BY id DESC"
    );

    $stmt->bind_param("ss", $termino, $termino);
    $stmt->execute();

    $empleados = $stmt->get_result();

} else {

    $empleados = $conn->query(
        "SELECT * FROM empleados ORDER BY id DESC"
    );
}

$total = $empleados->num_rows;

$mensaje = $_SESSION["mensaje"] ?? "";
$codigoNuevo = $_SESSION["codigo_nuevo"] ?? "";

unset($_SESSION["mensaje"], $_SESSION["codigo_nuevo"]);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Empleados | PUNTO</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../css/estilos.css">
</head>

<body class="panel-body">

<!-- Encabezado compartido -->

<?php require __DIR__ . "/includes/header.php"; ?>

<main class="panel-main container-fluid">

    <!-- Título principal -->

    <div class="panel-titulo">

        <div>
            <span class="seccion-etiqueta">
                ADMINISTRACIÓN / PERSONAL
            </span>

            <h1>
                Empleados<span>.</span>
            </h1>

            <p>
                Consulta y administra las personas registradas
                en la empresa.
            </p>
        </div>

        <a href="nuevo_empleado.php" class="btn btn-acceso">
            + Nuevo empleado
        </a>

    </div>

    <!-- Mensajes -->

    <?php if ($mensaje !== ""): ?>

        <div class="alert alert-success">

            <?= escapar($mensaje) ?>

            <?php if ($codigoNuevo !== ""): ?>

                <div class="mt-2">

                    Código de acceso asignado:

                    <strong class="codigo-generado">
                        <?= escapar($codigoNuevo) ?>
                    </strong>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

    <!-- Directorio de empleados -->

    <section class="panel-bloque">

        <div class="bloque-encabezado">

            <div>
                <span class="seccion-etiqueta">
                    DIRECTORIO
                </span>

                <h2>Personal registrado</h2>
            </div>

            <span class="contador-empleados">
                <?= $total ?> resultado(s)
            </span>

        </div>

        <!-- Buscador -->

        <form method="GET" class="buscador-empleados">

            <input
                type="search"
                name="buscar"
                class="form-control"
                value="<?= escapar($buscar) ?>"
                placeholder="Buscar por nombre o cédula"
            >

            <button type="submit" class="btn btn-acceso">
                Buscar
            </button>

            <?php if ($buscar !== ""): ?>

                <a href="empleados.php" class="btn-limpiar">
                    Limpiar
                </a>

            <?php endif; ?>

        </form>

        <!-- Tabla de empleados -->

        <div class="table-responsive">

            <table class="table tabla-panel">

                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Empleado</th>
                        <th>Teléfono</th>
                        <th>Código</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total > 0): ?>

                    <?php while ($fila = $empleados->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= escapar($fila["cedula"]) ?>
                            </td>

                            <td>

                                <strong>
                                    <?= escapar($fila["nombre"]) ?>
                                </strong>

                                <small>
                                    <?= escapar($fila["correo"]) ?>
                                </small>

                            </td>

                            <td>
                                <?= escapar($fila["telefono"]) ?>
                            </td>

                            <td>

                                <span class="codigo-tabla">
                                    <?= escapar($fila["codigo_acceso"]) ?>
                                </span>

                            </td>

                            <td>

                                <?php if ($fila["estado"] === "Activo"): ?>

                                    <span class="estado estado-abierto">
                                        Activo
                                    </span>

                                <?php else: ?>

                                    <span class="estado estado-cerrado">
                                        Inactivo
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= date(
                                    "d/m/Y",
                                    strtotime($fila["fecha_registro"])
                                ) ?>
                            </td>

                            <td>

                                <div class="acciones-empleado">

                                    <a
                                        href="editar_empleado.php?id=<?= (int)$fila["id"] ?>"
                                        class="accion-editar"
                                    >
                                        Editar
                                    </a>

                                    <form
                                        method="POST"
                                        action="cambiar_estado.php"
                                        onsubmit="return confirm('¿Deseas cambiar el estado de este empleado?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escapar(
                                                $_SESSION["csrf_token"]
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$fila["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="accion-estado"
                                        >
                                            <?= $fila["estado"] === "Activo"
                                                ? "Desactivar"
                                                : "Activar" ?>
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="tabla-vacia">
                            No se encontraron empleados.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>
