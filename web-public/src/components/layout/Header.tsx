import { Link } from 'react-router-dom';
import { ReceiptText, UserRound } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCiudadanoAuth } from '@/context/CiudadanoAuthProvider';

export default function Header() {
  const { ciudadano } = useCiudadanoAuth();

  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur border-b shadow-sm print:hidden">
      <div className="container mx-auto px-4 h-14 flex items-center justify-between gap-3">
        <Link
          to="/"
          className="flex items-center gap-2 font-bold text-teal-700 hover:text-teal-800 transition-colors min-w-0"
        >
          <img
            src="/img/logo-gad-beni.png"
            alt="Logo GAD Beni"
            className="w-36 h-40 flex-shrink-0 object-contain"
          />
          <span className="text-sm sm:text-base whitespace-nowrap overflow-hidden text-ellipsis">
            Canchas GAD Beni
          </span>
        </Link>

        <nav className="flex items-center gap-1 sm:gap-2">
          <Button variant="ghost" asChild className="hidden sm:inline-flex">
            <Link to="/campos">Canchas</Link>
          </Button>

          {ciudadano ? (
            <Button variant="outline" asChild className="hidden sm:inline-flex">
              <Link to="/mi-asociacion" className="flex items-center gap-2">
                <UserRound className="w-4 h-4" />
                <span>Mi asociación</span>
              </Link>
            </Button>
          ) : (
            <Button variant="outline" asChild className="hidden sm:inline-flex">
              <Link to="/login" className="flex items-center gap-2">
                <UserRound className="w-4 h-4" />
                <span>Ingresar</span>
              </Link>
            </Button>
          )}

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
