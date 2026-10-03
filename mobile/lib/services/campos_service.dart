import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/campo_deportivo.dart';

/// Service para consumir los endpoints públicos de campos deportivos.
/// No requiere autenticación (consulta ciudadana).
class CamposService {
  static String get baseUrl {
    const envUrl = String.fromEnvironment('API_BASE_URL');
    if (envUrl.isNotEmpty) {
      return envUrl;
    }

    // Por defecto: localhost para web, 10.0.2.2 para emulador Android
    return 'http://localhost:8000/api/v1';
  }

  /// Lista todos los campos deportivos públicos (activos y en mantenimiento).
  /// GET /api/v1/public/campos
  Future<List<CampoDeportivo>> listarCampos() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/public/campos'),
        headers: {'Accept': 'application/json'},
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final List<dynamic> camposJson = data['data'];
        return camposJson
            .map((json) => CampoDeportivo.fromJson(json))
            .toList();
      } else {
        throw Exception(
            'Error al listar campos: ${response.statusCode} - ${response.body}');
      }
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }

  /// Obtiene meta información del catálogo (fuente de precios, sincronización, etc.)
  Future<Map<String, dynamic>?> obtenerMeta() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/public/campos'),
        headers: {'Accept': 'application/json'},
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data['meta'] as Map<String, dynamic>?;
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  /// Obtiene el detalle de un campo deportivo específico.
  /// GET /api/v1/public/campos/{id}
  Future<CampoDeportivo> obtenerCampo(String id) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/public/campos/$id'),
        headers: {'Accept': 'application/json'},
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return CampoDeportivo.fromJson(data['data']);
      } else if (response.statusCode == 404) {
        throw Exception('Campo no encontrado');
      } else {
        throw Exception(
            'Error al obtener campo: ${response.statusCode} - ${response.body}');
      }
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }
}
