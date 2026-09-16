import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/franja_carrito.dart';

/// Error tipado del endpoint de creación de solicitudes.
/// La app distingue 409 (franja perdida: quitar del carrito)
/// de 503 (cobro caído: reintentar más tarde, carrito intacto).
class SolicitudReservaException implements Exception {
  final int statusCode;
  final String message;
  final Map<String, dynamic>? franja;

  SolicitudReservaException(this.statusCode, this.message, this.franja);

  bool get esFranjaNoDisponible => statusCode == 409;
  bool get esCobroNoDisponible => statusCode == 503;

  @override
  String toString() => message;
}

/// Respuesta 201: la solicitud creada + el bloque de cobro del Core.
class SolicitudCreada {
  final Map<String, dynamic> solicitud;
  final Map<String, dynamic>? cobro;

  SolicitudCreada({required this.solicitud, this.cobro});

  String get codigoSeguimiento =>
      (solicitud['codigo_seguimiento'] as String?) ?? '';

  String get expiraEn => (solicitud['expira_en'] as String?) ?? '';

  double get montoTotal =>
      ((solicitud['monto_total'] as num?) ?? 0).toDouble();

  String? get qrString => (cobro?['qr_string'] as String?);
  String? get checkoutUrl => (cobro?['checkout_url'] as String?);
}

class SolicitudReservaService {
  static String get baseUrl {
    const envUrl = String.fromEnvironment('API_BASE_URL');
    if (envUrl.isNotEmpty) {
      return envUrl;
    }
    return 'http://localhost:8000/api/v1';
  }

  /// POST /api/v1/public/solicitudes-reserva
  Future<SolicitudCreada> crearSolicitud({
    required String nombrePagador,
    required String telefonoPagador,
    String? ciNitPagador,
    required List<FranjaCarrito> franjas,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/public/solicitudes-reserva'),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'nombre_pagador': nombrePagador,
        'telefono_pagador': telefonoPagador,
        'ci_nit_pagador': ciNitPagador,
        'franjas': franjas
            .map((f) => {
                  'campo_id': f.campoId,
                  'fecha': f.fecha,
                  'hora_inicio': f.horaInicio,
                  'hora_fin': f.horaFin,
                })
            .toList(),
      }),
    );

    if (response.statusCode == 201) {
      final body = jsonDecode(response.body) as Map<String, dynamic>;
      return SolicitudCreada(
        solicitud: (body['data'] as Map<String, dynamic>?) ?? {},
        cobro: body['cobro'] as Map<String, dynamic>?,
      );
    }

    Map<String, dynamic> body = {};
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) body = decoded;
    } catch (_) {
      // respuesta no-JSON: se reporta con el status code
    }

    throw SolicitudReservaException(
      response.statusCode,
      (body['message'] as String?) ??
          'Error al crear la solicitud (${response.statusCode})',
      body['franja'] as Map<String, dynamic>?,
    );
  }
}
