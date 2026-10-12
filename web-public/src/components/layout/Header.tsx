import { Link } from 'react-router-dom';
import { MapPinned, ReceiptText } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function Header() {
  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur border-b shadow-sm print:hidden">
      <div className="container mx-auto px-4 h-14 flex items-center justify-between gap-3">
        <Link
          to="/"
          className="flex items-center gap-2 font-bold text-teal-700 hover:text-teal-800 transition-colors min-w-0"
        >
          <div className="w-8 h-8 flex-shrink-0 rounded-lg bg-teal-600 text-white flex items-center justify-center shadow-sm">
            <MapPinned className="w-4 h-4" />
          </div>
          {/* whitespace-nowrap evita el wrap a 2 líneas; min-w-0 + truncate
              corta el texto en lugar de romper el layout en pantallas muy chicas */}
          <span className="text-sm sm:text-base whitespace-nowrap overflow-hidden text-ellipsis">
            Canchas GAD Beni
          </span>
        </Link>

        <nav className="flex items-center gap-1 sm:gap-2">
          <Button variant="ghost" asChild className="hidden sm:inline-flex">
            <Link to="/campos">Canchas</Link>
          </Button>

          {/* Mobile: solo ícono | Desktop: botón con texto */}
          <Button variant="outline" size="icon" asChild className="sm:hidden" aria-label="Mis comprobantes">
            <Link to="/estado">
              <ReceiptText className="w-4 h-4" />
            </Link>
          </Button>
          <Button variant="outline" asChild className="hidden sm:inline-flex">
            <Link to="/estado" className="flex items-center gap-2">
              <ReceiptText className="w-4 h-4" />
              <span>Mis comprobantes</span>
            </Link>
          </Button>
        </nav>
      </div>
    </header>
  );
}
