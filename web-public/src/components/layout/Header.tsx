import { Link } from 'react-router-dom';
import { MapPinned, ReceiptText } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function Header() {
  return (
    <header className="sticky top-0 z-40 bg-white border-b shadow-sm print:hidden">
      <div className="container mx-auto px-4 h-14 flex items-center justify-between">
        <Link to="/" className="flex items-center gap-2 font-bold text-teal-700">
          <MapPinned className="w-5 h-5" />
          Canchas GAD Beni
        </Link>
        <div className="flex items-center gap-2">
          <Button variant="ghost" asChild className="hidden sm:flex">
            <Link to="/campos">Canchas</Link>
          </Button>
          <Button variant="outline" asChild>
            <Link to="/estado">
              <ReceiptText className="w-4 h-4 mr-2" />
              Mis comprobantes
            </Link>
          </Button>
        </div>
      </div>
    </header>
  );
} 
