import 'dart:async';

import 'package:flutter/material.dart';
import '../models/bloque_disponibilidad.dart';
import '../models/campo_deportivo.dart';
import '../services/disponibilidad_service.dart';

/// Pantalla de detalle de un campo con selector de fecha (HU-C2).
///
/// Muestra la información pública del campo y permite consultar la
/// disponibilidad horaria para fechas dentro de la ventana válida
/// (hoy hasta +60 días, misma validación que el backend).
///
/// La grilla se refresca automáticamente cada 45 segundos mientras la
/// pantalla está abierta. Esto NO es el Active Polling de HU-D5: aquel
/// es el backend consultando al Core de Recaudaciones; este es la app
/// refrescando su propia vista contra el backend de Canchas.
///
/// El estado de selección de bloques se mantiene localmente para
/// preparar el flujo de reserva (se conecta en el módulo siguiente).
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

  // Estado preparado para el flujo de reserva (Épica D)
  final Set<BloqueSeleccionado> _bloquesSeleccionados = {};

  @override
  void initState() {
    super.initState();
    _fechaSeleccionada = DateTime.now();
    _cargar(silencioso: false);
    // Refresco automático de la grilla cada 45 segundos
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

  /// Carga la disponibilidad. En modo silencioso (refresco automático)
  /// no muestra spinner ni limpia la selección; si falla, conserva la
  /// última grilla conocida.
  Future<void> _cargar({required bool silencioso}) async {
    if (!silencioso) {
      setState(() {
        _cargando = true;
        _error = null;
        _bloquesSeleccionados.clear();
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

  void _seleccionarFecha(DateTime fecha) {
    if (fecha != _fechaSeleccionada) {
      setState(() {
        _fechaSeleccionada = fecha;
      });
      _cargar(silencioso: false);
    }
  }

  void _toggleBloque(BloqueDisponibilidad bloque) {
    setState(() {
      final seleccion = BloqueSeleccionado(
        horaInicio: bloque.horaInicio,
        horaFin: bloque.horaFin,
        precio: bloque.precio,
      );

      if (_bloquesSeleccionados.contains(seleccion)) {
        _bloquesSeleccionados.remove(seleccion);
      } else {
        _bloquesSeleccionados.add(seleccion);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final fechas = _generarFechas(14);

    return Scaffold(
      appBar: AppBar(title: Text(widget.campo.nombre)),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // ─── Información del campo ───
          Container(
            color: Colors.grey[100],
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.campo.tipoCampo.nombre,
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
                    Expanded(child: Text(widget.campo.direccion)),
                  ],
                ),
                if (widget.campo.tarifaVigente != null) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.payments, size: 20, color: Colors.green),
                      Text(
                        'Bs ${widget.campo.tarifaVigente!.precioPorHora.toStringAsFixed(2)} por hora',
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
          ),

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

          // ─── Grilla de disponibilidad ───
          Expanded(child: _construirGrilla()),

          // ─── Barra inferior con bloques seleccionados ───
          if (_bloquesSeleccionados.isNotEmpty)
            _BarraSeleccionados(
              seleccionados: _bloquesSeleccionados,
              onConfirmar: () {
                // PLACEHOLDER: aquí se conectará el flujo de reserva
                // en el módulo siguiente (Épica D)
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      '${_bloquesSeleccionados.length} bloque(s) seleccionado(s). '
                      'Flujo de reserva pendiente (Módulo 4).',
                    ),
                    duration: const Duration(seconds: 2),
                  ),
                );
              },
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

    // El campo no abre ese día (sin horario_atencion)
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
      bloquesSeleccionados: _bloquesSeleccionados,
      onToggleBloque: _toggleBloque,
    );
  }
}

/// Modelo simple para un bloque seleccionado (hora de inicio es la clave única).
class BloqueSeleccionado {
  final String horaInicio;
  final String horaFin;
  final double? precio;

  const BloqueSeleccionado({
    required this.horaInicio,
    required this.horaFin,
    this.precio,
  });

  @override
  bool operator ==(Object other) =>
      other is BloqueSeleccionado && other.horaInicio == horaInicio;

  @override
  int get hashCode => horaInicio.hashCode;
}

/// Widget de grilla horaria con los 3 estados de bloque.
class _GrillaHoraria extends StatelessWidget {
  final List<BloqueDisponibilidad> bloques;
  final Set<BloqueSeleccionado> bloquesSeleccionados;
  final void Function(BloqueDisponibilidad) onToggleBloque;

  const _GrillaHoraria({
    required this.bloques,
    required this.bloquesSeleccionados,
    required this.onToggleBloque,
  });

  Color _colorEstado(BloqueDisponibilidad bloque) {
    if (bloque.estaOcupada) return Colors.red[100]!;
    if (bloque.estaBloqueadaTemporal) return Colors.orange[100]!;
    final seleccionado =
        bloquesSeleccionados.any((s) => s.horaInicio == bloque.horaInicio);
    return seleccionado ? Colors.teal[100]! : Colors.green[100]!;
  }

  Color _colorBorde(BloqueDisponibilidad bloque) {
    if (bloque.estaOcupada) return Colors.red;
    if (bloque.estaBloqueadaTemporal) return Colors.orange;
    final seleccionado =
        bloquesSeleccionados.any((s) => s.horaInicio == bloque.horaInicio);
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
        final esLibre = bloque.estaLibre;
        final seleccionado =
            bloquesSeleccionados.any((s) => s.horaInicio == bloque.horaInicio);

        return InkWell(
          onTap: esLibre ? () => onToggleBloque(bloque) : null,
          borderRadius: BorderRadius.circular(8),
          child: Container(
            decoration: BoxDecoration(
              color: _colorEstado(bloque),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: _colorBorde(bloque), width: 2),
            ),
            padding: const EdgeInsets.all(8),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(_iconoEstado(bloque),
                        size: 16, color: _colorBorde(bloque)),
                    const SizedBox(width: 4),
                    Text(
                      '${bloque.horaInicio} - ${bloque.horaFin}',
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
                    color: _colorBorde(bloque),
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

/// Barra inferior que muestra los bloques seleccionados y botón de confirmar.
class _BarraSeleccionados extends StatelessWidget {
  final Set<BloqueSeleccionado> seleccionados;
  final VoidCallback onConfirmar;

  const _BarraSeleccionados({
    required this.seleccionados,
    required this.onConfirmar,
  });

  @override
  Widget build(BuildContext context) {
    final total = seleccionados.fold<double>(
      0,
      (suma, b) => suma + (b.precio ?? 0),
    );

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
                  '${seleccionados.length} bloque(s) seleccionado(s)',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                Text(
                  'Total: Bs ${total.toStringAsFixed(2)}',
                  style: const TextStyle(color: Colors.green),
                ),
              ],
            ),
          ),
          ElevatedButton(
            onPressed: onConfirmar,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.teal,
              foregroundColor: Colors.white,
            ),
            child: const Text('Continuar'),
          ),
        ],
      ),
    );
  }
}
