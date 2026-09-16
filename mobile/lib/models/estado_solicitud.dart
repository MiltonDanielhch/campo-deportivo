/// Una reserva confirmada dentro de una solicitud (HU-D6).
class ReservaConfirmada {
  final String codigoReserva;
  final String campoNombre;
  final String fecha;
  final String horaInicio;
  final String horaFin;

  const ReservaConfirmada({
    required this.codigoReserva,
    required this.campoNombre,
    required this.fecha,
    required this.horaInicio,
    required this.horaFin,
  });

  /// Fecha legible: '17/09'.
  String get fechaCorta {
    final partes = fecha.split('-');
    return partes.length == 3 ? '${partes[2]}/${partes[1]}' : fecha;
  }

  factory ReservaConfirmada.fromJson(Map<String, dynamic> json) {
    return ReservaConfirmada(
      codigoReserva: (json['codigo_reserva'] as String?) ?? '',
      campoNombre: (json['campo_nombre'] as String?) ?? '',
      fecha: (json['fecha'] as String?) ?? '',
      horaInicio: (json['hora_inicio'] as String?) ?? '',
      horaFin: (json['hora_fin'] as String?) ?? '',
    );
  }
}

/// Estado público de una solicitud (respuesta del endpoint de la Fase 5.4).
class EstadoSolicitud {
  final String codigoSeguimiento;
  final String estado;
  final double montoTotal;
  final String? expiraEn;
  final List<ReservaConfirmada> reservas;

  const EstadoSolicitud({
    required this.codigoSeguimiento,
    required this.estado,
    required this.montoTotal,
    this.expiraEn,
    this.reservas = const [],
  });

  bool get estaConfirmada => estado == 'confirmada';
  bool get estaExpirada => estado == 'expirada';
  bool get estaRechazada => estado == 'rechazada';

  factory EstadoSolicitud.fromJson(Map<String, dynamic> json) {
    return EstadoSolicitud(
      codigoSeguimiento: (json['codigo_seguimiento'] as String?) ?? '',
      estado: (json['estado'] as String?) ?? '',
      montoTotal: ((json['monto_total'] as num?) ?? 0).toDouble(),
      expiraEn: json['expira_en'] as String?,
      reservas: (json['reservas'] as List<dynamic>? ?? [])
          .map((r) => ReservaConfirmada.fromJson(r as Map<String, dynamic>))
          .toList(),
    );
  }
}
