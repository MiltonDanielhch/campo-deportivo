<?php

namespace App\Enums;

enum ModalidadReservaCampo: string
{
    case FranjaHoraria = 'franja_horaria'; // Canchas: reserva por franja horaria
    case TurnoPorPerfil = 'turno_por_perfil'; // Piscina: reserva por turno + perfil + cantidad
    case SinReservaOnline = 'sin_reserva_online'; // Solo ventanilla
}
