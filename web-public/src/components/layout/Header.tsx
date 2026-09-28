import { Link } from 'react-router-dom';
import { MapPinned, ReceiptText } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function Header() {
  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur border-b shadow-sm print:hidden">
      <div className="container mx-auto px-4 h-14 flex items-center justify-between">
        <Link
          to="/"
          className="flex items-center gap-2 font-bold text-teal-700 hover:text-teal-800 transition-colors"
        >
          <div className="w-8 h-8 rounded-lg bg-teal-600 text-white flex items-center justify-center shadow-sm">
            <MapPinned className="w-4 h-4" />
          </div>
          <span className="text-sm sm:text-base">Canchas GAD Beni</span>
        </Link>

        <nav className="flex items-center gap-1 sm:gap-2">
          <Button variant="ghost" asChild className="hidden sm:inline-flex">
            <Link to="/campos">Canchas</Link>
          </Button>
          <Button variant="outline" asChild>
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
