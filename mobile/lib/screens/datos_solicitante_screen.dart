import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/carrito_reserva_provider.dart';
import '../services/solicitud_reserva_service.dart';
import 'pago_qr_screen.dart';

/// Datos de contacto del solicitante y envío de la solicitud (HU-D1).
///
/// Manejo de errores diferenciado:
///  • 409 → la franja en conflicto se quita del carrito y se invita a revisar
///  • 503 → mensaje de "intenta más tarde", el carrito queda INTACTO
class DatosSolicitanteScreen extends StatefulWidget {
  const DatosSolicitanteScreen({super.key});

  @override
  State<DatosSolicitanteScreen> createState() => _DatosSolicitanteScreenState();
}

class _DatosSolicitanteScreenState extends State<DatosSolicitanteScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nombre = TextEditingController();
  final _telefono = TextEditingController();
  final _ciNit = TextEditingController();
  bool _enviando = false;

  @override
  void dispose() {
    _nombre.dispose();
    _telefono.dispose();
    _ciNit.dispose();
    super.dispose();
  }

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

    final carrito = context.read<CarritoReservaProvider>();

    setState(() => _enviando = true);

    try {
      final resultado = await SolicitudReservaService().crearSolicitud(
        nombrePagador: _nombre.text.trim(),
        telefonoPagador: _telefono.text.trim(),
        ciNitPagador:
            _ciNit.text.trim().isEmpty ? null : _ciNit.text.trim(),
        franjas: carrito.franjas,
      );

      if (!mounted) return;

      // Las franjas ya quedaron bloqueadas como 'pendiente':
      // el carrito se vacía y pasamos directo a la pantalla de cobro.
      carrito.limpiar();
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => PagoQrScreen(resultado: resultado),
        ),
      );
    } on SolicitudReservaException catch (e) {
      if (!mounted) return;

      if (e.esFranjaNoDisponible) {
        final f = e.franja;
        if (f != null) {
          final key = '${f['campo_id']}|${f['fecha']}|${f['hora_inicio']}';
          carrito.quitarPorKey(key);
          _mostrar(
            'Una franja ya no estaba disponible y la quitamos del carrito. '
            'Revisa el resto o elige otra.',
            color: Colors.orange,
          );
          if (carrito.estaVacio && mounted) {
            Navigator.of(context).pop();
          }
        } else {
          _mostrar(e.message, color: Colors.orange);
        }
      } else if (e.esCobroNoDisponible) {
        // HU-D8: el carrito queda intacto, el ciudadano solo reintenta
        _mostrar(e.message, color: Colors.orange);
      } else {
        _mostrar(e.message);
      }
    } catch (e) {
      if (!mounted) return;
      _mostrar('Error de conexión: $e');
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final carrito = context.watch<CarritoReservaProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('Tus datos')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              '${carrito.cantidad} franja(s) · Bs ${carrito.totalEstimado.toStringAsFixed(2)} estimado',
              style: const TextStyle(color: Colors.grey),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _nombre,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Nombre completo *',
                border: OutlineInputBorder(),
              ),
              validator: (v) {
                final texto = (v ?? '').trim();
                if (texto.isEmpty) return 'Ingresa tu nombre';
                if (texto.length < 3) return 'Nombre demasiado corto';
                return null;
              },
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _telefono,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Teléfono / celular *',
                hintText: 'Ej: 70000000',
                border: OutlineInputBorder(),
              ),
              validator: (v) {
                final texto = (v ?? '').trim();
                if (texto.isEmpty) return 'Ingresa tu teléfono';
                if (!RegExp(r'^[0-9]{7,8}$').hasMatch(texto)) {
                  return 'Debe tener 7 u 8 dígitos numéricos';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _ciNit,
              decoration: const InputDecoration(
                labelText: 'CI / NIT (opcional)',
                border: OutlineInputBorder(),
              ),
              validator: (v) {
                final texto = (v ?? '').trim();
                if (texto.length > 20) return 'Máximo 20 caracteres';
                return null;
              },
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
                  : const Text('Confirmar reserva'),
            ),
          ],
        ),
      ),
    );
  }
}
