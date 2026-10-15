import 'package:flutter_test/flutter_test.dart';

import 'package:campos_deportivos/main.dart';
import 'package:campos_deportivos/models/campo_deportivo.dart';
import 'package:campos_deportivos/models/estado_solicitud.dart';
import 'package:campos_deportivos/models/piscina_disponibilidad.dart';

/// JSON mínimo de un campo tal como lo devuelve
/// GET /api/v1/public/campos (el backend siempre envía `modalidad_reserva`;
/// `null` para campos que se reservan por franja horaria).
Map<String, dynamic> _campoJson({String? modalidad}) => {
      'id': 'campo-1',
      'nombre': 'Piscina Municipal',
      'direccion': 'Calle 1',
      'latitud': -14.83,
      'longitud': -64.90,
      'estado': 'activo',
      'modalidad_reserva': modalidad,
    };

void main() {
  group('CampoDeportivo.esPiscina', () {
    test('marca piscina cuando modalidad_reserva es turno_por_perfil', () {
      final campo = CampoDeportivo.fromJson(
        _campoJson(modalidad: 'turno_por_perfil'),
      );

      expect(campo.esPiscina, isTrue);
      expect(campo.modalidadReserva, 'turno_por_perfil');
    });

    test('no marca piscina cuando modalidad_reserva viene nula (campo)', () {
      final campo = CampoDeportivo.fromJson(_campoJson());

      expect(campo.esPiscina, isFalse);
      expect(campo.modalidadReserva, isNull);
    });
  });

  group('PiscinaDisponibilidad.fromJson', () {
    test('parsea turnos con perfiles, cupos y precios', () {
      final data = PiscinaDisponibilidad.fromJson({
        'campo_id': 'campo-1',
        'fecha': '2026-10-20',
        'abierto': true,
        'turnos': [
          {
            'id': 'turno-1',
            'hora_inicio': '09:00',
            'hora_fin': '11:00',
            'perfiles': [
              {
                'perfil': 'mayor_12',
                'etiqueta': 'Mayores de 12',
                'precio': 15,
                'cupo': 30,
                'disponible': 28,
                'horario': 'diurno',
              },
              {
                'perfil': 'menor_12',
                'etiqueta': 'Menores de 12',
                'precio': 10,
                'cupo': 20,
                'disponible': 0,
                'horario': 'diurno',
              },
            ],
          },
        ],
      });

      expect(data.abierto, isTrue);
      expect(data.turnos, hasLength(1));

      final turno = data.turnos.first;
      expect(turno.rango, '09:00 - 11:00');
      expect(turno.cupoTotal, 50);
      expect(turno.perfiles.first.hayCupo, isTrue);
      expect(turno.perfiles[1].hayCupo, isFalse);
    });

    test('devuelve lista vacía cuando el día está cerrado', () {
      final data = PiscinaDisponibilidad.fromJson({
        'campo_id': 'campo-1',
        'fecha': '2026-10-20',
        'abierto': false,
        'turnos': [],
      });

      expect(data.abierto, isFalse);
      expect(data.turnos, isEmpty);
    });
  });

  group('EstadoSolicitud.fromJson (consulta unificada)', () {
    test('parsea una reserva de piscina confirmada con perfiles', () {
      final estado = EstadoSolicitud.fromJson({
        'tipo': 'piscina',
        'codigo_seguimiento': 'PIS-20261020-ABC123',
        'estado': 'confirmada',
        'monto_total': 55.5,
        'reservas': [
          {
            'codigo_reserva': 'PIS-20261020-ABC123',
            'campo_nombre': 'Piscina Municipal',
            'fecha': '2026-10-20',
            'hora_inicio': '09:00',
            'hora_fin': '11:00',
            'perfil': 'mayor_12',
            'cantidad': 3,
          },
        ],
      });

      expect(estado.esPiscina, isTrue);
      expect(estado.estaConfirmada, isTrue);
      expect(estado.montoTotal, 55.5);

      final reserva = estado.reservas.single;
      expect(reserva.tienePerfil, isTrue);
      expect(reserva.etiquetaPerfil, 'Mayores de 12 × 3');
      expect(reserva.fechaCorta, '20/10');
    });

    test('parsea una solicitud de campo pendiente con su bloque de cobro', () {
      final estado = EstadoSolicitud.fromJson({
        'tipo': 'campo',
        'codigo_seguimiento': 'RES-20261020-XYZ789',
        'estado': 'pendiente',
        'monto_total': 40,
        'datos_cobro_pendiente': {'qr_string': 'SIREB:RES-20261020-XYZ789'},
      });

      expect(estado.esPiscina, isFalse);
      expect(estado.estaConfirmada, isFalse);
      expect(estado.qrString, 'SIREB:RES-20261020-XYZ789');
    });

    test('parsea una reserva de piscina pendiente y su QR para reanudar', () {
      final estado = EstadoSolicitud.fromJson({
        'tipo': 'piscina',
        'codigo_seguimiento': 'PIS-20261020-ABC123',
        'estado': 'pendiente',
        'monto_total': 30,
        'expira_en': '2026-10-20T12:00:00-04:00',
        'referencia_recaudaciones': 'K699-88PZ-J7G',
        'datos_cobro_pendiente': {
          'qr_string': 'SIREB:K699-88PZ-J7G',
          'codigo_publico': 'K699-88PZ-J7G',
          'checkout_url': null,
        },
      });

      expect(estado.esPiscina, isTrue);
      expect(estado.reservas, isEmpty);
      expect(estado.qrString, 'SIREB:K699-88PZ-J7G');
      expect(estado.expiraEn, '2026-10-20T12:00:00-04:00');
    });

    test('usa tipo "campo" por defecto y 0 cuando faltan monto/tipo', () {
      final estado = EstadoSolicitud.fromJson({
        'codigo_seguimiento': 'RES-1',
        'estado': 'pendiente',
      });

      expect(estado.tipo, 'campo');
      expect(estado.esPiscina, isFalse);
      expect(estado.montoTotal, 0);
    });
  });

  testWidgets('la app arranca en el listado público', (tester) async {
    await tester.pumpWidget(const MyApp());
    await tester.pump();

    expect(find.text('Campos Deportivos'), findsOneWidget);
  });
}
