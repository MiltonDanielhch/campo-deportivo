/// Modelo de campo deportivo público.
/// Refleja exactamente el JSON devuelto por el backend en
/// GET /api/v1/public/campos y GET /api/v1/public/campos/{id}.
class CampoDeportivo {
  final String id;
  final String nombre;
  final TipoCampo tipoCampo;
  final String direccion;
  final double latitud;
  final double longitud;
  final String estado;
  final TarifaVigente? tarifaVigente;
  final List<HorarioAtencion> horariosAtencion;

  const CampoDeportivo({
    required this.id,
    required this.nombre,
    required this.tipoCampo,
    required this.direccion,
    required this.latitud,
    required this.longitud,
    required this.estado,
    this.tarifaVigente,
    this.horariosAtencion = const [],
  });

  /// Parsea el JSON del backend.
  factory CampoDeportivo.fromJson(Map<String, dynamic> json) {
    return CampoDeportivo(
      id: json['id'] as String,
      nombre: json['nombre'] as String,
      tipoCampo: TipoCampo.fromJson(json['tipo_campo'] as Map<String, dynamic>),
      direccion: json['direccion'] as String,
      latitud: (json['latitud'] as num).toDouble(),
      longitud: (json['longitud'] as num).toDouble(),
      estado: json['estado'] as String,
      tarifaVigente: json['tarifa_vigente'] != null
          ? TarifaVigente.fromJson(json['tarifa_vigente'] as Map<String, dynamic>)
          : null,
      horariosAtencion: (json['horarios_atencion'] as List<dynamic>?)
              ?.map((e) => HorarioAtencion.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  /// Serializa a JSON (para cache local si se necesita en el futuro).
  Map<String, dynamic> toJson() => {
        'id': id,
        'nombre': nombre,
        'tipo_campo': tipoCampo.toJson(),
        'direccion': direccion,
        'latitud': latitud,
        'longitud': longitud,
        'estado': estado,
        'tarifa_vigente': tarifaVigente?.toJson(),
        'horarios_atencion': horariosAtencion.map((e) => e.toJson()).toList(),
      };

  /// True si el campo está activo y disponible para reserva.
  bool get estaActivo => estado == 'activo';

  /// True si el campo está en mantenimiento (no disponible temporalmente).
  bool get estaEnMantenimiento => estado == 'mantenimiento';
}

/// Tipo de campo deportivo (fútbol, tenis, etc.)
class TipoCampo {
  final String id;
  final String nombre;

  const TipoCampo({
    required this.id,
    required this.nombre,
  });

  factory TipoCampo.fromJson(Map<String, dynamic> json) {
    return TipoCampo(
      id: json['id'] as String,
      nombre: json['nombre'] as String,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'nombre': nombre,
      };
}

/// Tarifa vigente del campo (precio por hora).
class TarifaVigente {
  final double precioPorHora;
  final DateTime vigenteDesde;

  const TarifaVigente({
    required this.precioPorHora,
    required this.vigenteDesde,
  });

  factory TarifaVigente.fromJson(Map<String, dynamic> json) {
    return TarifaVigente(
      precioPorHora: (json['precio_por_hora'] as num).toDouble(),
      vigenteDesde: DateTime.parse(json['vigente_desde'] as String),
    );
  }

  Map<String, dynamic> toJson() => {
        'precio_por_hora': precioPorHora,
        'vigente_desde': vigenteDesde.toIso8601String(),
      };
}

/// Horario de atención por día de la semana (1=lunes, 7=domingo).
class HorarioAtencion {
  final int diaSemana;
  final String horaApertura;
  final String horaCierre;

  const HorarioAtencion({
    required this.diaSemana,
    required this.horaApertura,
    required this.horaCierre,
  });

  factory HorarioAtencion.fromJson(Map<String, dynamic> json) {
    return HorarioAtencion(
      diaSemana: json['dia_semana'] as int,
      horaApertura: json['hora_apertura'] as String,
      horaCierre: json['hora_cierre'] as String,
    );
  }

  Map<String, dynamic> toJson() => {
        'dia_semana': diaSemana,
        'hora_apertura': horaApertura,
        'hora_cierre': horaCierre,
      };

  /// Obtiene el nombre del día de la semana en español.
  String get nombreDia {
    const dias = [
      'Lunes', 'Martes', 'Miércoles', 'Jueves',
      'Viernes', 'Sábado', 'Domingo',
    ];
    return dias[diaSemana - 1];
  }
}
