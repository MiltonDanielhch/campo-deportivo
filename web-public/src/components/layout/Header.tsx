import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Menu, X, ReceiptText, UserRound, CalendarCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCiudadanoAuth } from '@/context/CiudadanoAuthProvider';

export default function Header() {
  const { ciudadano } = useCiudadanoAuth();
  const [abierto, setAbierto] = useState(false);

  const cerrar = () => setAbierto(false);

  return (
    <header className="sticky top-0 z-40 border-b border-slate-200/60 bg-white/80 backdrop-blur-lg print:hidden">
      <div className="container mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4">
        <Link to="/" className="flex min-w-0 items-center gap-2.5" onClick={cerrar}>
          <img
            src="/img/logo-gad-beni.png"
            alt="Logo GAD Beni"
            className="h-10 w-auto flex-shrink-0 object-contain"
          />
          <span className="hidden flex-col leading-tight sm:flex">
            <span className="text-sm font-bold tracking-tight text-slate-900">
              Canchas GAD Beni
            </span>
            <span className="text-[11px] text-slate-500">
              Reserva oficial en línea
            </span>
          </span>
        </Link>

        <nav className="hidden items-center gap-1 md:flex">
          <Button variant="ghost" asChild className="text-slate-600">
            <Link to="/campos">Canchas</Link>
          </Button>
          <Button variant="ghost" asChild className="text-slate-600">
            <Link to="/estado" className="flex items-center gap-2">
              <ReceiptText className="h-4 w-4" />
              Mis comprobantes
            </Link>
          </Button>
          {ciudadano ? (
            <Button variant="ghost" asChild className="text-slate-600">
              <Link to="/mi-asociacion" className="flex items-center gap-2">
                <UserRound className="h-4 w-4" />
                Mi asociación
              </Link>
            </Button>
          ) : (
            <Button variant="ghost" asChild className="text-slate-600">
              <Link to="/login" className="flex items-center gap-2">
                <UserRound className="h-4 w-4" />
                Ingresar
              </Link>
            </Button>
          )}
          <Button
            asChild
            className="ml-2 rounded-full bg-emerald-600 px-5 hover:bg-emerald-700"
          >
            <Link to="/campos" className="flex items-center gap-2">
              <CalendarCheck className="h-4 w-4" />
              Reservar
            </Link>
          </Button>
        </nav>

        <Button
          variant="ghost"
          size="icon"
          className="md:hidden"
          aria-label={abierto ? 'Cerrar menú' : 'Abrir menú'}
          onClick={() => setAbierto((v) => !v)}
        >
          {abierto ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
        </Button>
      </div>

      {abierto && (
        <div className="border-t border-slate-100 bg-white px-4 pb-4 pt-2 md:hidden">
          <nav className="flex flex-col gap-1">
            <Button variant="ghost" asChild className="justify-start">
              <Link to="/campos" onClick={cerrar}>Canchas</Link>
            </Button>
            <Button variant="ghost" asChild className="justify-start">
              <Link to="/estado" onClick={cerrar} className="flex items-center gap-2">
                <ReceiptText className="h-4 w-4" />
                Mis comprobantes
              </Link>
            </Button>
            {ciudadano ? (
              <Button variant="ghost" asChild className="justify-start">
                <Link to="/mi-asociacion" onClick={cerrar} className="flex items-center gap-2">
                  <UserRound className="h-4 w-4" />
                  Mi asociación
                </Link>
              </Button>
            ) : (
              <Button variant="ghost" asChild className="justify-start">
                <Link to="/login" onClick={cerrar} className="flex items-center gap-2">
                  <UserRound className="h-4 w-4" />
                  Ingresar
                </Link>
              </Button>
            )}
            <Button
              asChild
              className="mt-2 rounded-full bg-emerald-600 hover:bg-emerald-700"
            >
              <Link to="/campos" onClick={cerrar} className="flex items-center gap-2">
                <CalendarCheck className="h-4 w-4" />
                Reservar ahora
              </Link>
            </Button>
          </nav>
        </div>
      )}
    </header>
  );
}