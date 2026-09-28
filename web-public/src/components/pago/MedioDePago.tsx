import { useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { Button } from '@/components/ui/button';
import {
  ExternalLink,
  Copy,
  Check,
  Smartphone,
  ScanLine,
  BadgeCheck,
} from 'lucide-react';

interface DatosCobro {
  qr_string?: string | null;
  qr_image_base64?: string | null;
  checkout_url?: string | null;
}

interface MedioDePagoProps {
  datos: DatosCobro | null;
  monto: number;
}

export default function MedioDePago({ datos, monto }: MedioDePagoProps) {
  const [copiado, setCopiado] = useState(false);

  if (!datos) {
    return (
      <div className="text-center py-8 text-slate-500">
        <p>El sistema de cobro no devolvió un medio de pago.</p>
        <p className="text-xs mt-2">Recargá la página o contactá a soporte.</p>
      </div>
    );
  }

  const copiarCodigo = async () => {
    if (!datos.qr_string) return;
    try {
      await navigator.clipboard.writeText(datos.qr_string);
      setCopiado(true);
      setTimeout(() => setCopiado(false), 2000);
    } catch {
      /* ignorar */
    }
  };

  // Instrucciones paso a paso
  const pasos = [
    {
      num: 1,
      icon: Smartphone,
      texto: 'Abrí tu app de banca móvil',
      detalle: 'BNB, BCP, Mercantil, Unión, etc.',
    },
    {
      num: 2,
      icon: ScanLine,
      texto: 'Escaneá el QR',
      detalle: 'Usá la opción "Pagar con QR" de tu banco',
    },
    {
      num: 3,
      icon: BadgeCheck,
      texto: 'Confirmá el pago',
      detalle: `El monto Bs ${monto.toFixed(2)} se acreditará solo`,
    },
  ];

  // ─── QR string ───
  if (datos.qr_string) {
    return (
      <div className="space-y-6">
        {/* QR con marco decorativo */}
        <div className="flex justify-center">
          <div className="relative p-6 bg-white rounded-2xl shadow-lg shadow-slate-200/60">
            {/* Esquinas decorativas */}
            <div className="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-teal-600 rounded-tl-xl" />
            <div className="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-teal-600 rounded-tr-xl" />
            <div className="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-teal-600 rounded-bl-xl" />
            <div className="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-teal-600 rounded-br-xl" />

            <QRCodeSVG value={datos.qr_string} size={220} level="M" />
          </div>
        </div>

        {/* Código legible + botón copiar */}
        <div className="bg-slate-50 rounded-xl p-3 border border-slate-200">
          <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
            Código del QR
          </p>
          <div className="flex items-center gap-2">
            <code className="flex-1 text-xs font-mono text-slate-700 break-all line-clamp-2 leading-relaxed">
              {datos.qr_string}
            </code>
            <Button
              onClick={copiarCodigo}
              variant="outline"
              size="sm"
              className="flex-shrink-0"
            >
              {copiado ? (
                <>
                  <Check className="w-3.5 h-3.5 mr-1.5 text-emerald-600" />
                  Copiado
                </>
              ) : (
                <>
                  <Copy className="w-3.5 h-3.5 mr-1.5" />
                  Copiar
                </>
              )}
            </Button>
          </div>
        </div>

        {/* Instrucciones 1-2-3 */}
        <div>
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">
            Cómo pagar
          </p>
          <div className="space-y-2">
            {pasos.map((paso) => (
              <div
                key={paso.num}
                className="flex items-start gap-3 p-3 bg-white rounded-xl border border-slate-100"
              >
                <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center flex-shrink-0">
                  <span className="text-sm font-bold text-teal-700">
                    {paso.num}
                  </span>
                </div>
                <div className="min-w-0 flex-1">
                  <p className="font-semibold text-sm text-slate-900">
                    {paso.texto}
                  </p>
                  <p className="text-xs text-slate-500">{paso.detalle}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  // ─── Imagen base64 ───
  if (datos.qr_image_base64) {
    return (
      <div className="space-y-6">
        <div className="flex justify-center">
          <div className="relative p-6 bg-white rounded-2xl shadow-lg shadow-slate-200/60">
            <div className="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-teal-600 rounded-tl-xl" />
            <div className="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-teal-600 rounded-tr-xl" />
            <div className="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-teal-600 rounded-bl-xl" />
            <div className="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-teal-600 rounded-br-xl" />
            <img
              src={`data:image/png;base64,${datos.qr_image_base64}`}
              alt="QR de pago"
              className="w-[220px] h-[220px]"
            />
          </div>
        </div>

        <div>
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">
            Cómo pagar
          </p>
          <div className="space-y-2">
            {pasos.map((paso) => (
              <div
                key={paso.num}
                className="flex items-start gap-3 p-3 bg-white rounded-xl border border-slate-100"
              >
                <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center flex-shrink-0">
                  <span className="text-sm font-bold text-teal-700">
                    {paso.num}
                  </span>
                </div>
                <div className="min-w-0 flex-1">
                  <p className="font-semibold text-sm text-slate-900">
                    {paso.texto}
                  </p>
                  <p className="text-xs text-slate-500">{paso.detalle}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  // ─── URL de checkout ───
  if (datos.checkout_url) {
    return (
      <div className="text-center space-y-4 py-6">
        <div className="w-16 h-16 mx-auto rounded-2xl bg-teal-50 flex items-center justify-center">
          <ExternalLink className="w-8 h-8 text-teal-700" />
        </div>
        <p className="text-slate-700 font-medium">
          Vas a ser redirigido al checkout del Core de Recaudaciones
        </p>
        <Button
          size="lg"
          onClick={() => window.open(datos.checkout_url!, '_blank')}
          className="rounded-full"
        >
          <ExternalLink className="w-4 h-4 mr-2" />
          Pagar en el navegador
        </Button>
      </div>
    );
  }

  return (
    <div className="text-center py-8 text-slate-500">
      <p>El sistema de cobro no devolvió un medio de pago válido.</p>
    </div>
  );
}
