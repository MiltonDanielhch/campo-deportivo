import { Link } from 'react-router-dom';
import { CalendarDays, QrCode, Trophy, ArrowRight } from 'lucide-react';

const PASOS = [
  {
    icon: CalendarDays,
    titulo: 'Elegí cancha y horario',
    detalle:
      'Mirá la disponibilidad en tiempo real y seleccioná una o varias franjas. Quedan bloqueadas 15 minutos para vos.',
  },
  {
    icon: QrCode,
    titulo: 'Pagá con QR o en banco',
    detalle:
      'Recibís un código de pago oficial. Escaneá el QR para pagar por Síntesis, o presentá el código y tu CI en cualquier banco.',
  },
  {
    icon: Trophy,
    titulo: '¡A jugar!',
    detalle:
      'Al confirmarse el pago se genera tu comprobante digital. Presentalo el día de tu reserva y listo.',
  },
];

export default function ComoFunciona() {
  return (
    <section id="como-funciona" className="bg-white pb-20 pt-32 md:pb-28 md:pt-40">
      <div className="container mx-auto max-w-6xl px-4">
        <div className="mx-auto mb-14 max-w-2xl text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">
            Proceso simple
          </p>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-5xl">
            ¿Cómo funciona?
          </h2>
          <p className="mt-4 text-lg text-slate-600">
            Tres pasos desde que entrás a la web hasta que pisás la cancha.
          </p>
        </div>

        <div className="relative grid gap-10 md:grid-cols-3 md:gap-8">
          {/* Línea conectora desktop */}
          <div className="absolute left-[16%] right-[16%] top-8 hidden border-t-2 border-dashed border-emerald-200 md:block" />

          {PASOS.map((paso, i) => (
            <div key={paso.titulo} className="relative text-center">
              <div className="relative mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/25">
                <paso.icon className="h-7 w-7" />
                <span className="absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-emerald-950 text-xs font-bold text-white">
                  {i + 1}
                </span>
              </div>
              <h3 className="text-lg font-bold text-slate-900">{paso.titulo}</h3>
              <p className="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-slate-600">
                {paso.detalle}
              </p>
            </div>
          ))}
        </div>

        <div className="mt-14 text-center">
          <Link
            to="/campos"
            className="inline-flex items-center gap-2 font-semibold text-emerald-700 transition-colors hover:text-emerald-800"
          >
            Empezar ahora
            <ArrowRight className="h-4 w-4" />
          </Link>
        </div>
      </div>
    </section>
  );
}
