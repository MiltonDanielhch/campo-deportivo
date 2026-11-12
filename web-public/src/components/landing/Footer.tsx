import { Link } from 'react-router-dom';
import { MapPin, Phone, Clock, ShieldCheck } from 'lucide-react';

export default function Footer() {
  return (
    <footer className="bg-slate-950 text-slate-300 print:hidden">
      <div className="container mx-auto max-w-6xl px-4 py-14">
        <div className="grid gap-10 md:grid-cols-3">
          <div>
            <div className="flex items-center gap-2.5">
              <img
                src="/img/logo-gad-beni.png"
                alt="Logo GAD Beni"
                className="h-10 w-auto object-contain"
              />
              <span className="text-base font-bold text-white">Canchas GAD Beni</span>
            </div>
            <p className="mt-4 max-w-xs text-sm leading-relaxed text-slate-400">
              Sistema oficial de reserva de campos deportivos del Gobierno
              Autónomo Departamental del Beni. Pagá con QR o en ventanilla
              bancaria; tu comprobante digital queda guardado.
            </p>
            <p className="mt-4 flex items-center gap-2 text-xs text-slate-500">
              <ShieldCheck className="h-4 w-4 text-emerald-500" />
              Cobros gestionados por Paitití · SIREB
            </p>
          </div>

          <div>
            <h3 className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
              Explorar
            </h3>
            <ul className="mt-4 space-y-2.5 text-sm">
              <li>
                <Link to="/campos" className="transition-colors hover:text-emerald-400">
                  Canchas disponibles
                </Link>
              </li>
              <li>
                <Link to="/estado" className="transition-colors hover:text-emerald-400">
                  Consultar mi reserva
                </Link>
              </li>
              <li>
                <Link to="/registro" className="transition-colors hover:text-emerald-400">
                  Crear cuenta
                </Link>
              </li>
              <li>
                <Link to="/login" className="transition-colors hover:text-emerald-400">
                  Ingresar
                </Link>
              </li>
            </ul>
          </div>

          <div>
            <h3 className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
              Contacto
            </h3>
            <ul className="mt-4 space-y-3 text-sm text-slate-400">
              <li className="flex items-start gap-2.5">
                <MapPin className="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-500" />
                Av. Ganadera, Trinidad — Beni, Bolivia
              </li>
              <li className="flex items-start gap-2.5">
                <Phone className="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-500" />
                +591 3 462-XXXX
              </li>
              <li className="flex items-start gap-2.5">
                <Clock className="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-500" />
                Lunes a viernes · 08:00 — 18:00
              </li>
            </ul>
          </div>
        </div>

        <div className="mt-12 flex flex-col items-center justify-between gap-3 border-t border-slate-800 pt-6 text-xs text-slate-500 sm:flex-row">
          <p>
            © 2026 Gobierno Autónomo Departamental del Beni. Todos los derechos
            reservados.
          </p>
          <p>Hecho en Trinidad, Bolivia</p>
        </div>
      </div>
    </footer>
  );
}