import { MapPin, Phone, Clock } from 'lucide-react';

export default function InfoInstitucional() {
  return (
    <section className="py-16 bg-gray-50">
      <div className="container mx-auto px-4">
        <h2 className="text-3xl font-bold text-center mb-12">Información institucional</h2>
        <div className="grid md:grid-cols-3 gap-8">
          <div className="text-center">
            <div className="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <MapPin className="w-8 h-8 text-teal-600" />
            </div>
            <h3 className="text-xl font-semibold mb-2">Dirección</h3>
            <p className="text-gray-600">
              Gobierno Autónomo Departamental del Beni
              <br />
              Av. Ganadera, Trinidad
            </p>
          </div>
          <div className="text-center">
            <div className="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <Phone className="w-8 h-8 text-teal-600" />
            </div>
            <h3 className="text-xl font-semibold mb-2">Teléfono</h3>
            <p className="text-gray-600">
              +591 3 462-XXXX
              <br />
              Lun-Vie 8:00-18:00
            </p>
          </div>
          <div className="text-center">
            <div className="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <Clock className="w-8 h-8 text-teal-600" />
            </div>
            <h3 className="text-xl font-semibold mb-2">Horarios de atención</h3>
            <p className="text-gray-600">
              Lunes a Viernes
              <br />
              8:00 - 18:00
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
