import 'package:flutter/material.dart';
import '../models/campo_deportivo.dart';
import '../models/piscina_disponibilidad.dart';
import '../services/piscina_service.dart';
import 'pago_qr_screen.dart';

/// Datos de contacto del solicitante y envío de la reserva de piscina.
///
/// A diferencia de campos (carrito multi-franja), la piscina se reserva por
/// un único turno con cantidades por perfil. Al confirmar se crea la reserva
/// y se pasa a la pantalla de pago, igual que en el flujo de campos.
class PiscinaDatosScreen extends StatefulWidget {
  final CampoDeportivo campo;
  final String fecha;
  final PiscinaTurno turno;

  /// Cantidad por perfil (perfil → personas). Solo incluye perfiles > 0.
  final Map<String, int> cantidades;

  const PiscinaDatosScreen({
    super.key,
    required this.campo,
    required this.fecha,
    required this.turno,
    required this.cantidades,
  });

  @override
  State<PiscinaDatosScreen> createState() => _PiscinaDatosScreenState();
}

class _PiscinaDatosScreenState extends State<PiscinaDatosScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nombre = TextEditingController();
  final _telefono = TextEditingController();
  final _ciNit = TextEditingController();
  bool _enviando = false;

  final _service = PiscinaService();

  @override
  void dispose() {
    _nombre.dispose();
    _telefono.dispose();
    _ciNit.dispose();
    super.dispose();
  }

  /// Perfiles elegidos con su precio, en el orden del turno.
  List<PiscinaPerfilTurno> get _perfilesElegidos => widget.turno.perfiles
      .where((p) => (widget.cantidades[p.perfil] ?? 0) > 0)
      .toList();

  double get _total => _perfilesElegidos.fold(
        0,
        (suma, p) => suma + p.precio * (widget.cantidades[p.perfil] ?? 0),
      );

  int get _totalPersonas =>
      widget.cantidades.values.fold(0, (suma, c) => suma + c);

  void _mostrar(String texto, {Color color = Colors.red}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(texto),
        backgroundColor: color,
        duration: const Duration(seconds: 4),
      ),
    );
  }

  Future<void> _enviar() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _enviando = true);

    try {
      final items = _perfilesElegidos
          .map((p) => PiscinaItemSeleccion(
                perfil: p.perfil,
                cantidad: widget.cantidades[p.perfil]!,
              ))
          .toList();

      final resultado = await _service.crearReserva(
        fecha: widget.fecha,
        turnoId: widget.turno.id,
        items: items,
        nombrePagador: _nombre.text.trim(),
        telefonoPagador: _telefono.text.trim(),
        ciNitPagador: _ciNit.text.trim(),
      );

      if (!mounted) return;

      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => PagoQrScreen(resultado: resultado)),
      );
    } on PiscinaReservaException catch (e) {
      if (!mounted) return;
      _mostrar(
        e.message,
        color: e.esCupoInsuficiente ? Colors.orange : Colors.red,
      );
    } catch (e) {
      if (!mounted) return;
      _mostrar('Error de conexión: $e');
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tus datos')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _ResumenSeleccion(
              campoNombre: widget.campo.nombre,
              fecha: widget.fecha,
              turno: widget.turno,
              perfiles: _perfilesElegidos,
              cantidades: widget.cantidades,
              total: _total,
              totalPersonas: _totalPersonas,
            ),
            const SizedBox(height: 24),
            const Text(
              'Datos del solicitante',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _nombre,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Nombre completo',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.person_outline),
              ),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'Ingresá tu nombre' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _telefono,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Teléfono',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.phone_outlined),
              ),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'Ingresá tu teléfono' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _ciNit,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'CI / NIT',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.badge_outlined),
              ),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'Ingresá tu CI o NIT' : null,
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _enviando ? null : _enviar,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.teal,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: _enviando
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text('Reservar y pagar · Bs ${_total.toStringAsFixed(2)}'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Resumen de la selección de piscina (campo, fecha, turno y perfiles).
class _ResumenSeleccion extends StatelessWidget {
  final String campoNombre;
  final String fecha;
  final PiscinaTurno turno;
  final List<PiscinaPerfilTurno> perfiles;
  final Map<String, int> cantidades;
  final double total;
  final int totalPersonas;

  const _ResumenSeleccion({
    required this.campoNombre,
    required this.fecha,
    required this.turno,
    required this.perfiles,
    required this.cantidades,
    required this.total,
    required this.totalPersonas,
  });

  String get _fechaCorta {
    final partes = fecha.split('-');
    return partes.length == 3 ? '${partes[2]}/${partes[1]}/${partes[0]}' : fecha;
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.pool, color: Colors.teal),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    campoNombre,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text('$_fechaCorta · ${turno.rango}'),
            const Divider(height: 24),
            ...perfiles.map(
              (p) => Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('${p.etiqueta} × ${cantidades[p.perfil] ?? 0}'),
                    Text(
                      'Bs ${(p.precio * (cantidades[p.perfil] ?? 0)).toStringAsFixed(2)}',
                    ),
                  ],
                ),
              ),
            ),
            const Divider(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('$totalPersonas persona(s)'),
                Text(
                  'Total: Bs ${total.toStringAsFixed(2)}',
                  style: const TextStyle(
                    color: Colors.green,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
