
<?php
$paginaActual = basename($_SERVER["SCRIPT_NAME"]);
$nombreAdmin = $_SESSION["admin_nombre"] ?? "Administrador";

$inicialAdmin = mb_strtoupper(
    mb_substr($nombreAdmin, 0, 1, "UTF-8"),
    "UTF-8"
);
?>

<header class="panel-header">
    <div class="container-fluid panel-header-inner">

        <a href="index.php" class="panel-logo">
            <span class="logo-simbolo">◷</span>
            <span class="marca-texto">
                <strong>PUNTO<span class="logo-acento">.</span></strong>
                <small>CONTROL DE ASISTENCIA</small>
            </span>
        </a>

        <nav class="panel-nav" aria-label="Navegación administrativa">

            <a href="index.php"
               class="<?= $paginaActual === 'index.php' ? 'activo' : '' ?>">
                Resumen
            </a>

            <a href="empleados.php"
               class="<?= in_array(
                   $paginaActual,
                   [
                       'empleados.php',
                       'nuevo_empleado.php',
                       'editar_empleado.php'
                   ],
                   true
               ) ? 'activo' : '' ?>">
                Empleados
            </a>

            <a href="asistencias.php"
               class="<?= $paginaActual === 'asistencias.php' ? 'activo' : '' ?>">
                Asistencias
            </a>

            <a href="reportes.php"
               class="<?= $paginaActual === 'reportes.php' ? 'activo' : '' ?>">
                Reportes
            </a>

            <a href="../index.php"
               target="_blank"
               rel="noopener">
                Marcación ↗
            </a>

        </nav>

        <div class="panel-usuario">

            <div class="usuario-avatar">
                <?= htmlspecialchars(
                    $inicialAdmin,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

            <span>
                <?= htmlspecialchars(
                    $nombreAdmin,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </span>

            <a href="../logout.php" class="salir-link">
                Salir
            </a>

        </div>

    </div>
</header>
