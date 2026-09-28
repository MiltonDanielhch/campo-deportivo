import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/carrito_reserva_provider.dart';
import 'screens/campos_listado_screen.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => CarritoReservaProvider(),
      child: MaterialApp(
        title: 'Campos Deportivos GAD Beni',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal),
          useMaterial3: true,
        ),
        locale: const Locale('es'),
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        supportedLocales: const [
          Locale('es', 'BO'),
          Locale('es'),
          Locale('en'),
        ],
        home: const CamposListadoScreen(),
      ),
    );
  }
}
