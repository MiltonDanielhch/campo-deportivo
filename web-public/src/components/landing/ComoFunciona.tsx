import { Calendar, CreditCard, CheckCircle } from 'lucide-react';

export default function ComoFunciona() {
  const pasos = [
    {
      icon: Calendar,
      titulo: '1. Elegí tu cancha',
      descripcion: 'Buscá por tipo de deporte, ubicación y horario disponible.',
    },
    {
      icon: CreditCard,
      titulo: '2. Pagá online',
      descripcion: 'Pago seguro con QR o transferencia bancaria.',
    },
    {
      icon: CheckCircle,
      titulo: '3. ¡A jugar!',
      descripcion: 'Recibí tu comprobante digital y presentalo al llegar.',
    },
  ];

  return (
    <section className="py-16 bg-gray-50">
      <div className="container mx-auto px-4">
        <h2 className="text-3xl font-bold text-center mb-12">¿Cómo funciona?</h2>
        <div className="grid md:grid-cols-3 gap-8">
          {pasos.map((paso, i) => (
            <div key={i} className="text-center">
              <div className="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <paso.icon className="w-8 h-8 text-teal-600" />
              </div>
              <h3 className="text-xl font-semibold mb-2">{paso.titulo}</h3>
              <p className="text-gray-600">{paso.descripcion}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
