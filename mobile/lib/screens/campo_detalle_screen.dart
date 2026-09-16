import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/bloque_disponibilidad.dart';
import '../models/campo_deportivo.dart';
import '../models/franja_carrito.dart';
import '../providers/carrito_reserva_provider.dart';
import '../services/disponibilidad_service.dart';
import 'carrito_resumen_screen.dart';

/// Pantalla de detalle de un campo con selector de fecha (HU-C2) y
/// selección de franjas hacia el carrito global (HU-D1).
///
/// La grilla se refresca cada 45s. El carrito NO se limpia al cambiar
/// de fecha ni al refrescar: las franjas pueden ser de fechas y campos
/// distintos (multi-franja).
class CampoDetalleScreen extends StatefulWidget {
  final CampoDeportivo campo;

  const CampoDetalleScreen({super.key, required this.campo});

  @override
  State<CampoDetalleScreen> createState() => _CampoDetalleScreenState();
}

class _CampoDetalleScreenState extends State<CampoDetalleScreen> {
  final _service = DisponibilidadService();
  late DateTime _fechaSeleccionada;

  Disponibilidad? _disponibilidad;
  bool _cargando = true;
  String? _error;
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    _fechaSeleccionada = DateTime.now();
    _cargar(silencioso: false);
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 45),
      (_) => _cargar(silencioso: true),
    );
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _cargar({required bool silencioso}) async {
    if (!silencioso) {
      setState(() {
        _cargando = true;
        _error = null;
      });
    }

    try {
      final data = await _service.obtenerDisponibilidad(
        widget.campo.id,
        _fechaSeleccionada,
      );
      if (mounted) {
        setState(() {
          _disponibilidad = data;
          _cargando = false;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _cargando = false;
          if (!silencioso) _error = e.toString();
        });
      }
    }
  }

  List<DateTime> _generarFechas(int cantidad) {
    final hoy = DateTime.now();
    return List.generate(
      cantidad,
      (i) => DateTime(hoy.year, hoy.month, hoy.day).add(Duration(days: i)),
    );
  }

  String _formatearFecha(DateTime fecha) {
    const dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    final hoy = DateTime.now();
    final manana = hoy.add(const Duration(days: 1));

    if (fecha.year == hoy.year &&
        fecha.month == hoy.month &&
        fecha.day == hoy.day) {
      return 'Hoy';
    } else if (fecha.year == manana.year &&
        fecha.month == manana.month &&
        fecha.day == manana.day) {
      return 'Mañana';
    }

    final dia = dias[fecha.weekday - 1];
    return '$dia ${fecha.day}/${fecha.month}';
  }

  String get _fechaStr {
    final f = _fechaSeleccionada;
    return '${f.year.toString().padLeft(4, '0')}-${f.month.toString().padLeft(2, '0')}-${f.day.toString().padLeft(2, '0')}';
  }

  void _seleccionarFecha(DateTime fecha) {
    if (fecha != _fechaSeleccionada) {
      setState(() {
        _fechaSeleccionada = fecha;
      });
      _cargar(silencioso: false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final fechas = _generarFechas(14);
    final carrito = context.watch<CarritoReservaProvider>();

    return Scaffold(
      appBar: AppBar(title: Text(widget.campo.nombre)),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _InfoCampo(campo: widget.campo),

          // ─── Selector de fechas ───
          Container(
            height: 80,
            padding: const EdgeInsets.symmetric(vertical: 8),
            color: Colors.white,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 8),
              itemCount: fechas.length,
              separatorBuilder: (_, _) => const SizedBox(width: 4),
              itemBuilder: (context, index) {
                final fecha = fechas[index];
                final esSeleccionada = fecha == _fechaSeleccionada;

                return InkWell(
                  onTap: () => _seleccionarFecha(fecha),
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    width: 70,
                    decoration: BoxDecoration(
                      color: esSeleccionada ? Colors.teal : Colors.grey[200],
                      borderRadius: BorderRadius.circular(12),
                      border: esSeleccionada
                          ? Border.all(color: Colors.teal, width: 2)
                          : null,
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          _formatearFecha(fecha),
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: esSeleccionada ? Colors.white : Colors.black,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${fecha.day}/${fecha.month}',
                          style: TextStyle(
                            fontSize: 10,
                            color: esSeleccionada
                                ? Colors.white70
                                : Colors.grey[600],
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),

          const Divider(height: 1),

          Expanded(child: _construirGrilla()),

          // ─── Barra del carrito global ───
          if (!carrito.estaVacio)
            _BarraCarrito(
              cantidad: carrito.cantidad,
              total: carrito.totalEstimado,
              onVer: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const CarritoResumenScreen(),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _construirGrilla() {
    if (_cargando) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 8),
              Text('Error: $_error', textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: () => _cargar(silencioso: false),
                icon: const Icon(Icons.refresh),
                label: const Text('Reintentar'),
              ),
            ],
          ),
        ),
      );
    }

    final disponibilidad = _disponibilidad;
    if (disponibilidad == null) {
      return const Center(child: Text('Sin datos'));
    }

    if (!disponibilidad.abierto) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.event_busy, size: 64, color: Colors.grey[400]),
              const SizedBox(height: 16),
              const Text(
                'Este campo no abre en esta fecha',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'No hay horarios de atención definidos para este día',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.grey[600]),
              ),
            ],
          ),
        ),
      );
    }

    if (disponibilidad.bloques.isEmpty) {
      return const Center(child: Text('No hay horarios disponibles'));
    }

    return _GrillaHoraria(
      bloques: disponibilidad.bloques,
      fecha: _fechaStr,
      campo: widget.campo,
    );
  }
}

/// Información pública del campo en la cabecera.
class _InfoCampo extends StatelessWidget {
  final CampoDeportivo campo;

  const _InfoCampo({required this.campo});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.grey[100],
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            campo.tipoCampo.nombre,
            style: const TextStyle(
              fontSize: 14,
              color: Colors.grey,
              fontStyle: FontStyle.italic,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.location_on, size: 16),
              const SizedBox(width: 4),
              Expanded(child: Text(campo.direccion)),
            ],
          ),
          if (campo.tarifaVigente != null) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.payments, size: 20, color: Colors.green),
                Text(
                  'Bs ${campo.tarifaVigente!.precioPorHora.toStringAsFixed(2)} por hora',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: Colors.green,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// Grilla horaria: los bloques 'libre' se agregan/quitan del carrito global.
class _GrillaHoraria extends StatelessWidget {
  final List<BloqueDisponibilidad> bloques;
  final String fecha;
  final CampoDeportivo campo;

  const _GrillaHoraria({
    required this.bloques,
    required this.fecha,
    required this.campo,
  });

  Color _colorEstado(BloqueDisponibilidad bloque, bool seleccionado) {
    if (bloque.estaOcupada) return Colors.red[100]!;
    if (bloque.estaBloqueadaTemporal) return Colors.orange[100]!;
    return seleccionado ? Colors.teal[100]! : Colors.green[100]!;
  }

  Color _colorBorde(BloqueDisponibilidad bloque, bool seleccionado) {
    if (bloque.estaOcupada) return Colors.red;
    if (bloque.estaBloqueadaTemporal) return Colors.orange;
    return seleccionado ? Colors.teal : Colors.green;
  }

  IconData _iconoEstado(BloqueDisponibilidad bloque) {
    if (bloque.estaOcupada) return Icons.lock;
    if (bloque.estaBloqueadaTemporal) return Icons.hourglass_empty;
    return Icons.check_circle;
  }

  String _textoEstado(BloqueDisponibilidad bloque) {
    if (bloque.estaOcupada) return 'Ocupada';
    if (bloque.estaBloqueadaTemporal) return 'En proceso de cobro';
    return 'Libre';
  }

  @override
  Widget build(BuildContext context) {
    final carrito = context.watch<CarritoReservaProvider>();

    return GridView.builder(
      padding: const EdgeInsets.all(12),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 2.5,
        crossAxisSpacing: 8,
        mainAxisSpacing: 8,
      ),
      itemCount: bloques.length,
      itemBuilder: (context, index) {
        final bloque = bloques[index];

        final franja = FranjaCarrito(
          campoId: campo.id,
          campoNombre: campo.nombre,
          fecha: fecha,
          horaInicio: bloque.horaInicio,
          horaFin: bloque.horaFin,
          precio: bloque.precio,
        );

        final seleccionado = carrito.esta(franja);
        final esLibre = bloque.estaLibre;

        return InkWell(
          onTap: esLibre ? () => carrito.toggle(franja) : null,
          borderRadius: BorderRadius.circular(8),
          child: Container(
            decoration: BoxDecoration(
              color: _colorEstado(bloque, seleccionado),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(
                color: _colorBorde(bloque, seleccionado),
                width: 2,
              ),
            ),
            padding: const EdgeInsets.all(8),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(_iconoEstado(bloque),
                        size: 16, color: _colorBorde(bloque, seleccionado)),
                    const SizedBox(width: 4),
                    Text(
                      '${bloque.horaInicio.substring(0, 5)} - ${bloque.horaFin.substring(0, 5)}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  _textoEstado(bloque),
                  style: TextStyle(
                    fontSize: 11,
                    color: _colorBorde(bloque, seleccionado),
                    fontWeight: FontWeight.w500,
                  ),
                ),
                if (seleccionado) ...[
                  const SizedBox(height: 2),
                  const Icon(Icons.check, size: 16, color: Colors.teal),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Barra inferior con el estado del carrito global y acceso al resumen.
class _BarraCarrito extends StatelessWidget {
  final int cantidad;
  final double total;
  final VoidCallback onVer;

  const _BarraCarrito({
    required this.cantidad,
    required this.total,
    required this.onVer,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 4,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$cantidad franja(s) seleccionada(s)',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                Text(
                  'Total estimado: Bs ${total.toStringAsFixed(2)}',
                  style: const TextStyle(color: Colors.green),
                ),
              ],
            ),
          ),
          ElevatedButton(
            onPressed: onVer,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.teal,
              foregroundColor: Colors.white,
            ),
            child: const Text('Ver carrito'),
          ),
        ],
      ),
    );
  }
}
