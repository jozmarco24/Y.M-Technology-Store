<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoCupon;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cupon extends Model
{
    use HasFactory;

    protected $table = 'cupones';

    protected $fillable = [
        'codigo',
        'tipo',
        'valor',
        'minimo_compra',
        'usos_maximos',
        'usos_actuales',
        'desde',
        'hasta',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCupon::class,
            'valor' => 'decimal:2',
            'minimo_compra' => 'decimal:2',
            'usos_maximos' => 'integer',
            'usos_actuales' => 'integer',
            'desde' => 'datetime',
            'hasta' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function esVigente(): bool
    {
        if (! $this->activo) {
            return false;
        }

        $now = Carbon::now();
        if ($this->desde && $now->lt($this->desde)) {
            return false;
        }

        if ($this->hasta && $now->gt($this->hasta)) {
            return false;
        }

        if ($this->usos_maximos !== null && $this->usos_actuales >= $this->usos_maximos) {
            return false;
        }

        return true;
    }

    public function calcularDescuento(float $montoSubtotal): float
    {
        if ($this->minimo_compra !== null && $montoSubtotal < (float) $this->minimo_compra) {
            return 0.0;
        }

        if ($this->tipo === TipoCupon::PORCENTAJE) {
            $monto = ($montoSubtotal * (float) $this->valor) / 100.0;

            return round(min($montoSubtotal, $monto), 2);
        }

        // TipoCupon::MONTO
        return round(min($montoSubtotal, (float) $this->valor), 2);
    }
}
