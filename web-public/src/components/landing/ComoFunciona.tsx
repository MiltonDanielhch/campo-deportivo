import { MapPin, CreditCard, CheckCircle2 } from 'lucide-react';

const pasos = [
  {
    num: '01',
    icon: MapPin,
    titulo: 'Elegí tu cancha',
    descripcion:
      'Explorá las canchas disponibles por tipo de deporte, ubicación y horario. Vedé el precio diurno y nocturno antes de reservar.',
  },
  {
    num: '02',
    icon: CreditCard,
    titulo: 'Pagá online',
    descripcion:
      'Agregá las franjas al carrito y pagá con QR bancario. El proceso toma menos de 2 minutos.',
  },
  {
    num: '03',
    icon: CheckCircle2,
    titulo: '¡A jugar!',
    descripcion:
      'Recibís tu comprobante digital por email. Presentalo al llegar a la cancha el día de tu reserva.',
  },
];

export default function ComoFunciona() {
  return (
    <section id="como-funciona" className="py-20 md:py-28 bg-white">
      <div className="container mx-auto px-4 max-w-6xl">
        {/* Encabezado de sección */}
        <div className="text-center mb-16 max-w-2xl mx-auto">
          <p className="text-teal-600 font-semibold uppercase tracking-widest text-xs mb-3">
            Proceso simple
          </p>
          <h2 className="text-3xl md:text-5xl font-bold mb-4 tracking-tight">
            ¿Cómo funciona?
          </h2>
          <p className="text-slate-600 text-lg">
            Tres pasos desde que entrás a la web hasta que pisás la cancha.
          </p>
        </div>

        {/* Pasos numerados con conector visual */}
        <div className="grid md:grid-cols-3 gap-8 md:gap-6 relative">
          {/* Línea conectora (solo en desktop) */}
          <div className="hidden md:block absolute top-10 left-[20%] right-[20%] h-0.5 bg-gradient-to-r from-teal-200 via-teal-400 to-teal-200 -z-0" />

          {pasos.map((paso) => (
            <div
              key={paso.num}
              className="relative bg-white rounded-2xl p-8 text-center border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group"
            >
              {/* Número grande de fondo */}
              <div className="absolute top-4 right-4 text-6xl font-black text-slate-100 group-hover:text-teal-100 transition-colors">
                {paso.num}
              </div>

              {/* Icono circular */}
              <div className="relative z-10 w-20 h-20 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-teal-500 to-emerald-600 text-white flex items-center justify-center shadow-lg shadow-teal-500/30 group-hover:scale-110 transition-transform">
                <paso.icon className="w-9 h-9" strokeWidth={2} />
              </div>

              <h3 className="relative z-10 text-xl font-bold mb-3 tracking-tight">
                {paso.titulo}
              </h3>
              <p className="relative z-10 text-slate-600 leading-relaxed text-sm">
                {paso.descripcion}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
