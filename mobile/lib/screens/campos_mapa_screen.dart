import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../models/campo_deportivo.dart';

/// Vista de mapa interactivo con un marcador por campo (HU-C1).
/// Los campos en mantenimiento usan color e ícono distintos.
///
/// El permiso de ubicación es opcional y no bloqueante: el mapa se
/// centra por defecto en Trinidad (ciudad principal de operación) y
/// la app sigue siendo completamente usable sin ese permiso.
class CamposMapaView extends StatelessWidget {
  final List<CampoDeportivo> campos;

  const CamposMapaView({super.key, required this.campos});

  /// Centro por defecto: Trinidad, departamento del Beni.
  static const LatLng centroTrinidad = LatLng(-14.8333, -64.9000);

  @override
  Widget build(BuildContext context) {
    final marcadores = campos
        .map((campo) => Marker(
              point: LatLng(campo.latitud, campo.longitud),
              width: 44,
              height: 44,
              child: Tooltip(
                message: campo.estaEnMantenimiento
                    ? '${campo.nombre} (en mantenimiento)'
                    : campo.nombre,
                child: Icon(
                  campo.estaEnMantenimiento
                      ? Icons.sports_soccer_outlined
                      : Icons.sports_soccer,
                  color: campo.estaEnMantenimiento ? Colors.orange : Colors.teal,
                  size: 44,
                ),
              ),
            ))
        .toList();

    return Stack(
      children: [
        FlutterMap(
          options: const MapOptions(
            initialCenter: centroTrinidad,
            initialZoom: 13,
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.gadbeni.camposdeportivos',
            ),
            MarkerLayer(markers: marcadores),
          ],
        ),
        // Atribución OSM (obligatoria por licencia ODbL, ver ADR-005)
        Positioned(
          bottom: 0,
          right: 0,
          child: Container(
            color: Colors.white70,
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            child: const Text(
              '© OpenStreetMap contributors',
              style: TextStyle(fontSize: 10),
            ),
          ),
        ),
      ],
    );
  }
}
