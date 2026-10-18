<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoDocumentoCliente: string
{
    case DNI = 'DNI';
    case RUC = 'RUC';
    case CE = 'CE';
    case SIN_DOC = 'SIN_DOC';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DNI => 'DNI',
            self::RUC => 'RUC',
            self::CE => 'Carnet de Extranjería',
            self::SIN_DOC => 'Sin Documento',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DNI => 'success',
            self::RUC => 'primary',
            self::CE => 'warning',
            self::SIN_DOC => 'gray',
        };
    }
}
