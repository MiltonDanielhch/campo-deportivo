import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { ShoppingBag, ArrowRight } from 'lucide-react';
import { useCarritoStore } from '@/store/carritoStore';

export default function BarraCarrito() {
  const { franjas, calcularTotal } = useCarritoStore();

  if (franjas.length === 0) return null;

  const total = calcularTotal();

  return (
    <div className="fixed bottom-0 left-0 right-0 z-50 p-3 sm:p-4 pointer-events-none">
      <div className="container mx-auto max-w-5xl pointer-events-auto">
        <div className="bg-white/95 backdrop-blur-lg border border-slate-200 shadow-2xl shadow-black/10 rounded-2xl p-3 sm:p-4 flex items-center gap-3 sm:gap-4">
          {/* Icono con badge de cantidad */}
          <div className="relative flex-shrink-0">
            <div className="w-12 h-12 rounded-xl bg-teal-50 flex items-center justify-center">
              <ShoppingBag className="w-6 h-6 text-teal-600" />
            </div>
            <span className="absolute -top-1.5 -right-1.5 bg-teal-600 text-white text-xs font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1.5">
              {franjas.length}
            </span>
          </div>

          {/* Info del carrito */}
          <div className="flex-1 min-w-0">
            <p className="text-xs text-slate-500 truncate">
              {franjas.length === 1
                ? '1 franja seleccionada'
                : `${franjas.length} franjas seleccionadas`}
            </p>
            <p className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-tight">
              Bs {total.toFixed(2)}
            </p>
          </div>

          {/* Botón */}
          <Button
            asChild
            size="lg"
            className="rounded-full px-5 sm:px-6 h-11 sm:h-12 shadow-lg shadow-teal-500/20"
          >
            <Link to="/reserva" className="flex items-center gap-2">
              <span>Ver carrito</span>
              <ArrowRight className="w-4 h-4" />
            </Link>
          </Button>
        </div>
      </div>
    </div>
  );
}
