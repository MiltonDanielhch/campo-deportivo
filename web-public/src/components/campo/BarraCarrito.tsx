import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { useCarritoStore } from '@/store/carritoStore';

export default function BarraCarrito() {
  const { franjas, calcularTotal } = useCarritoStore();

  if (franjas.length === 0) return null;

  return (
    <div className="fixed bottom-0 left-0 right-0 bg-white border-t-2 border-teal-600 shadow-lg p-4 z-50">
      <div className="container mx-auto flex items-center justify-between gap-4">
        <div className="flex-1">
          <p className="text-sm text-gray-600">
            {franjas.length} {franjas.length === 1 ? 'franja' : 'franjas'} seleccionada
            {franjas.length === 1 ? '' : 's'}
          </p>
          <p className="text-2xl font-bold text-teal-700">
            Bs {calcularTotal().toFixed(2)}
          </p>
        </div>
        <Button asChild size="lg">
          <Link to="/reserva">Ver carrito</Link>
        </Button>
      </div>
    </div>
  );
}