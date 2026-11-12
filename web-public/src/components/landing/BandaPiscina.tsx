import { Link } from 'react-router-dom';
import { Waves, Users, Clock, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function BandaPiscina() {
  return (
    <section className="bg-white py-20 md:py-28">
      <div className="container mx-auto max-w-6xl px-4">
        <div className="overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-600 via-teal-600 to-emerald-700">
          <div className="grid items-center gap-8 p-8 md:grid-cols-2 md:p-14">
            <div className="text-white">
              <span className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.18em]">
                <Waves className="h-3.5 w-3.5" />
                Piscina olímpica
              </span>
              <h2 className="mt-5 text-3xl font-bold tracking-tight md:text-4xl">
                ¿Lo tuyo es el agua?
              </h2>
              <p className="mt-4 max-w-md text-lg leading-relaxed text-cyan-50/90">
                Reservá tu turno en la piscina olímpica. Cupos por turno y
                perfiles para toda la familia, con la misma tarifa oficial.
              </p>
              <div className="mt-6 flex flex-wrap gap-4 text-sm text-cyan-50/90">
                <span className="flex items-center gap-2">
                  <Users className="h-4 w-4" />
                  Perfiles por edad
                </span>
                <span className="flex items-center gap-2">
                  <Clock className="h-4 w-4" />
                  Turnos programados
                </span>
              </div>
              <Button
                asChild
                size="lg"
                className="mt-8 rounded-full bg-white px-7 text-teal-700 hover:bg-cyan-50"
              >
                <Link to="/campos" className="flex items-center gap-2">
                  Ver turnos de piscina
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </Button>
            </div>

            {/* Ilustración de carriles */}
            <div className="relative mx-auto hidden w-full max-w-sm md:block">
              <div className="space-y-3 rounded-2xl border border-white/20 bg-white/10 p-6 backdrop-blur">
                {[0, 1, 2, 3].map((i) => (
                  <div
                    key={i}
                    className="flex h-9 items-center rounded-full bg-gradient-to-r from-cyan-300/40 to-cyan-200/20 px-4"
                  >
                    <div
                      className="h-2 rounded-full bg-white/70"
                      style={{ width: `${70 - i * 12}%` }}
                    />
                  </div>
                ))}
                <p className="pt-1 text-center text-xs font-medium uppercase tracking-[0.2em] text-cyan-100/70">
                  4 carriles · nivel olímpico
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
