<?php

namespace App\Enums;

enum EstadoSolicitudReserva: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Expirada = 'expirada';
    case Cancelada = 'cancelada';
    case Rechazada = 'rechazada';
}
