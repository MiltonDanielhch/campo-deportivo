import { MapPin, Phone, Clock } from 'lucide-react';

const CANALES = [
  {
    icon: MapPin,
    titulo: 'Dirección',
    lineas: ['Gobierno Autónomo Departamental del Beni', 'Av. Ganadera, Trinidad'],
  },
  {
    icon: Phone,
    titulo: 'Teléfono',
    lineas: ['+591 3 462-XXXX', 'Llamadas en horario de oficina'],
  },
  {
    icon: Clock,
    titulo: 'Horarios de atención',
    lineas: ['Lunes a viernes', '08:00 — 18:00'],
  },
];

export default function InfoInstitucional() {
  return (
    <section id="contacto" className="bg-white py-20 md:py-28">
      <div className="container mx-auto max-w-6xl px-4">
        <div className="mx-auto mb-12 max-w-2xl text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">
            Información institucional
          </p>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">
            ¿Dónde estamos?
          </h2>
          <p className="mt-4 text-lg text-slate-600">
            Canales oficiales del GAD Beni para consultas sobre el sistema.
          </p>
        </div>

        <div className="grid gap-6 md:grid-cols-3">
          {CANALES.map((canal) => (
            <div
              key={canal.titulo}
              className="rounded-3xl border border-slate-100 bg-slate-50/50 p-7 transition-colors hover:border-emerald-200 hover:bg-emerald-50/40"
            >
              <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/20">
                <canal.icon className="h-5 w-5" />
              </div>
              <h3 className="text-base font-bold text-slate-900">{canal.titulo}</h3>
              {canal.lineas.map((linea) => (
                <p key={linea} className="mt-1 text-sm text-slate-600">
                  {linea}
                </p>
              ))}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}