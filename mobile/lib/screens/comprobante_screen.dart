import 'package:flutter/material.dart';
import '../models/estado_solicitud.dart';

/// Comprobante digital de una reserva confirmada (HU-D6).
/// Muestra el código de seguimiento y cada reserva generada con su
/// código, campo, fecha y horario.
class ComprobanteScreen extends StatelessWidget {
  final EstadoSolicitud estado;

  const ComprobanteScreen({super.key, required this.estado});

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          Navigator.of(context).popUntil((route) => route.isFirst);
        }
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Comprobante de reserva')),
        body: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Center(
              child: Column(
                children: [
                  const Icon(Icons.check_circle, size: 72, color: Colors.green),
                  const SizedBox(height: 12),
                  const Text(
                    '¡Reserva confirmada!',
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  SelectableText(
                    'Código: ${estado.codigoSeguimiento}',
                    style: const TextStyle(fontFamily: 'monospace', fontSize: 16),
                  ),
                  Text(
                    'Monto pagado: Bs ${estado.montoTotal.toStringAsFixed(2)}',
                    style: const TextStyle(
                      color: Colors.green,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            const Text(
              'Tus reservas',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            ...estado.reservas.map(
              (r) => Card(
                child: ListTile(
                  leading: const Icon(Icons.confirmation_number,
                      color: Colors.teal),
                  title: Text(
                    r.campoNombre,
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  subtitle:
                      Text('${r.fechaCorta} · ${r.horaInicio} - ${r.horaFin}'),
                  trailing: SelectableText(
                    r.codigoReserva,
                    style: const TextStyle(
                      fontFamily: 'monospace',
                      fontSize: 12,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Presenta estos códigos al llegar al campo deportivo.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () =>
                  Navigator.of(context).popUntil((route) => route.isFirst),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.teal,
                foregroundColor: Colors.white,
              ),
              child: const Text('Volver al inicio'),
            ),
          ],
        ),
      ),
    );
  }
}
