/// Una reserva confirmada dentro de una solicitud (HU-D6).
class ReservaConfirmada {
  final String codigoReserva;
  final String campoNombre;
  final String fecha;
  final String horaInicio;
  final String horaFin;

  /// Solo piscina: perfil de la reserva ('mayor_12' | 'menor_12' | 'institucion')
  final String? perfil;

  /// Solo piscina: cantidad de personas del perfil.
  final int? cantidad;

  const ReservaConfirmada({
    required this.codigoReserva,
    required this.campoNombre,
    required this.fecha,
    required this.horaInicio,
    required this.horaFin,
    this.perfil,
    this.cantidad,
  });

  /// Fecha legible: '17/09'.
  String get fechaCorta {
    final partes = fecha.split('-');
    return partes.length == 3 ? '${partes[2]}/${partes[1]}' : fecha;
  }

  /// True si la reserva trae perfil (reservas de piscina).
  bool get tienePerfil => perfil != null;

  /// Etiqueta legible del perfil, ej. 'Mayores de 12 × 2'.
  String? get etiquetaPerfil {
    if (perfil == null) return null;
    final nombre = switch (perfil) {
      'mayor_12' => 'Mayores de 12',
      'menor_12' => 'Menores de 12',
      'institucion' => 'Institución',
      _ => perfil!,
    };
    return cantidad != null ? '$nombre × $cantidad' : nombre;
  }

  factory ReservaConfirmada.fromJson(Map<String, dynamic> json) {
    return ReservaConfirmada(
      codigoReserva: (json['codigo_reserva'] as String?) ?? '',
      campoNombre: (json['campo_nombre'] as String?) ?? '',
      fecha: (json['fecha'] as String?) ?? '',
      horaInicio: (json['hora_inicio'] as String?) ?? '',
      horaFin: (json['hora_fin'] as String?) ?? '',
      perfil: json['perfil'] as String?,
      cantidad: (json['cantidad'] as num?)?.toInt(),
    );
  }
}

/// Estado público de una reserva (campos o piscina).
///
/// Lo devuelve el endpoint unificado `/public/consultas/{termino}/estado`,
/// que acepta código de seguimiento (RES-/PIS-), código de ventanilla del
/// Banco Unión, CI/NIT o teléfono.
class EstadoSolicitud {
  /// 'campo' | 'piscina'
  final String tipo;
  final String codigoSeguimiento;
  final String estado;
  final double montoTotal;
  final String? expiraEn;
  final List<ReservaConfirmada> reservas;

  /// Bloque de cobro del Core (solo mientras el estado es 'pendiente').
  final Map<String, dynamic>? datosCobroPendiente;

  const EstadoSolicitud({
    this.tipo = 'campo',
    required this.codigoSeguimiento,
    required this.estado,
    required this.montoTotal,
    this.expiraEn,
    this.reservas = const [],
    this.datosCobroPendiente,
  });

  bool get estaConfirmada => estado == 'confirmada';
  bool get estaExpirada => estado == 'expirada';
  bool get estaRechazada => estado == 'rechazada';
  bool get esPiscina => tipo == 'piscina';

  /// Contenido del QR del Core, reconstruido desde el código público.
  String? get qrString => datosCobroPendiente?['qr_string'] as String?;

  factory EstadoSolicitud.fromJson(Map<String, dynamic> json) {
    return EstadoSolicitud(
      tipo: (json['tipo'] as String?) ?? 'campo',
      codigoSeguimiento: (json['codigo_seguimiento'] as String?) ?? '',
      estado: (json['estado'] as String?) ?? '',
      montoTotal: ((json['monto_total'] as num?) ?? 0).toDouble(),
      expiraEn: json['expira_en'] as String?,
      reservas: (json['reservas'] as List<dynamic>? ?? [])
          .map((r) => ReservaConfirmada.fromJson(r as Map<String, dynamic>))
          .toList(),
      datosCobroPendiente: json['datos_cobro_pendiente'] as Map<String, dynamic>?,
    );
  }
}
