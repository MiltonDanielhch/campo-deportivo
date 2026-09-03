import 'package:flutter/material.dart';
import '../models/campo_deportivo.dart';

/// Pantalla de detalle de un campo deportivo.
/// PLACEHOLDER: se completará en la Fase 3.4 con selector de fecha y grilla.
class CampoDetalleScreen extends StatelessWidget {
  final CampoDeportivo campo;

  const CampoDetalleScreen({super.key, required this.campo});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(campo.nombre)),
      body: Center(
        child: Text(
          'Próximamente: selector de fecha y grilla de disponibilidad',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyLarge,
        ),
      ),
    );
  }
}
