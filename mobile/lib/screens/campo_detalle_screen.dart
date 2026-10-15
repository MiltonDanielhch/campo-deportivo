import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/bloque_disponibilidad.dart';
import '../models/campo_deportivo.dart';
import '../models/franja_carrito.dart';
import '../models/piscina_disponibilidad.dart';
import '../providers/carrito_reserva_provider.dart';
import '../services/disponibilidad_service.dart';
import '../services/piscina_service.dart';
import 'carrito_resumen_screen.dart';
import 'piscina_datos_screen.dart';

/// Pantalla de detalle de un campo con selector de fecha (HU-C2) y
/// selección de franjas hacia el carrito global (HU-D1).
class CampoDetalleScreen extends StatefulWidget {
  final CampoDeportivo campo;

  const CampoDetalleScreen({super.key, required this.campo});

  @override
  State<CampoDetalleScreen> createState() => _CampoDetalleScreenState();
}

class _CampoDetalleScreenState extends State<CampoDetalleScreen> {
  final _service = DisponibilidadService();
  final _piscinaService = PiscinaService();
  late DateTime _fechaSeleccionada;

  Disponibilidad? _disponibilidad;
  bool _cargando = true;
  String? _error;
  Timer? _refreshTimer;

  // ─── Selección de piscina (un turno + cantidades por perfil) ───
  PiscinaDisponibilidad? _piscina;
  String? _turnoPiscinaId;
  Map<String, int> _cantidadesPiscina = {};

  bool get _esPiscina => widget.campo.esPiscina;

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
      if (_esPiscina) {
        final data = await _piscinaService.obtenerTurnos(
          widget.campo.id,
          _fechaStr,
        );
        if (mounted) {
          setState(() {
            _piscina = data;
            _cargando = false;
            _error = null;
            // Si el turno elegido ya no está, se limpia la selección.
            if (_turnoPiscinaId != null &&
                !data.turnos.any((t) => t.id == _turnoPiscinaId)) {
              _turnoPiscinaId = null;
              _cantidadesPiscina = {};
            }
          });
        }
        return;
      }

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
        // La selección de piscina es por turno del día: se reinicia.
        _turnoPiscinaId = null;
        _cantidadesPiscina = {};
      });
      _cargar(silencioso: false);
    }
  }

    /// Abre el selector de calendario completo (fechas fuera de los 14 días).
  Future<void> _abrirCalendario() async {
    final hoy = DateTime.now();
    final hoySinHora = DateTime(hoy.year, hoy.month, hoy.day);

    // Si la fecha actual es anterior a hoy (no debería pasar), usamos hoy
    final inicial = _fechaSeleccionada.isBefore(hoySinHora)
        ? hoySinHora
        : _fechaSeleccionada;

    final seleccion = await showDatePicker(
      context: context,
      initialDate: inicial,
      firstDate: hoySinHora,
      lastDate: DateTime(hoy.year + 1, hoy.month, hoy.day),
      helpText: 'Elegí una fecha',
      cancelText: 'Cancelar',
      confirmText: 'Aceptar',
      fieldLabelText: 'Fecha de reserva',
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: Theme.of(context).colorScheme.copyWith(
                  primary: Colors.teal,
                  onPrimary: Colors.white,
                ),
          ),
          child: child!,
        );
      },
    );

    if (seleccion != null) {
      _seleccionarFecha(seleccion);
    }
  }

  /// Último elemento de la fila de fechas:
  /// - Si la fecha activa está dentro de los 14 días: botón outline "Más fechas".
  /// - Si está fuera: chip teal con la fecha elegida (click reabre el calendario).
  Widget _buildBotonCalendario(List<DateTime> fechas) {
    final estaEnRango = fechas.any((f) => f == _fechaSeleccionada);

    if (!estaEnRango) {
      return InkWell(
        onTap: _abrirCalendario,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          width: 70,
          decoration: BoxDecoration(
            color: Colors.teal,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.teal, width: 2),
          ),
          child: Stack(
            children: [
              Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      _formatearFecha(_fechaSeleccionada),
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${_fechaSeleccionada.day}/${_fechaSeleccionada.month}',
                      style: const TextStyle(fontSize: 10, color: Colors.white70),
                    ),
                  ],
                ),
              ),
              const Positioned(
                top: 4,
                right: 4,
                child: Icon(Icons.calendar_month, size: 12, color: Colors.white70),
              ),
            ],
          ),
        ),
      );
    }

    return InkWell(
      onTap: _abrirCalendario,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 70,
        decoration: BoxDecoration(
          color: Colors.grey[200],
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.calendar_month, size: 20, color: Colors.grey[700]),
            const SizedBox(height: 2),
            const Text(
              'Más',
              style: TextStyle(fontSize: 10, color: Colors.black87),
            ),
            const Text(
              'fechas',
              style: TextStyle(fontSize: 10, color: Colors.black87),
            ),
          ],
        ),
      ),
    );
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
          // ─── Selector de fechas (scroll) + calendario FIJO a la derecha ───
          Container(
            height: 80,
            padding: const EdgeInsets.symmetric(vertical: 8),
            color: Colors.white,
            child: Row(
              children: [
                // Lista deslizable de 14 días
                Expanded(
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

                // Botón de calendario FIJO: siempre visible, fuera del scroll
                Padding(
                  padding: const EdgeInsets.only(left: 4, right: 8),
                  child: _buildBotonCalendario(fechas),
                ),
              ],
            ),
          ),

          const Divider(height: 1),

          Expanded(child: _construirGrilla()),

          // ─── Barra inferior: carrito (campos) o resumen (piscina) ───
          if (_esPiscina) ...[
            if (_turnoPiscinaId != null && _cantidadesPiscina.isNotEmpty)
              _BarraPiscina(
                totalPersonas: _totalPersonasPiscina,
                total: _totalPiscina,
                onContinuar: _continuarPiscina,
              ),
          ] else ...[
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
        ],
      ),
    );
  }

  PiscinaTurno? get _turnoPiscina {
    final piscina = _piscina;
    if (piscina == null || _turnoPiscinaId == null) return null;
    for (final t in piscina.turnos) {
      if (t.id == _turnoPiscinaId) return t;
    }
    return null;
  }

  int get _totalPersonasPiscina =>
      _cantidadesPiscina.values.fold(0, (suma, c) => suma + c);

  double get _totalPiscina {
    final turno = _turnoPiscina;
    if (turno == null) return 0;
    return turno.perfiles.fold(
      0,
      (suma, p) => suma + p.precio * (_cantidadesPiscina[p.perfil] ?? 0),
    );
  }

  void _continuarPiscina() {
    final turno = _turnoPiscina;
    if (turno == null) return;

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => PiscinaDatosScreen(
          campo: widget.campo,
          fecha: _fechaStr,
          turno: turno,
          cantidades: Map<String, int>.from(_cantidadesPiscina),
        ),
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

    if (_esPiscina) {
      return _construirTurnosPiscina();
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

  /// Grilla de turnos por perfil (piscina) con selección de turno y cantidades.
  Widget _construirTurnosPiscina() {
    final piscina = _piscina;
    if (piscina == null) {
      return const Center(child: Text('Sin datos'));
    }

    if (!piscina.abierto || piscina.turnos.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.pool, size: 64, color: Colors.grey[400]),
              const SizedBox(height: 16),
              const Text(
                'No hay turnos para esta fecha',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'Elegí otra fecha para ver los turnos disponibles',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.grey[600]),
              ),
            ],
          ),
        ),
      );
    }

    return _ListaTurnosPiscina(
      turnos: piscina.turnos,
      turnoSeleccionado: _turnoPiscinaId,
      cantidades: _cantidadesPiscina,
      onSeleccionarTurno: (turno) => setState(() {
        _turnoPiscinaId = turno.id;
        _cantidadesPiscina = {};
      }),
      onCambiarCantidad: (perfil, cantidad) => setState(() {
        if (cantidad <= 0) {
          _cantidadesPiscina.remove(perfil);
        } else {
          _cantidadesPiscina[perfil] = cantidad;
        }
      }),
    );
  }
}

/// Información pública del campo en la cabecera.
class _InfoCampo extends StatelessWidget {
  final CampoDeportivo campo;

  const _InfoCampo({required this.campo});

  /// Formatea "18:00:00" a "18:00"
  String _formatHora(String hhmmss) {
    return hhmmss.length >= 5 ? hhmmss.substring(0, 5) : hhmmss;
  }

  @override
  Widget build(BuildContext context) {
    final tieneAlgunaTarifa =
        campo.tarifas.diurna != null || campo.tarifas.nocturna != null;

    return Container(
      color: Colors.grey[100],
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // El tipo es opcional: un campo puede no tenerlo asignado.
          if (campo.tipoCampo != null) ...[
            Text(
              campo.tipoCampo!.nombre,
              style: const TextStyle(
                fontSize: 14,
                color: Colors.grey,
                fontStyle: FontStyle.italic,
              ),
            ),
            const SizedBox(height: 4),
          ],
          Row(
            children: [
              const Icon(Icons.location_on, size: 16),
              const SizedBox(width: 4),
              Expanded(child: Text(campo.direccion)),
            ],
          ),
          if (campo.esPiscina) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.cyan[50],
                border: Border.all(color: Colors.cyan[200]!),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  const Icon(Icons.pool, size: 18, color: Colors.cyan),
                  const SizedBox(width: 8),
                  const Expanded(
                    child: Text(
                      'Reserva por turno y perfil. Elegí un turno y la '
                      'cantidad de personas.',
                      style: TextStyle(fontSize: 12, color: Colors.black87),
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (tieneAlgunaTarifa) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                // Tarifa regular (diurna)
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.amber[50],
                      border: Border.all(color: Colors.amber[300]!),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.wb_sunny,
                                size: 16, color: Colors.amber),
                            const SizedBox(width: 4),
                            const Text(
                              'Regular',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                                color: Colors.amber,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        if (campo.tarifas.diurna != null) ...[
                          Text(
                            'Bs ${campo.tarifas.diurna!.precioPorHora.toStringAsFixed(2)}/h',
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: Colors.amber,
                            ),
                          ),
                          Text(
                            'Antes ${_formatHora(campo.horaInicioNoche)}',
                            style: TextStyle(
                              fontSize: 10,
                              color: Colors.amber[700],
                            ),
                          ),
                        ] else
                          const Text(
                            'Sin definir',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey,
                              fontStyle: FontStyle.italic,
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                // Tarifa con iluminación (nocturna)
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.indigo[50],
                      border: Border.all(color: Colors.indigo[300]!),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.lightbulb,
                                size: 16, color: Colors.indigo),
                            const SizedBox(width: 4),
                            const Text(
                              'Con luces',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                                color: Colors.indigo,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        if (campo.tarifas.nocturna != null) ...[
                          Text(
                            'Bs ${campo.tarifas.nocturna!.precioPorHora.toStringAsFixed(2)}/h',
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: Colors.indigo,
                            ),
                          ),
                          Text(
                            'Desde ${_formatHora(campo.horaInicioNoche)}',
                            style: TextStyle(
                              fontSize: 10,
                              color: Colors.indigo[700],
                            ),
                          ),
                        ] else
                          const Text(
                            'Sin definir',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey,
                              fontStyle: FontStyle.italic,
                            ),
                          ),
                      ],
                    ),
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

  Color _colorEstado(BloqueDisponibilidad bloque, bool seleccionado, bool esNocturno) {
    // 'pasada' primero: prioridad sobre todo (espeja backend + web).
    if (bloque.estaPasada) return Colors.grey[300]!;
    if (bloque.estaOcupada) return Colors.red[100]!;
    if (bloque.estaBloqueadaTemporal) return Colors.orange[100]!;
    if (seleccionado) return Colors.teal[100]!;
    return esNocturno ? Colors.indigo[50]! : Colors.amber[50]!;
  }

  Color _colorBorde(BloqueDisponibilidad bloque, bool seleccionado, bool esNocturno) {
    if (bloque.estaPasada) return Colors.grey[400]!;
    if (bloque.estaOcupada) return Colors.red;
    if (bloque.estaBloqueadaTemporal) return Colors.orange;
    if (seleccionado) return Colors.teal;
    return esNocturno ? Colors.indigo : Colors.amber;
  }

  IconData _iconoEstado(BloqueDisponibilidad bloque, bool esNocturno) {
    if (bloque.estaPasada) return Icons.schedule; // reloj, equivale al Clock del web
    if (bloque.estaOcupada) return Icons.lock;
    if (bloque.estaBloqueadaTemporal) return Icons.hourglass_empty;
    return esNocturno ? Icons.lightbulb : Icons.wb_sunny;
  }

  String _textoEstado(BloqueDisponibilidad bloque, bool esNocturno, double? precio) {
    if (bloque.estaPasada) return 'Pasada';
    if (bloque.estaOcupada) return 'Ocupada';
    if (bloque.estaBloqueadaTemporal) return 'En proceso';
    if (precio == null) return 'Sin tarifa';
    return 'Bs ${precio.toStringAsFixed(2)}';
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

        final esNocturno = campo.esHoraNocturna(bloque.horaInicio);
        final precio = campo.precioParaBloque(bloque.horaInicio);

        final franja = FranjaCarrito(
          campoId: campo.id,
          campoNombre: campo.nombre,
          fecha: fecha,
          horaInicio: bloque.horaInicio,
          horaFin: bloque.horaFin,
          precio: precio,
        );

        final seleccionado = carrito.esta(franja);

        // [FIX contrato] esSeleccionable (regla de oro del modelo) en vez de
        // estaLibre: un estado desconocido cae en no-seleccionable, no en
        // "parece libre". precio != null sigue siendo requisito extra.
        final esSeleccionable = bloque.esSeleccionable && precio != null;

        return InkWell(
          onTap: esSeleccionable ? () => carrito.toggle(franja) : null,
          borderRadius: BorderRadius.circular(8),
          child: Opacity(
            opacity: bloque.estaPasada ? 0.6 : 1.0,
            child: Container(
              decoration: BoxDecoration(
                color: _colorEstado(bloque, seleccionado, esNocturno),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: _colorBorde(bloque, seleccionado, esNocturno),
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
                      Icon(_iconoEstado(bloque, esNocturno),
                          size: 16, color: _colorBorde(bloque, seleccionado, esNocturno)),
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
                    _textoEstado(bloque, esNocturno, precio),
                    style: TextStyle(
                      fontSize: 11,
                      color: _colorBorde(bloque, seleccionado, esNocturno),
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

/// Lista de turnos de piscina. El turno elegido se expande para elegir
/// cantidades por perfil (mayores/menores/institución).
class _ListaTurnosPiscina extends StatelessWidget {
  final List<PiscinaTurno> turnos;
  final String? turnoSeleccionado;
  final Map<String, int> cantidades;
  final ValueChanged<PiscinaTurno> onSeleccionarTurno;
  final void Function(String perfil, int cantidad) onCambiarCantidad;

  const _ListaTurnosPiscina({
    required this.turnos,
    required this.turnoSeleccionado,
    required this.cantidades,
    required this.onSeleccionarTurno,
    required this.onCambiarCantidad,
  });

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: turnos.length,
      itemBuilder: (context, index) {
        final turno = turnos[index];
        final seleccionado = turno.id == turnoSeleccionado;

        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: BorderSide(
              color: seleccionado ? Colors.teal : Colors.grey.shade300,
              width: seleccionado ? 2 : 1,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              InkWell(
                onTap: () => onSeleccionarTurno(turno),
                borderRadius: BorderRadius.circular(12),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      Icon(
                        Icons.schedule,
                        color: seleccionado ? Colors.teal : Colors.grey[600],
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          turno.rango,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      Text(
                        'Cupo ${turno.cupoTotal}',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey[600],
                        ),
                      ),
                      const SizedBox(width: 4),
                      Icon(
                        seleccionado
                            ? Icons.check_circle
                            : Icons.radio_button_unchecked,
                        size: 20,
                        color: seleccionado ? Colors.teal : Colors.grey,
                      ),
                    ],
                  ),
                ),
              ),
              if (seleccionado)
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                  child: Column(
                    children: turno.perfiles
                        .map((p) => _FilaPerfil(
                              perfil: p,
                              cantidad: cantidades[p.perfil] ?? 0,
                              onCambiar: (c) => onCambiarCantidad(p.perfil, c),
                            ))
                        .toList(),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}

/// Fila de un perfil con su precio y un selector de cantidad.
class _FilaPerfil extends StatelessWidget {
  final PiscinaPerfilTurno perfil;
  final int cantidad;
  final ValueChanged<int> onCambiar;

  const _FilaPerfil({
    required this.perfil,
    required this.cantidad,
    required this.onCambiar,
  });

  @override
  Widget build(BuildContext context) {
    final sinCupo = !perfil.hayCupo;
    final menosActivo = cantidad > 0;
    final masActivo = cantidad < perfil.disponible;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  perfil.etiqueta,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                Text(
                  sinCupo
                      ? 'Sin cupo'
                      : 'Bs ${perfil.precio.toStringAsFixed(2)} · '
                          '${perfil.disponible} disponible(s)',
                  style: TextStyle(
                    fontSize: 12,
                    color: sinCupo ? Colors.red : Colors.grey[600],
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: menosActivo ? () => onCambiar(cantidad - 1) : null,
            icon: const Icon(Icons.remove_circle_outline),
            color: Colors.teal,
            tooltip: 'Quitar uno',
          ),
          SizedBox(
            width: 24,
            child: Text(
              '$cantidad',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          IconButton(
            onPressed: masActivo ? () => onCambiar(cantidad + 1) : null,
            icon: const Icon(Icons.add_circle_outline),
            color: Colors.teal,
            tooltip: 'Agregar uno',
          ),
        ],
      ),
    );
  }
}

/// Barra inferior con el resumen de la selección de piscina.
class _BarraPiscina extends StatelessWidget {
  final int totalPersonas;
  final double total;
  final VoidCallback onContinuar;

  const _BarraPiscina({
    required this.totalPersonas,
    required this.total,
    required this.onContinuar,
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
                  '$totalPersonas persona(s) seleccionada(s)',
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
            onPressed: onContinuar,
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
