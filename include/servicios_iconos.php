<?php
declare(strict_types=1);

function obtener_iconos_servicio_disponibles(): array
{
    return [
        'fa-shield-halved' => 'Seguridad (fa-shield-halved)',
        'fa-lock' => 'Candado (fa-lock)',
        'fa-user-shield' => 'Proteccion de usuario (fa-user-shield)',
        'fa-laptop' => 'Laptop (fa-laptop)',
        'fa-network-wired' => 'Redes (fa-network-wired)',
        'fa-server' => 'Servidores (fa-server)',
        'fa-cloud' => 'Nube (fa-cloud)',
        'fa-headset' => 'Soporte tecnico (fa-headset)',
        'fa-cart-shopping' => 'E-Commerce (fa-cart-shopping)',
        'fa-code' => 'Desarrollo (fa-code)',
        'fa-mobile-screen-button' => 'Movil (fa-mobile-screen-button)',
        'fa-chart-line' => 'Analitica (fa-chart-line)',
        'fa-database' => 'Base de datos (fa-database)',
        'fa-cogs' => 'Configuracion (fa-cogs)',
        'fa-gears' => 'Automatizacion (fa-gears)',
        'fa-wifi' => 'Conectividad (fa-wifi)',
        'fa-bolt' => 'Rendimiento (fa-bolt)',
        'fa-circle-info' => 'Informacion (fa-circle-info)',
    ];
}

function normalizar_icono_servicio_lista(string $icono): string
{
    return strtolower(trim($icono));
}

function icono_servicio_es_valido(string $icono): bool
{
    $iconoNormalizado = normalizar_icono_servicio_lista($icono);
    if ($iconoNormalizado === '') {
        return false;
    }

    $iconos = obtener_iconos_servicio_disponibles();
    return isset($iconos[$iconoNormalizado]);
}
