import { Link } from 'react-router-dom';
import { MapPinned, Code2, Mail } from 'lucide-react';

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
                aria-label="Email"
              >
                <Mail className="w-4 h-4" />
              </a>
              <a
                href="#"
                className="w-9 h-9 rounded-lg bg-slate-800 hover:bg-teal-600 flex items-center justify-center transition-colors"
                aria-label="GitHub"
              >
                <Code2 className="w-4 h-4" />
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
                <Link
                  to="/campos"
                  className="hover:text-teal-400 transition-colors"
                >
                  Campos disponibles
                </Link>
              </li>
              <li>
                <Link
                  to="/estado"
                  className="hover:text-teal-400 transition-colors"
                >
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
