import 'dart:convert';

import 'package:http/http.dart' as http;
import '../models/piscina_disponibilidad.dart';
import '../models/reserva_creada.dart';
import 'solicitud_reserva_service.dart';

/// Error tipado del endpoint de creación de reservas de piscina.
/// Distingue falta de cupo (409/422) de cobro caído (503).
class PiscinaReservaException implements Exception {
  final int statusCode;
  final String message;

  PiscinaReservaException(this.statusCode, this.message);

  bool get esCupoInsuficiente => statusCode == 409 || statusCode == 422;
  bool get esCobroNoDisponible => statusCode == 503;

  @override
  String toString() => message;
}

/// Un renglón de la reserva de piscina: perfil + cantidad de personas.
class PiscinaItemSeleccion {
  final String perfil;
  final int cantidad;

  const PiscinaItemSeleccion({required this.perfil, required this.cantidad});

  Map<String, dynamic> toJson() => {'perfil': perfil, 'cantidad': cantidad};
}

/// Respuesta 201 del endpoint de reserva de piscina.
///
/// El QR no viene en la respuesta de creación (a diferencia de campos): se
/// reconstruye desde el código público, así que se busca con una consulta de
/// estado posterior a la creación.
class PiscinaReservaCreada implements ReservaCreada {
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

  const PiscinaReservaCreada({
    required this.codigoSeguimiento,
    required this.montoTotal,
    required this.expiraEn,
    this.qrString,
    this.checkoutUrl,
    this.qrImageBase64,
    this.qrDiferido = true,
  });
}

/// Service para consumir los endpoints públicos de piscina.
class PiscinaService {
  static String get baseUrl => SolicitudReservaService.baseUrl;

  /// GET /api/v1/public/piscina/campos/{campoId}/turnos?fecha=YYYY-MM-DD
  Future<PiscinaDisponibilidad> obtenerTurnos(
    String campoId,
    String fecha,
  ) async {
    final response = await http.get(
      Uri.parse('$baseUrl/public/piscina/campos/$campoId/turnos?fecha=$fecha'),
      headers: {'Accept': 'application/json'},
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return PiscinaDisponibilidad.fromJson(data['data']);
    } else if (response.statusCode == 422) {
      throw Exception('Fecha inválida: debe estar entre hoy y 60 días');
    } else if (response.statusCode == 404) {
      throw Exception('Campo no encontrado');
    }
    throw Exception('Error al consultar turnos: ${response.statusCode}');
  }

  /// POST /api/v1/public/piscina/reservas
  Future<PiscinaReservaCreada> crearReserva({
    required String fecha,
    required String turnoId,
    required List<PiscinaItemSeleccion> items,
    required String nombrePagador,
    required String telefonoPagador,
    required String ciNitPagador,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/public/piscina/reservas'),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'fecha': fecha,
        'turno_id': turnoId,
        'items': items.map((i) => i.toJson()).toList(),
        'nombre_pagador': nombrePagador,
        'telefono_pagador': telefonoPagador,
        'ci_nit_pagador': ciNitPagador,
      }),
    );

    if (response.statusCode == 201) {
      final body = jsonDecode(response.body) as Map<String, dynamic>;
      final data = (body['data'] as Map<String, dynamic>?) ?? {};

      final codigo = (data['codigo_seguimiento'] as String?) ?? '';

      // El QR no viene en la creación: se lee del estado (best-effort).
      final qr = await _obtenerQr(codigo);

      return PiscinaReservaCreada(
        codigoSeguimiento: codigo,
        montoTotal: ((data['monto_total'] as num?) ?? 0).toDouble(),
        expiraEn: (data['expira_en'] as String?) ?? '',
        qrString: qr,
      );
    }

    Map<String, dynamic> body = {};
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) body = decoded;
    } catch (_) {
      // respuesta no-JSON: se reporta con el status code
    }

    throw PiscinaReservaException(
      response.statusCode,
      (body['message'] as String?) ??
          'Error al crear la reserva (${response.statusCode})',
    );
  }

  /// GET /public/piscina/reservas/{codigo}/estado → datos_cobro_pendiente.qr_string
  Future<String?> _obtenerQr(String codigo) async {
    if (codigo.isEmpty) return null;
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/public/piscina/reservas/$codigo/estado'),
        headers: {'Accept': 'application/json'},
      );
      if (response.statusCode != 200) return null;

      final body = jsonDecode(response.body) as Map<String, dynamic>;
      final data = body['data'] as Map<String, dynamic>?;
      final cobro = data?['datos_cobro_pendiente'] as Map<String, dynamic>?;
      return cobro?['qr_string'] as String?;
    } catch (_) {
      // La liquidación puede tardar: en ese caso la pantalla de pago
      // reintenta obtener el QR con su propio polling.
      return null;
    }
  }
}
