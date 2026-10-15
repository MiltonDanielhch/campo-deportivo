/// Contrato común de una reserva recién creada.
///
/// Lo implementan tanto las solicitudes de campos (multi-franja) como las
/// reservas de piscina (turno por perfil). Así la pantalla de pago
/// (`PagoQrScreen`) y su cuenta regresiva sirven a ambos flujos sin duplicar
/// lógica, aunque los endpoints de origen sean distintos.
abstract class ReservaCreada {
  /// Código de seguimiento (RES-… para campos, PIS-… para piscina).
  String get codigoSeguimiento;

  /// Monto a pagar.
  double get montoTotal;

  /// Momento de expiración (ISO 8601).
  String get expiraEn;

  /// Contenido del QR del Core de Recaudaciones (p.ej. 'SIREB:XXXX').
  String? get qrString;

  /// URL de checkout externa, si el Core la provee.
  String? get checkoutUrl;

  /// Imagen del QR en base64, si el Core la provee.
  String? get qrImageBase64;

  /// True si el QR no viene en la respuesta de creación y hay que
  /// esperarlo por polling (caso piscina).
  bool get qrDiferido => false;
}

/// Adapta una reserva **pendiente** obtenida por la consulta pública al
/// contrato `ReservaCreada`, para poder reanudar el pago (mismo QR y misma
/// cuenta regresiva) desde `ConsultarEstadoScreen`.
class ReservaDesdeEstado implements ReservaCreada {
  @override
  final String codigoSeguimiento;
  @override
  final double montoTotal;
  @override
  final String expiraEn;
  @override
  final String? qrString;
  @override
  final String? checkoutUrl;
  @override
  final String? qrImageBase64;
  @override
  final bool qrDiferido;

  const ReservaDesdeEstado({
    required this.codigoSeguimiento,
    required this.montoTotal,
    this.expiraEn = '',
    this.qrString,
    this.checkoutUrl,
    this.qrImageBase64,
    this.qrDiferido = false,
  });
}
