import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
// import '../models/estado_solicitud.dart';
import '../services/estado_solicitud_service.dart';
import '../services/solicitud_reserva_service.dart';
import 'comprobante_screen.dart';

/// Pantalla de cobro con cuenta regresiva (Fase 4.4) y detección
/// automática de confirmación (Fase 5.5, HU-D6).
///
/// Mientras está visible consulta el estado cada 5 segundos:
///  • confirmada → navega al comprobante
///  • expirada   → vista de expiración (Módulo 4)
///  • rechazada  → mensaje distinto: el Core procesó el intento y lo
///                  rechazó (no confundir con el 503 inmediato del Módulo 4)
class PagoQrScreen extends StatefulWidget {
  final SolicitudCreada resultado;

  const PagoQrScreen({super.key, required this.resultado});

  @override
  State<PagoQrScreen> createState() => _PagoQrScreenState();
}

class _PagoQrScreenState extends State<PagoQrScreen> {
  Timer? _timer;
  Timer? _pollingTimer;
  Duration _restante = Duration.zero;
  bool _expirado = false;
  bool _rechazado = false;
  bool _navegando = false;

  final EstadoSolicitudService _estadoService = EstadoSolicitudService();

  @override
  void initState() {
    super.initState();
    final expira = DateTime.parse(widget.resultado.expiraEn);
    _sincronizar(expira);
    _timer = Timer.periodic(
      const Duration(seconds: 1),
      (_) => _sincronizar(expira),
    );
    _pollingTimer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _consultarEstado(),
    );
  }

  void _sincronizar(DateTime expira) {
    final r = expira.difference(DateTime.now());
    if (!mounted) return;
    setState(() {
      if (r.isNegative) {
        _restante = Duration.zero;
        _expirado = true;
        _detenerTimers();
      } else {
        _restante = r;
      }
    });
  }

  void _detenerTimers() {
    _timer?.cancel();
    _pollingTimer?.cancel();
  }

  Future<void> _consultarEstado() async {
    if (_navegando) return;

    try {
      final estado =
          await _estadoService.consultar(widget.resultado.codigoSeguimiento);
      if (!mounted || _navegando) return;

      if (estado.estaConfirmada) {
        _navegando = true;
        _detenerTimers();
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => ComprobanteScreen(estado: estado)),
        );
        return;
      }

      if (estado.estaExpirada) {
        setState(() {
          _expirado = true;
          _detenerTimers();
        });
        return;
      }

      if (estado.estaRechazada) {
        setState(() {
          _rechazado = true;
          _detenerTimers();
        });
      }

    } catch (_) {
      // Si la consulta falla (red caída), se reintenta en el siguiente
      // tick. La cuenta regresiva sigue siendo la fuente de verdad local.
    }
  }

  @override
  void dispose() {
    _detenerTimers();
    super.dispose();
  }

  String get _mmss {
    final m = _restante.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = _restante.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  Color get _colorCuenta => _restante.inSeconds <= 60 ? Colors.red : Colors.teal;

  Future<void> _abrirCheckout(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('No se pudo abrir el enlace de pago: $url')),
      );
    }
  }

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
        appBar: AppBar(title: const Text('Pago de tu reserva')),
        body: _expirado
            ? _vistaExpirada()
            : _rechazado
                ? _vistaRechazada()
                : _vistaPago(),
      ),
    );
  }

  Widget _vistaPago() {
    final r = widget.resultado;

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Center(
          child: Column(
            children: [
              const Text('Tiempo restante para pagar'),
              Text(
                _mmss,
                style: TextStyle(
                  fontSize: 42,
                  fontWeight: FontWeight.bold,
                  color: _colorCuenta,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Center(
          child: Column(
            children: [
              Text(
                'Bs ${r.montoTotal.toStringAsFixed(2)}',
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  color: Colors.green,
                ),
              ),
              const SizedBox(height: 8),
              SelectableText(
                'Código: ${r.codigoSeguimiento}',
                style: const TextStyle(fontFamily: 'monospace', fontSize: 16),
              ),
              const Text(
                'Guárdalo por si necesitas soporte',
                style: TextStyle(fontSize: 11, color: Colors.grey),
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        _medioDePago(r),
        const SizedBox(height: 24),
        const Text(
          'Escanea el QR con tu app de banca móvil o usa el botón de pago. '
          'Esta pantalla detecta automáticamente cuando el Core confirma '
          'tu pago y te muestra el comprobante.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 12, color: Colors.grey),
        ),
      ],
    );
  }

  Widget _medioDePago(SolicitudCreada r) {
    if (r.qrString != null && r.qrString!.isNotEmpty) {
      return Center(
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.white,
            border: Border.all(color: Colors.grey.shade300),
            borderRadius: BorderRadius.circular(12),
          ),
          child: QrImageView(
            data: r.qrString!,
            version: QrVersions.auto,
            size: 220,
          ),
        ),
      );
    }

    if (r.qrImageBase64 != null && r.qrImageBase64!.isNotEmpty) {
      return Center(
        child: Image.memory(
          base64Decode(r.qrImageBase64!),
          width: 220,
          height: 220,
        ),
      );
    }

    if (r.checkoutUrl != null && r.checkoutUrl!.isNotEmpty) {
      return Center(
        child: ElevatedButton.icon(
          onPressed: () => _abrirCheckout(r.checkoutUrl!),
          icon: const Icon(Icons.open_in_new),
          label: const Text('Pagar en el navegador'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.teal,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
          ),
        ),
      );
    }

    return const Center(
      child: Text(
        'El sistema de cobro no devolvió un medio de pago. '
        'Reintenta o contacta a soporte.',
        textAlign: TextAlign.center,
      ),
    );
  }

  Widget _vistaExpirada() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.timer_off, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            const Text(
              'El tiempo para pagar expiró',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Tu solicitud ${widget.resultado.codigoSeguimiento} quedó sin '
              'pago y será cerrada al cumplir el plazo.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.grey),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () =>
                  Navigator.of(context).popUntil((route) => route.isFirst),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.teal,
                foregroundColor: Colors.white,
              ),
              child: const Text('Volver a elegir franjas'),
            ),
          ],
        ),
      ),
    );
  }

  /// Distinta de la expiración y del 503 del Módulo 4: acá el Core SÍ
  /// procesó el intento de cobro pero lo rechazó.
  Widget _vistaRechazada() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.orange),
            const SizedBox(height: 16),
            const Text(
              'Tu pago no pudo procesarse',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'El sistema de recaudaciones rechazó el intento de cobro. '
              'Intenta nuevamente con otro medio de pago.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () =>
                  Navigator.of(context).popUntil((route) => route.isFirst),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.teal,
                foregroundColor: Colors.white,
              ),
              child: const Text('Volver a elegir franjas'),
            ),
          ],
        ),
      ),
    );
  }
}
