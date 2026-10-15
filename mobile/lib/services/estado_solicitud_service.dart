import 'dart:convert';

import 'package:http/http.dart' as http;
import '../models/estado_solicitud.dart';
import 'solicitud_reserva_service.dart';

class EstadoSolicitudNoEncontradaException implements Exception {}

/// Consulta pública del estado de una reserva (HU-D7).
/// La usa la pantalla de pago (polling cada 5s) y la de consulta manual.
///
/// Usa el endpoint unificado, que acepta como término el código de seguimiento
/// (RES-… / PIS-…), el código de pago en ventanilla del Banco Unión, o el
/// CI/NIT / teléfono del pagador.
class EstadoSolicitudService {
  Future<EstadoSolicitud> consultar(String termino) async {
    final busqueda = termino.trim();
    final response = await http.get(
      Uri.parse(
        '${SolicitudReservaService.baseUrl}/public/consultas/${Uri.encodeComponent(busqueda)}/estado',
      ),
      headers: {'Accept': 'application/json'},
    );

    if (response.statusCode == 200) {
      final body = jsonDecode(response.body) as Map<String, dynamic>;
      return EstadoSolicitud.fromJson(body['data'] as Map<String, dynamic>);
    }

    if (response.statusCode == 404 || response.statusCode == 422) {
      throw EstadoSolicitudNoEncontradaException();
    }

    throw Exception('Error al consultar el estado (${response.statusCode})');
  }
}
