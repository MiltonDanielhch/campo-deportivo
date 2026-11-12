import { Link } from 'react-router-dom';
import { ArrowRight, CalendarCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function BandaCTA() {
  return (
    <section className="relative overflow-hidden bg-emerald-950 py-20 text-white md:py-24">
      <div className="pointer-events-none absolute -left-24 top-0 h-72 w-72 rounded-full bg-emerald-500/20 blur-3xl" />
      <div className="pointer-events-none absolute -right-24 bottom-0 h-72 w-72 rounded-full bg-teal-400/15 blur-3xl" />

      <div className="container relative mx-auto max-w-3xl px-4 text-center">
        <span className="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">
          <CalendarCheck className="h-3.5 w-3.5" />
          ¿Listo para jugar?
        </span>
        <h2 className="mt-6 text-3xl font-bold tracking-tight md:text-5xl">
          Reservá tu cancha esta semana
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-lg text-emerald-100/80">
          Pagá con QR o en el banco, recibí tu comprobante digital al confirmar
          y presentalo el día de tu reserva.
        </p>
        <Button
          asChild
          size="lg"
          className="mt-8 rounded-full bg-emerald-500 px-8 text-emerald-950 hover:bg-emerald-400"
        >
          <Link to="/campos" className="flex items-center gap-2">
            Ver canchas disponibles
            <ArrowRight className="h-4 w-4" />
          </Link>
        </Button>
      </div>
    </section>
  );
}
