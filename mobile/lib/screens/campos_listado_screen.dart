import 'package:flutter/material.dart';
import '../models/campo_deportivo.dart';
import '../services/campos_service.dart';
import 'campo_detalle_screen.dart';
import 'campos_mapa_screen.dart';
import 'consultar_estado_screen.dart';

enum ModoVista { lista, mapa }

/// Pantalla principal pública: listado y mapa de campos deportivos.
/// Un toggle en la parte superior alterna entre ambas vistas (HU-C1).
class CamposListadoScreen extends StatefulWidget {
  const CamposListadoScreen({super.key});

  @override
  State<CamposListadoScreen> createState() => _CamposListadoScreenState();
}

class _CamposListadoScreenState extends State<CamposListadoScreen> {
  final _service = CamposService();
  late Future<List<CampoDeportivo>> _camposFuture;
  ModoVista _modo = ModoVista.lista;

  @override
  void initState() {
    super.initState();
    _camposFuture = _service.listarCampos();
  }

  void _recargar() {
    setState(() {
      _camposFuture = _service.listarCampos();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Campos Deportivos'),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.receipt_long),
            tooltip: 'Consultar estado de mi reserva',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const ConsultarEstadoScreen()),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: SegmentedButton<ModoVista>(
              segments: const [
                ButtonSegment(
                  value: ModoVista.lista,
                  label: Text('Lista'),
                  icon: Icon(Icons.list),
                ),
                ButtonSegment(
                  value: ModoVista.mapa,
                  label: Text('Mapa'),
                  icon: Icon(Icons.map),
                ),
              ],
              selected: {_modo},
              onSelectionChanged: (nuevo) {
                setState(() => _modo = nuevo.first);
              },
            ),
          ),
          Expanded(
            child: FutureBuilder<List<CampoDeportivo>>(
              future: _camposFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (snapshot.hasError) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.error_outline,
                              size: 64, color: Colors.red),
                          const SizedBox(height: 16),
                          Text('Error al cargar campos',
                              style: Theme.of(context).textTheme.titleLarge),
                          const SizedBox(height: 8),
                          Text(snapshot.error.toString(),
                              textAlign: TextAlign.center,
                              style: const TextStyle(color: Colors.grey)),
                          const SizedBox(height: 16),
                          ElevatedButton(
                            onPressed: _recargar,
                            child: const Text('Reintentar'),
                          ),
                        ],
                      ),
                    ),
                  );
                }

                final campos = snapshot.data ?? [];
                if (campos.isEmpty) {
                  return const Center(
                    child: Text('No hay campos disponibles',
                        style: TextStyle(fontSize: 18, color: Colors.grey)),
                  );
                }

                if (_modo == ModoVista.mapa) {
                  return CamposMapaView(campos: campos);
                }

                return RefreshIndicator(
                  onRefresh: () async => _recargar(),
                  child: ListView.builder(
                    itemCount: campos.length,
                    itemBuilder: (context, index) {
                      return _CampoTarjeta(campo: campos[index]);
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// Tarjeta de un campo deportivo con información pública.
class _CampoTarjeta extends StatelessWidget {
  final CampoDeportivo campo;

  const _CampoTarjeta({required this.campo});

  @override
  Widget build(BuildContext context) {
    final esMantenimiento = campo.estaEnMantenimiento;

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      elevation: esMantenimiento ? 1 : 2,
      color: esMantenimiento ? Colors.grey[100] : null,
      clipBehavior: Clip.antiAlias, // ← para que la foto respete las esquinas redondeadas
      child: InkWell(
        onTap: esMantenimiento
            ? null
            : () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => CampoDetalleScreen(campo: campo),
                  ),
                );
              },
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            // ─── Foto del campo o placeholder ───
            _ImagenCampo(campo: campo),

            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          campo.nombre,
                          style: const TextStyle(
                              fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                      ),
                      if (esMantenimiento)
                        const Chip(
                          label: Text('Mantenimiento'),
                          backgroundColor: Colors.orange,
                          labelStyle:
                              TextStyle(color: Colors.white, fontSize: 12),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    campo.tipoCampo.nombre,
                    style: TextStyle(
                        color: Colors.grey[600],
                        fontSize: 14,
                        fontStyle: FontStyle.italic),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(Icons.location_on, size: 16, color: Colors.grey[600]),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(campo.direccion,
                            style: const TextStyle(fontSize: 14)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  // ─── Tarifas: regular (☀️) y con iluminación (💡) ───
                  if (campo.tarifas.diurna != null ||
                      campo.tarifas.nocturna != null) ...[
                    if (campo.tarifas.diurna != null)
                      Row(
                        children: [
                          const Icon(Icons.wb_sunny,
                              size: 16, color: Colors.amber),
                          const SizedBox(width: 4),
                          Text(
                            'Bs ${campo.tarifas.diurna!.precioPorHora.toStringAsFixed(2)}/h · Regular',
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                              color: Colors.amber,
                            ),
                          ),
                        ],
                      ),
                    if (campo.tarifas.nocturna != null) ...[
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(Icons.lightbulb,
                              size: 16, color: Colors.indigo),
                          const SizedBox(width: 4),
                          Text(
                            'Bs ${campo.tarifas.nocturna!.precioPorHora.toStringAsFixed(2)}/h · Con iluminación',
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                              color: Colors.indigo,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ] else
                    Row(
                      children: [
                        Icon(Icons.payments, size: 20, color: Colors.grey[600]),
                        const SizedBox(width: 4),
                        Text(
                          'Sin tarifa definida',
                          style:
                              TextStyle(fontSize: 14, color: Colors.grey[600]),
                        ),
                      ],
                    ),
                  if (esMantenimiento) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Este campo no está disponible temporalmente',
                      style: TextStyle(
                          fontSize: 12,
                          color: Colors.orange[700],
                          fontStyle: FontStyle.italic),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Foto del campo en la parte superior de la tarjeta (16:9 aprox).
/// Muestra spinner mientras carga y placeholder si no hay foto o falla.
class _ImagenCampo extends StatelessWidget {
  final CampoDeportivo campo;

  const _ImagenCampo({required this.campo});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 150,
      width: double.infinity,
      child: campo.imagenUrl != null
          ? Image.network(
              campo.imagenUrlProxy!,
              fit: BoxFit.cover,
              loadingBuilder: (context, child, progress) {
                if (progress == null) return child;
                return Container(
                  color: Colors.grey[200],
                  child: const Center(
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                );
              },
              errorBuilder: (context, error, stackTrace) =>
                  const _PlaceholderImagen(),
            )
          : const _PlaceholderImagen(),
    );
  }
}

/// Placeholder gris con icono cuando el campo no tiene foto.
class _PlaceholderImagen extends StatelessWidget {
  const _PlaceholderImagen();

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.grey[200],
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.image_outlined, size: 40, color: Colors.grey[400]),
            const SizedBox(height: 4),
            Text(
              'Sin foto',
              style: TextStyle(fontSize: 12, color: Colors.grey[500]),
            ),
          ],
        ),
      ),
    );
  }
}
