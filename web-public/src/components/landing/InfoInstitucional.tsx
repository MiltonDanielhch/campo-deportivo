import { MapPin, Phone, Clock, MessageCircle } from 'lucide-react';

/**
 * Datos institucionales del GAD Beni.
 * TODO: completar con los datos REALES del GAD antes de publicar.
 * Mantener todos los strings aquí para que sea fácil actualizarlos sin
 * tocar el JSX.
 */
const datosInstitucionales = {
  direccion: {
    principal: 'Gobierno Autónomo Departamental del Beni',
    detalle: 'Av. Ganadera, Trinidad', // TODO: verificar dirección oficial
    enlace_maps: 'https://maps.google.com/?q=GAD+Beni+Trinidad',
  },
  telefono: {
    numero: '+591 3 462-XXXX', // TODO: reemplazar con número real
    whatsapp: '+591 3 462-XXXX', // TODO: reemplazar con número real de WhatsApp
    whatsapp_disponible: false, // activar cuando haya número real
  },
  horarios: {
    dias: 'Lunes a Viernes', // TODO: confirmar si incluye sábados
    horario: '08:00 — 18:00',
  },
};

const datos = [
  {
    icon: MapPin,
    titulo: 'Dirección',
    lineas: [datosInstitucionales.direccion.principal, datosInstitucionales.direccion.detalle],
    enlace: datosInstitucionales.direccion.enlace_maps,
    color: 'from-teal-500 to-emerald-600',
  },
  {
    icon: Phone,
    titulo: 'Teléfono',
    lineas: [
      datosInstitucionales.telefono.numero,
      datosInstitucionales.telefono.whatsapp_disponible ? 'WhatsApp disponible' : 'Llamadas',
    ],
    enlace: `tel:${datosInstitucionales.telefono.numero.replace(/\s/g, '')}`,
    color: 'from-sky-500 to-teal-600',
  },
  {
    icon: Clock,
    titulo: 'Horarios de atención',
    lineas: [datosInstitucionales.horarios.dias, datosInstitucionales.horarios.horario],
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
            <a
              key={dato.titulo}
              href={dato.enlace || '#'}
              target={dato.enlace?.startsWith('http') ? '_blank' : undefined}
              rel={dato.enlace?.startsWith('http') ? 'noopener noreferrer' : undefined}
              className="block bg-slate-50 rounded-2xl p-8 border border-slate-100 hover:bg-white hover:shadow-lg hover:-translate-y-1 transition-all duration-300"
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
            </a>
          ))}
        </div>

        {/* Botón WhatsApp destacado — solo visible cuando hay número real */}
        {datosInstitucionales.telefono.whatsapp_disponible && (
          <div className="mt-10 text-center">
            <a
              href={`https://wa.me/${datosInstitucionales.telefono.whatsapp.replace(/[\s+]/g, '')}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold shadow-lg shadow-emerald-500/20 transition-colors"
            >
              <MessageCircle className="w-5 h-5" />
              Escribinos por WhatsApp
            </a>
          </div>
        )}
      </div>
    </section>
  );
}
