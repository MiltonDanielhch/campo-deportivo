/// Bloque de 1 hora de la grilla de disponibilidad (HU-C2).
/// Refleja el JSON del endpoint GET /public/campos/{id}/disponibilidad.
class BloqueDisponibilidad {
  final String horaInicio;
  final String horaFin;
  final String estado; // 'libre' | 'ocupada' | 'bloqueada_temporal'
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
