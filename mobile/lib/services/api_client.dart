import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiClient {
  static String get baseUrl {
    // Si se pasa --dart-define, usa ese valor
    const envUrl = String.fromEnvironment('API_BASE_URL');
    if (envUrl.isNotEmpty) {
      return envUrl;
    }

    // Por defecto: localhost para web, 10.0.2.2 para emulador Android
    return 'http://localhost:8000/api/v1';
  }

  Future<Map<String, dynamic>> getHealthCheck() async {
    final response = await http.get(Uri.parse('$baseUrl/health'));
    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Error al conectar con el backend: ${response.statusCode}');
    }
  }
}
