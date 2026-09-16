import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/carrito_reserva_provider.dart';
import 'datos_solicitante_screen.dart';

/// Resumen del carrito multi-franja antes de ingresar los datos
/// del solicitante (HU-D1).
class CarritoResumenScreen extends StatelessWidget {
  const CarritoResumenScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final carrito = context.watch<CarritoReservaProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('Resumen de tu reserva')),
      body: carrito.estaVacio
          ? const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.shopping_cart_outlined,
                      size: 64, color: Colors.grey),
                  SizedBox(height: 16),
                  Text(
                    'Tu carrito está vacío',
                    style: TextStyle(fontSize: 18, color: Colors.grey),
                  ),
                ],
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: carrito.franjas.length,
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final franja = carrito.franjas[index];

                return Card(
                  child: ListTile(
                    leading: const Icon(Icons.event_available,
                        color: Colors.teal),
                    title: Text(
                      franja.campoNombre,
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    subtitle: Text('${franja.fechaCorta} · ${franja.rango}'),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          franja.precio != null
                              ? 'Bs ${franja.precio!.toStringAsFixed(2)}'
                              : '-',
                          style: const TextStyle(
                            color: Colors.green,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.close,
                              size: 20, color: Colors.red),
                          tooltip: 'Quitar',
                          onPressed: () => carrito.quitar(franja),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
      bottomNavigationBar: carrito.estaVacio
          ? null
          : SafeArea(
              child: Container(
                padding: const EdgeInsets.all(16),
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
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Total estimado',
                          style: TextStyle(fontSize: 16),
                        ),
                        Text(
                          'Bs ${carrito.totalEstimado.toStringAsFixed(2)}',
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: Colors.green,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'El monto definitivo lo confirma el sistema al reservar',
                      style: TextStyle(fontSize: 11, color: Colors.grey),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const DatosSolicitanteScreen(),
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.teal,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                        ),
                        child: const Text('Continuar'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
