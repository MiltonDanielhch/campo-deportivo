/// Bloque de 1 hora de la grilla de disponibilidad (HU-C2).
/// Refleja el JSON del endpoint GET /public/campos/{id}/disponibilidad.
///
/// DECISIÓN DE DISEÑO — `estado` es un String abierto, NO un enum:
///   El backend puede agregar estados nuevos (hoy: 'libre' | 'ocupada' |
///   'bloqueada_temporal' | 'pasada') sin coordinar release con el mobile.
///   Si esto fuera un enum con `firstWhere`/`switch` exhaustivo, un valor
///   nuevo tumbaría el parser o caería en un branch inesperado. Con String,
///   un estado desconocido se parsea bien y —por la regla de oro de abajo—
///   se trata como NO-seleccionable por default. No convertir a enum sin
///   antes asegurar que el render use `esSeleccionable` (no encadenamientos
///   de if-else con fallback "parece libre").
///
/// REGLA DE ORO del contrato de disponibilidad:
///   Solo `estado == 'libre'` es elegible. Cualquier otro valor (incluidos
///   los que no conocemos aún) => `esSeleccionable == false`. Así el render
///   nunca necesita conocer cada estado nuevo para comportarse bien: lo
///   desconocido cae en "no tocable", que siempre es el lado seguro.
class BloqueDisponibilidad {
  final String horaInicio;
  final String horaFin;
  final String estado; // 'libre' | 'ocupada' | 'bloqueada_temporal' | 'pasada' | (+ futuro)
  final double? precio;

  const BloqueDisponibilidad({
    required this.horaInicio,
    required this.horaFin,
    required this.estado,
    this.precio,
  });

  factory BloqueDisponibilidad.fromJson(Map<String, dynamic> json) {
    return BloqueDisponibilidad(
      horaInicio: json['hora_inicio'] as String,
      horaFin: json['hora_fin'] as String,
      estado: json['estado'] as String,
      precio: (json['precio'] as num?)?.toDouble(),
    );
  }

  Map<String, dynamic> toJson() => {
        'hora_inicio': horaInicio,
        'hora_fin': horaFin,
        'estado': estado,
        'precio': precio,
      };

  bool get estaLibre => estado == 'libre';
  bool get estaOcupada => estado == 'ocupada';
  bool get estaBloqueadaTemporal => estado == 'bloqueada_temporal';

  /// [FIX contrato] Estado derivado del tiempo (hoy + hora Bolivia). El
  /// backend lo marca cuando la franja ya comenzó; el mobile debe pintarlo
  /// gris y NO seleccionable, alineado con el gate 422 de reserva.
  bool get estaPasada => estado == 'pasada';

  /// Única fuente de verdad de "se puede elegir". El render DEBE usar esto
  /// en vez de encadenar `estaLibre`/`estaOcupada`/… con fallback a "libre".
  /// Un estado desconocido => false => no-seleccionable (lado seguro).
  bool get esSeleccionable => estado == 'libre';
}

/// Respuesta completa del endpoint de disponibilidad.
class Disponibilidad {
  final String campoId;
  final String nombreCampo;
  final String estadoCampo;
  final String fecha;
  final bool abierto;
  final List<BloqueDisponibilidad> bloques;

  const Disponibilidad({
    required this.campoId,
    required this.nombreCampo,
    required this.estadoCampo,
    required this.fecha,
    required this.abierto,
    this.bloques = const [],
  });

  factory Disponibilidad.fromJson(Map<String, dynamic> json) {
    return Disponibilidad(
      campoId: json['campo_id'] as String,
      nombreCampo: json['nombre_campo'] as String,
      estadoCampo: json['estado_campo'] as String,
      fecha: json['fecha'] as String,
      abierto: json['abierto'] as bool,
      bloques: (json['bloques'] as List<dynamic>?)
              ?.map((e) => BloqueDisponibilidad.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}
