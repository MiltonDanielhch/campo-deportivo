import 'package:flutter/foundation.dart';
import '../models/franja_carrito.dart';

/// Carrito global de franjas seleccionadas (HU-D1).
///
/// Vive en un ChangeNotifier porque las franjas pueden agregarse desde
/// cualquier pantalla de detalle (distintas fechas e incluso campos) y
/// el resumen / envío se hacen desde otra pantalla.
///
/// El monto que muestra es ESTIMADO: el definitivo siempre lo calcula
/// el backend al crear la solicitud.
class CarritoReservaProvider extends ChangeNotifier {
  final List<FranjaCarrito> _franjas = [];

  List<FranjaCarrito> get franjas => List.unmodifiable(_franjas);

  bool get estaVacio => _franjas.isEmpty;

  int get cantidad => _franjas.length;

  double get totalEstimado =>
      _franjas.fold(0, (suma, f) => suma + (f.precio ?? 0));

  bool esta(FranjaCarrito franja) =>
      _franjas.any((x) => x.key == franja.key);

  void toggle(FranjaCarrito franja) {
    if (esta(franja)) {
      quitar(franja);
    } else {
      _franjas.add(franja);
      notifyListeners();
    }
  }

  void agregar(FranjaCarrito franja) {
    if (!esta(franja)) {
      _franjas.add(franja);
      notifyListeners();
    }
  }

  void quitar(FranjaCarrito franja) => quitarPorKey(franja.key);

  void quitarPorKey(String key) {
    final antes = _franjas.length;
    _franjas.removeWhere((x) => x.key == key);
    if (_franjas.length != antes) {
      notifyListeners();
    }
  }

  void limpiar() {
    _franjas.clear();
    notifyListeners();
  }
}
