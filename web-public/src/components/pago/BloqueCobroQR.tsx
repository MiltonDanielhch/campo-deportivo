import { useRef, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import {
  Copy,
  Check,
  Download,
  Smartphone,
  ScanLine,
  MessageCircle,
  ExternalLink,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { construirWhatsappUrl, type CobroNormalizado } from '@/lib/cobro';

interface BloqueCobroQRProps {
  cobro: CobroNormalizado;
  codigo: string;
  monto: number;
  codigoSeguimiento: string;
  /** Base del panel SIREB; si viene, se muestra el link "Verificar en SIREB". */
  sirebBaseUrl?: string;
}

/**
 * [S16] El bloque de cobro (QR + Descargar + WhatsApp + código de ventanilla +
 * caja "¿Estás en el celular?") extraído pa' reusarse en línea (ConsultarEstado)
 * sin obligar a navegar a Pago. La regla de cobro/wa.me viene de lib/cobro.ts
 * (fuente única). El link SIREB es opt-in (sirebBaseUrl): Pago lo pasa, la
 * consulta en línea no (el polling/manual-refresh lo reemplaza).
 */
export default function BloqueCobroQR({
  cobro,
  codigo,
  monto,
  codigoSeguimiento,
  sirebBaseUrl,
}: BloqueCobroQRProps) {
  const [copiadoCodigo, setCopiadoCodigo] = useState(false);
  const [copiadoLink, setCopiadoLink] = useState(false);
  const qrRef = useRef<HTMLDivElement>(null);

  const whatsappShareUrl = construirWhatsappUrl(codigoSeguimiento, monto, cobro);

  const copiarCodigo = () => {
    navigator.clipboard.writeText(cobro.codigoPublico);
    setCopiadoCodigo(true);
    setTimeout(() => setCopiadoCodigo(false), 2000);
  };
  const copiarLink = () => {
    navigator.clipboard.writeText(cobro.qrString);
    setCopiadoLink(true);
    setTimeout(() => setCopiadoLink(false), 2000);
  };
  const descargarQR = () => {
    if (!qrRef.current) return;
    const svg = qrRef.current.querySelector('svg');
    if (!svg) return;
    const svgData = new XMLSerializer().serializeToString(svg);
    const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
    const svgUrl = URL.createObjectURL(svgBlob);
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    img.onload = () => {
      canvas.width = 1024;
      canvas.height = 1024;
      ctx?.drawImage(img, 0, 0, 1024, 1024);
      canvas.toBlob(
        (blob) => {
          if (!blob) return;
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `qr-pago-${codigo}.png`;
          a.click();
          URL.revokeObjectURL(url);
        },
        'image/png',
      );
      URL.revokeObjectURL(svgUrl);
    };
    img.src = svgUrl;
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-center" ref={qrRef}>
        <div className="relative p-6 bg-white rounded-2xl shadow-lg shadow-slate-200/60">
          <div className="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-teal-600 rounded-tl-xl" />
          <div className="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-teal-600 rounded-tr-xl" />
          <div className="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-teal-600 rounded-bl-xl" />
          <div className="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-teal-600 rounded-br-xl" />
          <QRCodeSVG value={cobro.qrString} size={220} level="H" />
        </div>
      </div>

      <div className="flex items-center justify-center gap-2">
        <Button variant="outline" size="sm" onClick={descargarQR} className="rounded-full">
          <Download className="w-4 h-4 mr-1.5" />
          Descargar QR
        </Button>
        <a
          href={whatsappShareUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1.5 px-4 h-9 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-sm font-medium text-slate-700 transition-colors"
        >
          <MessageCircle className="w-4 h-4 text-emerald-600" />
          Enviar por WhatsApp
        </a>
      </div>

      <div className="text-center space-y-3">
        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
          Código de pago en ventanilla
        </p>
        <div className="flex items-center justify-center gap-3">
          <p className="text-2xl md:text-3xl font-mono font-bold text-slate-900 tracking-widest">
            {cobro.codigoPublico}
          </p>
          <Button
            variant="ghost"
            size="icon"
            onClick={copiarCodigo}
            className="h-9 w-9 text-slate-500 hover:text-teal-600"
            title="Copiar código"
          >
            {copiadoCodigo ? (
              <Check className="w-4 h-4 text-green-500" />
            ) : (
              <Copy className="w-4 h-4" />
            )}
          </Button>
        </div>
        <p className="text-sm text-slate-600 max-w-sm mx-auto">
          Presentá este código o escaneá el QR en ventanilla del{' '}
          <strong>Banco Unión</strong> junto con tu CI para pagar tu reserva.
        </p>
      </div>

      <div className="bg-blue-50/60 border border-blue-200 rounded-xl p-4">
        <div className="flex items-start gap-2.5 mb-3">
          <Smartphone className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
          <p className="text-sm font-semibold text-slate-900">¿Estás en el celular?</p>
        </div>
        <p className="text-sm text-slate-600 mb-3">
          No podés escanear un QR de tu propia pantalla. Usá una de estas opciones:
        </p>
        <div className="space-y-2">
          <button
            onClick={copiarLink}
            className="w-full flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-teal-50/30 transition-colors text-left"
          >
            <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center flex-shrink-0">
              {copiadoLink ? (
                <Check className="w-4 h-4 text-green-600" />
              ) : (
                <Copy className="w-4 h-4 text-teal-600" />
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-semibold text-slate-900">
                {copiadoLink ? '¡Copiado!' : 'Copiar link de pago'}
              </p>
              <p className="text-xs text-slate-500">
                Pegalo en la app de tu banco para pagar desde ahí.
              </p>
            </div>
          </button>

          <div className="flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-200">
            <div className="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
              <ScanLine className="w-4 h-4 text-slate-500" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-semibold text-slate-900">
                Escanear desde otro dispositivo
              </p>
              <p className="text-xs text-slate-500">
                Abrí esta página en la compu y escaneá con el celu.
              </p>
            </div>
          </div>

          <a
            href={whatsappShareUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/30 transition-colors"
          >
            <div className="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
              <MessageCircle className="w-4 h-4 text-emerald-600" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-semibold text-slate-900">Enviar por WhatsApp</p>
              <p className="text-xs text-slate-500">
                Compartilo con vos mismo o con alguien que pueda pagar.
              </p>
            </div>
          </a>
        </div>
      </div>

      {sirebBaseUrl && (
        <div className="pt-4 border-t border-slate-100 text-center">
          <a
            href={`${sirebBaseUrl}/${cobro.codigoPublico}`}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 text-sm text-teal-600 hover:text-teal-700 font-medium"
          >
            Verificar estado en el panel oficial de SIREB
            <ExternalLink className="w-3.5 h-3.5" />
          </a>
        </div>
      )}
    </div>
  );
}
