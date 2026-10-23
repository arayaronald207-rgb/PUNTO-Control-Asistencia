# PUNTO. — Control de Asistencia

Sistema web para la gestión y el control de asistencia de empleados, desarrollado con PHP y MySQL.

## Tecnologías utilizadas

- PHP
- MySQL
- HTML5
- CSS3
- Bootstrap 5
- JavaScript
- XAMPP

## Funcionalidades

- Inicio y cierre de sesión del administrador.
- Registro y administración de empleados.
- Activación y desactivación de empleados.
- Generación de códigos de acceso para marcación.
- Registro de entrada y salida mediante cédula y código.
- Historial de asistencias con filtros de búsqueda.
- Cálculo de horas registradas.
- Clasificación de jornadas habituales, con tiempo adicional y para revisión.
- Registro de observaciones administrativas.
- Reportes por empleado.
- Exportación de información en formato CSV compatible con Excel.

## Requisitos

- XAMPP con Apache y MySQL o MariaDB.
- Navegador web.
- Conexión a Internet para cargar Bootstrap desde CDN.

## Instalación

1. Descargar o clonar este repositorio.
2. Copiar la carpeta `control_asistencia` dentro de `htdocs` de XAMPP.
3. Iniciar Apache y MySQL desde el panel de XAMPP.
4. Abrir phpMyAdmin desde `http://localhost/phpmyadmin`.
5. Crear una base de datos llamada `control_asistencia`.
6. Importar el archivo `control_asistencia.sql`.
7. Comprobar los datos de conexión en `config/conexion.php`.
8. Abrir `http://localhost/control_asistencia/` en el navegador.

## Acceso al sistema

**Marcación de asistencia:**

`http://localhost/control_asistencia/`

**Panel de administración:**

`http://localhost/control_asistencia/login.php`

Para acceder al panel administrativo se necesita una cuenta de administrador activa. Las credenciales de prueba se facilitarán al docente por un medio adecuado.

## Funcionamiento

Los empleados registran su entrada y salida utilizando su cédula y código de acceso. El sistema almacena la fecha y hora del servidor.

El administrador puede consultar los registros, gestionar empleados, agregar observaciones y generar reportes.

Las clasificaciones de jornadas son referencias administrativas y no constituyen un cálculo automático de horas extraordinarias conforme a la legislación laboral.

## Consideraciones

El sistema está diseñado como una aplicación web de uso interno. Para utilizarlo en un entorno de producción sería necesario reforzar la seguridad, configurar adecuadamente el servidor y establecer políticas de acceso y respaldo de datos.