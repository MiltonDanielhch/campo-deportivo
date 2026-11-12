import { Link } from 'react-router-dom';
import {
  ArrowRight,
  BadgeCheck,
  CheckCircle2,
  Clock,
  Landmark,
  QrCode,
  ReceiptText,
  ShieldCheck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';

const CONFIANZA = [
  { icon: Clock, titulo: 'Hold de 15 minutos', detalle: 'Tu franja queda bloqueada mientras pagás.' },
  { icon: Landmark, titulo: 'Banco o QR', detalle: 'Pagá en ventanilla bancaria o escaneando el QR.' },
  { icon: ReceiptText, titulo: 'Comprobante digital', detalle: 'Se genera al confirmar el pago, sin filas.' },
  { icon: ShieldCheck, titulo: 'Precios oficiales', detalle: 'Tarifas vigentes publicadas en SIREB.' },
];

export default function Hero() {
  return (
    <section className="relative bg-emerald-950 text-white">
      {/* Wrapper SOLO para glows: contiene el desborde sin recortar el strip */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        <div className="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl" />
        <div className="absolute -bottom-40 right-0 h-[28rem] w-[28rem] rounded-full bg-teal-400/15 blur-3xl" />
        <div className="absolute inset-0 opacity-[0.04] bg-[linear-gradient(to_right,white_1px,transparent_1px),linear-gradient(to_bottom,white_1px,transparent_1px)] bg-[size:56px_56px]" />
      </div>

      <div className="container relative mx-auto max-w-6xl px-4 pb-28 pt-16 md:pb-36 md:pt-24">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          {/* Copy */}
          <div className="animate-in fade-in slide-in-from-bottom-4 duration-700">
            <span className="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">
              <BadgeCheck className="h-3.5 w-3.5" />
              Sistema oficial · GAD Beni
            </span>

            <h1 className="mt-6 text-4xl font-bold leading-[1.05] tracking-tight md:text-6xl">
              Tu cancha,
              <br />
              reservada{' '}
              <span className="bg-gradient-to-r from-emerald-300 to-teal-200 bg-clip-text text-transparent">
                en minutos.
              </span>
            </h1>

            <p className="mt-5 max-w-lg text-lg leading-relaxed text-emerald-100/80">
              Fútbol, tenis, frontón y más. Elegí tu horario en tiempo real,
              pagá con QR o en el banco, y recibí tu comprobante digital al
              instante.
            </p>

            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Button
                asChild
                size="lg"
                className="rounded-full bg-emerald-500 px-7 text-emerald-950 hover:bg-emerald-400"
              >
                <Link to="/campos" className="flex items-center gap-2">
                  Ver canchas disponibles
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </Button>
              <Button
                asChild
                size="lg"
                variant="outline"
                className="rounded-full border-white/25 bg-white/5 px-7 text-white hover:bg-white/10 hover:text-white"
              >
                <Link to="/estado" className="flex items-center gap-2">
                  <ReceiptText className="h-4 w-4" />
                  Consultar mi reserva
                </Link>
              </Button>
            </div>

            <p className="mt-6 flex items-center gap-2 text-sm text-emerald-200/60">
              <QrCode className="h-4 w-4" />
              Sin registro obligatorio para reservar. Solo tu CI/NIT.
            </p>
          </div>

          {/* Visual: ticket de reserva */}
          <div className="relative mx-auto w-full max-w-md animate-in fade-in slide-in-from-bottom-6 duration-1000 lg:mx-0">
            <div className="relative rounded-3xl border border-white/15 bg-white/10 p-6 shadow-2xl backdrop-blur-xl">
              <div className="relative mb-5 flex h-40 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700">
                <div className="relative h-24 w-44 rounded-sm border-2 border-white/60">
                  <div className="absolute inset-x-0 top-1/2 h-0.5 bg-white/60" />
                  <div className="absolute left-1/2 top-1/2 h-10 w-10 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/60" />
                  <div className="absolute left-0 top-1/2 h-12 w-6 -translate-y-1/2 border-2 border-l-0 border-white/60" />
                  <div className="absolute right-0 top-1/2 h-12 w-6 -translate-y-1/2 border-2 border-r-0 border-white/60" />
                </div>
                <span className="absolute right-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                  En vivo
                </span>
              </div>

              <div className="flex items-center gap-2 text-emerald-300">
                <CheckCircle2 className="h-5 w-5" />
                <span className="text-sm font-semibold">Reserva confirmada</span>
              </div>
              <p className="mt-2 font-mono text-xl font-bold tracking-wider text-white">
                RES-20261112-K699PZ
              </p>
              <p className="mt-1 text-xs text-emerald-100/60">
                Código de seguimiento · ejemplo
              </p>

              <div className="mt-5 flex items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                <div className="flex items-center gap-2 text-sm text-emerald-100/80">
                  <QrCode className="h-5 w-5 text-emerald-300" />
                  Código de pago SIREB
                </div>
                <span className="font-mono text-sm font-bold text-white">PIS-···</span>
              </div>
            </div>

            <div className="absolute -left-4 -top-4 hidden items-center gap-2 rounded-full border border-white/20 bg-emerald-900/90 px-4 py-2 text-xs font-semibold shadow-lg backdrop-blur sm:flex">
              <BadgeCheck className="h-4 w-4 text-emerald-300" />
              Tarifa oficial SIREB
            </div>
            <div className="absolute -bottom-4 -right-2 hidden items-center gap-2 rounded-full border border-white/20 bg-emerald-900/90 px-4 py-2 text-xs font-semibold shadow-lg backdrop-blur sm:flex">
              <ShieldCheck className="h-4 w-4 text-teal-300" />
              Pago 100% seguro
            </div>
          </div>
        </div>
      </div>

      {/* Strip: bloque normal con margen negativo, ya no lo recorta overflow-hidden */}
      <div className="container relative z-10 mx-auto max-w-6xl px-4">
        <div className="-mb-16 rounded-2xl border border-slate-100 bg-white p-6 shadow-xl shadow-emerald-950/10 md:-mb-20">
          <div className="grid grid-cols-2 gap-6 lg:grid-cols-4">
            {CONFIANZA.map((item) => (
              <div key={item.titulo} className="flex items-start gap-3">
                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                  <item.icon className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-sm font-bold text-slate-900">{item.titulo}</p>
                  <p className="mt-0.5 text-xs leading-snug text-slate-500">
                    {item.detalle}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
