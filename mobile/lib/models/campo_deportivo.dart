/// Modelo de campo deportivo público.
/// Refleja exactamente el JSON devuelto por el backend en
/// GET /api/v1/public/campos y GET /api/v1/public/campos/{id}.
class CampoDeportivo {
  final String id;
  final String nombre;
  final TipoCampo? tipoCampo;
  final String direccion;
  final double latitud;
  final double longitud;
  final String estado;
  final String? imagenUrl;
  /// Hora (HH:MM:SS o HH:MM) a partir de la cual se aplica la tarifa nocturna
  final String horaInicioNoche;
  final Tarifas tarifas;
  final List<HorarioAtencion> horariosAtencion;
  /// ID del servicio SIREB vinculado (nullable)
  final String? servicioSirebId;
  /// Datos del servicio SIREB (nullable)
  final ServicioSireb? sireb;
  /// True si el campo es reservable online según reglas SIREB
  final bool reservableOnline;
  /// Mensaje explicativo si no es reservable
  final String? mensajeNoReservable;
  /// Fuente de los precios (SIREB, LOCAL_FALLBACK, NO_VINCULADO)
  final String? fuentePrecios;

  const CampoDeportivo({
    required this.id,
    required this.nombre,
    this.tipoCampo,
    required this.direccion,
    required this.latitud,
    required this.longitud,
    required this.estado,
    this.imagenUrl,
    required this.horaInicioNoche,
    required this.tarifas,
    this.horariosAtencion = const [],
    this.servicioSirebId,
    this.sireb,
    this.reservableOnline = false,
    this.mensajeNoReservable,
    this.fuentePrecios,
  });

  /// Parsea el JSON del backend.
  factory CampoDeportivo.fromJson(Map<String, dynamic> json) {
    return CampoDeportivo(
      id: json['id'] as String,
      nombre: json['nombre'] as String,
      tipoCampo: json['tipo_campo'] != null
          ? TipoCampo.fromJson(json['tipo_campo'] as Map<String, dynamic>)
          : null,
      direccion: json['direccion'] as String,
      latitud: (json['latitud'] as num).toDouble(),
      longitud: (json['longitud'] as num).toDouble(),
      estado: json['estado'] as String,
      imagenUrl: json['imagen_url'] as String?,
      horaInicioNoche: (json['hora_inicio_noche'] as String?) ?? '18:00:00',
      tarifas: Tarifas.fromJson(json['tarifas'] as Map<String, dynamic>?),
      horariosAtencion: (json['horarios_atencion'] as List<dynamic>?)
              ?.map((e) => HorarioAtencion.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      servicioSirebId: json['servicio_sireb_id'] as String?,
      sireb: json['sireb'] != null
          ? ServicioSireb.fromJson(json['sireb'] as Map<String, dynamic>)
          : null,
      reservableOnline: json['reservable_online'] as bool? ?? false,
      mensajeNoReservable: json['mensaje_no_reservable'] as String?,
      fuentePrecios: json['fuente_precios'] as String?,
    );
  }

  /// Serializa a JSON (para cache local si se necesita en el futuro).
  Map<String, dynamic> toJson() => {
        'id': id,
        'nombre': nombre,
        'tipo_campo': tipoCampo?.toJson(),
        'direccion': direccion,
        'latitud': latitud,
        'longitud': longitud,
        'estado': estado,
        'imagen_url': imagenUrl,
        'hora_inicio_noche': horaInicioNoche,
        'tarifas': tarifas.toJson(),
        'horarios_atencion': horariosAtencion.map((e) => e.toJson()).toList(),
        'servicio_sireb_id': servicioSirebId,
        'sireb': sireb?.toJson(),
        'reservable_online': reservableOnline,
        'mensaje_no_reservable': mensajeNoReservable,
        'fuente_precios': fuentePrecios,
      };

  /// True si el campo está activo y disponible para reserva.
  bool get estaActivo => estado == 'activo';

  /// True si el campo está en mantenimiento (no disponible temporalmente).
  bool get estaEnMantenimiento => estado == 'mantenimiento';

  /// True si el campo es reservable online (considerando estado operativo y SIREB).
  bool get esReservable => reservableOnline && estaActivo;

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

  /// Formatea el precio desde SIREB si está disponible.
  String formatoPrecioSireb() {
    final sireb = this.sireb;
    if (sireb == null) return 'Consultar precio';

    if (sireb.precioMin != null && sireb.precioMax != null) {
      if (sireb.precioMin == sireb.precioMax) {
        return 'Bs. ${sireb.precioMin!.toStringAsFixed(2)}';
      }
      return 'Bs. ${sireb.precioMin!.toStringAsFixed(2)} – Bs. ${sireb.precioMax!.toStringAsFixed(2)}';
    }

    final precios = sireb.tarifas
        .map((t) => t.precio)
        .where((p) => p != null)
        .cast<double>()
        .toList();

    if (precios.isEmpty) return 'Consultar precio';

    final min = precios.reduce((a, b) => a < b ? a : b);
    final max = precios.reduce((a, b) => a > b ? a : b);

    if (min == max) {
      return 'Bs. ${min.toStringAsFixed(2)}';
    }
    return 'Bs. ${min.toStringAsFixed(2)} – Bs. ${max.toStringAsFixed(2)}';
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

/// Servicio SIREB vinculado al campo.
class ServicioSireb {
  final String id;
  final String codigo;
  final String nombre;
  final String? rubro;
  final String? estado;
  final String? tarifario;
  final String? unidadMedida;
  final double? precioMin;
  final double? precioMax;
  final List<TarifaSireb> tarifas;
  final String fuente;

  const ServicioSireb({
    required this.id,
    required this.codigo,
    required this.nombre,
    this.rubro,
    this.estado,
    this.tarifario,
    this.unidadMedida,
    this.precioMin,
    this.precioMax,
    this.tarifas = const [],
    required this.fuente,
  });

  factory ServicioSireb.fromJson(Map<String, dynamic> json) {
    return ServicioSireb(
      id: json['id'] as String,
      codigo: json['codigo'] as String,
      nombre: json['nombre'] as String,
      rubro: json['rubro'] as String?,
      estado: json['estado'] as String?,
      tarifario: json['tarifario'] as String?,
      unidadMedida: json['unidad_medida'] as String?,
      precioMin: json['precio_min']?.toDouble(),
      precioMax: json['precio_max']?.toDouble(),
      tarifas: (json['tarifas'] as List<dynamic>?)
              ?.map((e) => TarifaSireb.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      fuente: json['fuente'] as String,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'codigo': codigo,
        'nombre': nombre,
        'rubro': rubro,
        'estado': estado,
        'tarifario': tarifario,
        'unidad_medida': unidadMedida,
        'precio_min': precioMin,
        'precio_max': precioMax,
        'tarifas': tarifas.map((e) => e.toJson()).toList(),
        'fuente': fuente,
      };
}

/// Tarifa SIREB.
class TarifaSireb {
  final String? id;
  final String? tipo;
  final String etiqueta;
  final double? precio;
  final String? unidadMedida;
  final String? vigenteDesde;

  const TarifaSireb({
    this.id,
    this.tipo,
    required this.etiqueta,
    this.precio,
    this.unidadMedida,
    this.vigenteDesde,
  });

  factory TarifaSireb.fromJson(Map<String, dynamic> json) {
    return TarifaSireb(
      id: json['id'] as String?,
      tipo: json['tipo'] as String?,
      etiqueta: json['etiqueta'] as String,
      precio: json['precio']?.toDouble(),
      unidadMedida: json['unidad_medida'] as String?,
      vigenteDesde: json['vigente_desde'] as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'tipo': tipo,
        'etiqueta': etiqueta,
        'precio': precio,
        'unidad_medida': unidadMedida,
        'vigente_desde': vigenteDesde,
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

  factory Tarifas.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const Tarifas();
    }
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
