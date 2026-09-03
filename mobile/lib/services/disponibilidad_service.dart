import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/bloque_disponibilidad.dart';

/// Service para consumir el endpoint público de disponibilidad horaria.
class DisponibilidadService {
  static String get baseUrl {
    const envUrl = String.fromEnvironment('API_BASE_URL');
    if (envUrl.isNotEmpty) {
      return envUrl;
    }
    return 'http://localhost:8000/api/v1';
  }

  /// GET /api/v1/public/campos/{id}/disponibilidad?fecha=YYYY-MM-DD
  Future<Disponibilidad> obtenerDisponibilidad(
    String campoId,
    DateTime fecha,
  ) async {
    final fechaStr =
        '${fecha.year.toString().padLeft(4, '0')}-${fecha.month.toString().padLeft(2, '0')}-${fecha.day.toString().padLeft(2, '0')}';

    final response = await http.get(
      Uri.parse('$baseUrl/public/campos/$campoId/disponibilidad?fecha=$fechaStr'),
      headers: {'Accept': 'application/json'},
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return Disponibilidad.fromJson(data['data']);
    } else if (response.statusCode == 422) {
      throw Exception('Fecha inválida: debe estar entre hoy y 60 días');
    } else if (response.statusCode == 404) {
      throw Exception('Campo no encontrado');
    } else {
      throw Exception('Error al consultar disponibilidad: ${response.statusCode}');
    }
  }
}
