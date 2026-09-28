import { Link } from 'react-router-dom';
import { ArrowRight, Calendar } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function BandaCTA() {
  return (
    <section className="py-20 md:py-24 bg-gradient-to-br from-teal-700 via-teal-800 to-emerald-800 text-white relative overflow-hidden">
      {/* Glow decorativo */}
      <div className="absolute top-0 right-0 w-96 h-96 bg-emerald-400/20 rounded-full blur-3xl" />
      <div className="absolute bottom-0 left-0 w-96 h-96 bg-teal-400/20 rounded-full blur-3xl" />

      <div className="relative container mx-auto px-4 max-w-3xl text-center">
        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur border border-white/20 text-xs font-semibold uppercase tracking-widest mb-6">
          <Calendar className="w-3.5 h-3.5" />
          ¿Listo para jugar?
        </div>
        <h2 className="text-3xl md:text-5xl font-bold mb-5 tracking-tight leading-tight">
          Reservá tu cancha esta semana
        </h2>
        <p className="text-lg md:text-xl text-teal-50 mb-10 leading-relaxed">
          Pagá con QR, recibí tu comprobante al instante y presentalo el día de
          tu reserva.
        </p>
        <Button
          asChild
          size="lg"
          className="bg-white text-teal-700 hover:bg-teal-50 shadow-xl shadow-black/20 rounded-full px-8 h-12 text-base font-semibold"
        >
          <Link to="/campos" className="flex items-center gap-2">
            Ver canchas disponibles
            <ArrowRight className="w-4 h-4" />
          </Link>
        </Button>
      </div>
    </section>
  );
}
