<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;

abstract class BaseReporteService
{
    /**
     * Duración en minutos de la caché de reportes (5 a 15 minutos).
     */
    protected const CACHE_TTL_MINUTOS = 10;

    /**
     * Versión de caché para invalidación global.
     */
    protected const CACHE_VERSION_KEY = 'reportes_cache_version';

    /**
     * Obtener versión actual de la caché de reportes.
     */
    public static function obtenerCacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    /**
     * Incrementar versión de caché para invalidar todos los reportes tras venta o compra.
     */
    public static function invalidarCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, self::obtenerCacheVersion() + 1);
    }

    /**
     * Generar clave única de caché que incorpora versión, filtros y usuario.
     *
     * @param  array<string, mixed>  $params
     */
    protected function generarCacheKey(string $nombreReporte, User $usuario, array $params = []): string
    {
        $version = self::obtenerCacheVersion();
        $permisosMargen = $this->puedeVerMargen($usuario) ? '1' : '0';
        $vendedorId = $usuario->hasRole('admin') ? 'all' : (string) $usuario->id;
        $serializedParams = md5(serialize($params));

        return "rep:v{$version}:{$nombreReporte}:usr{$vendedorId}:m{$permisosMargen}:{$serializedParams}";
    }

    /**
     * Ejecuta una consulta cacheada según parámetros.
     *
     * @template T
     *
     * @param  array<string, mixed>  $params
     * @param  callable(): T  $callback
     * @return T
     */
    protected function recordar(string $nombreReporte, User $usuario, array $params, callable $callback): mixed
    {
        $key = $this->generarCacheKey($nombreReporte, $usuario, $params);

        return Cache::remember($key, Carbon::now()->addMinutes(self::CACHE_TTL_MINUTOS), $callback);
    }

    /**
     * Determina si el usuario tiene permiso para ver costos y márgenes.
     */
    public function puedeVerMargen(User $usuario): bool
    {
        return $usuario->hasRole('admin') || $usuario->hasPermissionTo('reportes.ver_margen');
    }

    /**
     * Determina si el usuario solo puede ver sus propios registros (vendedor sin admin).
     */
    public function esVendedorRestringido(User $usuario): bool
    {
        return ! $usuario->hasRole('admin') && $usuario->hasRole('vendedor');
    }

    /**
     * Aplica filtros estándar de fecha y canal sobre un Query Builder de ventas.
     *
     * @param  Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    protected function aplicarFiltrosVentas(
        mixed $query,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        ?string $canal = null,
        string $columnaFecha = 'ventas.created_at',
        string $columnaCanal = 'ventas.canal'
    ): mixed {
        if ($desde !== null) {
            $query->whereDate($columnaFecha, '>=', $desde->toDateString());
        }

        if ($hasta !== null) {
            $query->whereDate($columnaFecha, '<=', $hasta->toDateString());
        }

        if ($canal !== null && $canal !== 'todos' && $canal !== '') {
            $query->where($columnaCanal, $canal);
        }

        return $query;
    }
}
