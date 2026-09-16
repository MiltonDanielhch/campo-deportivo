/// Una franja seleccionada en el carrito de reserva.
/// Puede pertenecer a fechas e incluso campos distintos (multi-franja).
class FranjaCarrito {
  final String campoId;
  final String campoNombre;
  final String fecha;
  final String horaInicio;
  final String horaFin;
  final double? precio;

  const FranjaCarrito({
    required this.campoId,
    required this.campoNombre,
    required this.fecha,
    required this.horaInicio,
    required this.horaFin,
    this.precio,
  });

  /// Clave única de la franja: campo + fecha + hora de inicio.
  String get key => '$campoId|$fecha|$horaInicio';

  /// Rango legible sin segundos: '10:00 - 11:00'.
  String get rango =>
      '${horaInicio.substring(0, 5)} - ${horaFin.substring(0, 5)}';

  /// Fecha legible: '17/09'.
  String get fechaCorta {
    final partes = fecha.split('-');
    return '${partes[2]}/${partes[1]}';
  }

  @override
  bool operator ==(Object other) =>
      other is FranjaCarrito && other.key == key;

  @override
  int get hashCode => key.hashCode;
}
