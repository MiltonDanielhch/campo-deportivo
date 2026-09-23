import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';

export default function Hero() {
  return (
    <section className="bg-gradient-to-br from-teal-600 to-teal-700 text-white py-20">
      <div className="container mx-auto px-4 text-center">
        <h1 className="text-4xl md:text-6xl font-bold mb-6">
          Reserva Canchas Deportivas en el Beni
        </h1>
        <p className="text-xl md:text-2xl mb-8 text-teal-100">
          El sistema oficial del GAD Beni para reservar campos deportivos
        </p>
        <Button asChild size="lg" className="bg-white text-teal-700 hover:bg-teal-50">
          <Link to="/campos">Ver canchas disponibles</Link>
        </Button>
      </div>
    </section>
  );
}
