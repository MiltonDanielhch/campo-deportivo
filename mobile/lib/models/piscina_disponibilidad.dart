/// Modelos de disponibilidad de piscina.
/// Reflejan el JSON de GET /api/v1/public/piscina/campos/{id}/turnos?fecha=…
///
/// A diferencia de los campos (grilla de bloques de 1 hora), la piscina se
/// reserva por TURNO y cada turno ofrece cupo por PERFIL
/// (`mayor_12` | `menor_12` | `institucion`), cada uno con su propio precio.
class PiscinaPerfilTurno {
  final String perfil;
  final String etiqueta;
  final double precio;
  final int cupo;
  final int disponible;
  final String? tarifaSirebId;

  /// 'diurno' | 'nocturno' (solo informativo para el color del bloque).
  final String? horario;

  const PiscinaPerfilTurno({
    required this.perfil,
    required this.etiqueta,
    required this.precio,
    required this.cupo,
    required this.disponible,
    this.tarifaSirebId,
    this.horario,
  });

  bool get hayCupo => disponible > 0;

  factory PiscinaPerfilTurno.fromJson(Map<String, dynamic> json) {
    return PiscinaPerfilTurno(
      perfil: json['perfil'] as String,
      etiqueta: (json['etiqueta'] as String?) ?? (json['perfil'] as String),
      precio: ((json['precio'] as num?) ?? 0).toDouble(),
      cupo: (json['cupo'] as num?)?.toInt() ?? 0,
      disponible: (json['disponible'] as num?)?.toInt() ?? 0,
      tarifaSirebId: json['tarifa_sireb_id'] as String?,
      horario: json['horario'] as String?,
    );
  }
}

/// Un turno de piscina (p.ej. 09:00 - 11:00) con sus perfiles y cupos.
class PiscinaTurno {
  final String id;
  final String horaInicio; // HH:MM
  final String horaFin; // HH:MM
  final List<PiscinaPerfilTurno> perfiles;

  const PiscinaTurno({
    required this.id,
    required this.horaInicio,
    required this.horaFin,
    this.perfiles = const [],
  });

  String get rango => '$horaInicio - $horaFin';

  /// Cupo total sumando todos los perfiles.
  int get cupoTotal =>
      perfiles.fold(0, (suma, p) => suma + p.cupo);

  factory PiscinaTurno.fromJson(Map<String, dynamic> json) {
    return PiscinaTurno(
      id: json['id'] as String,
      horaInicio: (json['hora_inicio'] as String?) ?? '',
      horaFin: (json['hora_fin'] as String?) ?? '',
      perfiles: (json['perfiles'] as List<dynamic>? ?? [])
          .map((e) => PiscinaPerfilTurno.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

/// Respuesta completa del endpoint de turnos de piscina.
class PiscinaDisponibilidad {
  final String campoId;
  final String fecha;
  final bool abierto;
  final List<PiscinaTurno> turnos;

  const PiscinaDisponibilidad({
    required this.campoId,
    required this.fecha,
    required this.abierto,
    this.turnos = const [],
  });

  factory PiscinaDisponibilidad.fromJson(Map<String, dynamic> json) {
    return PiscinaDisponibilidad(
      campoId: (json['campo_id'] as String?) ?? '',
      fecha: (json['fecha'] as String?) ?? '',
      abierto: (json['abierto'] as bool?) ?? false,
      turnos: (json['turnos'] as List<dynamic>? ?? [])
          .map((e) => PiscinaTurno.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
