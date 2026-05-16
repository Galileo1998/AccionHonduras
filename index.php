<?php
// Este archivo sirve como puente de seguridad.
// Redirige todo el tráfico que entra a la raíz directamente a la carpeta pública.
header("Location: public/");
exit();
?>