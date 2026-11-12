import { ChevronDown } from 'lucide-react';

const PREGUNTAS = [
  {
    q: '¿Necesito crear una cuenta para reservar?',
    a: 'No. Podés reservar como visitante con tu nombre y CI/NIT. La cuenta es solo para responsables de asociaciones que acceden a tarifas de convenio y gestionan sus alumnos.',
  },
  {
    q: '¿Cómo pago mi reserva?',
    a: 'Al reservar recibís un código de pago oficial de SIREB. Escaneá el QR para pagar por Síntesis, o presentá el código junto a tu CI/NIT en la ventanilla de cualquier banco.',
  },
  {
    q: '¿Cuánto tiempo tengo para pagar?',
    a: 'Tu franja queda bloqueada 15 minutos mientras pagás. Si el pago no se confirma en ese plazo, la reserva expira y la franja vuelve a estar disponible para otros.',
  },
  {
    q: '¿Dónde veo mi comprobante?',
    a: 'En "Mis comprobantes", ingresando tu código de seguimiento (RES-…) o el código de tu reserva. Ahí podés ver el estado y descargar tu comprobante digital.',
  },
];

export default function Faq() {
  return (
    <section id="faq" className="bg-slate-50 py-20 md:py-28">
      <div className="container mx-auto max-w-3xl px-4">
        <div className="mb-12 text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">
            Preguntas frecuentes
          </p>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">
            Todo lo que necesitás saber
          </h2>
        </div>

        <div className="space-y-3">
          {PREGUNTAS.map((item) => (
            <details
              key={item.q}
              className="group rounded-2xl border border-slate-200 bg-white transition-shadow open:shadow-md"
            >
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5 font-semibold text-slate-900 [&::-webkit-details-marker]:hidden">
                {item.q}
                <ChevronDown className="h-5 w-5 flex-shrink-0 text-emerald-600 transition-transform duration-200 group-open:rotate-180" />
              </summary>
              <p className="px-6 pb-5 text-sm leading-relaxed text-slate-600">
                {item.a}
              </p>
            </details>
          ))}
        </div>
      </div>
    </section>
  );
}
