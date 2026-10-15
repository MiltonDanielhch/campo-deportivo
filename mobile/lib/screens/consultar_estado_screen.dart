import 'package:flutter/material.dart';
import '../models/estado_solicitud.dart';
import '../models/reserva_creada.dart';
import '../services/estado_solicitud_service.dart';
import 'comprobante_screen.dart';
import 'pago_qr_screen.dart';

/// Consulta manual del estado de una solicitud por código (HU-D7).
/// Accesible desde la pantalla principal en cualquier momento.
class ConsultarEstadoScreen extends StatefulWidget {
  const ConsultarEstadoScreen({super.key});

  @override
  State<ConsultarEstadoScreen> createState() => _ConsultarEstadoScreenState();
}

class _ConsultarEstadoScreenState extends State<ConsultarEstadoScreen> {
  final _controller = TextEditingController();
  final _service = EstadoSolicitudService();

  bool _cargando = false;
  EstadoSolicitud? _estado;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _consultar() async {
    final codigo = _controller.text.trim();
    if (codigo.isEmpty) return;

    setState(() {
      _cargando = true;
      _estado = null;
      _error = null;
    });

    try {
      final estado = await _service.consultar(codigo);
      if (!mounted) return;
      setState(() {
        _cargando = false;
        _estado = estado;
      });
    } on EstadoSolicitudNoEncontradaException {
      if (!mounted) return;
      setState(() {
        _cargando = false;
        _error = 'No encontramos ninguna reserva con ese dato.';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _cargando = false;
        _error = 'Error de conexión: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Consultar mi reserva')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text(
            'Consultá tu reserva',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 4),
          const Text(
            'Ingresá tu código de seguimiento (RES-… / PIS-…), el código de '
            'pago en ventanilla del Banco Unión, tu CI/NIT o tu teléfono.',
            style: TextStyle(fontSize: 12, color: Colors.grey),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _controller,
            textCapitalization: TextCapitalization.characters,
            decoration: const InputDecoration(
              hintText: 'RES-… / PIS-… / K699-… / CI / teléfono',
              border: OutlineInputBorder(),
              prefixIcon: Icon(Icons.search),
            ),
            onSubmitted: (_) => _consultar(),
          ),
          const SizedBox(height: 12),
          ElevatedButton(
            onPressed: _cargando ? null : _consultar,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.teal,
              foregroundColor: Colors.white,
            ),
            child: _cargando
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Consultar'),
          ),
          const SizedBox(height: 24),
          if (_error != null)
            Card(
              color: Colors.red.shade50,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Text(_error!, style: const TextStyle(color: Colors.red)),
              ),
            ),
          if (_estado != null) _vistaEstado(_estado!),
        ],
      ),
    );
  }

  Widget _vistaEstado(EstadoSolicitud estado) {
    if (estado.estaConfirmada) {
      return Card(
        child: ListTile(
          leading: Icon(
            estado.esPiscina ? Icons.pool : Icons.check_circle,
            color: Colors.green,
          ),
          title: Text(
            estado.esPiscina ? 'Reserva de piscina confirmada' : 'Reserva confirmada',
          ),
          subtitle: Text('${estado.reservas.length} reserva(s) generada(s)'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => ComprobanteScreen(estado: estado)),
          ),
        ),
      );
    }

    if (estado.estaExpirada) {
      return const Card(
        child: ListTile(
          leading: Icon(Icons.timer_off, color: Colors.red),
          title: Text('Solicitud expirada'),
          subtitle: Text('El tiempo para pagar venció. Puedes volver a reservar.'),
        ),
      );
    }

    if (estado.estaRechazada) {
      return const Card(
        child: ListTile(
          leading: Icon(Icons.error_outline, color: Colors.orange),
          title: Text('Pago rechazado'),
          subtitle: Text('El cobro no pudo procesarse. Intenta nuevamente.'),
        ),
      );
    }

    if (estado.estado == 'cancelada') {
      return const Card(
        child: ListTile(
          leading: Icon(Icons.cancel, color: Colors.grey),
          title: Text('Solicitud cancelada'),
        ),
      );
    }

    // pendiente: se puede reanudar el pago con el mismo QR/código.
    return Card(
      child: ListTile(
        leading: const Icon(Icons.hourglass_empty, color: Colors.teal),
        title: const Text('Pendiente de pago'),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Código: ${estado.codigoSeguimiento}'),
            Text('Monto: Bs ${estado.montoTotal.toStringAsFixed(2)}'),
          ],
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => PagoQrScreen(
              resultado: ReservaDesdeEstado(
                codigoSeguimiento: estado.codigoSeguimiento,
                montoTotal: estado.montoTotal,
                expiraEn: estado.expiraEn ?? '',
                qrString: estado.qrString,
                // Sin QR en el payload: la pantalla de pago lo espera por
                // polling (mismo comportamiento que la creación de piscina).
                qrDiferido: estado.qrString == null,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
