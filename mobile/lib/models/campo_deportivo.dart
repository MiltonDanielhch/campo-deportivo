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
  final String? imagenUrl;
  /// Hora (HH:MM:SS o HH:MM) a partir de la cual se aplica la tarifa nocturna
  final String horaInicioNoche;
  final Tarifas tarifas;
  final List<HorarioAtencion> horariosAtencion;

  const CampoDeportivo({
    required this.id,
    required this.nombre,
    required this.tipoCampo,
    required this.direccion,
    required this.latitud,
    required this.longitud,
    required this.estado,
    this.imagenUrl,
    required this.horaInicioNoche,
    required this.tarifas,
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
      imagenUrl: json['imagen_url'] as String?,
      horaInicioNoche: (json['hora_inicio_noche'] as String?) ?? '18:00:00',
      tarifas: Tarifas.fromJson(json['tarifas'] as Map<String, dynamic>),
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
        'imagen_url': imagenUrl,
        'hora_inicio_noche': horaInicioNoche,
        'tarifas': tarifas.toJson(),
        'horarios_atencion': horariosAtencion.map((e) => e.toJson()).toList(),
      };

  /// True si el campo está activo y disponible para reserva.
  bool get estaActivo => estado == 'activo';

  /// True si el campo está en mantenimiento (no disponible temporalmente).
  bool get estaEnMantenimiento => estado == 'mantenimiento';

  /// URL de la imagen pasando por el proxy CORS de la API.
  /// Necesario en Flutter Web: los /storage/... directos no traen cabeceras
  /// CORS y CanvasKit los rechaza. Si la URL no es local (ej: S3), se deja igual.
  String? get imagenUrlProxy {
    if (imagenUrl == null) return null;
    return imagenUrl!.replaceFirst('/storage/', '/api/v1/public/storage/');
  }

  /// Calcula el precio de un bloque según su hora de inicio.
  /// Retorna null si no hay tarifa definida para ese tipo.
  double? precioParaBloque(String horaInicio) {
    final esNocturno = esHoraNocturna(horaInicio);
    final tarifa = esNocturno ? tarifas.nocturna : tarifas.diurna;
    return tarifa?.precioPorHora;
  }

  /// Determina si una hora (HH:MM o HH:MM:SS) es nocturna según horaInicioNoche.
  bool esHoraNocturna(String horaInicio) {
    final minutosBloque = _minutosDesdeMedianoche(horaInicio);
    final minutosCorte = _minutosDesdeMedianoche(horaInicioNoche);
    return minutosBloque >= minutosCorte;
  }

  /// Convierte "HH:MM" o "HH:MM:SS" a minutos desde la medianoche.
  int _minutosDesdeMedianoche(String hora) {
    final partes = hora.split(':');
    final h = int.tryParse(partes[0]) ?? 0;
    final m = int.tryParse(partes.length > 1 ? partes[1] : '0') ?? 0;
    return h * 60 + m;
  }
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

/// Contenedor de las dos tarifas (regular y con iluminación).
class Tarifas {
  final TarifaVigente? diurna;
  final TarifaVigente? nocturna;

  const Tarifas({
    this.diurna,
    this.nocturna,
  });

  factory Tarifas.fromJson(Map<String, dynamic> json) {
    return Tarifas(
      diurna: json['diurna'] != null
          ? TarifaVigente.fromJson(json['diurna'] as Map<String, dynamic>)
          : null,
      nocturna: json['nocturna'] != null
          ? TarifaVigente.fromJson(json['nocturna'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'diurna': diurna?.toJson(),
        'nocturna': nocturna?.toJson(),
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
