import { Link } from 'react-router-dom';
import { MapPinned, Mail } from 'lucide-react';

export default function Footer() {
  return (
    <footer className="bg-slate-900 text-slate-300">
      <div className="container mx-auto px-4 py-16 max-w-6xl">
        <div className="grid md:grid-cols-4 gap-10 mb-12">
          {/* Marca */}
          <div className="md:col-span-2">
            <div className="flex items-center gap-2 font-bold text-white mb-4">
              <div className="w-9 h-9 rounded-lg bg-teal-600 flex items-center justify-center">
                <MapPinned className="w-5 h-5" />
              </div>
              <span className="text-lg">Canchas GAD Beni</span>
            </div>
            <p className="text-sm leading-relaxed max-w-sm text-slate-400">
              Sistema oficial de reserva de campos deportivos del Gobierno
              Autónomo Departamental del Beni. Pago online seguro y comprobante
              digital al instante.
            </p>
            <div className="flex gap-3 mt-6">
              <a
                href="mailto:canchas@gadbeni.bo"
                className="w-9 h-9 rounded-lg bg-slate-800 hover:bg-teal-600 flex items-center justify-center transition-colors"
                aria-label="Email de contacto"
              >
                <Mail className="w-4 h-4" />
              </a>
              {/* SVG de Facebook inline (lucide no provee íconos de marca) */}
              <a
                href="#"
                className="w-9 h-9 rounded-lg bg-slate-800 hover:bg-teal-600 flex items-center justify-center transition-colors"
                aria-label="Facebook GAD Beni"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                  aria-hidden="true"
                >
                  <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z" />
                </svg>
              </a>
            </div>
          </div>

          {/* Enlaces */}
          <div>
            <h3 className="text-white font-semibold mb-4 text-sm uppercase tracking-wider">
              Sistema
            </h3>
            <ul className="space-y-3 text-sm">
              <li>
                <Link to="/campos" className="hover:text-teal-400 transition-colors">
                  Campos disponibles
                </Link>
              </li>
              <li>
                <Link to="/estado" className="hover:text-teal-400 transition-colors">
                  Consultar mi reserva
                </Link>
              </li>
            </ul>
          </div>

          {/* Legal */}
          <div>
            <h3 className="text-white font-semibold mb-4 text-sm uppercase tracking-wider">
              Legal
            </h3>
            <ul className="space-y-3 text-sm">
              <li>
                <a href="#" className="hover:text-teal-400 transition-colors">
                  Términos y condiciones
                </a>
              </li>
              <li>
                <a href="#" className="hover:text-teal-400 transition-colors">
                  Política de privacidad
                </a>
              </li>
              <li>
                <a href="#" className="hover:text-teal-400 transition-colors">
                  Accesibilidad
                </a>
              </li>
            </ul>
          </div>
        </div>

        {/* Copyright */}
        <div className="border-t border-slate-800 pt-8 flex flex-col md:flex-row items-center justify-between gap-3 text-sm text-slate-500">
          <p>
            © {new Date().getFullYear()} Gobierno Autónomo Departamental del
            Beni. Todos los derechos reservados.
          </p>
          <p className="flex items-center gap-2">
            Hecho con
            <span className="text-teal-500">●</span>
            en Trinidad, Bolivia
          </p>
        </div>
      </div>
    </footer>
  );
}
