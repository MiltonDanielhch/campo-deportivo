import { Link } from 'react-router-dom';
import { ArrowRight, Play, ShieldCheck, Users, Trophy } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function Hero() {
  return (
    <section className="relative overflow-hidden bg-gradient-to-br from-teal-600 via-teal-700 to-emerald-700 text-white">
      {/* Patrón decorativo de fondo (líneas de cancha) */}
      <div
        className="absolute inset-0 opacity-[0.07]"
        style={{
          backgroundImage:
            'repeating-linear-gradient(45deg, white 0, white 1px, transparent 1px, transparent 20px), repeating-linear-gradient(-45deg, white 0, white 1px, transparent 1px, transparent 20px)',
        }}
      />
      {/* Glow */}
      <div className="absolute -top-24 -right-24 w-96 h-96 bg-emerald-400/30 rounded-full blur-3xl" />
      <div className="absolute -bottom-24 -left-24 w-96 h-96 bg-teal-400/20 rounded-full blur-3xl" />

      <div className="relative container mx-auto px-4 py-20 md:py-28 text-center max-w-4xl">
        {/* Eyebrow */}
        <span className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur border border-white/20 text-xs font-semibold uppercase tracking-widest mb-6">
          <ShieldCheck className="w-3.5 h-3.5" />
          Sistema oficial del GAD Beni
        </span>

        {/* H1 gigante */}
        <h1 className="text-4xl sm:text-5xl md:text-6xl font-bold leading-tight mb-6 tracking-tight">
          Reservá tu cancha
          <br />
          <span className="bg-gradient-to-r from-emerald-200 to-yellow-200 bg-clip-text text-transparent">
            en minutos
          </span>
        </h1>

        {/* Subtítulo */}
        <p className="text-lg md:text-xl text-teal-50 mb-10 max-w-2xl mx-auto leading-relaxed">
          El sistema oficial del Gobierno Autónomo Departamental del Beni para
          reservar canchas de fútbol, tenis, vóley y más. Pago seguro y
          comprobante digital al instante.
        </p>

        {/* CTAs */}
        <div className="flex flex-col sm:flex-row items-center justify-center gap-3 mb-16">
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
          <Button
            asChild
            variant="outline"
            size="lg"
            className="bg-transparent border-white/30 text-white hover:bg-white/10 rounded-full px-8 h-12 text-base"
          >
            <a href="#como-funciona" className="flex items-center gap-2">
              <Play className="w-4 h-4" />
              ¿Cómo funciona?
            </a>
          </Button>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-3 gap-4 max-w-xl mx-auto pt-8 border-t border-white/15">
          <div className="text-center">
            <Trophy className="w-5 h-5 mx-auto mb-2 text-emerald-200" />
            <p className="text-2xl md:text-3xl font-bold">3</p>
            <p className="text-xs md:text-sm text-teal-100">Canchas disponibles</p>
          </div>
          <div className="text-center border-x border-white/15">
            <Users className="w-5 h-5 mx-auto mb-2 text-emerald-200" />
            <p className="text-2xl md:text-3xl font-bold">2 min</p>
            <p className="text-xs md:text-sm text-teal-100">Para reservar</p>
          </div>
          <div className="text-center">
            <ShieldCheck className="w-5 h-5 mx-auto mb-2 text-emerald-200" />
            <p className="text-2xl md:text-3xl font-bold">QR</p>
            <p className="text-xs md:text-sm text-teal-100">Pago seguro</p>
          </div>
        </div>
      </div>
    </section>
  );
}
