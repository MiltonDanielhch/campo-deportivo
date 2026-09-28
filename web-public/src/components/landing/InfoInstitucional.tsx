import { MapPin, Phone, Clock } from 'lucide-react';

const datos = [
  {
    icon: MapPin,
    titulo: 'Dirección',
    lineas: [
      'Gobierno Autónomo Departamental del Beni',
      'Av. Ganadera, Trinidad',
    ],
    color: 'from-teal-500 to-emerald-600',
  },
  {
    icon: Phone,
    titulo: 'Teléfono',
    lineas: ['+591 3 462-XXXX', 'WhatsApp disponible'],
    color: 'from-sky-500 to-teal-600',
  },
  {
    icon: Clock,
    titulo: 'Horarios de atención',
    lineas: ['Lunes a Viernes', '08:00 — 18:00'],
    color: 'from-indigo-500 to-teal-600',
  },
];

export default function InfoInstitucional() {
  return (
    <section className="py-20 md:py-28 bg-white">
      <div className="container mx-auto px-4 max-w-6xl">
        {/* Encabezado */}
        <div className="text-center mb-16 max-w-2xl mx-auto">
          <p className="text-teal-600 font-semibold uppercase tracking-widest text-xs mb-3">
            Información institucional
          </p>
          <h2 className="text-3xl md:text-5xl font-bold mb-4 tracking-tight">
            ¿Dónde estamos?
          </h2>
          <p className="text-slate-600 text-lg">
            Canales oficiales de contacto del GAD Beni para consultas sobre el
            sistema.
          </p>
        </div>

        {/* Cards */}
        <div className="grid md:grid-cols-3 gap-6">
          {datos.map((dato) => (
            <div
              key={dato.titulo}
              className="bg-slate-50 rounded-2xl p-8 border border-slate-100 hover:bg-white hover:shadow-lg hover:-translate-y-1 transition-all duration-300"
            >
              <div
                className={`w-14 h-14 rounded-xl bg-gradient-to-br ${dato.color} text-white flex items-center justify-center mb-5 shadow-md`}
              >
                <dato.icon className="w-6 h-6" strokeWidth={2} />
              </div>
              <h3 className="text-lg font-bold mb-3 tracking-tight">
                {dato.titulo}
              </h3>
              {dato.lineas.map((linea, i) => (
                <p
                  key={i}
                  className={`text-sm ${
                    i === 0 ? 'text-slate-900 font-medium' : 'text-slate-500'
                  }`}
                >
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
